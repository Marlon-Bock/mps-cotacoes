<?php
/**
 * POST /api/cotacao/fianca/{protocolo}/seguradoras/proposta/enviar
 * "Enviar proposta" (MPS): a seguradora gerou a proposta (PDF) da que o
 * cliente aceitou — libera pra imobiliária enquanto a apólice é emitida.
 */
$user = require_admin();
// O PDF da proposta pode ainda não estar em cache (a seguradora leva ~1 min).
set_time_limit(120);
header('Content-Type: application/json; charset=utf-8');
csrf_verify();

require_once BASE_PATH . '/src/helpers/api.php';
$ficha = api_ficha_autorizada($user, (string) ($params['protocolo'] ?? ''));

if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

$r = api_enviar_proposta((int) $ficha['id'], $user);
if (!$r['ok']) {
    json_response(['success' => false, 'message' => $r['erro']], 422);
}

json_response(['success' => true]);
