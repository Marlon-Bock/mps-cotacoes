<?php
/**
 * POST /api/admin/tarefas/criar-rapido  { setor_id, status_id }
 * Cria uma tarefa em branco (só com setor/status do grupo) para o usuário
 * preencher no offcanvas — é o "+ Adicionar tarefa" da lista, estilo Monday.
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');
csrf_verify();

$ctx   = tarefas_contexto_usuario((int) $current_user->uid);
$input = json_decode(file_get_contents('php://input'), true) ?? [];

$setor_id     = (int) ($input['setor_id'] ?? 0);
$status_id    = (int) ($input['status_id'] ?? 0);
$depois_de_id = (int) ($input['depois_de_id'] ?? 0);

if ($setor_id <= 0) {
    echo json_encode(['ok' => false, 'message' => 'Selecione um setor para criar a tarefa.']);
    exit;
}

$setor = db_fetch_one('SELECT id FROM setores WHERE id = ?', ['i', $setor_id]);
if (!$setor) { echo json_encode(['ok' => false, 'message' => 'Setor inválido.']); exit; }

if (!$ctx['gestor'] && !in_array($setor_id, $ctx['setores'], true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Você não pode criar tarefas neste setor.']);
    exit;
}

// Sem status informado, usa o primeiro status ativo (equivale ao "Pendente").
if ($status_id > 0) {
    $st = db_fetch_one('SELECT id, is_final FROM tarefas_status WHERE id = ?', ['i', $status_id]);
    if (!$st) $status_id = 0;
}
if ($status_id <= 0) {
    $st = db_fetch_one('SELECT id FROM tarefas_status WHERE ativo = 1 ORDER BY ordem ASC, id ASC LIMIT 1');
    $status_id = (int) ($st['id'] ?? 0);
}

$final = false;
if ($status_id > 0) {
    $s = db_fetch_one('SELECT is_final FROM tarefas_status WHERE id = ?', ['i', $status_id]);
    $final = (int) ($s['is_final'] ?? 0) === 1;
}

try {
    $id = (int) db_execute(
        'INSERT INTO tarefas (titulo, descricao, setor_id, status_id, prioridade, prazo, criado_por, concluida_em)
         VALUES (?, NULL, ?, ?, NULL, NULL, ?, ' . ($final ? 'NOW()' : 'NULL') . ')',
        ['siii', '', $setor_id, $status_id ?: null, (int) $current_user->uid]
    );

    tarefa_log($id, (int) $current_user->uid, 'criou');

    // "Criar abaixo": insere logo após a tarefa de referência dentro do mesmo
    // grupo (setor, e status quando aplicável) e renumera o restante.
    if ($depois_de_id > 0) {
        $ref = db_fetch_one('SELECT id, setor_id, status_id FROM tarefas WHERE id = ?', ['i', $depois_de_id]);
        if ($ref && (int) $ref['setor_id'] === $setor_id) {
            $membros = db_fetch_all(
                'SELECT id FROM tarefas WHERE setor_id = ? AND status_id <=> ? AND arquivada_em IS NULL AND id != ? ORDER BY ordem ASC, id ASC',
                ['iii', $setor_id, $ref['status_id'], $id]
            );
            $ordenados = [];
            foreach ($membros as $m) {
                $ordenados[] = (int) $m['id'];
                if ((int) $m['id'] === $depois_de_id) $ordenados[] = $id;
            }
            if (!in_array($id, $ordenados, true)) $ordenados[] = $id;
            tarefas_renumerar_grupo($ordenados);
        }
    }

    echo json_encode(['ok' => true, 'id' => $id], JSON_UNESCAPED_UNICODE);
} catch (\Throwable $e) {
    error_log('[admin-tarefas-criar-rapido] ' . $e->getMessage());
    echo json_encode(['ok' => false, 'message' => 'Erro ao criar a tarefa.']);
}
