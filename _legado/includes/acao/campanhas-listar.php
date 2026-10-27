<?php
/**
 * AJAX endpoint — GET /api/campanhas/listar
 * Lista paginada e filtrável das campanhas visíveis para a imobiliária logada
 * (mesma regra de segmentação de pages/campanhas.php: sem vínculo = pra
 * todas, ou vinculada a ela). Campanhas com status='inativo' nunca aparecem
 * aqui, em nenhum filtro — isso é diferente de "encerrada por data", é uma
 * campanha que o admin escondeu deliberadamente.
 * Retorna JSON { html, total, page, per_page, total_pages, contadores }
 */
$current_user = require_auth();
header('Content-Type: application/json; charset=utf-8');

$imobiliariaId = (int) ($current_user->imobiliaria_id ?? 0);

$busca        = trim($_GET['busca']  ?? '');
$statusRapido = trim($_GET['status_rapido'] ?? '');
if (!in_array($statusRapido, ['ativas', 'encerradas', 'futuras'], true)) $statusRapido = '';

$data_de  = trim($_GET['data_de']  ?? '');
$data_ate = trim($_GET['data_ate'] ?? '');
if ($data_de  && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_de))  $data_de  = '';
if ($data_ate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_ate)) $data_ate = '';

$view = ($_GET['view'] ?? '') === 'list' ? 'list' : 'grid';

$page     = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 12;
$offset   = ($page - 1) * $per_page;

// Visibilidade base: campanha ativa E (sem vínculos OU vinculada a esta imobiliária).
$visibilidade = "c.status = 'ativo' AND (
    NOT EXISTS (SELECT 1 FROM campanha_imobiliarias WHERE campanha_id = c.id)
    OR EXISTS (SELECT 1 FROM campanha_imobiliarias ci WHERE ci.campanha_id = c.id AND ci.imobiliaria_id = ?)
)";

$where  = $visibilidade;
$tipos  = 'i';
$params = [$imobiliariaId];

if ($busca !== '') {
    $where   .= ' AND c.titulo LIKE ?';
    $tipos   .= 's';
    $params[] = "%{$busca}%";
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
if ($statusRapido === 'ativas') {
    $where .= ' AND NOW() BETWEEN c.data_inicio AND c.data_fim';
} elseif ($statusRapido === 'encerradas') {
    $where .= ' AND c.data_fim < NOW()';
} elseif ($statusRapido === 'futuras') {
    $where .= ' AND c.data_inicio > NOW()';
}

$campanhas  = [];
$total      = 0;
$contadores = ['todas' => 0, 'ativas' => 0, 'encerradas' => 0, 'futuras' => 0];

try {
    $sql = "SELECT c.id, c.titulo, c.imagem, c.observacoes, c.data_inicio, c.data_fim
            FROM campanhas c
            WHERE {$where}
            ORDER BY c.data_inicio DESC
            LIMIT ? OFFSET ?";
    $campanhas = db_fetch_all($sql, [$tipos . 'ii', ...$params, $per_page, $offset]);

    $sqlCount = "SELECT COUNT(*) AS total FROM campanhas c WHERE {$where}";
    $total = (int) (db_fetch_one($sqlCount, [$tipos, ...$params])['total'] ?? 0);

    $contadores['todas']      = (int) (db_fetch_one("SELECT COUNT(*) AS total FROM campanhas c WHERE {$visibilidade}", ['i', $imobiliariaId])['total'] ?? 0);
    $contadores['ativas']     = (int) (db_fetch_one("SELECT COUNT(*) AS total FROM campanhas c WHERE {$visibilidade} AND NOW() BETWEEN c.data_inicio AND c.data_fim", ['i', $imobiliariaId])['total'] ?? 0);
    $contadores['encerradas'] = (int) (db_fetch_one("SELECT COUNT(*) AS total FROM campanhas c WHERE {$visibilidade} AND c.data_fim < NOW()", ['i', $imobiliariaId])['total'] ?? 0);
    $contadores['futuras']    = (int) (db_fetch_one("SELECT COUNT(*) AS total FROM campanhas c WHERE {$visibilidade} AND c.data_inicio > NOW()", ['i', $imobiliariaId])['total'] ?? 0);
} catch (Throwable $e) {
    error_log('[campanhas-listar] ' . $e->getMessage());
}

$total_pages = max(1, (int) ceil($total / $per_page));

ob_start();

if (empty($campanhas)): ?>
<div class="admin-cot-empty">Nenhuma campanha encontrada<?= ($busca !== '' || $statusRapido !== '' || $data_de !== '' || $data_ate !== '') ? ' para os filtros aplicados' : '' ?>.</div>
<?php else: ?>
<?php foreach ($campanhas as $campanha):
    $agora = time();
    $inicio = strtotime((string) $campanha['data_inicio']);
    $fim    = strtotime((string) $campanha['data_fim']);

    if ($fim < $agora) {
        $badge = 'Encerrada em ' . date_to_br(substr((string) $campanha['data_fim'], 0, 10));
        $statusLabel = 'Encerrada';
        $statusPill  = 'status-pill-recusado';
    } elseif ($inicio > $agora) {
        $badge = 'Começa em ' . date_to_br(substr((string) $campanha['data_inicio'], 0, 10));
        $statusLabel = 'Futura';
        $statusPill  = 'status-pill-pendente';
    } else {
        $dias_restantes = max(0, (int) ceil(($fim - $agora) / 86400));
        $badge = $dias_restantes > 0 ? "Termina em {$dias_restantes} dia" . ($dias_restantes > 1 ? 's' : '') : 'Encerra hoje';
        $statusLabel = 'Ativa';
        $statusPill  = 'status-pill-aprovado';
    }

    if ($view === 'list'): ?>
<div class="admin-cot-row admin-cot-row-campanhas admin-cot-row-campanhas-imob admin-cot-row-clickable" data-abrir-campanha data-imagem="<?= e($campanha['imagem'] ?? '') ?>" data-titulo="<?= e($campanha['titulo']) ?>" data-observacoes="<?= e($campanha['observacoes'] ?? '') ?>">
    <div class="admin-cot-cell admin-cot-cell-nome"><?= e($campanha['titulo']) ?></div>
    <div class="admin-cot-cell"><span class="status-pill <?= $statusPill ?>"><?= e($statusLabel) ?></span></div>
    <div class="admin-cot-cell"><?= date_to_br(substr((string) $campanha['data_inicio'], 0, 10)) ?> &rarr; <?= date_to_br(substr((string) $campanha['data_fim'], 0, 10)) ?></div>
</div>
<?php else: ?>
<button type="button" class="campaign-card campaign-card-clickable" data-abrir-campanha data-imagem="<?= e($campanha['imagem'] ?? '') ?>" data-titulo="<?= e($campanha['titulo']) ?>" data-observacoes="<?= e($campanha['observacoes'] ?? '') ?>">
    <?php if (!empty($campanha['imagem'])): ?>
    <img src="<?= e($campanha['imagem']) ?>" alt="" loading="lazy">
    <?php endif; ?>
    <div class="campaign-card-overlay">
        <strong><?= e($campanha['titulo']) ?></strong>
        <span class="campaign-card-badge"><?= e($badge) ?></span>
    </div>
</button>
<?php endif; ?>
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
