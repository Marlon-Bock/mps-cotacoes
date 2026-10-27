<?php
/**
 * AJAX endpoint — GET /api/admin/pipeline/listar
 * Recalcula o Kanban do pipeline conforme os filtros (data, imobiliária,
 * atendente) — mesma fonte de dados de /api/admin/cotacoes/listar
 * (admin_cotacoes_buscar_todas: fiança + auto + título + condomínio), sem
 * navegar nem alterar a URL de /admin/pipeline.
 * Retorna JSON { html }
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');

// ── Filtros ────────────────────────────────────────────────────────────────
$data_inicio  = $_GET['data_inicio']   ?? date('Y-m-d', strtotime('-6 months'));
$data_fim     = $_GET['data_fim']      ?? date('Y-m-d');
$filtro_imob  = (int) ($_GET['imobiliaria_id'] ?? 0);
$filtro_atend = (int) ($_GET['atendente_id']   ?? 0);

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_inicio)) {
    $data_inicio = date('Y-m-d', strtotime('-6 months'));
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_fim)) {
    $data_fim = date('Y-m-d');
}

// ── Definição das colunas do Kanban — mesmos 7 estágios das KPIs de
//    /admin/cotacoes, classificados via classificar_status_cotacao(). ───────
$colunas = [
    'pre_analise' => ['label' => 'Pré-análise',        'icon' => 'lock',              'cards' => []],
    'aguardando'  => ['label' => 'Aguardando Análise',  'icon' => 'clock',             'cards' => []],
    'em_analise'  => ['label' => 'Em Análise',          'icon' => 'magnifying-glass',  'cards' => []],
    'aprovados'   => ['label' => 'Aprovados',           'icon' => 'check-circle',      'cards' => []],
    'recusados'   => ['label' => 'Recusados',           'icon' => 'x',                 'cards' => []],
    'desistencias'=> ['label' => 'Desistências',        'icon' => 'sign-out',          'cards' => []],
    'concluidos'  => ['label' => 'Concluídas',          'icon' => 'check',             'cards' => []],
];

// ── Busca — fiança + auto + título + condomínio (mesma fonte da listagem de
//    cotações do admin), filtrada por data de cadastro / imobiliária / atendente ─
$rows = admin_cotacoes_buscar_todas();

$rows = array_values(array_filter($rows, function ($r) use ($data_inicio, $data_fim, $filtro_imob, $filtro_atend) {
    $data = substr((string) ($r['data_cadastro'] ?? ''), 0, 10);
    if ($data === '' || $data < $data_inicio || $data > $data_fim) return false;
    if ($filtro_imob > 0 && (int) ($r['imobiliaria_id'] ?? 0) !== $filtro_imob) return false;
    if ($filtro_atend > 0 && (int) ($r['atendente_id'] ?? 0) !== $filtro_atend) return false;
    return true;
}));

// ── Distribuição nas colunas ───────────────────────────────────────────────
foreach ($rows as $row) {
    $estagio = classificar_status_cotacao((string) ($row['status'] ?? ''));
    if ($estagio !== null && isset($colunas[$estagio])) {
        $colunas[$estagio]['cards'][] = $row;
    }
}

// ── Render do Kanban ──────────────────────────────────────────────────────
ob_start();
foreach ($colunas as $key => $col): ?>
<div class="pipeline-col pipeline-col--<?= e($key) ?>">
    <div class="pipeline-col-header">
        <span class="pipeline-col-icon"><?= icon($col['icon']) ?></span>
        <span class="pipeline-col-label"><?= e($col['label']) ?></span>
        <span class="pipeline-col-count"><?= count($col['cards']) ?></span>
    </div>

    <div class="pipeline-col-body">
        <?php if (empty($col['cards'])): ?>
        <div class="empty-state pipeline-empty">
            <div class="empty-state-icon"><?= icon('clipboard-text') ?></div>
            <p>Nenhuma cotação nesta etapa.</p>
        </div>
        <?php else: ?>
        <?php foreach ($col['cards'] as $card):
            $origem = $card['origem'] ?? 'fianca';
            $icone_tipo = match ($origem) {
                'auto'        => 'car',
                'titulo'      => 'piggy-bank',
                'condominial' => 'buildings',
                'residencial' => 'house-line',
                default       => 'house',
            };
        ?>
        <div class="pipeline-card">
            <div class="pipeline-card-top">
                <span class="pipeline-card-protocolo"><?= e($card['protocolo']) ?></span>
                <span class="pipeline-card-tipo pipeline-card-tipo--<?= e($origem) ?>">
                    <?= icon($icone_tipo) ?>
                </span>
            </div>
            <div class="pipeline-card-nome"><?= e($card['pretendente'] ?? '—') ?></div>
            <div class="pipeline-card-meta">
                <span class="pipeline-card-imob">
                    <?= icon('buildings') ?>
                    <?= e($card['imob_nome'] ?? '—') ?>
                </span>
                <span class="pipeline-card-data">
                    <?= icon('calendar-blank') ?>
                    <?= e(date_to_br($card['data_cadastro'])) ?>
                </span>
            </div>
            <a
                href="<?= e(cotacao_admin_url($card['protocolo'])) ?>"
                class="btn btn-secondary btn-sm btn-block pipeline-card-btn"
            >
                <?= icon('eye') ?> Ver cotação
            </a>
        </div>
        <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>
<?php endforeach;

$html = ob_get_clean();
echo json_encode(['html' => $html]);
