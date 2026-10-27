<?php
/**
 * GET /api/cotacao/{protocolo}/pdf
 * Gera e envia o PDF personalizado da cotação de seguro fiança, preenchido
 * com os dados reais — não é mais um `window.print()` da tela.
 */
$user = require_auth();

$protocolo = $params['protocolo'] ?? '';
if (!$protocolo) {
    http_response_code(400);
    exit('Protocolo inválido.');
}

$resolvido = cotacao_resolver($protocolo);
if (!$resolvido || $resolvido['tipo'] !== 'fianca') {
    http_response_code(404);
    exit('Cotação não encontrada.');
}

if (!cotacao_pode_acessar($resolvido, $user)) {
    http_response_code(404);
    exit('Cotação não encontrada.');
}

$cotacao = db_fetch_one(
    "SELECT sf.*, im.nome AS imob_nome,
            ua.username AS atendente_nome
     FROM seguro_fianca sf
     LEFT JOIN imobiliarias im ON im.id = sf.imobiliaria_id
     LEFT JOIN users ua ON ua.id = sf.usuario_id
     WHERE sf.protocolo = ?",
    ['s', $protocolo]
);
if (!$cotacao) {
    http_response_code(404);
    exit('Cotação não encontrada.');
}

$pretendentesAdicionais = [];
if (($cotacao['pretendenteAdicional'] ?? 'N') === 'S') {
    try {
        $pretendentesAdicionais = db_fetch_all(
            "SELECT * FROM pretendentes WHERE seguro_fianca_id = ? ORDER BY numero_pretendente ASC",
            ['i', (int) $cotacao['id']]
        );
    } catch (Throwable) {}
}

require_once BASE_PATH . '/src/helpers/cotacao_pdf.php';

$pdf = cotacao_fianca_montar_pdf($cotacao, $pretendentesAdicionais);
$pdf->Output('I', 'Cotacao_' . $protocolo . '.pdf');
