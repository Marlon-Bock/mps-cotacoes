<?php
/**
 * AJAX endpoint — GET /api/admin/leads/listar
 * Leads do site (tabela leads_site) com filtros e paginação.
 * Retorna JSON { html, total, page, per_page, total_pages }
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');

$seguro        = trim($_GET['seguro']        ?? '');
$protocolo     = trim($_GET['protocolo']     ?? '');
$busca         = trim($_GET['busca']         ?? '');
$busca_digitos = preg_replace('/\D+/', '', $busca);
$data_de       = trim($_GET['data_de']       ?? '');
$data_ate      = trim($_GET['data_ate']      ?? '');
$page          = max(1, (int)($_GET['page']     ?? 1));
$per_page      = max(1, min(50, (int)($_GET['per_page'] ?? 15)));
$sort_dir      = strtolower(trim($_GET['sort_dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';

if ($data_de  && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_de))  $data_de  = '';
if ($data_ate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_ate)) $data_ate = '';

$rows = [];
try {
    $rows = db_fetch_all('SELECT id, protocolo, seguro, origem AS origem_site, perfil, nome, email, telefone, dados, criado_em FROM leads_site');
} catch (Throwable) {}

foreach ($rows as &$r) {
    $dados = json_decode((string) $r['dados'], true);
    if (!is_array($dados)) $dados = [];
    [$r['origem'], $r['produto_label']] = lead_site_produto((string) $r['seguro'], $dados);
    $r['cpf']           = (string) ($dados['pretendente']['cpf'] ?? $dados['pretendente']['cnpj'] ?? '');
}
unset($r);

// Operador restrito só vê os leads dos produtos liberados; seguro fora do sistema só admin.
$rows = array_values(array_filter($rows, fn($r) => lead_site_visivel($current_user, $r['origem'])));

// ── Filtros ───────────────────────────────────────────────────────────────────
if ($seguro !== '')    $rows = array_values(array_filter($rows, fn($r) => $r['seguro'] === $seguro));
if ($protocolo !== '') $rows = array_values(array_filter($rows, fn($r) => mb_stripos($r['protocolo'], $protocolo) !== false));
if ($busca !== '') {
    $rows = array_values(array_filter($rows, function ($r) use ($busca, $busca_digitos) {
        if (mb_stripos((string) $r['nome'], $busca) !== false)  return true;
        if (mb_stripos((string) $r['email'], $busca) !== false) return true;
        if ($busca_digitos !== '' && str_contains(preg_replace('/\D+/', '', $r['telefone'] . ' ' . $r['cpf']), $busca_digitos)) return true;
        return false;
    }));
}
if ($data_de !== '')  $rows = array_values(array_filter($rows, fn($r) => substr($r['criado_em'], 0, 10) >= $data_de));
if ($data_ate !== '') $rows = array_values(array_filter($rows, fn($r) => substr($r['criado_em'], 0, 10) <= $data_ate));

// ── Ordenação / paginação ─────────────────────────────────────────────────────
usort($rows, fn($a, $b) => $sort_dir === 'asc' ? strcmp($a['criado_em'], $b['criado_em']) : strcmp($b['criado_em'], $a['criado_em']));

$total       = count($rows);
$total_pages = max(1, (int) ceil($total / $per_page));
if ($page > $total_pages) $page = $total_pages;
$rows_pagina = array_slice($rows, ($page - 1) * $per_page, $per_page);

// ── Render HTML (linhas em grid — sem <table>) ─────────────────────────────────
ob_start();

if (empty($rows_pagina)): ?>
<div class="admin-cot-empty">Nenhum lead encontrado.</div>
<?php else:
    foreach ($rows_pagina as $row):
        $link = url('/admin/leads/' . rawurlencode($row['protocolo']));
?>
<div class="admin-cot-row">
    <div class="admin-cot-cell"><span class="tipo-pill <?= e(lead_site_pill_class($row['seguro'])) ?>"><?= e($row['produto_label']) ?></span><?php if ($row['origem_site'] === 'suspeito'): ?> <span class="tipo-pill tipo-pill-lead-suspeito" title="Envio suspeito de spam (isca preenchida ou rápido demais) — não gera aviso">Suspeito</span><?php endif; ?></div>
    <div class="admin-cot-cell"><span class="proto-code"><?= e($row['protocolo']) ?></span></div>
    <div class="admin-cot-cell admin-cot-cell-nome"><?= e($row['nome'] ?: '—') ?></div>
    <div class="admin-cot-cell"><?= e($row['telefone'] ?: '—') ?></div>
    <div class="admin-cot-cell"><?= e($row['email'] ?: '—') ?></div>
    <div class="admin-cot-cell"><?= e($row['perfil'] ? lead_site_perfil_label($row['perfil']) : '—') ?></div>
    <div class="admin-cot-cell admin-cot-cell-datas">
        <span><span class="admin-cot-data-label">Enviado</span> <?= e(date('d/m/Y H:i', strtotime($row['criado_em']))) ?></span>
    </div>
    <div class="admin-cot-cell admin-cot-cell-acoes">
        <div class="table-actions">
            <a href="<?= e($link) ?>" target="_blank" rel="noopener" class="btn btn-secondary btn-sm"><?= icon('eye') ?> Ver detalhes</a>
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
]);
