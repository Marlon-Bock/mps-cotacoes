<?php
/**
 * GET /admin/chamados
 * Lista todos os chamados de suporte de todas as imobiliárias.
 * Filtros e paginação via AJAX (/api/admin/chamados/listar), a URL nunca muda.
 */
$current_user = require_admin();

$imobiliarias = [];
try {
    $imobiliarias = db_fetch_all('SELECT id, nome FROM imobiliarias ORDER BY nome ASC');
} catch (\Throwable $e) {}
$opcoes_imob = [];
foreach ($imobiliarias as $imob) { $opcoes_imob[(string) $imob['id']] = $imob['nome']; }

$atendentes = [];
try {
    $atendentes = db_fetch_all('SELECT id, username FROM usuario ORDER BY username ASC');
} catch (\Throwable $e) {}
$opcoes_atendente = [];
foreach ($atendentes as $a) { $opcoes_atendente[(string) $a['id']] = $a['username']; }

$page_title      = 'Chamados de Suporte';
$page_heading    = 'Chamados';
$page_subheading = 'Todos os tickets abertos pelas imobiliárias';
$page_back       = null;
$extra_css       = ['dashboard.css', 'cotacoes.css'];
$extra_js        = ['admin-chamados-listar.js'];

require BASE_PATH . '/pages/partials/app-head.php';
require BASE_PATH . '/pages/partials/nav-admin.php';
?>
<div class="app-shell">
    <?php require BASE_PATH . '/pages/partials/sidebar.php'; ?>
    <div class="app-main">
        <?php require BASE_PATH . '/pages/partials/topbar.php'; ?>
        <main class="app-content">

            <div class="admin-cot-filterbar">
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
                            <span class="status-kpi-label">Aguardando Imobiliária</span>
                        </a>
                        <a href="#" class="status-kpi-card" data-status="encerrado">
                            <span class="status-kpi-icon"><?= icon('x') ?></span>
                            <span class="status-kpi-value" id="count-encerrado">0</span>
                            <span class="status-kpi-label">Encerrados</span>
                        </a>
                    </div>

                    <div class="admin-cot-toolbar-actions">
                        <button type="button" id="btn-limpar" class="btn btn-secondary btn-sm" style="display:none">
                            Limpar filtros
                        </button>
                    </div>
                </div>

                <div class="cotacoes-filtros admin-filtros cotacao-container">
                    <div class="field">
                        <label class="field-label">Assunto / Título</label>
                        <div class="field-input-wrap">
                            <span class="field-input-icon"><?= icon('magnifying-glass') ?></span>
                            <input type="text" id="f-assunto" class="field-input has-icon" placeholder="Buscar por assunto ou título">
                        </div>
                    </div>
                    <div class="field">
                        <label class="field-label">Imobiliária</label>
                        <div class="field-input-wrap">
                            <span class="field-input-icon"><?= icon('buildings') ?></span>
                            <?= custom_select('f_imobiliaria', $opcoes_imob, 'Todas', null, 'has-icon', false, ['id' => 'f-imobiliaria-wrap']) ?>
                        </div>
                    </div>
                    <div class="field">
                        <label class="field-label">Atendente</label>
                        <div class="field-input-wrap">
                            <span class="field-input-icon"><?= icon('headset') ?></span>
                            <?= custom_select('f_atendente', $opcoes_atendente, 'Todos', null, 'has-icon', false, ['id' => 'f-atendente-wrap']) ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="panel cot-panel">
                <div class="admin-cot-grid-wrap">
                    <div class="admin-cot-row admin-cot-row-chamados-admin admin-cot-head">
                        <div class="admin-cot-cell">Protocolo</div>
                        <div class="admin-cot-cell admin-cot-cell-nome">Título</div>
                        <div class="admin-cot-cell">Imobiliária</div>
                        <div class="admin-cot-cell">Atendente</div>
                        <div class="admin-cot-cell">Status</div>
                        <div class="admin-cot-cell">Atualizado em</div>
                        <div class="admin-cot-cell">Ações</div>
                    </div>
                    <div id="chamados-rows">
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
