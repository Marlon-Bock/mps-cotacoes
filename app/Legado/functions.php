<?php
/**
 * Funções utilitárias globais do sistema.
 */

// url(), redirect(), e() e json_response(): ver app/Legado/nucleo.php e os helpers do Laravel.

/** Grava mensagem flash na sessão. */
function flash(string $type, string $message): void
{
    $lista   = session('flash_mps', []);
    $lista[] = ['type' => $type, 'message' => $message];
    session(['flash_mps' => $lista]);
}

/** Retorna e limpa as mensagens flash. */
function flash_get(): array
{
    $messages = session('flash_mps', []);
    session()->forget('flash_mps');
    return $messages;
}

/** Valida CPF. */
function validar_cpf(string $cpf): bool
{
    $cpf = preg_replace('/\D/', '', $cpf);

    if (strlen($cpf) !== 11 || preg_match('/^(\d)\1{10}$/', $cpf)) {
        return false;
    }

    for ($t = 9; $t < 11; $t++) {
        $sum = 0;
        for ($i = 0; $i < $t; $i++) {
            $sum += (int) $cpf[$i] * ($t + 1 - $i);
        }
        $digit = ((10 * $sum) % 11) % 10;
        if ((int) $cpf[$t] !== $digit) return false;
    }

    return true;
}

/** Valida CNPJ. */
function validar_cnpj(string $cnpj): bool
{
    $cnpj = preg_replace('/\D/', '', $cnpj);

    if (strlen($cnpj) !== 14 || preg_match('/^(\d)\1{13}$/', $cnpj)) {
        return false;
    }

    $calc = function (string $cnpj, int $len): int {
        $sum = 0;
        $pos = $len - 7;
        for ($i = $len; $i >= 1; $i--) {
            $sum += (int) $cnpj[$len - $i] * $pos--;
            if ($pos < 2) $pos = 9;
        }
        return $sum % 11 < 2 ? 0 : 11 - $sum % 11;
    };

    return (int) $cnpj[12] === $calc($cnpj, 12)
        && (int) $cnpj[13] === $calc($cnpj, 13);
}

/** Gera protocolo único com retry. Exemplo: MPS2026-00042 */
function gerar_protocolo(string $prefixo, string $tabela, string $coluna = 'protocolo'): string
{
    $ano = date('Y');

    for ($i = 0; $i < 10; $i++) {
        $num       = str_pad((string) random_int(1, 99999), 5, '0', STR_PAD_LEFT);
        $protocolo = "{$prefixo}{$ano}-{$num}";

        if (!\Illuminate\Support\Facades\DB::table($tabela)->where($coluna, $protocolo)->exists()) {
            return $protocolo;
        }
    }

    // fallback: usa timestamp para garantir unicidade
    return "{$prefixo}{$ano}-" . substr((string) time(), -5);
}

/** Formata data pt-BR → Y-m-d (aceita null). */
function date_from_br(?string $date): ?string
{
    if (!$date) return null;
    if (!preg_match('/^(\d{2})\/(\d{2})\/(\d{4})$/', trim($date), $m)) return null;
    [, $d, $mo, $y] = $m;
    if (!checkdate((int) $mo, (int) $d, (int) $y)) return null;
    return "{$y}-{$mo}-{$d}";
}

/** Formata Y-m-d → d/m/Y para exibição. */
function date_to_br(?string $date): string
{
    if (!$date) return '';
    $d = DateTime::createFromFormat('Y-m-d', $date)
      ?: DateTime::createFromFormat('Y-m-d H:i:s', $date);
    return $d ? $d->format('d/m/Y') : $date;
}

/** Verifica se a requisição é AJAX (fetch/XHR). */
function is_ajax(): bool
{
    return ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '') === 'XMLHttpRequest'
        || str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json');
}

/** Sanitiza string para uso seguro (não substitui prepared statements). */
function sanitize(string $value): string
{
    return trim(strip_tags($value));
}

/**
 * Executa uma query de contagem com fallback seguro.
 * Usado em dashboards que referenciam tabelas de módulos ainda não construídos
 * (ex: seguro_fianca, seguro_auto) — evita fatal error enquanto o módulo não existe.
 */
function safe_count(string $sql, array $params = []): int
{
    try {
        $row = db_fetch_one($sql, $params);
        $total = $row['total'] ?? 0;
        return (int) $total;
    } catch (Throwable $e) {
        return 0;
    }
}

/**
 * Conta cotações (fiança + auto + título + condomínio) de uma imobiliária
 * filtrando por status. Aceita múltiplos valores de status (ex: variações
 * de grafia "Concluída"/"Concluida").
 *
 * $produtosPermitidos restringe quais produtos entram na contagem — usar as
 * permissões do usuário logado (perm_fianca/perm_auto/perm_titulo/perm_condo)
 * pra não somar cotações de produto que ele não tem acesso. Passar null
 * (padrão) conta todos os produtos, sem filtro.
 *
 * $usuarioId restringe às cotações daquele usuário (quem criou, do lado imobiliária) — operador comum só
 * deve ver as próprias (mesma regra de includes/acao/cotacoes-listar.php);
 * null (padrão/admin da imobiliária) conta de todos os usuários.
 */
function count_seguros_by_status(int $imobiliariaId, array $statuses, ?array $produtosPermitidos = null, ?int $usuarioId = null): int
{
    $placeholders = implode(',', array_fill(0, count($statuses), '?'));
    $tabelas_por_produto = [
        'fianca'      => 'seguro_fianca',
        'auto'        => 'seguro_auto',
        'titulo'      => 'titulo_capitalizacao',
        'condominial' => 'seguro_condominial',
        'residencial' => 'seguro_residencial',
    ];

    $total = 0;
    foreach ($tabelas_por_produto as $produto => $tabela) {
        if ($produtosPermitidos !== null && !in_array($produto, $produtosPermitidos, true)) {
            continue;
        }
        $sql  = "SELECT COUNT(*) AS total FROM {$tabela} WHERE imobiliaria_id = ? AND status IN ({$placeholders})";
        $bind = ['i' . str_repeat('s', count($statuses)), $imobiliariaId, ...$statuses];
        if ($usuarioId !== null) {
            $sql .= " AND usuario_id = ?";
            $bind[0] .= 'i';
            $bind[]  = $usuarioId;
        }
        // Cada tabela é contada isoladamente (e com safe_count) porque título
        // e condomínio são tabelas opcionais em alguns ambientes — uma
        // ausente não deve derrubar a contagem dos outros produtos.
        $total += safe_count($sql, $bind);
    }

    return $total;
}

/**
 * Total de cotações (fiança + auto + título + condomínio) emitidas por mês,
 * no ano informado. Retorna array[1..12] => int.
 *
 * $produtosPermitidos restringe quais produtos entram na contagem (ver
 * count_seguros_by_status) — null (padrão) conta todos os produtos.
 * $usuarioId restringe às cotações daquele usuário (quem criou, do lado imobiliária) — null (padrão/admin)
 * conta de todos os usuários.
 */
function monthly_quotes_count(int $imobiliariaId, int $year, ?array $produtosPermitidos = null, ?int $usuarioId = null): array
{
    $months = array_fill(1, 12, 0);
    $pode = fn(string $produto): bool => $produtosPermitidos === null || in_array($produto, $produtosPermitidos, true);
    $filtroUsuario = $usuarioId !== null ? " AND usuario_id = ?" : "";

    if ($pode('fianca') || $pode('auto') || $pode('residencial')) {
        try {
            $selects = [];
            if ($pode('fianca')) $selects[] = "SELECT data_cadastro FROM seguro_fianca WHERE imobiliaria_id = ? AND YEAR(data_cadastro) = ?{$filtroUsuario}";
            if ($pode('auto'))   $selects[] = "SELECT data_cadastro FROM seguro_auto WHERE imobiliaria_id = ? AND YEAR(data_cadastro) = ?{$filtroUsuario}";
            if ($pode('residencial')) $selects[] = "SELECT data_cadastro FROM seguro_residencial WHERE imobiliaria_id = ? AND YEAR(data_cadastro) = ?{$filtroUsuario}";
            $bind = [];
            foreach ($selects as $_) {
                $bind[] = $imobiliariaId;
                $bind[] = $year;
                if ($usuarioId !== null) $bind[] = $usuarioId;
            }
            $tipos = str_repeat($usuarioId !== null ? 'iii' : 'ii', count($selects));

            $rows = db_fetch_all(
                "SELECT MONTH(data_cadastro) AS mes, COUNT(*) AS total FROM (" . implode(' UNION ALL ', $selects) . ") t GROUP BY MONTH(data_cadastro)",
                [$tipos, ...$bind]
            );

            foreach ($rows as $row) {
                $months[(int) $row['mes']] = (int) $row['total'];
            }
        } catch (Throwable $e) {
            // mantém os zeros
        }
    }

    // Título de capitalização e condomínio usam `criado_em` em vez de `data_cadastro`,
    // e são tabelas opcionais — somadas separadamente para não derrubar a query principal.
    if ($pode('titulo') || $pode('condominial')) {
        try {
            $selects = [];
            if ($pode('titulo'))      $selects[] = "SELECT criado_em FROM titulo_capitalizacao WHERE imobiliaria_id = ? AND YEAR(criado_em) = ?{$filtroUsuario}";
            if ($pode('condominial')) $selects[] = "SELECT criado_em FROM seguro_condominial WHERE imobiliaria_id = ? AND YEAR(criado_em) = ?{$filtroUsuario}";
            $bind = [];
            foreach ($selects as $_) {
                $bind[] = $imobiliariaId;
                $bind[] = $year;
                if ($usuarioId !== null) $bind[] = $usuarioId;
            }
            $tipos = str_repeat($usuarioId !== null ? 'iii' : 'ii', count($selects));

            $rows = db_fetch_all(
                "SELECT MONTH(criado_em) AS mes, COUNT(*) AS total FROM (" . implode(' UNION ALL ', $selects) . ") t GROUP BY MONTH(criado_em)",
                [$tipos, ...$bind]
            );

            foreach ($rows as $row) {
                $months[(int) $row['mes']] += (int) $row['total'];
            }
        } catch (Throwable $e) {
            // mantém o que já foi somado
        }
    }

    return $months;
}

/**
 * Pendências da imobiliária que precisam de uma ação dela: biometria ainda
 * não concluída (só Fiança), e proposta enviada aguardando
 * aceite/recusa (qualquer produto). Usada no popup de "bem-vindo" do
 * dashboard — mostrado uma vez por login, nunca repetido durante a sessão.
 *
 * $produtosPermitidos restringe quais produtos entram (ver
 * count_seguros_by_status) — null (padrão/admin) considera todos.
 * $usuarioId restringe às cotações daquele usuário (quem criou, do lado imobiliária) — null (padrão/admin)
 * considera de todos os usuários.
 *
 * @return array<array{protocolo:string, tipo:string}> tipo: 'biometria'|'proposta'
 */
function imob_pendencias(int $imobiliariaId, ?array $produtosPermitidos = null, ?int $usuarioId = null): array
{
    $itens = [];
    $pode = fn(string $produto): bool => $produtosPermitidos === null || in_array($produto, $produtosPermitidos, true);
    $filtroUsuario = $usuarioId !== null ? " AND usuario_id = ?" : "";

    if ($pode('fianca')) {
        try {
            $bind = $usuarioId !== null ? ['ii', $imobiliariaId, $usuarioId] : ['i', $imobiliariaId];
            $rows = db_fetch_all(
                "SELECT DISTINCT sf.protocolo, sf.status FROM seguro_fianca sf
                  INNER JOIN seguro_fianca_biometrias b ON b.protocolo = sf.protocolo AND b.situacao = 'pendente'
                  WHERE sf.imobiliaria_id = ?" . str_replace('usuario_id', 'sf.usuario_id', $filtroUsuario),
                $bind
            );
            foreach ($rows as $r) {
                // A cotação pode ter sido encerrada (concluída/recusada/desistência)
                // com o link ainda pendente — nada mais a fazer pra imobiliária.
                if (cotacao_status_encerrado((string) $r['status'])) continue;
                $itens[] = ['protocolo' => $r['protocolo'], 'tipo' => 'biometria'];
            }
        } catch (Throwable $e) {}
    }

    $tabelas_anexos = [
        'fianca'      => ['seguro_fianca', 'cotacoes_anexos'],
        'auto'        => ['seguro_auto', 'cotacoes_anexos_auto'],
        'titulo'      => ['titulo_capitalizacao', 'cotacoes_anexos_titulo'],
        'condominial' => ['seguro_condominial', 'cotacoes_anexos_condominial'],
        'residencial' => ['seguro_residencial', 'cotacoes_anexos_residencial'],
    ];
    $filtroUsuarioC = $usuarioId !== null ? " AND c.usuario_id = ?" : "";
    foreach ($tabelas_anexos as $produto => [$tblCotacao, $tblAnexo]) {
        if (!$pode($produto)) continue;
        try {
            $bind = $usuarioId !== null ? ['ii', $imobiliariaId, $usuarioId] : ['i', $imobiliariaId];
            $rows = db_fetch_all(
                "SELECT c.protocolo FROM {$tblCotacao} c
                  INNER JOIN {$tblAnexo} a ON a.protocolo = c.protocolo
                  WHERE c.imobiliaria_id = ? AND c.status = 'Aprovado' AND a.status_aprovacao = 'Aguardando'
                    AND a.id = (SELECT MAX(id) FROM {$tblAnexo} WHERE protocolo = c.protocolo AND tipo_arquivo = 'proposta'){$filtroUsuarioC}",
                $bind
            );
            foreach ($rows as $r) {
                $itens[] = ['protocolo' => $r['protocolo'], 'tipo' => 'proposta'];
            }
        } catch (Throwable $e) {}
    }

    // Incêndio fica de fora do loop acima: 1 protocolo pode ter N imóveis,
    // cada um com sua própria proposta (cotacoes_anexos_incendio.seguro_incendio_imovel_id) —
    // "MAX(id) do protocolo inteiro" não identifica corretamente qual imóvel
    // está com proposta pendente, então a checagem é por imóvel mesmo.
    if ($pode('incendio')) {
        try {
            $filtroUsuarioSi = $usuarioId !== null ? " AND si.usuario_id = ?" : "";
            $bind = $usuarioId !== null ? ['ii', $imobiliariaId, $usuarioId] : ['i', $imobiliariaId];
            $rows = db_fetch_all(
                "SELECT DISTINCT si.protocolo
                   FROM seguro_incendio si
                   INNER JOIN seguro_incendio_imoveis sii ON sii.seguro_incendio_id = si.id
                   INNER JOIN cotacoes_anexos_incendio a ON a.seguro_incendio_imovel_id = sii.id
                  WHERE si.imobiliaria_id = ? AND sii.status = 'Aprovado' AND a.status_aprovacao = 'Aguardando'
                    AND a.id = (SELECT MAX(id) FROM cotacoes_anexos_incendio WHERE seguro_incendio_imovel_id = sii.id)
                    {$filtroUsuarioSi}",
                $bind
            );
            foreach ($rows as $r) {
                $itens[] = ['protocolo' => $r['protocolo'], 'tipo' => 'proposta'];
            }
        } catch (Throwable $e) {}
    }

    return $itens;
}

/**
 * Últimas cotações (fiança + auto + título + condomínio + incêndio) de uma
 * imobiliária, mais recentes primeiro.
 *
 * $produtosPermitidos restringe quais produtos entram na listagem (ver
 * count_seguros_by_status) — null (padrão) lista todos os produtos.
 * $usuarioId restringe às cotações daquele usuário (quem criou, do lado imobiliária) — null (padrão/admin)
 * lista de todos os usuários.
 */
function recent_quotes(int $imobiliariaId, int $limit = 6, ?array $produtosPermitidos = null, ?int $usuarioId = null): array
{
    $rows = [];
    $pode = fn(string $produto): bool => $produtosPermitidos === null || in_array($produto, $produtosPermitidos, true);
    $filtroUsuario = $usuarioId !== null ? " AND usuario_id = ?" : "";

    if ($pode('fianca') || $pode('auto')) {
        try {
            $selects = [];
            if ($pode('fianca')) $selects[] = "SELECT 'fianca' AS tipo, tipo_ficha, nome_completo AS pretendente, status, protocolo, data_atualizacao, origem FROM seguro_fianca WHERE imobiliaria_id = ?{$filtroUsuario}";
            if ($pode('auto'))   $selects[] = "SELECT 'auto' AS tipo, NULL AS tipo_ficha, segurado_nome AS pretendente, status, protocolo, data_atualizacao, NULL AS origem FROM seguro_auto WHERE imobiliaria_id = ?{$filtroUsuario}";
            $bind = [];
            foreach ($selects as $_) {
                $bind[] = $imobiliariaId;
                if ($usuarioId !== null) $bind[] = $usuarioId;
            }
            $tipos = str_repeat($usuarioId !== null ? 'ii' : 'i', count($selects));
            $rows = array_merge($rows, db_fetch_all(
                "SELECT * FROM (" . implode(' UNION ALL ', $selects) . ") t",
                [$tipos, ...$bind]
            ));
        } catch (Throwable $e) {}
    }

    if ($pode('titulo') || $pode('condominial')) {
        try {
            $selects = [];
            if ($pode('titulo'))      $selects[] = "SELECT 'titulo' AS tipo, NULL AS tipo_ficha, COALESCE(NULLIF(inq_nome,''), prop_nome) AS pretendente, status, protocolo, COALESCE(data_atualizacao, criado_em) AS data_atualizacao FROM titulo_capitalizacao WHERE imobiliaria_id = ?{$filtroUsuario}";
            if ($pode('condominial')) $selects[] = "SELECT 'condominial' AS tipo, NULL AS tipo_ficha, COALESCE(NULLIF(imovel_nome,''), sindico_nome) AS pretendente, status, protocolo, COALESCE(data_atualizacao, criado_em) AS data_atualizacao FROM seguro_condominial WHERE imobiliaria_id = ?{$filtroUsuario}";
            $bind = [];
            foreach ($selects as $_) {
                $bind[] = $imobiliariaId;
                if ($usuarioId !== null) $bind[] = $usuarioId;
            }
            $tipos = str_repeat($usuarioId !== null ? 'ii' : 'i', count($selects));
            $rows = array_merge($rows, db_fetch_all(
                "SELECT * FROM (" . implode(' UNION ALL ', $selects) . ") t",
                [$tipos, ...$bind]
            ));
        } catch (Throwable $e) {}
    }

    if ($pode('residencial')) {
        try {
            $rows = array_merge($rows, db_fetch_all(
                "SELECT 'residencial' AS tipo, NULL AS tipo_ficha, nome_completo AS pretendente, status, protocolo,
                        COALESCE(data_atualizacao, data_cadastro) AS data_atualizacao
                   FROM seguro_residencial WHERE imobiliaria_id = ?{$filtroUsuario}",
                $usuarioId !== null ? ['ii', $imobiliariaId, $usuarioId] : ['i', $imobiliariaId]
            ));
        } catch (Throwable $e) {}
    }

    if ($pode('incendio')) {
        try {
            $sql_incendio  = "SELECT si.id, 'incendio' AS tipo, NULL AS tipo_ficha,
                        status, protocolo, COALESCE(data_atualizacao, data_cadastro) AS data_atualizacao
                 FROM seguro_incendio si WHERE imobiliaria_id = ?{$filtroUsuario}";
            $bind_incendio = $usuarioId !== null ? ['ii', $imobiliariaId, $usuarioId] : ['i', $imobiliariaId];
            $incendio_rows = db_fetch_all($sql_incendio, $bind_incendio);
            foreach ($incendio_rows as &$ir) {
                $ir['pretendente'] = seguro_incendio_resumo_imoveis((int) $ir['id']);
            }
            unset($ir);
            $rows = array_merge($rows, $incendio_rows);
        } catch (Throwable $e) {}
    }

    usort($rows, fn($a, $b) => strcmp($b['data_atualizacao'] ?? '', $a['data_atualizacao'] ?? ''));

    return array_slice($rows, 0, $limit);
}

/** Últimas notificações (lidas ou não) de um destinatário, mais recentes primeiro. */
function recent_notifications(int $destinatarioId, string $destinatarioTipo, int $limit = 5): array
{
    try {
        return db_fetch_all(
            "SELECT id, titulo, mensagem, tipo, lida, criado_em
             FROM notificacoes_mps
             WHERE destinatario_id = ? AND destinatario_tipo = ?
             ORDER BY criado_em DESC
             LIMIT ?",
            ['isi', $destinatarioId, $destinatarioTipo, $limit]
        );
    } catch (Throwable $e) {
        return [];
    }
}

/** Campanhas ativas e vigentes direcionadas a uma imobiliária. */
function active_campaigns(int $imobiliariaId, int $limit = 10): array
{
    try {
        return db_fetch_all(
            "SELECT c.id, c.titulo, c.imagem, c.observacoes, c.data_fim
             FROM campanhas c
             WHERE c.status = 'ativo' AND NOW() BETWEEN c.data_inicio AND c.data_fim
               AND (
                    NOT EXISTS (SELECT 1 FROM campanha_imobiliarias WHERE campanha_id = c.id)
                    OR EXISTS (SELECT 1 FROM campanha_imobiliarias ci WHERE ci.campanha_id = c.id AND ci.imobiliaria_id = ?)
               )
             ORDER BY c.criado_em DESC, c.id DESC
             LIMIT ?",
            ['ii', $imobiliariaId, $limit]
        );
    } catch (Throwable $e) {
        return [];
    }
}

/**
 * Campanha ativa que deve aparecer como popup numa tela de produto específica
 * ('residencial'|'comercial'|'auto'|'titulo'|'condominial'). Mesma regra de
 * visibilidade de active_campaigns() (segmentada ou "para todas"), mais o
 * filtro popup_ativo + popup_{$tipo}. Se houver mais de uma elegível, mostra
 * só a mais recente.
 */
function campanha_popup_ativa(int $imobiliariaId, string $tipo): ?array
{
    $tiposValidos = ['residencial', 'comercial', 'auto', 'titulo', 'condominial'];
    if (!in_array($tipo, $tiposValidos, true)) {
        return null;
    }

    try {
        return db_fetch_one(
            "SELECT c.id, c.titulo, c.imagem, c.observacoes
             FROM campanhas c
             WHERE c.status = 'ativo' AND NOW() BETWEEN c.data_inicio AND c.data_fim
               AND c.popup_ativo = 1 AND c.popup_{$tipo} = 1
               AND (
                    NOT EXISTS (SELECT 1 FROM campanha_imobiliarias WHERE campanha_id = c.id)
                    OR EXISTS (SELECT 1 FROM campanha_imobiliarias ci WHERE ci.campanha_id = c.id AND ci.imobiliaria_id = ?)
               )
             ORDER BY c.criado_em DESC, c.id DESC
             LIMIT 1",
            ['i', $imobiliariaId]
        );
    } catch (Throwable $e) {
        return null;
    }
}

/**
 * Rodízio de atendentes por funil (ver /admin/rodizio — tabelas `funis` e
 * `funil_corretores`): escolhe o próximo atendente ativo em ordem circular
 * pro tipo de seguro informado ('fianca'|'auto'|'titulo'|'condominial') e
 * avança o ponteiro pro próximo. Retorna 0 se o funil não existir, estiver
 * inativo, ou não tiver nenhum atendente ativo.
 */
function funil_atribuir_atendente(string $tipoSeguro): int
{
    try {
        return \Illuminate\Support\Facades\DB::transaction(function () use ($tipoSeguro): int {
            // Trava a linha do funil até o fim da transação — evita duas
            // cotações simultâneas caírem no mesmo índice da fila.
            $funil = db_fetch_one(
                "SELECT id, proximo_idx FROM funis WHERE tipo_seguro = ? AND ativo = 'S' FOR UPDATE",
                ['s', $tipoSeguro]
            );
            if (!$funil) {
                return 0;
            }

            $ativos = db_fetch_all(
                "SELECT user_id FROM funil_corretores WHERE funil_id = ? AND ativo = 'S' ORDER BY ordem ASC",
                ['i', $funil['id']]
            );
            $total = count($ativos);
            if ($total === 0) {
                return 0;
            }

            $idx = ((int) $funil['proximo_idx']) % $total;
            db_execute('UPDATE funis SET proximo_idx = ? WHERE id = ?', ['ii', ($idx + 1) % $total, (int) $funil['id']]);

            return (int) $ativos[$idx]['user_id'];
        });
    } catch (Throwable $e) {
        error_log('[funil_atribuir_atendente] ' . $e->getMessage());
        return 0;
    }
}

/* ═══════════════════════════════════════════════════════════════════════════
   Dashboard Admin — funções que agregam dados de todas as imobiliárias.
   ═══════════════════════════════════════════════════════════════════════════ */

/** Total de cotações por mês (todas as imobiliárias), no ano informado. */
function admin_monthly_quotes_count(int $year, ?array $produtosPermitidos = null): array
{
    $months = array_fill(1, 12, 0);
    $permite = fn(string $tipo) => $produtosPermitidos === null || in_array($tipo, $produtosPermitidos, true);

    $blocosA = array_filter([
        $permite('fianca')   ? "SELECT data_cadastro FROM seguro_fianca WHERE YEAR(data_cadastro) = ?" : null,
        $permite('auto')     ? "SELECT data_cadastro FROM seguro_auto   WHERE YEAR(data_cadastro) = ?" : null,
        $permite('incendio') ? "SELECT data_cadastro FROM seguro_incendio WHERE YEAR(data_cadastro) = ?" : null,
        $permite('residencial') ? "SELECT data_cadastro FROM seguro_residencial WHERE YEAR(data_cadastro) = ?" : null,
    ]);
    if ($blocosA) {
        try {
            $rows = db_fetch_all(
                "SELECT MONTH(data_cadastro) AS mes, COUNT(*) AS total FROM (" . implode(' UNION ALL ', $blocosA) . ") t GROUP BY MONTH(data_cadastro)",
                [str_repeat('i', count($blocosA)), ...array_fill(0, count($blocosA), $year)]
            );
            foreach ($rows as $row) $months[(int) $row['mes']] += (int) $row['total'];
        } catch (Throwable $e) {}
    }

    // Título de capitalização e condomínio usam `criado_em` e são tabelas
    // opcionais — somadas separadamente para não zerar a contagem principal
    // caso não existam no ambiente (mesmo racional de monthly_quotes_count()).
    $blocosB = array_filter([
        $permite('titulo')      ? "SELECT criado_em FROM titulo_capitalizacao WHERE YEAR(criado_em) = ?" : null,
        $permite('condominial') ? "SELECT criado_em FROM seguro_condominial   WHERE YEAR(criado_em) = ?" : null,
    ]);
    if ($blocosB) {
        try {
            $rows = db_fetch_all(
                "SELECT MONTH(criado_em) AS mes, COUNT(*) AS total FROM (" . implode(' UNION ALL ', $blocosB) . ") t GROUP BY MONTH(criado_em)",
                [str_repeat('i', count($blocosB)), ...array_fill(0, count($blocosB), $year)]
            );
            foreach ($rows as $row) $months[(int) $row['mes']] += (int) $row['total'];
        } catch (Throwable $e) {}
    }

    return $months;
}

/**
 * Distribuição de status do mês corrente — todas as imobiliárias.
 * Retorna ['statuses' => [...], 'total' => int].
 */
function admin_status_panorama(?array $produtosPermitidos = null): array
{
    $permite = fn(string $tipo) => $produtosPermitidos === null || in_array($tipo, $produtosPermitidos, true);
    $slots = [
        'aguardando' => ['label' => 'Aguardando análise', 'key' => 'is-aguardando', 'total' => 0, 'pct' => 0],
        'em_analise' => ['label' => 'Em análise',         'key' => 'is-analise',    'total' => 0, 'pct' => 0],
        'aprovado'   => ['label' => 'Aprovado',           'key' => 'is-aprovado',   'total' => 0, 'pct' => 0],
        'concluido'  => ['label' => 'Concluída',          'key' => 'is-concluido',  'total' => 0, 'pct' => 0],
        'recusado'   => ['label' => 'Recusado',           'key' => 'is-recusado',   'total' => 0, 'pct' => 0],
        'desistencia'=> ['label' => 'Desistência',        'key' => 'is-desistencia','total' => 0, 'pct' => 0],
    ];

    $aplicar_linhas = function (array $rows) use (&$slots) {
        foreach ($rows as $row) {
            $s = $row['status'];
            $n = (int) $row['total'];

            if (in_array($s, ['Aguardando análise', 'Aguardando analise'])) {
                $slots['aguardando']['total'] += $n;
            } elseif (in_array($s, ['Em Analise', 'Em Análise'])) {
                $slots['em_analise']['total'] += $n;
            } elseif ($s === 'Aprovado') {
                $slots['aprovado']['total'] += $n;
            } elseif (in_array($s, ['Concluída', 'Concluida'])) {
                $slots['concluido']['total'] += $n;
            } elseif (cotacao_status_recusado($s)) {
                $slots['recusado']['total'] += $n;
            } elseif (cotacao_status_desistencia($s)) {
                $slots['desistencia']['total'] += $n;
            }
        }
    };

    $blocosA = array_filter([
        $permite('fianca')   ? "SELECT status FROM seguro_fianca    WHERE YEAR(data_cadastro) = YEAR(NOW()) AND MONTH(data_cadastro) = MONTH(NOW())" : null,
        $permite('auto')     ? "SELECT status FROM seguro_auto      WHERE YEAR(data_cadastro) = YEAR(NOW()) AND MONTH(data_cadastro) = MONTH(NOW())" : null,
        $permite('incendio') ? "SELECT status FROM seguro_incendio  WHERE YEAR(data_cadastro) = YEAR(NOW()) AND MONTH(data_cadastro) = MONTH(NOW())" : null,
        $permite('residencial') ? "SELECT status FROM seguro_residencial WHERE YEAR(data_cadastro) = YEAR(NOW()) AND MONTH(data_cadastro) = MONTH(NOW())" : null,
    ]);
    if ($blocosA) {
        try {
            $aplicar_linhas(db_fetch_all("SELECT status, COUNT(*) AS total FROM (" . implode(' UNION ALL ', $blocosA) . ") t GROUP BY status"));
        } catch (Throwable $e) {}
    }

    // Título de capitalização e condomínio usam `criado_em` e são tabelas
    // opcionais — somadas separadamente para não zerar o panorama principal
    // caso não existam no ambiente.
    $blocosB = array_filter([
        $permite('titulo')      ? "SELECT status FROM titulo_capitalizacao WHERE YEAR(criado_em) = YEAR(NOW()) AND MONTH(criado_em) = MONTH(NOW())" : null,
        $permite('condominial') ? "SELECT status FROM seguro_condominial   WHERE YEAR(criado_em) = YEAR(NOW()) AND MONTH(criado_em) = MONTH(NOW())" : null,
    ]);
    if ($blocosB) {
        try {
            $aplicar_linhas(db_fetch_all("SELECT status, COUNT(*) AS total FROM (" . implode(' UNION ALL ', $blocosB) . ") t GROUP BY status"));
        } catch (Throwable $e) {}
    }

    $grand = array_sum(array_column($slots, 'total'));

    foreach ($slots as &$item) {
        $item['pct'] = $grand > 0 ? (int) round($item['total'] / $grand * 100) : 0;
    }

    return ['statuses' => $slots, 'total' => $grand];
}

/**
 * Cotações concluídas por tipo — mês atual vs mês anterior.
 * Retorna array indexado por tipo com 'atual', 'anterior' e 'delta'.
 */
function admin_concluidos_por_tipo(?array $produtosPermitidos = null): array
{
    $permite = fn(string $tipo) => $produtosPermitidos === null || in_array($tipo, $produtosPermitidos, true);

    $data = array_filter([
        'fianca'      => $permite('fianca')      ? ['label' => 'Seguro Fiança',            'icone' => 'house',      'atual' => 0, 'anterior' => 0] : null,
        'auto'        => $permite('auto')        ? ['label' => 'Seguro Auto',              'icone' => 'car',        'atual' => 0, 'anterior' => 0] : null,
        'titulo'      => $permite('titulo')      ? ['label' => 'Título de Capitalização',  'icone' => 'piggy-bank', 'atual' => 0, 'anterior' => 0] : null,
        'condominial' => $permite('condominial') ? ['label' => 'Seguro Condomínio',        'icone' => 'buildings',  'atual' => 0, 'anterior' => 0] : null,
        'incendio'    => $permite('incendio')    ? ['label' => 'Seguro Incêndio',          'icone' => 'fire',       'atual' => 0, 'anterior' => 0] : null,
        'residencial' => $permite('residencial') ? ['label' => 'Seguro Residencial',       'icone' => 'house-line', 'atual' => 0, 'anterior' => 0] : null,
    ]);

    $mes_atual = date('Y-m');
    $mes_ant   = date('Y-m', mktime(1, 1, 1, (int) date('m') - 1, 1, (int) date('Y')));

    $aplicar_linhas = function (array $rows) use (&$data, $mes_atual, $mes_ant) {
        foreach ($rows as $row) {
            $tipo = $row['tipo'];
            if (!isset($data[$tipo])) continue;
            if ($row['mes'] === $mes_atual) $data[$tipo]['atual']    = (int) $row['total'];
            if ($row['mes'] === $mes_ant)   $data[$tipo]['anterior'] = (int) $row['total'];
        }
    };

    $blocosA = array_filter([
        $permite('fianca')   ? "SELECT 'fianca' AS tipo, data_atualizacao FROM seguro_fianca WHERE status IN ('Concluída','Concluida') AND data_atualizacao >= DATE_FORMAT(NOW() - INTERVAL 1 MONTH, '%Y-%m-01')" : null,
        $permite('auto')     ? "SELECT 'auto' AS tipo, data_atualizacao FROM seguro_auto WHERE status IN ('Concluída','Concluida') AND data_atualizacao >= DATE_FORMAT(NOW() - INTERVAL 1 MONTH, '%Y-%m-01')" : null,
        $permite('incendio') ? "SELECT 'incendio' AS tipo, data_atualizacao FROM seguro_incendio WHERE status IN ('Concluída','Concluida','Finalizado') AND data_atualizacao >= DATE_FORMAT(NOW() - INTERVAL 1 MONTH, '%Y-%m-01')" : null,
        $permite('residencial') ? "SELECT 'residencial' AS tipo, data_atualizacao FROM seguro_residencial WHERE status IN ('Concluída','Concluida') AND data_atualizacao >= DATE_FORMAT(NOW() - INTERVAL 1 MONTH, '%Y-%m-01')" : null,
    ]);
    if ($blocosA) {
        try {
            $aplicar_linhas(db_fetch_all(
                "SELECT tipo, DATE_FORMAT(data_atualizacao, '%Y-%m') AS mes, COUNT(*) AS total FROM (" . implode(' UNION ALL ', $blocosA) . ") t GROUP BY tipo, DATE_FORMAT(data_atualizacao, '%Y-%m')"
            ));
        } catch (Throwable $e) {}
    }

    // Título de capitalização e condomínio usam `criado_em` (sem coluna própria
    // de atualização) e são tabelas opcionais — somadas separadamente para não
    // zerar os contadores de fiança/auto caso não existam no ambiente.
    $blocosB = array_filter([
        $permite('titulo')      ? "SELECT 'titulo' AS tipo, criado_em FROM titulo_capitalizacao WHERE status IN ('Concluída','Concluida') AND criado_em >= DATE_FORMAT(NOW() - INTERVAL 1 MONTH, '%Y-%m-01')" : null,
        $permite('condominial') ? "SELECT 'condominial' AS tipo, criado_em FROM seguro_condominial WHERE status IN ('Concluída','Concluida') AND criado_em >= DATE_FORMAT(NOW() - INTERVAL 1 MONTH, '%Y-%m-01')" : null,
    ]);
    if ($blocosB) {
        try {
            $aplicar_linhas(db_fetch_all(
                "SELECT tipo, DATE_FORMAT(criado_em, '%Y-%m') AS mes, COUNT(*) AS total FROM (" . implode(' UNION ALL ', $blocosB) . ") t GROUP BY tipo, DATE_FORMAT(criado_em, '%Y-%m')"
            ));
        } catch (Throwable $e) {}
    }

    foreach ($data as &$item) {
        $item['delta'] = $item['atual'] - $item['anterior'];
    }

    return $data;
}

/**
 * Fila de cotações em análise (mais recentes primeiro, por data de criação) com nome da imobiliária.
 */
function admin_pending_queue(int $limit = 8, ?array $produtosPermitidos = null): array
{
    $rows = [];
    $statusEmAnalise = "('Em Analise','Em Análise','Em análise','Pré-aprovado')";
    $permite = fn(string $tipo) => $produtosPermitidos === null || in_array($tipo, $produtosPermitidos, true);

    $blocosA = array_filter([
        $permite('fianca') ? "SELECT 'fianca' AS tipo, tipo_ficha, nome_completo AS pretendente,
                       status, protocolo, imobiliaria_id, data_cadastro
                FROM seguro_fianca WHERE status IN {$statusEmAnalise}" : null,
        $permite('auto') ? "SELECT 'auto' AS tipo, NULL AS tipo_ficha, segurado_nome AS pretendente,
                       status, protocolo, imobiliaria_id, data_cadastro
                FROM seguro_auto WHERE status IN {$statusEmAnalise}" : null,
        $permite('residencial') ? "SELECT 'residencial' AS tipo, NULL AS tipo_ficha, nome_completo AS pretendente,
                       status, protocolo, imobiliaria_id, data_cadastro
                FROM seguro_residencial WHERE status IN {$statusEmAnalise}" : null,
    ]);
    if ($blocosA) {
        try {
            $rows = array_merge($rows, db_fetch_all(
                "SELECT t.*, i.nome AS imobiliaria_nome FROM (" . implode(' UNION ALL ', $blocosA) . ") t
                 LEFT JOIN imobiliarias i ON i.id = t.imobiliaria_id"
            ));
        } catch (Throwable $e) {}
    }

    // Título de capitalização e condomínio são tabelas opcionais — buscadas
    // separadamente para não zerar a fila principal caso não existam no
    // ambiente (mesmo racional das demais funções deste dashboard).
    $blocosB = array_filter([
        $permite('titulo') ? "SELECT 'titulo' AS tipo, NULL AS tipo_ficha,
                       COALESCE(NULLIF(inq_nome,''), prop_nome, '—') AS pretendente,
                       status, protocolo, imobiliaria_id, criado_em AS data_cadastro
                FROM titulo_capitalizacao WHERE status IN {$statusEmAnalise}" : null,
        $permite('condominial') ? "SELECT 'condominial' AS tipo, NULL AS tipo_ficha,
                       COALESCE(NULLIF(imovel_nome,''), sindico_nome, '—') AS pretendente,
                       status, protocolo, imobiliaria_id, criado_em AS data_cadastro
                FROM seguro_condominial WHERE status IN {$statusEmAnalise}" : null,
    ]);
    if ($blocosB) {
        try {
            $rows = array_merge($rows, db_fetch_all(
                "SELECT t.*, i.nome AS imobiliaria_nome FROM (" . implode(' UNION ALL ', $blocosB) . ") t
                 LEFT JOIN imobiliarias i ON i.id = t.imobiliaria_id"
            ));
        } catch (Throwable $e) {}
    }

    if ($permite('incendio')) {
        try {
            $incendio_rows = db_fetch_all(
                "SELECT t.*, i.nome AS imobiliaria_nome
                 FROM (
                    SELECT si.id, 'incendio' AS tipo, NULL AS tipo_ficha,
                           status, protocolo, imobiliaria_id, data_cadastro
                    FROM seguro_incendio si
                    WHERE status IN {$statusEmAnalise}
                 ) t
                 LEFT JOIN imobiliarias i ON i.id = t.imobiliaria_id"
            );
            foreach ($incendio_rows as &$ir) {
                $ir['pretendente'] = seguro_incendio_resumo_imoveis((int) $ir['id']);
            }
            unset($ir);
            $rows = array_merge($rows, $incendio_rows);
        } catch (Throwable $e) {}
    }

    usort($rows, fn($a, $b) => strcmp($b['data_cadastro'] ?? '', $a['data_cadastro'] ?? ''));

    return array_slice($rows, 0, $limit);
}

/**
 * Soma COUNT(*) de vários produtos pros KPIs do dashboard admin, pulando os
 * que não estão em $produtosPermitidos (null = sem restrição). Cada entrada
 * de $tabelasPorTipo é tipo_key => ['tabela' => ..., 'data_col' => ...] —
 * $dataMinima (quando informada) filtra por essa coluna, variando por
 * produto porque fiança/auto usam data_atualizacao e título/condomínio só
 * têm criado_em.
 */
function admin_kpi_total(array $tabelasPorTipo, array $statuses, ?array $produtosPermitidos = null, ?string $dataMinima = null): int
{
    $placeholders = implode(',', array_fill(0, count($statuses), '?'));
    $total = 0;

    foreach ($tabelasPorTipo as $tipo => $cfg) {
        if ($produtosPermitidos !== null && !in_array($tipo, $produtosPermitidos, true)) continue;

        $sql   = "SELECT COUNT(*) AS total FROM {$cfg['tabela']} WHERE status IN ({$placeholders})";
        $binds = $statuses;
        $types = str_repeat('s', count($statuses));
        if ($dataMinima !== null) {
            $sql    .= " AND {$cfg['data_col']} >= ?";
            $binds[] = $dataMinima;
            $types  .= 's';
        }
        $total += safe_count($sql, [$types, ...$binds]);
    }

    return $total;
}

/**
 * Produtos (tipo_key) que o usuário MPS logado pode ver em "Todas as
 * Cotações". Retorna null quando não há restrição (administrador completo,
 * mps_admin, ou operador sem nenhum perm_* marcado — configuração ausente
 * não deve virar "não vê nada"). Senão, a lista de tipo_key liberados pelas
 * colunas perm_fianca/perm_auto/perm_titulo/perm_condominial/perm_incendio
 * da tabela `usuario` (ver pages/admin/usuarios/editar.php).
 */
function admin_produtos_permitidos(object $user): ?array
{
    if (!empty($user->mps_admin)) return null;

    $mapa = [
        'fianca'      => $user->perm_fianca      ?? 'N',
        'auto'        => $user->perm_auto        ?? 'N',
        'titulo'      => $user->perm_titulo      ?? 'N',
        'condominial' => $user->perm_condominial ?? 'N',
        'incendio'    => $user->perm_incendio    ?? 'N',
        'residencial' => $user->perm_seguro_residencial ?? 'N',
    ];
    $permitidos = array_keys(array_filter($mapa, fn($v) => $v === 'S'));

    return $permitidos ? $permitidos : null;
}

/**
 * Mesma ideia de admin_produtos_permitidos(), só que pro lado imobiliária:
 * null = sem restrição (admin da imobiliária vê tudo), array = só os
 * produtos que o usuário tem perm_* = 'S'. Mesma regra já usada em
 * pages/index.php pro dashboard, extraída aqui pra reusar nas notificações.
 */
function imob_produtos_permitidos(object $user): ?array
{
    if (!empty($user->imob_admin)) return null;

    $mapa = [
        'fianca'      => $user->perm_fianca   ?? 'N',
        'auto'        => $user->perm_auto     ?? 'N',
        'titulo'      => $user->perm_titulo   ?? 'N',
        'condominial' => $user->perm_condo    ?? 'N',
        'incendio'    => $user->perm_incendio ?? 'N',
        'residencial' => $user->perm_seguro_residencial ?? 'N',
    ];
    $permitidos = array_keys(array_filter($mapa, fn($v) => $v === 'S'));

    return $permitidos ? $permitidos : null;
}

/**
 * Busca todas as cotações (todas as imobiliárias) dos 4 produtos, opcionalmente
 * filtrando por um único produto. Usado pela listagem e exportação admin.
 */
function admin_cotacoes_buscar_todas(string $produto = ''): array
{
    $rows = [];

    if ($produto === '' || $produto === 'fianca') {
        try {
            $rows = array_merge($rows, db_fetch_all(
                "SELECT
                    'fianca' AS origem,
                    sf.id, sf.protocolo, sf.tipo_ficha,
                    COALESCE(NULLIF(sf.nome_completo,''),'—') AS pretendente,
                    COALESCE(NULLIF(sf.cpf_cnpj,''), NULLIF(sf.cpf_cnpj_proprietario,''), NULLIF(sf.cnpj_empresa,''), '') AS cpf_cnpj,
                    sf.status,
                    COALESCE(sf.data_atualizacao, sf.data_cadastro) AS atualizado_em,
                    sf.data_cadastro,
                    sf.atendente_id,
                    sf.imobiliaria_id,
                    sf.origem AS origem_ficha,
                    sf.encaminhado_mps_detalhe,
                    sf.encaminhado_mps_em,
                    EXISTS (SELECT 1 FROM api_cotacoes ac WHERE ac.seguro_fianca_id = sf.id AND ac.apolice_id IS NOT NULL) AS api_aceita,
                    im.nome AS imob_nome,
                    ua.username AS atendente_nome,
                    ua.avatar AS atendente_avatar
                 FROM seguro_fianca sf
                 LEFT JOIN imobiliarias im ON im.id = sf.imobiliaria_id
                 LEFT JOIN usuario ua ON ua.id = sf.atendente_id"
            ));
        } catch (Throwable) {}
    }

    if ($produto === '' || $produto === 'auto') {
        try {
            $rows = array_merge($rows, db_fetch_all(
                "SELECT
                    'auto' AS origem,
                    sa.id, sa.protocolo, '' AS tipo_ficha,
                    COALESCE(NULLIF(sa.segurado_nome,''), sa.placa, '—') AS pretendente,
                    COALESCE(NULLIF(sa.segurado_cpf,''), NULLIF(sa.condutor_cpf,''), '') AS cpf_cnpj,
                    sa.status,
                    COALESCE(sa.data_atualizacao, sa.data_cadastro) AS atualizado_em,
                    COALESCE(sa.data_cadastro, sa.data_atualizacao) AS data_cadastro,
                    sa.atendente_id,
                    sa.imobiliaria_id,
                    im.nome AS imob_nome,
                    ua.username AS atendente_nome,
                    ua.avatar AS atendente_avatar
                 FROM seguro_auto sa
                 LEFT JOIN imobiliarias im ON im.id = sa.imobiliaria_id
                 LEFT JOIN usuario ua ON ua.id = sa.atendente_id"
            ));
        } catch (Throwable) {}
    }

    if ($produto === '' || $produto === 'titulo') {
        try {
            $rows = array_merge($rows, db_fetch_all(
                "SELECT
                    'titulo' AS origem,
                    tc.id, tc.protocolo, '' AS tipo_ficha,
                    COALESCE(NULLIF(tc.inq_nome,''), tc.prop_nome, '—') AS pretendente,
                    COALESCE(NULLIF(tc.inq_cpf,''), NULLIF(tc.inq_cnpj,''), NULLIF(tc.prop_cpf,''), NULLIF(tc.prop_cnpj,''), '') AS cpf_cnpj,
                    tc.status,
                    COALESCE(tc.data_atualizacao, tc.criado_em) AS atualizado_em,
                    tc.criado_em AS data_cadastro,
                    tc.atendente_id,
                    tc.imobiliaria_id,
                    im.nome AS imob_nome,
                    ua.username AS atendente_nome,
                    ua.avatar AS atendente_avatar
                 FROM titulo_capitalizacao tc
                 LEFT JOIN imobiliarias im ON im.id = tc.imobiliaria_id
                 LEFT JOIN usuario ua ON ua.id = tc.atendente_id"
            ));
        } catch (Throwable) {}
    }

    if ($produto === '' || $produto === 'condominial') {
        try {
            $rows = array_merge($rows, db_fetch_all(
                "SELECT
                    'condominial' AS origem,
                    sc.id, sc.protocolo, '' AS tipo_ficha,
                    COALESCE(NULLIF(sc.imovel_nome,''), sc.sindico_nome, '—') AS pretendente,
                    COALESCE(NULLIF(sc.imovel_cnpj,''), '') AS cpf_cnpj,
                    sc.status,
                    COALESCE(sc.data_atualizacao, sc.criado_em) AS atualizado_em,
                    sc.criado_em AS data_cadastro,
                    sc.atendente_id,
                    sc.imobiliaria_id,
                    im.nome AS imob_nome,
                    ua.username AS atendente_nome,
                    ua.avatar AS atendente_avatar
                 FROM seguro_condominial sc
                 LEFT JOIN imobiliarias im ON im.id = sc.imobiliaria_id
                 LEFT JOIN usuario ua ON ua.id = sc.atendente_id"
            ));
        } catch (Throwable) {}
    }

    if ($produto === '' || $produto === 'incendio') {
        try {
            $incendio_rows = db_fetch_all(
                "SELECT
                    'incendio' AS origem,
                    si.id, si.protocolo, '' AS tipo_ficha,
                    (SELECT GROUP_CONCAT(CONCAT_WS(',', sii.cpf_cnpj_locador, sii.cpf_cnpj_locatario) SEPARATOR ',')
                       FROM seguro_incendio_imoveis sii WHERE sii.seguro_incendio_id = si.id) AS cpf_cnpj,
                    si.status,
                    COALESCE(si.data_atualizacao, si.data_cadastro) AS atualizado_em,
                    si.data_cadastro,
                    si.atendente_id,
                    si.imobiliaria_id,
                    im.nome AS imob_nome,
                    ua.username AS atendente_nome,
                    ua.avatar AS atendente_avatar
                 FROM seguro_incendio si
                 LEFT JOIN imobiliarias im ON im.id = si.imobiliaria_id
                 LEFT JOIN usuario ua ON ua.id = si.atendente_id"
            );
            foreach ($incendio_rows as &$ir) {
                $ir['pretendente']         = seguro_incendio_resumo_imoveis((int) $ir['id']);
                $ir['incendio_imoveis']    = seguro_incendio_imoveis_tooltip((int) $ir['id']);
            }
            unset($ir);
            $rows = array_merge($rows, $incendio_rows);
        } catch (Throwable) {}
    }

    if ($produto === '' || $produto === 'residencial') {
        try {
            $rows = array_merge($rows, db_fetch_all(
                "SELECT
                    'residencial' AS origem,
                    sr.id, sr.protocolo, '' AS tipo_ficha,
                    COALESCE(NULLIF(sr.nome_completo,''),'—') AS pretendente,
                    COALESCE(sr.cpf_cnpj, '') AS cpf_cnpj,
                    sr.status,
                    COALESCE(sr.data_atualizacao, sr.data_cadastro) AS atualizado_em,
                    sr.data_cadastro,
                    sr.atendente_id,
                    sr.imobiliaria_id,
                    sr.origem AS origem_ficha,
                    sr.encaminhado_mps_detalhe,
                    sr.encaminhado_mps_em,
                    EXISTS (SELECT 1 FROM api_cotacoes ac WHERE ac.seguro_fianca_id = sr.id AND ac.apolice_id IS NOT NULL) AS api_aceita,
                    im.nome AS imob_nome,
                    ua.username AS atendente_nome,
                    ua.avatar AS atendente_avatar
                 FROM seguro_residencial sr
                 LEFT JOIN imobiliarias im ON im.id = sr.imobiliaria_id
                 LEFT JOIN usuario ua ON ua.id = sr.atendente_id"
            ));
        } catch (Throwable) {}
    }

    return $rows;
}

/**
 * Igual admin_cotacoes_buscar_todas(), mas inclui as colunas de timestamp de
 * cada etapa (criado_em, data_pre_analise, data_status_aguardando/em_analise,
 * biometria_concluida_em, data_status_aprovado/recusado, data_envio_cotacao,
 * data_status_concluido) usadas pelo Gerenciador pra calcular o tempo entre
 * cada etapa. Título/Condominial não têm pré-análise/aguardando/biometria/
 * envio — ficam NULL nessas duas. Mantida separada da função acima para não
 * alterar o shape que Todas as Cotações/Pipeline já consomem.
 */
function admin_cotacoes_buscar_tempos(): array
{
    $rows = [];

    try {
        $rows = array_merge($rows, db_fetch_all(
            "SELECT
                'fianca' AS origem, sf.id, sf.origem AS origem_ficha, sf.protocolo, sf.status,
                sf.data_cadastro AS criado_em, sf.data_pre_analise,
                sf.data_status_aguardando, sf.data_status_em_analise,
                (SELECT MIN(b.concluida_em) FROM seguro_fianca_biometrias b WHERE b.protocolo = sf.protocolo) AS biometria_concluida_em, sf.data_status_aprovado,
                sf.data_status_recusado, sf.data_envio_cotacao, sf.data_status_concluido,
                sf.atendente_id, sf.imobiliaria_id,
                im.nome AS imob_nome, ua.username AS atendente_nome, ua.avatar AS atendente_avatar
             FROM seguro_fianca sf
             LEFT JOIN imobiliarias im ON im.id = sf.imobiliaria_id
             LEFT JOIN usuario ua ON ua.id = sf.atendente_id"
        ));
    } catch (Throwable $e) { error_log('[gerenciador] Falha ao buscar cotações: ' . $e->getMessage()); }

    try {
        $rows = array_merge($rows, db_fetch_all(
            "SELECT
                'auto' AS origem, sa.protocolo, sa.status,
                sa.data_cadastro AS criado_em, NULL AS data_pre_analise,
                sa.data_status_aguardando, sa.data_status_em_analise,
                NULL AS biometria_concluida_em, sa.data_status_aprovado,
                sa.data_status_recusado, sa.data_envio_cotacao, sa.data_status_concluido,
                sa.atendente_id, sa.imobiliaria_id,
                im.nome AS imob_nome, ua.username AS atendente_nome, ua.avatar AS atendente_avatar
             FROM seguro_auto sa
             LEFT JOIN imobiliarias im ON im.id = sa.imobiliaria_id
             LEFT JOIN usuario ua ON ua.id = sa.atendente_id"
        ));
    } catch (Throwable $e) { error_log('[gerenciador] Falha ao buscar cotações: ' . $e->getMessage()); }

    try {
        $rows = array_merge($rows, db_fetch_all(
            "SELECT
                'titulo' AS origem, tc.protocolo, tc.status,
                tc.criado_em, NULL AS data_pre_analise,
                NULL AS data_status_aguardando, tc.data_status_em_analise,
                NULL AS biometria_concluida_em, tc.data_status_aprovado,
                tc.data_status_recusado, NULL AS data_envio_cotacao, tc.data_status_concluido,
                tc.atendente_id, tc.imobiliaria_id,
                im.nome AS imob_nome, ua.username AS atendente_nome, ua.avatar AS atendente_avatar
             FROM titulo_capitalizacao tc
             LEFT JOIN imobiliarias im ON im.id = tc.imobiliaria_id
             LEFT JOIN usuario ua ON ua.id = tc.atendente_id"
        ));
    } catch (Throwable $e) { error_log('[gerenciador] Falha ao buscar cotações: ' . $e->getMessage()); }

    try {
        $rows = array_merge($rows, db_fetch_all(
            "SELECT
                'condominial' AS origem, sc.protocolo, sc.status,
                sc.criado_em, NULL AS data_pre_analise,
                NULL AS data_status_aguardando, sc.data_status_em_analise,
                NULL AS biometria_concluida_em, sc.data_status_aprovado,
                sc.data_status_recusado, NULL AS data_envio_cotacao, sc.data_status_concluido,
                sc.atendente_id, sc.imobiliaria_id,
                im.nome AS imob_nome, ua.username AS atendente_nome, ua.avatar AS atendente_avatar
             FROM seguro_condominial sc
             LEFT JOIN imobiliarias im ON im.id = sc.imobiliaria_id
             LEFT JOIN usuario ua ON ua.id = sc.atendente_id"
        ));
    } catch (Throwable $e) { error_log('[gerenciador] Falha ao buscar cotações: ' . $e->getMessage()); }

    // Seguro Incêndio: o andamento é POR IMÓVEL (seguro_incendio_imoveis) — as
    // colunas data_status_* da remessa (seguro_incendio) quase nunca são
    // preenchidas, e o Gerenciador mostrava "Tempo médio: —" pra quem atende
    // Incêndio. As etapas da remessa saem dos imóveis:
    //   em análise   → o primeiro imóvel que entrou em análise
    //   aprovado     → a primeira resposta com preço (proposta ou, no fluxo
    //                  novo, cotação anexada)
    //   recusado     → só se a remessa inteira terminou recusada
    //   concluído    → remessa encerrada (Concluída/Finalizado): o último imóvel a terminar
    try {
        $rows = array_merge($rows, db_fetch_all(
            "SELECT
                'incendio' AS origem, si.protocolo, si.status,
                si.data_cadastro AS criado_em, NULL AS data_pre_analise,
                NULL AS data_status_aguardando,
                COALESCE(si.data_status_em_analise, imv.primeiro_em_analise) AS data_status_em_analise,
                NULL AS biometria_concluida_em,
                COALESCE(si.data_status_aprovado, imv.primeira_resposta) AS data_status_aprovado,
                COALESCE(si.data_status_recusado, IF(si.status IN ('Recusado', 'Recusado pelo cliente'), imv.ultima_recusa, NULL)) AS data_status_recusado,
                imv.primeira_cotacao AS data_envio_cotacao,
                IF(si.status IN ('Concluída', 'Concluida', 'Finalizado'), imv.ultimo_fim, NULL) AS data_status_concluido,
                si.atendente_id, si.imobiliaria_id,
                im.nome AS imob_nome, ua.username AS atendente_nome, ua.avatar AS atendente_avatar
             FROM seguro_incendio si
             LEFT JOIN (
                SELECT seguro_incendio_id,
                       MIN(data_status_em_analise) AS primeiro_em_analise,
                       MIN(LEAST(COALESCE(data_status_aprovado, data_status_cotacao_anexada), COALESCE(data_status_cotacao_anexada, data_status_aprovado))) AS primeira_resposta,
                       MIN(data_status_cotacao_anexada) AS primeira_cotacao,
                       MAX(data_status_recusado) AS ultima_recusa,
                       MAX(COALESCE(data_status_concluido, data_status_recusado, data_atualizacao)) AS ultimo_fim
                  FROM seguro_incendio_imoveis
                 GROUP BY seguro_incendio_id
             ) imv ON imv.seguro_incendio_id = si.id
             LEFT JOIN imobiliarias im ON im.id = si.imobiliaria_id
             LEFT JOIN usuario ua ON ua.id = si.atendente_id"
        ));
    } catch (Throwable $e) {
        error_log('[gerenciador] Falha ao buscar Seguro Incêndio: ' . $e->getMessage());
    }

    // Seguro Residencial: mesmo fluxo da Fiança (API das seguradoras ou manual),
    // sem pré-análise, biometria nem "envio da cotação".
    try {
        $rows = array_merge($rows, db_fetch_all(
            "SELECT
                'residencial' AS origem, sr.id, sr.origem AS origem_ficha, sr.protocolo, sr.status,
                sr.data_cadastro AS criado_em, NULL AS data_pre_analise,
                sr.data_status_aguardando, sr.data_status_em_analise,
                NULL AS biometria_concluida_em, sr.data_status_aprovado,
                sr.data_status_recusado, NULL AS data_envio_cotacao, sr.data_status_concluido,
                sr.atendente_id, sr.imobiliaria_id,
                im.nome AS imob_nome, ua.username AS atendente_nome, ua.avatar AS atendente_avatar
             FROM seguro_residencial sr
             LEFT JOIN imobiliarias im ON im.id = sr.imobiliaria_id
             LEFT JOIN usuario ua ON ua.id = sr.atendente_id"
        ));
    } catch (Throwable $e) {
        error_log('[gerenciador] Falha ao buscar Seguro Residencial: ' . $e->getMessage());
    }

    return $rows;
}

/**
 * Contadores por status (Pré-análise / Em análise / Aguardando / Aprovados /
 * Recusados / Concluídos), calculados ANTES do filtro de status rápido.
 */
function admin_cotacoes_contadores(array $rows): array
{
    $cont = [
        'todos'       => count($rows),
        'pre_analise' => 0,
        'em_analise'  => 0,
        'aguardando'  => 0,
        'aprovados'   => 0,
        'reprovados'  => 0,
        'desistencias'=> 0,
        'concluidos'  => 0,
        // API das seguradoras — contados à parte (uma ficha pode estar num
        // destes E num status acima, ex: encaminhada + Em análise na MPS).
        'api'             => 0,
        'api_seguradoras' => 0,
        'api_cotar'       => 0,
        'api_aceitas'     => 0,
        'api_recusadas'   => 0,
        // Etapas do painel (chips da listagem) — manual + API juntos.
        'acao_mps'        => 0,
        'andamento'       => 0,
        'com_cliente'     => 0,
    ];
    $pre_analise_statuses = ['Aguardando liberação', 'Aguardando liberacao', 'Aguardando preenchimento', 'Pré-análise aceita', 'Pre-analise aceita', 'Pré-análise recusada', 'Pre-analise recusada'];
    foreach ($rows as $r) {
        $st = (string)($r['status'] ?? '');
        if (in_array($st, $pre_analise_statuses, true))                                  $cont['pre_analise']++;
        elseif (in_array($st, ['Em Analise', 'Em Análise', 'Pré-aprovado', 'Aguardando aprovação', 'Aguardando nova cotação', 'Alteração solicitada'], true)) $cont['em_analise']++;
        elseif (in_array($st, ['Aguardando análise', 'Aguardando analise'], true))       $cont['aguardando']++;
        elseif (in_array($st, ['Aprovado', 'Cotação aprovada', 'Aguardando apólice', 'Aguardando contrato', 'Aguardando Pagamento', 'Pago'], true)) $cont['aprovados']++;
        elseif (cotacao_status_desistencia($st))                                          $cont['desistencias']++;
        elseif (cotacao_status_recusado($st))                                             $cont['reprovados']++;
        elseif (in_array($st, ['Concluída', 'Concluida', 'Concluído', 'Concluido', 'Finalizado'], true)) $cont['concluidos']++;

        $etapaPainel = admin_cotacao_etapa_painel($r);
        if ($etapaPainel !== null) $cont[$etapaPainel]++;

        if (canal_automatico((string) ($r['origem_ficha'] ?? ''))) {
            $cont['api']++;
            if ($st === 'Cotação API')               $cont['api_seguradoras']++;
            if (!empty($r['api_cotar']))             $cont['api_cotar']++;
            if (!empty($r['api_aceita']))            $cont['api_aceitas']++;
            if (cotacao_status_recusado($st))       $cont['api_recusadas']++;
        }
    }
    return $cont;
}

/**
 * Onde a cotação está no atendimento — a mesma régua pra manual e API
 * (painel da MPS e os filtros vindos dele):
 *   acao_mps     a MPS precisa agir (aguardando análise/liberação; na API:
 *                fazer cotação, enviar proposta pronta ou apólice emitida);
 *   andamento    em análise na MPS ou nas seguradoras, biometria, emissão;
 *   com_cliente  proposta enviada aguardando a decisão do cliente (ou a ficha
 *                completa, depois da pré-análise aceita).
 * null = encerrada ou fora dessas etapas (rascunho, pré-análise respondida...).
 *
 * @param array $row Linha de admin_cotacoes_buscar_todas() — com 'api_etapa' e
 *                   'api_cotar' nas fichas da API (admin_cotacoes_marcar_api).
 */
function admin_cotacao_etapa_painel(array $row): ?string
{
    $st = (string) ($row['status'] ?? '');
    if (!empty($row['api_etapa'])) {
        if (!empty($row['api_cotar'])) return 'acao_mps';
        return $row['api_etapa'] === 'proposta' ? 'com_cliente' : (in_array($row['api_etapa'], ['recusada'], true) ? null : 'andamento');
    }
    if (in_array($st, ['Aguardando análise', 'Aguardando analise', 'Aguardando liberação', 'Aguardando liberacao'], true)) return 'acao_mps';
    if (in_array($st, ['Em Analise', 'Em Análise', 'Pré-aprovado', 'Aguardando aprovação', 'Aguardando nova cotação', 'Alteração solicitada', 'Aguardando biometria', 'Aguardando apólice', 'Aguardando contrato'], true)) return 'andamento';
    if (in_array($st, ['Aprovado', 'Cotação aprovada', 'Aguardando preenchimento', 'Pré-análise aceita', 'Pre-analise aceita'], true)) return 'com_cliente';
    return null;
}

/**
 * Marca nas linhas de admin_cotacoes_buscar_todas() as fichas da API (ou da
 * Segimob) em andamento: 'api_etapa' (etapa do fluxo) e 'api_cotar' (a MPS
 * precisa agir) — cada ficha pelo módulo do canal dela.
 */
function admin_cotacoes_marcar_api(array $rows): array
{
    $etapas = api_etapas_ativas();
    // Segimob: só a análise ('Cotação API') — a MPS não age nela (o resultado pré-aprova ou recusa sozinho).
    $idsSeg = array_map(fn($r) => (int) $r['id'], array_filter($rows, fn($r) => ($r['origem'] ?? '') === 'fianca'
        && ($r['origem_ficha'] ?? '') === SEGIMOB_ORIGEM && ($r['status'] ?? '') === SEGIMOB_STATUS_FICHA));
    $etapasSeg = array_map(fn($lote) => ['etapa' => segimob_status_resumo_lote($lote)['etapa'], 'mps' => false], segimob_status_lote($idsSeg));
    foreach ($rows as &$r) {
        $api = match (true) {
            ($r['origem'] ?? '') === 'fianca' && ($r['origem_ficha'] ?? '') === SEGIMOB_ORIGEM => $etapasSeg[(int) $r['id']] ?? null,
            in_array($r['origem'] ?? '', ['fianca', 'residencial'], true) => $etapas[(int) $r['id']] ?? null,
            default => null,
        };
        $r['api_etapa'] = $api['etapa'] ?? null;
        $r['api_cotar'] = (bool) ($api['mps'] ?? false);
    }
    unset($r);
    return $rows;
}

/**
 * Filtro "Canal" (manual / API / Segimob) sobre as linhas de
 * admin_cotacoes_buscar_todas() / admin_cotacoes_buscar_tempos() — antes dos
 * contadores, pra os números serem do canal escolhido. '' = todos.
 */
function admin_cotacoes_filtrar_canal(array $rows, string $canal): array
{
    if ($canal === '') {
        return $rows;
    }
    return array_values(array_filter($rows, fn($r) => canal_da_cotacao((string) ($r['origem'] ?? ''), $r['origem_ficha'] ?? null) === $canal));
}

/**
 * Aplica o filtro de status rápido (chips) sobre a lista já carregada.
 */
function admin_cotacoes_filtrar_status(array $rows, string $status_rapido): array
{
    $pre_analise_statuses = ['Aguardando liberação', 'Aguardando liberacao', 'Aguardando preenchimento', 'Pré-análise aceita', 'Pre-analise aceita', 'Pré-análise recusada', 'Pre-analise recusada'];

    return match ($status_rapido) {
        'pre_analise' => array_values(array_filter($rows, fn($r) => in_array($r['status'] ?? '', $pre_analise_statuses, true))),
        'em_analise'  => array_values(array_filter($rows, fn($r) => in_array($r['status'] ?? '', ['Em Analise', 'Em Análise', 'Pré-aprovado', 'Aguardando aprovação', 'Aguardando nova cotação', 'Alteração solicitada'], true))),
        'aguardando'  => array_values(array_filter($rows, fn($r) => in_array($r['status'] ?? '', ['Aguardando análise', 'Aguardando analise'], true))),
        'aprovados'   => array_values(array_filter($rows, fn($r) => in_array($r['status'] ?? '', ['Aprovado', 'Cotação aprovada', 'Aguardando apólice', 'Aguardando contrato', 'Aguardando Pagamento', 'Pago'], true))),
        'reprovados'  => array_values(array_filter($rows, fn($r) => cotacao_status_recusado((string) ($r['status'] ?? '')))),
        'desistencias'=> array_values(array_filter($rows, fn($r) => cotacao_status_desistencia((string) ($r['status'] ?? '')))),
        'concluidos'  => array_values(array_filter($rows, fn($r) => in_array($r['status'] ?? '', ['Concluída', 'Concluida', 'Concluído', 'Concluido', 'Finalizado'], true))),
        // API das seguradoras (Fiança).
        'api'             => array_values(array_filter($rows, fn($r) => canal_automatico((string) ($r['origem_ficha'] ?? '')))),
        'api_seguradoras' => array_values(array_filter($rows, fn($r) => ($r['status'] ?? '') === 'Cotação API')),
        'api_cotar'       => array_values(array_filter($rows, fn($r) => !empty($r['api_cotar']))),
        'api_aceitas'     => array_values(array_filter($rows, fn($r) => !empty($r['api_aceita']))),
        'api_recusadas'   => array_values(array_filter($rows, fn($r) => canal_automatico((string) ($r['origem_ficha'] ?? '')) && cotacao_status_recusado((string) ($r['status'] ?? '')))),
        // Etapas do painel da MPS (manual + API juntos — admin_cotacao_etapa_painel).
        'acao_mps', 'andamento', 'com_cliente' => array_values(array_filter($rows, fn($r) => admin_cotacao_etapa_painel($r) === $status_rapido)),
        default       => $rows,
    };
}

/**
 * Avatar circular pequeno (ver .avatar-sm em cotacoes.css) — foto do usuário
 * quando existe (coluna usuario.avatar), senão a inicial do nome. Mesmo
 * componente usado em cotações, rascunhos, chamados e rodízio.
 */
function avatar_sm(?string $avatarUrl, string $nome, string $class = 'avatar-sm'): string
{
    if ($avatarUrl) {
        return '<img class="' . e($class) . ' avatar-sm-img" src="' . e($avatarUrl) . '" alt="">';
    }

    $iniciais = mb_strtoupper(mb_substr(trim($nome), 0, 1));
    return '<span class="' . e($class) . '">' . e($iniciais) . '</span>';
}

/**
 * Rótulo de exibição amigável para um status bruto do banco.
 */
function admin_status_label(string $status, ?string $tipoKey = null): string
{
    // Fluxo específico do Título de Capitalização: "Em Analise" (valor salvo
    // no banco, inalterado) aparece como "Aguardando Proposta" — nome mais
    // claro do que está acontecendo de fato nesse momento (a MPS ainda não
    // enviou a proposta/boleto). Não afeta o "Em análise" de outros produtos.
    if ($tipoKey === 'titulo' && in_array($status, ['Em Analise', 'Em Análise'], true)) {
        return 'Aguardando Proposta';
    }

    return match ($status) {
        'Aguardando liberação', 'Aguardando liberacao' => 'Aguardando liberação',
        'Pré-análise aceita',   'Pre-analise aceita'    => 'Pré-análise aceita',
        'Pré-análise recusada', 'Pre-analise recusada'  => 'Pré-análise recusada',
        'Aguardando preenchimento'                      => 'Aguardando preenchimento',
        'Rascunho'                                       => 'Rascunho',
        'Aguardando análise', 'Aguardando analise'      => 'Aguardando análise',
        'Em Analise', 'Em Análise'                      => 'Em análise',
        // Análise nas seguradoras (API/Segimob) — pra quem vê é só "Em análise".
        'Cotação API'                                    => 'Em análise',
        'Pré-aprovado'                                   => 'Pré-aprovado',
        STATUS_DESISTENCIA, 'Desistencia'                => 'Desistência',
        'Concluída', 'Concluida', 'Concluído', 'Concluido' => 'Concluída',
        default => $status !== '' ? $status : '—',
    };
}

/**
 * Gera o HTML de um custom select.
 *
 * @param string      $name        Atributo name do input hidden.
 * @param array       $options     Associativo [value => label] ou lista [label].
 * @param string      $placeholder Texto exibido quando nenhum valor está selecionado.
 * @param string|null $selected    Valor pré-selecionado.
 * @param string      $class       Classes extras no wrapper (ex: 'has-icon').
 * @param bool        $required    Se true, adiciona required no input hidden.
 *
 * Uso:
 *   <?= custom_select('estado_civil', ['Solteiro','Casado','Divorciado'], 'Selecione...') ?>
 *   <?= custom_select('vinculo', $vinculos, 'Selecione...', $current, '', true) ?>
 */
function custom_select(
    string $name,
    array  $options,
    string $placeholder = 'Selecione...',
    ?string $selected   = null,
    string $class       = '',
    bool   $required    = false,
    array  $wrapperAttrs = []
): string {
    $triggerClass = 'custom-select-trigger field-input' . ($class ? " {$class}" : '');
    $hiddenAttrs  = $required ? ' required' : '';
    $extraAttrs   = '';
    foreach ($wrapperAttrs as $attr => $val) {
        $extraAttrs .= ' ' . e($attr) . '="' . e($val) . '"';
    }

    $currentLabel = e($placeholder);
    $isPlaceholder = true;

    // Normaliza: se for uma lista sequencial (0,1,2...), usa valor == label.
    // Não dá pra decidir isso por chave (is_int($k)) dentro do loop: o PHP
    // converte automaticamente chaves-string numéricas em int (ex: '21' => 'x'
    // vira [21 => 'x']), o que faria um array [id_do_usuario => nome] ser
    // tratado como lista e perder o id de verdade, usando o nome como valor.
    $isList = count($options) === 0 || array_keys($options) === range(0, count($options) - 1);

    $normalized = [];
    foreach ($options as $k => $v) {
        $val   = $isList ? $v : $k;
        $label = $v;
        $normalized[(string)$val] = $label;
        if ((string)$val === (string)$selected) {
            $currentLabel  = e($label);
            $isPlaceholder = false;
        }
    }

    $valueClass = $isPlaceholder ? 'custom-select-value is-placeholder' : 'custom-select-value';
    $caretSvg   = icon('caret-down');

    $html  = "<div data-custom-select class=\"custom-select\" data-placeholder=\"" . e($placeholder) . "\"{$extraAttrs}>\n";
    $html .= "  <button type=\"button\" class=\"{$triggerClass}\" role=\"combobox\" aria-haspopup=\"listbox\" aria-expanded=\"false\">";
    $html .= "<span class=\"{$valueClass}\">{$currentLabel}</span>";
    $html .= "<span class=\"custom-select-caret\">{$caretSvg}</span>";
    $html .= "</button>\n";
    $html .= "  <ul class=\"custom-select-dropdown\" role=\"listbox\">\n";

    // Opção placeholder
    $selClass = ($selected === null || $selected === '') ? ' is-selected' : '';
    $html .= "    <li class=\"custom-select-option is-placeholder{$selClass}\" data-value=\"\" role=\"option\">" . e($placeholder) . "</li>\n";

    foreach ($normalized as $val => $label) {
        $selClass = ((string)$val === (string)$selected) ? ' is-selected' : '';
        $html .= "    <li class=\"custom-select-option{$selClass}\" data-value=\"" . e($val) . "\" role=\"option\">" . e($label) . "</li>\n";
    }

    $html .= "  </ul>\n";
    $html .= "  <input type=\"hidden\" name=\"" . e($name) . "\" value=\"" . e($selected ?? '') . "\"{$hiddenAttrs}>\n";
    $html .= "</div>";

    return $html;
}

/**
 * Campo de data com calendário próprio (ver .date-picker em components.css
 * e date-picker.js) — mesmo padrão visual/estrutural do custom_select().
 * O valor (ISO, YYYY-MM-DD) fica no input hidden; o calendário é montado
 * inteiramente no JS (mês/ano corrente, navegação).
 */
function date_picker(string $id, string $placeholder = 'DD/MM/AAAA', ?string $value = null): string
{
    $caret       = icon('caret-down');
    $temValor    = $value && preg_match('/^\d{4}-\d{2}-\d{2}$/', $value);
    $valueClass  = $temValor ? 'date-picker-value' : 'date-picker-value is-placeholder';
    $valueLabel  = $temValor ? e(date('d/m/Y', strtotime($value))) : e($placeholder);

    $html  = "<div data-date-picker class=\"date-picker\" data-placeholder=\"" . e($placeholder) . "\">\n";
    $html .= "  <button type=\"button\" class=\"date-picker-trigger field-input has-icon\" aria-haspopup=\"dialog\" aria-expanded=\"false\">";
    $html .= "<span class=\"field-input-icon\">" . icon('calendar-blank') . "</span>";
    $html .= "<span class=\"{$valueClass}\">{$valueLabel}</span>";
    $html .= "</button>\n";
    $html .= "  <div class=\"date-picker-dropdown\" role=\"dialog\" aria-label=\"Selecionar data\">\n";
    $html .= "    <div class=\"date-picker-cal-header\">\n";
    $html .= "      <button type=\"button\" class=\"date-picker-nav\" data-nav=\"-1\" aria-label=\"M\u{ea}s anterior\"><span class=\"date-picker-nav-icon date-picker-nav-prev\">{$caret}</span></button>\n";
    $html .= "      <span class=\"date-picker-month-label\"></span>\n";
    $html .= "      <button type=\"button\" class=\"date-picker-nav\" data-nav=\"1\" aria-label=\"Pr\u{f3}ximo m\u{ea}s\"><span class=\"date-picker-nav-icon date-picker-nav-next\">{$caret}</span></button>\n";
    $html .= "    </div>\n";
    $html .= "    <div class=\"date-picker-weekdays\"><span>D</span><span>S</span><span>T</span><span>Q</span><span>Q</span><span>S</span><span>S</span></div>\n";
    $html .= "    <div class=\"date-picker-days\"></div>\n";
    $html .= "    <div class=\"date-picker-footer\">\n";
    $html .= "      <button type=\"button\" class=\"date-picker-footer-btn\" data-action=\"hoje\">Hoje</button>\n";
    $html .= "      <button type=\"button\" class=\"date-picker-footer-btn\" data-action=\"limpar\">Limpar</button>\n";
    $html .= "    </div>\n";
    $html .= "  </div>\n";
    $html .= "  <input type=\"hidden\" id=\"" . e($id) . "\" name=\"" . e($id) . "\" value=\"" . ($temValor ? e($value) : '') . "\">\n";
    $html .= "</div>";

    return $html;
}

/**
 * Campo de horário com dropdown de horas/minutos próprio (ver .time-picker em
 * components.css e time-picker.js) — mesmo padrão visual/estrutural do
 * date_picker(), evita o seletor nativo do navegador (<input type="time">),
 * que não segue o design system. Valor no input hidden em "HH:MM".
 */
function time_picker(string $id, string $placeholder = '--:--', ?string $value = null): string
{
    $temValor   = $value && preg_match('/^\d{2}:\d{2}$/', $value);
    $valueClass = $temValor ? 'time-picker-value' : 'time-picker-value is-placeholder';
    $valueLabel = $temValor ? e($value) : e($placeholder);

    $html  = "<div data-time-picker class=\"time-picker\" data-placeholder=\"" . e($placeholder) . "\">\n";
    $html .= "  <button type=\"button\" class=\"time-picker-trigger field-input has-icon\" aria-haspopup=\"dialog\" aria-expanded=\"false\">";
    $html .= "<span class=\"field-input-icon\">" . icon('clock') . "</span>";
    $html .= "<span class=\"{$valueClass}\">{$valueLabel}</span>";
    $html .= "</button>\n";
    $html .= "  <div class=\"time-picker-dropdown\" role=\"dialog\" aria-label=\"Selecionar hor\u{e1}rio\">\n";
    $html .= "    <div class=\"time-picker-cols\">\n";
    $html .= "      <div class=\"time-picker-col\" data-col=\"hora\">\n";
    for ($h = 0; $h < 24; $h++) {
        $hh = str_pad((string) $h, 2, '0', STR_PAD_LEFT);
        $html .= "        <button type=\"button\" class=\"time-picker-opt\" data-val=\"{$hh}\">{$hh}</button>\n";
    }
    $html .= "      </div>\n";
    $html .= "      <div class=\"time-picker-col\" data-col=\"minuto\">\n";
    for ($m = 0; $m < 60; $m++) {
        $mm = str_pad((string) $m, 2, '0', STR_PAD_LEFT);
        $html .= "        <button type=\"button\" class=\"time-picker-opt\" data-val=\"{$mm}\">{$mm}</button>\n";
    }
    $html .= "      </div>\n";
    $html .= "    </div>\n";
    $html .= "    <div class=\"date-picker-footer\">\n";
    $html .= "      <button type=\"button\" class=\"date-picker-footer-btn\" data-action=\"agora\">Agora</button>\n";
    $html .= "      <button type=\"button\" class=\"date-picker-footer-btn\" data-action=\"limpar\">Limpar</button>\n";
    $html .= "    </div>\n";
    $html .= "  </div>\n";
    $html .= "  <input type=\"hidden\" id=\"" . e($id) . "\" value=\"" . ($temValor ? e($value) : '') . "\">\n";
    $html .= "</div>";

    return $html;
}

/**
 * Statuses finais de recusa de uma cotação: pela MPS ("Recusado"/"Recusada") ou
 * pelo cliente, quando a imobiliária recusa as propostas e a MPS encerra o caso
 * ("Recusado pelo cliente", ver includes/acao/cotacao-recusar-cliente.php).
 */
const STATUS_RECUSADO_CLIENTE = 'Recusado pelo cliente';

function statuses_recusados(): array
{
    return ['Recusado', 'Recusada', STATUS_RECUSADO_CLIENTE];
}

function cotacao_status_recusado(string $status): bool
{
    return in_array($status, statuses_recusados(), true);
}

/**
 * Desistência: o cliente/imobiliária abandona a cotação antes do desfecho.
 * É um status final próprio, separado das recusas (que são decisão de análise),
 * gravado por includes/acao/cotacao-desistir.php.
 */
const STATUS_DESISTENCIA = 'Desistência';

function cotacao_status_desistencia(string $status): bool
{
    return in_array($status, [STATUS_DESISTENCIA, 'Desistencia'], true);
}

/**
 * Classe CSS do badge de status (ver .status-pill-* em dashboard.css).
 * Strings de status idênticas ao sistema legado (mps-antigo).
 */
function status_pill_class(string $status): string
{
    return match ($status) {
        // Cada status com a sua cor (ver .status-pill-* em dashboard.css);
        // grafias diferentes do mesmo status dividem a cor.
        'Aprovado', 'Aprovada', 'Emitida', 'Pré-análise aceita', 'Pre-analise aceita' => 'status-pill-aprovado',
        'Concluída', 'Concluida', 'Concluído', 'Concluido'     => 'status-pill-concluida',
        'Recusado', 'Recusada', STATUS_RECUSADO_CLIENTE, 'Pré-análise recusada', 'Pre-analise recusada' => 'status-pill-recusado',
        'Em Analise', 'Em Análise', 'Em análise'               => 'status-pill-analise',
        'Aguardando análise', 'Aguardando analise'             => 'status-pill-aguardando',
        'Pré-aprovado'                                         => 'status-pill-pre-aprovado',
        'Aguardando liberação', 'Aguardando liberacao'         => 'status-pill-liberacao',
        'Aguardando preenchimento'                             => 'status-pill-preenchimento',
        // Fluxo específico do Título de Capitalização (proposta+boleto -> pagamento -> certificado).
        'Aguardando Proposta'  => 'status-pill-proposta',
        'Aguardando Pagamento' => 'status-pill-pagamento',
        'Pago'                 => 'status-pill-pago',
        STATUS_DESISTENCIA, 'Desistencia' => 'status-pill-desistencia',
        // Agregado de remessa com N imóveis (Seguro Incêndio) já toda
        // resolvida, mas possivelmente com resultados mistos entre os
        // imóveis — ver seguro_incendio_recalcular_status().
        'Finalizado' => 'status-pill-finalizado',
        // Fluxo novo do Seguro Incêndio (ver migration 078).
        'Aguardando aprovação' => 'status-pill-pendente',
        'Aguardando nova cotação', 'Alteração solicitada' => 'status-pill-alteracao',
        'Cotação aprovada'     => 'status-pill-cotacao-aprovada',
        'Aguardando apólice'   => 'status-pill-apolice',
        // Fiança: apólice anexada, falta o contrato de locação da imobiliária.
        'Aguardando contrato'  => 'status-pill-contrato',
        'Rascunho'             => 'status-pill-rascunho',
        // Ficha Fiança API ainda com a seguradora (fora da fila da MPS).
        'Cotação API'          => 'status-pill-analise',
        default => 'status-pill-pendente',
    };
}

/**
 * Classifica um status bruto de cotação num dos 7 estágios usados nas KPIs de
 * /admin/cotacoes e nas colunas do /admin/pipeline — mesma lógica nos dois
 * lugares, pra não desalinhar as contagens. Retorna null se não reconhecer.
 */
function classificar_status_cotacao(string $status): ?string
{
    $pre_analise_statuses = [
        'Aguardando liberação', 'Aguardando liberacao', 'Aguardando preenchimento',
        'Pré-análise aceita', 'Pre-analise aceita', 'Pré-análise recusada', 'Pre-analise recusada',
    ];
    $concluido_statuses = ['Concluída', 'Concluida', 'Concluído', 'Concluido', 'Finalizado'];

    if (in_array($status, $pre_analise_statuses, true)) return 'pre_analise';
    if (in_array($status, $concluido_statuses, true))   return 'concluidos';
    if ($status === 'Pré-aprovado')                     return 'em_analise';
    if (cotacao_status_desistencia($status))            return 'desistencias';

    $st = mb_strtolower(trim($status));
    if (str_contains($st, 'em an'))         return 'em_analise';
    if (str_contains($st, 'aguardando an')) return 'aguardando';
    if (str_contains($st, 'aprovad'))       return 'aprovados';
    if (str_contains($st, 'recusad'))       return 'recusados';

    return null;
}

/**
 * Retorna a URL de detalhe admin para um protocolo, deduzindo o tipo pelo prefixo.
 */
function cotacao_admin_url(string $protocolo): string
{
    $upper = strtoupper($protocolo);
    if (str_starts_with($upper, 'AUTO')) return url("/admin/cotacao/auto/{$protocolo}");
    if (str_starts_with($upper, 'TC'))   return url("/admin/cotacao/titulo/{$protocolo}");
    if (str_starts_with($upper, 'COND')) return url("/admin/cotacao/condominial/{$protocolo}");
    if (str_starts_with($upper, 'INC'))  return url("/admin/cotacao/incendio/{$protocolo}");
    if (str_starts_with($upper, 'RES'))  return url("/admin/cotacao/residencial/{$protocolo}");
    return url("/admin/cotacao/fianca/{$protocolo}");
}

/**
 * Retorna a URL de detalhe do lado imobiliária para um protocolo, deduzindo
 * o tipo pelo prefixo (mesmo critério de cotacao_admin_url).
 */
function cotacao_imob_url(string $protocolo): string
{
    $upper = strtoupper($protocolo);
    if (str_starts_with($upper, 'AUTO')) return url("/cotacao/auto/{$protocolo}");
    if (str_starts_with($upper, 'TC'))   return url("/cotacao/titulo/{$protocolo}");
    if (str_starts_with($upper, 'COND')) return url("/cotacao/condominial/{$protocolo}");
    if (str_starts_with($upper, 'INC'))  return url("/cotacao/incendio/{$protocolo}");
    if (str_starts_with($upper, 'RES'))  return url("/cotacao/residencial/{$protocolo}");
    return url("/cotacao/fianca/{$protocolo}");
}

/**
 * Resolve um protocolo para a tabela/tipo de cotação correspondente, deduzindo
 * pelo prefixo (mesmo critério de cotacao_admin_url) e buscando a linha real.
 * Fonte única de verdade usada pelos endpoints de progressão de status e pelo
 * chat/histórico — substitui os ternários "$is_auto" espalhados no código.
 *
 * Retorna null se o protocolo não existir na tabela correspondente.
 */
function cotacao_resolver(string $protocolo): ?array
{
    $upper = strtoupper($protocolo);

    if (str_starts_with($upper, 'AUTO')) {
        $tipo = 'auto';
    } elseif (str_starts_with($upper, 'TC')) {
        $tipo = 'titulo';
    } elseif (str_starts_with($upper, 'COND')) {
        $tipo = 'condominial';
    } elseif (str_starts_with($upper, 'INC')) {
        $tipo = 'incendio';
    } elseif (str_starts_with($upper, 'RES')) {
        $tipo = 'residencial';
    } else {
        $tipo = 'fianca';
    }

    $config = [
        'fianca'      => ['tabela' => 'seguro_fianca',         'tabela_anexos' => 'cotacoes_anexos'],
        'auto'        => ['tabela' => 'seguro_auto',           'tabela_anexos' => 'cotacoes_anexos_auto'],
        'titulo'      => ['tabela' => 'titulo_capitalizacao',  'tabela_anexos' => 'cotacoes_anexos_titulo'],
        'condominial' => ['tabela' => 'seguro_condominial',    'tabela_anexos' => 'cotacoes_anexos_condominial'],
        'incendio'    => ['tabela' => 'seguro_incendio',       'tabela_anexos' => 'cotacoes_anexos_incendio'],
        'residencial' => ['tabela' => 'seguro_residencial',    'tabela_anexos' => 'cotacoes_anexos_residencial'],
    ][$tipo];

    $tabela = $config['tabela'];
    $row    = db_fetch_one("SELECT * FROM {$tabela} WHERE protocolo = ? LIMIT 1", ['s', $protocolo]);
    if (!$row) {
        return null;
    }

    return [
        'tipo'           => $tipo,
        'tabela'         => $tabela,
        'tabela_anexos'  => $config['tabela_anexos'],
        'id'             => (int) $row['id'],
        'imobiliaria_id' => (int) $row['imobiliaria_id'],
        // usuario_id = quem da imobiliária criou a cotação. atendente_id (em
        // $row) é o atendente da MPS designado pelo funil de rodízio — pessoa
        // diferente, não usar pra checar "de quem é essa cotação".
        'usuario_id'     => isset($row['usuario_id']) ? (int) $row['usuario_id'] : null,
        'status'         => $row['status'],
        'row'            => $row,
    ];
}

/**
 * Checa se o usuário logado pode ver/agir numa cotação já resolvida por
 * cotacao_resolver(). Admin MPS (role='admin') sempre pode. Do lado
 * imobiliária: admin da imobiliária (imob_admin) vê tudo da própria
 * imobiliária; operador comum só a própria (usuario_id = uid dele, ou seja,
 * quem da imobiliária criou a cotação) — mesma regra de
 * includes/acao/cotacoes-listar.php.
 */
function cotacao_pode_acessar(array $resolvido, object $user): bool
{
    if (($user->role ?? '') === 'admin') {
        return true;
    }
    if ($resolvido['imobiliaria_id'] !== (int) ($user->imobiliaria_id ?? 0)) {
        return false;
    }
    if (!empty($user->imob_admin)) {
        return true;
    }
    return $resolvido['usuario_id'] === (int) ($user->uid ?? 0);
}

/**
 * Monta a URL de detalhe de uma cotação, pro papel certo — usada pra
 * "clicar na notificação e ir direto pra cotação" (sino, toast, popup do SO).
 */
function cotacao_notif_url(string $tipo, string $protocolo, string $papel): string
{
    $tipo = in_array($tipo, ['fianca', 'auto', 'titulo', 'condominial', 'incendio', 'residencial'], true) ? $tipo : 'fianca';
    $base = $papel === 'admin' ? "/admin/cotacao/{$tipo}" : "/cotacao/{$tipo}";
    return "{$base}/" . urlencode($protocolo);
}

/**
 * Se o status de uma cotação é terminal (não aceita mais progressão nem
 * mensagens novas no chat da cotação).
 */
function cotacao_status_encerrado(string $status): bool
{
    return cotacao_status_recusado($status)
        || cotacao_status_desistencia($status)
        || in_array($status, ['Concluída', 'Concluida', 'Concluído', 'Concluido', 'Finalizado'], true);
}

/**
 * Recalcula o status AGREGADO de uma remessa de Seguro Incêndio a partir do
 * status de cada imóvel dela (seguro_incendio_imoveis) — cada imóvel tem
 * seu próprio ciclo de vida (Aguardando análise → Em Análise → Aprovado/
 * Recusado/Desistência), independente dos outros da mesma remessa, mas
 * `seguro_incendio.status` precisa continuar existindo pra não quebrar
 * listagens/dashboards/fila de atendimento que só conhecem o protocolo.
 * Chamada no fim de cada ação de status por imóvel
 * (cotacao-incendio-imovel-*.php).
 *
 * Regra: todos ainda "Aguardando análise" → agregado "Aguardando análise";
 * algum já andou mas nem todos terminaram → "Em Analise" (mantém a remessa
 * na fila de "em andamento"); todos em estado terminal (Aprovado, Concluída,
 * Recusado, Recusado pelo cliente ou desistência) → "Finalizado" — NUNCA
 * "Aprovado", porque a remessa pode ter uma mistura de resultados (ex: 1
 * aprovado, 1 recusado, 1 desistência) e chamar isso de "Aprovado" seria
 * enganoso. O resultado de cada imóvel se vê no detalhe; o agregado só
 * precisa dizer "ainda tem pendência" vs "não tem mais nada a fazer".
 */
function seguro_incendio_recalcular_status(int $seguroIncendioId): void
{
    $imoveis = db_fetch_all(
        'SELECT status FROM seguro_incendio_imoveis WHERE seguro_incendio_id = ?',
        ['i', $seguroIncendioId]
    );
    if (!$imoveis) return;

    // Remessa com 1 imóvel: a cotação segue exatamente o status do imóvel.
    if (count($imoveis) === 1) {
        db_execute(
            'UPDATE seguro_incendio SET status = ?, data_atualizacao = NOW() WHERE id = ?',
            ['si', (string) $imoveis[0]['status'], $seguroIncendioId]
        );
        return;
    }

    // 'Aprovado' = proposta enviada, aguardando a imobiliária aceitar ou
    // recusar — NÃO é terminal (cotacao_status_encerrado() não o considera
    // encerrado). Só entra como "Finalizado" quando a imobiliária já decidiu
    // (Concluída/Recusado/Desistência).
    $todos_aguardando        = true;
    $todos_encerrados        = true;
    $todos_aprovado_ou_encerrado = true;
    foreach ($imoveis as $im) {
        $s = (string) $im['status'];
        if ($s !== 'Aguardando análise') $todos_aguardando = false;
        if (!cotacao_status_recusado($s) && !cotacao_status_desistencia($s) && !in_array($s, ['Concluída', 'Concluida', 'Concluído', 'Concluido'], true)) $todos_encerrados = false;
        if ($s !== 'Aprovado' && !cotacao_status_encerrado($s)) $todos_aprovado_ou_encerrado = false;
    }

    $agregado = $todos_aguardando
        ? 'Aguardando análise'
        : ($todos_encerrados
            ? 'Finalizado'
            : ($todos_aprovado_ou_encerrado ? 'Aprovado' : 'Em Analise'));

    db_execute(
        'UPDATE seguro_incendio SET status = ?, data_atualizacao = NOW() WHERE id = ?',
        ['si', $agregado, $seguroIncendioId]
    );
}

/**
 * Texto da coluna "pretendente" na listagem: com 1 imóvel, o nome do
 * pretendente (locatário) — sem ele, "1 Imóvel"; com vários, "N Imóveis"
 * (os nomes ficam no tooltip de seguro_incendio_imoveis_tooltip(), pra não
 * estourar a coluna da tabela).
 */
function seguro_incendio_resumo_imoveis(int $seguroIncendioId): string
{
    $imoveis = db_fetch_all(
        'SELECT locatario FROM seguro_incendio_imoveis WHERE seguro_incendio_id = ?',
        ['i', $seguroIncendioId]
    );
    $total = count($imoveis);

    if ($total === 1) {
        $nome = trim((string) ($imoveis[0]['locatario'] ?? ''));
        return $nome !== '' ? $nome : '1 Imóvel';
    }

    return "{$total} Imóveis";
}

/**
 * Tooltip da listagem (mesmo componente .api-status-hover das seguradoras do
 * Fiança) quando a remessa tem MAIS de 1 imóvel: número do imóvel + nome do
 * pretendente (locatário). O status de cada imóvel entra junto só quando eles
 * NÃO estão todos no mesmo status — o status agregado
 * (seguro_incendio_recalcular_status()) só vira "Finalizado" quando TODOS
 * terminam, então até lá quem olha só a lista não saberia que 1 já teve
 * desfecho. Retorna [] com 1 imóvel (o nome já aparece na coluna).
 *
 * @return list<array{numero_imovel:int, locatario:string, status:?string}>
 */
function seguro_incendio_imoveis_tooltip(int $seguroIncendioId): array
{
    $imoveis = db_fetch_all(
        'SELECT numero_imovel, locatario, status FROM seguro_incendio_imoveis WHERE seguro_incendio_id = ? ORDER BY numero_imovel',
        ['i', $seguroIncendioId]
    );
    if (count($imoveis) <= 1) {
        return [];
    }

    $status_misto = count(array_unique(array_column($imoveis, 'status'))) > 1;

    return array_map(fn($im) => [
        'numero_imovel' => (int) $im['numero_imovel'],
        'locatario'     => trim((string) ($im['locatario'] ?? '')),
        'status'        => $status_misto ? (string) $im['status'] : null,
    ], $imoveis);
}

/**
 * Registra uma notificação na tabela real `notificacoes_mps`
 * (enum de destinatario_tipo é 'admin'|'imob' — não 'mps'/'imobiliaria').
 * Substitui os antigos `INSERT INTO notificacoes` que apontavam para uma
 * tabela que nunca existiu no banco (falhavam sempre, silenciosamente).
 */
function notificar_mps(
    string $destinatario_tipo,
    int $destinatario_id,
    string $tipo,
    string $titulo,
    string $mensagem,
    ?int $cotacao_id = null,
    ?string $url = null
): void {
    try {
        db_execute(
            "INSERT INTO notificacoes_mps (cotacao_id, tipo, lida, criado_em, titulo, mensagem, destinatario_id, destinatario_tipo, url)
             VALUES (?, ?, 0, NOW(), ?, ?, ?, ?, ?)",
            ['isssiss', $cotacao_id ?? 0, $tipo, $titulo, $mensagem, $destinatario_id, $destinatario_tipo, $url]
        );
    } catch (Throwable $e) {
        error_log('[notificar_mps] ' . $e->getMessage());
    }
}

/** Notifica todos os usuários MPS (equipe interna, tabela `usuario`). */
function notificar_todos_admins(string $tipo, string $titulo, string $mensagem, ?int $cotacao_id = null, ?string $url = null): void
{
    try {
        $admins = db_fetch_all("SELECT id FROM usuario LIMIT 20");
        foreach ($admins as $adm) {
            notificar_mps('admin', (int) $adm['id'], $tipo, $titulo, $mensagem, $cotacao_id, $url);
        }
    } catch (Throwable $e) {
        error_log('[notificar_todos_admins] ' . $e->getMessage());
    }
}

/** Notifica todos os usuários de uma imobiliária (tabela `users`). */
/**
 * $cotacao_usuario_id (dono da cotação) restringe quem na imobiliária recebe
 * a notificação: além do dono, só quem é admin da imobiliária (mesma regra
 * de "operador comum só vê as próprias cotações" usada no dashboard/listagem
 * — ver pages/index.php). Null (padrão) mantém o comportamento antigo de
 * notificar todo mundo, usado só pra eventos sem dono definido.
 */
function notificar_imobiliaria(int $imobiliaria_id, string $tipo, string $titulo, string $mensagem, ?int $cotacao_id = null, ?string $url = null, ?int $cotacao_usuario_id = null): void
{
    try {
        if ($cotacao_usuario_id !== null) {
            $usuarios = db_fetch_all(
                "SELECT id FROM users WHERE imobiliaria_id = ? AND (admin = 'S' OR id = ?) LIMIT 20",
                ['ii', $imobiliaria_id, $cotacao_usuario_id]
            );
        } else {
            $usuarios = db_fetch_all("SELECT id FROM users WHERE imobiliaria_id = ? LIMIT 20", ['i', $imobiliaria_id]);
        }
        foreach ($usuarios as $u) {
            notificar_mps('imob', (int) $u['id'], $tipo, $titulo, $mensagem, $cotacao_id, $url);
        }
    } catch (Throwable $e) {
        error_log('[notificar_imobiliaria] ' . $e->getMessage());
    }
}

/**
 * Monta o feed único e cronológico de uma cotação: eventos de status
 * (tabela `historico`) mesclados com as mensagens do chat (tabela
 * `cotacao_mensagens`), ordenados por data/hora. Cada item vem com
 * `tipo` = 'evento' ou 'mensagem' para o template decidir como renderizar.
 * Sem $comInternos (imobiliária), os eventos só da MPS (historico.interno —
 * resultado de cada seguradora na API) ficam de fora.
 */
function cotacao_montar_feed(string $protocolo, bool $comInternos = true): array
{
    $eventos = db_fetch_all(
        'SELECT tipo_evento, status_anterior, status_novo, observacao, usuario_nome, data_evento
           FROM historico WHERE protocolo = ?' . ($comInternos ? '' : ' AND interno = 0') . ' ORDER BY data_evento ASC',
        ['s', $protocolo]
    );
    $mensagens = db_fetch_all(
        'SELECT id, autor_tipo, autor_nome, mensagem, anexo_url, anexo_nome, anexo_tipo, criado_em
           FROM cotacao_mensagens WHERE protocolo = ? ORDER BY criado_em ASC',
        ['s', $protocolo]
    );

    $feed = [];
    foreach ($eventos as $e) {
        $feed[] = [
            'tipo'       => 'evento',
            'ts'         => $e['data_evento'],
            'tipo_evento'=> $e['tipo_evento'],
            'status_anterior' => $e['status_anterior'],
            'status_novo'     => $e['status_novo'],
            'observacao' => $e['observacao'],
            'usuario_nome' => $e['usuario_nome'] ?? 'MPS',
        ];
    }
    foreach ($mensagens as $m) {
        $feed[] = [
            'tipo'       => 'mensagem',
            'ts'         => $m['criado_em'],
            'id'         => (int) $m['id'],
            'autor_tipo' => $m['autor_tipo'],
            'autor_nome' => $m['autor_nome'],
            'mensagem'   => $m['mensagem'],
            'anexo_url'  => $m['anexo_url'],
            'anexo_nome' => $m['anexo_nome'],
            'anexo_tipo' => $m['anexo_tipo'],
        ];
    }

    usort($feed, fn($a, $b) => strtotime($a['ts']) <=> strtotime($b['ts']));

    return $feed;
}

/**
 * Conta quantas mensagens do chat de uma cotação ainda não foram lidas pelo
 * usuário atual (mensagens do "outro lado" com id maior que o último lido).
 * Usado para o badge do botão de chat nas telas de detalhe (uma cotação só).
 */
function cotacao_chat_unread(string $protocolo, string $papel, int $usuario_id): int
{
    $lida = db_fetch_one(
        "SELECT ultima_id_lida, atualizado_em FROM cotacao_mensagens_lidas WHERE protocolo = ? AND usuario_id = ? AND papel = ?",
        ['sis', $protocolo, $usuario_id, $papel]
    );
    $ultima_id = (int) ($lida['ultima_id_lida'] ?? 0);
    $desde     = $lida['atualizado_em'] ?? '1970-01-01 00:00:00';

    $msgs = db_fetch_one(
        "SELECT COUNT(*) AS total FROM cotacao_mensagens WHERE protocolo = ? AND autor_tipo != ? AND id > ?",
        ['ssi', $protocolo, $papel, $ultima_id]
    );
    // Exclui só os próprios eventos do usuário (não o lado inteiro) — um admin
    // deve continuar vendo como não lida a ação de OUTRO admin, só não a dele
    // mesmo (ex: ele mesmo enviou a proposta e não pode ver isso como "novo").
    // Evento interno (só da MPS) não conta pra imobiliária.
    $eventos = db_fetch_one(
        "SELECT COUNT(*) AS total FROM historico
          WHERE protocolo = ? AND data_evento > ?
            AND (usuario_tipo IS NULL OR NOT (usuario_tipo = ? AND usuario_id = ?))"
            . ($papel === 'admin' ? '' : ' AND interno = 0'),
        ['sssi', $protocolo, $desde, $papel, $usuario_id]
    );

    return (int) ($msgs['total'] ?? 0) + (int) ($eventos['total'] ?? 0);
}

/**
 * Versão em lote de cotacao_chat_unread() — uma query pra mensagens e outra
 * pra eventos de histórico, cobrindo todos os protocolos de uma página de
 * grid de uma vez (evita N+1). Retorna [protocolo => total_nao_lidos].
 */
function cotacao_unread_map(array $protocolos, string $papel, int $usuario_id): array
{
    $mapa = [];
    if (!$protocolos) return $mapa;

    $placeholders = implode(',', array_fill(0, count($protocolos), '?'));

    $msgs = db_fetch_all(
        "SELECT cm.protocolo, COUNT(*) AS total
           FROM cotacao_mensagens cm
           LEFT JOIN cotacao_mensagens_lidas l
             ON l.protocolo = cm.protocolo AND l.usuario_id = ? AND l.papel = ?
          WHERE cm.protocolo IN ({$placeholders})
            AND cm.autor_tipo != ?
            AND cm.id > COALESCE(l.ultima_id_lida, 0)
          GROUP BY cm.protocolo",
        ['is' . str_repeat('s', count($protocolos)) . 's', $usuario_id, $papel, ...$protocolos, $papel]
    );
    foreach ($msgs as $m) $mapa[$m['protocolo']] = (int) $m['total'];

    // Exclui só os próprios eventos do usuário (não o lado inteiro) — ver
    // comentário equivalente em cotacao_chat_unread().
    $eventos = db_fetch_all(
        "SELECT h.protocolo, COUNT(*) AS total
           FROM historico h
           LEFT JOIN cotacao_mensagens_lidas l
             ON l.protocolo = h.protocolo AND l.usuario_id = ? AND l.papel = ?
          WHERE h.protocolo IN ({$placeholders})
            AND h.data_evento > COALESCE(l.atualizado_em, '1970-01-01 00:00:00')
            AND (h.usuario_tipo IS NULL OR NOT (h.usuario_tipo = ? AND h.usuario_id = ?))"
            . ($papel === 'admin' ? '' : ' AND h.interno = 0') . "
          GROUP BY h.protocolo",
        ['is' . str_repeat('s', count($protocolos)) . 'si', $usuario_id, $papel, ...$protocolos, $papel, $usuario_id]
    );
    foreach ($eventos as $e) {
        $mapa[$e['protocolo']] = ($mapa[$e['protocolo']] ?? 0) + (int) $e['total'];
    }

    return $mapa;
}

/**
 * Vínculos empregatícios do pretendente de Fiança (formulário e "Cotar com
 * outro pretendente") — os valores que as seguradoras da API reconhecem.
 */
function fianca_vinculos_empregaticios(): array
{
    return ['CLT', 'Autônomo', 'Empresário', 'Funcionário público', 'Aposentado', 'Renda de aluguel', 'Estudante'];
}

/**
 * 'Cotação API' é status interno (Fiança na API das seguradoras) — nunca
 * aparece no histórico/chat: como anterior some (a linha mostra só o novo);
 * como novo vira "Em análise".
 *
 * @return array{0: ?string, 1: ?string} [anterior, novo]
 */
function historico_status_visiveis(?string $anterior, ?string $novo): array
{
    return [
        $anterior === 'Cotação API' ? null : $anterior,
        $novo === 'Cotação API' ? 'Em análise' : $novo,
    ];
}

/**
 * Registra uma entrada na tabela nativa `historico` (auditoria de seguro_fianca).
 * Reaproveita o schema existente — nenhuma tabela/coluna nova é necessária.
 */
function historico_registrar(
    int $seguro_fianca_id,
    string $protocolo,
    string $tipo_evento, // CRIACAO|ALTERACAO_STATUS|ENVIO_COTACAO|APROVACAO_COTACAO|RECUSA_COTACAO|TRANSFERENCIA
    ?string $status_anterior,
    ?string $status_novo,
    int $usuario_id,
    string $usuario_tipo, // 'admin' | 'imobiliaria' — alimenta o filtro de não lidas do chat (cotacao_chat_unread)
    string $usuario_nome,
    ?string $observacao = null
): void {
    [$status_anterior, $status_novo] = historico_status_visiveis($status_anterior, $status_novo);
    try {
        db_execute(
            "INSERT INTO historico
                (seguro_fianca_id, protocolo, tipo_evento, status_anterior, status_novo,
                 observacao, usuario_id, usuario_tipo, usuario_nome, data_evento)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
            ['isssssiss',
             $seguro_fianca_id, $protocolo, $tipo_evento, $status_anterior, $status_novo,
             $observacao, $usuario_id, $usuario_tipo, $usuario_nome]
        );
    } catch (Throwable $e) {
        error_log('[historico] ' . $e->getMessage());
    }
}

/**
 * Converte um texto em slug (minúsculo, sem acento, hífens no lugar de espaços).
 */
function slugify(string $texto): string
{
    static $mapa = [
        'á'=>'a','à'=>'a','ã'=>'a','â'=>'a','ä'=>'a',
        'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
        'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i',
        'ó'=>'o','ò'=>'o','õ'=>'o','ô'=>'o','ö'=>'o',
        'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u',
        'ç'=>'c','ñ'=>'n',
    ];

    $texto = mb_strtolower($texto, 'UTF-8');
    $texto = strtr($texto, $mapa);
    $texto = preg_replace('/[^a-z0-9]+/', '-', $texto);
    return trim($texto, '-');
}

/** Mínimo 6 caracteres, com pelo menos 1 letra maiúscula e 1 número. */
function senha_valida(string $senha): bool
{
    return strlen($senha) >= 6 && preg_match('/[A-Z]/', $senha) === 1 && preg_match('/[0-9]/', $senha) === 1;
}

/**
 * Gera um slug único para um usuário da imobiliária, a partir do nome.
 * Em caso de colisão (mesmo nome já usado), acrescenta -2, -3, etc.
 *
 * $excluir_id: ao editar um usuário, passe o próprio id para não colidir consigo mesmo.
 */
function gerar_slug_usuario(string $username, int $imobiliaria_id, ?int $excluir_id = null): string
{
    $base = slugify($username) ?: 'usuario';
    $slug = $base;
    $sufixo = 2;

    while (true) {
        $sql    = 'SELECT id FROM users WHERE imobiliaria_id = ? AND slug = ?';
        $params = ['is', $imobiliaria_id, $slug];
        if ($excluir_id !== null) {
            $sql .= ' AND id != ?';
            $params[0] .= 'i';
            $params[]   = $excluir_id;
        }
        if (!db_fetch_one($sql, $params)) {
            return $slug;
        }
        $slug = "{$base}-{$sufixo}";
        $sufixo++;
    }
}

/**
 * Gera um slug único para uma imobiliária, a partir do nome (mesma ideia de
 * gerar_slug_usuario(), mas único globalmente — não faz sentido escopar por
 * nada, já que imobiliária não pertence a outra entidade).
 * $excluir_id: ao editar, passe o próprio id para não colidir consigo mesma.
 */
function gerar_slug_imobiliaria(string $nome, ?int $excluir_id = null): string
{
    $base = slugify($nome) ?: 'imobiliaria';
    $slug = $base;
    $sufixo = 2;

    while (true) {
        $sql    = 'SELECT id FROM imobiliarias WHERE slug = ?';
        $params = ['s', $slug];
        if ($excluir_id !== null) {
            $sql .= ' AND id != ?';
            $params[0] .= 'i';
            $params[]   = $excluir_id;
        }
        if (!db_fetch_one($sql, $params)) {
            return $slug;
        }
        $slug = "{$base}-{$sufixo}";
        $sufixo++;
    }
}

/** Igual gerar_slug_imobiliaria(), mas pra `usuario` (equipe interna MPS) — slug globalmente único. */
function gerar_slug_usuario_mps(string $nome, ?int $excluir_id = null): string
{
    $base = slugify($nome) ?: 'usuario';
    $slug = $base;
    $sufixo = 2;

    while (true) {
        $sql    = 'SELECT id FROM usuario WHERE slug = ?';
        $params = ['s', $slug];
        if ($excluir_id !== null) {
            $sql .= ' AND id != ?';
            $params[0] .= 'i';
            $params[]   = $excluir_id;
        }
        if (!db_fetch_one($sql, $params)) {
            return $slug;
        }
        $slug = "{$base}-{$sufixo}";
        $sufixo++;
    }
}

/**
 * E-mail que deve receber notificações de cotação para um usuário MPS
 * (`usuario.email_cotacao`) — se o usuário preencheu um e-mail alternativo
 * (válido) no perfil, usa ele; senão cai pro e-mail de login. Mesma regra
 * de `obterEmailsDestinatarios()` do sistema legado.
 */
function resolver_email_cotacao(?string $email_cotacao, string $email): string
{
    $email_cotacao = trim((string) $email_cotacao);
    if ($email_cotacao !== '' && filter_var($email_cotacao, FILTER_VALIDATE_EMAIL)) {
        return $email_cotacao;
    }
    return $email;
}

/**
 * Protocolo de exibição de um chamado de suporte (ex.: CHA2026-00001).
 * Não existe coluna `protocolo` na tabela — é sempre derivado do id + ano de
 * CRIAÇÃO (não o ano atual, que geraria protocolos diferentes pro mesmo
 * chamado dependendo de quando a página foi carregada).
 */
function chamado_protocolo(int $id, string $criado_em): string
{
    return 'CHA' . date('Y', strtotime($criado_em)) . '-' . str_pad((string) $id, 5, '0', STR_PAD_LEFT);
}

/** Extrai o id numérico de um protocolo de chamado (CHA2026-00001 → 1). Retorna 0 se inválido. */
function chamado_id_do_protocolo(string $protocolo): int
{
    return preg_match('/^CHA\d{4}-(\d{5})$/i', $protocolo, $m) ? (int) $m[1] : 0;
}

/**
 * Rótulo/classe de pill pro status real de um chamado — só os 3 valores que
 * existem de fato na coluna (`chamados_suporte.status` é
 * enum('aguardando_mps','aguardando_imob','encerrado')).
 */
function chamado_status_label(string $status): string
{
    return match ($status) {
        'aguardando_mps'  => 'Aguardando MPS',
        'aguardando_imob' => 'Aguardando Imobiliária',
        'encerrado'       => 'Encerrado',
        default           => $status,
    };
}

function chamado_status_pill(string $status): string
{
    return match ($status) {
        'aguardando_mps'  => 'status-pill-pendente',
        'aguardando_imob' => 'status-pill-andamento',
        'encerrado'       => 'status-pill-aprovado',
        default           => '',
    };
}

/** Formata uma data (DATETIME/timestamp) como "há X min/horas/dias" ou "Agora mesmo". */
function tempo_decorrido(string $data): string
{
    $diff = time() - strtotime($data);
    if ($diff < 60) return 'Agora mesmo';
    if ($diff < 3600) {
        $min = (int) floor($diff / 60);
        return "há {$min} " . ($min === 1 ? 'minuto' : 'minutos');
    }
    if ($diff < 86400) {
        $h = (int) floor($diff / 3600);
        return "há {$h} " . ($h === 1 ? 'hora' : 'horas');
    }
    if ($diff < 2592000) {
        $d = (int) floor($diff / 86400);
        return "há {$d} " . ($d === 1 ? 'dia' : 'dias');
    }
    return date('d/m/Y', strtotime($data));
}

/** Formata uma duração (diferença entre dois timestamps, em segundos) como "2h 15min", "3 dias 4h" etc. */
function formatar_duracao(int $segundos): string
{
    if ($segundos < 60) return 'menos de 1min';

    $dias = intdiv($segundos, 86400);
    $horas = intdiv($segundos % 86400, 3600);
    $minutos = intdiv($segundos % 3600, 60);

    if ($dias > 0) {
        return $horas > 0 ? "{$dias} " . ($dias === 1 ? 'dia' : 'dias') . " {$horas}h" : "{$dias} " . ($dias === 1 ? 'dia' : 'dias');
    }
    if ($horas > 0) {
        return $minutos > 0 ? "{$horas}h {$minutos}min" : "{$horas}h";
    }
    return "{$minutos}min";
}

/** Ícone (Phosphor) associado a um tipo de notificação — ver notificacoes_mps.tipo. */
function notif_tipo_icon(string $tipo): string
{
    return match ($tipo) {
        'cotacao'  => 'clipboard-text',
        'chamado'  => 'headset',
        'campanha' => 'megaphone',
        default    => 'bell',
    };
}

/** Ícone (Phosphor) associado a um tipo de notificação do time MPS (destinatario_tipo = 'admin'). */
function admin_notif_tipo_icon(string $tipo): string
{
    return match ($tipo) {
        'nova_cotacao'       => 'clipboard-text',
        'chat_mensagem'      => 'chat-circle',
        'cotacao_finalizada' => 'check-circle',
        'acao_imob'          => 'arrows-clockwise',
        'tarefa_mencao'      => 'at',
        'tarefa_atribuida'   => 'list-checks',
        'tarefa_prazo'       => 'clock',
        'tarefa_atraso'      => 'warning',
        'lead_site'          => 'monitor',
        'api_emissao_presa'  => 'warning',
        default              => 'bell',
    };
}

/** Identifica navegador e tipo de dispositivo a partir do User-Agent (sem libs externas). */
function ua_parse(?string $user_agent): array
{
    $ua = $user_agent ?? '';

    $navegador = 'Navegador desconhecido';
    if (preg_match('/Edg\/([\d.]+)/', $ua, $m)) {
        $navegador = 'Edge ' . strtok($m[1], '.');
    } elseif (preg_match('/OPR\/([\d.]+)/', $ua, $m)) {
        $navegador = 'Opera ' . strtok($m[1], '.');
    } elseif (preg_match('/Chrome\/([\d.]+)/', $ua, $m)) {
        $navegador = 'Chrome ' . strtok($m[1], '.');
    } elseif (preg_match('/Firefox\/([\d.]+)/', $ua, $m)) {
        $navegador = 'Firefox ' . strtok($m[1], '.');
    } elseif (preg_match('/Version\/([\d.]+).*Safari/', $ua, $m)) {
        $navegador = 'Safari ' . strtok($m[1], '.');
    }

    if (preg_match('/iPad|Tablet/i', $ua)) {
        $dispositivo = 'Tablet';
    } elseif (preg_match('/Mobile|Android|iPhone/i', $ua)) {
        $dispositivo = 'Celular';
    } else {
        $dispositivo = 'Computador';
    }

    return ['navegador' => $navegador, 'dispositivo' => $dispositivo];
}

/**
 * Verifica horário de atendimento e bloqueios temporários configurados pra
 * um produto (tipo_seguro: fianca_residencial/fianca_comercial/auto/
 * titulo_capitalizacao/condominial). Retorna null se está tudo normal, ou
 * uma mensagem explicando a situação — nunca bloqueia o envio, só avisa.
 */
function horario_atendimento_verificar(string $tipoSeguro): ?string
{
    try {
        $agora    = new DateTime();
        $agoraStr = $agora->format('Y-m-d H:i:s');

        $bloqueio = db_fetch_one(
            "SELECT mensagem, data_fim FROM bloqueios_horario
              WHERE ativo = 'S' AND FIND_IN_SET(?, tipo_seguro) AND ? BETWEEN data_inicio AND data_fim
           ORDER BY data_fim ASC LIMIT 1",
            ['ss', $tipoSeguro, $agoraStr]
        );
        if ($bloqueio) {
            $fim = date('d/m/Y \à\s H:i', strtotime($bloqueio['data_fim']));
            return trim($bloqueio['mensagem']) . " A equipe volta a responder a partir de {$fim}.";
        }

        $horario = db_fetch_one(
            "SELECT hora_inicio, hora_fim, dias_semana FROM horarios_atendimento WHERE tipo_seguro = ? AND ativo = 'S' LIMIT 1",
            ['s', $tipoSeguro]
        ) ?? db_fetch_one(
            "SELECT hora_inicio, hora_fim, dias_semana FROM horarios_atendimento WHERE tipo_seguro = 'todos' AND ativo = 'S' LIMIT 1"
        );
        if (!$horario) {
            return null;
        }

        $diaSemana      = (int) $agora->format('w');
        $diasPermitidos = array_map('intval', explode(',', $horario['dias_semana']));
        $horaAtual      = $agora->format('H:i:s');

        $dentroDoDia     = in_array($diaSemana, $diasPermitidos, true);
        $dentroDoHorario = $horaAtual >= $horario['hora_inicio'] && $horaAtual <= $horario['hora_fim'];

        if ($dentroDoDia && $dentroDoHorario) {
            return null;
        }

        $inicio = substr($horario['hora_inicio'], 0, 5);
        $fim    = substr($horario['hora_fim'], 0, 5);

        return "Recebemos sua cotação fora do nosso horário de atendimento ({$inicio} às {$fim}). Nossa equipe vai retornar assim que possível, no próximo horário útil.";
    } catch (Throwable $e) {
        error_log('[horario_atendimento_verificar] ' . $e->getMessage());
        return null;
    }
}

// ═════════════════════════════════════════════════════════════════════════════
// MÓDULO DE TAREFAS
// ═════════════════════════════════════════════════════════════════════════════

/**
 * Contexto de tarefas do usuário MPS logado — consultado no banco (o JWT não
 * carrega essas infos e não é reconsultado a cada request).
 *
 * @return array{gestor: bool, setores: int[]}  gestor = vê/gerencia tudo;
 *         setores = ids dos setores a que o usuário pertence.
 */
function tarefas_contexto_usuario(int $uid): array
{
    $gestor  = false;
    $setores = [];

    try {
        $u = db_fetch_one('SELECT tarefas_gestor FROM usuario WHERE id = ?', ['i', $uid]);
        $gestor = ($u['tarefas_gestor'] ?? 'N') === 'S';

        $rows = db_fetch_all('SELECT setor_id FROM tarefas_usuario_setor WHERE usuario_id = ?', ['i', $uid]);
        $setores = array_map(static fn($r) => (int) $r['setor_id'], $rows);
    } catch (\Throwable $e) {
        error_log('[tarefas_contexto_usuario] ' . $e->getMessage());
    }

    return ['gestor' => $gestor, 'setores' => $setores];
}

/**
 * Renumera sequencialmente a coluna `ordem` das tarefas de um grupo, na ordem
 * dada (drag-and-drop e "criar tarefa abaixo" reaproveitam isso). Grupos são
 * pequenos (uma tela), então um UPDATE por id é suficiente.
 *
 * @param int[] $idsOrdenados ids das tarefas, já na ordem final desejada.
 */
function tarefas_renumerar_grupo(array $idsOrdenados): void
{
    $ordem = 1;
    foreach ($idsOrdenados as $id) {
        $id = (int) $id;
        if ($id <= 0) continue;
        try {
            db_execute('UPDATE tarefas SET ordem = ? WHERE id = ?', ['ii', $ordem, $id]);
        } catch (\Throwable $e) {
            error_log('[tarefas_renumerar_grupo] ' . $e->getMessage());
        }
        $ordem++;
    }
}

/**
 * Idempotência de envio de formulário (anti duplo-clique / reenvio).
 *
 * Cada carregamento do formulário gera um `submit_token` único. Por mais que o
 * cliente reenvie (clicar 100x, retry de rede), o servidor só processa a
 * PRIMEIRA vez; reenvios do mesmo token recebem de volta o mesmo protocolo.
 *
 * Funciona porque o PHP serializa requisições da mesma sessão (lock de sessão):
 * cliques concorrentes são resolvidos em ordem, então o 2º já vê o 1º concluído.
 */
function submit_token_ler(): string
{
    return trim((string) request()->input('submit_token', ''));
}

/** Retorna o protocolo já gerado para este token, se for um reenvio; senão null. */
function submit_token_duplicado(string $token): ?string
{
    if ($token === '') return null;                 // sem token: não há como deduplicar
    return session('submit_tokens', [])[$token] ?? null;
}

/** Marca o token como processado, guardando o protocolo gerado. */
function submit_token_concluir(string $token, string $protocolo): void
{
    if ($token === '') return;
    // Sessão do Laravel (não depende do lock de sessão do PHP nativo).
    $tokens = session('submit_tokens', []);
    $tokens[$token] = $protocolo;
    // Limita o histórico para a sessão não crescer indefinidamente.
    session(['submit_tokens' => array_slice($tokens, -50, null, true)]);
    session()->save();
}

/**
 * Registra um evento no histórico da tarefa (nunca interrompe a ação principal).
 *
 * @param string $acao  criou|titulo|descricao|setor|status|responsaveis|prazo|anexo_add|anexo_del|comentou
 */
function tarefa_log(int $tarefa_id, ?int $uid, string $acao, ?string $detalhe = null): void
{
    if ($tarefa_id <= 0) return;
    try {
        db_execute(
            'INSERT INTO tarefas_historico (tarefa_id, usuario_id, acao, detalhe, criado_em)
             VALUES (?, ?, ?, ?, NOW())',
            ['iiss', $tarefa_id, $uid ?? 0, $acao, $detalhe]
        );
    } catch (\Throwable $e) {
        error_log('[tarefa_log] ' . $e->getMessage());
    }
}

/** Frase legível de um evento do histórico da tarefa. */
function tarefa_historico_frase(string $acao, ?string $detalhe): string
{
    $d = trim((string) $detalhe);
    return match ($acao) {
        'criou'        => 'criou a tarefa',
        'titulo'       => 'renomeou a tarefa' . ($d !== '' ? ' para "' . $d . '"' : ''),
        'descricao'    => 'atualizou a descrição',
        'setor'        => 'moveu para o setor' . ($d !== '' ? ' ' . $d : ''),
        'status'       => 'alterou o status' . ($d !== '' ? ' para ' . $d : ''),
        'responsaveis' => $d !== '' ? $d : 'atualizou os responsáveis',
        'prazo'        => $d !== '' ? 'definiu o prazo para ' . $d : 'removeu o prazo',
        'anexo_add'    => 'anexou o arquivo' . ($d !== '' ? ' ' . $d : ''),
        'anexo_del'    => 'removeu o arquivo' . ($d !== '' ? ' ' . $d : ''),
        'comentou'     => 'comentou na tarefa',
        'arquivou'     => 'arquivou a tarefa',
        'desarquivou'  => 'desarquivou a tarefa',
        default        => 'atualizou a tarefa',
    };
}

/**
 * Monta a frase de alteração de responsáveis para o histórico.
 * Retorna '' quando nada mudou (aí não vale a pena registrar).
 */
function tarefa_resp_diff_frase(array $antes, array $depois): string
{
    $add = array_values(array_diff($depois, $antes));
    $rem = array_values(array_diff($antes, $depois));
    if (!$add && !$rem) return '';

    $nomes = static function (array $ids): string {
        if (!$ids) return '';
        $ph = implode(',', array_fill(0, count($ids), '?'));
        try {
            $rows = db_fetch_all("SELECT username FROM usuario WHERE id IN ({$ph})", [str_repeat('i', count($ids)), ...$ids]);
        } catch (\Throwable $e) {
            return '';
        }
        return implode(', ', array_map(static fn($r) => $r['username'], $rows));
    };

    $partes = [];
    if ($add) $partes[] = 'atribuiu ' . $nomes($add);
    if ($rem) $partes[] = 'removeu ' . $nomes($rem);

    return implode(' e ', $partes);
}

/**
 * Notifica um usuário MPS sobre uma tarefa: sino sempre, e-mail quando pedido.
 * Nunca interrompe a ação principal (falha de SMTP só vai para o log).
 */
function tarefa_notificar(
    int $usuario_id,
    string $tipo,
    string $titulo,
    string $mensagem,
    bool $enviar_email = false,
    string $email_extra = ''
): void {
    if ($usuario_id <= 0) return;

    notificar_mps('admin', $usuario_id, $tipo, $titulo, $mensagem);

    if (!$enviar_email) return;

    try {
        $u = db_fetch_one('SELECT username, email FROM usuario WHERE id = ?', ['i', $usuario_id]);
        if (!$u || empty($u['email'])) return;
        send_email($u['email'], $titulo, tarefa_email_html($u['username'], $titulo, $mensagem, $email_extra));
    } catch (\Throwable $e) {
        error_log('[tarefa_notificar] ' . $e->getMessage());
    }
}

/** Corpo HTML padrão dos e-mails de tarefa. */
function tarefa_email_html(string $nome, string $titulo, string $mensagem, string $extra = ''): string
{
    $link = rtrim(APP_URL, '/') . '/admin/tarefas';

    return '<p style="margin:0 0 14px;">Olá, ' . e($nome) . '!</p>'
        . '<p style="margin:0 0 14px;">' . e($mensagem) . '</p>'
        . ($extra !== '' ? $extra : '')
        . '<p style="margin:22px 0 0;">' . email_botao('Abrir o Gerenciador de Tarefas', $link) . '</p>';
}

/** Blocos de detalhe (tabela) reaproveitados nos e-mails de tarefa. */
function tarefa_email_detalhes(array $linhas): string
{
    return email_detalhes($linhas);   // tabela genérica em mailer.php
}

/**
 * Colunas de preferência de e-mail (usuario.recebe_email_*) por tipo de aviso.
 * Whitelist — nunca interpole $preferencia direto no SQL.
 */
function email_preferencia_coluna(string $preferencia): ?string
{
    return [
        'fianca'      => 'recebe_email_fianca',
        'auto'        => 'recebe_email_auto',
        'titulo'      => 'recebe_email_titulo',
        'condominial' => 'recebe_email_condominial',
        'incendio'    => 'recebe_email_incendio',
        'residencial' => 'recebe_email_seguro_residencial',
        'suporte'     => 'recebe_email_suporte',
    ][$preferencia] ?? null;
}

/**
 * Envia um e-mail (já no layout MPS) para todos os usuários MPS que optaram por
 * receber avisos daquele tipo. Usa o e-mail de cotação, se cadastrado; senão o
 * de login. Todos recebem numa ÚNICA conexão SMTP (cópia oculta), então não
 * importa quantos sejam — o request não fica lento. Retorna quantos e-mails saíram.
 */
function email_mps_por_preferencia(string $preferencia, string $assunto, string $conteudo): int
{
    $coluna = email_preferencia_coluna($preferencia);
    if ($coluna === null) return 0;

    try {
        $usuarios = db_fetch_all("SELECT email, email_cotacao FROM usuario WHERE {$coluna} = 'S'");

        $destinatarios = [];
        foreach ($usuarios as $u) {
            $para = resolver_email_cotacao($u['email_cotacao'] ?? '', $u['email'] ?? '');
            if ($para !== '' && filter_var($para, FILTER_VALIDATE_EMAIL)) {
                $destinatarios[$para] = true;   // deduplica
            }
        }
        $destinatarios = array_keys($destinatarios);
        if (!$destinatarios) return 0;

        return send_email($destinatarios, $assunto, $conteudo) ? count($destinatarios) : 0;
    } catch (\Throwable $e) {
        error_log('[email_mps_por_preferencia] ' . $e->getMessage());
        return 0;
    }
}

/**
 * Avisa a equipe MPS por e-mail sobre uma nova cotação, respeitando a
 * preferência de cada usuário (Fiança/Auto/Título/Condominial).
 */
function notificar_cotacao_email(string $preferencia, string $produto, string $protocolo, int $imobiliaria_id): void
{
    try {
        $imob = db_fetch_one('SELECT nome FROM imobiliarias WHERE id = ?', ['i', $imobiliaria_id]);
        $conteudo = '<p style="margin:0 0 14px;">Uma nova cotação foi recebida e está aguardando análise.</p>'
            . email_detalhes([
                'Produto'     => $produto,
                'Protocolo'   => $protocolo,
                'Imobiliária' => $imob['nome'] ?? ('#' . $imobiliaria_id),
                'Recebida em' => date('d/m/Y H:i'),
            ])
            . '<p style="margin:22px 0 0;">' . email_botao('Ver no painel', rtrim(APP_URL, '/') . '/admin/cotacoes') . '</p>';

        email_mps_por_preferencia($preferencia, "Nova cotação — {$produto} ({$protocolo})", $conteudo);
    } catch (\Throwable $e) {
        error_log('[notificar_cotacao_email] ' . $e->getMessage());
    }
}

/** Rótulo legível da categoria de um chamado de suporte. */
function chamado_categoria_label(string $categoria): string
{
    return [
        'seguro_fianca_residencial' => 'Seguro Fiança Residencial',
        'seguro_fianca_comercial'   => 'Seguro Fiança Comercial',
        'seguro_auto'               => 'Seguro Auto',
        'sinistro'                  => 'Sinistro',
        'duvidas_gerais'            => 'Dúvidas gerais',
        'outros'                    => 'Outros',
    ][$categoria] ?? 'Outros';
}

/**
 * Avisa a equipe MPS por e-mail quando uma imobiliária abre um chamado.
 * Vai para quem marcou "Chamados de suporte" nas preferências de e-mail.
 */
function notificar_chamado_email(string $assunto, string $titulo, string $categoria, int $imobiliaria_id): void
{
    try {
        $imob = db_fetch_one('SELECT nome FROM imobiliarias WHERE id = ?', ['i', $imobiliaria_id]);
        $conteudo = '<p style="margin:0 0 14px;">Uma imobiliária abriu um novo chamado de suporte.</p>'
            . email_detalhes([
                'Assunto'     => $assunto,
                'Categoria'   => chamado_categoria_label($categoria),
                'Imobiliária' => $imob['nome'] ?? ('#' . $imobiliaria_id),
                'Aberto em'   => date('d/m/Y H:i'),
            ])
            . '<p style="margin:22px 0 0;">' . email_botao('Abrir chamados', rtrim(APP_URL, '/') . '/admin/chamados') . '</p>';

        email_mps_por_preferencia('suporte', "Novo chamado de suporte — {$titulo}", $conteudo);
    } catch (\Throwable $e) {
        error_log('[notificar_chamado_email] ' . $e->getMessage());
    }
}

/** Ícone Phosphor de cada tipo de evento do histórico. */
function tarefa_historico_icone(string $acao): string
{
    return match ($acao) {
        'criou'        => 'plus',
        'titulo'       => 'note-pencil',
        'descricao'    => 'note-pencil',
        'setor'        => 'briefcase',
        'status'       => 'flag',
        'responsaveis' => 'users',
        'prazo'        => 'calendar-blank',
        'anexo_add'    => 'paperclip',
        'anexo_del'    => 'trash',
        'comentou'     => 'chat-circle',
        default        => 'clock',
    };
}

/** Rótulo legível da prioridade de uma tarefa. */
function tarefa_prioridade_label(?string $prioridade): string
{
    return match ($prioridade) {
        'alta'  => 'Alta',
        'media' => 'Média',
        'baixa' => 'Baixa',
        default => '—',
    };
}

/**
 * Pílula colorida (setor/status/etiqueta). A cor vem do banco (hex arbitrário),
 * então segue o padrão data-* + JS (mesma ideia do .panorama-segment[data-width])
 * em vez de style="" inline — o admin-tarefas.js aplica a cor lendo [data-cor].
 */
function tarefa_pill(string $nome, ?string $cor, string $extraClass = ''): string
{
    $cls = 'tarefa-pill' . ($extraClass !== '' ? " {$extraClass}" : '');
    return '<span class="' . $cls . '" data-cor="' . e($cor ?: '#64748b') . '">' . e($nome) . '</span>';
}

/**
 * Slugs de seguro do site que correspondem a produtos do sistema. Os demais
 * (seguro conteúdo, vida, viagem, automóvel, incêndio empresarial...) não
 * existem no sistema e ficam como 'outros' — só o administrador vê.
 */
const LEAD_SITE_PRODUTOS_SISTEMA = [
    'seguro-fianca-locaticia'     => 'fianca',
    'seguro-incendio-imobiliario' => 'incendio',
    'titulo-de-capitalizacao'     => 'titulo',
    'seguro-condominial'          => 'condominial',
    'seguro-residencial'          => 'residencial',
];

/**
 * Produto (tipo_key) e rótulo de um lead do site institucional (tabela
 * leads_site, gravada pelo próprio site). Rótulo vem do seguro_nome do JSON;
 * na fiança, Residencial/Comercial pelo tipo de locação.
 */
function lead_site_produto(string $slug, array $dados = []): array
{
    $origem = LEAD_SITE_PRODUTOS_SISTEMA[$slug] ?? 'outros';

    if ($origem === 'fianca') {
        return [$origem, match (mb_strtolower((string) ($dados['locacao']['tipo'] ?? ''))) {
            'comercial'   => 'Fiança Comercial',
            'residencial' => 'Fiança Residencial',
            default       => 'Seguro Fiança',
        }];
    }
    if (!empty($dados['seguro_nome']) && is_string($dados['seguro_nome'])) {
        return [$origem, $dados['seguro_nome']];
    }

    $label = trim(preg_replace('/[-_]+/', ' ', $slug));
    return [$origem, $label !== '' ? mb_convert_case($label, MB_CASE_TITLE) : 'Lead do site'];
}

/**
 * Classe do badge (.tipo-pill-*) de um lead: produto do sistema usa a cor
 * dele; seguro fora do sistema tem cor própria (ver dashboard.css), e um
 * seguro novo que ainda não tem cor cai no cinza (.tipo-pill-lead).
 */
function lead_site_pill_class(string $slug): string
{
    $origem = LEAD_SITE_PRODUTOS_SISTEMA[$slug] ?? null;
    if ($origem !== null) return 'tipo-pill-' . $origem;

    $s = mb_strtolower($slug);
    $cor = match (true) {
        str_contains($s, 'conteudo')                                   => 'conteudo',
        str_contains($s, 'vida') && str_contains($s, 'coletiv')        => 'vida-coletivo',
        str_contains($s, 'vida')                                       => 'vida',
        str_contains($s, 'viagem')                                     => 'viagem',
        str_contains($s, 'previdencia')                                => 'previdencia',
        str_contains($s, 'moto')                                       => 'moto',
        str_contains($s, 'frota')                                      => 'frota',
        str_contains($s, 'automovel') || str_contains($s, 'auto')      => 'automovel',
        str_contains($s, 'residencial')                                => 'residencial',
        str_contains($s, 'equipamento') || str_contains($s, 'portat')  => 'equipamentos',
        str_contains($s, 'incendio') && str_contains($s, 'empresa')    => 'incendio-empresarial',
        str_contains($s, 'responsabilidade') || str_contains($s, 'civil') => 'responsabilidade-civil',
        default                                                        => null,
    };
    return 'tipo-pill-lead' . ($cor !== null ? ' tipo-pill-lead-' . $cor : '');
}

/** Perfil de quem preencheu o lead no site ("proprietario" → "Proprietário"). */
function lead_site_perfil_label(string $perfil): string
{
    return match (mb_strtolower($perfil)) {
        'proprietario' => 'Proprietário',
        'imobiliaria'  => 'Imobiliária',
        'inquilino'    => 'Inquilino',
        default        => mb_convert_case(str_replace(['_', '-'], ' ', $perfil), MB_CASE_TITLE),
    };
}

/**
 * Se o usuário MPS pode ver um lead do site. Mesma regra de Todas as Cotações
 * (admin_produtos_permitidos): operador restrito só vê os produtos liberados.
 * Seguro que não existe no sistema (origem 'outros', ex.: Seguro Conteúdo)
 * só aparece pro administrador completo.
 */
function lead_site_visivel(object $user, string $origem): bool
{
    if (!empty($user->mps_admin)) return true;
    if ($origem === 'outros') return false;

    $permitidos = admin_produtos_permitidos($user);
    return $permitidos === null || in_array($origem, $permitidos, true);
}

// ─── Seguro Residencial ─────────────────────────────────────────────────────
// Produto cotado na API das seguradoras (Pottencial e Too — ver
// src/helpers/api/seguradoras/*_res.php), tabela seguro_residencial,
// protocolo RES. Definições usadas pelo formulário, envio e detalhes.

/** Coberturas (coluna => [rótulo, descrição curta]). Incêndio é obrigatória. */
const RESIDENCIAL_COBERTURAS = [
    'cob_incendio'         => ['Incêndio, raio e explosão', 'Cobertura básica — obrigatória.'],
    'cob_danos_eletricos'  => ['Danos elétricos', 'Curto-circuito e oscilação de energia em aparelhos e instalações.'],
    'cob_vendaval'         => ['Vendaval e granizo', 'Vendaval, furacão, ciclone, tornado e granizo.'],
    'cob_roubo'            => ['Roubo e furto qualificado', 'Bens do imóvel, com sinais de arrombamento.'],
    'cob_vidros'           => ['Quebra de vidros', 'Vidros, espelhos, mármores e granitos.'],
    'cob_vazamento'        => ['Vazamento de tubulações', 'Ruptura de tubulações de água.'],
    'cob_rc_familiar'      => ['Responsabilidade civil familiar', 'Danos causados a terceiros pela família.'],
    'cob_perda_aluguel'    => ['Perda ou pagamento de aluguel', 'Aluguel enquanto o imóvel fica inabitável após um sinistro.'],
    'cob_impacto_veiculos' => ['Impacto de veículos', 'Danos por veículos terrestres (só Pottencial).'],
];

const RESIDENCIAL_TIPOS_IMOVEL = [
    'casa'            => 'Casa',
    'apartamento'     => 'Apartamento',
    'casa_condominio' => 'Casa em condomínio',
    'chacara'         => 'Chácara, sítio ou fazenda',
];
const RESIDENCIAL_CONSTRUCAO = ['alvenaria' => 'Alvenaria', 'madeira' => 'Madeira'];
const RESIDENCIAL_USO = ['habitual' => 'Moradia habitual', 'veraneio' => 'Veraneio', 'desocupado' => 'Desocupado'];
