<?php
/**
 * GET /admin/faq
 * CRUD de blocos e perguntas do FAQ. Toda escrita é via AJAX (/api/admin/faq/*).
 */
$current_user = require_admin();

$page_title   = 'Gerenciar FAQ';
$page_heading = 'Gerenciar FAQ';
$page_back    = null;
$extra_css    = ['dashboard.css', 'cotacoes.css', 'faq.css', 'admin-faq.css'];
$extra_js     = ['admin-faq.js'];

require BASE_PATH . '/pages/partials/app-head.php';
require BASE_PATH . '/pages/partials/nav-admin.php';
?>
<div class="app-shell">
    <?php require BASE_PATH . '/pages/partials/sidebar.php'; ?>
    <div class="app-main">
        <?php require BASE_PATH . '/pages/partials/topbar.php'; ?>
        <main class="app-content" data-csrf="<?= e(csrf_token()) ?>">

            <div class="admin-faq-toolbar">
                <button type="button" class="btn btn-primary btn-sm" id="btn-novo-bloco">
                    <?= icon('plus') ?> Novo bloco
                </button>
            </div>

            <div id="faq-lista">
                <div class="admin-cot-empty">Carregando...</div>
            </div>

        </main>
    </div>
</div>

<!-- Modal: bloco -->
<div class="modal" id="modal-bloco" hidden>
    <div class="modal-backdrop" data-close="modal-bloco"></div>
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 id="modal-bloco-titulo">Novo bloco</h3>
            <button type="button" class="modal-close" data-close="modal-bloco"><?= icon('x') ?></button>
        </div>
        <form id="form-bloco" class="modal-body">
            <input type="hidden" name="id" value="">
            <div class="field">
                <label class="field-label" for="bloco-titulo">Título do bloco</label>
                <div class="field-input-wrap">
                    <span class="field-input-icon"><?= icon('note-pencil') ?></span>
                    <input class="field-input has-icon" type="text" id="bloco-titulo" name="titulo" required>
                </div>
            </div>
            <div class="field">
                <label class="field-label" for="bloco-ordem">Ordem</label>
                <div class="field-input-wrap">
                    <span class="field-input-icon"><?= icon('list') ?></span>
                    <input class="field-input has-icon" type="text" inputmode="numeric" pattern="[0-9]*" id="bloco-ordem" name="ordem" value="0">
                </div>
            </div>
            <label class="switch">
                <input type="checkbox" id="bloco-ativo" name="ativo" value="1" checked>
                <span class="switch-track"></span>
                <span class="switch-label">Bloco ativo</span>
            </label>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close="modal-bloco">Cancelar</button>
                <button type="submit" class="btn btn-primary"><?= icon('check') ?> Salvar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: item -->
<div class="modal" id="modal-item" hidden>
    <div class="modal-backdrop" data-close="modal-item"></div>
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 id="modal-item-titulo">Nova pergunta</h3>
            <button type="button" class="modal-close" data-close="modal-item"><?= icon('x') ?></button>
        </div>
        <form id="form-item" class="modal-body">
            <input type="hidden" name="id" value="">
            <input type="hidden" name="bloco_id" value="">
            <div class="field">
                <label class="field-label" for="item-pergunta">Pergunta</label>
                <div class="field-input-wrap">
                    <span class="field-input-icon"><?= icon('question') ?></span>
                    <input class="field-input has-icon" type="text" id="item-pergunta" name="pergunta" required>
                </div>
            </div>
            <div class="field">
                <label class="field-label" for="item-resposta">Resposta</label>
                <textarea class="field-input" id="item-resposta" name="resposta" rows="4" required></textarea>
            </div>
            <div class="field">
                <label class="field-label" for="item-ordem">Ordem</label>
                <div class="field-input-wrap">
                    <span class="field-input-icon"><?= icon('list') ?></span>
                    <input class="field-input has-icon" type="text" inputmode="numeric" pattern="[0-9]*" id="item-ordem" name="ordem" value="0">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close="modal-item">Cancelar</button>
                <button type="submit" class="btn btn-primary"><?= icon('check') ?> Salvar</button>
            </div>
        </form>
    </div>
</div>

<?php require BASE_PATH . '/pages/partials/app-foot.php'; ?>
