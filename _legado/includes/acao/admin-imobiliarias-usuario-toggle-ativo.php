<?php
/**
 * POST /api/admin/imobiliarias/usuario-toggle-ativo
 * Ativa ou inativa um usuário vinculado a uma imobiliária.
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');
csrf_verify();

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$usuarioId     = (int) ($input['usuario_id'] ?? 0);
$imobiliariaId = (int) ($input['imobiliaria_id'] ?? 0);

if ($usuarioId <= 0 || $imobiliariaId <= 0) {
    json_response(['ok' => false, 'message' => 'Usuário inválido.'], 422);
}

$usuario = db_fetch_one(
    'SELECT id, ativo FROM users WHERE id = ? AND imobiliaria_id = ?',
    ['ii', $usuarioId, $imobiliariaId]
);

if (!$usuario) {
    json_response(['ok' => false, 'message' => 'Usuário não encontrado.'], 404);
}

$novoAtivo = $usuario['ativo'] ? 0 : 1;

db_execute('UPDATE users SET ativo = ? WHERE id = ?', ['ii', $novoAtivo, $usuarioId]);

json_response(['ok' => true, 'ativo' => (bool) $novoAtivo]);
