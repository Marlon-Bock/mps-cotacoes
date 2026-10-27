<?php
/**
 * AJAX endpoint — GET /api/admin/cotacoes/listar
 * Visão global da MPS (todas as imobiliárias), com os mesmos filtros/colunas
 * do sistema antigo: produto, protocolo, corretor MPS, imobiliária, período,
 * ordenação por coluna e paginação.
 * Retorna JSON { html, total, page, per_page, total_pages, contadores }
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');

$status_rapido = trim($_GET['status_rapido'] ?? '');
$protocolo     = trim($_GET['protocolo']     ?? '');
$corretor_id   = max(0, (int)($_GET['corretor_id'] ?? 0));
$busca         = trim($_GET['busca']         ?? ''); // imobiliária, pretendente ou CPF/CNPJ
$busca_digitos = preg_replace('/\D+/', '', $busca);
$produto       = trim($_GET['produto']       ?? ''); // '' | 'fianca' | 'auto' | 'titulo' | 'condominial'
$data_de       = trim($_GET['data_de']       ?? '');
$data_ate      = trim($_GET['data_ate']      ?? '');
$page          = max(1, (int)($_GET['page']     ?? 1));
$per_page      = max(1, min(50, (int)($_GET['per_page'] ?? 15)));
$sort_by       = trim($_GET['sort_by']       ?? 'atualizacao');
$sort_dir      = strtolower(trim($_GET['sort_dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

if ($data_de  && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_de))  $data_de  = '';
if ($data_ate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_ate)) $data_ate = '';

$rows = admin_cotacoes_buscar_todas($produto);

// Operador MPS restrito a só alguns produtos (perm_fianca/perm_auto/perm_titulo/
// perm_condominial/perm_incendio) não vê os demais aqui — ver admin_produtos_permitidos().
$produtos_permitidos = admin_produtos_permitidos($current_user);
if ($produtos_permitidos !== null) {
    $rows = array_values(array_filter($rows, fn($r) => in_array($r['origem'] ?? '', $produtos_permitidos, true)));
}

// ── Filtros ───────────────────────────────────────────────────────────────────
if ($protocolo !== '')  $rows = array_values(array_filter($rows, fn($r) => mb_stripos($r['protocolo'] ?? '', $protocolo) !== false));
if ($corretor_id > 0)   $rows = array_values(array_filter($rows, fn($r) => (int)($r['atendente_id'] ?? 0) === $corretor_id));
// Um único campo busca por imobiliária, pretendente/segurado ou CPF/CNPJ
// (com ou sem máscara) — o que bater já inclui a linha.
if ($busca !== '') {
    $rows = array_values(array_filter($rows, function ($r) use ($busca, $busca_digitos) {
        if (mb_stripos($r['imob_nome'] ?? '', $busca) !== false)   return true;
        if (mb_stripos($r['pretendente'] ?? '', $busca) !== false) return true;
        if ($busca_digitos !== '' && str_contains(preg_replace('/\D+/', '', (string) ($r['cpf_cnpj'] ?? '')), $busca_digitos)) return true;
        return false;
    }));
}
if ($data_de !== '')    $rows = array_values(array_filter($rows, fn($r) => substr($r['atualizado_em'] ?? '', 0, 10) >= $data_de));
if ($data_ate !== '')   $rows = array_values(array_filter($rows, fn($r) => substr($r['atualizado_em'] ?? '', 0, 10) <= $data_ate));

// ── API das seguradoras: fichas esperando a MPS (fazer cotação / enviar apólice) ──
require_once BASE_PATH . '/src/helpers/api.php';
$rows = admin_cotacoes_marcar_api($rows);

// ── Contadores (calculados antes do filtro de status) ─────────────────────────
$cont = admin_cotacoes_contadores($rows);

// ── Filtro de status rápido ───────────────────────────────────────────────────
$rows = admin_cotacoes_filtrar_status($rows, $status_rapido);

// ── Ordenação ─────────────────────────────────────────────────────────────────
usort($rows, function ($a, $b) use ($sort_by, $sort_dir) {
    $campo = match ($sort_by) {
        'criacao'     => 'data_cadastro',
        'status'      => 'status',
        'imobiliaria' => 'imob_nome',
        'atendente'   => 'atendente_nome',
        'protocolo'   => 'protocolo',
        'produto'     => 'origem',
        default       => 'atualizado_em',
    };
    $cmp = strcmp((string)($a[$campo] ?? ''), (string)($b[$campo] ?? ''));
    return $sort_dir === 'asc' ? $cmp : -$cmp;
});

// ── Paginação ─────────────────────────────────────────────────────────────────
$total       = count($rows);
$total_pages = max(1, (int) ceil($total / $per_page));
if ($page > $total_pages) $page = $total_pages;
$offset = ($page - 1) * $per_page;
$rows_pagina = array_slice($rows, $offset, $per_page);

// ── Fiança cotada na API das seguradoras: status em cada seguradora, em lote ──
// Enquanto a ficha está em 'Cotação API' (fora da fila da MPS), o Status
// mostra a etapa nas seguradoras (Cotando, Aprovada, Apólice emitida...) — a
// MPS acompanha sem abrir a cotação; o (i) detalha por seguradora.
$ids_api = [];
foreach ($rows_pagina as $r) {
    if (in_array($r['origem'] ?? '', ['fianca', 'residencial'], true) && ($r['origem_ficha'] ?? '') === 'api') {
        $ids_api[] = (int) $r['id'];
    }
}
$status_api = [];
if ($ids_api !== []) {
    require_once BASE_PATH . '/src/helpers/api.php';
    $status_api = api_status_lote($ids_api);
}

// ── Não lidas do chat da cotação (mensagens + eventos de status, em lote) ─────
$unread_map = cotacao_unread_map(array_column($rows_pagina, 'protocolo'), 'admin', (int) $current_user->uid);
// Novidades na tela das seguradoras (fichas da API) — contador no botão.
$novidades_api = $ids_api === [] ? [] : api_novidades_lote(
    array_column(array_filter($rows_pagina, fn($r) => in_array($r['origem'] ?? 'fianca', ['fianca', 'residencial'], true) && ($r['origem_ficha'] ?? '') === 'api'), 'id'),
    'admin',
    (int) $current_user->uid
);

// ── Render HTML (linhas em grid — sem <table>) ─────────────────────────────────
ob_start();

if (empty($rows_pagina)): ?>
<div class="admin-cot-empty">Nenhuma cotação encontrada.</div>
<?php else:
    foreach ($rows_pagina as $row):
        $origem      = $row['origem'] ?? 'fianca';
        $status_raw  = (string)($row['status'] ?? '');
        $label       = admin_status_label($status_raw, $origem);
        $pill        = status_pill_class($status_raw);

        $is_api       = in_array($origem, ['fianca', 'residencial'], true) && ($row['origem_ficha'] ?? '') === 'api';
        $cotacoes_api = $is_api ? ($status_api[(int) $row['id']]['cotacoes'] ?? []) : [];
        if ($is_api && $status_raw === API_STATUS_FICHA) {
            ['label' => $label, 'pill' => $pill] = api_status_resumo_fluxo($status_api[(int) $row['id']]);
        }

        $produto_label = match($origem) {
            'auto'        => 'Seguro Auto',
            'titulo'      => 'Título de Capitalização',
            'condominial' => 'Seguro Condomínio',
            'incendio'    => 'Seguro Incêndio',
            'residencial' => 'Seguro Residencial',
            default       => 'Fiança ' . (strtolower($row['tipo_ficha'] ?? '') === 'comercial' ? 'Comercial' : 'Residencial'),
        };
        $tipo_pill_key = match($origem) { 'auto' => 'auto', 'titulo' => 'titulo', 'condominial' => 'condominial', 'incendio' => 'incendio', 'residencial' => 'residencial', default => 'fianca' };

        $protocolo_val    = (string)($row['protocolo']      ?? '');
        $pretendente_val  = (string)($row['pretendente']    ?? '—');
        $imob_nome_val    = (string)($row['imob_nome']      ?? '');
        $atendente_val    = (string)($row['atendente_nome'] ?? '');
        $data_criacao_val = (string)($row['data_cadastro']  ?? '');
        $data_atual_val   = (string)($row['atualizado_em']  ?? '');

        $link = cotacao_admin_url($protocolo_val);
?>
<div class="admin-cot-row">
    <div class="admin-cot-cell">
        <span class="tipo-pill tipo-pill-<?= e($tipo_pill_key) ?>"><?= e($produto_label) ?></span>
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
        <?php if ($is_api): ?>
        <span class="api-status-hover" tabindex="0" aria-label="Status por seguradora">
            <?= icon('info') ?>
            <span class="api-status-panel api-status-panel--seguradoras"><?= api_status_painel_html($cotacoes_api, (string) ($row['encaminhado_mps_detalhe'] ?? ''), $status_raw, $status_api[(int) $row['id']]['propostas'] ?? []) ?></span>
        </span>
        <?php endif; ?>
    </div>
    <div class="admin-cot-cell"><?= e($imob_nome_val ?: '—') ?></div>
    <div class="admin-cot-cell">
        <?php if ($atendente_val !== ''): ?>
        <span class="atendente-chip"><?= avatar_sm($row['atendente_avatar'] ?? null, $atendente_val) ?> <?= e($atendente_val) ?></span>
        <?php else: ?>
        <span style="color:var(--color-gray-400)">Não atribuído</span>
        <?php endif; ?>
    </div>
    <div class="admin-cot-cell admin-cot-cell-datas">
        <span><span class="admin-cot-data-label">Criada</span> <?= $data_criacao_val ? e(date('d/m/Y H:i', strtotime($data_criacao_val))) : '—' ?></span>
        <span><span class="admin-cot-data-label">Atual.</span> <?= $data_atual_val ? e(date('d/m/Y H:i', strtotime($data_atual_val))) : '—' ?></span>
    </div>
    <div class="admin-cot-cell admin-cot-cell-acoes">
        <div class="table-actions">
            <a href="<?= e($link) ?>" target="_blank" rel="noopener" class="btn btn-secondary btn-sm"><?= icon('eye') ?> Ver detalhes</a>
            <?php if ($is_api): ?>
            <?php $novas_api = $novidades_api[(int) $row['id']] ?? 0; ?>
            <a href="<?= e(url('/admin/cotacao/' . $origem . '/' . rawurlencode($protocolo_val) . '/seguradoras')) ?>" target="_blank" rel="noopener" class="btn btn-sm btn-chat-cotacao <?= $novas_api > 0 ? 'btn-danger-outline' : 'btn-secondary' ?>" title="Seguradoras" aria-label="Seguradoras">
                <?= icon('shield-check') ?>
                <?php if ($novas_api > 0): ?>
                    <span class="count-badge"><?= $novas_api > 99 ? '99+' : e($novas_api) ?></span>
                <?php endif; ?>
            </a>
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
    'contadores'  => $cont,
]);
