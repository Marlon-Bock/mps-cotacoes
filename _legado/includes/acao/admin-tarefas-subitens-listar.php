<?php
/**
 * GET /api/admin/tarefas/subitens/listar  ?tarefa_id=
 * Lista o checklist (subitens) de uma tarefa — usado pelo bloco expansível na linha.
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');

$ctx       = tarefas_contexto_usuario((int) $current_user->uid);
$tarefa_id = (int) ($_GET['tarefa_id'] ?? 0);

if ($tarefa_id <= 0) { echo json_encode(['ok' => false, 'message' => 'Tarefa inválida.']); exit; }

$tarefa = db_fetch_one('SELECT id, setor_id FROM tarefas WHERE id = ?', ['i', $tarefa_id]);
if (!$tarefa) { echo json_encode(['ok' => false, 'message' => 'Tarefa não encontrada.']); exit; }

if (!$ctx['gestor'] && !in_array((int) $tarefa['setor_id'], $ctx['setores'], true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Sem permissão para esta tarefa.']);
    exit;
}

try {
    $subitens = db_fetch_all(
        'SELECT id, titulo, concluido, ordem FROM tarefas_subitens WHERE tarefa_id = ? ORDER BY ordem ASC, id ASC',
        ['i', $tarefa_id]
    );
    echo json_encode(['ok' => true, 'subitens' => $subitens], JSON_UNESCAPED_UNICODE);
} catch (\Throwable $e) {
    error_log('[admin-tarefas-subitens-listar] ' . $e->getMessage());
    echo json_encode(['ok' => false, 'message' => 'Erro ao listar subitens.']);
}
