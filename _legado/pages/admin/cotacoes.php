<?php
/**
 * GET /admin/cotacoes
 * Visão global da MPS — todas as imobiliárias, com os mesmos filtros/colunas
 * do sistema antigo (produto, protocolo, corretor MPS, imobiliária, período,
 * ordenação e paginação), na estilização atual do sistema novo.
 */
$current_user = require_admin();

$atendentes = [];
try {
    $atendentes = db_fetch_all('SELECT id, username FROM usuario ORDER BY username ASC');
} catch (Throwable) {}
$opcoes_corretor = [];
foreach ($atendentes as $a) { $opcoes_corretor[(string)$a['id']] = $a['username']; }

// Operador restrito a só alguns produtos não vê os demais no filtro.
$produtos_permitidos = admin_produtos_permitidos($current_user);
$opcoes_produto_todas = [
    'fianca'      => 'Fiança',
    'auto'        => 'Seguro Auto',
    'titulo'      => 'Título de Capitalização',
    'condominial' => 'Seguro Condomínio',
    'incendio'    => 'Seguro Incêndio',
    'residencial' => 'Seguro Residencial',
];
$opcoes_produto = $produtos_permitidos === null
    ? $opcoes_produto_todas
    : array_intersect_key($opcoes_produto_todas, array_flip($produtos_permitidos));

// Vem do dashboard admin — os cards de KPI e o popup de "cotações em análise"
// linkam pra cá já com o filtro certo.
$status_inicial    = trim($_GET['status'] ?? '');
$data_de_inicial   = trim($_GET['data_de'] ?? '');
$corretor_inicial  = trim($_GET['corretor_id'] ?? '');
$status_ativo      = static fn (string $status) => $status_inicial === $status ? ' is-active' : '';

$page_title   = 'Todas as Cotações';
$page_heading = 'Todas as Cotações';
$page_back    = null;
$extra_css    = ['dashboard.css', 'cotacoes.css'];
$extra_js     = ['admin-cotacoes.js'];

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
                    <!-- Filtros por etapa — a mesma régua do painel da MPS, pra
                         atendimento manual e API juntos (admin_cotacao_etapa_painel). -->
                    <div class="status-kpi-grid">
                        <a href="#" class="status-kpi-card<?= $status_inicial === '' ? ' is-active' : '' ?>" data-status="">
                            <span class="status-kpi-icon"><?= icon('list') ?></span>
                            <span class="status-kpi-value" data-count="todos">0</span>
                            <span class="status-kpi-label">Todos</span>
                        </a>
                        <a href="#" class="status-kpi-card<?= $status_ativo('acao_mps') ?>" data-status="acao_mps">
                            <span class="status-kpi-icon"><?= icon('clock') ?></span>
                            <span class="status-kpi-value" data-count="acao_mps">0</span>
                            <span class="status-kpi-label">Aguardando a MPS</span>
                        </a>
                        <a href="#" class="status-kpi-card<?= $status_ativo('andamento') ?>" data-status="andamento">
                            <span class="status-kpi-icon"><?= icon('magnifying-glass') ?></span>
                            <span class="status-kpi-value" data-count="andamento">0</span>
                            <span class="status-kpi-label">Em andamento</span>
                        </a>
                        <a href="#" class="status-kpi-card<?= $status_ativo('com_cliente') ?>" data-status="com_cliente">
                            <span class="status-kpi-icon"><?= icon('paper-plane-tilt') ?></span>
                            <span class="status-kpi-value" data-count="com_cliente">0</span>
                            <span class="status-kpi-label">Com o cliente</span>
                        </a>
                        <a href="#" class="status-kpi-card<?= $status_ativo('concluidos') ?>" data-status="concluidos">
                            <span class="status-kpi-icon"><?= icon('check') ?></span>
                            <span class="status-kpi-value" data-count="concluidos">0</span>
                            <span class="status-kpi-label">Concluídas</span>
                        </a>
                        <a href="#" class="status-kpi-card<?= $status_ativo('reprovados') ?>" data-status="reprovados">
                            <span class="status-kpi-icon"><?= icon('x') ?></span>
                            <span class="status-kpi-value" data-count="reprovados">0</span>
                            <span class="status-kpi-label">Recusadas</span>
                        </a>
                        <a href="#" class="status-kpi-card<?= $status_ativo('desistencias') ?>" data-status="desistencias">
                            <span class="status-kpi-icon"><?= icon('sign-out') ?></span>
                            <span class="status-kpi-value" data-count="desistencias">0</span>
                            <span class="status-kpi-label">Desistências</span>
                        </a>
                    </div>

                    <div class="admin-cot-toolbar-actions">
                        <button type="button" id="btn-limpar" class="btn btn-secondary btn-sm" style="display:none">
                            Limpar filtros
                        </button>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="cotacoes-filtros admin-filtros cotacao-container">
                    <div class="field">
                        <label class="field-label">Produto</label>
                        <div class="field-input-wrap">
                            <span class="field-input-icon"><?= icon('clipboard-text') ?></span>
                            <?= custom_select('f_produto', $opcoes_produto, 'Todos', null, 'has-icon', false, ['id' => 'f-produto-wrap']) ?>
                        </div>
                    </div>
                    <div class="field">
                        <label class="field-label">Protocolo</label>
                        <div class="field-input-wrap">
                            <span class="field-input-icon"><?= icon('magnifying-glass') ?></span>
                            <input type="text" id="f-protocolo" class="field-input has-icon" placeholder="Protocolo">
                        </div>
                    </div>
                    <div class="field">
                        <label class="field-label">Corretor MPS</label>
                        <div class="field-input-wrap">
                            <span class="field-input-icon"><?= icon('headset') ?></span>
                            <?= custom_select('f_corretor', $opcoes_corretor, 'Todos', $corretor_inicial ?: null, 'has-icon', false, ['id' => 'f-corretor-wrap']) ?>
                        </div>
                    </div>
                    <div class="field field-search-wide">
                        <label class="field-label">Imobiliária, pretendente ou CPF/CNPJ</label>
                        <div class="field-input-wrap">
                            <span class="field-input-icon"><?= icon('magnifying-glass') ?></span>
                            <input type="text" id="f-busca" class="field-input has-icon" placeholder="Buscar por imobiliária, pretendente ou CPF/CNPJ">
                        </div>
                    </div>
                    <div class="field">
                        <label class="field-label">Data atualização (de / até)</label>
                        <div class="field-date-range">
                            <?= date_picker('f-data-de', 'DD/MM/AAAA', $data_de_inicial ?: null) ?>
                            <?= date_picker('f-data-ate') ?>
                        </div>
                    </div>
                </div>

            </div>

            <!-- Lista (grid de divs — sem table) -->
            <div class="panel cot-panel">
                <div class="admin-cot-grid-wrap">
                    <div class="admin-cot-row admin-cot-head">
                        <div class="admin-cot-cell">
                            <span class="th-produto-so">Produto</span>
                            <span class="th-produto-protocolo">Produto/Protocolo</span>
                        </div>
                        <div class="admin-cot-cell">Protocolo</div>
                        <div class="admin-cot-cell admin-cot-cell-nome">Pretendente / Segurado</div>
                        <div class="admin-cot-cell">Status</div>
                        <div class="admin-cot-cell">Imobiliária</div>
                        <div class="admin-cot-cell">Atendente</div>
                        <div class="admin-cot-cell th-sortable" data-sort="atualizacao">Datas <span class="sort-arrow">▼</span></div>
                        <div class="admin-cot-cell">Ações</div>
                    </div>
                    <div id="cotacoes-rows">
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
