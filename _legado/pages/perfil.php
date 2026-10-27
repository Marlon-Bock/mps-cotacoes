<?php
/**
 * GET /perfil
 * Tela de perfil — comum a imobiliária e admin (MPS). A tabela usada
 * depende do `role` do JWT: 'imobiliaria' -> users, 'admin' -> usuario.
 */
$current_user = require_auth();
$tabela = $current_user->role === 'admin' ? 'usuario' : 'users';

// email_cotacao só existe na tabela `usuario` (equipe MPS) — não em `users` (imobiliária).
$campos = 'id, username, email, telefone, avatar, last_login_at' . ($tabela === 'usuario' ? ', email_cotacao' : '');
$usuario = db_fetch_one("SELECT {$campos} FROM {$tabela} WHERE id = ?", ['i', $current_user->uid]);
if (!$usuario) {
    redirect('/sessao-expirada');
}

// ── Últimos acessos ────────────────────────────────────────────────────────
$ip_atual = $_SERVER['REMOTE_ADDR'] ?? '—';
$ua_atual = $_SERVER['HTTP_USER_AGENT'] ?? '';

$historico_acessos = db_fetch_all(
    'SELECT ip, user_agent, criado_em FROM login_historico
     WHERE tabela_origem = ? AND usuario_id = ? ORDER BY criado_em DESC LIMIT 6',
    ['si', $tabela, $current_user->uid]
);
foreach ($historico_acessos as &$acesso) {
    $device = ua_parse($acesso['user_agent']);
    $acesso['navegador']   = $device['navegador'];
    $acesso['dispositivo'] = $device['dispositivo'];
    $acesso['tempo']       = tempo_decorrido($acesso['criado_em']);
    $acesso['e_atual']     = $acesso['ip'] === $ip_atual && $acesso['user_agent'] === $ua_atual;
}
unset($acesso);

$sessao_atual = ua_parse($ua_atual);

$page_title      = 'Meu Perfil';
$page_heading    = 'Meu Perfil';
$page_subheading = 'Gerencie seus dados pessoais e sua senha de acesso';
$page_back       = null;
$extra_css       = ['cotacao.css', 'perfil.css'];
$extra_js        = ['perfil.js'];

require BASE_PATH . '/pages/partials/app-head.php';
require BASE_PATH . '/pages/partials/' . ($current_user->role === 'admin' ? 'nav-admin.php' : 'nav-imobiliaria.php');
?>
<div class="app-shell">
    <?php require BASE_PATH . '/pages/partials/sidebar.php'; ?>
    <div class="app-main">
        <?php require BASE_PATH . '/pages/partials/topbar.php'; ?>
        <main class="app-content">

            <form id="form-perfil" class="cotacao-container" data-csrf="<?= e(csrf_token()) ?>" novalidate>

                <div class="form-layout-70-30">
                    <div class="form-layout-main">

                        <div class="panel">
                            <div class="panel-body">

                                <div class="alert alert-danger" data-form-alert hidden></div>

                                <div class="form-section">
                                    <h3 class="form-section-title"><?= icon('identification-card') ?> Dados pessoais</h3>
                                    <div class="form-grid">

                                        <div class="field">
                                            <label class="field-label" for="username">
                                                Nome <span class="required">*</span>
                                                <span class="field-error" data-error-for="username"></span>
                                            </label>
                                            <div class="field-input-wrap">
                                                <span class="field-input-icon"><?= icon('user') ?></span>
                                                <input class="field-input has-icon" type="text" id="username" name="username"
                                                       value="<?= e($usuario['username']) ?>" required>
                                            </div>
                                        </div>

                                        <div class="field">
                                            <label class="field-label" for="email">
                                                E-mail <span class="required">*</span>
                                                <span class="field-error" data-error-for="email"></span>
                                            </label>
                                            <div class="field-input-wrap">
                                                <span class="field-input-icon"><?= icon('envelope-simple') ?></span>
                                                <input class="field-input has-icon" type="email" id="email" name="email"
                                                       value="<?= e($usuario['email']) ?>" required>
                                            </div>
                                        </div>

                                        <div class="field span-full">
                                            <label class="field-label" for="telefone">
                                                Telefone
                                                <span class="field-error" data-error-for="telefone"></span>
                                            </label>
                                            <div class="field-input-wrap">
                                                <span class="field-input-icon"><?= icon('phone') ?></span>
                                                <input class="field-input has-icon" type="text" id="telefone" name="telefone"
                                                       data-mask="phone" value="<?= e($usuario['telefone'] ?? '') ?>" placeholder="(00) 00000-0000">
                                            </div>
                                        </div>

                                        <?php if ($tabela === 'usuario'): ?>
                                        <div class="field span-full">
                                            <label class="field-label" for="email_cotacao">
                                                E-mail para receber cotações (opcional)
                                                <span class="field-error" data-error-for="email_cotacao"></span>
                                            </label>
                                            <div class="field-input-wrap">
                                                <span class="field-input-icon"><?= icon('envelope-simple') ?></span>
                                                <input class="field-input has-icon" type="email" id="email_cotacao" name="email_cotacao"
                                                       value="<?= e($usuario['email_cotacao'] ?? '') ?>" placeholder="Se vazio, usaremos o e-mail de login">
                                            </div>
                                            <span class="field-hint">Se preenchido, este será usado para alertas de novas cotações.</span>
                                        </div>
                                        <?php endif; ?>

                                    </div>
                                </div>

                                <div class="form-section no-margin-bottom">
                                    <h3 class="form-section-title"><?= icon('lock') ?> Alterar senha</h3>
                                    <p class="form-section-hint">Deixe em branco para manter a senha atual.</p>
                                    <div class="form-grid">

                                        <div class="field">
                                            <label class="field-label" for="senha_atual">
                                                Senha atual
                                                <span class="field-error" data-error-for="senha_atual"></span>
                                            </label>
                                            <div class="field-input-wrap">
                                                <span class="field-input-icon"><?= icon('lock') ?></span>
                                                <input class="field-input has-icon has-icon-both" type="password" id="senha_atual" name="senha_atual"
                                                       placeholder="••••••••" autocomplete="current-password">
                                                <button type="button" class="field-password-toggle" data-toggle="password" data-target="#senha_atual"
                                                        aria-label="Mostrar senha">
                                                    <?= icon('eye', 'icon-eye') ?>
                                                    <?= icon('eye-slash', 'icon-eye-slash') ?>
                                                </button>
                                            </div>
                                        </div>

                                        <div class="field">
                                            <label class="field-label" for="nova_senha">
                                                Nova senha
                                                <span class="field-error" data-error-for="nova_senha"></span>
                                            </label>
                                            <div class="field-input-wrap">
                                                <span class="field-input-icon"><?= icon('key') ?></span>
                                                <input class="field-input has-icon has-icon-both" type="password" id="nova_senha" name="nova_senha"
                                                       placeholder="••••••••" minlength="6" autocomplete="new-password">
                                                <button type="button" class="field-password-toggle" data-toggle="password" data-target="#nova_senha"
                                                        aria-label="Mostrar senha">
                                                    <?= icon('eye', 'icon-eye') ?>
                                                    <?= icon('eye-slash', 'icon-eye-slash') ?>
                                                </button>
                                            </div>
                                            <span class="field-hint">Mínimo 6 caracteres</span>
                                        </div>

                                    </div>
                                </div>

                                <div class="stepper-nav">
                                    <div></div>
                                    <div class="stepper-nav-right">
                                        <button type="submit" class="btn btn-sm btn-primary">
                                            <span data-label><?= icon('check') ?> Salvar alterações</span>
                                            <span class="spinner"></span>
                                        </button>
                                    </div>
                                </div>

                            </div>
                        </div>

                    </div>

                    <div class="form-layout-main">

                        <!-- Avatar -->
                        <div class="panel">
                            <div class="panel-header">
                                <h2><?= icon('user') ?> Foto de perfil</h2>
                            </div>
                            <div class="panel-body perfil-avatar-panel">
                                <div class="perfil-avatar-wrap">
                                    <?php if (!empty($usuario['avatar'])): ?>
                                        <img src="<?= e($usuario['avatar']) ?>" alt="Avatar" class="perfil-avatar-img" id="perfil-avatar-img">
                                    <?php else: ?>
                                        <div class="perfil-avatar-placeholder" id="perfil-avatar-placeholder">
                                            <?= e(mb_strtoupper(mb_substr($usuario['username'], 0, 1))) ?>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <input type="file" id="perfil-avatar-input" accept="image/png,image/jpeg,image/webp" hidden>
                                <button type="button" class="btn btn-secondary btn-sm" id="btn-trocar-avatar">
                                    <?= icon('user') ?> Trocar foto
                                </button>
                                <p class="perfil-avatar-hint">PNG, JPEG ou WEBP — até 2MB.</p>
                            </div>
                        </div>

                        <!-- Sessões ativas -->
                        <div class="panel">
                            <div class="panel-header">
                                <h2><?= icon($sessao_atual['dispositivo'] === 'Celular' ? 'device-mobile' : 'monitor') ?> Sessões ativas</h2>
                            </div>
                            <div class="panel-body p-0">
                                <div class="acesso-item acesso-item-atual">
                                    <span class="acesso-icon"><?= icon($sessao_atual['dispositivo'] === 'Celular' ? 'device-mobile' : 'monitor') ?></span>
                                    <div class="acesso-info">
                                        <span class="acesso-tempo">Esta sessão</span>
                                        <span class="acesso-meta"><?= e($sessao_atual['navegador']) ?> · Agora mesmo · <?= e($ip_atual) ?></span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Últimos acessos -->
                        <div class="panel">
                            <div class="panel-header">
                                <h2><?= icon('arrows-clockwise') ?> Últimos acessos</h2>
                            </div>
                            <div class="panel-body p-0 acessos-list">
                                <?php if (!$historico_acessos): ?>
                                    <p class="acessos-empty">Nenhum acesso registrado ainda.</p>
                                <?php else: foreach ($historico_acessos as $acesso): ?>
                                    <div class="acesso-item">
                                        <span class="acesso-icon"><?= icon($acesso['dispositivo'] === 'Celular' ? 'device-mobile' : 'monitor') ?></span>
                                        <div class="acesso-info">
                                            <span class="acesso-tempo"><?= e(ucfirst($acesso['tempo'])) ?></span>
                                            <span class="acesso-meta"><?= e($acesso['navegador']) ?> · <?= e($acesso['dispositivo']) ?> · <?= e($acesso['ip']) ?></span>
                                        </div>
                                        <?php if ($acesso['e_atual']): ?>
                                            <span class="badge-atual">Atual</span>
                                        <?php endif; ?>
                                    </div>
                                <?php endforeach; endif; ?>
                            </div>
                        </div>

                    </div>

                </div>
            </form>

        </main>
    </div>
</div>
<?php require BASE_PATH . '/pages/partials/app-foot.php'; ?>
