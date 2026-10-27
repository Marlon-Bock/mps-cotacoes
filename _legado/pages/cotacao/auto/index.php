<?php
$current_user = require_perm('perm_cotar', 'perm_auto');

// Carrega rascunho salvo, se informado na URL
$rascunho_protocolo = trim($_GET['rascunho'] ?? '');
$rascunho_dados     = null;
if ($rascunho_protocolo !== '') {
    $rascunho_dados = db_fetch_one(
        'SELECT * FROM seguro_auto_rascunho WHERE protocolo = ? AND imobiliaria_id = ?',
        ['si', $rascunho_protocolo, $current_user->imobiliaria_id]
    );
    if (!$rascunho_dados) {
        $rascunho_protocolo = '';
    } else {
        foreach (['segurado_data_nasc', 'condutor_data_nasc'] as $campo_data) {
            if (!empty($rascunho_dados[$campo_data])) {
                $rascunho_dados[$campo_data] = date_to_br($rascunho_dados[$campo_data]);
            }
        }
    }
}

$page_title   = 'Seguro Auto — Nova Cotação';
$page_heading = 'Seguro Auto';
$page_back    = ['url' => '/cotacoes', 'label' => 'Minhas Cotações', 'icon' => 'arrow-left'];
$extra_css    = ['cotacao.css'];
$extra_js     = ['rascunho.js', 'cotacao-auto.js'];

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
$campanha_popup_tipo = 'auto';
require __DIR__ . '/../../partials/campanha-popup.php';
$extra_js[] = 'campanha-popup.js';
?>

<?php require __DIR__ . '/../../partials/app-foot.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('form-cotacao-auto');
    var btn  = document.getElementById('btn-salvar-rascunho');
    RascunhoCotacao.ligarBotaoSalvar(btn, form, 'auto', btn.dataset.csrf);

    if (window.__RASCUNHO_DADOS) {
        var dados = window.__RASCUNHO_DADOS;
        var fipeKeys = ['tipo_veiculo', 'marca_id', 'modelo_id', 'ano_id'];
        var resto = {};
        Object.keys(dados).forEach(function (k) {
            if (fipeKeys.indexOf(k) === -1) resto[k] = dados[k];
        });
        RascunhoCotacao.preencher(form, resto);
        RascunhoCotacao.preencherFipeCascata(form, dados);
    }
});
</script>
