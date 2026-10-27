<?php
/**
 * POST /api/admin/tarefas/prazo  { id, prazo }  (prazo = 'YYYY-MM-DD' ou '')
 * Define/limpa o prazo de uma tarefa (calendário inline). Visibilidade por setor.
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');
csrf_verify();

$ctx   = tarefas_contexto_usuario((int) $current_user->uid);
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$id    = (int) ($input['id'] ?? 0);
$prazo = trim($input['prazo'] ?? '');

if ($id <= 0) { echo json_encode(['ok' => false, 'message' => 'Tarefa inválida.']); exit; }
if ($prazo !== '' && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $prazo)) $prazo = '';

$tarefa = db_fetch_one('SELECT id, setor_id, prazo FROM tarefas WHERE id = ?', ['i', $id]);
if (!$tarefa) { echo json_encode(['ok' => false, 'message' => 'Tarefa não encontrada.']); exit; }

if (!$ctx['gestor'] && !in_array((int) $tarefa['setor_id'], $ctx['setores'], true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Sem permissão para esta tarefa.']);
    exit;
}

try {
    db_execute('UPDATE tarefas SET prazo = ? WHERE id = ?', ['si', $prazo !== '' ? $prazo : null, $id]);
    if ((string) ($tarefa['prazo'] ?? '') !== $prazo) {
        tarefa_log($id, (int) $current_user->uid, 'prazo', $prazo !== '' ? date('d/m/Y', strtotime($prazo)) : '');
    }
    echo json_encode(['ok' => true]);
} catch (\Throwable $e) {
    error_log('[admin-tarefas-prazo] ' . $e->getMessage());
    echo json_encode(['ok' => false, 'message' => 'Erro ao salvar prazo.']);
}
