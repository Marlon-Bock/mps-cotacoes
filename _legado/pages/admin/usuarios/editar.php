<?php
/**
 * GET+POST /admin/usuarios/{slug}/editar
 * Formulário para editar usuário MPS existente.
 */
$current_user = require_admin();

$slug_param = trim($params['slug'] ?? '');
if ($slug_param === '') redirect('/admin/usuarios');

$usuario = db_fetch_one('SELECT * FROM usuario WHERE slug = ?', ['s', $slug_param]);
if (!$usuario) redirect('/admin/usuarios');

$id = (int) $usuario['id'];

$setores_disp = [];
try { $setores_disp = db_fetch_all('SELECT id, nome FROM setores ORDER BY ordem ASC, nome ASC'); } catch (\Throwable $e) {}
$setores_usuario = [];
try {
    $rows = db_fetch_all('SELECT setor_id FROM tarefas_usuario_setor WHERE usuario_id = ?', ['i', $id]);
    $setores_usuario = array_map(static fn($r) => (int) $r['setor_id'], $rows);
} catch (\Throwable $e) {}

$erros   = [];
$valores = [
    'username'                  => $usuario['username'],
    'email'                     => $usuario['email'],
    'email_cotacao'             => $usuario['email_cotacao'] ?? '',
    'telefone'                  => $usuario['telefone'] ?? '',
    'painel'                    => ($usuario['painel'] ?? 'N') === 'S',
    'admin'                     => $usuario['admin'] === 'S',
    'perm_fianca'               => $usuario['perm_fianca'] === 'S',
    'perm_auto'                 => $usuario['perm_auto'] === 'S',
    'perm_titulo'               => $usuario['perm_titulo'] === 'S',
    'perm_condominial'          => $usuario['perm_condominial'] === 'S',
    'perm_incendio'             => ($usuario['perm_incendio'] ?? 'N') === 'S',
    'perm_seguro_residencial'             => ($usuario['perm_seguro_residencial'] ?? 'N') === 'S',
    'perm_suporte'              => $usuario['perm_suporte'] === 'S',
    'equipe_comercial'          => $usuario['equipe_comercial'] === 'S',
    'tarefas_gestor'            => ($usuario['tarefas_gestor'] ?? 'N') === 'S',
    'setores'                   => $setores_usuario,
    'recebe_email_fianca'       => $usuario['recebe_email_fianca'] === 'S',
    'recebe_email_auto'         => $usuario['recebe_email_auto'] === 'S',
    'recebe_email_titulo'       => $usuario['recebe_email_titulo'] === 'S',
    'recebe_email_condominial'  => $usuario['recebe_email_condominial'] === 'S',
    'recebe_email_incendio'     => ($usuario['recebe_email_incendio'] ?? 'N') === 'S',
    'recebe_email_seguro_residencial'     => ($usuario['recebe_email_seguro_residencial'] ?? 'N') === 'S',
    'recebe_email_suporte'      => ($usuario['recebe_email_suporte'] ?? 'N') === 'S',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    $valores['username']      = trim($_POST['username'] ?? '');
    $valores['email']         = trim($_POST['email'] ?? '');
    $valores['email_cotacao'] = trim($_POST['email_cotacao'] ?? '');
    $valores['telefone']      = trim($_POST['telefone'] ?? '');
    $senha                    = (string) ($_POST['senha'] ?? '');
    $senha_confirmar          = (string) ($_POST['senha_confirmar'] ?? '');

    // Conta do painel de TV: só exibe a tela /painel, sem acesso ao resto do sistema.
    $valores['painel']           = ($_POST['painel'] ?? '') === 'S';
    $valores['admin']            = !$valores['painel'] && ($_POST['admin'] ?? '') === 'S';
    $valores['perm_fianca']      = $valores['admin'] || ($_POST['perm_fianca'] ?? '') === 'S';
    $valores['perm_auto']        = $valores['admin'] || ($_POST['perm_auto'] ?? '') === 'S';
    $valores['perm_titulo']      = $valores['admin'] || ($_POST['perm_titulo'] ?? '') === 'S';
    $valores['perm_condominial'] = $valores['admin'] || ($_POST['perm_condominial'] ?? '') === 'S';
    $valores['perm_incendio']    = $valores['admin'] || ($_POST['perm_incendio'] ?? '') === 'S';
    $valores['perm_seguro_residencial']    = $valores['admin'] || ($_POST['perm_seguro_residencial'] ?? '') === 'S';
    $valores['perm_suporte']     = $valores['admin'] || ($_POST['perm_suporte'] ?? '') === 'S';
    $valores['equipe_comercial'] = $valores['admin'] || ($_POST['equipe_comercial'] ?? '') === 'S';

    $valores['tarefas_gestor'] = ($_POST['tarefas_gestor'] ?? '') === 'S';
    $valores['setores']        = array_values(array_unique(array_map('intval', (array) ($_POST['setores'] ?? []))));

    $valores['recebe_email_fianca']      = ($_POST['recebe_email_fianca'] ?? '') === 'S';
    $valores['recebe_email_auto']        = ($_POST['recebe_email_auto'] ?? '') === 'S';
    $valores['recebe_email_titulo']      = ($_POST['recebe_email_titulo'] ?? '') === 'S';
    $valores['recebe_email_condominial'] = ($_POST['recebe_email_condominial'] ?? '') === 'S';
    $valores['recebe_email_incendio']    = ($_POST['recebe_email_incendio'] ?? '') === 'S';
    $valores['recebe_email_seguro_residencial']    = ($_POST['recebe_email_seguro_residencial'] ?? '') === 'S';
    $valores['recebe_email_suporte']     = ($_POST['recebe_email_suporte'] ?? '') === 'S';

    // A conta de exibição não recebe permissão, setor nem e-mail de nada.
    if ($valores['painel']) {
        foreach ([
            'perm_fianca', 'perm_auto', 'perm_titulo', 'perm_condominial', 'perm_incendio', 'perm_seguro_residencial', 'perm_suporte',
            'equipe_comercial', 'tarefas_gestor',
            'recebe_email_fianca', 'recebe_email_auto', 'recebe_email_titulo',
            'recebe_email_condominial', 'recebe_email_incendio', 'recebe_email_seguro_residencial', 'recebe_email_suporte',
        ] as $campo) {
            $valores[$campo] = false;
        }
        $valores['setores'] = [];
    }

    if ($valores['username'] === '') $erros['username'] = 'Nome é obrigatório.';
    if ($valores['email'] === '' || !filter_var($valores['email'], FILTER_VALIDATE_EMAIL)) {
        $erros['email'] = 'Informe um e-mail válido.';
    }
    if ($valores['email_cotacao'] !== '' && !filter_var($valores['email_cotacao'], FILTER_VALIDATE_EMAIL)) {
        $erros['email_cotacao'] = 'Informe um e-mail válido ou deixe em branco.';
    }
    if ($senha !== '' && !senha_valida($senha)) $erros['senha'] = 'A senha deve ter no mínimo 6 caracteres, com 1 maiúscula e 1 número.';
    if ($senha !== '' && $senha !== $senha_confirmar) $erros['senha_confirmar'] = 'As senhas não coincidem.';

    if (empty($erros) && $valores['email'] !== $usuario['email']) {
        $existente = db_fetch_one('SELECT id FROM usuario WHERE email = ? AND id != ?', ['si', $valores['email'], $id]);
        if ($existente) $erros['email'] = 'Já existe um usuário com este e-mail.';
    }

    if (empty($erros)) {
        $novo_slug = $valores['username'] !== $usuario['username']
            ? gerar_slug_usuario_mps($valores['username'], $id)
            : $usuario['slug'];

        $valores_bd = [
            // email_cotacao é NOT NULL no banco — vazio fica string vazia, não null.
            $valores['username'], $novo_slug, $valores['email'], $valores['email_cotacao'], $valores['telefone'] ?: null,
        ];
        $tipos_bd = 'sssss';

        if ($senha !== '') {
            $valores_bd[] = password_hash($senha, PASSWORD_DEFAULT);
            $tipos_bd    .= 's';
        }

        $valores_bd = array_merge($valores_bd, [
            $valores['painel'] ? 'S' : 'N',
            $valores['admin'] ? 'S' : 'N',
            $valores['perm_fianca'] ? 'S' : 'N',
            // perm_residencial/perm_comercial mantidas em sincronia com o toggle único de Fiança.
            $valores['perm_fianca'] ? 'S' : 'N',
            $valores['perm_fianca'] ? 'S' : 'N',
            $valores['perm_auto'] ? 'S' : 'N',
            $valores['perm_titulo'] ? 'S' : 'N',
            $valores['perm_condominial'] ? 'S' : 'N',
            $valores['perm_incendio'] ? 'S' : 'N',
            $valores['perm_suporte'] ? 'S' : 'N',
            $valores['equipe_comercial'] ? 'S' : 'N',
            $valores['tarefas_gestor'] ? 'S' : 'N',
            $valores['recebe_email_fianca'] ? 'S' : 'N',
            $valores['recebe_email_auto'] ? 'S' : 'N',
            $valores['recebe_email_titulo'] ? 'S' : 'N',
            $valores['recebe_email_condominial'] ? 'S' : 'N',
            $valores['recebe_email_incendio'] ? 'S' : 'N',
            $valores['recebe_email_suporte'] ? 'S' : 'N',
        ]);
        $tipos_bd .= 'ssssssssssssssssss';

        $valores_bd[] = $id;
        $tipos_bd    .= 'i';

        db_execute(
            'UPDATE usuario SET username = ?, slug = ?, email = ?, email_cotacao = ?, telefone = ?, ' .
            ($senha !== '' ? 'password = ?, ' : '') .
            'painel = ?, admin = ?, perm_fianca = ?, perm_residencial = ?, perm_comercial = ?, perm_auto = ?, perm_titulo = ?, perm_condominial = ?, perm_incendio = ?, perm_suporte = ?, equipe_comercial = ?, tarefas_gestor = ?,
             recebe_email_fianca = ?, recebe_email_auto = ?, recebe_email_titulo = ?, recebe_email_condominial = ?, recebe_email_incendio = ?, recebe_email_suporte = ?,
             updated_at = NOW()
             WHERE id = ?',
            [$tipos_bd, ...$valores_bd]
        );

        // Sincroniza setores (só para não-gestores — gestor vê tudo).
        db_execute('DELETE FROM tarefas_usuario_setor WHERE usuario_id = ?', ['i', $id]);
        if (!$valores['tarefas_gestor']) {
            foreach ($valores['setores'] as $sid) {
                if ($sid > 0) {
                    db_execute('INSERT IGNORE INTO tarefas_usuario_setor (usuario_id, setor_id) VALUES (?, ?)', ['ii', $id, $sid]);
                }
            }
        }

        // Seguro Residencial (migration 094) — fora da query acima, que tem a lista fixa de colunas.

        db_execute('UPDATE usuario SET perm_seguro_residencial = ?, recebe_email_seguro_residencial = ? WHERE id = ?', ['ssi', $valores['perm_seguro_residencial'] ? 'S' : 'N', $valores['recebe_email_seguro_residencial'] ? 'S' : 'N', $id]);

        flash('success', 'Usuário "' . $valores['username'] . '" atualizado com sucesso.');
        redirect('/admin/usuarios');
    }
}

$page_title      = 'Editar Usuário MPS — ' . $usuario['username'];
$page_heading    = 'Editar Usuário MPS';
$page_subheading = e($usuario['username']);
$page_back       = ['url' => '/admin/usuarios', 'label' => 'Usuários MPS'];
$extra_css       = ['cotacao.css'];
$extra_js        = ['masks.js', 'auth.js', 'senha-validar.js'];

require BASE_PATH . '/pages/partials/app-head.php';
require BASE_PATH . '/pages/partials/nav-admin.php';
?>
<div class="app-shell">
    <?php require BASE_PATH . '/pages/partials/sidebar.php'; ?>
    <div class="app-main">
        <?php require BASE_PATH . '/pages/partials/topbar.php'; ?>
        <main class="app-content">

            <form id="form-editar-usuario-mps" method="POST" action="<?= e(BASE_URI) ?>/admin/usuarios/<?= e($slug_param) ?>/editar" novalidate class="cotacao-container">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">

                <div class="form-layout-70-30">
                    <div class="form-layout-main">

                        <!-- Dados pessoais -->
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
                                            <label class="field-label" for="email_cotacao">
                                                E-mail para receber cotações (opcional)
                                                <?php if (!empty($erros['email_cotacao'])): ?>
                                                    <span class="field-error"><?= e($erros['email_cotacao']) ?></span>
                                                <?php endif; ?>
                                            </label>
                                            <div class="field-input-wrap">
                                                <span class="field-input-icon"><?= icon('envelope-simple') ?></span>
                                                <input class="field-input has-icon<?= !empty($erros['email_cotacao']) ? ' is-error' : '' ?>"
                                                       type="email" id="email_cotacao" name="email_cotacao"
                                                       value="<?= e($valores['email_cotacao']) ?>" maxlength="255"
                                                       placeholder="Se vazio, usaremos o e-mail de login">
                                            </div>
                                            <span class="field-hint">Se preenchido, este será usado para alertas de novas cotações.</span>
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
                                        <a href="<?= e(url('/admin/usuarios')) ?>" class="btn btn-sm btn-secondary">Cancelar</a>
                                        <button type="submit" class="btn btn-sm btn-primary">
                                            <?= icon('check') ?> Salvar
                                        </button>
                                    </div>
                                </div>

                            </div>
                        </div>

                    </div>

                    <div class="form-layout-side">

                        <!-- Painel de exibição (TV) -->
                        <div class="panel">
                            <div class="panel-header">
                                <h2><?= icon('monitor') ?> Painel de exibição</h2>
                            </div>
                            <div class="panel-body">
                                <label class="switch">
                                    <input type="checkbox" name="painel" value="S" id="perm-painel" <?= $valores['painel'] ? 'checked' : '' ?>>
                                    <span class="switch-track"></span>
                                    <span class="switch-label"><strong>Conta do painel de TV</strong></span>
                                </label>
                                <p class="field-hint">Entra apenas na tela <strong>/painel</strong>, que fica exibindo as cotações na TV. Não acessa cotações, tarefas nem administração.</p>
                            </div>
                        </div>

                        <!-- Permissões -->
                        <div class="panel" id="painel-permissoes">
                            <div class="panel-header">
                                <h2><?= icon('key') ?> Permissões</h2>
                            </div>
                            <div class="panel-body">
                                <label class="switch">
                                    <input type="checkbox" name="admin" value="S" id="perm-admin" <?= $valores['admin'] ? 'checked' : '' ?>>
                                    <span class="switch-track"></span>
                                    <span class="switch-label"><strong>Administrador</strong></span>
                                </label>
                                <label class="switch">
                                    <input type="checkbox" name="perm_fianca" value="S" class="perm-check" <?= $valores['perm_fianca'] ? 'checked' : '' ?>>
                                    <span class="switch-track"></span>
                                    <span class="switch-label">Fiança</span>
                                </label>
                                <label class="switch">
                                    <input type="checkbox" name="perm_auto" value="S" class="perm-check" <?= $valores['perm_auto'] ? 'checked' : '' ?>>
                                    <span class="switch-track"></span>
                                    <span class="switch-label">Seguro Auto</span>
                                </label>
                                <label class="switch">
                                    <input type="checkbox" name="perm_titulo" value="S" class="perm-check" <?= $valores['perm_titulo'] ? 'checked' : '' ?>>
                                    <span class="switch-track"></span>
                                    <span class="switch-label">Título de Capitalização</span>
                                </label>
                                <label class="switch">
                                    <input type="checkbox" name="perm_condominial" value="S" class="perm-check" <?= $valores['perm_condominial'] ? 'checked' : '' ?>>
                                    <span class="switch-track"></span>
                                    <span class="switch-label">Condominial</span>
                                </label>
                                <label class="switch">
                                    <input type="checkbox" name="perm_incendio" value="S" class="perm-check" <?= $valores['perm_incendio'] ? 'checked' : '' ?>>
                                    <span class="switch-track"></span>
                                    <span class="switch-label">Seguro Incêndio</span>
                                </label>
                                <label class="switch">
                                    <input type="checkbox" name="perm_seguro_residencial" value="S" class="perm-check" <?= $valores['perm_seguro_residencial'] ? 'checked' : '' ?>>
                                    <span class="switch-track"></span>
                                    <span class="switch-label">Seguro Residencial</span>
                                </label>
                                <label class="switch">
                                    <input type="checkbox" name="perm_suporte" value="S" class="perm-check" <?= $valores['perm_suporte'] ? 'checked' : '' ?>>
                                    <span class="switch-track"></span>
                                    <span class="switch-label">Suporte</span>
                                </label>
                                <label class="switch">
                                    <input type="checkbox" name="equipe_comercial" value="S" class="perm-check" <?= $valores['equipe_comercial'] ? 'checked' : '' ?>>
                                    <span class="switch-track"></span>
                                    <span class="switch-label">Equipe comercial</span>
                                </label>
                            </div>
                        </div>

                        <!-- Tarefas -->
                        <div class="panel" id="painel-tarefas">
                            <div class="panel-header">
                                <h2><?= icon('list-checks') ?> Tarefas</h2>
                            </div>
                            <div class="panel-body">
                                <label class="switch">
                                    <input type="checkbox" name="tarefas_gestor" value="S" id="perm-tarefas-gestor" <?= $valores['tarefas_gestor'] ? 'checked' : '' ?>>
                                    <span class="switch-track"></span>
                                    <span class="switch-label"><strong>Gestor de Tarefas</strong> (vê tudo, setor = Administrador)</span>
                                </label>
                                <div id="setores-wrap"<?= $valores['tarefas_gestor'] ? ' class="is-hidden"' : '' ?>>
                                    <p class="field-hint">Setores do usuário</p>
                                    <?php if (empty($setores_disp)): ?>
                                        <p class="field-hint">Nenhum setor criado ainda. Crie setores em <a href="<?= e(url('/admin/tarefas')) ?>">Tarefas &rsaquo; Gerenciar</a>.</p>
                                    <?php else: ?>
                                    <div class="switch-group">
                                        <?php foreach ($setores_disp as $s): ?>
                                        <label class="switch">
                                            <input type="checkbox" name="setores[]" value="<?= (int) $s['id'] ?>" <?= in_array((int) $s['id'], $valores['setores'], true) ? 'checked' : '' ?>>
                                            <span class="switch-track"></span>
                                            <span class="switch-label"><?= e($s['nome']) ?></span>
                                        </label>
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>

                        <!-- Preferências de e-mail -->
                        <div class="panel" id="painel-emails">
                            <div class="panel-header">
                                <h2><?= icon('envelope-simple') ?> Preferências de e-mail</h2>
                            </div>
                            <div class="panel-body">
                                <label class="switch">
                                    <input type="checkbox" name="recebe_email_fianca" value="S" <?= $valores['recebe_email_fianca'] ? 'checked' : '' ?>>
                                    <span class="switch-track"></span>
                                    <span class="switch-label">Fiança</span>
                                </label>
                                <label class="switch">
                                    <input type="checkbox" name="recebe_email_auto" value="S" <?= $valores['recebe_email_auto'] ? 'checked' : '' ?>>
                                    <span class="switch-track"></span>
                                    <span class="switch-label">Auto</span>
                                </label>
                                <label class="switch">
                                    <input type="checkbox" name="recebe_email_titulo" value="S" <?= $valores['recebe_email_titulo'] ? 'checked' : '' ?>>
                                    <span class="switch-track"></span>
                                    <span class="switch-label">Título de Capitalização</span>
                                </label>
                                <label class="switch">
                                    <input type="checkbox" name="recebe_email_condominial" value="S" <?= $valores['recebe_email_condominial'] ? 'checked' : '' ?>>
                                    <span class="switch-track"></span>
                                    <span class="switch-label">Condominial</span>
                                </label>
                                <label class="switch">
                                    <input type="checkbox" name="recebe_email_incendio" value="S" <?= $valores['recebe_email_incendio'] ? 'checked' : '' ?>>
                                    <span class="switch-track"></span>
                                    <span class="switch-label">Seguro Incêndio</span>
                                </label>
                                <label class="switch">
                                    <input type="checkbox" name="recebe_email_seguro_residencial" value="S" <?= $valores['recebe_email_seguro_residencial'] ? 'checked' : '' ?>>
                                    <span class="switch-track"></span>
                                    <span class="switch-label">Seguro Residencial</span>
                                </label>
                                <label class="switch">
                                    <input type="checkbox" name="recebe_email_suporte" value="S" <?= $valores['recebe_email_suporte'] ? 'checked' : '' ?>>
                                    <span class="switch-track"></span>
                                    <span class="switch-label">Chamados de suporte</span>
                                </label>
                            </div>
                        </div>

                    </div>
                </div>
            </form>

        </main>
    </div>
</div>

<script>
(function () {
    var form = document.getElementById('form-editar-usuario-mps');
    if (!form) return;

    var adminCb   = form.querySelector('#perm-admin');
    var outrosCbs = form.querySelectorAll('.perm-check');

    function sincronizarAdmin() {
        outrosCbs.forEach(function (cb) {
            if (adminCb.checked) cb.checked = true;
            cb.disabled = adminCb.checked;
        });
    }

    adminCb.addEventListener('change', sincronizarAdmin);
    sincronizarAdmin();

    var gestorCb    = form.querySelector('#perm-tarefas-gestor');
    var setoresWrap = form.querySelector('#setores-wrap');
    if (gestorCb && setoresWrap) {
        var sincronizarGestor = function () {
            setoresWrap.classList.toggle('is-hidden', gestorCb.checked);
        };
        gestorCb.addEventListener('change', sincronizarGestor);
        sincronizarGestor();
    }

    // Conta do painel de TV não tem permissão, setor nem preferência de e-mail:
    // marcar o toggle esconde esses blocos e limpa o que estiver marcado.
    var painelCb = form.querySelector('#perm-painel');
    var blocos   = ['#painel-permissoes', '#painel-tarefas', '#painel-emails']
        .map(function (sel) { return form.querySelector(sel); })
        .filter(Boolean);

    if (painelCb) {
        var sincronizarPainel = function () {
            blocos.forEach(function (bloco) {
                bloco.classList.toggle('is-hidden', painelCb.checked);
                if (painelCb.checked) {
                    bloco.querySelectorAll('input[type="checkbox"]').forEach(function (cb) { cb.checked = false; });
                }
            });
            if (!painelCb.checked) sincronizarAdmin();
        };
        painelCb.addEventListener('change', sincronizarPainel);
        sincronizarPainel();
    }
}());
</script>
<?php require BASE_PATH . '/pages/partials/app-foot.php'; ?>
