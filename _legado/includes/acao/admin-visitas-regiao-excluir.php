<?php
/**
 * AJAX endpoint — POST /api/admin/visitas/regiao-excluir
 * Remove um tipo de região do cadastro. Recusa se houver agendamentos
 * usando essa região, pra não deixar registros antigos órfãos.
 * Body: { id }
 * Retorna JSON { success, message }
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');
csrf_verify();

$body = json_decode(file_get_contents('php://input'), true) ?? [];
$id   = (int) ($body['id'] ?? 0);

if ($id <= 0) {
    json_response(['success' => false, 'message' => 'Região inválida.'], 422);
}

$em_uso = (int) (db_fetch_one('SELECT COUNT(*) AS total FROM agendamentos_visita WHERE regiao_id = ?', ['i', $id])['total'] ?? 0);
if ($em_uso > 0) {
    json_response(['success' => false, 'message' => "Essa região está em uso em {$em_uso} agendamento(s) e não pode ser excluída."], 422);
}

try {
    db_execute('DELETE FROM regioes WHERE id = ?', ['i', $id]);
} catch (Throwable $e) {
    error_log('[visitas-regiao-excluir] ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'Não foi possível excluir a região.'], 500);
}

json_response(['success' => true, 'message' => 'Região excluída.']);
