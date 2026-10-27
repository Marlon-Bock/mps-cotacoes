<?php
/**
 * GET /admin/usuarios
 * Lista todos os usuários internos da MPS (corretores/atendentes/admins).
 * Mesmo padrão de /usuarios (Gerenciar Usuários da imobiliária) — busca via
 * AJAX sem paginação, a URL nunca muda.
 */
$current_user = require_admin();

$flash_messages = flash_get();

$page_title      = 'Usuários MPS';
$page_heading    = 'Usuários MPS';
$page_subheading = 'Corretores, atendentes e administradores do sistema';
$page_back       = null;
$extra_css       = ['cotacoes.css'];
$extra_js        = ['admin-usuarios-listar.js'];

require BASE_PATH . '/pages/partials/app-head.php';
require BASE_PATH . '/pages/partials/nav-admin.php';
?>
<div class="app-shell">
    <?php require BASE_PATH . '/pages/partials/sidebar.php'; ?>
    <div class="app-main">
        <?php require BASE_PATH . '/pages/partials/topbar.php'; ?>
        <main class="app-content" data-flash="<?= e(json_encode($flash_messages)) ?>">

            <div class="admin-cot-filterbar">
                <div class="admin-cot-toolbar">
                    <div class="cotacoes-filtros admin-filtros cotacao-container">
                        <div class="field field-search-wide">
                            <label class="field-label">Nome / E-mail</label>
                            <div class="field-input-wrap">
                                <span class="field-input-icon"><?= icon('magnifying-glass') ?></span>
                                <input type="text" id="f-busca" class="field-input has-icon" placeholder="Buscar por nome ou e-mail">
                            </div>
                        </div>
                    </div>
                    <div class="admin-cot-toolbar-actions">
                        <a href="<?= e(url('/admin/usuarios/criar')) ?>" class="btn btn-primary btn-sm">
                            <?= icon('user') ?> Criar usuário
                        </a>
                    </div>
                </div>
            </div>

            <div class="panel cot-panel">
                <div class="admin-cot-grid-wrap">
                    <div class="admin-cot-row admin-cot-row-usuarios admin-cot-head">
                        <div class="admin-cot-cell admin-cot-cell-nome">Nome</div>
                        <div class="admin-cot-cell">E-mail</div>
                        <div class="admin-cot-cell">Telefone</div>
                        <div class="admin-cot-cell">Perfil</div>
                        <div class="admin-cot-cell">Último login</div>
                        <div class="admin-cot-cell">Ações</div>
                    </div>
                    <div id="usuarios-lista" data-csrf="<?= e(csrf_token()) ?>">
                        <div class="admin-cot-empty">Carregando...</div>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>
<?php require BASE_PATH . '/pages/partials/app-foot.php'; ?>
