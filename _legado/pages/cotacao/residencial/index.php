<?php
/**
 * GET /cotacao/residencial — nova cotação de Seguro Residencial.
 * Envio em includes/acao/cotacao-residencial.php (cota na API das
 * seguradoras quando alguma está ligada; senão vai pra fila da MPS).
 */
$current_user = require_perm('perm_cotar', 'perm_seguro_residencial');

$page_title   = 'Seguro Residencial — Nova Cotação';
$page_heading = 'Seguro Residencial';
$page_back    = ['url' => '/cotacoes', 'label' => 'Minhas Cotações', 'icon' => 'arrow-left'];
$extra_css    = ['cotacao.css'];
$extra_js     = ['cotacao-residencial.js'];

require __DIR__ . '/../../partials/app-head.php';
require __DIR__ . '/../../partials/nav-imobiliaria.php';
?>
<div class="app-shell">
    <?php require __DIR__ . '/../../partials/sidebar.php'; ?>
    <div class="app-main">
        <?php require __DIR__ . '/../../partials/topbar.php'; ?>
        <main class="app-content">
            <div class="cotacao-container">
                <section class="panel">
                    <div class="panel-body">
                        <?php require __DIR__ . '/_form.php'; ?>
                    </div>
                </section>
            </div>
        </main>
    </div>
</div>

<?php require __DIR__ . '/../../partials/app-foot.php'; ?>
