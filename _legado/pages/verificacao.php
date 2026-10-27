<?php
/**
 * GET+POST /verificacao
 * Página de verificação (Cloudflare Turnstile) para acessos de fora do Brasil.
 * Ao passar, marca a sessão como verificada e volta ao destino original.
 */

// Já verificado (ou recurso desligado) → não fica preso aqui.
if (!empty($_SESSION['geo_ok']) || env('TURNSTILE_SITE_KEY', '') === '') {
    redirect($_SESSION['geo_destino'] ?? '/login');
}

$erro = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = $_POST['cf-turnstile-response'] ?? '';
    if (turnstile_verificar((string) $token, cliente_ip())) {
        $_SESSION['geo_ok'] = true;
        $destino = $_SESSION['geo_destino'] ?? '/login';
        unset($_SESSION['geo_destino']);
        redirect($destino);
    }
    $erro = 'Verificação falhou. Tente novamente.';
}

$site_key = env('TURNSTILE_SITE_KEY', '');
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificação de segurança — MPS Cotações</title>
    <link rel="icon" href="<?= e(url('/assets/img/mesquita-prime-e-sami-corretora-de-seguros-favicon.png')) ?>">
    <meta name="base-path" content="<?= e(BASE_URI) ?>">
    <script src="<?= e(asset_v('/assets/js/base-path.js')) ?>"></script>
    <link rel="stylesheet" href="<?= e(asset_v('/assets/css/tokens.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_v('/assets/css/base.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_v('/assets/css/components.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_v('/assets/css/auth.css')) ?>">
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
</head>
<body>
    <div class="auth-screen">
        <?php require __DIR__ . '/partials/auth-brand.php'; ?>

        <main class="auth-form-side">
            <div class="auth-form-box">
                <div class="auth-form-box-header">
                    <h2>Verificação de segurança</h2>
                    <p>Detectamos um acesso de fora do Brasil. Confirme que você não é um robô para continuar.</p>
                </div>

                <?php if ($erro !== ''): ?>
                <div class="alert alert-danger"><?= e($erro) ?></div>
                <?php endif; ?>

                <form method="POST" action="<?= e(url('/verificacao')) ?>" class="verificacao-form">
                    <div class="cf-turnstile" data-sitekey="<?= e($site_key) ?>" data-callback="onTurnstileOk"></div>
                    <button type="submit" class="btn btn-primary btn-block verificacao-btn" id="btn-verificar" disabled>
                        Continuar
                    </button>
                </form>
            </div>
        </main>
    </div>

    <script>
        // Habilita o botão só quando o Turnstile resolver o desafio.
        function onTurnstileOk() {
            var b = document.getElementById('btn-verificar');
            if (b) b.disabled = false;
        }
    </script>
</body>
</html>
