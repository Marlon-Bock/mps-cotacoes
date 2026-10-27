<?php
/**
 * GET /admin/visitas/google/callback?code=...&state=...
 * Callback do OAuth do Google — troca o code por token e salva.
 * O "state" já garante que o code pertence a quem clicou em Conectar
 * (assinado com o mesmo segredo do JWT de sessão, ver google_auth_url()).
 */
$current_user = require_admin();

$code  = (string) ($_GET['code']  ?? '');
$state = (string) ($_GET['state'] ?? '');
$erro  = (string) ($_GET['error'] ?? '');

if ($erro !== '') {
    flash('error', 'Conexão com o Google cancelada ou recusada.');
    redirect('/admin/visitas/agendar');
}

$userIdDoState = google_validar_state($state);
if (!$userIdDoState || $userIdDoState !== (int) $current_user->uid) {
    error_log(sprintf(
        '[google-callback] state inválido — uid_logado=%s uid_do_state=%s state_vazio=%s',
        (string) $current_user->uid,
        $userIdDoState === null ? 'null (expirado/assinatura inválida/malformado)' : (string) $userIdDoState,
        $state === '' ? 'sim' : 'não'
    ));
    flash('error', 'Não foi possível validar a solicitação de conexão. Tente novamente.');
    redirect('/admin/visitas/agendar');
}

if ($code === '') {
    flash('error', 'O Google não retornou um código de autorização.');
    redirect('/admin/visitas/agendar');
}

$tokenData = google_exchange_code($code);
if (!$tokenData) {
    flash('error', 'Não foi possível concluir a conexão com o Google. Tente novamente.');
    redirect('/admin/visitas/agendar');
}

google_save_token((int) $current_user->uid, $tokenData);

flash('success', 'Conta Google conectada com sucesso! Seus agendamentos agora sincronizam automaticamente.');
redirect('/admin/visitas/agendar');
