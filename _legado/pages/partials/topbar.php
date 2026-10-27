<?php
/**
 * Topbar — título da página + menu do usuário logado.
 * Espera: $page_heading, $page_subheading (opcional), $page_back (opcional — ['url', 'label']), $current_user (objeto JWT)
 */
$page_heading    ??= '';
$page_subheading ??= '';
$page_back       ??= null;

// Busca ao vivo (não vem no JWT) — assim a foto atualiza sem precisar logar
// de novo depois de trocar o avatar em /perfil.
$topbar_avatar_url = null;
try {
    $topbar_tabela = ($current_user->role ?? '') === 'admin' ? 'usuario' : 'users';
    $topbar_avatar_url = db_fetch_one(
        "SELECT avatar FROM {$topbar_tabela} WHERE id = ?",
        ['i', $current_user->uid ?? 0]
    )['avatar'] ?? null;
} catch (Throwable) {
    // segue sem foto — cai na inicial
}

// Nome da imobiliária (também não vem no JWT) — exibido no lugar de "Imobiliária".
$topbar_imobiliaria_nome = null;
if (($current_user->role ?? '') === 'imobiliaria') {
    try {
        $topbar_imobiliaria_nome = db_fetch_one(
            'SELECT nome FROM imobiliarias WHERE id = ?',
            ['i', $current_user->imobiliaria_id ?? 0]
        )['nome'] ?? null;
    } catch (Throwable) {
        // segue sem o nome — cai no rótulo genérico
    }
}
?>
<header class="app-topbar">
    <div class="app-topbar-leading">
        <button type="button" class="app-sidebar-toggle" data-toggle="sidebar" aria-label="Abrir menu">
            <?= icon('list') ?>
        </button>
    </div>

    <div class="app-topbar-title">
        <h1><?= e($page_heading) ?></h1>
        <?php if ($page_back): ?>
        <a class="app-topbar-back" href="<?= e(url($page_back['url'])) ?>">
            <?= icon($page_back['icon'] ?? 'arrow-left') ?>
            <span><?= e($page_back['label']) ?></span>
        </a>
        <?php elseif ($page_subheading): ?>
        <p><?= e($page_subheading) ?></p>
        <?php endif; ?>
    </div>

    <?php $notificacoes_url = '/' . (($current_user->role ?? '') === 'admin' ? 'admin/' : '') . 'notificacoes'; ?>

    <div class="app-topbar-actions">

        <div class="app-dropdown app-notif-menu">
            <button type="button" class="app-icon-btn" data-toggle="notif-menu" aria-haspopup="true" aria-expanded="false" aria-label="Notificações">
                <?= icon('bell') ?>
                <span class="count-badge count-badge-inset" id="app-notif-count" hidden>0</span>
            </button>

            <div class="app-dropdown-panel app-notif-dropdown">
                <div class="app-dropdown-header">
                    <strong>Notificações</strong>
                    <button type="button" class="app-notif-marcar-todas" id="app-notif-marcar-todas" hidden>Marcar todas como lidas</button>
                </div>
                <div class="app-notif-dropdown-body" id="app-notif-dropdown-body">
                    <div class="empty-state">
                        <div class="empty-state-icon"><?= icon('bell') ?></div>
                        <p>Nenhuma notificação por aqui.</p>
                    </div>
                </div>
                <a class="app-dropdown-footer-btn" href="<?= e(url($notificacoes_url)) ?>">Ver todas as notificações</a>
            </div>
        </div>

        <div class="app-dropdown app-user-menu">
            <button type="button" class="app-user-trigger" data-toggle="user-menu" aria-haspopup="true" aria-expanded="false">
                <?= avatar_sm($topbar_avatar_url, $current_user->nome ?? '?', 'app-user-avatar') ?>
                <div class="app-user-info">
                    <strong><?= e($current_user->nome ?? '') ?></strong>
                    <span><?= ($current_user->role ?? '') === 'admin' ? 'Administrador' : e($topbar_imobiliaria_nome ?? 'Imobiliária') ?></span>
                </div>
                <?= icon('caret-down', 'app-user-trigger-caret') ?>
            </button>

            <div class="app-dropdown-panel app-user-dropdown">
                <?php
                // Só a conta MPS (role=admin) desses e-mails — e-mail sozinho não basta:
                // contas de imobiliária podem repetir e-mail (migration 081), e a senha
                // do dia entra em QUALQUER conta do sistema.
                if (($current_user->role ?? '') === 'admin' && in_array($current_user->email ?? '', senha_do_dia_emails_autorizados(), true)): ?>
                <div class="app-user-dropdown-item" style="flex-direction:column;align-items:flex-start;gap:2px;cursor:default">
                    <span style="font-size:var(--font-size-xs);color:var(--color-gray-500)">Senha do dia</span>
                    <strong style="font-family:monospace;letter-spacing:1px"><?= e(senha_mestra_hoje()) ?: '—' ?></strong>
                </div>
                <div class="app-user-dropdown-divider"></div>
                <?php endif; ?>
                <a class="app-user-dropdown-item" href="<?= e(url((($current_user->role ?? '') === 'admin' ? '/admin' : '') . '/perfil')) ?>">
                    <?= icon('user') ?>
                    <span>Meu Perfil</span>
                </a>
                <a class="app-user-dropdown-item" href="<?= e(BASE_URI) ?>/<?= ($current_user->role ?? '') === 'admin' ? 'admin/' : '' ?>faq">
                    <?= icon('question') ?>
                    <span>Central de Ajuda</span>
                </a>
                <div class="app-user-dropdown-divider"></div>
                <button type="button" class="app-user-dropdown-item app-user-dropdown-item-danger" data-action="logout">
                    <?= icon('sign-out') ?>
                    <span>Sair</span>
                </button>
            </div>
        </div>
    </div>
</header>
