<?php
/**
 * AJAX endpoint — GET /api/admin/imobiliarias/listar
 * Lista paginada e filtrável das imobiliárias parceiras.
 * Retorna JSON { html, total, page, per_page, total_pages, contadores }
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');

$busca  = trim($_GET['busca']  ?? '');
$status = trim($_GET['status'] ?? '');
if (!in_array($status, ['ativo', 'inativo'], true)) $status = '';

$page     = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 20;
$offset   = ($page - 1) * $per_page;

$colunas_ordenaveis = ['nome', 'cnpj', 'email', 'telefone', 'ativo', 'criado_em'];
$sort_by  = in_array($_GET['sort_by'] ?? '', $colunas_ordenaveis, true) ? $_GET['sort_by'] : 'nome';
$sort_dir = strtolower($_GET['sort_dir'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';

$where  = '1=1';
$tipos  = '';
$params = [];

if ($busca !== '') {
    $where   .= ' AND (nome LIKE ? OR cnpj LIKE ?)';
    $tipos   .= 'ss';
    $params[] = "%{$busca}%";
    $params[] = "%{$busca}%";
}
if ($status !== '') {
    $where   .= ' AND ativo = ?';
    $tipos   .= 'i';
    $params[] = $status === 'ativo' ? 1 : 0;
}

$imobiliarias = [];
$total        = 0;
$contadores   = ['todas' => 0, 'ativas' => 0, 'inativas' => 0];

try {
    $sql = "SELECT id, nome, slug, cnpj, email, telefone, ativo, criado_em FROM imobiliarias WHERE {$where} ORDER BY {$sort_by} {$sort_dir} LIMIT ? OFFSET ?";
    $imobiliarias = $tipos !== ''
        ? db_fetch_all($sql, [$tipos . 'ii', ...$params, $per_page, $offset])
        : db_fetch_all($sql, ['ii', $per_page, $offset]);

    $sqlCount = "SELECT COUNT(*) AS total FROM imobiliarias WHERE {$where}";
    $total = (int) ($tipos !== ''
        ? (db_fetch_one($sqlCount, [$tipos, ...$params])['total'] ?? 0)
        : (db_fetch_one($sqlCount)['total'] ?? 0));

    $contadores['todas']    = (int) (db_fetch_one('SELECT COUNT(*) AS total FROM imobiliarias')['total'] ?? 0);
    $contadores['ativas']   = (int) (db_fetch_one('SELECT COUNT(*) AS total FROM imobiliarias WHERE ativo = 1')['total'] ?? 0);
    $contadores['inativas'] = (int) (db_fetch_one('SELECT COUNT(*) AS total FROM imobiliarias WHERE ativo = 0')['total'] ?? 0);
} catch (Throwable $e) {
    error_log('[imobiliarias-listar] ' . $e->getMessage());
}

$total_pages = max(1, (int) ceil($total / $per_page));

ob_start();

if (empty($imobiliarias)): ?>
<div class="admin-cot-empty">Nenhuma imobiliária encontrada<?= ($busca !== '' || $status !== '') ? ' para os filtros aplicados' : '' ?>.</div>
<?php else: ?>
<?php foreach ($imobiliarias as $imob): ?>
<div class="admin-cot-row admin-cot-row-imobiliarias">
    <div class="admin-cot-cell admin-cot-cell-nome"><?= e($imob['nome']) ?></div>
    <div class="admin-cot-cell"><?= e($imob['cnpj'] ?: '—') ?></div>
    <div class="admin-cot-cell"><?= e($imob['email'] ?: '—') ?></div>
    <div class="admin-cot-cell"><?= e($imob['telefone'] ?: '—') ?></div>
    <div class="admin-cot-cell">
        <?php if (!empty($imob['ativo'])): ?>
            <span class="status-pill status-pill-aprovado">Ativa</span>
        <?php else: ?>
            <span class="status-pill status-pill-recusado">Inativa</span>
        <?php endif; ?>
    </div>
    <div class="admin-cot-cell"><?= e($imob['criado_em'] ? date_to_br(substr($imob['criado_em'], 0, 10)) : '—') ?></div>
    <div class="admin-cot-cell admin-cot-cell-acoes">
        <div class="table-actions">
            <a href="<?= e(BASE_URI) ?>/admin/imobiliarias/<?= e($imob['slug']) ?>/editar" class="btn btn-secondary btn-sm" title="Editar"><?= icon('note-pencil') ?></a>
            <a href="<?= e(BASE_URI) ?>/admin/imobiliarias/<?= e($imob['slug']) ?>/usuarios" class="btn btn-secondary btn-sm" title="Usuários"><?= icon('users') ?></a>
        </div>
    </div>
</div>
<?php endforeach; ?>
<?php endif;

$html = ob_get_clean();

echo json_encode([
    'html'        => $html,
    'total'       => $total,
    'page'        => $page,
    'per_page'    => $per_page,
    'total_pages' => $total_pages,
    'contadores'  => $contadores,
]);
