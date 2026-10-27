<?php
/**
 * GET /api/admin/usuarios/listar
 * Lista os usuários internos da MPS, com busca opcional por nome/e-mail.
 * Mesmo padrão de /api/usuarios/listar (imobiliária) — sem paginação.
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');

$busca = trim($_GET['busca'] ?? '');

$where  = '1=1';
$tipos  = '';
$params = [];

if ($busca !== '') {
    $where   .= ' AND (username LIKE ? OR email LIKE ?)';
    $tipos   .= 'ss';
    $params[] = "%{$busca}%";
    $params[] = "%{$busca}%";
}

$usuarios = [];
try {
    $sql = "SELECT id, username, slug, email, telefone, admin, painel, avatar, last_login_at
              FROM usuario
             WHERE {$where}
             ORDER BY username ASC";
    $usuarios = $tipos !== '' ? db_fetch_all($sql, [$tipos, ...$params]) : db_fetch_all($sql);
} catch (\Throwable $e) {}

ob_start();

if (empty($usuarios)): ?>
    <div class="admin-cot-empty">Nenhum usuário encontrado.</div>
<?php else:
    foreach ($usuarios as $u):
        $eh_admin     = $u['admin'] === 'S';
        $eh_painel    = ($u['painel'] ?? 'N') === 'S';
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
            <?php if ($eh_painel): ?>
                <span class="badge">Painel de TV</span>
            <?php elseif ($eh_admin): ?>
                <span class="badge badge-primary">Administrador</span>
            <?php else: ?>
                <span class="badge">Atendente</span>
            <?php endif; ?>
        </div>
        <div class="admin-cot-cell" style="color:var(--color-gray-500)"><?= $ultimo_login ?></div>
        <div class="admin-cot-cell">
            <div class="table-actions">
                <a href="<?= e(BASE_URI) ?>/admin/usuarios/<?= e($u['slug']) ?>/editar" class="btn btn-secondary btn-sm">
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
