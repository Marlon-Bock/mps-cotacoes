<?php
/**
 * POST /api/cotacao/fianca/{protocolo}/seguradoras/propostas/recusar
 * O cliente (imobiliária) recusa todas as propostas enviadas pela MPS — a
 * cotação volta pra MPS, que pode enviar outras propostas ou encerrar.
 * Body JSON: { motivo? }
 */
$user = require_perm('perm_aceitar');
header('Content-Type: application/json; charset=utf-8');
csrf_verify();

require_once BASE_PATH . '/src/helpers/api.php';
$ficha = api_ficha_autorizada($user, (string) ($params['protocolo'] ?? ''));

$body = json_decode(file_get_contents('php://input'), true) ?: [];
$r    = api_recusar_propostas((int) $ficha['id'], $user, sanitize((string) ($body['motivo'] ?? '')));
if (!$r['ok']) {
    json_response(['success' => false, 'message' => $r['erro']], 422);
}

json_response(['success' => true]);
