<?php
/**
 * AJAX endpoint — GET /api/cotacoes/listar
 * Mesma apresentação da listagem admin (grid de linhas, sem <table>), mas
 * restrita à imobiliaria_id do usuário logado.
 * Retorna JSON { html, contadores }
 */
$current_user = require_auth();
if (($current_user->role ?? '') === 'admin') { http_response_code(403); exit; }

header('Content-Type: application/json; charset=utf-8');

$imobiliaria_id     = (int)($current_user->imobiliaria_id ?? 0);
$is_imob_admin      = !empty($current_user->imob_admin);
$usuario_id_filtro = (int) ($current_user->uid ?? 0);
$filtro_status      = trim($_GET['status']      ?? '');
$filtro_tipo        = trim($_GET['tipo']        ?? '');
$filtro_pretendente = trim($_GET['pretendente'] ?? '');
$filtro_protocolo   = trim($_GET['protocolo']   ?? '');
$filtro_data_de     = trim($_GET['data_de']     ?? '');
$filtro_data_ate    = trim($_GET['data_ate']    ?? '');
$page               = max(1, (int) ($_GET['page'] ?? 1));
$per_page           = max(1, min(50, (int) ($_GET['per_page'] ?? 15)));

if ($filtro_data_de  && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filtro_data_de))  $filtro_data_de  = '';
if ($filtro_data_ate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $filtro_data_ate)) $filtro_data_ate = '';

// ── Queries por tipo (somente esta imobiliária) ───────────────────────────────
$rows = [];

$queries = [
    'fianca' => [
        'sql'  => "SELECT sf.id AS cotacao_id,
                          sf.protocolo,
                          'fianca' AS tipo_key,
                          sf.tipo_ficha,
                          COALESCE(NULLIF(sf.nome_completo,''),'—') AS pretendente,
                          sf.status,
                          COALESCE(sf.data_atualizacao, sf.data_cadastro) AS atualizado_em,
                          sf.data_cadastro AS data_criacao,
                          ua.username AS atendente_nome,
                          ua.avatar AS atendente_avatar,
                          NULLIF(sf.tel_celular, '') AS whatsapp_tel,
                          sf.biometria_url,
                          sf.biometria_status,
                          sf.biometria_link_lido_em,
                          sf.origem AS origem_ficha,
                          sf.encaminhado_mps_detalhe
                   FROM seguro_fianca sf
                   LEFT JOIN usuario ua ON ua.id = sf.atendente_id
                   WHERE sf.imobiliaria_id = ?" . (!$is_imob_admin ? " AND sf.usuario_id = ?" : ""),
        'bind' => !$is_imob_admin ? ['ii', $imobiliaria_id, $usuario_id_filtro] : ['i', $imobiliaria_id],
    ],
    'auto' => [
        'sql'  => "SELECT sa.protocolo,
                          'auto' AS tipo_key,
                          NULL AS tipo_ficha,
                          COALESCE(NULLIF(sa.segurado_nome,''), sa.placa, '—') AS pretendente,
                          sa.status,
                          COALESCE(sa.data_atualizacao, sa.data_cadastro) AS atualizado_em,
                          COALESCE(sa.data_cadastro, sa.data_atualizacao) AS data_criacao,
                          ua.username AS atendente_nome,
                          ua.avatar AS atendente_avatar,
                          NULL AS whatsapp_tel,
                          NULL AS biometria_url,
                          NULL AS biometria_status
                   FROM seguro_auto sa
                   LEFT JOIN usuario ua ON ua.id = sa.atendente_id
                   WHERE sa.imobiliaria_id = ?" . (!$is_imob_admin ? " AND sa.usuario_id = ?" : ""),
        'bind' => !$is_imob_admin ? ['ii', $imobiliaria_id, $usuario_id_filtro] : ['i', $imobiliaria_id],
    ],
    'titulo' => [
        'sql'  => "SELECT tc.protocolo,
                          'titulo' AS tipo_key,
                          NULL AS tipo_ficha,
                          COALESCE(NULLIF(tc.inq_nome,''), tc.prop_nome, '—') AS pretendente,
                          tc.status, COALESCE(tc.data_atualizacao, tc.criado_em) AS atualizado_em,
                          tc.criado_em AS data_criacao,
                          ua.username AS atendente_nome,
                          ua.avatar AS atendente_avatar,
                          COALESCE(NULLIF(tc.inq_telefone1,''), NULLIF(tc.inq_pj_telefone,'')) AS whatsapp_tel,
                          NULL AS biometria_url,
                          NULL AS biometria_status
                   FROM titulo_capitalizacao tc
                   LEFT JOIN usuario ua ON ua.id = tc.atendente_id
                   WHERE tc.imobiliaria_id = ?" . (!$is_imob_admin ? " AND tc.usuario_id = ?" : ""),
        'bind' => !$is_imob_admin ? ['ii', $imobiliaria_id, $usuario_id_filtro] : ['i', $imobiliaria_id],
    ],
    'condominial' => [
        'sql'  => "SELECT sc.protocolo,
                          'condominial' AS tipo_key,
                          NULL AS tipo_ficha,
                          COALESCE(NULLIF(sc.imovel_nome,''),'—') AS pretendente,
                          sc.status, COALESCE(sc.data_atualizacao, sc.criado_em) AS atualizado_em,
                          sc.criado_em AS data_criacao,
                          ua.username AS atendente_nome,
                          ua.avatar AS atendente_avatar,
                          NULLIF(sc.sindico_telefone, '') AS whatsapp_tel,
                          NULL AS biometria_url,
                          NULL AS biometria_status
                   FROM seguro_condominial sc
                   LEFT JOIN usuario ua ON ua.id = sc.atendente_id
                   WHERE sc.imobiliaria_id = ?" . (!$is_imob_admin ? " AND sc.usuario_id = ?" : ""),
        'bind' => !$is_imob_admin ? ['ii', $imobiliaria_id, $usuario_id_filtro] : ['i', $imobiliaria_id],
    ],
    'incendio' => [
        'sql'  => "SELECT si.id AS cotacao_id,
                          si.protocolo,
                          'incendio' AS tipo_key,
                          NULL AS tipo_ficha,
                          NULL AS pretendente,
                          si.status,
                          COALESCE(si.data_atualizacao, si.data_cadastro) AS atualizado_em,
                          si.data_cadastro AS data_criacao,
                          ua.username AS atendente_nome,
                          ua.avatar AS atendente_avatar,
                          NULL AS whatsapp_tel,
                          NULL AS biometria_url,
                          NULL AS biometria_status
                   FROM seguro_incendio si
                   LEFT JOIN usuario ua ON ua.id = si.atendente_id
                   WHERE si.imobiliaria_id = ?" . (!$is_imob_admin ? " AND si.usuario_id = ?" : ""),
        'bind' => !$is_imob_admin ? ['ii', $imobiliaria_id, $usuario_id_filtro] : ['i', $imobiliaria_id],
    ],
    'residencial' => [
        'sql'  => "SELECT sr.id AS cotacao_id,
                          sr.protocolo,
                          'residencial' AS tipo_key,
                          NULL AS tipo_ficha,
                          COALESCE(NULLIF(sr.nome_completo,''),'—') AS pretendente,
                          sr.status,
                          COALESCE(sr.data_atualizacao, sr.data_cadastro) AS atualizado_em,
                          sr.data_cadastro AS data_criacao,
                          ua.username AS atendente_nome,
                          ua.avatar AS atendente_avatar,
                          NULLIF(sr.tel_celular, '') AS whatsapp_tel,
                          NULL AS biometria_url,
                          NULL AS biometria_status,
                          sr.origem AS origem_ficha
                   FROM seguro_residencial sr
                   LEFT JOIN usuario ua ON ua.id = sr.atendente_id
                   WHERE sr.imobiliaria_id = ?" . (!$is_imob_admin ? " AND sr.usuario_id = ?" : ""),
        'bind' => !$is_imob_admin ? ['ii', $imobiliaria_id, $usuario_id_filtro] : ['i', $imobiliaria_id],
    ],
];

// ── Filtra por permissão do usuário: só vê os produtos que tem acesso ────────
// Admin da imobiliária vê tudo; operador comum só vê as próprias cotações
// (filtro por usuario_id já aplicado nas queries acima).
$perm_por_tipo = [
    'fianca'      => 'perm_fianca',
    'auto'        => 'perm_auto',
    'titulo'      => 'perm_titulo',
    'condominial' => 'perm_condo',
    'incendio'    => 'perm_incendio',
    'residencial' => 'perm_seguro_residencial',
];
foreach ($queries as $tipo_key => $q) {
    if (!$is_imob_admin && ($current_user->{$perm_por_tipo[$tipo_key]} ?? 'N') !== 'S') {
        continue;
    }
    try {
        $rows = array_merge($rows, db_fetch_all($q['sql'], $q['bind']));
    } catch (Throwable) {}
}

// Incêndio: "pretendente" é só "N Imóveis" (curto, pra não estourar a
// coluna) — o detalhamento por status, quando misto (ex: 1 recusado, 1 em
// análise), fica num tooltip (ver render abaixo), igual ao hover das
// seguradoras diretas do Fiança.
foreach ($rows as &$r) {
    if ($r['tipo_key'] === 'incendio') {
        $r['pretendente']            = seguro_incendio_resumo_imoveis((int) $r['cotacao_id']);
        $r['incendio_status_mistos'] = seguro_incendio_imoveis_status_mistos((int) $r['cotacao_id']);
    }
}
unset($r);

usort($rows, fn($a, $b) => strcmp($b['atualizado_em'] ?? '', $a['atualizado_em'] ?? ''));

// ── Fianças com as seguradoras ('Cotação API') ────────────────────────────────
// Não é status de atendimento da MPS — pros contadores e filtros, a ficha usa
// o status equivalente à etapa real nas seguradoras (Em Análise, Aprovado,
// Concluída, Recusado). O lote (2 queries) também serve pro render abaixo.
$ids_api = [];
foreach ($rows as $r) {
    if (in_array($r['tipo_key'], ['fianca', 'residencial'], true) && ($r['origem_ficha'] ?? '') === 'api') {
        $ids_api[] = (int) $r['cotacao_id'];
    }
}
$status_api = [];
if ($ids_api !== []) {
    require_once BASE_PATH . '/src/helpers/api.php';
    $status_api = api_status_lote($ids_api);
}
foreach ($rows as &$r) {
    $r['status_filtro'] = (string) ($r['status'] ?? '');
    if (isset($status_api[(int) ($r['cotacao_id'] ?? 0)]) && in_array($r['tipo_key'], ['fianca', 'residencial'], true) && $r['status'] === API_STATUS_FICHA) {
        $lote = $status_api[(int) $r['cotacao_id']];
        $r['status_filtro'] = api_status_equivalente(api_status_resumo_fluxo($lote, false)['categoria']);
    }
}
unset($r);

// ── Contadores ────────────────────────────────────────────────────────────────
$pre_analise_statuses = ['Aguardando liberação', 'Aguardando liberacao', 'Aguardando preenchimento', 'Pré-análise aceita', 'Pre-analise aceita', 'Pré-análise recusada', 'Pre-analise recusada'];

$concluido_statuses = ['Concluída', 'Concluida', 'Concluído', 'Concluido', 'Finalizado'];

$contadores = ['todos' => count($rows), 'pre_analise' => 0, 'em_analise' => 0, 'aguardando' => 0, 'aprovados' => 0, 'recusados' => 0, 'desistencias' => 0, 'concluidos' => 0];
foreach ($rows as $r) {
    $status_val = (string)($r['status_filtro'] ?? '');
    $st = mb_strtolower(trim($status_val));
    if (in_array($status_val, $pre_analise_statuses, true)) $contadores['pre_analise']++;
    elseif (in_array($status_val, $concluido_statuses, true)) $contadores['concluidos']++;
    elseif (cotacao_status_desistencia($status_val))       $contadores['desistencias']++;
    elseif (str_contains($st, 'em an'))           $contadores['em_analise']++;
    elseif (str_contains($st, 'aguardando an')) $contadores['aguardando']++;
    elseif (str_contains($st, 'aprovad'))        $contadores['aprovados']++;
    elseif (str_contains($st, 'recusad'))     $contadores['recusados']++;
}

// ── Filtros ───────────────────────────────────────────────────────────────────
if ($filtro_tipo)        $rows = array_values(array_filter($rows, fn($r) => $r['tipo_key'] === $filtro_tipo));
if ($filtro_status === 'pre_analise') {
    $rows = array_values(array_filter($rows, fn($r) => in_array((string)($r['status_filtro'] ?? ''), $pre_analise_statuses, true)));
} elseif ($filtro_status === 'concluidos') {
    $rows = array_values(array_filter($rows, fn($r) => in_array((string)($r['status_filtro'] ?? ''), $concluido_statuses, true)));
} elseif ($filtro_status) {
    $rows = array_values(array_filter($rows, fn($r) => mb_stripos((string) $r['status_filtro'], $filtro_status) !== false));
}
if ($filtro_pretendente) $rows = array_values(array_filter($rows, fn($r) => mb_stripos((string) $r['pretendente'], $filtro_pretendente) !== false));
if ($filtro_protocolo)   $rows = array_values(array_filter($rows, fn($r) => mb_stripos((string) $r['protocolo'], $filtro_protocolo) !== false));
if ($filtro_data_de)     $rows = array_values(array_filter($rows, fn($r) => substr($r['atualizado_em'] ?? '', 0, 10) >= $filtro_data_de));
if ($filtro_data_ate)    $rows = array_values(array_filter($rows, fn($r) => substr($r['atualizado_em'] ?? '', 0, 10) <= $filtro_data_ate));

// ── Paginação (igual à listagem admin) ────────────────────────────────────────
// As buscas em lote abaixo (não lidas, última proposta) só rodam pras linhas
// desta página — antes rodavam pra TODAS as cotações da imobiliária a cada
// filtro/digitação, o que deixava a tela lenta pra quem tem muitas cotações.
$total       = count($rows);
$total_pages = max(1, (int) ceil($total / $per_page));
if ($page > $total_pages) $page = $total_pages;
$offset      = ($page - 1) * $per_page;
$rows_pagina = array_slice($rows, $offset, $per_page);

// ── Não lidas do chat da cotação (mensagens + eventos de status, em lote) ─────
$unread_map = cotacao_unread_map(array_column($rows_pagina, 'protocolo'), 'imobiliaria', (int) $current_user->uid);

// ── Última proposta (e, no Título, também o boleto/link de pagamento)
// enviados pela MPS por protocolo (uma tabela de anexos por tipo) ─────────────
// $anexos_map guarda por protocolo + tipo_arquivo — Título de Capitalização
// grava "proposta" e "boleto" (ou "link_pagamento_cartao") como registros
// separados, e os outros produtos só usam "proposta". Sem separar por
// tipo_arquivo, o boleto some da listagem (fica só o mais recente dos dois).
$anexos_map = [];
$anexo_tables = [
    'fianca'      => 'cotacoes_anexos',
    'auto'        => 'cotacoes_anexos_auto',
    'titulo'      => 'cotacoes_anexos_titulo',
    'condominial' => 'cotacoes_anexos_condominial',
    'incendio'    => 'cotacoes_anexos_incendio',
    'residencial' => 'cotacoes_anexos_residencial',
];
$protocolos_por_tipo = [];
foreach ($rows_pagina as $r) {
    $protocolos_por_tipo[$r['tipo_key']][] = $r['protocolo'];
}
foreach ($protocolos_por_tipo as $tipo_key => $protos) {
    if (empty($protos)) continue;
    $tabela = $anexo_tables[$tipo_key];
    $placeholders = implode(',', array_fill(0, count($protos), '?'));
    try {
        $anexos_tipo = db_fetch_all(
            "SELECT protocolo, caminho_arquivo, tipo_arquivo, status_aprovacao, data_envio
             FROM {$tabela}
             WHERE protocolo IN ({$placeholders})
             ORDER BY data_envio DESC",
            [str_repeat('s', count($protos)), ...$protos]
        );
        foreach ($anexos_tipo as $a) {
            $tipo_arquivo = $a['tipo_arquivo'] ?: 'proposta';
            // Primeira ocorrência por protocolo+tipo_arquivo = mais recente
            // (ORDER BY data_envio DESC).
            if (!isset($anexos_map[$a['protocolo']][$tipo_arquivo])) {
                $anexos_map[$a['protocolo']][$tipo_arquivo] = $a;
            }
        }
    } catch (Throwable) {}
}

// ── Render linhas (grid de divs — mesma apresentação do admin) ────────────────
ob_start();

if (empty($rows_pagina)): ?>
<div class="admin-cot-empty">Nenhuma cotação encontrada.</div>
<?php else:
    foreach ($rows_pagina as $row):
        $tipo_key      = $row['tipo_key'];
        $tipo_ficha    = $row['tipo_ficha'] ?? null;
        $produto_label = match($tipo_key) {
            'auto'        => 'Seguro Auto',
            'titulo'      => 'Título de Capitalização',
            'condominial' => 'Seguro Condomínio',
            'incendio'    => 'Seguro Incêndio',
            'residencial' => 'Seguro Residencial',
            default       => 'Fiança ' . (strtolower($tipo_ficha ?? '') === 'comercial' ? 'Comercial' : 'Residencial'),
        };
        $status_raw       = (string)($row['status'] ?? '');
        $label             = admin_status_label($status_raw, $tipo_key);
        $pill              = status_pill_class($status_raw);

        // Fiança cotada na API ('Cotação API'): a imobiliária vê só a
        // pré-análise (Em análise / Pré-aprovado), depois as propostas — nada
        // de seguradora por seguradora (isso é da MPS).
        $is_api              = in_array($tipo_key, ['fianca', 'residencial'], true) && ($row['origem_ficha'] ?? '') === 'api';
        if ($is_api && $status_raw === API_STATUS_FICHA) {
            ['label' => $label, 'pill' => $pill] = api_status_resumo_fluxo($status_api[(int) $row['cotacao_id']], false);
        }

        $protocolo_val     = (string)($row['protocolo']      ?? '');
        $pretendente_val   = (string)($row['pretendente']    ?? '—');
        $atendente_val     = (string)($row['atendente_nome'] ?? '');
        $data_criacao_val  = (string)($row['data_criacao']   ?? '');
        $data_atual_val    = (string)($row['atualizado_em']  ?? '');
        $href              = '/cotacao/' . $tipo_key . '/' . urlencode($protocolo_val);

        // Após resposta da pré-análise (aceita ou recusada), a ação principal passa a ser
        // completar a ficha — não há "detalhe" útil para ver antes disso.
        $pode_completar = $tipo_key === 'fianca' && in_array($status_raw, [
            'Pré-análise aceita', 'Pre-analise aceita', 'Pré-análise recusada', 'Pre-analise recusada',
            'Aguardando preenchimento',
        ], true);
        if ($pode_completar) {
            $href_completar = strtolower($tipo_ficha ?? '') === 'comercial'
                ? '/cotacao/fianca/comercial?cotacao=' . urlencode($protocolo_val)
                : '/cotacao/fianca/residencial?cotacao=' . urlencode($protocolo_val);
        }
?>
<div class="admin-cot-row admin-cot-row-imob">
    <div class="admin-cot-cell">
        <span class="tipo-pill tipo-pill-<?= e($tipo_key) ?>"><?= e($produto_label) ?></span>
    </div>
    <div class="admin-cot-cell"><span class="proto-code"><?= e($protocolo_val) ?></span></div>
    <div class="admin-cot-cell admin-cot-cell-nome">
        <?= e($pretendente_val) ?>
        <?php if (!empty($row['incendio_status_mistos'])): ?>
        <span class="api-status-hover" tabindex="0" aria-label="Status por imóvel">
            <?= icon('info') ?>
            <span class="api-status-panel">
                <?php foreach ($row['incendio_status_mistos'] as $im): ?>
                <span class="api-status-panel-item"><strong>Imóvel <?= (int) $im['numero_imovel'] ?>:</strong> <?= e($im['status']) ?></span>
                <?php endforeach; ?>
            </span>
        </span>
        <?php endif; ?>
    </div>
    <div class="admin-cot-cell">
        <span class="status-pill <?= e($pill) ?>"><?= e($label) ?></span>
    </div>
    <div class="admin-cot-cell">
        <?php if ($atendente_val !== ''): ?>
        <span class="atendente-chip"><?= avatar_sm($row['atendente_avatar'] ?? null, $atendente_val) ?> <?= e($atendente_val) ?></span>
        <?php else: ?>
        <span style="color:var(--color-gray-400)">Não atribuído</span>
        <?php endif; ?>
    </div>
    <?php
        // Os downloads da cotação (proposta/boleto/certificado/biometria)
        // eram uma coluna própria ("Cotação") entre Atendente e Data criação —
        // agora entram direto no início da célula de Ações, junto com
        // Ver detalhes/Completar e o chat.
        $proposta_row  = $anexos_map[$protocolo_val]['proposta'] ?? null;
        // Título de Capitalização pode acumular até 3 anexos distintos da MPS
        // (proposta, boleto/link de pagamento, e o certificado depois do
        // pagamento confirmado) — mostrados juntos na listagem, com rótulo,
        // pra não precisar abrir a cotação só pra baixar cada um.
        $eh_titulo     = $tipo_key === 'titulo';
        $eh_incendio   = $tipo_key === 'incendio';
        $boleto_row    = $eh_titulo
            ? ($anexos_map[$protocolo_val]['boleto'] ?? $anexos_map[$protocolo_val]['link_pagamento_cartao'] ?? null)
            : null;
        $boleto_eh_link_pagamento = $boleto_row && ($boleto_row['tipo_arquivo'] ?? '') === 'link_pagamento_cartao';
        $certificado_row = $eh_titulo ? ($anexos_map[$protocolo_val]['certificado'] ?? null) : null;
        $whatsapp_tel  = preg_replace('/\D/', '', (string) ($row['whatsapp_tel'] ?? ''));
        $biometria_ok  = $tipo_key === 'fianca'
            && ($row['biometria_status'] ?? null) === 'pendente'
            && !empty($row['biometria_url']);
        $biometria_nao_lida = $biometria_ok && empty($row['biometria_link_lido_em']);
        // Biometria já resolvida (concluída ou dispensada pela MPS) e ainda sem
        // proposta enviada: nada pra imobiliária fazer, só esperar a MPS. Mas se
        // a cotação já foi recusada ou o cliente desistiu, não tem mais nada a
        // caminho — não faz sentido mostrar "Aguardando envio da MPS".
        $biometria_feita = $tipo_key === 'fianca'
            && in_array($row['biometria_status'] ?? null, ['concluida', 'sem_biometria'], true)
            && !cotacao_status_recusado((string) $status_raw)
            && !cotacao_status_desistencia((string) $status_raw);
        ?>
        <?php
        // Título pode ter até 3 anexos (proposta/boleto/certificado) — a coluna
        // é estreita demais (130px) pra 3 botões com texto lado a lado, então
        // vira 1 botão só que abre um painel com o nome de cada um (mesmo
        // padrão hover já usado acima pro "status por seguradora").
        $anexos_titulo = $eh_titulo ? array_filter([
            $proposta_row    ? ['label' => 'Proposta',                                             'url' => $proposta_row['caminho_arquivo'],    'eh_link' => false] : null,
            $boleto_row      ? ['label' => $boleto_eh_link_pagamento ? 'Link de pagamento (cartão)' : 'Boleto', 'url' => $boleto_row['caminho_arquivo'], 'eh_link' => $boleto_eh_link_pagamento] : null,
            $certificado_row ? ['label' => 'Certificado',                                           'url' => $certificado_row['caminho_arquivo'], 'eh_link' => false] : null,
        ]) : [];
        ?>
    <div class="admin-cot-cell admin-cot-cell-datas">
        <span><span class="admin-cot-data-label">Criada</span> <?= $data_criacao_val ? e(date('d/m/Y H:i', strtotime($data_criacao_val))) : '—' ?></span>
        <span><span class="admin-cot-data-label">Atual.</span> <?= $data_atual_val ? e(date('d/m/Y H:i', strtotime($data_atual_val))) : '—' ?></span>
    </div>
    <div class="admin-cot-cell admin-cot-cell-acoes">
        <div class="table-actions">
            <?php if ($eh_titulo && count($anexos_titulo) > 1): ?>
            <span class="api-status-hover" tabindex="0" aria-label="Arquivos enviados pela MPS">
                <button type="button" class="btn btn-secondary btn-sm" title="Ver arquivos enviados">
                    <?= icon('download') ?>
                </button>
                <span class="api-status-panel anexos-titulo-panel">
                    <?php foreach ($anexos_titulo as $af): ?>
                        <?php if ($af['eh_link']): ?>
                        <button type="button" class="btn btn-secondary btn-sm anexos-titulo-item" data-action="copiar-link-titulo" data-link="<?= e($af['url']) ?>">
                            <?= icon('clipboard-text') ?> <?= e($af['label']) ?>
                        </button>
                        <?php else: ?>
                        <a class="btn btn-secondary btn-sm anexos-titulo-item" href="<?= e($af['url']) ?>" target="_blank">
                            <?= icon('download') ?> <?= e($af['label']) ?>
                        </a>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </span>
            </span>
            <?php elseif ($eh_titulo && count($anexos_titulo) === 1): ?>
            <?php $unico = reset($anexos_titulo); ?>
            <?php if ($unico['eh_link']): ?>
            <button type="button" class="btn btn-secondary btn-sm" data-action="copiar-link-titulo" data-link="<?= e($unico['url']) ?>" title="Copiar link de pagamento">
                <?= icon('clipboard-text') ?>
            </button>
            <?php else: ?>
            <a href="<?= e($unico['url']) ?>" target="_blank" class="btn btn-secondary btn-sm" title="Baixar <?= e(mb_strtolower($unico['label'])) ?>">
                <?= icon('download') ?>
            </a>
            <?php endif; ?>
            <?php if ($proposta_row && $whatsapp_tel): ?>
            <?php $link_wa = 'https://wa.me/55' . $whatsapp_tel . '?text=' . urlencode('Segue sua cotação: ' . $unico['url']); ?>
            <a href="<?= e($link_wa) ?>" target="_blank" class="btn btn-secondary btn-sm" title="Compartilhar via WhatsApp">
                <?= icon('whatsapp-logo') ?>
            </a>
            <?php endif; ?>
            <?php elseif (!$eh_titulo && !$eh_incendio && $proposta_row): ?>
            <a href="<?= e($proposta_row['caminho_arquivo']) ?>" target="_blank" class="btn btn-secondary btn-sm" title="Baixar proposta enviada pela MPS">
                <?= icon('download') ?>
            </a>
            <?php if ($whatsapp_tel): ?>
            <?php $link_wa = 'https://wa.me/55' . $whatsapp_tel . '?text=' . urlencode('Segue sua cotação: ' . $proposta_row['caminho_arquivo']); ?>
            <a href="<?= e($link_wa) ?>" target="_blank" class="btn btn-secondary btn-sm" title="Compartilhar proposta via WhatsApp">
                <?= icon('whatsapp-logo') ?>
            </a>
            <?php endif; ?>
            <?php endif; ?>
            <?php if ($biometria_ok): ?>
            <button type="button" class="btn btn-sm <?= $biometria_nao_lida ? 'btn-danger-outline' : 'btn-secondary' ?>" data-action="copiar-biometria" data-protocolo="<?= e($protocolo_val) ?>" data-link="<?= e($row['biometria_url']) ?>" title="Copiar link da biometria">
                <?= icon('fingerprint') ?>
            </button>
            <?php endif; ?>
            <?php if ($pode_completar): ?>
            <a href="<?= e(url($href_completar)) ?>" class="btn btn-primary btn-sm"><?= icon('note-pencil') ?> Completar cotação</a>
            <?php else: ?>
            <a href="<?= e(url($href)) ?>" target="_blank" rel="noopener" class="btn btn-secondary btn-sm"><?= icon('eye') ?> Ver detalhes</a>
            <?php endif; ?>
            <?php $unread_chat = $unread_map[$protocolo_val] ?? 0; ?>
            <button type="button" class="btn btn-sm btn-chat-cotacao <?= $unread_chat > 0 ? 'btn-danger-outline' : 'btn-secondary' ?>" data-action="abrir-chat-cotacao" data-protocolo="<?= e($protocolo_val) ?>" title="Chat da cotação">
                <?= icon('chat-circle') ?>
                <?php if ($unread_chat > 0): ?>
                    <span class="count-badge"><?= $unread_chat > 99 ? '99+' : e($unread_chat) ?></span>
                <?php endif; ?>
            </button>
        </div>
    </div>
</div>
<?php endforeach;
endif;

$html = ob_get_clean();
echo json_encode([
    'html'        => $html,
    'page'        => $page,
    'per_page'    => $per_page,
    'total'       => $total,
    'total_pages' => $total_pages,
    'contadores'  => $contadores,
]);
