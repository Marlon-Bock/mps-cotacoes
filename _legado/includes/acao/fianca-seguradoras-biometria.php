<?php
/**
 * GET /api/cotacao/fianca/{protocolo}/seguradoras/{seguradora}/biometria
 * Link de biometria facial da cotação, pra enviar ao pretendente. A
 * imobiliária só pega o link de seguradora que está na proposta enviada.
 */
$user = require_auth();
header('Content-Type: application/json; charset=utf-8');

require_once BASE_PATH . '/src/helpers/api.php';
$seguradora = api_seguradora_ou_404((string) ($params['seguradora'] ?? ''));
$ficha      = api_ficha_autorizada($user, (string) ($params['protocolo'] ?? ''));

if (($user->role ?? '') === 'imobiliaria' && !api_proposta_pendente((int) $ficha['id'], $seguradora)) {
    json_response(['success' => false, 'message' => 'Link de biometria não disponível.'], 403);
}

if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

$r = api_biometria((int) $ficha['id'], $seguradora);
if (!$r['ok']) {
    error_log('[fianca-seguradoras-biometria] ' . ($r['erro'] ?? 'erro desconhecido'));
    json_response(['success' => false, 'message' => 'Não foi possível obter o link de biometria. Tente novamente.'], 424);
}

json_response(['success' => true, 'url' => $r['url']]);
