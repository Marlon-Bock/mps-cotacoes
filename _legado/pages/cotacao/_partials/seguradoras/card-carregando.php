<?php
/**
 * Card "Carregando proposta..." de uma seguradora que ainda está cotando
 * (ou recotando depois de "Corrigir dados"). Espera: $seguradora.
 */
?>
<div class="seg-card seg-card--skeleton">
    <div class="seg-card-marca">
        <?php require __DIR__ . '/logo.php'; ?>
    </div>
    <div class="seg-card-titulo">
        <span class="seg-nome"><?= e($seguradora['nome']) ?></span>
    </div>
    <div class="seg-card-premio">
        <div class="seg-skeleton-bloco" style="width:110px;height:12px;margin:0 auto"></div>
        <div class="seg-skeleton-bloco" style="width:150px;height:28px;margin:8px auto 0"></div>
    </div>
    <p class="seg-skeleton-texto"><?= icon('arrows-clockwise') ?> Carregando proposta...</p>
</div>
