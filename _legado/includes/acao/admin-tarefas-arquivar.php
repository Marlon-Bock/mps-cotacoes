<?php
/**
 * POST /api/admin/tarefas/arquivar  { id, arquivar: bool }
 * Arquiva/desarquiva uma tarefa (mesmo endpoint serve os dois sentidos).
 * Tarefas arquivadas somem da lista/contadores padrão até o filtro "Arquivadas".
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');
csrf_verify();

$ctx   = tarefas_contexto_usuario((int) $current_user->uid);
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$id       = (int) ($input['id'] ?? 0);
$arquivar = !empty($input['arquivar']);

if ($id <= 0) { echo json_encode(['ok' => false, 'message' => 'Tarefa inválida.']); exit; }

$tarefa = db_fetch_one('SELECT id, setor_id FROM tarefas WHERE id = ?', ['i', $id]);
if (!$tarefa) { echo json_encode(['ok' => false, 'message' => 'Tarefa não encontrada.']); exit; }

if (!$ctx['gestor'] && !in_array((int) $tarefa['setor_id'], $ctx['setores'], true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Sem permissão para esta tarefa.']);
    exit;
}

try {
    db_execute(
        'UPDATE tarefas SET arquivada_em = ' . ($arquivar ? 'NOW()' : 'NULL') . ' WHERE id = ?',
        ['i', $id]
    );
    tarefa_log($id, (int) $current_user->uid, $arquivar ? 'arquivou' : 'desarquivou');
    echo json_encode(['ok' => true]);
} catch (\Throwable $e) {
    error_log('[admin-tarefas-arquivar] ' . $e->getMessage());
    echo json_encode(['ok' => false, 'message' => 'Erro ao arquivar a tarefa.']);
}
