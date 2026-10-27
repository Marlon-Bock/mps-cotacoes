<?php
/**
 * AJAX endpoint — GET /api/admin/gerenciador/stats
 * Estatísticas consolidadas de negócio: total por produto, breakdown por
 * status e rankings de imobiliárias/atendentes, filtrados por período
 * (data de criação), atendente e imobiliária.
 * Retorna JSON { produto_totais, contadores, ranking_imobiliarias_html, ranking_atendentes_html }
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');

$data_de       = trim($_GET['data_de']       ?? '');
$data_ate      = trim($_GET['data_ate']      ?? '');
$atendente_id  = max(0, (int) ($_GET['atendente_id'] ?? 0));
$imobiliaria   = trim($_GET['imobiliaria']   ?? '');
$status_rapido = trim($_GET['status_rapido'] ?? '');

if ($data_de  && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_de))  $data_de  = '';
if ($data_ate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_ate)) $data_ate = '';

$rows = admin_cotacoes_buscar_tempos();

// ── Filtros (período por data de criação, atendente, imobiliária) ────────────
if ($data_de !== '')      $rows = array_values(array_filter($rows, fn($r) => substr($r['criado_em'] ?? '', 0, 10) >= $data_de));
if ($data_ate !== '')     $rows = array_values(array_filter($rows, fn($r) => substr($r['criado_em'] ?? '', 0, 10) <= $data_ate));
if ($atendente_id > 0)    $rows = array_values(array_filter($rows, fn($r) => (int) ($r['atendente_id'] ?? 0) === $atendente_id));
if ($imobiliaria !== '')  $rows = array_values(array_filter($rows, fn($r) => mb_stripos($r['imob_nome'] ?? '', $imobiliaria) !== false));

// ── Totais por produto + breakdown de status (ANTES do filtro de status rápido) ──
$produto_totais = ['fianca' => 0, 'auto' => 0, 'titulo' => 0, 'condominial' => 0, 'incendio' => 0, 'geral' => count($rows)];
foreach ($rows as $r) {
    $origem = $r['origem'] ?? 'fianca';
    if (isset($produto_totais[$origem])) $produto_totais[$origem]++;
}

$cont = admin_cotacoes_contadores($rows);

// ── Filtro de status rápido (afeta só os rankings) ────────────────────────────
$rows_ranking = admin_cotacoes_filtrar_status($rows, $status_rapido);
$total_ranking = count($rows_ranking);

// ── Ranking de imobiliárias ────────────────────────────────────────────────────
$grupo_imob = [];
foreach ($rows_ranking as $r) {
    $id = (int) ($r['imobiliaria_id'] ?? 0);
    if ($id <= 0) continue;
    if (!isset($grupo_imob[$id])) $grupo_imob[$id] = ['nome' => $r['imob_nome'] ?? '—', 'total' => 0];
    $grupo_imob[$id]['total']++;
}
usort($grupo_imob, fn($a, $b) => $b['total'] <=> $a['total']);
$ranking_imob = array_slice($grupo_imob, 0, 10);

// ── Ranking de atendentes (+ tempo médio de resolução) ────────────────────────
$grupo_atend = [];
foreach ($rows_ranking as $r) {
    $id = (int) ($r['atendente_id'] ?? 0);
    if ($id <= 0) continue;
    if (!isset($grupo_atend[$id])) {
        $grupo_atend[$id] = [
            'nome' => $r['atendente_nome'] ?? '—', 'avatar' => $r['atendente_avatar'] ?? null,
            'total' => 0, 'soma_segundos' => 0, 'qtd_finalizadas' => 0,
        ];
    }
    $grupo_atend[$id]['total']++;

    $data_fim = $r['data_status_concluido'] ?? $r['data_status_aprovado'] ?? $r['data_status_recusado'] ?? null;
    if ($data_fim && !empty($r['criado_em'])) {
        $segundos = strtotime($data_fim) - strtotime($r['criado_em']);
        if ($segundos > 0) {
            $grupo_atend[$id]['soma_segundos']    += $segundos;
            $grupo_atend[$id]['qtd_finalizadas']++;
        }
    }
}
foreach ($grupo_atend as $id => $g) {
    $grupo_atend[$id]['tempo_medio_segundos'] = $g['qtd_finalizadas'] > 0 ? (int) ($g['soma_segundos'] / $g['qtd_finalizadas']) : null;
}
usort($grupo_atend, fn($a, $b) => $b['total'] <=> $a['total']);
$ranking_atend = array_slice($grupo_atend, 0, 10);

// ── Render HTML dos rankings (grid — sem <table>) ─────────────────────────────
function gerenciador_render_ranking_imob(array $itens, int $total): string
{
    ob_start();

    $max = $itens[0]['total'] ?? 0;
    if (empty($itens)): ?>
    <li class="tk-empty">Nenhum dado no período selecionado.</li>
    <?php else:
        foreach ($itens as $pos => $item):
            $percentual = $total > 0 ? ($item['total'] / $total) * 100 : 0;
            $largura    = $max > 0 ? ($item['total'] / $max) * 100 : 0;
    ?>
    <li class="tk-rank-item">
        <span class="tk-rank-pos"><?= e($pos + 1) ?></span>
        <div class="tk-rank-body">
            <div class="tk-rank-top">
                <span class="tk-rank-nome"><?= e($item['nome']) ?></span>
                <span class="tk-rank-val"><?= e(number_format($item['total'], 0, ',', '.')) ?> · <?= e(number_format($percentual, 1, ',', '.')) ?>%</span>
            </div>
            <div class="tk-dash-bar"><span class="tk-dash-fill" data-w="<?= e(round($largura, 1)) ?>"></span></div>
        </div>
    </li>
    <?php endforeach;
    endif;

    return ob_get_clean();
}

function gerenciador_render_ranking_atend(array $itens, int $total): string
{
    ob_start();

    $max = $itens[0]['total'] ?? 0;
    if (empty($itens)): ?>
    <li class="tk-empty">Nenhum dado no período selecionado.</li>
    <?php else:
        foreach ($itens as $pos => $item):
            $percentual = $total > 0 ? ($item['total'] / $total) * 100 : 0;
            $largura    = $max > 0 ? ($item['total'] / $max) * 100 : 0;
            $tempo      = $item['tempo_medio_segundos'] !== null ? formatar_duracao($item['tempo_medio_segundos']) : '—';
    ?>
    <li class="tk-rank-item">
        <span class="tk-rank-pos"><?= e($pos + 1) ?></span>
        <div class="tk-rank-body">
            <div class="tk-rank-top">
                <span class="tk-rank-nome tk-rank-nome-av"><?= avatar_sm($item['avatar'] ?? null, $item['nome']) ?><?= e($item['nome']) ?></span>
                <span class="tk-rank-val"><?= e(number_format($item['total'], 0, ',', '.')) ?> · <?= e(number_format($percentual, 1, ',', '.')) ?>%</span>
            </div>
            <div class="tk-dash-bar"><span class="tk-dash-fill" data-w="<?= e(round($largura, 1)) ?>"></span></div>
            <span class="tk-rank-meta"><?= icon('clock') ?> Tempo médio: <?= e($tempo) ?></span>
        </div>
    </li>
    <?php endforeach;
    endif;

    return ob_get_clean();
}

$ranking_imobiliarias_html = gerenciador_render_ranking_imob($ranking_imob, $total_ranking);
$ranking_atendentes_html   = gerenciador_render_ranking_atend($ranking_atend, $total_ranking);

echo json_encode([
    'produto_totais'             => $produto_totais,
    'contadores'                 => $cont,
    'ranking_imobiliarias_html'  => $ranking_imobiliarias_html,
    'ranking_atendentes_html'    => $ranking_atendentes_html,
]);
