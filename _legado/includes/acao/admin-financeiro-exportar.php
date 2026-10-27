<?php
/**
 * GET /admin/financeiro/exportar
 * Planilha Excel (.xlsx) do Financeiro com os mesmos filtros da página:
 * cabeçalho, larguras, datas, moeda, % e linha de total (fórmulas).
 */
$current_user = require_admin();

require_once BASE_PATH . '/src/helpers/api.php';
require_once BASE_PATH . '/src/helpers/api/financeiro.php';
require_once BASE_PATH . '/src/helpers/xlsx.php';

$filtros = api_financeiro_filtros($_GET);
$linhas  = api_financeiro_linhas($filtros);

$periodo = $filtros['de'] || $filtros['ate']
    ? 'Contratações de ' . ($filtros['de'] ? date('d/m/Y', strtotime($filtros['de'])) : 'início') . ' a ' . ($filtros['ate'] ? date('d/m/Y', strtotime($filtros['ate'])) : 'hoje')
    : 'Todas as contratações';

$xlsx = xlsx_gerar('Financeiro', [
    'titulo'    => 'Financeiro — contratações pela API das seguradoras',
    'subtitulo' => $periodo . ' · gerado em ' . date('d/m/Y \à\s H:i') . ' · comissão sobre o prêmio líquido',
    // Só o que importa pro financeiro — o detalhe completo fica na tela.
    'colunas'   => [
        ['Contratação', 'data', 13],
        ['Protocolo', 'texto', 17],
        ['Imobiliária', 'texto', 30],
        ['Pretendente', 'texto', 28],
        ['Seguradora', 'texto', 16],
        ['Apólice', 'texto', 26],
        ['Vigência até', 'data', 13],
        ['Prêmio', 'moeda', 14],
        ['IOF', 'moeda', 12],
        ['Prêmio líquido', 'moeda', 15],
        ['Pagamento', 'texto', 14],
        ['Parcelas', 'inteiro', 10],
        ['Comissão %', 'percentual', 13],
        ['Comissão', 'moeda', 14],
        ['Situação', 'texto', 26],
    ],
    'linhas' => array_map(static fn(array $l): array => [
        $l['contratado_em'], $l['protocolo'], $l['imobiliaria'], $l['pretendente'], $l['seguradora'],
        $l['apolice'], $l['vigencia_fim'], $l['premio_total'], $l['iof'], $l['premio_liquido'], $l['forma'],
        $l['parcelas'], $l['comissao_pct'] ?? 0, $l['comissao_valor'], $l['situacao_label'],
    ], $linhas),
    'totais' => ['Prêmio', 'IOF', 'Prêmio líquido', 'Comissão'],
]);

header('Content-Type: application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
header('Content-Disposition: attachment; filename="financeiro-' . date('Y-m-d_His') . '.xlsx"');
header('Content-Length: ' . strlen($xlsx));
echo $xlsx;
