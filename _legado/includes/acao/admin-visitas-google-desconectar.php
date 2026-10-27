<?php
/**
 * POST /api/admin/visitas/google-desconectar
 * Desconecta a conta Google do usuário logado (revoga o token e apaga o
 * registro local).
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');
csrf_verify();

google_disconnect((int) $current_user->uid);

json_response(['success' => true, 'message' => 'Conta Google desconectada.']);
