<?php
/**
 * AJAX endpoint — GET /api/notificacoes/listar
 * Lista as notificações do usuário logado (imobiliária), filtráveis por tipo
 * e paginadas — mesmo padrão de /api/cotacoes/listar (JSON { html, ... }),
 * usado pela tela /notificacoes pra nunca recarregar a página.
 * Retorna JSON { html, contadores, total, page, per_page, total_pages }
 */
$current_user = require_auth();
header('Content-Type: application/json; charset=utf-8');

$destinatario_id = (int) ($current_user->uid ?? 0);

$tipos_validos = ['cotacao', 'chamado', 'campanha'];
$tipo_filtro   = sanitize($_GET['tipo'] ?? '');
if (!in_array($tipo_filtro, $tipos_validos, true)) {
    $tipo_filtro = '';
}

$page     = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 20;
$offset   = ($page - 1) * $per_page;

$notificacoes = [];
$total        = 0;

// Operador restrito a só alguns produtos só vê, nesta central completa, as
// notificações das cotações desses produtos — mesmo filtro já aplicado no
// resumo/dropdown do sininho (ver notificacoes-resumo.php).
$filtro_produto_sql   = '';
$filtro_produto_binds = [];
$produtos_permitidos  = imob_produtos_permitidos($current_user);
if ($produtos_permitidos !== null) {
    $condicoes = array_fill(0, count($produtos_permitidos), 'url LIKE ?');
    $filtro_produto_sql = " AND (url NOT LIKE '%/cotacao/%' OR " . implode(' OR ', $condicoes) . ')';
    foreach ($produtos_permitidos as $p) $filtro_produto_binds[] = "%/cotacao/{$p}/%";
}

try {
    if ($tipo_filtro !== '') {
        $notificacoes = db_fetch_all(
            "SELECT id, titulo, mensagem, tipo, lida, criado_em
             FROM notificacoes_mps
             WHERE destinatario_id = ? AND destinatario_tipo = 'imob' AND tipo = ?{$filtro_produto_sql}
             ORDER BY criado_em DESC
             LIMIT ? OFFSET ?",
            ['is' . str_repeat('s', count($filtro_produto_binds)) . 'ii', $destinatario_id, $tipo_filtro, ...$filtro_produto_binds, $per_page, $offset]
        );
        $total = safe_count(
            "SELECT COUNT(*) AS total FROM notificacoes_mps
             WHERE destinatario_id = ? AND destinatario_tipo = 'imob' AND tipo = ?{$filtro_produto_sql}",
            ['is' . str_repeat('s', count($filtro_produto_binds)), $destinatario_id, $tipo_filtro, ...$filtro_produto_binds]
        );
    } else {
        $notificacoes = db_fetch_all(
            "SELECT id, titulo, mensagem, tipo, lida, criado_em
             FROM notificacoes_mps
             WHERE destinatario_id = ? AND destinatario_tipo = 'imob'{$filtro_produto_sql}
             ORDER BY criado_em DESC
             LIMIT ? OFFSET ?",
            ['i' . str_repeat('s', count($filtro_produto_binds)) . 'ii', $destinatario_id, ...$filtro_produto_binds, $per_page, $offset]
        );
        $total = safe_count(
            "SELECT COUNT(*) AS total FROM notificacoes_mps
             WHERE destinatario_id = ? AND destinatario_tipo = 'imob'{$filtro_produto_sql}",
            ['i' . str_repeat('s', count($filtro_produto_binds)), $destinatario_id, ...$filtro_produto_binds]
        );
    }
} catch (Throwable $e) {
    $notificacoes = [];
    $total        = 0;
}

// ── Contadores por tipo (pros KPI cards do topo, sempre sobre o total — não
//    filtrado por tipo, mas já respeitando o filtro de produto — pra não
//    sumirem os outros contadores ao trocar de aba) ─────────────────────────
$contadores = ['todos' => 0, 'cotacao' => 0, 'chamado' => 0, 'campanha' => 0];
try {
    $contadores['todos'] = safe_count(
        "SELECT COUNT(*) AS total FROM notificacoes_mps WHERE destinatario_id = ? AND destinatario_tipo = 'imob'{$filtro_produto_sql}",
        ['i' . str_repeat('s', count($filtro_produto_binds)), $destinatario_id, ...$filtro_produto_binds]
    );
    $rows_contagem = db_fetch_all(
        "SELECT tipo, COUNT(*) AS total FROM notificacoes_mps
         WHERE destinatario_id = ? AND destinatario_tipo = 'imob'{$filtro_produto_sql}
         GROUP BY tipo",
        ['i' . str_repeat('s', count($filtro_produto_binds)), $destinatario_id, ...$filtro_produto_binds]
    );
    foreach ($rows_contagem as $row) {
        if (isset($contadores[$row['tipo']])) {
            $contadores[$row['tipo']] = (int) $row['total'];
        }
    }
} catch (Throwable $e) {
    // mantém contadores zerados
}

$total_pages = $total > 0 ? (int) ceil($total / $per_page) : 1;

ob_start();

if (empty($notificacoes)): ?>
<div class="admin-cot-empty">
    <?php if ($tipo_filtro !== ''): ?>
        Nenhuma notificação por aqui.
    <?php else: ?>
        Você não tem nenhuma notificação ainda.
    <?php endif; ?>
</div>
<?php else: ?>
<ul class="notif-list" id="notif-list">
    <?php foreach ($notificacoes as $notif):
        $lida     = (int) ($notif['lida'] ?? 0);
        $notif_id = (int) $notif['id'];
        $data_fmt = !empty($notif['criado_em'])
            ? date('d/m/Y \à\s H:i', strtotime($notif['criado_em']))
            : '—';
    ?>
        <li class="notif-item<?= $lida ? ' is-read' : '' ?>" data-id="<?= $notif_id ?>">
            <span class="notif-item-icon notif-item-icon--<?= e($notif['tipo'] ?? 'geral') ?>">
                <?= icon(notif_tipo_icon($notif['tipo'] ?? '')) ?>
            </span>
            <div class="notif-item-body">
                <div class="notif-item-header">
                    <strong class="notif-item-titulo"><?= e($notif['titulo'] ?? '') ?></strong>
                    <?php if (!$lida): ?>
                        <span class="badge badge-primary">Nova</span>
                    <?php endif; ?>
                </div>
                <p class="notif-item-mensagem"><?= e($notif['mensagem'] ?? '') ?></p>
                <span class="notif-item-data"><?= e($data_fmt) ?></span>
            </div>
            <?php if (!$lida): ?>
                <button type="button"
                        class="btn btn-link btn-sm notif-marcar-btn"
                        data-action="marcar-lida"
                        data-id="<?= $notif_id ?>">
                    Marcar como lida
                </button>
            <?php endif; ?>
        </li>
    <?php endforeach; ?>
</ul>
<?php endif;

$html = ob_get_clean();
echo json_encode([
    'html'        => $html,
    'contadores'  => $contadores,
    'total'       => $total,
    'page'        => $page,
    'per_page'    => $per_page,
    'total_pages' => $total_pages,
]);
