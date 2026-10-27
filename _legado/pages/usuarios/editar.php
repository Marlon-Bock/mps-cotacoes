<?php
/**
 * GET+POST /usuarios/{slug}/editar
 * Edita um usuário vinculado à imobiliária logada.
 */
$current_user = require_auth();

if (!($current_user->imob_admin ?? false)) {
    redirect('/403');
}

$slug_param = trim($params['slug'] ?? '');
if ($slug_param === '') redirect('/usuarios');

$usuario = db_fetch_one(
    'SELECT * FROM users WHERE slug = ? AND imobiliaria_id = ?',
    ['si', $slug_param, $current_user->imobiliaria_id]
);
if (!$usuario) redirect('/usuarios');

$id = (int) $usuario['id'];

$erros   = [];
$valores = [
    'username'             => $usuario['username'],
    'email'                => $usuario['email'],
    'telefone'             => $usuario['telefone'] ?? '',
    'admin'                => $usuario['admin'] === 'S',
    'perm_criar_usuarios'  => $usuario['perm_criar_usuarios'] === 'S',
    'perm_cotar'           => $usuario['perm_cotar'] === 'S',
    'perm_suporte'         => $usuario['perm_suporte'] === 'S',
    'perm_aceitar'         => $usuario['perm_aceitar'] === 'S',
    'perm_fianca'          => $usuario['perm_fianca'] === 'S',
    'perm_auto'            => $usuario['perm_auto'] === 'S',
    'perm_condo'           => $usuario['perm_condo'] === 'S',
    'perm_titulo'          => $usuario['perm_titulo'] === 'S',
    'perm_incendio'        => ($usuario['perm_incendio'] ?? 'N') === 'S',
    'perm_seguro_residencial'        => ($usuario['perm_seguro_residencial'] ?? 'N') === 'S',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $valores['username'] = trim($_POST['username'] ?? '');
    $valores['email']    = trim($_POST['email'] ?? '');
    $valores['telefone'] = trim($_POST['telefone'] ?? '');
    $senha               = (string) ($_POST['senha'] ?? '');
    $senha_confirmar     = (string) ($_POST['senha_confirmar'] ?? '');

    $valores['admin']               = ($_POST['admin'] ?? '') === 'S';
    // Admin da imobiliária tem acesso total — força todas as permissões.
    $valores['perm_criar_usuarios'] = $valores['admin'] || ($_POST['perm_criar_usuarios'] ?? '') === 'S';
    $valores['perm_cotar']          = $valores['admin'] || ($_POST['perm_cotar'] ?? '') === 'S';
    $valores['perm_suporte']        = $valores['admin'] || ($_POST['perm_suporte'] ?? '') === 'S';
    $valores['perm_aceitar']        = $valores['admin'] || ($_POST['perm_aceitar'] ?? '') === 'S';
    // Acesso a cada produto só faz sentido se a pessoa pode cotar.
    $valores['perm_fianca']         = $valores['perm_cotar'] && ($valores['admin'] || ($_POST['perm_fianca'] ?? '') === 'S');
    $valores['perm_auto']           = $valores['perm_cotar'] && ($valores['admin'] || ($_POST['perm_auto'] ?? '') === 'S');
    $valores['perm_condo']          = $valores['perm_cotar'] && ($valores['admin'] || ($_POST['perm_condo'] ?? '') === 'S');
    $valores['perm_titulo']         = $valores['perm_cotar'] && ($valores['admin'] || ($_POST['perm_titulo'] ?? '') === 'S');
    $valores['perm_incendio']       = $valores['perm_cotar'] && ($valores['admin'] || ($_POST['perm_incendio'] ?? '') === 'S');
    $valores['perm_seguro_residencial']       = $valores['perm_cotar'] && ($valores['admin'] || ($_POST['perm_seguro_residencial'] ?? '') === 'S');

    if ($valores['username'] === '') $erros['username'] = 'Nome é obrigatório.';
    if ($valores['email'] === '' || !filter_var($valores['email'], FILTER_VALIDATE_EMAIL))
        $erros['email'] = 'Informe um e-mail válido.';
    if ($senha !== '' && !senha_valida($senha)) $erros['senha'] = 'A senha deve ter no mínimo 6 caracteres, com 1 maiúscula e 1 número.';
    if ($senha !== '' && $senha !== $senha_confirmar) $erros['senha_confirmar'] = 'As senhas não coincidem.';

    if (empty($erros) && $valores['email'] !== $usuario['email']) {
        $existente = db_fetch_one('SELECT id FROM users WHERE email = ? AND id != ?', ['si', $valores['email'], $id]);
        if ($existente) $erros['email'] = 'Já existe um usuário com este e-mail.';
    }

    if (empty($erros)) {
        $novo_slug = $valores['username'] !== $usuario['username']
            ? gerar_slug_usuario($valores['username'], $current_user->imobiliaria_id, $id)
            : $usuario['slug'];

        if ($senha !== '') {
            db_execute(
                "UPDATE users SET username = ?, slug = ?, email = ?, telefone = ?, password = ?, admin = ?,
                    perm_criar_usuarios = ?, perm_cotar = ?, perm_suporte = ?, perm_aceitar = ?,
                    perm_fianca = ?, perm_auto = ?, perm_condo = ?, perm_titulo = ?, perm_incendio = ?
                 WHERE id = ?",
                ['sssssssssssssssi',
                    $valores['username'], $novo_slug, $valores['email'], $valores['telefone'] ?: null,
                    password_hash($senha, PASSWORD_DEFAULT),
                    $valores['admin'] ? 'S' : 'N',
                    $valores['perm_criar_usuarios'] ? 'S' : 'N',
                    $valores['perm_cotar'] ? 'S' : 'N',
                    $valores['perm_suporte'] ? 'S' : 'N',
                    $valores['perm_aceitar'] ? 'S' : 'N',
                    $valores['perm_fianca'] ? 'S' : 'N',
                    $valores['perm_auto'] ? 'S' : 'N',
                    $valores['perm_condo'] ? 'S' : 'N',
                    $valores['perm_titulo'] ? 'S' : 'N',
                    $valores['perm_incendio'] ? 'S' : 'N',
                    $id,
                ]
            );
        } else {
            db_execute(
                "UPDATE users SET username = ?, slug = ?, email = ?, telefone = ?, admin = ?,
                    perm_criar_usuarios = ?, perm_cotar = ?, perm_suporte = ?, perm_aceitar = ?,
                    perm_fianca = ?, perm_auto = ?, perm_condo = ?, perm_titulo = ?, perm_incendio = ?
                 WHERE id = ?",
                ['ssssssssssssssi',
                    $valores['username'], $novo_slug, $valores['email'], $valores['telefone'] ?: null,
                    $valores['admin'] ? 'S' : 'N',
                    $valores['perm_criar_usuarios'] ? 'S' : 'N',
                    $valores['perm_cotar'] ? 'S' : 'N',
                    $valores['perm_suporte'] ? 'S' : 'N',
                    $valores['perm_aceitar'] ? 'S' : 'N',
                    $valores['perm_fianca'] ? 'S' : 'N',
                    $valores['perm_auto'] ? 'S' : 'N',
                    $valores['perm_condo'] ? 'S' : 'N',
                    $valores['perm_titulo'] ? 'S' : 'N',
                    $valores['perm_incendio'] ? 'S' : 'N',
                    $id,
                ]
            );
        }

        // Seguro Residencial (migration 094) — fora da query acima, que tem a lista fixa de colunas.

        db_execute('UPDATE users SET perm_seguro_residencial = ? WHERE id = ?', ['si', $valores['perm_seguro_residencial'] ? 'S' : 'N', $id]);

        flash('success', 'Usuário "' . $valores['username'] . '" atualizado com sucesso.');
        redirect('/usuarios');
    }
}

$page_title      = 'Editar Usuário';
$page_heading    = 'Editar Usuário';
$page_subheading = e($usuario['username']);
$page_back       = ['url' => '/usuarios', 'label' => 'Gerenciar Usuários'];
$extra_css       = ['cotacao.css'];
$extra_js        = ['masks.js', 'auth.js', 'senha-validar.js'];

require BASE_PATH . '/pages/partials/app-head.php';
require BASE_PATH . '/pages/partials/nav-imobiliaria.php';
?>
<div class="app-shell">
    <?php require BASE_PATH . '/pages/partials/sidebar.php'; ?>
    <div class="app-main">
        <?php require BASE_PATH . '/pages/partials/topbar.php'; ?>
        <main class="app-content">

            <form id="form-editar-usuario" method="POST" action="<?= e(BASE_URI) ?>/usuarios/<?= e($usuario['slug']) ?>/editar" novalidate class="cotacao-container">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">

                <div class="form-layout-70-30">
                    <div class="form-layout-main">

                        <!-- Dados do usuário -->
                        <div class="panel">
                            <div class="panel-body">

                                <div class="form-section">
                                    <h3 class="form-section-title"><?= icon('identification-card') ?> Dados pessoais</h3>
                                    <div class="form-grid">

                                        <div class="field">
                                            <label class="field-label" for="username">
                                                Nome <span class="required">*</span>
                                                <?php if (!empty($erros['username'])): ?>
                                                    <span class="field-error"><?= e($erros['username']) ?></span>
                                                <?php endif; ?>
                                            </label>
                                            <div class="field-input-wrap">
                                                <span class="field-input-icon"><?= icon('user') ?></span>
                                                <input class="field-input has-icon<?= !empty($erros['username']) ? ' is-error' : '' ?>"
                                                       type="text" id="username" name="username"
                                                       value="<?= e($valores['username']) ?>" maxlength="100" required>
                                            </div>
                                        </div>

                                        <div class="field">
                                            <label class="field-label" for="email">
                                                E-mail <span class="required">*</span>
                                                <?php if (!empty($erros['email'])): ?>
                                                    <span class="field-error"><?= e($erros['email']) ?></span>
                                                <?php endif; ?>
                                            </label>
                                            <div class="field-input-wrap">
                                                <span class="field-input-icon"><?= icon('envelope-simple') ?></span>
                                                <input class="field-input has-icon<?= !empty($erros['email']) ? ' is-error' : '' ?>"
                                                       type="email" id="email" name="email"
                                                       value="<?= e($valores['email']) ?>" maxlength="255" required>
                                            </div>
                                        </div>

                                        <div class="field span-full">
                                            <label class="field-label" for="telefone">Telefone</label>
                                            <div class="field-input-wrap">
                                                <span class="field-input-icon"><?= icon('phone') ?></span>
                                                <input class="field-input has-icon" type="text" id="telefone" name="telefone"
                                                       data-mask="phone" placeholder="(00) 00000-0000"
                                                       value="<?= e($valores['telefone']) ?>" maxlength="16">
                                            </div>
                                        </div>

                                    </div>
                                </div>

                                <div class="form-section no-margin-bottom">
                                    <h3 class="form-section-title"><?= icon('lock') ?> Senha de acesso</h3>
                                    <div class="form-grid">

                                        <div class="field">
                                            <label class="field-label" for="senha">
                                                Nova senha
                                                <?php if (!empty($erros['senha'])): ?>
                                                    <span class="field-error"><?= e($erros['senha']) ?></span>
                                                <?php endif; ?>
                                            </label>
                                            <div class="field-input-wrap">
                                                <span class="field-input-icon"><?= icon('lock') ?></span>
                                                <input class="field-input has-icon has-icon-both<?= !empty($erros['senha']) ? ' is-error' : '' ?>"
                                                       type="password" id="senha" name="senha"
                                                       minlength="6" autocomplete="new-password" placeholder="Deixe em branco para manter">
                                            <button type="button" class="field-password-toggle" data-toggle="password" data-target="#senha" aria-label="Mostrar senha">
                                                <?= icon('eye', 'icon-eye') ?>
                                                <?= icon('eye-slash', 'icon-eye-slash') ?>
                                            </button>
                                            </div>
                                            <span class="field-hint">Deixe em branco para não alterar</span>
                                        </div>

                                        <div class="field">
                                            <label class="field-label" for="senha_confirmar">
                                                Confirmar nova senha
                                                <?php if (!empty($erros['senha_confirmar'])): ?>
                                                    <span class="field-error"><?= e($erros['senha_confirmar']) ?></span>
                                                <?php endif; ?>
                                            </label>
                                            <div class="field-input-wrap">
                                                <span class="field-input-icon"><?= icon('lock') ?></span>
                                                <input class="field-input has-icon has-icon-both<?= !empty($erros['senha_confirmar']) ? ' is-error' : '' ?>"
                                                       type="password" id="senha_confirmar" name="senha_confirmar"
                                                       autocomplete="new-password" placeholder="••••••••">
                                            <button type="button" class="field-password-toggle" data-toggle="password" data-target="#senha_confirmar" aria-label="Mostrar confirmação de senha">
                                                <?= icon('eye', 'icon-eye') ?>
                                                <?= icon('eye-slash', 'icon-eye-slash') ?>
                                            </button>
                                            </div>
                                        </div>

                                    </div>
                                </div>

                                <div class="stepper-nav">
                                    <div></div>
                                    <div class="stepper-nav-right">
                                        <a href="<?= e(url('/usuarios')) ?>" class="btn btn-sm btn-secondary">Cancelar</a>
                                        <button type="submit" class="btn btn-sm btn-primary">
                                            <?= icon('check') ?> Salvar
                                        </button>
                                    </div>
                                </div>

                            </div>
                        </div>

                    </div>

                    <!-- Permissões -->
                    <div class="panel">
                        <div class="panel-header">
                            <h2><?= icon('key') ?> Permissões</h2>
                        </div>
                        <div class="panel-body">
                            <label class="switch">
                                <input type="checkbox" name="admin" value="S" id="perm-admin" <?= $valores['admin'] ? 'checked' : '' ?>>
                                <span class="switch-track"></span>
                                <span class="switch-label"><strong>Administrador da imobiliária</strong></span>
                            </label>
                            <label class="switch">
                                <input type="checkbox" name="perm_criar_usuarios" value="S" class="perm-check" <?= $valores['perm_criar_usuarios'] ? 'checked' : '' ?>>
                                <span class="switch-track"></span>
                                <span class="switch-label">Pode criar outros usuários</span>
                            </label>
                            <label class="switch">
                                <input type="checkbox" name="perm_cotar" value="S" class="perm-check" id="perm-cotar" <?= $valores['perm_cotar'] ? 'checked' : '' ?>>
                                <span class="switch-track"></span>
                                <span class="switch-label">Pode realizar cotações</span>
                            </label>
                            <div class="perm-produtos-wrap" id="perm-produtos-wrap" <?= $valores['perm_cotar'] ? '' : 'hidden' ?>>
                                <label class="switch">
                                    <input type="checkbox" name="perm_fianca" value="S" class="perm-check perm-produto" <?= $valores['perm_fianca'] ? 'checked' : '' ?>>
                                    <span class="switch-track"></span>
                                    <span class="switch-label">Acesso a Seguro Fiança</span>
                                </label>
                                <label class="switch">
                                    <input type="checkbox" name="perm_auto" value="S" class="perm-check perm-produto" <?= $valores['perm_auto'] ? 'checked' : '' ?>>
                                    <span class="switch-track"></span>
                                    <span class="switch-label">Acesso a Seguro Auto</span>
                                </label>
                                <label class="switch">
                                    <input type="checkbox" name="perm_condo" value="S" class="perm-check perm-produto" <?= $valores['perm_condo'] ? 'checked' : '' ?>>
                                    <span class="switch-track"></span>
                                    <span class="switch-label">Acesso a Seguro Condominial</span>
                                </label>
                                <label class="switch">
                                    <input type="checkbox" name="perm_titulo" value="S" class="perm-check perm-produto" <?= $valores['perm_titulo'] ? 'checked' : '' ?>>
                                    <span class="switch-track"></span>
                                    <span class="switch-label">Acesso a Título de Capitalização</span>
                                </label>
                                <label class="switch">
                                    <input type="checkbox" name="perm_incendio" value="S" class="perm-check perm-produto" <?= $valores['perm_incendio'] ? 'checked' : '' ?>>
                                    <span class="switch-track"></span>
                                    <span class="switch-label">Acesso a Seguro Incêndio</span>
                                </label>
                                <label class="switch">
                                    <input type="checkbox" name="perm_seguro_residencial" value="S" class="perm-check perm-produto" <?= $valores['perm_seguro_residencial'] ? 'checked' : '' ?>>
                                    <span class="switch-track"></span>
                                    <span class="switch-label">Acesso a Seguro Residencial</span>
                                </label>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="perm_suporte" value="S" class="perm-check" <?= $valores['perm_suporte'] ? 'checked' : '' ?>>
                                <span class="switch-track"></span>
                                <span class="switch-label">Pode abrir chamados de suporte</span>
                            </label>
                            <label class="switch">
                                <input type="checkbox" name="perm_aceitar" value="S" class="perm-check" <?= $valores['perm_aceitar'] ? 'checked' : '' ?>>
                                <span class="switch-track"></span>
                                <span class="switch-label">Pode aceitar propostas</span>
                            </label>
                        </div>
                    </div>

                </div>
            </form>

        </main>
    </div>
</div>

<script>
(function () {
    var form = document.getElementById('form-editar-usuario');
    if (!form) return;

    var adminCb    = form.querySelector('#perm-admin');
    var cotarCb    = form.querySelector('#perm-cotar');
    var outrosCbs  = form.querySelectorAll('.perm-check');
    var produtoCbs = form.querySelectorAll('.perm-produto');
    var produtosWrap = form.querySelector('#perm-produtos-wrap');

    function sincronizarProdutos() {
        produtosWrap.hidden = !cotarCb.checked;
        if (!cotarCb.checked) {
            produtoCbs.forEach(function (cb) { cb.checked = false; });
        }
    }

    function sincronizarAdmin() {
        outrosCbs.forEach(function (cb) {
            if (adminCb.checked) cb.checked = true;
            cb.disabled = adminCb.checked;
        });
        produtoCbs.forEach(function (cb) { cb.disabled = adminCb.checked; });
        if (adminCb.checked) {
            produtosWrap.hidden = false;
        } else {
            sincronizarProdutos();
        }
    }

    adminCb.addEventListener('change', sincronizarAdmin);
    cotarCb.addEventListener('change', sincronizarProdutos);
    sincronizarAdmin();
    sincronizarProdutos();
}());
</script>
<?php require BASE_PATH . '/pages/partials/app-foot.php'; ?>
