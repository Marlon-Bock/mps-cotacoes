<?php
/**
 * Placeholder para produtos de seguro ainda não disponíveis (Incêndio, Condominial).
 * Espera (opcional via query string): ?produto=Seguro+Incêndio
 */

$current_user = require_auth();

$produtos_por_rota = [
    '/cotacao/capitalizacao' => 'Título de Capitalização',
];
$rota_atual = request_path();
$produto = $produtos_por_rota[$rota_atual] ?? 'Este produto';

$page_title      = $produto;
$page_heading     = $produto;
$page_subheading  = 'Em breve disponível para cotação.';
$extra_css = ['dashboard.css'];

require __DIR__ . '/../partials/app-head.php';
require __DIR__ . '/../partials/nav-imobiliaria.php';
?>

<div class="app-shell">
    <?php require __DIR__ . '/../partials/sidebar.php'; ?>

    <div class="app-main">
        <?php require __DIR__ . '/../partials/topbar.php'; ?>

        <main class="app-content">
            <div class="panel">
                <div class="panel-body">
                    <div class="empty-state">
                        <div class="empty-state-icon"><?= icon('clock') ?></div>
                        <p><?= e($produto) ?> ainda não está disponível para cotação. Estamos trabalhando para trazer esse produto em breve.</p>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php require __DIR__ . '/../partials/app-foot.php'; ?>
