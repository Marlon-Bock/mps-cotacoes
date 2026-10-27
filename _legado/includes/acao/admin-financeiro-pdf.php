<?php
/**
 * GET /admin/financeiro/pdf
 * Relatório financeiro em PDF com os mesmos filtros da tela — ver
 * src/helpers/financeiro_pdf.php.
 */
$current_user = require_admin();

require_once BASE_PATH . '/src/helpers/api.php';
require_once BASE_PATH . '/src/helpers/api/financeiro.php';
require_once BASE_PATH . '/src/helpers/financeiro_pdf.php';

$filtros = api_financeiro_filtros($_GET);
$linhas  = api_financeiro_linhas($filtros);
$pdf     = financeiro_pdf($filtros, $linhas, api_financeiro_totais($linhas));

header('Content-Type: application/pdf');
header('Content-Disposition: inline; filename="relatorio-financeiro-' . date('Y-m-d') . '.pdf"');
header('Content-Length: ' . strlen($pdf));
echo $pdf;
