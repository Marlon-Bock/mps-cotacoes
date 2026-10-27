<?php
/**
 * POST /api/admin/faq/item-excluir
 * Exclui uma pergunta/resposta de FAQ.
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');
csrf_verify();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$id    = (int) ($input['id'] ?? 0);

if ($id <= 0) {
    json_response(['ok' => false, 'message' => 'Pergunta inválida.'], 422);
}

$existente = db_fetch_one('SELECT id FROM faq_itens WHERE id = ?', ['i', $id]);
if (!$existente) {
    json_response(['ok' => false, 'message' => 'Pergunta não encontrada.'], 404);
}

db_execute('DELETE FROM faq_itens WHERE id = ?', ['i', $id]);

json_response(['ok' => true]);
