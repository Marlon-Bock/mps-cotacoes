<?php
/**
 * AJAX endpoint — GET /api/admin/faq-atendimentos/listar
 * Lista paginada e filtrável dos atendimentos do chat guiado do FAQ.
 * Retorna JSON { html, total, page, per_page, total_pages, contadores }
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');

$status_rapido = trim($_GET['status_rapido'] ?? '');
if (!in_array($status_rapido, ['em_andamento', 'concluido', 'chamado_aberto'], true)) $status_rapido = '';

$imobiliaria_id = (int) ($_GET['imobiliaria_id'] ?? 0);
$busca          = trim($_GET['busca'] ?? '');

$page     = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 20;
$offset   = ($page - 1) * $per_page;

$where  = '1=1';
$tipos  = '';
$params = [];

if ($status_rapido === 'em_andamento')  { $where .= ' AND fa.concluido = 0'; }
if ($status_rapido === 'concluido')     { $where .= ' AND fa.concluido = 1'; }
if ($status_rapido === 'chamado_aberto') { $where .= ' AND fa.chamado_aberto = 1'; }

if ($imobiliaria_id > 0) {
    $where   .= ' AND fa.imobiliaria_id = ?';
    $tipos   .= 'i';
    $params[] = $imobiliaria_id;
}
if ($busca !== '') {
    $where   .= ' AND (fa.usuario_nome LIKE ? OR fa.nome_informado LIKE ? OR fa.telefone_informado LIKE ? OR fa.imobiliaria_informada LIKE ?)';
    $tipos   .= 'ssss';
    $params[] = "%{$busca}%";
    $params[] = "%{$busca}%";
    $params[] = "%{$busca}%";
    $params[] = "%{$busca}%";
}

$atendimentos = [];
$total        = 0;
$contadores   = ['todos' => 0, 'em_andamento' => 0, 'concluido' => 0, 'chamado_aberto' => 0];

try {
    $sql = "SELECT fa.id, fa.usuario_nome, fa.usuario_tipo, fa.nome_informado, fa.telefone_informado,
                   fa.imobiliaria_informada, fa.caminho, fa.chamado_aberto, fa.concluido,
                   fa.criado_em, fa.atualizado_em, i.nome AS imobiliaria_nome
              FROM faq_atendimentos fa
              LEFT JOIN imobiliarias i ON i.id = fa.imobiliaria_id
             WHERE {$where}
          ORDER BY fa.atualizado_em DESC
             LIMIT ? OFFSET ?";
    $atendimentos = $tipos !== ''
        ? db_fetch_all($sql, [$tipos . 'ii', ...$params, $per_page, $offset])
        : db_fetch_all($sql, ['ii', $per_page, $offset]);

    $sqlCount = "SELECT COUNT(*) AS total FROM faq_atendimentos fa WHERE {$where}";
    $total = (int) ($tipos !== ''
        ? (db_fetch_one($sqlCount, [$tipos, ...$params])['total'] ?? 0)
        : (db_fetch_one($sqlCount)['total'] ?? 0));

    $contadores['todos']          = (int) (db_fetch_one('SELECT COUNT(*) AS total FROM faq_atendimentos')['total'] ?? 0);
    $contadores['em_andamento']   = (int) (db_fetch_one('SELECT COUNT(*) AS total FROM faq_atendimentos WHERE concluido = 0')['total'] ?? 0);
    $contadores['concluido']      = (int) (db_fetch_one('SELECT COUNT(*) AS total FROM faq_atendimentos WHERE concluido = 1')['total'] ?? 0);
    $contadores['chamado_aberto'] = (int) (db_fetch_one('SELECT COUNT(*) AS total FROM faq_atendimentos WHERE chamado_aberto = 1')['total'] ?? 0);
} catch (\Throwable $e) {
    error_log('[admin-faq-atendimentos-listar] ' . $e->getMessage());
}

$total_pages = max(1, (int) ceil($total / $per_page));

ob_start();

if (empty($atendimentos)): ?>
<div class="admin-cot-empty">Nenhum atendimento encontrado<?= ($status_rapido !== '' || $imobiliaria_id > 0 || $busca !== '') ? ' para os filtros aplicados' : '' ?>.</div>
<?php else: ?>
<?php foreach ($atendimentos as $a):
    $usuarioTipoLabel = $a['usuario_tipo'] === 'admin' ? 'MPS' : ($a['imobiliaria_nome'] ?? 'Imobiliária');
    $atualizado = $a['atualizado_em'] ? date('d/m/Y H:i', strtotime($a['atualizado_em'])) : '—';

    if ((int) $a['concluido'] === 1) {
        $statusLabel = 'Concluído';
        $statusPill  = 'status-pill-aprovado';
    } elseif ((int) $a['chamado_aberto'] === 1) {
        $statusLabel = 'Abriu chamado';
        $statusPill  = 'status-pill-pendente';
    } else {
        $statusLabel = 'Em andamento';
        $statusPill  = 'status-pill-andamento';
    }

    $dados = array_filter([
        $a['nome_informado']        ? 'Nome: ' . $a['nome_informado']               : null,
        $a['telefone_informado']    ? 'Tel: ' . $a['telefone_informado']            : null,
        $a['imobiliaria_informada'] ? 'Imob: ' . $a['imobiliaria_informada']        : null,
    ]);
?>
<div class="admin-cot-row admin-cot-row-faq-atendimentos">
    <div class="admin-cot-cell">
        <span class="admin-cot-cell-nome"><?= e($a['usuario_nome'] ?? '—') ?></span><br>
        <span class="admin-cot-cell-vazio"><?= e($usuarioTipoLabel) ?></span>
    </div>
    <div class="admin-cot-cell">
        <?php if ($dados): ?>
            <?= implode('<br>', array_map('e', $dados)) ?>
        <?php else: ?>
            <span class="admin-cot-cell-vazio">Nada informado</span>
        <?php endif; ?>
    </div>
    <div class="admin-cot-cell admin-cot-cell-nome">
        <?php if (!empty($a['caminho'])): ?>
            <?= e($a['caminho']) ?>
        <?php else: ?>
            <span class="admin-cot-cell-vazio">—</span>
        <?php endif; ?>
    </div>
    <div class="admin-cot-cell"><span class="status-pill <?= $statusPill ?>"><?= $statusLabel ?></span></div>
    <div class="admin-cot-cell"><?= e($atualizado) ?></div>
</div>
<?php endforeach; ?>
<?php endif;

$html = ob_get_clean();

echo json_encode([
    'html'        => $html,
    'total'       => $total,
    'page'        => $page,
    'per_page'    => $per_page,
    'total_pages' => $total_pages,
    'contadores'  => $contadores,
]);
