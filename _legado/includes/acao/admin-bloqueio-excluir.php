<?php
/**
 * POST /api/admin/bloqueio-horario/excluir
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');
csrf_verify();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$id = (int) ($input['id'] ?? 0);
if ($id <= 0) {
    json_response(['ok' => false, 'message' => 'Bloqueio inválido.'], 422);
}

db_execute('DELETE FROM bloqueios_horario WHERE id = ?', ['i', $id]);

json_response(['ok' => true]);
