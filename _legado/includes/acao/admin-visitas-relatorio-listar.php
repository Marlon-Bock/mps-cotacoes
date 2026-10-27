<?php
/**
 * AJAX endpoint — GET /api/admin/visitas-relatorio/listar
 * Lista as visitas já registradas hoje pelo usuário logado.
 * Retorna JSON { html, total }
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');

$itens = [];
try {
    $itens = db_fetch_all(
        "SELECT rvi.*
         FROM relatorio_visitas_itens rvi
         JOIN relatorios_visitas rv ON rv.id = rvi.relatorio_id
         WHERE rv.usuario_id = ? AND rv.data_relatorio = CURDATE()
         ORDER BY rvi.id DESC",
        ['i', (int) $current_user->uid]
    );
} catch (Throwable $e) {
    error_log('[visitas-relatorio-listar] ' . $e->getMessage());
}

// Corretores que foram junto, por item (batch, mesmo padrão de $extras_map
// em admin-visitas-agendar-listar.php) — só pra prefill do formulário de
// edição, não aparece nesta lista.
$extras_map = [];
$item_ids = array_map(static fn($i) => (int) $i['id'], $itens);
if (!empty($item_ids)) {
    $inPh = implode(',', array_fill(0, count($item_ids), '?'));
    $inTp = str_repeat('i', count($item_ids));
    try {
        $extras = db_fetch_all(
            "SELECT item_id, usuario_id FROM relatorio_visitas_itens_corretores WHERE item_id IN ({$inPh})",
            [$inTp, ...$item_ids]
        );
        foreach ($extras as $ex) { $extras_map[(int) $ex['item_id']][] = (int) $ex['usuario_id']; }
    } catch (Throwable $e) {
        error_log('[visitas-relatorio-listar] extras: ' . $e->getMessage());
    }
}

$seguros_labels = [
    'seguro_fianca'   => 'Fiança',
    'seguro_cap'      => 'Título Cap.',
    'seguro_incendio' => 'Incêndio',
    'seguro_vida'     => 'Vida',
    'seguro_auto'     => 'Auto',
];

ob_start();

if (empty($itens)): ?>
<div class="panel-body">
    <div class="empty-state">
        <div class="empty-state-icon"><?= icon('clipboard-text') ?></div>
        <p>Nenhuma visita registrada hoje ainda.</p>
    </div>
</div>
<?php else: ?>
<div class="visita-hoje-list">
    <?php foreach ($itens as $item):
        $seguros_marcados = [];
        foreach ($seguros_labels as $col => $label) {
            if (!empty($item[$col])) $seguros_marcados[] = $label;
        }
        $itemParaJson = $item;
        $itemParaJson['corretores_extra'] = $extras_map[(int) $item['id']] ?? [];
        $itemJson = json_encode($itemParaJson, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT);
    ?>
    <div class="visita-hoje-card" data-item="<?= e($itemJson) ?>">
        <div class="visita-hoje-card-top">
            <strong><?= e($item['imobiliaria_nome'] ?: '—') ?></strong>
            <span class="visita-hoje-card-horario">
                <?= icon('clock') ?>
                <?= e(substr($item['horario_entrada'] ?? '', 0, 5)) ?> – <?= e(substr($item['horario_saida'] ?? '', 0, 5)) ?>
            </span>
        </div>
        <div class="visita-hoje-card-meta">
            <span><?= e($item['motivo_visita'] ?: '—') ?></span>
            <?php if ($seguros_marcados): ?>
                <span class="visita-hoje-card-seguros"><?= e(implode(', ', $seguros_marcados)) ?></span>
            <?php endif; ?>
            <?php if (!empty($item['falta_material'])): ?>
                <span class="status-pill status-pill-recusado">Falta material</span>
            <?php endif; ?>
        </div>
        <div class="visita-hoje-card-acoes">
            <button type="button" class="btn btn-secondary btn-sm" data-action="visita-editar">
                <?= icon('note-pencil') ?> Editar
            </button>
            <button type="button" class="btn btn-danger-outline btn-sm" data-action="visita-excluir" data-id="<?= (int) $item['id'] ?>">
                <?= icon('x') ?>
            </button>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif;

$html = ob_get_clean();
echo json_encode(['html' => $html, 'total' => count($itens)]);
