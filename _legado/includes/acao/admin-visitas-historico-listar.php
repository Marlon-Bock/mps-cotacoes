<?php
/**
 * AJAX endpoint — GET /api/admin/visitas-historico/listar
 * Lista paginada e filtrável de relatorio_visitas_itens (o log real de
 * visitas), somente leitura. Sem navegar nem alterar a URL de /admin/visitas.
 * Retorna JSON { html, total, page, per_page, total_pages }
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');

$data_de     = trim($_GET['data_de']  ?? '');
$data_ate    = trim($_GET['data_ate'] ?? '');
$corretor_id = (int) ($_GET['corretor_id'] ?? 0);
$imobiliaria = trim($_GET['imobiliaria'] ?? '');
$page        = max(1, (int) ($_GET['page'] ?? 1));
$per_page    = 20;
$offset      = ($page - 1) * $per_page;

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_de))  $data_de  = '';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_ate)) $data_ate = '';

// Sem data informada = sem restrição de período (mesma regra dos outros filtros).
$where  = '1=1';
$tipos  = '';
$params = [];

if ($data_de !== '' && $data_ate !== '') {
    $where   .= ' AND rv.data_relatorio BETWEEN ? AND ?';
    $tipos   .= 'ss';
    $params[] = $data_de;
    $params[] = $data_ate;
} elseif ($data_de !== '') {
    $where   .= ' AND rv.data_relatorio >= ?';
    $tipos   .= 's';
    $params[] = $data_de;
} elseif ($data_ate !== '') {
    $where   .= ' AND rv.data_relatorio <= ?';
    $tipos   .= 's';
    $params[] = $data_ate;
}

if ($corretor_id > 0) {
    // Casa tanto quem registrou o relatório (principal) quanto quem foi
    // junto como corretor adicional (relatorio_visitas_itens_corretores) —
    // mesmo padrão do filtro corretor_id em admin-visitas-agendar-listar.php.
    $where   .= ' AND (rv.usuario_id = ? OR EXISTS (SELECT 1 FROM relatorio_visitas_itens_corretores c WHERE c.item_id = rvi.id AND c.usuario_id = ?))';
    $tipos   .= 'ii';
    $params[] = $corretor_id;
    $params[] = $corretor_id;
}
if ($imobiliaria !== '') {
    $where   .= ' AND rvi.imobiliaria_nome LIKE ?';
    $tipos   .= 's';
    $params[] = '%' . $imobiliaria . '%';
}

$itens = [];
$total = 0;
try {
    $itens = db_fetch_all(
        "SELECT rvi.*, rv.data_relatorio, COALESCE(u.username, u.email) AS corretor_nome, u.avatar AS corretor_avatar
         FROM relatorio_visitas_itens rvi
         JOIN relatorios_visitas rv ON rv.id = rvi.relatorio_id
         JOIN usuario u ON u.id = rv.usuario_id
         WHERE {$where}
         ORDER BY rv.data_relatorio DESC, rvi.id DESC
         LIMIT ? OFFSET ?",
        [$tipos . 'ii', ...$params, $per_page, $offset]
    );
    $sqlCount = "SELECT COUNT(*) AS total FROM relatorio_visitas_itens rvi
         JOIN relatorios_visitas rv ON rv.id = rvi.relatorio_id
         JOIN usuario u ON u.id = rv.usuario_id
         WHERE {$where}";
    $total = (int) ($tipos !== ''
        ? (db_fetch_one($sqlCount, [$tipos, ...$params])['total'] ?? 0)
        : (db_fetch_one($sqlCount)['total'] ?? 0));
} catch (Throwable $e) {
    error_log('[visitas-historico-listar] ' . $e->getMessage());
}

// Corretores adicionais por item (batch, mesmo padrão de $extras_map em
// admin-visitas-agendar-listar.php).
$extras_map = [];
$item_ids = array_map(static fn($i) => (int) $i['id'], $itens);
if (!empty($item_ids)) {
    $inPh = implode(',', array_fill(0, count($item_ids), '?'));
    $inTp = str_repeat('i', count($item_ids));
    try {
        $extras = db_fetch_all(
            "SELECT c.item_id, u.id AS usuario_id, u.username, u.avatar
               FROM relatorio_visitas_itens_corretores c
               JOIN usuario u ON u.id = c.usuario_id
              WHERE c.item_id IN ({$inPh})
           ORDER BY u.username ASC",
            [$inTp, ...$item_ids]
        );
        foreach ($extras as $ex) { $extras_map[(int) $ex['item_id']][] = $ex; }
    } catch (Throwable $e) {
        error_log('[visitas-historico-listar] extras: ' . $e->getMessage());
    }
}

$total_pages = $total > 0 ? (int) ceil($total / $per_page) : 1;

$seguros_labels = [
    'seguro_fianca'   => 'Fiança',
    'seguro_cap'      => 'Cap',
    'seguro_incendio' => 'Incêndio',
    'seguro_vida'     => 'Vida',
    'seguro_auto'     => 'Auto',
];

function visita_sim_nao(bool $sim): string
{
    return $sim
        ? '<span class="status-pill status-pill-aprovado">Sim</span>'
        : '<span class="admin-cot-cell-vazio">Não</span>';
}

ob_start();

if (empty($itens)): ?>
<div class="admin-cot-empty">Nenhuma visita encontrada para os filtros selecionados.</div>
<?php else: ?>
<div class="admin-cot-grid-wrap">
    <div class="admin-cot-row admin-cot-row-visitas admin-cot-head">
        <div class="admin-cot-cell">Data</div>
        <div class="admin-cot-cell admin-cot-cell-nome">Imobiliária</div>
        <div class="admin-cot-cell">Corretor</div>
        <div class="admin-cot-cell">Entrada</div>
        <div class="admin-cot-cell">Saída</div>
        <div class="admin-cot-cell">Gerente</div>
        <div class="admin-cot-cell">Atendente</div>
        <div class="admin-cot-cell">Motivo</div>
        <div class="admin-cot-cell">Motivo (desc.)</div>
        <?php foreach ($seguros_labels as $label): ?>
        <div class="admin-cot-cell"><?= e($label) ?></div>
        <?php endforeach; ?>
        <div class="admin-cot-cell">Outros</div>
        <div class="admin-cot-cell">Outros (desc.)</div>
        <div class="admin-cot-cell">Falta material</div>
        <div class="admin-cot-cell">Materiais</div>
        <div class="admin-cot-cell">Observações</div>
    </div>
    <?php foreach ($itens as $item):
        $tem_falta   = !empty($item['falta_material']);
        $outros_txt  = trim((string) ($item['outros_seguros'] ?? ''));
        $extras_do_item = $extras_map[(int) $item['id']] ?? [];
        // Avatares empilhados: principal (quem registrou) primeiro, depois
        // os extras (mesmo padrão de .mnd-resp-av/.mnd-resp-mais usado em
        // admin-visitas-agendar-listar.php).
        $avatares_visita = array_merge(
            [['username' => $item['corretor_nome'], 'avatar' => $item['corretor_avatar'] ?? null]],
            array_map(static fn($ex) => ['username' => $ex['username'], 'avatar' => $ex['avatar']], $extras_do_item)
        );
    ?>
    <div class="admin-cot-row admin-cot-row-visitas">
        <div class="admin-cot-cell"><?= e(date_to_br($item['data_relatorio'])) ?></div>
        <div class="admin-cot-cell admin-cot-cell-nome"><?= e($item['imobiliaria_nome'] ?: '—') ?></div>
        <div class="admin-cot-cell">
            <span class="mnd-resp">
                <?php foreach (array_slice($avatares_visita, 0, 4) as $av): ?>
                <span class="mnd-resp-av" title="<?= e($av['username']) ?>"><?= avatar_sm($av['avatar'] ?? null, $av['username']) ?></span>
                <?php endforeach; ?>
            </span>
            <?php if (count($avatares_visita) > 4): ?><span class="mnd-resp-mais">+<?= count($avatares_visita) - 4 ?></span><?php endif; ?>
        </div>
        <div class="admin-cot-cell"><?= e(substr($item['horario_entrada'] ?? '', 0, 5) ?: '—') ?></div>
        <div class="admin-cot-cell"><?= e(substr($item['horario_saida'] ?? '', 0, 5) ?: '—') ?></div>
        <div class="admin-cot-cell"><?= e($item['gerente'] ?: '—') ?></div>
        <div class="admin-cot-cell"><?= e($item['atendente'] ?: '—') ?></div>
        <div class="admin-cot-cell"><?= e($item['motivo_visita'] ?: '—') ?></div>
        <div class="admin-cot-cell" title="<?= e($item['motivo_descricao'] ?? '') ?>"><?= e($item['motivo_descricao'] ?: '—') ?></div>
        <?php foreach ($seguros_labels as $col => $label): ?>
        <div class="admin-cot-cell"><?= visita_sim_nao(!empty($item[$col])) ?></div>
        <?php endforeach; ?>
        <div class="admin-cot-cell"><?= visita_sim_nao($outros_txt !== '') ?></div>
        <div class="admin-cot-cell" title="<?= e($outros_txt) ?>"><?= e($outros_txt ?: '—') ?></div>
        <div class="admin-cot-cell"><?= visita_sim_nao($tem_falta) ?></div>
        <div class="admin-cot-cell" title="<?= e($item['materiais_faltantes'] ?? '') ?>"><?= $tem_falta ? e($item['materiais_faltantes'] ?: '—') : '<span class="admin-cot-cell-vazio">—</span>' ?></div>
        <div class="admin-cot-cell admin-cot-cell-obs">
            <?php if (trim((string) ($item['observacoes'] ?? '')) !== ''): ?>
            <button type="button" class="admin-cot-obs-btn" data-action="ver-observacao" data-obs="<?= e($item['observacoes']) ?>" title="Ver observação completa"><?= icon('eye') ?></button>
            <?php else: ?>
            <span class="admin-cot-cell-vazio">—</span>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif;

$html = ob_get_clean();
echo json_encode([
    'html'        => $html,
    'total'       => $total,
    'page'        => $page,
    'per_page'    => $per_page,
    'total_pages' => $total_pages,
]);
