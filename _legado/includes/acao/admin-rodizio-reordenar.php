<?php
/**
 * POST /api/admin/rodizio/reordenar
 * Aplica a nova ordem de atendentes de um funil depois de um arrastar-e-soltar.
 * Body JSON: { "funil_id": 1, "ordem": [42, 17, 9, ...] }  ← ids de
 * funil_corretores na ordem final desejada (topo → base).
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');
csrf_verify();

$input     = json_decode(file_get_contents('php://input'), true) ?? [];
$funil_id  = (int) ($input['funil_id'] ?? 0);
$ordem_ids = array_values(array_unique(array_map('intval', $input['ordem'] ?? [])));

if ($funil_id <= 0 || empty($ordem_ids)) {
    json_response(['success' => false, 'message' => 'Requisição inválida.'], 400);
}

$placeholders = implode(',', array_fill(0, count($ordem_ids), '?'));
$rows = db_fetch_all(
    "SELECT id FROM funil_corretores WHERE funil_id = ? AND id IN ({$placeholders})",
    [str_repeat('i', count($ordem_ids) + 1), $funil_id, ...$ordem_ids]
);
if (count($rows) !== count($ordem_ids)) {
    json_response(['success' => false, 'message' => 'Lista de reordenação inválida.'], 422);
}

$conn = db();
try {
    $conn->begin_transaction();
    foreach ($ordem_ids as $posicao => $id) {
        db_execute(
            'UPDATE funil_corretores SET ordem = ? WHERE id = ? AND funil_id = ?',
            ['iii', $posicao + 1, $id, $funil_id]
        );
    }
    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    error_log('[rodizio-reordenar] ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'Erro ao reordenar.'], 500);
}

json_response(['success' => true, 'message' => 'Ordem atualizada.']);
