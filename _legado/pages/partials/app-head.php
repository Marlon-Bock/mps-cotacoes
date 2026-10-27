<?php
/**
 * <head> compartilhado de todas as telas autenticadas.
 * Espera: $page_title (string), $extra_css (array opcional de paths em /assets/css/)
 */
$extra_css ??= [];
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($page_title) ?> — MPS Cotações</title>
    <meta name="base-path" content="<?= e(BASE_URI) ?>">
    <script src="<?= e(asset_v('/assets/js/base-path.js')) ?>"></script>
    <link rel="icon" href="<?= e(url('/assets/img/mesquita-prime-e-sami-corretora-de-seguros-favicon.png')) ?>">
    <link rel="manifest" href="<?= e(url('/manifest.webmanifest')) ?>">
    <link rel="apple-touch-icon" href="<?= e(url('/assets/img/logo-mark.png')) ?>">
    <meta name="theme-color" content="#7F1B1B">
    <link rel="stylesheet" href="<?= e(asset_v('/assets/css/tokens.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_v('/assets/css/base.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_v('/assets/css/components.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_v('/assets/css/mps-alert.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_v('/assets/css/layout.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_v('/assets/css/chamados.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_v('/assets/css/cotacao-chat.css')) ?>">
    <?php foreach ($extra_css as $css): ?>
    <link rel="stylesheet" href="<?= e(asset_v('/assets/css/' . $css)) ?>">
    <?php endforeach; ?>
    <?php
    // Expõe o timestamp de expiração do token para o watchdog JS
    $__token_exp = 0;
    if (!empty($_COOKIE['mps_token'])) {
        $__parts = explode('.', $_COOKIE['mps_token']);
        if (count($__parts) === 3) {
            $__b64     = base64_decode(strtr($__parts[1], '-_', '+/'), false);
            $__payload = is_string($__b64) ? json_decode($__b64) : null;
            $__token_exp = is_object($__payload) ? (int)($__payload->exp ?? 0) : 0;
        }
    }
    unset($__parts, $__b64, $__payload);
    ?>
    <meta name="session-exp" content="<?= (int)$__token_exp ?>">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
</head>
<body data-role="<?= e($current_user->role ?? '') ?>" data-uid="<?= (int) ($current_user->uid ?? 0) ?>">
