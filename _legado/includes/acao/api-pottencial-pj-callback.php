<?php
/**
 * POST /api/webhook/pottencial-pj/{token}
 * Callback da Pottencial Fiança PJ (cotação, reanálise e emissão são
 * assíncronas lá). Não confia no corpo: só usa o RequestId pra reconsultar
 * a cotação na própria Pottencial e gravar o resultado — o mesmo que o
 * cron/api-acompanhamento.php faria na rodada seguinte, só que na hora.
 * Token na URL (pottencial_pj_callback_token) — sem login/CSRF.
 */
header('Content-Type: application/json; charset=utf-8');
require_once BASE_PATH . '/src/helpers/api.php';

if (!hash_equals(pottencial_pj_callback_token(), (string) ($params['token'] ?? ''))) {
    json_response(['ok' => false], 403);
}

$corpo     = json_decode((string) file_get_contents('php://input'), true) ?: [];
$requestId = (string) ($corpo['RequestId'] ?? $corpo['requestId'] ?? '');
if (!preg_match('/^[0-9a-f-]{36}$/i', $requestId)) {
    json_response(['ok' => false, 'message' => 'RequestId inválido.'], 422);
}

$cotacao = db_fetch_one(
    "SELECT seguro_fianca_id, apolice_id FROM api_cotacoes WHERE seguradora = 'pottencial_pj' AND cotacao_externa_id = ?",
    ['s', $requestId]
);
if (!$cotacao) {
    json_response(['ok' => true, 'ignorado' => true]);
}

// Responde já e reconsulta depois (a Pottencial não precisa esperar).
ignore_user_abort(true);
echo json_encode(['ok' => true]);
if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
}

$fiancaId = (int) $cotacao['seguro_fianca_id'];
if (empty($cotacao['apolice_id'])) {
    $r = pottencial_pj_consultar($requestId);
    if ($r['ok']) {
        api_aplicar_status_cotacao($fiancaId, 'pottencial_pj', $r);
    }
} else {
    api_atualizar_apolice($fiancaId, 'pottencial_pj', (string) $cotacao['apolice_id']);
}
