<?php
/**
 * POST /api/admin/tarefas/subitens/salvar
 * { tarefa_id, id (0 = novo), titulo, concluido ('S'|'N') }
 * Cria ou atualiza um subitem do checklist da tarefa.
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');
csrf_verify();

$ctx   = tarefas_contexto_usuario((int) $current_user->uid);
$input = json_decode(file_get_contents('php://input'), true) ?? [];

$tarefa_id = (int) ($input['tarefa_id'] ?? 0);
$id        = (int) ($input['id'] ?? 0);
$titulo    = trim($input['titulo'] ?? '');
$concluido = ($input['concluido'] ?? 'N') === 'S' ? 'S' : 'N';

if ($tarefa_id <= 0) { echo json_encode(['ok' => false, 'message' => 'Tarefa inválida.']); exit; }

$tarefa = db_fetch_one('SELECT id, setor_id FROM tarefas WHERE id = ?', ['i', $tarefa_id]);
if (!$tarefa) { echo json_encode(['ok' => false, 'message' => 'Tarefa não encontrada.']); exit; }

if (!$ctx['gestor'] && !in_array((int) $tarefa['setor_id'], $ctx['setores'], true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Sem permissão para esta tarefa.']);
    exit;
}

try {
    if ($id > 0) {
        $atual = db_fetch_one('SELECT id FROM tarefas_subitens WHERE id = ? AND tarefa_id = ?', ['ii', $id, $tarefa_id]);
        if (!$atual) { echo json_encode(['ok' => false, 'message' => 'Subitem não encontrado.']); exit; }

        if ($titulo === '') {
            // Sem título novo: trata como toggle de concluído apenas.
            db_execute('UPDATE tarefas_subitens SET concluido = ? WHERE id = ?', ['si', $concluido, $id]);
        } else {
            db_execute('UPDATE tarefas_subitens SET titulo = ?, concluido = ? WHERE id = ?', ['ssi', $titulo, $concluido, $id]);
        }
    } else {
        if ($titulo === '') { echo json_encode(['ok' => false, 'message' => 'Informe o título do subitem.']); exit; }
        $prox = db_fetch_one('SELECT COALESCE(MAX(ordem), 0) + 1 AS ordem FROM tarefas_subitens WHERE tarefa_id = ?', ['i', $tarefa_id]);
        $id = (int) db_execute(
            'INSERT INTO tarefas_subitens (tarefa_id, titulo, concluido, ordem, criado_por) VALUES (?, ?, ?, ?, ?)',
            ['issii', $tarefa_id, $titulo, $concluido, (int) ($prox['ordem'] ?? 1), (int) $current_user->uid]
        );
    }

    $total = db_fetch_one(
        "SELECT COUNT(*) AS total, SUM(concluido = 'S') AS feitos FROM tarefas_subitens WHERE tarefa_id = ?",
        ['i', $tarefa_id]
    );
    echo json_encode([
        'ok'     => true,
        'id'     => $id,
        'total'  => (int) ($total['total'] ?? 0),
        'feitos' => (int) ($total['feitos'] ?? 0),
    ]);
} catch (\Throwable $e) {
    error_log('[admin-tarefas-subitens-salvar] ' . $e->getMessage());
    echo json_encode(['ok' => false, 'message' => 'Erro ao salvar o subitem.']);
}
