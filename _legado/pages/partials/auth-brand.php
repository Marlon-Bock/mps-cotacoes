<?php
/**
 * Painel de marca exibido à esquerda nas telas de Acesso.
 * Espera (opcional): $auth_brand_title, $auth_brand_text
 */
$auth_brand_title ??= 'Cotações sem complicação.';
$auth_brand_text  ??= 'Gerencie suas cotações de fiança e seguro auto em um único lugar, com acompanhamento em tempo real.';
?>
<aside class="auth-brand">
    <div class="auth-brand-logo">
        <img src="<?= e(url('/assets/img/logo-mark.png')) ?>" alt="" width="40" height="40">
        <span>MPS Cotações</span>
    </div>
    <div class="auth-brand-message">
        <h1><?= e($auth_brand_title) ?></h1>
        <p><?= e($auth_brand_text) ?></p>
    </div>
    <div class="auth-brand-footer">
        &copy; <?= date('Y') ?> Mesquita Prime e Sami Corretora de Seguros. Todos os direitos reservados.
    </div>
</aside>
