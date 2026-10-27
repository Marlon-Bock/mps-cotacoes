<?php
/**
 * Popup de campanha por tela de produto.
 * Espera $campanha_popup_tipo definida pela página que incluir esta partial
 * ('residencial'|'comercial'|'auto'|'titulo'|'condominial') e $current_user
 * já autenticado (imobiliaria_id). Renderiza o modal só se houver uma
 * campanha ativa elegível — sem JS extra (campanha-popup.js) o modal fica
 * parado, escondido.
 */

$campanha_popup = campanha_popup_ativa((int) $current_user->imobiliaria_id, $campanha_popup_tipo ?? '');
?>
<?php $campanha_popup_obs = trim((string) ($campanha_popup['observacoes'] ?? '')); ?>
<?php if ($campanha_popup): ?>
<div class="modal modal-campanha-popup" id="modal-campanha-popup" data-popup-campanha data-campanha-id="<?= (int) $campanha_popup['id'] ?>" hidden>
    <div class="modal-backdrop" data-close="modal-campanha-popup"></div>
    <div class="modal-dialog">
        <button type="button" class="modal-close" data-close="modal-campanha-popup" aria-label="Fechar"><?= icon('x') ?></button>
        <?php if ($campanha_popup_obs !== ''): ?>
        <button type="button" class="modal-campanha-popup-obs-btn" data-open="modal-campanha-popup-obs"><?= icon('file-text') ?> Observações</button>
        <?php endif; ?>
        <a href="<?= e(url('/campanhas')) ?>" class="modal-campanha-popup-link">
            <img src="<?= e($campanha_popup['imagem']) ?>" alt="<?= e($campanha_popup['titulo']) ?>">
        </a>
    </div>
</div>
<?php endif; ?>
<?php if ($campanha_popup && $campanha_popup_obs !== ''): ?>
<div class="modal modal-campanha-obs" id="modal-campanha-popup-obs" hidden>
    <div class="modal-backdrop" data-close="modal-campanha-popup-obs"></div>
    <div class="modal-dialog">
        <div class="modal-header">
            <h3><?= icon('file-text') ?> Observações</h3>
            <button type="button" class="modal-close" data-close="modal-campanha-popup-obs" aria-label="Fechar"><?= icon('x') ?></button>
        </div>
        <div class="modal-body">
            <p><?= nl2br(e($campanha_popup_obs)) ?></p>
        </div>
    </div>
</div>
<?php endif; ?>
