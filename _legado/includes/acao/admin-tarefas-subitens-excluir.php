<?php
/**
 * POST /api/admin/tarefas/subitens/excluir  { id }
 * Exclui um subitem do checklist.
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');
csrf_verify();

$ctx   = tarefas_contexto_usuario((int) $current_user->uid);
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$id    = (int) ($input['id'] ?? 0);

if ($id <= 0) { echo json_encode(['ok' => false, 'message' => 'Subitem inválido.']); exit; }

$sub = db_fetch_one(
    'SELECT si.id, si.tarefa_id, t.setor_id FROM tarefas_subitens si JOIN tarefas t ON t.id = si.tarefa_id WHERE si.id = ?',
    ['i', $id]
);
if (!$sub) { echo json_encode(['ok' => false, 'message' => 'Subitem não encontrado.']); exit; }

if (!$ctx['gestor'] && !in_array((int) $sub['setor_id'], $ctx['setores'], true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Sem permissão para esta tarefa.']);
    exit;
}

try {
    db_execute('DELETE FROM tarefas_subitens WHERE id = ?', ['i', $id]);
    $total = db_fetch_one(
        "SELECT COUNT(*) AS total, SUM(concluido = 'S') AS feitos FROM tarefas_subitens WHERE tarefa_id = ?",
        ['i', $sub['tarefa_id']]
    );
    echo json_encode([
        'ok'     => true,
        'total'  => (int) ($total['total'] ?? 0),
        'feitos' => (int) ($total['feitos'] ?? 0),
    ]);
} catch (\Throwable $e) {
    error_log('[admin-tarefas-subitens-excluir] ' . $e->getMessage());
    echo json_encode(['ok' => false, 'message' => 'Erro ao excluir o subitem.']);
}
