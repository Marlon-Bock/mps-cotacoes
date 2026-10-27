<?php
/**
 * GET /api/cotacao/fianca/{protocolo}/seguradoras/situacao
 * Consulta leve (só banco) pro polling do detalhe da imobiliária: se ainda
 * espera algo (pré-análise, emissão, apólice) e a assinatura do que ELA vê
 * — mudança numa seguradora que não muda a tela dela não recarrega.
 */
$user = require_auth();
header('Content-Type: application/json; charset=utf-8');

require_once BASE_PATH . '/src/helpers/api.php';
$ficha = api_ficha_autorizada($user, (string) ($params['protocolo'] ?? ''));

if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

$lote = api_status_lote([(int) $ficha['id']])[(int) $ficha['id']];

json_response([
    'success'    => true,
    'aguardando' => api_imobiliaria_aguardando($ficha, api_etapa($lote['cotacoes'], $lote['fila'], $lote['propostas'])),
    'assinatura' => api_assinatura_imobiliaria($ficha, $lote),
]);
