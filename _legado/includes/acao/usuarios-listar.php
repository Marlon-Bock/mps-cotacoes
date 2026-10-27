<?php
/**
 * GET /api/usuarios/listar
 * Lista usuários da imobiliária logada, com busca opcional por nome/e-mail.
 */
$current_user = require_auth();
header('Content-Type: application/json; charset=utf-8');

if (!($current_user->imob_admin ?? false)) {
    echo json_encode(['html' => '<div class="admin-cot-empty">Sem permissão.</div>']);
    exit;
}

$busca = trim($_GET['busca'] ?? '');

$where  = 'imobiliaria_id = ?';
$params = ['i', $current_user->imobiliaria_id];

if ($busca !== '') {
    $where      .= ' AND (username LIKE ? OR email LIKE ?)';
    $params[0]  .= 'ss';
    $params[]    = "%{$busca}%";
    $params[]    = "%{$busca}%";
}

$usuarios = [];
try {
    $usuarios = db_fetch_all(
        "SELECT id, username, slug, email, telefone, admin, avatar, last_login_at
           FROM users
          WHERE {$where}
          ORDER BY username ASC",
        $params
    );
} catch (\Throwable $e) {}

ob_start();

if (empty($usuarios)): ?>
    <div class="admin-cot-empty">Nenhum usuário encontrado.</div>
<?php else:
    foreach ($usuarios as $u):
        $eh_admin     = $u['admin'] === 'S';
        $eh_voce      = (int) $u['id'] === (int) $current_user->uid;
        $ultimo_login = $u['last_login_at'] ? date('d/m/Y H:i', strtotime($u['last_login_at'])) : '—';
?>
    <div class="admin-cot-row admin-cot-row-usuarios">
        <div class="admin-cot-cell admin-cot-cell-nome">
            <span class="atendente-chip"><?= avatar_sm($u['avatar'] ?? null, $u['username'], 'avatar-md') ?> <?= e($u['username']) ?></span>
            <?php if ($eh_voce): ?><span class="badge">Você</span><?php endif; ?>
        </div>
        <div class="admin-cot-cell"><?= e($u['email']) ?></div>
        <div class="admin-cot-cell"><?= $u['telefone'] ? e($u['telefone']) : '—' ?></div>
        <div class="admin-cot-cell">
            <?php if ($eh_admin): ?>
                <span class="badge badge-primary">Administrador</span>
            <?php else: ?>
                <span class="badge">Operador</span>
            <?php endif; ?>
        </div>
        <div class="admin-cot-cell" style="color:var(--color-gray-500)"><?= $ultimo_login ?></div>
        <div class="admin-cot-cell">
            <div class="table-actions">
                <a href="<?= e(BASE_URI) ?>/usuarios/<?= e($u['slug']) ?>/editar" class="btn btn-secondary btn-sm">
                    <?= icon('note-pencil') ?> Editar
                </a>
                <?php if (!$eh_voce): ?>
                <button type="button" class="btn btn-danger-outline btn-sm" data-action="excluir-usuario" data-id="<?= (int) $u['id'] ?>">
                    <?= icon('x') ?> Excluir
                </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
<?php
    endforeach;
endif;

$html = ob_get_clean();
echo json_encode(['html' => $html]);
