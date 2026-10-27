<?php
/**
 * Tela exibida quando o JWT expira ou o usuário não está autenticado — /sessao-expirada
 */
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sessão expirada — MPS Cotações</title>
    <link rel="icon" href="<?= e(url('/assets/img/mesquita-prime-e-sami-corretora-de-seguros-favicon.png')) ?>">
    <meta name="base-path" content="<?= e(BASE_URI) ?>">
    <script src="<?= e(asset_v('/assets/js/base-path.js')) ?>"></script>
    <link rel="stylesheet" href="<?= e(asset_v('/assets/css/tokens.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_v('/assets/css/base.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_v('/assets/css/components.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_v('/assets/css/auth.css')) ?>">
</head>
<body>

<div class="auth-screen-centered">
    <div class="card auth-status-box">
        <div class="auth-status-icon"><?= icon('clock', 'icon-xl') ?></div>
        <h2>Sua sessão expirou</h2>
        <p>Por segurança, encerramos seu acesso após um período de inatividade. Faça login novamente para continuar.</p>
        <a class="btn btn-primary btn-block" href="<?= e(url('/login')) ?>">Fazer login novamente</a>
    </div>
</div>

</body>
</html>
