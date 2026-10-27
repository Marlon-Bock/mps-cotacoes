<?php
/**
 * GET /admin/comissoes
 * Configuração de percentuais de comissão por imobiliária e tipo de seguro.
 */
$current_user = require_admin();

$imobiliarias = [];
try {
    $imobiliarias = db_fetch_all(
        'SELECT i.id, i.nome,
                MAX(CASE WHEN c.tipo_seguro = "fianca_residencial" THEN c.percentual END) AS pct_fianca_res,
                MAX(CASE WHEN c.tipo_seguro = "fianca_comercial"   THEN c.percentual END) AS pct_fianca_com,
                MAX(CASE WHEN c.tipo_seguro = "auto"               THEN c.percentual END) AS pct_auto
         FROM imobiliarias i
         LEFT JOIN comissoes_imobiliarias c ON c.imobiliaria_id = i.id
         GROUP BY i.id ORDER BY i.nome ASC'
    );
} catch (Exception $e) {
    $imobiliarias = [];
}

$page_title      = 'Comissões';
$page_heading    = 'Comissões';
$page_subheading = 'Percentuais por imobiliária e tipo de seguro';
$page_back       = null;
$extra_css       = ['dashboard.css', 'cotacoes.css'];
$extra_js        = [];

require BASE_PATH . '/pages/partials/app-head.php';
require BASE_PATH . '/pages/partials/nav-admin.php';
?>
<div class="app-shell">
    <?php require BASE_PATH . '/pages/partials/sidebar.php'; ?>
    <div class="app-main">
        <?php require BASE_PATH . '/pages/partials/topbar.php'; ?>
        <main class="app-content">

            <div class="alert alert-info">
                <span class="alert-icon"><?= icon('info') ?></span>
                Os percentuais de comissão são aplicados sobre o prêmio do seguro. Clique em Editar para configurar uma imobiliária.
            </div>

            <div class="panel">
                <div class="panel-header">
                    <h2>Imobiliárias</h2>
                </div>
                <div class="panel-body">
                    <?php if (empty($imobiliarias)): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon"><?= icon('buildings') ?></div>
                            <p>Nenhuma imobiliária cadastrada.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-scroll">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Imobiliária</th>
                                        <th>Fiança Residencial (%)</th>
                                        <th>Fiança Comercial (%)</th>
                                        <th>Seguro Auto (%)</th>
                                        <th>Ações</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($imobiliarias as $imob): ?>
                                        <tr>
                                            <td><?= e($imob['nome']) ?></td>
                                            <td>
                                                <?= $imob['pct_fianca_res'] !== null
                                                    ? e(number_format((float)$imob['pct_fianca_res'], 2, ',', '.')) . '%'
                                                    : '—' ?>
                                            </td>
                                            <td>
                                                <?= $imob['pct_fianca_com'] !== null
                                                    ? e(number_format((float)$imob['pct_fianca_com'], 2, ',', '.')) . '%'
                                                    : '—' ?>
                                            </td>
                                            <td>
                                                <?= $imob['pct_auto'] !== null
                                                    ? e(number_format((float)$imob['pct_auto'], 2, ',', '.')) . '%'
                                                    : '—' ?>
                                            </td>
                                            <td>
                                                <button
                                                    class="btn btn-link btn-sm"
                                                    data-action="editar-comissao"
                                                    data-id="<?= e($imob['id']) ?>"
                                                >
                                                    <?= icon('note-pencil') ?> Editar
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </main>
    </div>
</div>
<?php require BASE_PATH . '/pages/partials/app-foot.php'; ?>
