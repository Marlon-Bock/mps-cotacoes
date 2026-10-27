<?php
/**
 * Aviso no detalhe da Fiança quando a ficha cotada na API das seguradoras
 * foi encaminhada pra fila da MPS — recusada nas seguradoras ou a
 * imobiliária pediu outras seguradoras (fluxo antigo — ver api_encaminhar_mps).
 * Só no detalhe da MPS (a imobiliária não vê seguradora por seguradora).
 * Espera no escopo: $cotacao, $protocolo, $is_admin_aviso (bool).
 */
if (empty($cotacao['encaminhado_mps_em'])) {
    return;
}

$__aviso_recusada = ($cotacao['encaminhado_mps_motivo'] ?? '') === 'recusada_api';
$__aviso_link     = ($is_admin_aviso ? '/admin' : '') . '/cotacao/fianca/' . $protocolo . '/seguradoras';
?>
<div class="encaminhado-aviso <?= $__aviso_recusada ? 'encaminhado-aviso--recusada' : '' ?>">
    <?= icon($__aviso_recusada ? 'warning' : 'paper-plane-tilt') ?>
    <div class="encaminhado-aviso-corpo">
        <span class="encaminhado-aviso-titulo"><?= e((string) $cotacao['encaminhado_mps_detalhe']) ?></span>
        <span class="encaminhado-aviso-texto">
            <?= $__aviso_recusada
                ? 'Cotação recusada na API das seguradoras'
                : 'Cotação feita na API das seguradoras; o cliente pediu para comparar com outras seguradoras' ?>
            — encaminhada para análise da MPS em <?= e(date('d/m/Y \à\s H:i', strtotime($cotacao['encaminhado_mps_em']))) ?>.
        </span>
    </div>
    <a href="<?= e(url($__aviso_link)) ?>" class="btn btn-secondary btn-sm"><?= icon('buildings') ?> Ver na seguradora</a>
</div>
