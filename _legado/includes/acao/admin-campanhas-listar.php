<?php
/**
 * AJAX endpoint — GET /api/admin/campanhas/listar
 * Lista paginada e filtrável das campanhas.
 * Retorna JSON { html, total, page, per_page, total_pages, contadores }
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');

$busca   = trim($_GET['busca']  ?? '');
$status  = trim($_GET['status'] ?? '');
if (!in_array($status, ['ativo', 'inativo'], true)) $status = '';

$data_de  = trim($_GET['data_de']  ?? '');
$data_ate = trim($_GET['data_ate'] ?? '');
if ($data_de  && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_de))  $data_de  = '';
if ($data_ate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_ate)) $data_ate = '';

$view = ($_GET['view'] ?? '') === 'grid' ? 'grid' : 'list';

$page     = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 20;
$offset   = ($page - 1) * $per_page;

$where  = '1=1';
$tipos  = '';
$params = [];

if ($busca !== '') {
    $where   .= ' AND c.titulo LIKE ?';
    $tipos   .= 's';
    $params[] = "%{$busca}%";
}
if ($status !== '') {
    $where   .= ' AND c.status = ?';
    $tipos   .= 's';
    $params[] = $status;
}
if ($data_de !== '') {
    $where   .= ' AND DATE(c.data_inicio) >= ?';
    $tipos   .= 's';
    $params[] = $data_de;
}
if ($data_ate !== '') {
    $where   .= ' AND DATE(c.data_inicio) <= ?';
    $tipos   .= 's';
    $params[] = $data_ate;
}

$campanhas  = [];
$total      = 0;
$contadores = ['todas' => 0, 'ativas' => 0, 'inativas' => 0];

try {
    $sql = "SELECT c.id, c.titulo, c.status, c.imagem, c.observacoes, c.data_inicio, c.data_fim,
                   COUNT(ci.imobiliaria_id) AS total_imobiliarias
            FROM campanhas c
            LEFT JOIN campanha_imobiliarias ci ON ci.campanha_id = c.id
            WHERE {$where}
            GROUP BY c.id
            ORDER BY c.data_inicio DESC
            LIMIT ? OFFSET ?";
    $campanhas = $tipos !== ''
        ? db_fetch_all($sql, [$tipos . 'ii', ...$params, $per_page, $offset])
        : db_fetch_all($sql, ['ii', $per_page, $offset]);

    $sqlCount = "SELECT COUNT(*) AS total FROM campanhas c WHERE {$where}";
    $total = (int) ($tipos !== ''
        ? (db_fetch_one($sqlCount, [$tipos, ...$params])['total'] ?? 0)
        : (db_fetch_one($sqlCount)['total'] ?? 0));

    $contadores['todas']    = (int) (db_fetch_one('SELECT COUNT(*) AS total FROM campanhas')['total'] ?? 0);
    $contadores['ativas']   = (int) (db_fetch_one("SELECT COUNT(*) AS total FROM campanhas WHERE status = 'ativo'")['total'] ?? 0);
    $contadores['inativas'] = (int) (db_fetch_one("SELECT COUNT(*) AS total FROM campanhas WHERE status = 'inativo'")['total'] ?? 0);
} catch (Throwable $e) {
    error_log('[campanhas-listar] ' . $e->getMessage());
}

$total_pages = max(1, (int) ceil($total / $per_page));

ob_start();

if (empty($campanhas)): ?>
<div class="admin-cot-empty">Nenhuma campanha encontrada<?= ($busca !== '' || $status !== '' || $data_de !== '' || $data_ate !== '') ? ' para os filtros aplicados' : '' ?>.</div>
<?php elseif ($view === 'grid'): ?>
<?php foreach ($campanhas as $c): ?>
<div class="campaign-card" data-abrir-campanha data-imagem="<?= e($c['imagem'] ?? '') ?>" data-titulo="<?= e($c['titulo']) ?>" data-observacoes="<?= e($c['observacoes'] ?? '') ?>">
    <?php if (!empty($c['imagem'])): ?>
    <img src="<?= e($c['imagem']) ?>" alt="" loading="lazy">
    <?php endif; ?>
    <div class="campaign-card-overlay">
        <strong><?= e($c['titulo']) ?></strong>
        <span class="campaign-card-badge">
            <?= $c['status'] === 'ativo' ? 'Ativa' : 'Inativa' ?>
            &middot; <?= $c['total_imobiliarias'] > 0 ? (int) $c['total_imobiliarias'] . ' imobiliária(s)' : 'Todas as imobiliárias' ?>
            &middot; <?= date_to_br(substr((string) $c['data_inicio'], 0, 10)) ?> &rarr; <?= date_to_br(substr((string) $c['data_fim'], 0, 10)) ?>
        </span>
    </div>
    <div class="campaign-card-actions">
        <a href="<?= e(BASE_URI) ?>/admin/campanhas/<?= (int) $c['id'] ?>/editar" class="btn btn-secondary btn-sm"><?= icon('note-pencil') ?> <span class="btn-label">Editar</span></a>
        <button type="button" class="btn btn-danger-outline btn-sm" data-action="excluir-campanha" data-id="<?= (int) $c['id'] ?>"><?= icon('x') ?> <span class="btn-label">Excluir</span></button>
    </div>
</div>
<?php endforeach; ?>
<?php else: ?>
<?php foreach ($campanhas as $c): ?>
<div class="admin-cot-row admin-cot-row-campanhas admin-cot-row-clickable" data-abrir-campanha data-imagem="<?= e($c['imagem'] ?? '') ?>" data-titulo="<?= e($c['titulo']) ?>" data-observacoes="<?= e($c['observacoes'] ?? '') ?>">
    <div class="admin-cot-cell admin-cot-cell-nome"><?= e($c['titulo']) ?></div>
    <div class="admin-cot-cell">
        <?php if ($c['status'] === 'ativo'): ?>
            <span class="status-pill status-pill-aprovado">Ativa</span>
        <?php else: ?>
            <span class="status-pill status-pill-recusado">Inativa</span>
        <?php endif; ?>
    </div>
    <div class="admin-cot-cell"><?= $c['total_imobiliarias'] > 0 ? (int) $c['total_imobiliarias'] : 'Todas' ?></div>
    <div class="admin-cot-cell">
        <?= date_to_br(substr((string) $c['data_inicio'], 0, 10)) ?> &rarr; <?= date_to_br(substr((string) $c['data_fim'], 0, 10)) ?>
    </div>
    <div class="admin-cot-cell admin-cot-cell-acoes">
        <div class="table-actions">
            <a href="<?= e(BASE_URI) ?>/admin/campanhas/<?= (int) $c['id'] ?>/editar" class="btn btn-secondary btn-sm"><?= icon('note-pencil') ?> Editar</a>
            <button type="button" class="btn btn-danger-outline btn-sm" data-action="excluir-campanha" data-id="<?= (int) $c['id'] ?>"><?= icon('x') ?> Excluir</button>
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
