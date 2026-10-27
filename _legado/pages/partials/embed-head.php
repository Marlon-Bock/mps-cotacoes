<?php
/**
 * <head> enxuto para as páginas de embed (cotação sem login, dentro de um
 * iframe no site do cliente) — versão reduzida de app-head.php: mesmos
 * CSS base do formulário, mas sem sidebar/topbar/chat/notificações (que
 * dependem de sessão autenticada e não fazem sentido dentro do iframe).
 *
 * Espera: $page_title, $extra_css (opcional), $current_user (stub do
 * embed_resolver()) e $embed_token (para o CSRF stateless via meta tag,
 * lido pelo mesmo csrf-fetch.js usado no resto do sistema).
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
    <link rel="stylesheet" href="<?= e(asset_v('/assets/css/tokens.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_v('/assets/css/base.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_v('/assets/css/components.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_v('/assets/css/mps-alert.css')) ?>">
    <link rel="stylesheet" href="<?= e(asset_v('/assets/css/layout.css')) ?>">
    <?php foreach ($extra_css as $css): ?>
    <link rel="stylesheet" href="<?= e(asset_v('/assets/css/' . $css)) ?>">
    <?php endforeach; ?>
    <meta name="csrf-token" content="<?= e(embed_csrf_token($embed_token)) ?>">
    <style>
        /* Dentro do iframe não existe app-shell (sidebar/topbar) — só o form. */
        body { background: transparent; }
        .app-content { padding: 16px; max-width: 1100px; margin: 0 auto; }
    </style>
</head>
<body data-role="imobiliaria" data-embed-token="<?= e($embed_token) ?>">
<main class="app-content">
