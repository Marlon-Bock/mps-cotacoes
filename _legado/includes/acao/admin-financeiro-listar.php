<?php
/**
 * GET /api/admin/financeiro/listar
 * Financeiro (AJAX): totais (cards), resumo por seguradora e as linhas das
 * contratações, já em HTML — mesmo esquema da /api/admin/cotacoes/listar.
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');

require_once BASE_PATH . '/src/helpers/api.php';
require_once BASE_PATH . '/src/helpers/api/financeiro.php';

$linhas = api_financeiro_linhas(api_financeiro_filtros($_GET));
$totais = api_financeiro_totais($linhas);

$reais = static fn($v): string => 'R$ ' . number_format((float) $v, 2, ',', '.');
$pct   = static fn($v): string => $v === null ? '—' : rtrim(rtrim(number_format((float) $v, 2, ',', ''), '0'), ',') . '%';
$data  = static fn($iso): string => $iso ? date('d/m/Y', strtotime((string) $iso)) : '—';
$pills = ['em_emissao' => 'status-pill-andamento', 'a_enviar' => 'status-pill-pendente', 'enviada' => 'status-pill-aprovado', 'cancelada' => 'status-pill-recusado'];

// ── Resumo do período: 5 números lado a lado ──
ob_start();
foreach ([
    ['Contratações', (string) $totais['qtd'], null],
    ['Prêmio total', $reais($totais['premio']), 'IOF ' . $reais($totais['iof'])],
    ['Prêmio líquido', $reais($totais['liquido']), 'Base da comissão'],
    ['Comissão', $reais($totais['comissao']), $totais['liquido'] > 0 ? 'Média ' . $pct($totais['comissao'] / $totais['liquido'] * 100) . ' do líquido' : null],
    ['Apólices a enviar', (string) $totais['a_enviar'], $totais['em_emissao'] . ' em emissão'],
] as [$rotulo, $valor, $detalhe]): ?>
<div class="fin-resumo-item">
    <span class="fin-resumo-rotulo"><?= e($rotulo) ?></span>
    <span class="fin-resumo-valor"><?= e($valor) ?></span>
    <?php if ($detalhe): ?><span class="fin-resumo-detalhe"><?= e($detalhe) ?></span><?php endif; ?>
</div>
<?php endforeach;
$html_resumo = ob_get_clean();

// ── Por seguradora: linha com participação no prêmio ──
ob_start();
foreach ($totais['por_seguradora'] as $nomeSeg => $s):
    $parte = $totais['premio'] > 0 ? round($s['premio'] / $totais['premio'] * 100) : 0; ?>
<div class="fin-seg-linha">
    <span class="fin-seg-nome"><span class="api-painel-cor" style="background:<?= e($s['cor']) ?>"></span><?= e($nomeSeg) ?></span>
    <span class="fin-seg-qtd"><?= (int) $s['qtd'] ?> <?= $s['qtd'] === 1 ? 'contratação' : 'contratações' ?></span>
    <span class="fin-seg-barra" title="<?= $parte ?>% do prêmio"><span style="width:<?= $parte ?>%;background:<?= e($s['cor']) ?>"></span></span>
    <span class="fin-seg-valor"><?= e($reais($s['premio'])) ?></span>
    <span class="fin-seg-comissao">Comissão <?= e($reais($s['comissao'])) ?></span>
</div>
<?php endforeach;
$html_seguradoras = ob_get_clean();

// ── Linhas ──
ob_start();
if (!$linhas): ?>
<div class="admin-cot-empty">Nenhuma contratação encontrada.</div>
<?php else:
    foreach ($linhas as $l): ?>
<div class="admin-cot-row admin-cot-row-fin">
    <div class="admin-cot-cell fin-pilha">
        <span class="fin-forte"><?= e($data($l['contratado_em'])) ?></span>
        <?php if ($l['vigencia_fim']): ?><span class="fin-sutil">até <?= e($data($l['vigencia_fim'])) ?></span><?php endif; ?>
    </div>
    <div class="admin-cot-cell fin-pilha">
        <a href="<?= e(url('/admin/cotacao/fianca/' . rawurlencode($l['protocolo']))) ?>" target="_blank" rel="noopener" class="proto-code"><?= e($l['protocolo']) ?></a>
        <span class="fin-sutil" title="<?= e($l['pretendente']) ?>"><?= e($l['pretendente']) ?></span>
    </div>
    <div class="admin-cot-cell" title="<?= e($l['imobiliaria']) ?>"><?= e($l['imobiliaria'] ?: '—') ?></div>
    <div class="admin-cot-cell fin-pilha">
        <span class="fin-forte"><span class="api-painel-cor" style="background:<?= e($l['cor']) ?>"></span><?= e($l['seguradora']) ?></span>
        <span class="fin-sutil" title="<?= e($l['apolice']) ?>"><?= $l['apolice'] !== '' ? 'Apólice ' . e($l['apolice']) : 'Apólice em emissão' ?></span>
    </div>
    <div class="admin-cot-cell fin-pilha fin-num">
        <span class="fin-forte"><?= e($reais($l['premio_total'])) ?></span>
        <span class="fin-sutil">IOF <?= e($reais($l['iof'])) ?></span>
    </div>
    <div class="admin-cot-cell fin-pilha">
        <span><?= e($l['forma'] ?: '—') ?></span>
        <span class="fin-sutil"><?= $l['parcelas'] > 1 ? e($l['parcelas'] . 'x de ' . $reais($l['premio_total'] / $l['parcelas'])) : 'Parcela única' ?></span>
    </div>
    <div class="admin-cot-cell fin-pilha fin-num">
        <span class="fin-forte"><?= e($reais($l['premio_liquido'])) ?></span>
        <?php if ($l['cotacao'] !== ''): ?><span class="fin-sutil">Cotação nº <?= e($l['cotacao']) ?></span><?php endif; ?>
    </div>
    <div class="admin-cot-cell fin-pilha fin-num">
        <span class="fin-forte"><?= e($reais($l['comissao_valor'])) ?></span>
        <span class="fin-sutil"><?= e($pct($l['comissao_pct'])) ?></span>
    </div>
    <div class="admin-cot-cell">
        <span class="status-pill <?= e($pills[$l['situacao']]) ?>"><?= e($l['situacao_label']) ?></span>
    </div>
</div>
<?php endforeach; ?>
<div class="admin-cot-row admin-cot-row-fin fin-total">
    <div class="admin-cot-cell">Total</div>
    <div class="admin-cot-cell"><?= (int) $totais['qtd'] ?> <?= $totais['qtd'] === 1 ? 'contratação' : 'contratações' ?></div>
    <div class="admin-cot-cell"></div>
    <div class="admin-cot-cell"></div>
    <div class="admin-cot-cell fin-num"><?= e($reais($totais['premio'])) ?></div>
    <div class="admin-cot-cell"></div>
    <div class="admin-cot-cell fin-num"><?= e($reais($totais['liquido'])) ?></div>
    <div class="admin-cot-cell fin-num"><?= e($reais($totais['comissao'])) ?></div>
    <div class="admin-cot-cell"></div>
</div>
<?php endif;
$html_linhas = ob_get_clean();

json_response([
    'success'     => true,
    'resumo'      => $html_resumo,
    'seguradoras' => $html_seguradoras,
    'html'        => $html_linhas,
    'total'       => $totais['qtd'],
]);
