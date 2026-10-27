<?php
/**
 * GET /api/admin/tarefas/obter?id=N
 * Devolve os dados completos de uma tarefa (para preencher o modal de edição).
 * Respeita a visibilidade por setor (não-gestor só acessa tarefas dos seus setores).
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');

$ctx = tarefas_contexto_usuario((int) $current_user->uid);
$id  = (int) ($_GET['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['ok' => false, 'message' => 'Tarefa inválida.']);
    exit;
}

$tarefa = db_fetch_one(
    'SELECT id, titulo, descricao, setor_id, status_id, prioridade, prazo FROM tarefas WHERE id = ?',
    ['i', $id]
);

if (!$tarefa) {
    echo json_encode(['ok' => false, 'message' => 'Tarefa não encontrada.']);
    exit;
}

// Visibilidade: não-gestor só vê tarefas dos seus setores.
if (!$ctx['gestor'] && !in_array((int) $tarefa['setor_id'], $ctx['setores'], true)) {
    echo json_encode(['ok' => false, 'message' => 'Sem permissão para esta tarefa.']);
    exit;
}

$resp = db_fetch_all('SELECT usuario_id FROM tarefas_responsaveis WHERE tarefa_id = ?', ['i', $id]);
$etq  = db_fetch_all('SELECT etiqueta_id FROM tarefas_etiquetas_vinculo WHERE tarefa_id = ?', ['i', $id]);
$val  = db_fetch_all('SELECT campo_id, valor FROM tarefas_campos_valores WHERE tarefa_id = ?', ['i', $id]);

$campos = [];
foreach ($val as $v) { $campos[(string) (int) $v['campo_id']] = $v['valor']; }

echo json_encode([
    'ok' => true,
    'tarefa' => [
        'id'           => (int) $tarefa['id'],
        'titulo'       => $tarefa['titulo'],
        'descricao'    => $tarefa['descricao'] ?? '',
        'setor_id'     => $tarefa['setor_id'] !== null ? (int) $tarefa['setor_id'] : '',
        'status_id'    => $tarefa['status_id'] !== null ? (int) $tarefa['status_id'] : '',
        'prioridade'   => $tarefa['prioridade'] ?? '',
        'prazo'        => $tarefa['prazo'] ?? '',
        'responsaveis' => array_map(static fn($r) => (int) $r['usuario_id'], $resp),
        'etiquetas'    => array_map(static fn($e) => (int) $e['etiqueta_id'], $etq),
        'campos'       => $campos,
    ],
], JSON_UNESCAPED_UNICODE);
