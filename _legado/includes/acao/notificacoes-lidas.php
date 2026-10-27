<?php
/**
 * POST /api/notificacoes/lidas
 * Marca uma ou todas as notificações do usuário logado como lidas
 * (admin ou imobiliária — o enum de destinatario_tipo é 'admin'|'imob').
 *
 * Body JSON:
 *   { "id": 42 }          → marca apenas a notificação com id=42
 *   { "todas": true }     → marca todas as não lidas do usuário
 */
header('Content-Type: application/json; charset=utf-8');
csrf_verify();

$current_user      = require_auth();
$destinatario_tipo = ($current_user->role ?? '') === 'admin' ? 'admin' : 'imob';
$destinatario_id   = $destinatario_tipo === 'admin'
    ? (int) ($current_user->uid ?? 0)
    : (int) ($current_user->uid ?? 0);

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$marcar_todas = !empty($input['todas']);
$notif_id     = isset($input['id']) ? (int) $input['id'] : 0;

if (!$marcar_todas && $notif_id <= 0) {
    json_response(['message' => 'Requisição inválida.'], 400);
}

try {
    if ($marcar_todas) {
        db_execute(
            "UPDATE notificacoes_mps
             SET lida = 1
             WHERE destinatario_id = ? AND destinatario_tipo = ? AND lida = 0",
            ['is', $destinatario_id, $destinatario_tipo]
        );
        json_response(['message' => 'Todas as notificações foram marcadas como lidas.']);
    } else {
        // Verifica que a notificação pertence a este usuário antes de atualizar
        $notif = db_fetch_one(
            "SELECT id FROM notificacoes_mps
             WHERE id = ? AND destinatario_id = ? AND destinatario_tipo = ?",
            ['iis', $notif_id, $destinatario_id, $destinatario_tipo]
        );

        if (!$notif) {
            json_response(['message' => 'Notificação não encontrada.'], 404);
        }

        db_execute(
            "UPDATE notificacoes_mps SET lida = 1 WHERE id = ?",
            ['i', $notif_id]
        );

        json_response(['message' => 'Notificação marcada como lida.']);
    }
} catch (Throwable $e) {
    error_log('[notificacoes-lidas] ' . $e->getMessage());
    json_response(['message' => 'Erro ao atualizar notificação.'], 500);
}
