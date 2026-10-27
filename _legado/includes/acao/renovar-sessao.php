<?php
/**
 * POST /api/renovar-sessao
 * Renova o JWT por mais 3 horas sem reconsultar o banco.
 * Retorna o novo timestamp de expiração para o watchdog JS atualizar seu timer.
 */
$user = auth_user();

if (!$user) {
    http_response_code(401);
    json_response(['success' => false, 'message' => 'Sessão inválida ou expirada.']);
}

$payload = (array) $user;
unset($payload['iat'], $payload['exp']);

auth_emit($payload);

// Calcula novo exp para o watchdog
$new_exp = time() + JWT_TTL;

json_response(['success' => true, 'exp' => $new_exp]);
