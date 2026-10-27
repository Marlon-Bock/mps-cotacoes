<?php
/**
 * GET /api/cotacao/{protocolo}/titulo-pdf
 * Gera e envia o PDF personalizado da cotação de título de capitalização,
 * preenchido com os dados reais — mesma estrutura do formulário TC-1.0.2024.
 */
$user = require_auth();

$protocolo = $params['protocolo'] ?? '';
if (!$protocolo) {
    http_response_code(400);
    exit('Protocolo inválido.');
}

$resolvido = cotacao_resolver($protocolo);
if (!$resolvido || $resolvido['tipo'] !== 'titulo') {
    http_response_code(404);
    exit('Cotação não encontrada.');
}

if (!cotacao_pode_acessar($resolvido, $user)) {
    http_response_code(404);
    exit('Cotação não encontrada.');
}

$cotacao = db_fetch_one('SELECT * FROM titulo_capitalizacao WHERE protocolo = ?', ['s', $protocolo]);
if (!$cotacao) {
    http_response_code(404);
    exit('Cotação não encontrada.');
}

require_once BASE_PATH . '/src/helpers/cotacao_pdf.php';

$pdf = cotacao_titulo_montar_pdf($cotacao);
$pdf->Output('I', 'Cotacao_' . $protocolo . '.pdf');
