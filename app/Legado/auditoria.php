<?php
/**
 * Auditoria — tudo o que acontece no sistema, pra responder "o que foi, como
 * foi e quem foi". Só o suporte da Sami vê (tela /admin/auditoria, os mesmos
 * e-mails da senha do dia — require_suporte_sami()).
 *
 *   auditoria_requisicoes  cada ação de quem usa o sistema (imobiliária, MPS,
 *                          embed, painel): quem (e se entrou com a senha do
 *                          dia), IP, navegador, URL, Controller@método, os dados
 *                          enviados (senhas/cartões mascarados) e o resultado.
 *                          Comandos automáticos (cron) entram quando mudam dado.
 *   auditoria_alteracoes   cada INSERT/UPDATE/DELETE que mudou o banco, com o
 *                          antes e o depois por campo.
 *
 * Como captura: todas as gravações passam por db_execute()/db_query()
 * (nucleo.php), que chamam auditoria_antes() antes e auditoria_escrita()
 * depois. Na web, o middleware RegistrarAuditoria abre o contexto e grava tudo
 * no fim (inclusive o que roda depois da resposta, nos terminating). No
 * console, cada alteração é gravada na hora, num registro por comando.
 *
 * Escrita que não mudou nada (UPDATE sem efeito, upsert igual) não entra — o
 * cron consulta a Segimob a cada minuto e só o que muda interessa.
 */

use Illuminate\Support\Facades\DB;

/** Tabelas que não entram (a própria auditoria e infraestrutura do Laravel). */
const AUDITORIA_TABELAS_IGNORADAS = [
    'auditoria_requisicoes', 'auditoria_alteracoes',
    'sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs',
];

/**
 * Datas de controle que mudam a cada gravação (o cron regrava "atualizado em"
 * a cada consulta): alteração só nelas não entra na auditoria.
 */
const AUDITORIA_CAMPOS_CONTROLE = ['atualizado_em', 'updated_at', 'data_atualizacao', 'respondido_em', 'consultado_em'];

/** Campos mascarados nos dados enviados e no antes/depois. */
const AUDITORIA_CAMPOS_SENSIVEIS = '/senha|password|token|secret|cvv|cartao|card|credencial|api_?key/i';

/** Estado da requisição (ou do comando) atual. */
function &auditoria_estado(): array
{
    static $estado = [
        'contexto'    => null,   // web: aberto pelo middleware
        'escritas'    => [],     // web: gravadas no fim
        'gravando'    => false,  // evita auditar a própria gravação
        'console_id'  => null,   // console: registro do comando (criado na 1ª alteração)
    ];
    return $estado;
}

/** Mascara campos sensíveis e encurta textos longos (recursivo). */
function auditoria_mascarar(mixed $dados, string $chave = ''): mixed
{
    if ($chave !== '' && preg_match(AUDITORIA_CAMPOS_SENSIVEIS, $chave)) {
        return $dados === null || $dados === '' ? $dados : '***';
    }
    if (is_array($dados)) {
        $saida = [];
        foreach ($dados as $k => $v) {
            $saida[$k] = auditoria_mascarar($v, (string) $k);
        }
        return $saida;
    }
    if ($dados instanceof \Illuminate\Http\UploadedFile) {
        return '[arquivo: ' . $dados->getClientOriginalName() . ']';
    }
    if (is_string($dados) && mb_strlen($dados) > 2000) {
        return mb_substr($dados, 0, 2000) . '…';
    }
    return $dados;
}

/** JSON compacto (com limite de tamanho) pra gravar. */
function auditoria_json(mixed $dados, int $limite = 60000): ?string
{
    if ($dados === null || $dados === []) {
        return null;
    }
    $json = json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR);
    return $json === false ? null : (strlen($json) > $limite ? substr($json, 0, $limite) . '…' : $json);
}

/**
 * Lê o SQL de escrita: operação, tabela e o WHERE (com quantos parâmetros
 * ele usa — são os últimos). null = não é escrita (ou tabela ignorada).
 *
 * @return array{operacao: string, tabela: string, where: ?string, n_where: int}|null
 */
function auditoria_analisar_sql(string $sql): ?array
{
    $sql = trim($sql);
    if (preg_match('/^(INSERT|REPLACE)\s+(?:IGNORE\s+)?INTO\s+`?(\w+)`?/i', $sql, $m)) {
        $info = ['operacao' => 'INSERT', 'tabela' => $m[2], 'where' => null, 'n_where' => 0];
    } elseif (preg_match('/^UPDATE\s+`?(\w+)`?(\s+\w+)?\s+(JOIN|INNER|LEFT|RIGHT|,)?/i', $sql, $m)) {
        // UPDATE com JOIN: sem foto do antes (o WHERE depende da outra tabela).
        $where = empty($m[3]) && preg_match('/\sWHERE\s(.+)$/is', $sql, $w) ? $w[1] : null;
        $info  = ['operacao' => 'UPDATE', 'tabela' => $m[1], 'where' => $where, 'n_where' => $where ? substr_count($where, '?') : 0];
    } elseif (preg_match('/^DELETE\s+FROM\s+`?(\w+)`?\s+WHERE\s(.+)$/is', $sql, $m)) {
        $info = ['operacao' => 'DELETE', 'tabela' => $m[1], 'where' => $m[2], 'n_where' => substr_count($m[2], '?')];
    } else {
        return null;
    }
    return in_array(strtolower($info['tabela']), AUDITORIA_TABELAS_IGNORADAS, true) ? null : $info;
}

/**
 * Foto do antes de um UPDATE/DELETE (até 200 linhas), pra comparar depois.
 *
 * @return array{info: array, linhas: list<array>}|null
 */
function auditoria_antes(string $sql, array $valores): ?array
{
    $estado = &auditoria_estado();
    $info   = auditoria_analisar_sql($sql);
    if ($info === null || $estado['gravando']) {
        return $info === null ? null : ['info' => $info, 'linhas' => []];
    }
    if ($info['operacao'] === 'INSERT') {
        return ['info' => $info, 'linhas' => auditoria_antes_upsert($sql, $info['tabela'], $valores)];
    }
    if ($info['where'] === null) {
        return ['info' => $info, 'linhas' => []];
    }
    try {
        // O WHERE sem ORDER BY/LIMIT finais; os parâmetros dele são os últimos.
        $where = preg_replace('/\s+(ORDER\s+BY\s+.+|LIMIT\s+\S+)\s*$/is', '', $info['where']);
        $params = $info['n_where'] > 0 ? array_slice($valores, -$info['n_where']) : [];
        if (substr_count($where, '?') !== count($params)) {
            return ['info' => $info, 'linhas' => []];
        }
        $linhas = array_map(static fn($r) => (array) $r, DB::select("SELECT * FROM `{$info['tabela']}` WHERE {$where} LIMIT 200", $params));
        return ['info' => $info, 'linhas' => $linhas];
    } catch (\Throwable) {
        return ['info' => $info, 'linhas' => []];
    }
}

/**
 * "INSERT ... ON DUPLICATE KEY UPDATE": a linha que pode ser atualizada, pela
 * chave única da tabela (a primeira cujas colunas vêm todas com "?" no INSERT).
 * Sem isso o upsert não teria antes/depois. [] = sem como achar (ou é INSERT simples).
 */
function auditoria_antes_upsert(string $sql, string $tabela, array $valores): array
{
    if (!preg_match('/^\s*INSERT\s+(?:IGNORE\s+)?INTO\s+`?\w+`?\s*\(([^)]*)\)\s*VALUES\s*\((.*?)\)\s*ON\s+DUPLICATE\s+KEY\s+UPDATE/is', $sql, $m)) {
        return [];
    }
    try {
        // Valor de cada coluna (sem casar colunas e valores, melhor sem antes do que um antes errado).
        $porCol = auditoria_valores_insert($sql, $valores);
        if ($porCol === null) {
            return [];
        }

        static $chaves = [];
        $chaves[$tabela] ??= (function () use ($tabela) {
            $porChave = [];
            foreach (DB::select("SHOW INDEX FROM `{$tabela}` WHERE Non_unique = 0") as $idx) {
                $porChave[$idx->Key_name][(int) $idx->Seq_in_index] = $idx->Column_name;
            }
            return $porChave;
        })();

        foreach ($chaves[$tabela] as $cols) {
            ksort($cols);
            if (array_diff($cols, array_keys($porCol)) !== []) continue;
            $where  = implode(' AND ', array_map(static fn($c) => "`{$c}` = ?", $cols));
            $params = array_map(static fn($c) => $porCol[$c], array_values($cols));
            return array_map(static fn($r) => (array) $r, DB::select("SELECT * FROM `{$tabela}` WHERE {$where} LIMIT 1", $params));
        }
    } catch (\Throwable) {
    }
    return [];
}

/**
 * Coluna => valor de um INSERT de uma linha (só as colunas com "?"; NOW() e
 * afins ficam de fora). null = não deu pra casar colunas e valores.
 */
function auditoria_valores_insert(string $sql, array $valores): ?array
{
    if (!preg_match('/^\s*(?:INSERT|REPLACE)\s+(?:IGNORE\s+)?INTO\s+`?\w+`?\s*\(([^)]*)\)\s*VALUES\s*\((.*?)\)\s*(?:ON\s+DUPLICATE|$)/is', trim($sql), $m)) {
        return null;
    }
    $colunas = array_map(static fn($c) => trim($c, " `\t\n\r"), explode(',', $m[1]));
    $itens   = array_map('trim', explode(',', $m[2]));
    if (count($itens) !== count($colunas)) {
        return null;
    }
    $linha = [];
    $p     = 0;
    foreach ($itens as $i => $item) {
        if ($item === '?') {
            $linha[$colunas[$i]] = $valores[$p++] ?? null;
        } else {
            $p += substr_count($item, '?');
        }
    }
    return $linha;
}

/** Colunas da chave primária da tabela (cache por requisição); [] = sem chave. */
function auditoria_chave_primaria(string $tabela): array
{
    static $cache = [];
    if (!isset($cache[$tabela])) {
        $cols = [];
        try {
            foreach (DB::select("SHOW INDEX FROM `{$tabela}` WHERE Key_name = 'PRIMARY'") as $idx) {
                $cols[(int) $idx->Seq_in_index] = $idx->Column_name;
            }
            ksort($cols);
        } catch (\Throwable) {
        }
        $cache[$tabela] = array_values($cols);
    }
    return $cache[$tabela];
}

/** Identificação de uma linha pela chave primária ("43" ou "1026/4"). */
function auditoria_id_linha(array $linha, array $chave): string
{
    return implode('/', array_map(static fn($c) => (string) ($linha[$c] ?? ''), $chave));
}

/**
 * Registra uma escrita que mudou o banco: o que mudou campo a campo (UPDATE),
 * a linha criada (INSERT) ou apagada (DELETE). Nunca lança exceção.
 */
function auditoria_escrita(string $sql, array $valores, int $afetadas, ?array $antes, ?int $idInserido): void
{
    $estado = &auditoria_estado();
    if ($antes === null || $afetadas <= 0 || $estado['gravando']) {
        return;
    }
    try {
        $info     = $antes['info'];
        $tabela   = $info['tabela'];
        $mudancas = null;
        $registro = null;

        // Upsert que caiu numa linha existente: é uma atualização (com antes/depois).
        if ($info['operacao'] === 'INSERT' && $afetadas === 2 && preg_match('/ON\s+DUPLICATE\s+KEY\s+UPDATE/i', $sql)) {
            $info['operacao'] = $antes['linhas'] !== [] ? 'UPDATE' : 'UPSERT';
            $afetadas = 1;
        }

        $chave = auditoria_chave_primaria($tabela);
        if ($info['operacao'] === 'UPDATE' && $antes['linhas'] !== [] && $chave !== [] && array_key_exists($chave[0], $antes['linhas'][0])) {
            // Depois: as mesmas linhas, pela chave primária (o WHERE pode ter mudado).
            $cond   = '(' . implode(' AND ', array_map(static fn($c) => "`{$c}` = ?", $chave)) . ')';
            $params = [];
            foreach ($antes['linhas'] as $linha) {
                foreach ($chave as $c) $params[] = $linha[$c];
            }
            $depois = [];
            foreach (DB::select("SELECT * FROM `{$tabela}` WHERE " . implode(' OR ', array_fill(0, count($antes['linhas']), $cond)), $params) as $r) {
                $depois[auditoria_id_linha((array) $r, $chave)] = (array) $r;
            }
            foreach ($antes['linhas'] as $linha) {
                $id   = auditoria_id_linha($linha, $chave);
                $novo = $depois[$id] ?? null;
                if ($novo === null) continue;
                foreach ($linha as $campo => $valor) {
                    if ((string) $valor !== (string) ($novo[$campo] ?? '') && !in_array($campo, AUDITORIA_CAMPOS_CONTROLE, true)) {
                        $mudancas[$id][$campo] = [
                            auditoria_mascarar($valor, $campo),
                            auditoria_mascarar($novo[$campo] ?? null, $campo),
                        ];
                    }
                }
            }
            $registro = count($antes['linhas']) === 1 ? auditoria_id_linha($antes['linhas'][0], $chave) : null;
            if ($mudancas === null) {
                return;   // só mudaram datas de controle (ou nada): não entra
            }
        } elseif ($info['operacao'] === 'DELETE') {
            $mudancas = auditoria_mascarar($antes['linhas']);
            $registro = count($antes['linhas']) === 1 && $chave !== [] ? auditoria_id_linha($antes['linhas'][0], $chave) : null;
        } elseif (in_array($info['operacao'], ['INSERT', 'UPSERT'], true)) {
            // UPSERT = upsert sem foto do antes (tabela sem chave única reconhecível).
            if ($idInserido) {
                $registro = (string) $idInserido;
                $linha    = DB::selectOne("SELECT * FROM `{$tabela}` WHERE id = ?", [$idInserido]);
                $mudancas = $linha ? auditoria_mascarar((array) $linha) : null;
            } elseif (($linha = auditoria_valores_insert($sql, $valores)) !== null) {
                // Tabela sem id (chave composta): a linha pelos valores do INSERT.
                $mudancas = auditoria_mascarar($linha);
                if ($chave !== [] && array_diff($chave, array_keys($linha)) === []) {
                    $registro = auditoria_id_linha($linha, $chave);
                }
            }
        }

        $escrita = [
            'criado_em'   => auditoria_agora(),
            'tabela'      => $tabela,
            'operacao'    => $info['operacao'],
            'registro_id' => $registro,
            'linhas'      => $afetadas,
            'mudancas'    => auditoria_json($mudancas),
            'sql_texto'   => mb_substr(preg_replace('/\s+/', ' ', trim($sql)), 0, 4000),
            'valores'     => auditoria_json(auditoria_mascarar_valores($sql, $valores), 8000),
        ];

        if ($estado['contexto'] !== null) {
            $estado['escritas'][] = $escrita;     // web: grava no fim da requisição
        } elseif (app()->runningInConsole()) {
            auditoria_gravar_console($escrita);   // console: grava na hora
        }
    } catch (\Throwable $e) {
        error_log('[auditoria] ' . $e->getMessage());
    }
}

/**
 * Valores do SQL pra gravar. Se o SQL mexe em alguma coluna sensível (senha,
 * token, cartão...), todos os valores dele são mascarados — mais seguro do
 * que adivinhar qual "?" é de qual coluna. O antes/depois por campo continua
 * (mascarado pelo nome da coluna).
 */
function auditoria_mascarar_valores(string $sql, array $valores): array
{
    if (preg_match(AUDITORIA_CAMPOS_SENSIVEIS, $sql)) {
        return array_map(static fn($v) => $v === null ? null : '***', $valores);
    }
    return array_map(static fn($v) => is_string($v) && mb_strlen($v) > 500 ? mb_substr($v, 0, 500) . '…' : $v, $valores);
}

/** Data/hora com milissegundos. */
function auditoria_agora(): string
{
    return DateTime::createFromFormat('U.u', sprintf('%.6F', microtime(true)))
        ->setTimezone(new DateTimeZone(date_default_timezone_get()))
        ->format('Y-m-d H:i:s.v');
}

/** Grava sem passar pela auditoria (a própria auditoria não se audita). */
function auditoria_inserir(string $tabela, array $dados): int
{
    $estado = &auditoria_estado();
    $estado['gravando'] = true;
    try {
        $colunas = array_keys($dados);
        DB::insert(
            "INSERT INTO {$tabela} (" . implode(', ', $colunas) . ') VALUES (' . implode(', ', array_fill(0, count($colunas), '?')) . ')',
            array_values($dados)
        );
        return (int) DB::getPdo()->lastInsertId();
    } finally {
        $estado['gravando'] = false;
    }
}

// ─── Web ──────────────────────────────────────────────────────────────────────

/** Abre o contexto da requisição (middleware RegistrarAuditoria, no começo). */
function auditoria_abrir(array $contexto): void
{
    $estado = &auditoria_estado();
    $estado['contexto'] = $contexto;
    $estado['escritas'] = [];
}

/**
 * Fecha a requisição: grava o registro dela e as alterações. $registrar =
 * false pula requisição de leitura sem alteração (o middleware decide).
 */
function auditoria_fechar(array $complemento, bool $registrar): void
{
    $estado   = &auditoria_estado();
    $contexto = $estado['contexto'];
    $escritas = $estado['escritas'];
    $estado['contexto'] = null;
    $estado['escritas'] = [];
    if ($contexto === null || (!$registrar && $escritas === [])) {
        return;
    }
    try {
        $id = auditoria_inserir('auditoria_requisicoes', array_merge($contexto, $complemento, ['alteracoes' => count($escritas)]));
        foreach ($escritas as $escrita) {
            auditoria_inserir('auditoria_alteracoes', ['requisicao_id' => $id] + $escrita);
        }
    } catch (\Throwable $e) {
        error_log('[auditoria] Falha ao gravar: ' . $e->getMessage());
    }
}

// ─── Console (cron, comandos) ────────────────────────────────────────────────

/** Console: grava a alteração na hora, no registro do comando (criado na primeira). */
function auditoria_gravar_console(array $escrita): void
{
    $estado = &auditoria_estado();
    try {
        if ($estado['console_id'] === null) {
            $argv = array_slice($_SERVER['argv'] ?? [], 1);
            $estado['console_id'] = auditoria_inserir('auditoria_requisicoes', [
                'criado_em' => auditoria_agora(),
                'canal'     => 'console',
                'ator_tipo' => 'sistema',
                'ator_nome' => 'Sistema (automático)',
                'metodo'    => 'CLI',
                'url'       => mb_substr('artisan ' . implode(' ', $argv), 0, 500),
                'acao'      => mb_substr((string) ($argv[0] ?? 'console'), 0, 160),
            ]);
        }
        auditoria_inserir('auditoria_alteracoes', ['requisicao_id' => $estado['console_id']] + $escrita);
        $estado['gravando'] = true;
        DB::update('UPDATE auditoria_requisicoes SET alteracoes = alteracoes + 1 WHERE id = ?', [$estado['console_id']]);
    } catch (\Throwable $e) {
        error_log('[auditoria] Falha ao gravar (console): ' . $e->getMessage());
    } finally {
        $estado['gravando'] = false;
    }
}
