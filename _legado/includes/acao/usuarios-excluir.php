<?php
/**
 * POST /api/usuarios/excluir
 * Exclui um usuário da imobiliária logada.
 */
$current_user = require_auth();
header('Content-Type: application/json; charset=utf-8');

if (!($current_user->imob_admin ?? false)) {
    echo json_encode(['ok' => false, 'message' => 'Sem permissão.']);
    exit;
}

csrf_verify();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$id    = (int) ($input['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['ok' => false, 'message' => 'Usuário inválido.']);
    exit;
}

if ($id === (int) $current_user->uid) {
    echo json_encode(['ok' => false, 'message' => 'Você não pode excluir sua própria conta.']);
    exit;
}

$usuario = db_fetch_one(
    'SELECT id FROM users WHERE id = ? AND imobiliaria_id = ?',
    ['ii', $id, $current_user->imobiliaria_id]
);

if (!$usuario) {
    echo json_encode(['ok' => false, 'message' => 'Usuário não encontrado.']);
    exit;
}

db_execute('DELETE FROM users WHERE id = ? AND imobiliaria_id = ?', ['ii', $id, $current_user->imobiliaria_id]);

echo json_encode(['ok' => true]);
