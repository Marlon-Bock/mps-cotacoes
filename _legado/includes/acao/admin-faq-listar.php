<?php
/**
 * GET /api/admin/faq/listar
 * Retorna JSON { html } com todos os blocos + itens de FAQ.
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');

$blocos = [];
try {
    $blocos = db_fetch_all('SELECT id, titulo, ordem, ativo FROM faq_blocos ORDER BY ordem ASC, id ASC');
    foreach ($blocos as &$bloco) {
        $bloco['itens'] = db_fetch_all('SELECT id, pergunta, resposta, ordem FROM faq_itens WHERE bloco_id = ? ORDER BY ordem ASC, id ASC', ['i', $bloco['id']]);
    }
    unset($bloco);
} catch (\Throwable $e) {
    $blocos = [];
}

ob_start();

if (!$blocos): ?>
<div class="panel">
    <div class="empty-state">
        <div class="empty-state-icon"><?= icon('question') ?></div>
        <p>Nenhum bloco cadastrado ainda.</p>
    </div>
</div>
<?php endif;

foreach ($blocos as $bloco): ?>
<div class="panel faq-bloco" data-bloco-id="<?= (int) $bloco['id'] ?>">
    <div class="panel-header">
        <h2>
            <?= e($bloco['titulo']) ?>
            <?php if (!$bloco['ativo']): ?><span class="status-pill status-pill-pendente">Inativo</span><?php endif; ?>
        </h2>
        <div class="admin-faq-bloco-actions">
            <button type="button" class="btn btn-secondary btn-sm" data-action="novo-item" data-bloco-id="<?= (int) $bloco['id'] ?>">
                <?= icon('plus') ?> Nova pergunta
            </button>
            <button type="button" class="btn btn-secondary btn-sm" data-action="editar-bloco" data-bloco-id="<?= (int) $bloco['id'] ?>" data-titulo="<?= e($bloco['titulo']) ?>" data-ativo="<?= (int) $bloco['ativo'] ?>" data-ordem="<?= (int) $bloco['ordem'] ?>">
                <?= icon('note-pencil') ?> Editar
            </button>
            <button type="button" class="btn btn-danger-outline btn-sm" data-action="excluir-bloco" data-bloco-id="<?= (int) $bloco['id'] ?>">
                <?= icon('x') ?> Excluir
            </button>
        </div>
    </div>
    <div class="panel-body faq-itens">
        <?php foreach ($bloco['itens'] as $item): ?>
        <div class="faq-item admin-faq-item" data-item-id="<?= (int) $item['id'] ?>">
            <button type="button" class="faq-item-pergunta" data-toggle="faq-item">
                <span><?= e($item['pergunta']) ?></span>
                <?= icon('caret-down', 'faq-item-caret') ?>
            </button>
            <div class="faq-item-resposta">
                <p><?= nl2br(e($item['resposta'])) ?></p>
                <div class="admin-faq-item-actions">
                    <button type="button" class="btn btn-secondary btn-sm" data-action="editar-item"
                        data-item-id="<?= (int) $item['id'] ?>"
                        data-bloco-id="<?= (int) $bloco['id'] ?>"
                        data-pergunta="<?= e($item['pergunta']) ?>"
                        data-resposta="<?= e($item['resposta']) ?>"
                        data-ordem="<?= (int) $item['ordem'] ?>">
                        <?= icon('note-pencil') ?> Editar
                    </button>
                    <button type="button" class="btn btn-danger-outline btn-sm" data-action="excluir-item" data-item-id="<?= (int) $item['id'] ?>">
                        <?= icon('x') ?> Excluir
                    </button>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
        <?php if (!$bloco['itens']): ?>
        <p class="faq-vazio">Nenhuma pergunta neste bloco.</p>
        <?php endif; ?>
    </div>
</div>
<?php endforeach;

$html = ob_get_clean();

echo json_encode(['html' => $html]);
