<?php
/**
 * POST /api/admin/tarefas/anexo/excluir  { anexo_id }
 * Remove um anexo de tarefa. Visibilidade por setor.
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');
csrf_verify();

$ctx      = tarefas_contexto_usuario((int) $current_user->uid);
$input    = json_decode(file_get_contents('php://input'), true) ?? [];
$anexo_id = (int) ($input['anexo_id'] ?? 0);

if ($anexo_id <= 0) { echo json_encode(['ok' => false, 'message' => 'Anexo inválido.']); exit; }

$anexo = db_fetch_one(
    'SELECT a.id, a.url, a.nome, a.tarefa_id, t.setor_id
       FROM tarefas_anexos a JOIN tarefas t ON t.id = a.tarefa_id
      WHERE a.id = ?',
    ['i', $anexo_id]
);
if (!$anexo) { echo json_encode(['ok' => false, 'message' => 'Anexo não encontrado.']); exit; }
if (!$ctx['gestor'] && !in_array((int) $anexo['setor_id'], $ctx['setores'], true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Sem permissão.']);
    exit;
}

try {
    db_execute('DELETE FROM tarefas_anexos WHERE id = ?', ['i', $anexo_id]);
    // Remove o arquivo físico: local (/uploads) ou no media server.
    if (is_string($anexo['url']) && $anexo['url'] !== '') {
        if (str_starts_with($anexo['url'], '/uploads/')) {
            $path = BASE_PATH . '/public' . $anexo['url'];
            if (is_file($path)) @unlink($path);
        } else {
            media_delete($anexo['url']);
        }
    }
    tarefa_log((int) $anexo['tarefa_id'], (int) $current_user->uid, 'anexo_del', $anexo['nome'] ?? '');
    echo json_encode(['ok' => true]);
} catch (\Throwable $e) {
    error_log('[admin-tarefas-anexo-excluir] ' . $e->getMessage());
    echo json_encode(['ok' => false, 'message' => 'Erro ao excluir anexo.']);
}
