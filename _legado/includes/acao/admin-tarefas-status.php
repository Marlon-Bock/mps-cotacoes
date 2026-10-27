<?php
/**
 * POST /api/admin/tarefas/status  { id, status_id }
 * Troca rápida do status de uma tarefa (menu inline, estilo Monday).
 * Respeita a visibilidade por setor; define/limpa concluida_em conforme is_final.
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');
csrf_verify();

$ctx   = tarefas_contexto_usuario((int) $current_user->uid);
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$id        = (int) ($input['id'] ?? 0);
$status_id = (int) ($input['status_id'] ?? 0);

if ($id <= 0) { echo json_encode(['ok' => false, 'message' => 'Tarefa inválida.']); exit; }

$tarefa = db_fetch_one('SELECT id, setor_id, status_id FROM tarefas WHERE id = ?', ['i', $id]);
if (!$tarefa) { echo json_encode(['ok' => false, 'message' => 'Tarefa não encontrada.']); exit; }

if (!$ctx['gestor'] && !in_array((int) $tarefa['setor_id'], $ctx['setores'], true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Sem permissão para esta tarefa.']);
    exit;
}

$final    = false;
$nome_st  = '';
if ($status_id > 0) {
    $st = db_fetch_one('SELECT is_final, nome FROM tarefas_status WHERE id = ?', ['i', $status_id]);
    if (!$st) { $status_id = 0; } else { $final = (int) $st['is_final'] === 1; $nome_st = $st['nome']; }
}

// Status de conclusão marca concluida_em (mantém a data se já existir); senão limpa.
$concluidaSql = $final ? 'COALESCE(concluida_em, NOW())' : 'NULL';

try {
    db_execute(
        "UPDATE tarefas SET status_id = ?, concluida_em = {$concluidaSql} WHERE id = ?",
        ['ii', $status_id ?: null, $id]
    );
    if ((int) ($tarefa['status_id'] ?? 0) !== $status_id) {
        tarefa_log($id, (int) $current_user->uid, 'status', $nome_st);
    }
    echo json_encode(['ok' => true]);
} catch (\Throwable $e) {
    error_log('[admin-tarefas-status] ' . $e->getMessage());
    echo json_encode(['ok' => false, 'message' => 'Erro ao alterar status.']);
}
