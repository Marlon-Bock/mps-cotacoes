<?php
/**
 * POST /api/cotacao/fianca/{protocolo}/seguradoras/atualizar
 * "Atualizar": reconsulta as cotações (e apólices) da ficha nas seguradoras.
 * Ficha que nunca chegou a ter cotação criada (falha na 1ª tentativa) é
 * cotada de novo — ver api_atualizar_ficha(). Só a MPS (a imobiliária
 * acompanha a pré-análise, que o cron atualiza).
 */
$user = require_admin();
header('Content-Type: application/json; charset=utf-8');
csrf_verify();

// Reconsulta na seguradora pode levar quase 1 min em homologação.
set_time_limit(130);

require_once BASE_PATH . '/src/helpers/api.php';
$ficha = api_ficha_autorizada($user, (string) ($params['protocolo'] ?? ''));

// Não segura a sessão (as outras abas) enquanto espera a seguradora.
if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

$r = api_atualizar_ficha((int) $ficha['id']);
api_apos_resultado((int) $ficha['id']);
if (!$r['ok']) {
    error_log('[fianca-seguradoras-atualizar] ' . ($r['erro'] ?? 'erro desconhecido'));
    json_response(['success' => false, 'message' => 'Erro ao consultar as seguradoras. Tente novamente.'], 424);
}

json_response(['success' => true]);
