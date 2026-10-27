<?php
/**
 * GET /faq
 * FAQ — visível para imobiliária e admin. Somente leitura.
 */
$current_user = require_auth();

$blocos = db_fetch_all('SELECT id, titulo FROM faq_blocos WHERE ativo = 1 ORDER BY ordem ASC, id ASC');
foreach ($blocos as &$bloco) {
    $bloco['itens'] = db_fetch_all('SELECT pergunta, resposta FROM faq_itens WHERE bloco_id = ? ORDER BY ordem ASC, id ASC', ['i', $bloco['id']]);
}
unset($bloco);

$page_title   = 'Perguntas Frequentes';
$page_heading = 'FAQ — Perguntas Frequentes';
$page_back    = null;
$extra_css    = ['dashboard.css', 'faq.css', 'chamados.css'];
$extra_js     = ['faq.js', 'faq-fluxo.js'];

require BASE_PATH . '/pages/partials/app-head.php';
require BASE_PATH . '/pages/partials/' . ($current_user->role === 'admin' ? 'nav-admin.php' : 'nav-imobiliaria.php');
?>
<div class="app-shell">
    <?php require BASE_PATH . '/pages/partials/sidebar.php'; ?>
    <div class="app-main">
        <?php require BASE_PATH . '/pages/partials/topbar.php'; ?>
        <main class="app-content">

            <div class="field faq-search">
                <div class="field-input-wrap">
                    <span class="field-input-icon"><?= icon('magnifying-glass') ?></span>
                    <input class="field-input has-icon" type="text" id="faq-busca" placeholder="Buscar pergunta...">
                </div>
            </div>

            <?php if (!$blocos): ?>
            <div class="panel">
                <div class="empty-state">
                    <div class="empty-state-icon"><?= icon('question') ?></div>
                    <p>Nenhuma pergunta cadastrada ainda.</p>
                </div>
            </div>
            <?php endif; ?>

            <?php foreach ($blocos as $bloco): ?>
            <div class="panel faq-bloco">
                <div class="panel-header">
                    <h2><?= e($bloco['titulo']) ?></h2>
                </div>
                <div class="panel-body faq-itens">
                    <?php foreach ($bloco['itens'] as $item): ?>
                    <div class="faq-item">
                        <button type="button" class="faq-item-pergunta" data-toggle="faq-item">
                            <span><?= e($item['pergunta']) ?></span>
                            <?= icon('caret-down', 'faq-item-caret') ?>
                        </button>
                        <div class="faq-item-resposta">
                            <p><?= nl2br(e($item['resposta'])) ?></p>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php if (!$bloco['itens']): ?>
                    <p class="faq-vazio">Nenhuma pergunta neste bloco.</p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>

        </main>
    </div>
</div>

<button type="button" class="faq-fluxo-launcher" id="faq-fluxo-launcher">
    <?= icon('chat-circle') ?>
    <span>Precisa de ajuda?</span>
</button>

<div class="faq-fluxo-widget" id="faq-fluxo-widget" hidden>
    <div class="faq-fluxo-widget-header">
        <span><?= icon('chat-circle') ?> Fale com a gente</span>
        <button type="button" class="faq-fluxo-widget-close" id="faq-fluxo-widget-close"><?= icon('x') ?></button>
    </div>
    <div class="chamado-chat-box" id="faq-fluxo-box">
        <div class="chamado-chat-messages">
            <div class="chamado-thread" id="faq-fluxo-thread">
                <div class="chamado-thread-empty">
                    <?= icon('chat-circle') ?>
                    <p>Carregando...</p>
                </div>
            </div>
        </div>
        <div class="chamado-chat-input-bar" id="faq-fluxo-input-bar" hidden>
            <input type="text" id="faq-fluxo-input" class="chamado-input-field" placeholder="Digite aqui..." maxlength="500" autocomplete="off">
            <button type="button" class="chamado-send-btn" id="faq-fluxo-send"><?= icon('paper-plane-tilt') ?></button>
        </div>
    </div>
</div>

<?php require BASE_PATH . '/pages/partials/app-foot.php'; ?>
