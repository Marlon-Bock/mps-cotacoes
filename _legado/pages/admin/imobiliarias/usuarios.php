<?php
/**
 * GET /admin/imobiliarias/{slug}/usuarios
 * Lista os usuários vinculados a uma imobiliária específica.
 */
$current_user = require_admin();

$flash_messages = flash_get();

$slug_param = trim($params['slug'] ?? '');

try {
    $imob = $slug_param !== '' ? db_fetch_one('SELECT id, nome FROM imobiliarias WHERE slug = ?', ['s', $slug_param]) : null;
} catch (Exception $e) {
    $imob = null;
}

if (!$imob) redirect('/admin/imobiliarias');

$id = (int) $imob['id'];

try {
    $usuarios = db_fetch_all(
        'SELECT id, username, slug, email, telefone, admin, ativo, last_login_at FROM users WHERE imobiliaria_id = ? ORDER BY username ASC',
        ['i', $id]
    );
} catch (Exception $e) {
    $usuarios = [];
}

$page_title      = 'Usuários — ' . $imob['nome'];
$page_heading    = 'Usuários — ' . e($imob['nome']);
$page_subheading = 'Gerencie os usuários vinculados a esta imobiliária';
$page_back       = ['url' => '/admin/imobiliarias', 'label' => 'Imobiliárias'];
$extra_css       = ['dashboard.css', 'cotacoes.css'];
$extra_js        = ['admin-imobiliarias-usuarios.js'];

require BASE_PATH . '/pages/partials/app-head.php';
require BASE_PATH . '/pages/partials/nav-admin.php';
?>
<div class="app-shell">
    <?php require BASE_PATH . '/pages/partials/sidebar.php'; ?>
    <div class="app-main">
        <?php require BASE_PATH . '/pages/partials/topbar.php'; ?>
        <main class="app-content" data-flash="<?= e(json_encode($flash_messages)) ?>">

            <div class="panel cot-panel">
                <div class="panel-header">
                    <h2>Usuários de <?= e($imob['nome']) ?></h2>
                    <a href="<?= e(BASE_URI) ?>/admin/imobiliarias/<?= e($slug_param) ?>/usuarios/criar" class="btn btn-primary btn-sm">
                        <?= icon('plus') ?> Criar Usuário
                    </a>
                </div>
                <?php if (empty($usuarios)): ?>
                    <div class="panel-body">
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                <?= icon('users') ?>
                            </div>
                            <p>Nenhum usuário vinculado a esta imobiliária ainda.</p>
                            <a href="<?= e(BASE_URI) ?>/admin/imobiliarias/<?= e($slug_param) ?>/usuarios/criar" class="btn btn-primary btn-sm">
                                Criar primeiro usuário
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="admin-cot-grid-wrap">
                        <div class="admin-cot-row admin-cot-row-imob-usuarios admin-cot-head">
                            <div class="admin-cot-cell admin-cot-cell-nome">Nome</div>
                            <div class="admin-cot-cell">E-mail</div>
                            <div class="admin-cot-cell">Telefone</div>
                            <div class="admin-cot-cell">Perfil</div>
                            <div class="admin-cot-cell">Último login</div>
                            <div class="admin-cot-cell">Status</div>
                            <div class="admin-cot-cell">Ações</div>
                        </div>
                        <?php foreach ($usuarios as $u): ?>
                        <div class="admin-cot-row admin-cot-row-imob-usuarios">
                            <div class="admin-cot-cell admin-cot-cell-nome"><?= e($u['username']) ?></div>
                            <div class="admin-cot-cell"><?= e($u['email']) ?></div>
                            <div class="admin-cot-cell"><?= $u['telefone'] ? e($u['telefone']) : '<span class="admin-cot-cell-vazio">—</span>' ?></div>
                            <div class="admin-cot-cell">
                                <?php if ($u['admin'] === 'S'): ?>
                                    <span class="badge badge-primary">Admin</span>
                                <?php else: ?>
                                    <span class="badge">Operador</span>
                                <?php endif; ?>
                            </div>
                            <div class="admin-cot-cell">
                                <?php if ($u['last_login_at']): ?>
                                    <?= e(date('d/m/Y H:i', strtotime($u['last_login_at']))) ?>
                                <?php else: ?>
                                    <span class="admin-cot-cell-vazio">Nunca</span>
                                <?php endif; ?>
                            </div>
                            <div class="admin-cot-cell">
                                <?php if ($u['ativo']): ?>
                                    <span class="status-pill status-pill-aprovado">Ativo</span>
                                <?php else: ?>
                                    <span class="status-pill status-pill-recusado">Inativo</span>
                                <?php endif; ?>
                            </div>
                            <div class="admin-cot-cell admin-cot-cell-acoes">
                                <div class="table-actions">
                                    <a href="<?= e(BASE_URI) ?>/admin/imobiliarias/<?= e($slug_param) ?>/usuarios/<?= e($u['slug']) ?>/editar" class="btn btn-secondary btn-sm" title="Editar"><?= icon('note-pencil') ?></a>
                                    <button
                                        type="button"
                                        class="btn <?= $u['ativo'] ? 'btn-danger-outline' : 'btn-secondary' ?> btn-sm js-toggle-ativo"
                                        data-action="toggle-ativo"
                                        data-usuario-id="<?= (int) $u['id'] ?>"
                                        data-ativo="<?= (int) $u['ativo'] ?>"
                                        data-imobiliaria-id="<?= (int) $imob['id'] ?>"
                                        title="<?= $u['ativo'] ? 'Inativar' : 'Ativar' ?>"
                                    >
                                        <?= $u['ativo'] ? icon('x') : icon('check') ?>
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>

        </main>
    </div>
</div>
<?php require BASE_PATH . '/pages/partials/app-foot.php'; ?>
