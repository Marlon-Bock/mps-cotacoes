<?php
/**
 * Tela de Login — /login
 * Login único: o backend decide o redirecionamento por perfil (role).
 */

// Se já autenticado, redireciona direto para a área correta
$current_user = auth_user();
if ($current_user) {
    redirect(match ($current_user->role ?? '') {
        'painel' => '/painel',
        'admin'  => '/admin',
        default  => '/',
    });
}

$redefinida = isset($_GET['redefinida']);
?>
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrar — MPS Cotações</title>
    <link rel="icon" href="<?= e(url('/assets/img/mesquita-prime-e-sami-corretora-de-seguros-favicon.png')) ?>">
    <link rel="manifest" href="<?= e(url('/manifest.webmanifest')) ?>">
    <link rel="apple-touch-icon" href="<?= e(url('/assets/img/logo-mark.png')) ?>">
    <meta name="theme-color" content="#7F1B1B">
    <meta name="base-path" content="<?= e(BASE_URI) ?>">
    <script src="<?= e(asset_v('/assets/js/base-path.js')) ?>"></script>
    <link rel="stylesheet" href="<?= e(asset_v('/assets/css/tokens.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_v('/assets/css/base.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_v('/assets/css/components.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_v('/assets/css/auth.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_v('/assets/css/mps-alert.css')) ?>">
</head>

<body>

    <div class="auth-screen">
            <?php require __DIR__ . '/partials/auth-brand.php'; ?>

        <main class="auth-form-side">
            <div class="auth-form-box">
                <div class="auth-form-box-header">
                    <h2>Acessar conta</h2>
                    <p>Informe seus dados de acesso para continuar.</p>
                </div>

                <div class="alert alert-success" data-form-alert-success <?= $redefinida ? '' : 'hidden' ?>>
                    <span class="alert-icon"><?= icon('check') ?></span>
                    <span>Senha redefinida com sucesso. Faça login com sua nova senha.</span>
                </div>

                <form data-form="login" data-csrf="<?= e(csrf_token()) ?>" data-loading-label="Validando..." novalidate>
                    <div class="alert alert-danger" data-form-alert hidden></div>

                    <div class="field">
                        <label class="field-label" for="email">E-mail</label>
                        <div class="field-input-wrap">
                            <span class="field-input-icon"><?= icon('envelope-simple') ?></span>
                            <input class="field-input has-icon" type="email" id="email" name="email" placeholder="seu@email.com"
                                autocomplete="username" required>
                        </div>
                        <span class="field-error" data-error-for="email"></span>
                    </div>

                    <div class="field">
                        <label class="field-label" for="senha">Senha</label>
                        <div class="field-input-wrap">
                            <span class="field-input-icon"><?= icon('key') ?></span>
                            <input class="field-input has-icon has-icon-both" type="password" id="senha" name="senha" placeholder="••••••••"
                                autocomplete="current-password" required>
                            <button type="button" class="field-password-toggle" data-toggle="password" data-target="#senha"
                                aria-label="Mostrar senha">
                                <?= icon('eye', 'icon-eye') ?>
                                <?= icon('eye-slash', 'icon-eye-slash') ?>
                            </button>
                        </div>
                        <span class="field-error" data-error-for="senha"></span>
                    </div>

                    <div class="auth-form-options">
                        <label class="switch">
                            <input type="checkbox" name="lembrar" value="1">
                            <span class="switch-track"></span>
                            <span class="switch-label">Lembrar de mim</span>
                        </label>
                        <a class="btn-link" href="<?= e(url('/esqueceu-senha')) ?>">Esqueceu a senha?</a>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">
                        <span data-label>Entrar</span>
                        <span class="spinner"></span>
                    </button>
                </form>
            </div>
        </main>
    </div>

    <script src="<?= e(asset_v('/assets/js/mpsAlert.js')) ?>"></script>
    <script src="<?= e(asset_v('/assets/js/auth.js')) ?>"></script>
    <script src="<?= e(asset_v('/assets/js/pwa-install.js')) ?>"></script>
</body>

</html>