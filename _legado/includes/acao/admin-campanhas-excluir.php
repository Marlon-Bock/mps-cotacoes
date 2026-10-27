<?php
/**
 * POST /api/admin/campanhas/excluir
 * Exclui uma campanha e seus vínculos de segmentação.
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');
csrf_verify();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$id    = (int) ($input['id'] ?? 0);

if ($id <= 0) {
    json_response(['ok' => false, 'message' => 'Campanha inválida.'], 422);
}

$existente = db_fetch_one('SELECT id FROM campanhas WHERE id = ?', ['i', $id]);
if (!$existente) {
    json_response(['ok' => false, 'message' => 'Campanha não encontrada.'], 404);
}

db_execute('DELETE FROM campanha_imobiliarias WHERE campanha_id = ?', ['i', $id]);
db_execute('DELETE FROM campanhas WHERE id = ?', ['i', $id]);

json_response(['ok' => true]);
