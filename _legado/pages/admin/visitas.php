<?php
/**
 * GET /admin/visitas
 * Histórico de Visitas — lista somente-leitura e paginada do que já foi
 * registrado no Relatório de Visitas, com filtros por período, corretor e
 * imobiliária. Filtros e paginação são tudo via AJAX
 * (/api/admin/visitas-historico/*), a URL nunca muda.
 */
$current_user = require_admin();

$corretores = [];
try {
    $corretores = db_fetch_all('SELECT id, username FROM usuario ORDER BY username ASC');
} catch (Throwable) {}

$opcoes_corretor = [];
foreach ($corretores as $c) {
    $opcoes_corretor[(string) $c['id']] = $c['username'];
}

$page_title      = 'Histórico de Visitas';
$page_heading    = 'Histórico de Visitas';
$page_subheading = 'Todas as visitas registradas pelos atendentes.';
$page_back       = null;
$extra_css       = ['dashboard.css', 'cotacoes.css', 'visitas.css', 'tarefas.css'];
$extra_js        = ['admin-visitas-historico.js'];

require BASE_PATH . '/pages/partials/app-head.php';
require BASE_PATH . '/pages/partials/nav-admin.php';
?>
<div class="app-shell">
    <?php require BASE_PATH . '/pages/partials/sidebar.php'; ?>
    <div class="app-main">
        <?php require BASE_PATH . '/pages/partials/topbar.php'; ?>
        <main class="app-content">

            <div class="cotacoes-filtros admin-filtros cotacao-container">
                <div class="field">
                    <label class="field-label">Corretor</label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon"><?= icon('headset') ?></span>
                        <?= custom_select('corretor_id', $opcoes_corretor, 'Todos os corretores', null, 'has-icon') ?>
                    </div>
                </div>

                <div class="field">
                    <label class="field-label">Imobiliária</label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon"><?= icon('buildings') ?></span>
                        <input type="text" id="f-imobiliaria" class="field-input has-icon" placeholder="Buscar por nome...">
                    </div>
                </div>

                <div class="field">
                    <label class="field-label">Data (de / até)</label>
                    <div class="field-date-range">
                        <?= date_picker('f-data-de') ?>
                        <?= date_picker('f-data-ate') ?>
                    </div>
                </div>
            </div>

            <div class="panel cot-panel">
                <div class="panel-header">
                    <h2>Visitas <span class="badge badge-primary" id="historico-total-badge" hidden>0</span></h2>
                </div>
                <div id="historico-lista">
                    <div class="admin-cot-empty">Carregando...</div>
                </div>
                <div class="admin-cot-pagination" id="historico-pagination" hidden>
                    <span id="historico-pag-info" class="admin-cot-pagination-info"></span>
                    <div class="admin-cot-pagination-btns">
                        <button type="button" id="historico-pag-anterior" class="btn btn-secondary btn-sm"><?= icon('arrow-left') ?> Anterior</button>
                        <button type="button" id="historico-pag-proxima" class="btn btn-secondary btn-sm">Próxima <?= icon('arrow-right') ?></button>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>
<?php require BASE_PATH . '/pages/partials/app-foot.php'; ?>
