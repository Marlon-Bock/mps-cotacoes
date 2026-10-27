<?php
/**
 * GET /campanhas
 * Lista as campanhas direcionadas à imobiliária logada, com busca, filtro
 * de período e chips Ativas/Encerradas/Futuras/Todas — tudo via AJAX
 * (/api/campanhas/listar), a URL nunca muda.
 */
$current_user = require_auth();

$page_title      = 'Campanhas';
$page_heading    = 'Campanhas';
$page_subheading = 'Campanhas para sua imobiliária';
$page_back       = null;
$extra_css       = ['dashboard.css', 'cotacoes.css'];
$extra_js        = ['campanhas-listar.js'];

require BASE_PATH . '/pages/partials/app-head.php';
require BASE_PATH . '/pages/partials/nav-imobiliaria.php';
?>
<div class="app-shell">
    <?php require BASE_PATH . '/pages/partials/sidebar.php'; ?>
    <div class="app-main">
        <?php require BASE_PATH . '/pages/partials/topbar.php'; ?>
        <main class="app-content">

            <div class="admin-cot-filterbar">
                <div class="admin-cot-toolbar">
                    <div class="status-kpi-grid">
                        <a href="#" class="status-kpi-card" data-status="">
                            <span class="status-kpi-icon"><?= icon('list') ?></span>
                            <span class="status-kpi-value" id="count-todas">0</span>
                            <span class="status-kpi-label">Todas</span>
                        </a>
                        <a href="#" class="status-kpi-card is-active" data-status="ativas">
                            <span class="status-kpi-icon"><?= icon('check-circle') ?></span>
                            <span class="status-kpi-value" id="count-ativas">0</span>
                            <span class="status-kpi-label">Ativas</span>
                        </a>
                        <a href="#" class="status-kpi-card" data-status="encerradas">
                            <span class="status-kpi-icon"><?= icon('x') ?></span>
                            <span class="status-kpi-value" id="count-encerradas">0</span>
                            <span class="status-kpi-label">Encerradas</span>
                        </a>
                        <a href="#" class="status-kpi-card" data-status="futuras">
                            <span class="status-kpi-icon"><?= icon('calendar-blank') ?></span>
                            <span class="status-kpi-value" id="count-futuras">0</span>
                            <span class="status-kpi-label">Futuras</span>
                        </a>
                    </div>

                    <div class="admin-cot-toolbar-actions">
                        <div class="view-toggle" role="group" aria-label="Modo de visualização">
                            <button type="button" class="view-toggle-btn is-active" id="btn-view-grid" data-view="grid" title="Visualizar em cards" aria-label="Visualizar em cards"><?= icon('kanban') ?></button>
                            <button type="button" class="view-toggle-btn" id="btn-view-list" data-view="list" title="Visualizar em lista" aria-label="Visualizar em lista"><?= icon('table') ?></button>
                        </div>
                    </div>
                </div>

                <div class="cotacoes-filtros admin-filtros cotacao-container">
                    <div class="field field-search-wide">
                        <label class="field-label">Buscar</label>
                        <div class="field-input-wrap">
                            <span class="field-input-icon"><?= icon('magnifying-glass') ?></span>
                            <input type="text" id="f-busca" class="field-input has-icon" placeholder="Título da campanha...">
                        </div>
                    </div>
                    <div class="field">
                        <label class="field-label">Início do período (de / até)</label>
                        <div class="field-date-range">
                            <?= date_picker('f-data-de') ?>
                            <?= date_picker('f-data-ate') ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="panel">
                <div class="panel-body" id="campanhas-view-grid">
                    <div class="campaign-grid campaign-grid-4" id="campanhas-cards">
                        <div class="admin-cot-empty">Carregando...</div>
                    </div>
                </div>
                <div class="admin-cot-grid-wrap" id="campanhas-view-list" hidden>
                    <div class="admin-cot-row admin-cot-row-campanhas admin-cot-row-campanhas-imob admin-cot-head">
                        <div class="admin-cot-cell admin-cot-cell-nome">Título</div>
                        <div class="admin-cot-cell">Status</div>
                        <div class="admin-cot-cell">Período</div>
                    </div>
                    <div id="campanhas-rows">
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

<div class="modal modal-campanha-popup" id="modal-campanha-detalhe" hidden>
    <div class="modal-backdrop" data-close="modal-campanha-detalhe"></div>
    <div class="modal-dialog">
        <button type="button" class="modal-close" data-close="modal-campanha-detalhe" aria-label="Fechar"><?= icon('x') ?></button>
        <button type="button" class="modal-campanha-popup-obs-btn" id="campanha-detalhe-obs-btn" data-open="modal-campanha-obs" hidden><?= icon('file-text') ?> Observações</button>
        <img id="campanha-detalhe-img" src="" alt="">
    </div>
</div>

<div class="modal modal-campanha-obs" id="modal-campanha-obs" hidden>
    <div class="modal-backdrop" data-close="modal-campanha-obs"></div>
    <div class="modal-dialog">
        <div class="modal-header">
            <h3><?= icon('file-text') ?> Observações</h3>
            <button type="button" class="modal-close" data-close="modal-campanha-obs" aria-label="Fechar"><?= icon('x') ?></button>
        </div>
        <div class="modal-body">
            <p id="campanha-detalhe-obs-texto"></p>
        </div>
    </div>
</div>

<?php require BASE_PATH . '/pages/partials/app-foot.php'; ?>
