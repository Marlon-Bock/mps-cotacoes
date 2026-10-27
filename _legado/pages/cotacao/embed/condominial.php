<?php
/**
 * Nova cotação via embed — Seguro Condominial — GET /embed/{embed_token}/condominial
 * Sem login: a imobiliária é resolvida pelo embed_token (ver src/core/embed.php).
 */

$embed_token  = $params['embed_token'];
$current_user = embed_resolver($embed_token);
$imob         = $current_user->_imobiliaria;

embed_apply_frame_headers($imob['embed_dominio'] ?? null);

$page_title = 'Seguro Condominial — Nova Cotação';
$extra_css  = ['cotacao.css'];
$extra_js   = ['cotacao-condominial.js'];

require BASE_PATH . '/pages/partials/embed-head.php';
?>
<div class="cotacao-container">
    <section class="panel">
        <div class="panel-body">
            <?php require BASE_PATH . '/pages/cotacao/condominial/_form.php'; ?>
        </div>
    </section>
</div>
<?php require BASE_PATH . '/pages/partials/embed-foot.php'; ?>
