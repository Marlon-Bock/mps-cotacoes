<?php
/**
 * POST /api/admin/tarefas/excluir  { id }
 * Exclui uma tarefa e seus vínculos (responsáveis, etiquetas, valores de campos).
 * Não-gestor só exclui tarefas dos seus setores.
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');
csrf_verify();

$ctx   = tarefas_contexto_usuario((int) $current_user->uid);
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$id    = (int) ($input['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['ok' => false, 'message' => 'Tarefa inválida.']);
    exit;
}

$tarefa = db_fetch_one('SELECT id, setor_id FROM tarefas WHERE id = ?', ['i', $id]);
if (!$tarefa) {
    echo json_encode(['ok' => false, 'message' => 'Tarefa não encontrada.']);
    exit;
}
if (!$ctx['gestor'] && !in_array((int) $tarefa['setor_id'], $ctx['setores'], true)) {
    echo json_encode(['ok' => false, 'message' => 'Sem permissão para excluir esta tarefa.']);
    exit;
}

try {
    db_execute('DELETE FROM tarefas_responsaveis WHERE tarefa_id = ?', ['i', $id]);
    db_execute('DELETE FROM tarefas_etiquetas_vinculo WHERE tarefa_id = ?', ['i', $id]);
    db_execute('DELETE FROM tarefas_campos_valores WHERE tarefa_id = ?', ['i', $id]);
    db_execute('DELETE FROM tarefas WHERE id = ?', ['i', $id]);
    echo json_encode(['ok' => true]);
} catch (\Throwable $e) {
    error_log('[admin-tarefas-excluir] ' . $e->getMessage());
    echo json_encode(['ok' => false, 'message' => 'Erro ao excluir a tarefa.']);
}
