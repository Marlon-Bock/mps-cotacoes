<?php
/**
 * Nova cotação — Seguro Fiança Comercial — GET /cotacao/fianca/comercial
 */

$current_user = require_perm('perm_cotar', 'perm_fianca');

$_cotacao_protocolo_get = trim($_GET['cotacao'] ?? '');

// Carrega dados da pré-análise, se informado na URL
$pre_analise_dados = null;
if ($_cotacao_protocolo_get !== '') {
    $pre_analise_dados = db_fetch_one(
        'SELECT * FROM seguro_fianca WHERE protocolo = ? AND imobiliaria_id = ?',
        ['si', $_cotacao_protocolo_get, $current_user->imobiliaria_id]
    );
    if (!$pre_analise_dados) {
        $_cotacao_protocolo_get = '';
    } else {
        foreach (['data_nascimento', 'p2_data_nascimento', 'p3_data_nascimento'] as $campo_data) {
            if (!empty($pre_analise_dados[$campo_data])) {
                $pre_analise_dados[$campo_data] = date_to_br($pre_analise_dados[$campo_data]);
            }
        }
    }
}

// Carrega rascunho salvo, se informado na URL
$rascunho_protocolo = trim($_GET['rascunho'] ?? '');
$rascunho_dados     = null;
if ($rascunho_protocolo !== '') {
    $rascunho_dados = db_fetch_one(
        'SELECT * FROM seguro_fianca_rascunho WHERE protocolo = ? AND imobiliaria_id = ?',
        ['si', $rascunho_protocolo, $current_user->imobiliaria_id]
    );
    if (!$rascunho_dados) {
        $rascunho_protocolo = '';
    } elseif (!empty($rascunho_dados['data_nascimento'])) {
        $rascunho_dados['data_nascimento'] = date_to_br($rascunho_dados['data_nascimento']);
    }
}

// Cliente Sami: habilita a busca de imóvel por código (integração ERP)
$_imob_sami = db_fetch_one(
    'SELECT eh_cliente_sami, sigla_erp FROM imobiliarias WHERE id = ?',
    ['i', $current_user->imobiliaria_id]
);
$imob_cliente_sami = $_imob_sami
    && (int) $_imob_sami['eh_cliente_sami'] === 1
    && !empty($_imob_sami['sigla_erp']);

$tipo_ficha      = 'comercial';
$page_title      = 'Fiança Comercial — Nova Cotação';
$page_heading    = 'Seguro Fiança Comercial';
$page_subheading = '';
$page_back       = ['url' => '/cotacoes', 'label' => 'Minhas Cotações', 'icon' => 'arrow-left'];
$extra_css       = ['cotacao.css'];
$extra_js        = ['rascunho.js', 'cotacao-fianca.js'];

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
                        <?php if ($_cotacao_protocolo_get !== ''): ?>
                        <input type="hidden" name="cotacao" value="<?= e($_cotacao_protocolo_get) ?>" id="field_cotacao" form="form-fianca">
                        <?php endif; ?>
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

<?php if ($pre_analise_dados): ?>
<script>window.__PRE_ANALISE_DADOS = <?= json_encode($pre_analise_dados) ?>;</script>
<?php endif; ?>

<?php
$campanha_popup_tipo = 'comercial';
require __DIR__ . '/../../partials/campanha-popup.php';
$extra_js[] = 'campanha-popup.js';
?>

<?php require __DIR__ . '/../../partials/app-foot.php'; ?>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('form-fianca');
    var btn  = document.getElementById('btn-salvar-rascunho');
    if (window.__PRE_ANALISE_DADOS) RascunhoCotacao.preencher(form, window.__PRE_ANALISE_DADOS);
    if (window.__RASCUNHO_DADOS) RascunhoCotacao.preencher(form, window.__RASCUNHO_DADOS);
    RascunhoCotacao.ligarBotaoSalvar(btn, form, 'fianca', btn.dataset.csrf);
});
</script>
