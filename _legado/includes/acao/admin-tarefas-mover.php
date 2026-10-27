<?php
/**
 * POST /api/admin/tarefas/mover
 * { id, agrupar_por: 'status'|'setor', grupo_id, ordenados: [ids em ordem no grupo destino] }
 *
 * Drag-and-drop da lista estilo Monday: reordena dentro do grupo e, quando o
 * grupo mudou, troca setor (visão "Todas") ou status (aba de setor) da tarefa
 * arrastada. Mesma checagem de visibilidade de admin-tarefas-salvar.php.
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');
csrf_verify();

$ctx   = tarefas_contexto_usuario((int) $current_user->uid);
$input = json_decode(file_get_contents('php://input'), true) ?? [];

$id          = (int) ($input['id'] ?? 0);
$agrupar_por = trim($input['agrupar_por'] ?? '');
$grupo_id    = (int) ($input['grupo_id'] ?? 0);
$ordenados   = array_values(array_filter(array_map('intval', (array) ($input['ordenados'] ?? []))));

if ($id <= 0) { echo json_encode(['ok' => false, 'message' => 'Tarefa inválida.']); exit; }
if (!in_array($agrupar_por, ['status', 'setor'], true)) { echo json_encode(['ok' => false, 'message' => 'Agrupamento inválido.']); exit; }

$tarefa = db_fetch_one('SELECT id, setor_id, status_id FROM tarefas WHERE id = ?', ['i', $id]);
if (!$tarefa) { echo json_encode(['ok' => false, 'message' => 'Tarefa não encontrada.']); exit; }

if (!$ctx['gestor'] && !in_array((int) $tarefa['setor_id'], $ctx['setores'], true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Sem permissão para esta tarefa.']);
    exit;
}

try {
    $uid_log = (int) $current_user->uid;

    if ($agrupar_por === 'setor') {
        // Visão "Todas": grupo = setor. Trocar de grupo troca a equipe da tarefa.
        if ($grupo_id > 0 && !$ctx['gestor'] && !in_array($grupo_id, $ctx['setores'], true)) {
            http_response_code(403);
            echo json_encode(['ok' => false, 'message' => 'Sem permissão para o setor de destino.']);
            exit;
        }
        if ($grupo_id > 0 && (int) $tarefa['setor_id'] !== $grupo_id) {
            $setor = db_fetch_one('SELECT id, nome FROM setores WHERE id = ?', ['i', $grupo_id]);
            if (!$setor) { echo json_encode(['ok' => false, 'message' => 'Setor inválido.']); exit; }
            db_execute('UPDATE tarefas SET setor_id = ? WHERE id = ?', ['ii', $grupo_id, $id]);
            tarefa_log($id, $uid_log, 'setor', $setor['nome']);
        }
    } else {
        // Aba de setor: grupo = status. Trocar de grupo troca o status (kanban).
        $status_id = $grupo_id > 0 ? $grupo_id : null;
        if ((int) ($tarefa['status_id'] ?? 0) !== ($status_id ?? 0)) {
            $nome_st = '';
            $final   = false;
            if ($status_id) {
                $st = db_fetch_one('SELECT nome, is_final FROM tarefas_status WHERE id = ?', ['i', $status_id]);
                if (!$st) { echo json_encode(['ok' => false, 'message' => 'Status inválido.']); exit; }
                $nome_st = $st['nome'];
                $final   = (int) $st['is_final'] === 1;
            }
            $concluidaSql = $final ? 'COALESCE(concluida_em, NOW())' : 'NULL';
            db_execute("UPDATE tarefas SET status_id = ?, concluida_em = {$concluidaSql} WHERE id = ?", ['ii', $status_id, $id]);
            tarefa_log($id, $uid_log, 'status', $nome_st);
        }
    }

    if (!empty($ordenados)) {
        tarefas_renumerar_grupo($ordenados);
    }

    echo json_encode(['ok' => true]);
} catch (\Throwable $e) {
    error_log('[admin-tarefas-mover] ' . $e->getMessage());
    echo json_encode(['ok' => false, 'message' => 'Erro ao mover a tarefa.']);
}
