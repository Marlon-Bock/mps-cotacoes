<?php
/**
 * POST /api/cotacao/fianca/{protocolo}/seguradoras/{seguradora}/reanalisar
 * Reanálise de uma cotação recusada com um pretendente adicional como
 * solidário. Só admin MPS (ação de negócio). Só ENFILEIRA — a reanálise
 * passa pela seguradora de novo (~1-2 min); o card mostra "Reanalisando".
 *
 * Body JSON: { cpf_cnpj, nome_completo }
 */
$user = require_admin();
header('Content-Type: application/json; charset=utf-8');
csrf_verify();

require_once BASE_PATH . '/src/helpers/api.php';
$seguradora = api_seguradora_ou_404((string) ($params['seguradora'] ?? ''));
$ficha      = api_ficha_autorizada($user, (string) ($params['protocolo'] ?? ''));

$body = json_decode(file_get_contents('php://input'), true) ?: [];
$cpf  = preg_replace('/\D/', '', (string) ($body['cpf_cnpj'] ?? ''));
$nome = trim((string) ($body['nome_completo'] ?? ''));
if ($nome === '') {
    json_response(['success' => false, 'message' => 'Informe o nome do pretendente adicional.'], 422);
}
if (!validar_cpf($cpf) && !validar_cnpj($cpf)) {
    json_response(['success' => false, 'message' => 'CPF/CNPJ do pretendente adicional inválido.'], 422);
}
if (api_ficha_encerrada($ficha)) {
    json_response(['success' => false, 'message' => 'Essa cotação está encerrada (' . $ficha['status'] . ').'], 409);
}
if (api_fila_ativa((int) $ficha['id']) !== null) {
    json_response(['success' => false, 'message' => 'A seguradora ainda está processando essa cotação. Aguarde terminar e tente de novo.'], 409);
}

api_fila_enfileirar('reanalise', (int) $ficha['id'], $seguradora, ['cpf' => $cpf, 'nome' => $nome]);
api_historico(
    (int) $ficha['id'], 'ALTERACAO_STATUS', 'Reanálise solicitada na ' . api_seguradora_nome($seguradora),
    'Pretendente adicional como solidário: ' . $nome . '.',
    api_historico_ator($user), null, true
);

json_response(['success' => true, 'enfileirado' => true]);
