<?php
$current_user = require_perm('perm_cotar', 'perm_titulo');

// Carrega rascunho salvo, se informado na URL
$rascunho_protocolo = trim($_GET['rascunho'] ?? '');
$rascunho_dados     = null;
if ($rascunho_protocolo !== '') {
    $rascunho_dados = db_fetch_one(
        'SELECT * FROM seguro_titulo_rascunho WHERE protocolo = ? AND imobiliaria_id = ?',
        ['si', $rascunho_protocolo, $current_user->imobiliaria_id]
    );
    if (!$rascunho_dados) $rascunho_protocolo = '';
}

$page_title   = 'Título de Capitalização — Nova Cotação';
$page_heading = 'Título de Capitalização';
$page_back    = ['url' => '/cotacoes', 'label' => 'Minhas Cotações', 'icon' => 'arrow-left'];
$extra_css    = ['cotacao.css'];
$extra_js     = ['rascunho.js', 'cotacao-titulo.js'];

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

<?php if ($rascunho_dados): ?>
<script>window.__RASCUNHO_DADOS = <?= json_encode($rascunho_dados) ?>;</script>
<?php endif; ?>

<?php
$campanha_popup_tipo = 'titulo';
require __DIR__ . '/../../partials/campanha-popup.php';
$extra_js[] = 'campanha-popup.js';
?>

<?php require __DIR__ . '/../../partials/app-foot.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('form-cotacao-titulo');
    var btn  = document.getElementById('btn-salvar-rascunho');
    if (window.__RASCUNHO_DADOS) RascunhoCotacao.preencher(form, window.__RASCUNHO_DADOS);
    RascunhoCotacao.ligarBotaoSalvar(btn, form, 'titulo', btn.dataset.csrf);
});
</script>
