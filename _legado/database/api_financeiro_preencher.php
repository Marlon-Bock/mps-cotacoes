<?php
/**
 * Preenche a comissão (api_cotacoes.comissao_*) das contratações feitas antes
 * do Financeiro existir — migration 091. Pottencial: reconsulta a cotação
 * (commissionedAgents); Too: TOO_COMISSAO sobre o prêmio líquido.
 *
 * Uso: php database/api_financeiro_preencher.php [--dry-run]
 */
if (PHP_SAPI !== 'cli') {
    exit("Somente via CLI.\n");
}
require_once dirname(__DIR__) . '/src/bootstrap.php';
require_once BASE_PATH . '/src/helpers/api.php';

$dryRun = in_array('--dry-run', $argv ?? [], true);
$linhas = db_fetch_all(
    'SELECT seguro_fianca_id, seguradora, cotacao_externa_id, premio_liquido FROM api_cotacoes
     WHERE apolice_id IS NOT NULL AND comissao_valor IS NULL'
);

foreach ($linhas as $c) {
    $pct = $valor = null;
    if ($c['seguradora'] === 'pottencial') {
        $r = pottencial_consultar((string) $c['cotacao_externa_id']);
        if (!$r['ok']) {
            echo "Ficha {$c['seguro_fianca_id']}: falha na Pottencial — " . ($r['erro'] ?? '') . "\n";
            continue;
        }
        [$pct, $valor] = [$r['comissao_percentual'] ?? null, $r['comissao_valor'] ?? null];
    } elseif ($c['seguradora'] === 'too') {
        $pct   = round((float) env('TOO_COMISSAO', 0.20) * 100, 2);
        $valor = round((float) $c['premio_liquido'] * $pct / 100, 2);
    }
    echo "Ficha {$c['seguro_fianca_id']} ({$c['seguradora']}): " . ($valor === null ? 'sem comissão informada' : "{$pct}% = R$ {$valor}") . "\n";
    if (!$dryRun && $valor !== null) {
        db_execute(
            'UPDATE api_cotacoes SET comissao_percentual = ?, comissao_valor = ? WHERE seguro_fianca_id = ? AND seguradora = ?',
            ['ddis', $pct, $valor, (int) $c['seguro_fianca_id'], (string) $c['seguradora']]
        );
    }
}
