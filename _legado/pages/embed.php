<?php
/**
 * GET /embed
 * Página da própria imobiliária com os códigos de <iframe> prontos para
 * colar no sistema/site dela. A imobiliária ativa o embed sozinha, aqui
 * mesmo, informando a URL do seu site (ver includes/acao/embed-ativar.php)
 * — só o admin da imobiliária (imob_admin) pode ativar/editar.
 */
$current_user = require_auth();

if (!FEATURE_EMBED_UI) {
    redirect('/');
}

if (($current_user->role ?? '') !== 'imobiliaria') {
    redirect('/');
}

$is_imob_admin = (bool) ($current_user->imob_admin ?? false);

$imob = db_fetch_one(
    'SELECT usa_embed, embed_token, embed_dominio FROM imobiliarias WHERE id = ?',
    ['i', $current_user->imobiliaria_id]
);

$configurado = (bool) ($imob['usa_embed'] ?? false) && !empty($imob['embed_token']) && !empty($imob['embed_dominio']);

if (!$configurado && !$is_imob_admin) {
    redirect('/');
}

$embed_token = $imob['embed_token'] ?? '';

$fluxos = [
    ['label' => 'Seguro Fiança — Residencial', 'icone' => 'house',      'path' => "/embed/{$embed_token}/fianca/residencial"],
    ['label' => 'Seguro Fiança — Comercial',   'icone' => 'briefcase',  'path' => "/embed/{$embed_token}/fianca/comercial"],
    ['label' => 'Seguro Auto',                 'icone' => 'car',        'path' => "/embed/{$embed_token}/auto"],
    ['label' => 'Seguro Condominial',          'icone' => 'buildings', 'path' => "/embed/{$embed_token}/condominial"],
    ['label' => 'Título de Capitalização',     'icone' => 'piggy-bank', 'path' => "/embed/{$embed_token}/titulo"],
];

$page_title      = 'Embed de Cotações';
$page_heading    = 'Embed de Cotações';
$page_subheading = 'Cole estes códigos no seu site ou sistema para abrir cotações sem sair dele';
$page_back       = null;
$extra_css       = ['dashboard.css', 'embed.css'];
$extra_js        = ['embed-config.js'];

require BASE_PATH . '/pages/partials/app-head.php';
require BASE_PATH . '/pages/partials/nav-imobiliaria.php';
?>
<div class="app-shell">
    <?php require BASE_PATH . '/pages/partials/sidebar.php'; ?>
    <div class="app-main">
        <?php require BASE_PATH . '/pages/partials/topbar.php'; ?>
        <main class="app-content">

            <?php if ($is_imob_admin): ?>
            <div class="panel embed-config">
                <div class="panel-body">
                    <div class="embed-config-header">
                        <label class="switch">
                            <input type="checkbox" id="embed-switch" <?= $configurado ? 'checked' : '' ?>>
                            <span class="switch-track"></span>
                            <span class="switch-label">Ativar embed de cotações no meu site</span>
                        </label>
                        <span class="badge <?= $configurado ? 'badge-primary' : '' ?>" id="embed-status-badge">
                            <?= $configurado ? 'Ativo' : 'Inativo' ?>
                        </span>
                    </div>
                    <p class="embed-config-desc">
                        Assim que o domínio for validado, os códigos de <code>&lt;iframe&gt;</code> aparecem abaixo,
                        prontos para colar no seu site ou sistema. Por segurança, o embed só carrega quando aberto
                        dentro desse domínio — em qualquer outro endereço, o navegador bloqueia o carregamento.
                    </p>

                    <div class="embed-domain-view <?= $configurado ? '' : 'is-hidden' ?>" id="embed-domain-view">
                        <span class="embed-domain-icon"><?= icon('monitor') ?></span>
                        <span class="embed-domain-text" id="embed-domain-text"><?= e($imob['embed_dominio'] ?? '') ?></span>
                        <div class="embed-domain-actions">
                            <button type="button" class="btn btn-secondary btn-sm" id="embed-editar-btn">
                                <?= icon('note-pencil') ?> Editar
                            </button>
                            <button type="button" class="btn btn-danger-outline btn-sm" id="embed-excluir-btn">
                                <?= icon('trash') ?> Excluir
                            </button>
                        </div>
                    </div>

                    <div class="embed-url-block" id="embed-url-block">
                        <div>
                            <form id="embed-ativar-form" class="embed-url-row">
                                <div class="field">
                                    <label class="field-label" for="embed-url">URL do seu site</label>
                                    <input type="text" id="embed-url" class="field-input" placeholder="https://www.seusite.com.br"
                                           value="<?= e($imob['embed_dominio'] ?? '') ?>">
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm" id="embed-ativar-btn">
                                    <span data-label><?= icon('check') ?> <?= $configurado ? 'Salvar domínio' : 'Validar e ativar' ?></span>
                                    <span class="spinner"></span>
                                </button>
                                <?php if ($configurado): ?>
                                <button type="button" class="btn btn-secondary btn-sm" id="embed-cancelar-btn">Cancelar</button>
                                <?php endif; ?>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!$configurado): ?>
                <?php if (!$is_imob_admin): ?>
                <div class="panel">
                    <div class="panel-body">
                        <p class="field-hint">
                            <?= icon('warning') ?>
                            O embed ainda não foi ativado para a sua imobiliária — peça ao administrador da conta
                            para ativá-lo em <?= e(url('/embed')) ?>.
                        </p>
                    </div>
                </div>
                <?php endif; ?>
            <?php else: ?>
            <p class="field-hint embed-grid-hint">
                <?= icon('info') ?>
                O formulário se ajusta à largura do espaço reservado a ele; para o melhor layout, reserve pelo
                menos <strong>960px</strong> de largura no local onde for colar o código.
            </p>
            <div class="embed-grid">
                <?php foreach ($fluxos as $fluxo):
                    $url_completa = APP_URL . url($fluxo['path']);
                    $snippet = '<iframe src="' . $url_completa . '" style="width:100%;max-width:1100px;height:1000px;border:0" title="' . $fluxo['label'] . '"></iframe>';
                ?>
                <div class="embed-card">
                    <div class="embed-card-title">
                        <span class="embed-card-icon"><?= icon($fluxo['icone']) ?></span>
                        <h3><?= e($fluxo['label']) ?></h3>
                    </div>
                    <div class="embed-code"><?= e($snippet) ?></div>
                    <textarea class="embed-snippet-value embed-snippet" readonly tabindex="-1" aria-hidden="true"><?= e($snippet) ?></textarea>
                    <div class="embed-card-footer">
                        <button type="button" class="btn btn-secondary btn-sm embed-copiar-btn" data-copiar>
                            <?= icon('clipboard-text') ?> Copiar código
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

        </main>
    </div>
</div>
<?php require BASE_PATH . '/pages/partials/app-foot.php'; ?>
