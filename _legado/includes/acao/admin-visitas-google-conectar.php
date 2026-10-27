<?php
/**
 * GET /admin/visitas/google/conectar
 * Redireciona pro consentimento OAuth do Google (sempre conecta a conta do
 * próprio usuário logado — não dá pra consentir em nome de outra pessoa).
 */
$current_user = require_admin();

redirect(google_auth_url((int) $current_user->uid));
