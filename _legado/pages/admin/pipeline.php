<?php
/**
 * GET /admin/pipeline
 * Pipeline/funil Kanban das cotações — visualização por coluna de status.
 * Filtros (data, imobiliária, atendente) são aplicados via AJAX
 * (/api/admin/pipeline/listar) — a URL nunca muda, mesmo padrão de /admin/cotacoes.
 */
$current_user = require_admin();

$data_inicio_padrao = date('Y-m-d', strtotime('-6 months'));
$data_fim_padrao    = date('Y-m-d');

// ── Listas para selects de filtro ──────────────────────────────────────────
$imobiliarias = [];
try {
    $imobiliarias = db_fetch_all('SELECT id, nome FROM imobiliarias ORDER BY nome ASC');
} catch (Throwable) {}

$atendentes = [];
try {
    $atendentes = db_fetch_all('SELECT id, username FROM usuario ORDER BY username ASC');
} catch (Throwable) {}

$opcoes_imob  = [];
foreach ($imobiliarias as $i) { $opcoes_imob[(string)$i['id']] = $i['nome']; }

$opcoes_atend = [];
foreach ($atendentes as $a) { $opcoes_atend[(string)$a['id']] = $a['username']; }

// ── Colunas do Kanban (só pra render do esqueleto — o conteúdo vem via AJAX;
//    mesmos 6 estágios das KPIs de /admin/cotacoes) ──────────────────────────
$colunas = [
    'pre_analise' => ['label' => 'Pré-análise',        'icon' => 'lock'],
    'aguardando'  => ['label' => 'Aguardando Análise',  'icon' => 'clock'],
    'em_analise'  => ['label' => 'Em Análise',          'icon' => 'magnifying-glass'],
    'aprovados'   => ['label' => 'Aprovados',           'icon' => 'check-circle'],
    'recusados'   => ['label' => 'Recusados',           'icon' => 'x'],
    'concluidos'  => ['label' => 'Concluídas',          'icon' => 'check'],
];

$page_title      = 'Pipeline de Cotações';
$page_heading    = 'Pipeline';
$page_subheading = 'Visualização kanban das cotações por etapa.';
$page_back       = null;
$extra_css       = ['dashboard.css', 'cotacoes.css'];
$extra_js        = ['admin-pipeline.js'];

require BASE_PATH . '/pages/partials/app-head.php';
require BASE_PATH . '/pages/partials/nav-admin.php';
?>
<div class="app-shell">
    <?php require BASE_PATH . '/pages/partials/sidebar.php'; ?>
    <div class="app-main">
        <?php require BASE_PATH . '/pages/partials/topbar.php'; ?>
        <main class="app-content">

            <!-- ── Barra de filtros — mesmo padrão visual da listagem de
                 cotações (AJAX, nunca navega) ─────────────────────────── -->
            <div class="cotacoes-filtros admin-filtros cotacao-container">
                <div class="field">
                    <label class="field-label">Imobiliária</label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon"><?= icon('buildings') ?></span>
                        <?= custom_select(
                            'imobiliaria_id',
                            $opcoes_imob,
                            'Todas',
                            null,
                            'has-icon'
                        ) ?>
                    </div>
                </div>

                <div class="field">
                    <label class="field-label">Atendente</label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon"><?= icon('headset') ?></span>
                        <?= custom_select(
                            'atendente_id',
                            $opcoes_atend,
                            'Todos',
                            null,
                            'has-icon'
                        ) ?>
                    </div>
                </div>

                <div class="field">
                    <label class="field-label">Data de cadastro (de / até)</label>
                    <div class="field-date-range">
                        <?= date_picker('pipeline-data-inicio', 'DD/MM/AAAA', $data_inicio_padrao) ?>
                        <?= date_picker('pipeline-data-fim', 'DD/MM/AAAA', $data_fim_padrao) ?>
                    </div>
                </div>

                <div class="pipeline-filterbar-actions">
                    <button type="button" id="btn-limpar-pipeline" class="btn btn-secondary btn-sm" hidden>
                        Limpar filtros
                    </button>
                </div>
            </div>

            <!-- ── Kanban (preenchido via AJAX) ────────────────────────────── -->
            <div class="pipeline-kanban" id="pipeline-kanban">
                <?php foreach ($colunas as $key => $col): ?>
                <div class="pipeline-col pipeline-col--<?= e($key) ?>">
                    <div class="pipeline-col-header">
                        <span class="pipeline-col-icon"><?= icon($col['icon']) ?></span>
                        <span class="pipeline-col-label"><?= e($col['label']) ?></span>
                        <span class="pipeline-col-count">0</span>
                    </div>
                    <div class="pipeline-col-body">
                        <div class="empty-state pipeline-empty">
                            <p>Carregando...</p>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

        </main>
    </div>
</div>

<?php require BASE_PATH . '/pages/partials/app-foot.php'; ?>
