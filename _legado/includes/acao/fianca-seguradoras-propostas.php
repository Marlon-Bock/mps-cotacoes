<?php
/**
 * POST /api/cotacao/fianca/{protocolo}/seguradoras/propostas
 * "Fazer cotação" (MPS): envia ao cliente as propostas das seguradoras
 * escolhidas (aprovadas ou aguardando biometria) — o cliente aceita a
 * melhor no detalhe da cotação. Reenviar substitui o envio anterior.
 * Body JSON: { seguradoras: [slug, ...], observacao? }
 */
$user = require_admin();
header('Content-Type: application/json; charset=utf-8');
csrf_verify();

require_once BASE_PATH . '/src/helpers/api.php';
$ficha = api_ficha_autorizada($user, (string) ($params['protocolo'] ?? ''));

if ($ficha['status'] !== API_STATUS_FICHA) {
    json_response(['success' => false, 'message' => 'Essa cotação não está mais em andamento.'], 409);
}

$body        = json_decode(file_get_contents('php://input'), true) ?: [];
$seguradoras = is_array($body['seguradoras'] ?? null) ? $body['seguradoras'] : [];
if ($seguradoras === []) {
    json_response(['success' => false, 'message' => 'Escolha ao menos uma seguradora para enviar ao cliente.'], 422);
}

$r = api_enviar_propostas((int) $ficha['id'], $seguradoras, $user, sanitize((string) ($body['observacao'] ?? '')));
if (!$r['ok']) {
    json_response(['success' => false, 'message' => $r['erro']], 422);
}

json_response(['success' => true, 'enviadas' => $r['enviadas']]);
