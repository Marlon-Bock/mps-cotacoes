<?php
/**
 * GET /admin/cotacao/fianca/{protocolo}/seguradoras
 * GET /admin/cotacao/residencial/{protocolo}/seguradoras
 * Tela de seguradoras da Fiança e do Seguro Residencial (só MPS): resultado de cada seguradora,
 * reanálise e, com a análise concluída, o "Fazer cotação" (envia as
 * propostas ao cliente). Conteúdo em pages/cotacao/_partials/seguradoras-conteudo.php.
 */
$current_user = require_admin();

$protocolo = $params['protocolo'] ?? '';
if (!$protocolo) redirect('/admin/cotacoes');

$__resolvido = cotacao_resolver($protocolo);
if (!$__resolvido || !in_array($__resolvido['tipo'], ['fianca', 'residencial'], true)) redirect('/admin/cotacoes');
$ficha = $__resolvido['row'] + ['produto' => $__resolvido['tipo']];

$is_admin = true;

$page_title      = 'Seguradoras — ' . $protocolo;
require_once BASE_PATH . '/src/helpers/api.php';
$__lote          = api_status_lote([(int) $ficha['id']])[(int) $ficha['id']];
$page_heading    = $ficha['status'] === API_STATUS_FICHA && in_array(api_etapa($__lote['cotacoes'], $__lote['fila'], $__lote['propostas']), [...API_ETAPAS_COTAR, 'proposta'], true)
    ? 'Fazer cotação'
    : 'Seguradoras cotadas';
$page_subheading = 'Cotação ' . $protocolo;
$page_back       = ['url' => '/admin/cotacao/' . $ficha['produto'] . '/' . $protocolo, 'label' => 'Voltar pra cotação', 'icon' => 'arrow-left'];
$extra_css       = ['dashboard.css', 'cotacao.css', 'cotacao-detalhe.css', 'cotacao-fianca-seguradoras.css', 'cotacao-fianca-fluxo.css'];
$extra_js        = [];

require BASE_PATH . '/pages/partials/app-head.php';
require BASE_PATH . '/pages/partials/nav-admin.php';
?>
<div class="app-shell">
    <?php require BASE_PATH . '/pages/partials/sidebar.php'; ?>
    <div class="app-main">
        <?php require BASE_PATH . '/pages/partials/topbar.php'; ?>
        <main class="app-content">
            <div class="cotacao-container">
                <?php require BASE_PATH . '/pages/cotacao/_partials/seguradoras-conteudo.php'; ?>
            </div>
        </main>
    </div>
</div>
<?php require BASE_PATH . '/pages/partials/app-foot.php'; ?>
