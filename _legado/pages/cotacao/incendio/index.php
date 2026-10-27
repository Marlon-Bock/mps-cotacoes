<?php
/**
 * GET+POST /cotacao/incendio
 * Formulário de nova cotação de Seguro Incêndio — 1 protocolo pode conter
 * N imóveis (ver _form.php e cotacao-incendio.js pro bloco repetível).
 */
$current_user = require_perm('perm_cotar', 'perm_incendio');

// Cliente Sami: habilita a busca de imóvel por código (integração ERP) em cada bloco de imóvel.
$_imob_sami = db_fetch_one(
    'SELECT eh_cliente_sami, sigla_erp FROM imobiliarias WHERE id = ?',
    ['i', $current_user->imobiliaria_id]
);
$imob_cliente_sami = $_imob_sami
    && (int) $_imob_sami['eh_cliente_sami'] === 1
    && !empty($_imob_sami['sigla_erp']);

$page_title   = 'Seguro Incêndio — Nova Cotação';
$page_heading = 'Seguro Incêndio';
$page_back    = ['url' => '/cotacoes', 'label' => 'Minhas Cotações', 'icon' => 'arrow-left'];
$extra_css    = ['cotacao.css'];
$extra_js     = ['cotacao-incendio.js'];

require __DIR__ . '/../../partials/app-head.php';
require __DIR__ . '/../../partials/nav-imobiliaria.php';
?>
<div class="app-shell">
    <?php require __DIR__ . '/../../partials/sidebar.php'; ?>
    <div class="app-main">
        <?php require __DIR__ . '/../../partials/topbar.php'; ?>
        <main class="app-content">
            <div class="cotacao-container">
                <?php require __DIR__ . '/_form.php'; ?>
            </div>
        </main>
    </div>
</div>
<?php require __DIR__ . '/../../partials/app-foot.php'; ?>
