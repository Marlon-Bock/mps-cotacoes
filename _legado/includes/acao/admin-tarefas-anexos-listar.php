<?php
/**
 * GET /api/admin/tarefas/anexos?id=N
 * Lista os anexos de uma tarefa. Visibilidade por setor.
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');

$ctx = tarefas_contexto_usuario((int) $current_user->uid);
$id  = (int) ($_GET['id'] ?? 0);

if ($id <= 0) { echo json_encode(['ok' => false, 'anexos' => []]); exit; }

$tarefa = db_fetch_one('SELECT id, setor_id FROM tarefas WHERE id = ?', ['i', $id]);
if (!$tarefa || (!$ctx['gestor'] && !in_array((int) $tarefa['setor_id'], $ctx['setores'], true))) {
    echo json_encode(['ok' => false, 'anexos' => []]);
    exit;
}

$anexos = [];
try {
    $anexos = array_map(static fn($a) => [
        'id'      => (int) $a['id'],
        'url'     => $a['url'],
        'nome'    => $a['nome'],
        'tipo'    => $a['tipo'],
        'tamanho' => (int) $a['tamanho'],
    ], db_fetch_all('SELECT id, url, nome, tipo, tamanho FROM tarefas_anexos WHERE tarefa_id = ? ORDER BY criado_em DESC', ['i', $id]));
} catch (\Throwable $e) {
    error_log('[admin-tarefas-anexos-listar] ' . $e->getMessage());
}

echo json_encode(['ok' => true, 'anexos' => $anexos], JSON_UNESCAPED_UNICODE);
