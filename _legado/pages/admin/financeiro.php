<?php
/**
 * GET /admin/financeiro
 * Financeiro (só MPS): contratações feitas pela API das seguradoras
 * (Pottencial, Too) — prêmio, IOF, comissão da corretora e a situação da
 * apólice. Mesmo padrão da "Todas as Cotações" (e do Financeiro do
 * dev-cotacoes-2): filtros aplicam sozinhos (AJAX — admin-financeiro.js +
 * /api/admin/financeiro/listar), "Limpar filtros" e lista em grid.
 * Cálculo em src/helpers/api/financeiro.php.
 */
$current_user = require_admin();

require_once BASE_PATH . '/src/helpers/api.php';
require_once BASE_PATH . '/src/helpers/api/financeiro.php';

// Opções dos filtros: seguradoras e imobiliárias que já têm contratação.
$opcoes_seguradora = [];
foreach (db_fetch_all('SELECT DISTINCT seguradora FROM api_cotacoes WHERE apolice_id IS NOT NULL ORDER BY seguradora') as $s) {
    $opcoes_seguradora[(string) $s['seguradora']] = api_seguradora_rotulo((string) $s['seguradora']);
}
$opcoes_imobiliaria = [];
foreach (db_fetch_all(
    'SELECT DISTINCT i.id, i.nome FROM api_cotacoes c
     JOIN seguro_fianca f ON f.id = c.seguro_fianca_id JOIN imobiliarias i ON i.id = f.imobiliaria_id
     WHERE c.apolice_id IS NOT NULL ORDER BY i.nome'
) as $i) {
    $opcoes_imobiliaria[(string) $i['id']] = (string) $i['nome'];
}

$page_title      = 'Financeiro';
$page_heading    = 'Financeiro';
$page_subheading = 'Contratações pela API das seguradoras — prêmios, comissões e apólices';
$page_back       = null;
$extra_css       = ['dashboard.css', 'cotacoes.css', 'admin-financeiro.css'];
$extra_js        = ['admin-financeiro.js'];

require BASE_PATH . '/pages/partials/app-head.php';
require BASE_PATH . '/pages/partials/nav-admin.php';
?>
<div class="app-shell">
    <?php require BASE_PATH . '/pages/partials/sidebar.php'; ?>
    <div class="app-main">
        <?php require BASE_PATH . '/pages/partials/topbar.php'; ?>
        <main class="app-content">

            <!-- Filtros — aplicam sozinhos (AJAX) -->
            <div class="fin-barra">
                <div class="cotacoes-filtros admin-filtros">
                    <div class="field fin-campo-busca">
                        <label class="field-label">Buscar</label>
                        <div class="field-input-wrap">
                            <span class="field-input-icon"><?= icon('magnifying-glass') ?></span>
                            <input type="text" id="f-busca" class="field-input has-icon" placeholder="Protocolo, pretendente ou apólice">
                        </div>
                    </div>
                    <div class="field">
                        <label class="field-label">Contratação (de / até)</label>
                        <div class="field-date-range">
                            <?= date_picker('f-data-de') ?>
                            <?= date_picker('f-data-ate') ?>
                        </div>
                    </div>
                    <div class="field">
                        <label class="field-label">Seguradora</label>
                        <div class="field-input-wrap" id="f-seguradora-wrap">
                            <span class="field-input-icon"><?= icon('shield-check') ?></span>
                            <?= custom_select('f_seguradora', $opcoes_seguradora, 'Todas', null, 'has-icon') ?>
                        </div>
                    </div>
                    <div class="field">
                        <label class="field-label">Imobiliária</label>
                        <div class="field-input-wrap" id="f-imobiliaria-wrap">
                            <span class="field-input-icon"><?= icon('buildings') ?></span>
                            <?= custom_select('f_imobiliaria', $opcoes_imobiliaria, 'Todas', null, 'has-icon', false, ['data-busca' => '']) ?>
                        </div>
                    </div>
                    <div class="field">
                        <label class="field-label">Situação da apólice</label>
                        <div class="field-input-wrap" id="f-situacao-wrap">
                            <span class="field-input-icon"><?= icon('file-pdf') ?></span>
                            <?= custom_select('f_situacao', API_FINANCEIRO_SITUACOES, 'Todas', null, 'has-icon') ?>
                        </div>
                    </div>
                </div>
                <div class="fin-barra-acoes">
                    <button type="button" id="btn-limpar" class="btn btn-secondary btn-sm" style="display:none">
                        <?= icon('x') ?> Limpar filtros
                    </button>
                    <a href="<?= e(url('/admin/financeiro/pdf')) ?>" id="btn-pdf" class="btn btn-secondary btn-sm" target="_blank" rel="noopener">
                        <?= icon('file-pdf') ?> Relatório PDF
                    </a>
                    <a href="<?= e(url('/admin/financeiro/exportar')) ?>" id="btn-exportar" class="btn btn-secondary btn-sm">
                        <?= icon('download') ?> Exportar Excel
                    </a>
                </div>
            </div>

            <!-- Resumo do período (AJAX) -->
            <div class="panel fin-resumo" id="fin-resumo"></div>

            <!-- Por seguradora (AJAX) -->
            <div class="panel" id="fin-seguradoras-painel" hidden>
                <div class="panel-header"><h2>Por seguradora</h2></div>
                <div id="fin-seguradoras"></div>
            </div>

            <!-- Contratações (grid de divs — sem table) -->
            <div class="panel cot-panel">
                <div class="panel-header">
                    <h2>Contratações</h2>
                    <span class="fin-contagem" id="fin-contagem"></span>
                </div>
                <div class="admin-cot-grid-wrap">
                    <div class="admin-cot-row admin-cot-row-fin admin-cot-head">
                        <div class="admin-cot-cell">Contratação</div>
                        <div class="admin-cot-cell">Cotação</div>
                        <div class="admin-cot-cell">Imobiliária</div>
                        <div class="admin-cot-cell">Seguradora / apólice</div>
                        <div class="admin-cot-cell fin-num">Prêmio</div>
                        <div class="admin-cot-cell">Pagamento</div>
                        <div class="admin-cot-cell fin-num">Prêmio líquido</div>
                        <div class="admin-cot-cell fin-num">Comissão</div>
                        <div class="admin-cot-cell">Situação</div>
                    </div>
                    <div id="fin-rows">
                        <div class="admin-cot-empty">Carregando...</div>
                    </div>
                </div>
                <div class="admin-cot-pagination">
                    <span class="admin-cot-pagination-info fin-nota"><?= icon('info') ?> Comissão da Pottencial informada por ela na cotação; da Too, o percentual configurado sobre o prêmio líquido. As seguradoras não informam a situação do pagamento.</span>
                </div>
            </div>

        </main>
    </div>
</div>
<?php require BASE_PATH . '/pages/partials/app-foot.php'; ?>
