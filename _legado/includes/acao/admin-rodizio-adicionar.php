<?php
/**
 * POST /api/admin/rodizio/adicionar
 * Adiciona um atendente ao final da fila de um funil de rodízio.
 * Body JSON: { "funil_id": 1, "user_id": 9 }
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');
csrf_verify();

$input    = json_decode(file_get_contents('php://input'), true) ?? [];
$funil_id = (int) ($input['funil_id'] ?? 0);
$user_id  = (int) ($input['user_id']  ?? 0);

if ($funil_id <= 0 || $user_id <= 0) {
    json_response(['success' => false, 'message' => 'Selecione um atendente.'], 400);
}

$funil = db_fetch_one('SELECT id FROM funis WHERE id = ?', ['i', $funil_id]);
if (!$funil) {
    json_response(['success' => false, 'message' => 'Funil não encontrado.'], 404);
}

$usuario = db_fetch_one('SELECT id FROM usuario WHERE id = ?', ['i', $user_id]);
if (!$usuario) {
    json_response(['success' => false, 'message' => 'Atendente não encontrado.'], 404);
}

$ja_existe = db_fetch_one(
    'SELECT id FROM funil_corretores WHERE funil_id = ? AND user_id = ?',
    ['ii', $funil_id, $user_id]
);
if ($ja_existe) {
    json_response(['success' => false, 'message' => 'Este atendente já está neste funil.'], 422);
}

try {
    $max = db_fetch_one('SELECT MAX(ordem) AS max_ordem FROM funil_corretores WHERE funil_id = ?', ['i', $funil_id]);
    $proxima_ordem = ((int) ($max['max_ordem'] ?? 0)) + 1;

    db_execute(
        "INSERT INTO funil_corretores (funil_id, user_id, ordem, ativo) VALUES (?, ?, ?, 'S')",
        ['iii', $funil_id, $user_id, $proxima_ordem]
    );
} catch (Throwable $e) {
    error_log('[rodizio-adicionar] ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'Erro ao adicionar atendente.'], 500);
}

json_response(['success' => true, 'message' => 'Atendente adicionado ao funil.']);
