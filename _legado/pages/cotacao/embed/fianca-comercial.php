<?php
/**
 * Nova cotação via embed — Seguro Fiança Comercial — GET /embed/{embed_token}/fianca/comercial
 * Sem login: a imobiliária é resolvida pelo embed_token (ver src/core/embed.php).
 */

$embed_token  = $params['embed_token'];
$current_user = embed_resolver($embed_token);
$imob         = $current_user->_imobiliaria;

embed_apply_frame_headers($imob['embed_dominio'] ?? null);

$imob_cliente_sami = (int) ($imob['eh_cliente_sami'] ?? 0) === 1 && !empty($imob['sigla_erp']);
$ocultar_rascunho  = true; // embed é anônimo — sem sessão de imobiliária para salvar rascunhos

$tipo_ficha   = 'comercial';
$page_title   = 'Fiança Comercial — Nova Cotação';
$extra_css    = ['cotacao.css'];
$extra_js     = ['cotacao-fianca.js'];

require BASE_PATH . '/pages/partials/embed-head.php';
?>
<div class="cotacao-container">
    <section class="panel">
        <div class="panel-body">
            <?php require BASE_PATH . '/pages/cotacao/fianca/_form.php'; ?>
        </div>
    </section>
</div>
<?php require BASE_PATH . '/pages/partials/embed-foot.php'; ?>
