<?php
/**
 * GET /api/cotacao/fianca/{protocolo}/seguradoras/fila
 * Status leve da fila da ficha — a tela de seguradoras consulta a cada
 * poucos segundos enquanto há cotação/emissão/reanálise rodando no worker
 * e recarrega quando termina.
 */
$user = require_auth();
header('Content-Type: application/json; charset=utf-8');

require_once BASE_PATH . '/src/helpers/api.php';
$ficha = api_ficha_autorizada($user, (string) ($params['protocolo'] ?? ''));

if (session_status() === PHP_SESSION_ACTIVE) session_write_close();

$ativo = api_fila_ativa((int) $ficha['id']);

json_response([
    'success' => true,
    // Inclui o PDF sendo gerado — a tela recarrega quando ele chega.
    'ativo'   => api_fila_aguardando((int) $ficha['id']),
    'tipo'    => $ativo['tipo'] ?? null,
    'status'  => $ativo['status'] ?? null,
]);
