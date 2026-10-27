<?php
/**
 * GET /admin/faq/atendimentos
 * Quem usou o chat guiado do FAQ, o que informou (nome/telefone/imobiliária,
 * quando o fluxo pede), o caminho de opções percorrido e se abriu chamado.
 * Filtros e paginação via AJAX (/api/admin/faq-atendimentos/listar), a URL
 * nunca muda.
 */
$current_user = require_admin();

$imobiliarias = [];
try {
    $imobiliarias = db_fetch_all('SELECT id, nome FROM imobiliarias ORDER BY nome ASC');
} catch (\Throwable $e) {}
$opcoes_imob = [];
foreach ($imobiliarias as $imob) { $opcoes_imob[(string) $imob['id']] = $imob['nome']; }

$page_title      = 'Atendimentos do Chat';
$page_heading    = 'Atendimentos do Chat';
$page_subheading = 'Quem usou o chat guiado do FAQ e o que percorreu';
$page_back       = ['url' => '/admin/faq/fluxo', 'label' => 'Fluxo do FAQ'];
$extra_css       = ['dashboard.css', 'cotacoes.css'];
$extra_js        = ['admin-faq-atendimentos-listar.js'];

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
                        <a href="#" class="status-kpi-card" data-status="em_andamento">
                            <span class="status-kpi-icon"><?= icon('clock') ?></span>
                            <span class="status-kpi-value" id="count-em-andamento">0</span>
                            <span class="status-kpi-label">Em andamento</span>
                        </a>
                        <a href="#" class="status-kpi-card" data-status="concluido">
                            <span class="status-kpi-icon"><?= icon('check-circle') ?></span>
                            <span class="status-kpi-value" id="count-concluido">0</span>
                            <span class="status-kpi-label">Concluídos</span>
                        </a>
                        <a href="#" class="status-kpi-card" data-status="chamado_aberto">
                            <span class="status-kpi-icon"><?= icon('ticket') ?></span>
                            <span class="status-kpi-value" id="count-chamado-aberto">0</span>
                            <span class="status-kpi-label">Abriram chamado</span>
                        </a>
                    </div>

                    <div class="admin-cot-toolbar-actions">
                        <button type="button" id="btn-limpar" class="btn btn-secondary btn-sm" hidden>
                            Limpar filtros
                        </button>
                    </div>
                </div>

                <div class="cotacoes-filtros admin-filtros cotacao-container">
                    <div class="field">
                        <label class="field-label">Nome / Telefone / Usuário</label>
                        <div class="field-input-wrap">
                            <span class="field-input-icon"><?= icon('magnifying-glass') ?></span>
                            <input type="text" id="f-busca" class="field-input has-icon" placeholder="Buscar atendimento">
                        </div>
                    </div>
                    <div class="field">
                        <label class="field-label">Imobiliária</label>
                        <div class="field-input-wrap">
                            <span class="field-input-icon"><?= icon('buildings') ?></span>
                            <?= custom_select('f_imobiliaria', $opcoes_imob, 'Todas', null, 'has-icon', false, ['id' => 'f-imobiliaria-wrap']) ?>
                        </div>
                    </div>
                </div>
            </div>

            <div class="panel cot-panel">
                <div class="admin-cot-grid-wrap">
                    <div class="admin-cot-row admin-cot-row-faq-atendimentos admin-cot-head">
                        <div class="admin-cot-cell">Usuário</div>
                        <div class="admin-cot-cell">Dados informados</div>
                        <div class="admin-cot-cell admin-cot-cell-nome">Caminho percorrido</div>
                        <div class="admin-cot-cell">Status</div>
                        <div class="admin-cot-cell">Atualizado em</div>
                    </div>
                    <div id="atendimentos-rows">
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
