<?php
/**
 * POST /api/novidade-incendio/visto
 * Marca que o usuário (imobiliária) já viu o popup "Novidade! Agora você
 * pode cotar Seguro Incêndio" — grava a data e nunca mais mostra o popup
 * pra esse usuário (ver pages/index.php e imob_pendencias-like check).
 */
$current_user = require_auth();
csrf_verify();
header('Content-Type: application/json; charset=utf-8');

if (($current_user->role ?? '') !== 'imobiliaria') {
    json_response(['success' => false, 'message' => 'Acesso não permitido.'], 403);
}

db_execute(
    'UPDATE users SET novidade_incendio_visto_em = NOW() WHERE id = ?',
    ['i', (int) $current_user->uid]
);

json_response(['success' => true]);
