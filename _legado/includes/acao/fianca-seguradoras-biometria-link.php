<?php
/**
 * GET /api/cotacao/fianca/{protocolo}/seguradoras/biometria-link?n=0
 * Link de biometria facial pro pretendente, na etapa "Biometria pendente"
 * (antes das propostas). A imobiliária não vê a seguradora: n é a posição
 * em api_biometrias() ("Biometria 1, 2..." — pode ter mais de uma).
 */
$user = require_auth();
header('Content-Type: application/json; charset=utf-8');

require_once BASE_PATH . '/src/helpers/api.php';
$ficha = api_ficha_autorizada($user, (string) ($params['protocolo'] ?? ''));

$cotacao = api_biometrias(api_cotacoes_da_ficha((int) $ficha['id']))[max(0, (int) ($_GET['n'] ?? 0))] ?? null;
if ($cotacao === null || !api_cotacao_biometria_pendente($cotacao) || api_ficha_encerrada($ficha)) {
    json_response(['success' => false, 'message' => 'Essa cotação não está aguardando biometria.'], 409);
}

if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

$r = api_biometria((int) $ficha['id'], (string) $cotacao['seguradora']);
if (!$r['ok']) {
    error_log('[fianca-seguradoras-biometria-link] ' . ($r['erro'] ?? 'erro desconhecido'));
    json_response(['success' => false, 'message' => 'Não foi possível obter o link de biometria agora. Tente de novo em instantes.'], 424);
}

json_response(['success' => true, 'url' => $r['url']]);
