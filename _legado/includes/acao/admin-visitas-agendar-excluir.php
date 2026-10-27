<?php
/**
 * AJAX endpoint — POST /api/admin/visitas-agendar/excluir
 * Exclui um agendamento. Não-admins só podem excluir os próprios
 * (para_usuario_id). Tenta apagar o evento do Google Agenda do principal e
 * de cada corretor adicional (best-effort — não impede a exclusão local se
 * o Google falhar). As linhas da tabela de extras somem via ON DELETE
 * CASCADE.
 * Body JSON: { "id": 42 }
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');
csrf_verify();

$is_mps_admin = !empty($current_user->mps_admin);
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$id    = (int) ($input['id'] ?? 0);

if ($id <= 0) {
    json_response(['success' => false, 'message' => 'Requisição inválida.'], 400);
}

$ag = db_fetch_one('SELECT * FROM agendamentos_visita WHERE id = ?', ['i', $id]);
if (!$ag) {
    json_response(['success' => false, 'message' => 'Agendamento não encontrado.'], 404);
}
if (!$is_mps_admin && (int) $ag['para_usuario_id'] !== (int) $current_user->uid) {
    json_response(['success' => false, 'message' => 'Você não tem permissão para excluir este agendamento.'], 403);
}

if (!empty($ag['google_event_id'])) {
    $token = google_get_valid_token((int) $ag['para_usuario_id']);
    if ($token) {
        google_calendar_delete_event($token, $ag['google_event_id']);
    }
}

$extras = db_fetch_all('SELECT usuario_id, google_event_id FROM agendamentos_visita_corretores WHERE agendamento_id = ?', ['i', $id]);
foreach ($extras as $extra) {
    if (empty($extra['google_event_id'])) continue;
    $token_extra = google_get_valid_token((int) $extra['usuario_id']);
    if ($token_extra) {
        google_calendar_delete_event($token_extra, $extra['google_event_id']);
    }
}

try {
    db_execute('DELETE FROM agendamentos_visita WHERE id = ?', ['i', $id]);
} catch (Throwable $e) {
    error_log('[visitas-agendar-excluir] ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'Erro ao excluir o agendamento.'], 500);
}

json_response(['success' => true, 'message' => 'Agendamento excluído.']);
