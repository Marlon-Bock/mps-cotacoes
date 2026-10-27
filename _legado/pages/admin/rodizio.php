<?php
/**
 * GET /admin/rodizio
 * Configuração dos funis de rodízio de atendentes. Lista, adicionar,
 * remover, reordenar e ativar/desativar atendentes são tudo via AJAX
 * (/api/admin/rodizio/*) — a URL nunca muda.
 */

$current_user = require_admin();

$page_title      = 'Rodízio de Atendentes';
$page_heading    = 'Rodízio de Atendentes';
$page_subheading = 'Configuração dos funis de distribuição automática de cotações.';
$page_back       = null;
$extra_css       = ['dashboard.css', 'cotacoes.css'];
$extra_js        = ['admin-rodizio.js'];

require __DIR__ . '/../partials/app-head.php';
require __DIR__ . '/../partials/nav-admin.php';
?>

<div class="app-shell">
    <?php require __DIR__ . '/../partials/sidebar.php'; ?>
    <div class="app-main">
        <?php require __DIR__ . '/../partials/topbar.php'; ?>
        <main class="app-content">

            <div class="alert alert-info">
                <span class="alert-icon"><?= icon('info') ?></span>
                O rodízio distribui novas cotações automaticamente entre os atendentes ativos, em ordem circular.
                O próximo atendente a receber uma cotação é indicado pelo badge <strong>Próximo</strong> em cada funil.
            </div>

            <div id="rodizio-lista" class="rodizio-lista">
                <div class="panel">
                    <div class="panel-body">
                        <div class="empty-state">
                            <div class="empty-state-icon"><?= icon('arrows-clockwise') ?></div>
                            <p>Carregando...</p>
                        </div>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>

<?php require __DIR__ . '/../partials/app-foot.php'; ?>
