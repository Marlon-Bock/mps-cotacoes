<?php
/**
 * GET /chamados
 * Lista todos os chamados de suporte da imobiliária logada.
 */
$current_user = require_perm('perm_suporte');

$page_title      = 'Chamados de Suporte';
$page_heading    = 'Chamados de Suporte';
$page_subheading = 'Acompanhe suas solicitações de atendimento';
$page_back       = null;
$extra_css       = ['dashboard.css', 'cotacoes.css', 'chamados.css'];
$extra_js        = ['chamados.js'];

require BASE_PATH . '/pages/partials/app-head.php';
require BASE_PATH . '/pages/partials/nav-imobiliaria.php';
?>
<div class="app-shell">
    <?php require BASE_PATH . '/pages/partials/sidebar.php'; ?>
    <div class="app-main">
        <?php require BASE_PATH . '/pages/partials/topbar.php'; ?>
        <main class="app-content">

            <div class="admin-cot-filterbar">

                <!-- Status — KPI cards clicáveis -->
                <div class="admin-cot-toolbar">
                    <div class="status-kpi-grid">
                        <a href="#" class="status-kpi-card is-active" data-status="">
                            <span class="status-kpi-icon"><?= icon('list') ?></span>
                            <span class="status-kpi-value" id="count-total">0</span>
                            <span class="status-kpi-label">Todos</span>
                        </a>
                        <a href="#" class="status-kpi-card" data-status="aguardando_mps">
                            <span class="status-kpi-icon"><?= icon('clock') ?></span>
                            <span class="status-kpi-value" id="count-aguardando-mps">0</span>
                            <span class="status-kpi-label">Aguardando MPS</span>
                        </a>
                        <a href="#" class="status-kpi-card" data-status="aguardando_imob">
                            <span class="status-kpi-icon"><?= icon('headset') ?></span>
                            <span class="status-kpi-value" id="count-aguardando-imob">0</span>
                            <span class="status-kpi-label">Aguardando Você</span>
                        </a>
                        <a href="#" class="status-kpi-card" data-status="encerrado">
                            <span class="status-kpi-icon"><?= icon('x') ?></span>
                            <span class="status-kpi-value" id="count-encerrado">0</span>
                            <span class="status-kpi-label">Encerrados</span>
                        </a>
                    </div>

                    <div class="admin-cot-toolbar-actions">
                        <button type="button" id="btn-limpar-chamados" class="btn btn-secondary btn-sm" style="display:none">
                            Limpar filtros
                        </button>
                        <a href="<?= e(url('/chamados/novo')) ?>" class="btn btn-primary btn-sm">
                            <?= icon('note-pencil') ?> Abrir novo chamado
                        </a>
                    </div>
                </div>

                <!-- Filtro de busca por assunto/título -->
                <div class="cotacoes-filtros admin-filtros cotacao-container">
                    <div class="field">
                        <label class="field-label">Assunto / Título</label>
                        <div class="field-input-wrap">
                            <span class="field-input-icon"><?= icon('magnifying-glass') ?></span>
                            <input type="text" id="f-assunto" class="field-input has-icon" placeholder="Buscar por assunto ou título">
                        </div>
                    </div>
                </div>

            </div>

            <!-- Lista de chamados (AJAX) -->
            <div class="panel cot-panel">
                <div class="admin-cot-grid-wrap">
                    <div class="admin-cot-row admin-cot-row-chamados admin-cot-head">
                        <div class="admin-cot-cell">Protocolo</div>
                        <div class="admin-cot-cell admin-cot-cell-nome">Título</div>
                        <div class="admin-cot-cell">Assunto</div>
                        <div class="admin-cot-cell">Status</div>
                        <div class="admin-cot-cell">Atendente</div>
                        <div class="admin-cot-cell">Atualizado em</div>
                        <div class="admin-cot-cell">Ações</div>
                    </div>
                    <div id="chamados-lista">
                        <div class="admin-cot-empty">Carregando...</div>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>
<?php require BASE_PATH . '/pages/partials/app-foot.php'; ?>
