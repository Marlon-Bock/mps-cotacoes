<?php
/**
 * AJAX endpoint — GET /api/admin/chamados/listar
 * Lista paginada e filtrável de todos os chamados de suporte (todas as
 * imobiliárias). Retorna JSON { html, total, page, per_page, total_pages, contadores }
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');

$status_rapido = trim($_GET['status_rapido'] ?? '');
if (!in_array($status_rapido, ['aguardando_mps', 'aguardando_imob', 'encerrado'], true)) $status_rapido = '';

$imobiliaria_id = (int) ($_GET['imobiliaria_id'] ?? 0);
$atendente_id   = (int) ($_GET['atendente_id']   ?? 0);
$busca          = trim($_GET['busca'] ?? '');

$page     = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 20;
$offset   = ($page - 1) * $per_page;

$where  = '1=1';
$tipos  = '';
$params = [];

if ($status_rapido !== '') {
    $where   .= ' AND cs.status = ?';
    $tipos   .= 's';
    $params[] = $status_rapido;
}
if ($imobiliaria_id > 0) {
    $where   .= ' AND cs.imobiliaria_id = ?';
    $tipos   .= 'i';
    $params[] = $imobiliaria_id;
}
if ($atendente_id > 0) {
    $where   .= ' AND cs.atendente_id = ?';
    $tipos   .= 'i';
    $params[] = $atendente_id;
}
if ($busca !== '') {
    $where   .= ' AND (cs.assunto LIKE ? OR cs.titulo LIKE ?)';
    $tipos   .= 'ss';
    $params[] = "%{$busca}%";
    $params[] = "%{$busca}%";
}

$chamados    = [];
$total       = 0;
$contadores  = ['todos' => 0, 'aguardando_mps' => 0, 'aguardando_imob' => 0, 'encerrado' => 0];

try {
    $sql = "SELECT cs.id, cs.titulo, cs.assunto, cs.status, cs.criado_em, cs.atualizado_em,
                   cs.atendente_nome, i.nome AS imobiliaria_nome, ua.avatar AS atendente_avatar
              FROM chamados_suporte cs
              LEFT JOIN imobiliarias i ON i.id = cs.imobiliaria_id
              LEFT JOIN usuario ua ON ua.id = cs.atendente_id
             WHERE {$where}
          ORDER BY cs.atualizado_em DESC
             LIMIT ? OFFSET ?";
    $chamados = $tipos !== ''
        ? db_fetch_all($sql, [$tipos . 'ii', ...$params, $per_page, $offset])
        : db_fetch_all($sql, ['ii', $per_page, $offset]);

    $sqlCount = "SELECT COUNT(*) AS total FROM chamados_suporte cs WHERE {$where}";
    $total = (int) ($tipos !== ''
        ? (db_fetch_one($sqlCount, [$tipos, ...$params])['total'] ?? 0)
        : (db_fetch_one($sqlCount)['total'] ?? 0));

    $contadores['todos']           = (int) (db_fetch_one('SELECT COUNT(*) AS total FROM chamados_suporte')['total'] ?? 0);
    $contadores['aguardando_mps']  = (int) (db_fetch_one("SELECT COUNT(*) AS total FROM chamados_suporte WHERE status = 'aguardando_mps'")['total'] ?? 0);
    $contadores['aguardando_imob'] = (int) (db_fetch_one("SELECT COUNT(*) AS total FROM chamados_suporte WHERE status = 'aguardando_imob'")['total'] ?? 0);
    $contadores['encerrado']       = (int) (db_fetch_one("SELECT COUNT(*) AS total FROM chamados_suporte WHERE status = 'encerrado'")['total'] ?? 0);
} catch (\Throwable $e) {
    error_log('[admin-chamados-listar] ' . $e->getMessage());
}

$total_pages = max(1, (int) ceil($total / $per_page));

ob_start();

if (empty($chamados)): ?>
<div class="admin-cot-empty">Nenhum chamado encontrado<?= ($status_rapido !== '' || $imobiliaria_id > 0 || $atendente_id > 0 || $busca !== '') ? ' para os filtros aplicados' : '' ?>.</div>
<?php else: ?>
<?php foreach ($chamados as $c):
    $id         = (int) $c['id'];
    $protocolo  = chamado_protocolo($id, $c['criado_em']);
    $status     = (string) ($c['status'] ?? '');
    $atendente  = trim($c['atendente_nome'] ?? '');
    $atualizado = $c['atualizado_em'] ? date('d/m/Y H:i', strtotime($c['atualizado_em'])) : '—';
?>
<div class="admin-cot-row admin-cot-row-chamados-admin">
    <div class="admin-cot-cell"><span class="proto-code"><?= e($protocolo) ?></span></div>
    <div class="admin-cot-cell admin-cot-cell-nome"><?= e($c['titulo'] ?? $c['assunto'] ?? '—') ?></div>
    <div class="admin-cot-cell"><?= e($c['imobiliaria_nome'] ?? '—') ?></div>
    <div class="admin-cot-cell">
        <?php if ($atendente !== ''): ?>
            <span class="atendente-chip"><?= avatar_sm($c['atendente_avatar'] ?? null, $atendente) ?> <?= e($atendente) ?></span>
        <?php else: ?>
            <span class="admin-cot-cell-vazio">Não atribuído</span>
        <?php endif; ?>
    </div>
    <div class="admin-cot-cell"><span class="status-pill <?= chamado_status_pill($status) ?>"><?= chamado_status_label($status) ?></span></div>
    <div class="admin-cot-cell"><?= e($atualizado) ?></div>
    <div class="admin-cot-cell admin-cot-cell-acoes">
        <div class="table-actions">
            <a href="<?= e(BASE_URI) ?>/admin/chamado/<?= e($protocolo) ?>" class="btn btn-secondary btn-sm" title="Ver detalhes"><?= icon('eye') ?></a>
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
