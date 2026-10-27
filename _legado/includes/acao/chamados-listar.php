<?php
/**
 * AJAX endpoint — GET /api/chamados/listar
 * Retorna JSON { html, contadores }
 */
$current_user = require_auth();
if (($current_user->role ?? '') === 'admin') { http_response_code(403); exit; }

header('Content-Type: application/json; charset=utf-8');

$imobiliaria_id = (int)($current_user->imobiliaria_id ?? 0);
$filtro_status  = trim($_GET['status']  ?? '');
$filtro_assunto = trim($_GET['assunto'] ?? '');

$allowed_statuses = ['aguardando_mps', 'aguardando_imob', 'encerrado'];
if (!in_array($filtro_status, $allowed_statuses, true)) $filtro_status = '';

// ── Busca todos os chamados da imobiliária ────────────────────────────────────
$rows = [];
try {
    $rows = db_fetch_all(
        "SELECT cs.id, cs.assunto, cs.titulo, cs.status, cs.criado_em, cs.atualizado_em,
                cs.atendente_nome, ua.avatar AS atendente_avatar
           FROM chamados_suporte cs
           LEFT JOIN usuario ua ON ua.id = cs.atendente_id
          WHERE cs.imobiliaria_id = ?
       ORDER BY cs.atualizado_em DESC",
        ['i', $imobiliaria_id]
    );
} catch (\Throwable $e) {}

// ── Contadores (antes de filtrar) ─────────────────────────────────────────────
$contadores = ['total' => count($rows), 'aguardando_mps' => 0, 'aguardando_imob' => 0, 'encerrado' => 0];
foreach ($rows as $r) {
    $st = $r['status'] ?? '';
    if (isset($contadores[$st])) $contadores[$st]++;
}

// ── Filtros ───────────────────────────────────────────────────────────────────
if ($filtro_status)  $rows = array_values(array_filter($rows, fn($r) => $r['status'] === $filtro_status));
if ($filtro_assunto) $rows = array_values(array_filter($rows, fn($r) => mb_stripos(($r['assunto'] ?? '') . ' ' . ($r['titulo'] ?? ''), $filtro_assunto) !== false));

// ── Render HTML ───────────────────────────────────────────────────────────────
ob_start();

if (empty($rows)): ?>
<div class="admin-cot-empty">Nenhum chamado encontrado.</div>
<?php else:
    foreach ($rows as $c):
        $id           = (int)$c['id'];
        $protocolo    = chamado_protocolo($id, $c['criado_em']);
        $titulo       = e($c['titulo']  ?? '—');
        $assunto      = e($c['assunto'] ?? '—');
        $status       = (string)($c['status'] ?? '');
        $atualizado   = $c['atualizado_em'] ? date('d/m/Y H:i', strtotime($c['atualizado_em'])) : '—';
        $atendente    = trim($c['atendente_nome'] ?? '');
?>
<div class="admin-cot-row admin-cot-row-chamados">
    <div class="admin-cot-cell"><span class="proto-code"><?= $protocolo ?></span></div>
    <div class="admin-cot-cell admin-cot-cell-nome"><?= $titulo ?></div>
    <div class="admin-cot-cell"><?= $assunto ?></div>
    <div class="admin-cot-cell"><span class="status-pill <?= chamado_status_pill($status) ?>"><?= chamado_status_label($status) ?></span></div>
    <div class="admin-cot-cell">
        <?php if ($atendente !== ''): ?>
            <span class="atendente-chip"><?= avatar_sm($c['atendente_avatar'] ?? null, $atendente) ?> <?= e($atendente) ?></span>
        <?php else: ?>
            <span class="admin-cot-cell-vazio">Não atribuído</span>
        <?php endif; ?>
    </div>
    <div class="admin-cot-cell" style="color:var(--color-gray-500)"><?= $atualizado ?></div>
    <div class="admin-cot-cell">
        <a href="<?= e(BASE_URI) ?>/chamado/<?= $protocolo ?>" class="btn btn-secondary btn-sm"><?= icon('eye') ?> Ver detalhes</a>
    </div>
</div>
<?php endforeach;
endif;

$html = ob_get_clean();
echo json_encode(['html' => $html, 'contadores' => $contadores]);
