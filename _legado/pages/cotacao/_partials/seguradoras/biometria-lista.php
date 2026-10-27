<?php
/**
 * Biometrias pedidas pelas seguradoras (etapa "Biometria pendente", antes
 * das propostas) — uma linha por seguradora: situação e, na pendente,
 * "Enviar por WhatsApp" / "Copiar link" pro pretendente. Pode ter mais de
 * uma: o pretendente faz todas. A imobiliária vê "Biometria 1, 2..."; a MPS,
 * o nome da seguradora.
 *
 * Espera: $__biometrias (api_biometrias()), $__telefone (celular do pretendente),
 * $__comSeguradora (bool).
 */
$__rotuloBio = [
    'aguardando_biometria' => ['Pendente', 'status-pill-pendente'],
    'biometria_concluida'  => ['Concluída', 'status-pill-aprovado'],
    'biometria_recusada'   => ['Recusada', 'status-pill-recusado'],
];
?>
<ul class="seg-biometrias">
    <?php foreach ($__biometrias as $__n => $__b): ?>
    <?php [$__txt, $__pill] = $__rotuloBio[$__b['status']]; ?>
    <li class="seg-biometria<?= $__b['status'] === 'aguardando_biometria' ? ' is-pendente' : '' ?>">
        <span class="seg-biometria-nome">
            <?= icon('fingerprint') ?>
            <?= e($__comSeguradora ? api_seguradora_nome((string) $__b['seguradora']) : 'Biometria' . (count($__biometrias) > 1 ? ' ' . ($__n + 1) : '')) ?>
        </span>
        <span class="status-pill <?= e($__pill) ?>"><?= e($__txt) ?></span>
        <?php if ($__b['status'] === 'aguardando_biometria'): ?>
        <span class="seg-biometria-acoes">
            <button type="button" class="btn btn-primary btn-sm btn-biometria-whatsapp" data-n="<?= $__n ?>" data-telefone="<?= e($__telefone) ?>"><?= icon('whatsapp-logo') ?> Enviar por WhatsApp</button>
            <button type="button" class="btn btn-secondary btn-sm btn-biometria-copiar" data-n="<?= $__n ?>"><?= icon('clipboard-text') ?> Copiar link</button>
        </span>
        <?php endif; ?>
    </li>
    <?php endforeach; ?>
</ul>
