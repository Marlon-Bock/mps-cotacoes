<?php
/**
 * POST /api/admin/rodizio/status
 * Ativa/desativa um atendente dentro de um funil (fica fora do rodízio
 * enquanto inativo, sem precisar removê-lo da lista).
 * Body JSON: { "id": 42, "ativo": true }
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');
csrf_verify();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$id    = (int) ($input['id'] ?? 0);
$ativo = !empty($input['ativo']) ? 'S' : 'N';

if ($id <= 0) {
    json_response(['success' => false, 'message' => 'Requisição inválida.'], 400);
}

$linha = db_fetch_one('SELECT id FROM funil_corretores WHERE id = ?', ['i', $id]);
if (!$linha) {
    json_response(['success' => false, 'message' => 'Atendente não encontrado neste funil.'], 404);
}

try {
    db_execute('UPDATE funil_corretores SET ativo = ? WHERE id = ?', ['si', $ativo, $id]);
} catch (Throwable $e) {
    error_log('[rodizio-status] ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'Erro ao atualizar status.'], 500);
}

json_response(['success' => true, 'message' => 'Status atualizado.']);
