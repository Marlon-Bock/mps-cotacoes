<?php

namespace App\Http\Controllers\Produtos;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/**
 * Rascunhos de cotação (fiança, auto, título, condominial, incêndio): listagem unificada
 * + cotações em pré-análise, salvar e excluir.
 * Era pages/cotacao/rascunhos.php e includes/acao/cotacao-rascunho-{salvar,excluir}.php.
 */
class RascunhosController extends Controller
{
    /**
     * GET /cotacao/rascunhos
     * Lista unificada de todos os rascunhos salvos (fiança, auto, título, condominial…)
     * e cotações em pré-análise (Aguardando liberação).
     */
    public function index()
    {
        /**
         * GET /cotacao/rascunhos
         * Lista unificada de todos os rascunhos salvos (fiança, auto, título, condominial…)
         * e cotações em pré-análise (Aguardando liberação).
         */

        $current_user = require_perm('perm_cotar');

        $imobiliaria_id = (int) ($current_user->imobiliaria_id ?? 0);
        $usuario_id = (int) ($current_user->uid ?? 0);
        $is_admin_imob = !empty($current_user->imob_admin);
        $tem_perm = fn(string $perm): bool => $is_admin_imob || ($current_user->{$perm} ?? 'N') === 'S';

        // ─── Configuração de produtos com rascunho ──────────────────────────────────
        // Para adicionar um novo produto: inclua uma entrada neste array.
        // tabela, colunas_select, col_nome (campo descritivo), url_continuar, icon, label.
        $produtos = [
            'fianca' => [
                'label' => 'Fiança',
                'icon' => 'clipboard-text',
                'perm' => 'perm_fianca',
                'tabela' => 'seguro_fianca_rascunho',
                'col_updated' => 'atualizado_em',
                'url_fn' => fn(array $r): string =>
                    '/cotacao/fianca/' . (strtolower($r['tipo_ficha'] ?? '') === 'comercial' ? 'comercial' : 'residencial')
                    . '?rascunho=' . urlencode($r['protocolo']),
                'label_fn' => fn(array $r): string =>
                    'Fiança ' . (strtolower($r['tipo_ficha'] ?? '') === 'comercial' ? 'Comercial' : 'Residencial'),
                'detalhe_fn' => fn(array $r): string => trim((string) ($r['nome_completo'] ?? '')) ?: '—',
            ],
            'auto' => [
                'label' => 'Auto',
                'icon' => 'car',
                'perm' => 'perm_auto',
                'tabela' => 'seguro_auto_rascunho',
                'col_updated' => 'updated_at',
                'url_fn' => fn(array $r): string => '/cotacao/auto?rascunho=' . urlencode($r['protocolo']),
                'detalhe_fn' => fn(array $r): string => trim(
                    ($r['marca_modelo'] ?? '') .
                    ($r['ano_modelo'] ? ' · ' . $r['ano_modelo'] : '') .
                    ($r['placa'] ? ' · ' . strtoupper($r['placa']) : '')
                ),
            ],
            'titulo' => [
                'label' => 'Título de Cap.',
                'icon' => 'piggy-bank',
                'perm' => 'perm_titulo',
                'tabela' => 'seguro_titulo_rascunho',
                'col_updated' => 'updated_at',
                'url_fn' => fn(array $r): string => '/cotacao/titulo?rascunho=' . urlencode($r['protocolo']),
                'detalhe_fn' => fn(array $r): string => trim(
                    ($r['finalidade'] ?? '') . ($r['inq_nome'] ? ' · ' . $r['inq_nome'] : '')
                ) ?: '—',
            ],
            'condominial' => [
                'label' => 'Condominial',
                'icon' => 'buildings',
                'perm' => 'perm_condo',
                'tabela' => 'seguro_condominial_rascunho',
                'col_updated' => 'updated_at',
                'url_fn' => fn(array $r): string => '/cotacao/condominial?rascunho=' . urlencode($r['protocolo']),
                'detalhe_fn' => fn(array $r): string => trim(
                    ($r['imovel_nome'] ?? '') . ($r['imovel_endereco'] ? ' · ' . $r['imovel_endereco'] : '')
                ) ?: '—',
            ],
            'incendio' => [
                'label' => 'Incêndio',
                'icon' => 'fire',
                'perm' => 'perm_incendio',
                'tabela' => 'seguro_incendio_rascunho',
                'col_updated' => 'updated_at',
                'url_fn' => fn(array $r): string => '/cotacao/incendio?rascunho=' . urlencode($r['protocolo']),
                'detalhe_fn' => function (array $r): string {
                    $imoveis  = json_decode((string) ($r['imoveis_json'] ?? ''), true) ?: [];
                    $qtd      = count($imoveis);
                    $primeiro = $imoveis[0] ?? [];
                    $resumo   = trim(($primeiro['locatario'] ?? '') . (($primeiro['endereco'] ?? '') !== '' ? ' · ' . $primeiro['endereco'] : ''));
                    return trim(($qtd > 1 ? "{$qtd} imóveis" : '') . ($qtd > 1 && $resumo !== '' ? ' · ' : '') . $resumo) ?: '—';
                },
            ],
        ];

        // ─── Busca unificada de rascunhos ───────────────────────────────────────────
        $rascunhos = [];

        foreach ($produtos as $tipo => $cfg) {
            if (!$tem_perm($cfg['perm'])) {
                continue;
            }
            try {
                $colUpdated = $cfg['col_updated'];
                if ($is_admin_imob) {
                    $rows = db_fetch_all(
                        "SELECT * FROM {$cfg['tabela']}
                          WHERE imobiliaria_id = ?
                       ORDER BY {$colUpdated} DESC",
                        ['i', $imobiliaria_id]
                    );
                } else {
                    $rows = db_fetch_all(
                        "SELECT * FROM {$cfg['tabela']}
                          WHERE imobiliaria_id = ? AND usuario_id = ?
                       ORDER BY {$colUpdated} DESC",
                        ['ii', $imobiliaria_id, $usuario_id]
                    );
                }

                foreach ($rows as $row) {
                    $rascunhos[] = [
                        'tipo' => $tipo,
                        'label' => isset($cfg['label_fn']) ? ($cfg['label_fn'])($row) : $cfg['label'],
                        'icon' => $cfg['icon'],
                        'id' => $row['id'],
                        'protocolo' => $row['protocolo'] ?? null,
                        'nome_rascunho' => $row['nome_rascunho'] ?? '',
                        'detalhe' => ($cfg['detalhe_fn'])($row),
                        'url' => ($cfg['url_fn'])($row),
                        'updated_at' => $row[$colUpdated] ?? '',
                    ];
                }
            } catch (\Throwable $e) {
                error_log("[rascunhos] Erro ao ler {$cfg['tabela']}: " . $e->getMessage());
            }
        }

        // Ordena todos os rascunhos por data de atualização (mais recente primeiro)
        usort($rascunhos, fn($a, $b) => strcmp($b['updated_at'], $a['updated_at']));

        // ─── Cotações em pré-análise (Aguardando liberação) ─────────────────────────
        // Mesmos status reconhecidos em cotacoes-listar.php como "pendentes de ação da imobiliária".
        $pre_analise_statuses = [
            'Aguardando liberação',
            'Aguardando liberacao',
            'Aguardando preenchimento',
            'Pré-análise aceita',
            'Pre-analise aceita',
            'Pré-análise recusada',
            'Pre-analise recusada',
        ];

        // Operador comum só vê as próprias cotações (mesma regra de
        // includes/acao/cotacoes-listar.php); admin da imobiliária vê tudo.
        $pre_analise = [];
        if ($tem_perm('perm_fianca')) {
            try {
                $placeholders = implode(',', array_fill(0, count($pre_analise_statuses), '?'));
                $sql_pre_analise = "SELECT sf.id AS cotacao_id,
                        sf.protocolo,
                        sf.tipo_ficha,
                        COALESCE(NULLIF(sf.nome_completo,''),'—') AS pretendente,
                        sf.status,
                        COALESCE(sf.data_atualizacao, sf.data_cadastro) AS atualizado_em,
                        sf.data_cadastro AS data_criacao,
                        ua.username AS atendente_nome,
                        ua.avatar AS atendente_avatar
                   FROM seguro_fianca sf
                   LEFT JOIN usuario ua ON ua.id = sf.atendente_id
                  WHERE sf.imobiliaria_id = ? AND sf.status IN ({$placeholders})"
                    . (!$is_admin_imob ? " AND sf.usuario_id = ?" : "")
                    . " ORDER BY sf.data_cadastro DESC";
                $bind = array_merge(['i' . str_repeat('s', count($pre_analise_statuses)), $imobiliaria_id], $pre_analise_statuses);
                if (!$is_admin_imob) {
                    $bind[0] .= 'i';
                    $bind[] = $usuario_id;
                }
                $pre_analise = db_fetch_all($sql_pre_analise, $bind);
            } catch (\Throwable $e) {
                $pre_analise = [];
            }
        }

        $count_rascunhos = count($rascunhos);
        $count_pre = count($pre_analise);

        // ─── Layout ─────────────────────────────────────────────────────────────────
        $page_title = 'Rascunhos';
        $page_heading = 'Rascunhos';
        $page_subheading = 'Cotações salvas e aguardando liberação';
        $page_back = null;
        $extra_css = ['dashboard.css', 'cotacoes.css'];
        $extra_js = [];

        return view('produtos.rascunhos.index', get_defined_vars());
    }

    /**
     * POST /api/cotacao/rascunho/salvar
     * Salva (insere ou atualiza) um rascunho de cotação — fiança, auto, título, condominial ou incêndio.
     * Aceita FormData com: tipo, nome_rascunho, rascunho (protocolo, opcional) + campos do produto.
     * Respostas com json_encode padrão (sem JSON_UNESCAPED_UNICODE), como o echo do antigo.
     */
    public function salvar(Request $request)
    {
        $current_user = require_auth();

        if (($current_user->role ?? '') !== 'imobiliaria') {
            return response()->json(['ok' => false, 'message' => 'Apenas imobiliárias podem salvar rascunhos.']);
        }

        $post = $request->post();

        $tipo = trim((string) ($post['tipo'] ?? ''));
        $cfg  = rascunho_config($tipo);
        if (!$cfg) {
            return response()->json(['ok' => false, 'message' => 'Tipo de cotação inválido.']);
        }

        $nome_rascunho = trim((string) ($post['nome_rascunho'] ?? ''));
        if ($nome_rascunho === '') {
            $nome_rascunho = 'Rascunho sem nome';
        }

        $rascunho_protocolo = trim((string) ($post['rascunho'] ?? ''));

        // Se veio de uma cotação em pré-análise (fluxo "completar cotação"), vincula o
        // protocolo real ao rascunho e tira a cotação original de "Aguardando Liberação".
        $cotacao_protocolo = trim((string) ($post['cotacao'] ?? ''));
        $protocolo_origem  = null;
        if ($cotacao_protocolo !== '' && $tipo === 'fianca') {
            $cotacao_origem = db_fetch_one(
                'SELECT protocolo FROM seguro_fianca WHERE protocolo = ? AND imobiliaria_id = ?',
                ['si', $cotacao_protocolo, $current_user->imobiliaria_id]
            );
            if ($cotacao_origem) {
                $protocolo_origem = $cotacao_origem['protocolo'];
            }
        }

        // Monta apenas os campos da whitelist presentes no POST
        $campos_data    = $cfg['campos_data'] ?? [];
        $campos_decimal = $cfg['campos_decimal'] ?? [];
        $colunas = [];
        $valores = [];
        foreach ($cfg['campos'] as $campo) {
            if (array_key_exists($campo, $post)) {
                $v = trim((string) $post[$campo]);
                if (in_array($campo, $campos_data, true) && $v !== '') {
                    // Campo é DATE no banco — o form envia DD/MM/AAAA (mascarado).
                    $v = date_from_br($v);
                } elseif (in_array($campo, $campos_decimal, true) && $v !== '') {
                    // Campo é DECIMAL no banco — o form envia mascarado (ex: "R$ 3.333,33").
                    $v = self::moneyFromBr($v);
                }
                $colunas[]  = $campo;
                $valores[]  = ($v === '' || $v === null) ? null : $v;
            }
        }

        // Produto com lista de blocos (Incêndio: N imóveis) — guarda os blocos como JSON.
        if (!empty($cfg['json'])) {
            $blocos = [];
            foreach ((array) ($post[$cfg['json']['post']] ?? []) as $item) {
                if (!is_array($item)) continue;
                $bloco = [];
                foreach ($cfg['json']['campos'] as $campo) {
                    if (isset($item[$campo]) && is_string($item[$campo])) {
                        $bloco[$campo] = mb_substr(trim($item[$campo]), 0, 200);
                    }
                }
                $blocos[] = $bloco;
            }
            $colunas[] = $cfg['json']['coluna'];
            $valores[] = json_encode(array_slice($blocos, 0, 20), JSON_UNESCAPED_UNICODE);
        }

        if ($rascunho_protocolo !== '') {
            // Atualização de um rascunho existente — verifica posse.
            $existente = db_fetch_one(
                "SELECT id, protocolo FROM {$cfg['tabela']} WHERE protocolo = ? AND imobiliaria_id = ?",
                ['si', $rascunho_protocolo, $current_user->imobiliaria_id]
            );
            if (!$existente) {
                return response()->json(['ok' => false, 'message' => 'Rascunho não encontrado.']);
            }

            // Se este rascunho ainda não tinha um protocolo vinculado a uma cotação real
            // e agora veio um (fluxo completar cotação), grava o vínculo.
            if ($protocolo_origem) {
                $colunas[] = 'protocolo';
                $valores[] = $protocolo_origem;
            }

            $sets   = array_map(fn($c) => "`{$c}` = ?", $colunas);
            $sets[] = '`nome_rascunho` = ?';
            $types  = str_repeat('s', count($colunas)) . 's';
            $binds  = [...$valores, $nome_rascunho];

            db_execute(
                "UPDATE {$cfg['tabela']} SET " . implode(', ', $sets) . " WHERE id = ?",
                [$types . 'i', ...$binds, $existente['id']]
            );

            $protocolo_final = $protocolo_origem ?: $existente['protocolo'];
        } else {
            // Inserção nova — protocolo é o da cotação de origem (se vier de "completar
            // cotação") ou um novo, gerado só para este rascunho.
            $protocolo_final = $protocolo_origem
                ?: gerar_protocolo($cfg['prefixo_protocolo'], $cfg['tabela']);

            $colunas[] = 'protocolo';
            $valores[] = $protocolo_final;
            $colunas[] = 'nome_rascunho';
            $valores[] = $nome_rascunho;
            $colunas[] = 'usuario_id';
            $valores[] = $current_user->uid;
            $colunas[] = 'imobiliaria_id';
            $valores[] = $current_user->imobiliaria_id;

            $placeholders = implode(', ', array_fill(0, count($colunas), '?'));
            $colunasSql   = implode(', ', array_map(fn($c) => "`{$c}`", $colunas));
            // colunas = [...campos(s), protocolo(s), nome_rascunho(s), usuario_id(i), imobiliaria_id(i)]
            $types        = str_repeat('s', count($colunas) - 2) . 'ii';

            db_execute(
                "INSERT INTO {$cfg['tabela']} ({$colunasSql}) VALUES ({$placeholders})",
                [$types, ...$valores]
            );
        }

        // Tira a cotação original de "Aguardando Liberação" — ela agora vive como rascunho.
        if ($protocolo_origem) {
            db_execute(
                "UPDATE seguro_fianca SET status = 'Rascunho' WHERE protocolo = ? AND imobiliaria_id = ?",
                ['si', $protocolo_origem, $current_user->imobiliaria_id]
            );
        }

        return response()->json(['ok' => true, 'protocolo' => $protocolo_final]);
    }

    /**
     * POST /api/cotacao/rascunho/excluir
     * Exclui um rascunho de cotação, validando posse pela imobiliária. Corpo JSON {tipo, protocolo}.
     */
    public function excluir(Request $request)
    {
        $current_user = require_auth();

        if (($current_user->role ?? '') !== 'imobiliaria') {
            return response()->json(['ok' => false, 'message' => 'Sem permissão.']);
        }

        $tipo      = trim((string) $request->input('tipo', ''));
        $protocolo = trim((string) $request->input('protocolo', ''));

        $cfg = rascunho_config($tipo);
        if (!$cfg || $protocolo === '') {
            return response()->json(['ok' => false, 'message' => 'Dados inválidos.']);
        }

        $existente = db_fetch_one(
            "SELECT * FROM {$cfg['tabela']} WHERE protocolo = ? AND imobiliaria_id = ?",
            ['si', $protocolo, $current_user->imobiliaria_id]
        );
        if (!$existente) {
            return response()->json(['ok' => false, 'message' => 'Rascunho não encontrado.']);
        }

        // Se este rascunho veio de uma cotação real (fluxo "completar cotação"), a
        // cotação foi marcada como 'Rascunho' para sumir de "Aguardando Liberação".
        // Excluir o rascunho não pode deixá-la travada nesse status sem volta —
        // ela precisa voltar a ficar acessível para completar/aceitar de novo.
        // (No-op se este protocolo não corresponder a nenhuma cotação real.)
        if ($tipo === 'fianca') {
            db_execute(
                "UPDATE seguro_fianca SET status = 'Aguardando preenchimento'
                  WHERE protocolo = ? AND imobiliaria_id = ? AND status = 'Rascunho'",
                ['si', $protocolo, $current_user->imobiliaria_id]
            );
        }

        db_execute("DELETE FROM {$cfg['tabela']} WHERE id = ?", ['i', $existente['id']]);

        return response()->json(['ok' => true]);
    }

    /** Converte valor mascarado (ex: "R$ 3.333,33") para decimal puro (ex: "3333.33"). */
    private static function moneyFromBr(string $v): ?string
    {
        $digitos = preg_replace('/\D/', '', $v);
        if ($digitos === '') return null;
        // Sem depender da extensão bcmath (pode não estar disponível no servidor):
        // os dígitos já representam centavos, então dá pra formatar direto sem
        // conversão para float (evita qualquer imprecisão para valores grandes).
        $digitos = ltrim($digitos, '0');
        if ($digitos === '') $digitos = '0';
        $digitos = str_pad($digitos, 3, '0', STR_PAD_LEFT);
        $centavos = substr($digitos, -2);
        $inteiros = substr($digitos, 0, -2);
        return $inteiros . '.' . $centavos;
    }
}
