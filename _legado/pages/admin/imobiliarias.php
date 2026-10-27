<?php
/**
 * GET /admin/imobiliarias
 * Lista todas as imobiliárias parceiras com busca e filtro por status.
 * Filtros e paginação são tudo via AJAX (/api/admin/imobiliarias/listar),
 * a URL nunca muda.
 */
$current_user = require_admin();

$flash_messages = flash_get();

$page_title      = 'Imobiliárias';
$page_heading    = 'Imobiliárias';
$page_subheading = 'Gerencie as imobiliárias parceiras';
$page_back       = null;
$extra_css       = ['dashboard.css', 'cotacoes.css'];
$extra_js        = ['admin-imobiliarias-listar.js'];

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
                    <div class="status-kpi-grid">
                        <a href="#" class="status-kpi-card is-active" data-status="">
                            <span class="status-kpi-icon"><?= icon('list') ?></span>
                            <span class="status-kpi-value" id="count-todas">0</span>
                            <span class="status-kpi-label">Todas</span>
                        </a>
                        <a href="#" class="status-kpi-card" data-status="ativo">
                            <span class="status-kpi-icon"><?= icon('check-circle') ?></span>
                            <span class="status-kpi-value" id="count-ativas">0</span>
                            <span class="status-kpi-label">Ativas</span>
                        </a>
                        <a href="#" class="status-kpi-card" data-status="inativo">
                            <span class="status-kpi-icon"><?= icon('x') ?></span>
                            <span class="status-kpi-value" id="count-inativas">0</span>
                            <span class="status-kpi-label">Inativas</span>
                        </a>
                    </div>

                    <div class="admin-cot-toolbar-actions">
                        <a href="<?= e(url('/admin/imobiliarias/criar')) ?>" class="btn btn-primary btn-sm">
                            <?= icon('plus') ?> Criar imobiliária
                        </a>
                    </div>
                </div>

                <div class="cotacoes-filtros admin-filtros cotacao-container">
                    <div class="field field-search-wide">
                        <label class="field-label">Buscar</label>
                        <div class="field-input-wrap">
                            <span class="field-input-icon"><?= icon('magnifying-glass') ?></span>
                            <input type="text" id="f-busca" class="field-input has-icon" placeholder="Nome ou CNPJ...">
                        </div>
                    </div>
                </div>
            </div>

            <div class="panel cot-panel">
                <div class="admin-cot-grid-wrap">
                    <div class="admin-cot-row admin-cot-row-imobiliarias admin-cot-head">
                        <div class="admin-cot-cell admin-cot-cell-nome th-sortable" data-sort="nome">Nome <span class="sort-arrow">▲</span></div>
                        <div class="admin-cot-cell th-sortable" data-sort="cnpj">CNPJ</div>
                        <div class="admin-cot-cell th-sortable" data-sort="email">E-mail</div>
                        <div class="admin-cot-cell th-sortable" data-sort="telefone">Telefone</div>
                        <div class="admin-cot-cell th-sortable" data-sort="ativo">Status</div>
                        <div class="admin-cot-cell th-sortable" data-sort="criado_em">Cadastrada em</div>
                        <div class="admin-cot-cell">Ações</div>
                    </div>
                    <div id="imobiliarias-rows">
                        <div class="admin-cot-empty">Carregando...</div>
                    </div>
                </div>
                <div class="admin-cot-pagination">
                    <span id="pag-info" class="admin-cot-pagination-info"></span>
                    <div class="admin-cot-pagination-btns">
                        <button type="button" id="pag-anterior" class="btn btn-secondary btn-sm"><?= icon('arrow-left') ?> Anterior</button>
                        <button type="button" id="pag-proxima" class="btn btn-secondary btn-sm">Próxima <?= icon('arrow-right') ?></button>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>
<?php require BASE_PATH . '/pages/partials/app-foot.php'; ?>
