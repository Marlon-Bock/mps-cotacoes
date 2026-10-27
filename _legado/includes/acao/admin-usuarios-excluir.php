<?php
/**
 * POST /api/admin/usuarios/excluir
 * Exclui um usuário interno da MPS (tabela `usuario`).
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');

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

$usuario = db_fetch_one('SELECT id FROM usuario WHERE id = ?', ['i', $id]);

if (!$usuario) {
    echo json_encode(['ok' => false, 'message' => 'Usuário não encontrado.']);
    exit;
}

db_execute('DELETE FROM usuario WHERE id = ?', ['i', $id]);

echo json_encode(['ok' => true]);
