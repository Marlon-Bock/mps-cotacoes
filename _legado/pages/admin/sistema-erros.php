<?php
/**
 * GET /admin/sistema-erros
 * Log central de erros do sistema (exceptions, erros PHP, fatais) — ver
 * src/core/error_logger.php. Acesso restrito de propósito: só o usuário
 * "Suporte Sami 2" enxerga essa tela (não tem item no menu). Qualquer outro
 * admin autenticado cai em /403, igual visitante sem permissão nenhuma.
 *
 * Filtros e ações são tudo via AJAX (ver admin-sistema-erros.js) — a URL
 * nunca muda, mesmo padrão do resto do painel admin (ex.: /admin/chamados).
 */
$current_user = require_admin();

if (($current_user->nome ?? '') !== 'Suporte Sami 2') {
    redirect('/403');
}

$page_title      = 'Erros do Sistema';
$page_heading    = 'Log de erros do sistema';
$page_subheading = 'Últimos 300 registros — exceptions, erros PHP e falhas fatais capturados automaticamente';
$page_back       = null;
$extra_css       = ['dashboard.css', 'cotacoes.css'];
$extra_js        = ['admin-sistema-erros.js'];

require BASE_PATH . '/pages/partials/app-head.php';
require BASE_PATH . '/pages/partials/nav-admin.php';
?>
<div class="app-shell">
    <?php require BASE_PATH . '/pages/partials/sidebar.php'; ?>
    <div class="app-main">
        <?php require BASE_PATH . '/pages/partials/topbar.php'; ?>
        <main class="app-content">

            <div class="panel cot-panel" style="margin-bottom:var(--space-4)">
                <div class="panel-body" style="display:flex; gap:var(--space-3); flex-wrap:wrap; align-items:center;">
                    <button type="button" id="btn-diagnostico-permissoes" class="btn btn-secondary btn-sm">
                        <span data-label><?= icon('check-circle') ?> Testar permissão de escrita agora</span>
                        <span class="spinner spinner-dark"></span>
                    </button>
                    <button type="button" id="btn-gerar-erro-teste" class="btn btn-secondary btn-sm">
                        <span data-label><?= icon('warning') ?> Gerar erro de teste (confirma o log inteiro)</span>
                        <span class="spinner spinner-dark"></span>
                    </button>
                    <span id="diagnostico-resultado" style="font-size:var(--font-size-sm)"></span>
                </div>
            </div>

            <div class="panel cot-panel" style="margin-bottom:var(--space-4)">
                <div class="panel-body" style="display:flex; gap:var(--space-3); flex-wrap:wrap; align-items:center;">
                    <button type="button" class="btn btn-sm btn-primary" data-filtro-status="pendentes">
                        Pendentes (<span data-count="pendentes">…</span>)
                    </button>
                    <button type="button" class="btn btn-sm btn-secondary" data-filtro-status="resolvidos">
                        Corrigidos (<span data-count="resolvidos">…</span>)
                    </button>
                    <button type="button" class="btn btn-sm btn-secondary" data-filtro-status="todos">
                        Todos (<span data-count="todos">…</span>)
                    </button>
                    <span style="width:1px; height:20px; background:var(--color-gray-200)"></span>
                    <button type="button" class="btn btn-sm btn-primary" data-filtro-tipo="">
                        Qualquer tipo
                    </button>
                    <button type="button" class="btn btn-sm btn-secondary" data-filtro-tipo="exception">
                        Exceptions (<span data-count="exception">…</span>)
                    </button>
                    <button type="button" class="btn btn-sm btn-secondary" data-filtro-tipo="error">
                        Erros PHP (<span data-count="error">…</span>)
                    </button>
                    <button type="button" class="btn btn-sm btn-secondary" data-filtro-tipo="fatal">
                        Fatais (<span data-count="fatal">…</span>)
                    </button>
                </div>
            </div>

            <div class="panel cot-panel">
                <div class="admin-cot-grid-wrap">
                    <div class="admin-cot-row admin-cot-head" style="grid-template-columns: 150px 100px 1fr 220px 110px 170px; min-width: 1200px;">
                        <div class="admin-cot-cell">Data/hora</div>
                        <div class="admin-cot-cell">Tipo</div>
                        <div class="admin-cot-cell">Mensagem</div>
                        <div class="admin-cot-cell">Onde</div>
                        <div class="admin-cot-cell">Contexto</div>
                        <div class="admin-cot-cell">Status</div>
                    </div>

                    <div id="sistema-erros-rows">
                        <div class="admin-cot-empty">Carregando...</div>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>
<?php require BASE_PATH . '/pages/partials/app-foot.php'; ?>
