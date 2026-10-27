<?php
/**
 * Tela de redefinição de senha — /recuperar-senha/{token}
 * $params['token'] vem do router.
 */

$token = $params['token'] ?? '';
$token_hash = hash('sha256', $token);

// Procura o token nas duas tabelas legadas de reset (imobiliária e MPS),
// guardando qual delas respondeu para não precisar adivinhar depois.
$reset_table = 'reset_senha_users';
$reset = db_fetch_one(
    'SELECT id, expires_at, used_at, (expires_at > NOW()) AS dentro_do_prazo FROM reset_senha_users WHERE token_hash = ? LIMIT 1',
    ['s', $token_hash]
);

if (!$reset) {
    $reset_table = 'reset_senha_usuario';
    $reset = db_fetch_one(
        'SELECT id, expires_at, used_at, (expires_at > NOW()) AS dentro_do_prazo FROM reset_senha_usuario WHERE token_hash = ? LIMIT 1',
        ['s', $token_hash]
    );
}

// Prazo comparado no próprio MySQL: expires_at é gravado com NOW() do banco, e
// comparar com time() do PHP falha quando os dois estão em fusos diferentes
// (ex.: PHP-FPM sem date.timezone = UTC, MySQL em -03) — o link já nascia expirado.
$token_valido = $reset && !$reset['used_at'] && (int) $reset['dentro_do_prazo'] === 1;

// Marca a abertura do link SEM consumi-lo — o token só é gasto no POST bem-sucedido
// (corrige o bug do sistema antigo, que invalidava o link já no simples GET)
if ($token_valido) {
    db_execute("UPDATE {$reset_table} SET opened_at = NOW() WHERE id = ? AND opened_at IS NULL", ['i', $reset['id']]);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redefinir senha — MPS Cotações</title>
    <link rel="icon" href="<?= e(url('/assets/img/mesquita-prime-e-sami-corretora-de-seguros-favicon.png')) ?>">
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
    <?php
    $auth_brand_title = 'Crie uma nova senha.';
    $auth_brand_text  = 'Escolha uma senha forte para manter sua conta protegida.';
    require __DIR__ . '/partials/auth-brand.php';
    ?>

    <main class="auth-form-side">
        <div class="auth-form-box">

            <?php if (!$token_valido): ?>

                <div class="auth-form-box-header">
                    <h2>Link inválido ou expirado</h2>
                    <p>Esse link de redefinição não é mais válido. Solicite um novo.</p>
                </div>
                <a class="btn btn-primary btn-block" href="<?= e(url('/esqueceu-senha')) ?>">Solicitar novo link</a>

            <?php else: ?>

                <div class="auth-form-box-header">
                    <h2>Defina sua nova senha</h2>
                    <p>Use no mínimo 6 caracteres, com letras e números.</p>
                </div>

                <form data-form="recuperar-senha" data-csrf="<?= e(csrf_token()) ?>" data-loading-label="Salvando..." novalidate>
                    <input type="hidden" name="token" value="<?= e($token) ?>">
                    <div class="alert alert-danger" data-form-alert hidden></div>

                    <div class="field">
                        <label class="field-label" for="senha">Nova senha</label>
                        <div class="field-input-wrap">
                            <span class="field-input-icon"><?= icon('key') ?></span>
                            <input
                                class="field-input has-icon has-icon-both"
                                type="password"
                                id="senha"
                                name="senha"
                                placeholder="••••••••"
                                autocomplete="new-password"
                                minlength="6"
                                required
                            >
                            <button type="button" class="field-password-toggle" data-toggle="password" data-target="#senha" aria-label="Mostrar senha">
                                <?= icon('eye', 'icon-eye') ?>
                                <?= icon('eye-slash', 'icon-eye-slash') ?>
                            </button>
                        </div>
                        <span class="field-error" data-error-for="senha"></span>
                    </div>

                    <div class="field">
                        <label class="field-label" for="senha_confirmacao">Confirme a nova senha</label>
                        <div class="field-input-wrap">
                            <span class="field-input-icon"><?= icon('key') ?></span>
                            <input
                                class="field-input has-icon has-icon-both"
                                type="password"
                                id="senha_confirmacao"
                                name="senha_confirmacao"
                                placeholder="••••••••"
                                autocomplete="new-password"
                                minlength="6"
                                required
                            >
                            <button type="button" class="field-password-toggle" data-toggle="password" data-target="#senha_confirmacao" aria-label="Mostrar confirmação de senha">
                                <?= icon('eye', 'icon-eye') ?>
                                <?= icon('eye-slash', 'icon-eye-slash') ?>
                            </button>
                        </div>
                        <span class="field-error" data-error-for="senha_confirmacao"></span>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">
                        <span data-label>Salvar nova senha</span>
                        <span class="spinner"></span>
                    </button>
                </form>

            <?php endif; ?>

        </div>
    </main>
</div>

<script src="<?= e(asset_v('/assets/js/mpsAlert.js')) ?>"></script>
<script src="<?= e(asset_v('/assets/js/auth.js')) ?>"></script>
</body>
</html>
