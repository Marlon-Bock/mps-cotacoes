<?php
/**
 * POST /api/cotacao/fianca/{protocolo}/seguradoras/{seguradora}/emitir
 * O cliente (imobiliária) aceita uma das propostas enviadas pela MPS:
 * valida os dados do proprietário (locador) e ENFILEIRA a emissão — o
 * worker inclui o locador na cotação, envia a proposta e aceita (o aceite
 * emite a apólice). A ficha vai pra 'Aprovado' e a MPS envia a apólice
 * quando sair. Também serve pra tentar de novo se a emissão falhou.
 *
 * Body JSON: { proprietario: { nome_completo, cpf_cnpj, email, tel_celular,
 *              cep, endereco, numero, complemento, bairro, cidade, uf } }
 */
$user = require_auth();
header('Content-Type: application/json; charset=utf-8');
csrf_verify();

require_once BASE_PATH . '/src/helpers/api.php';
$seguradora = api_seguradora_ou_404((string) ($params['seguradora'] ?? ''));
// Emitir apólice é "aceitar" a cotação — mesma permissão de cotacao-aprovar.php.
$ficha = api_ficha_autorizada($user, (string) ($params['protocolo'] ?? ''), ['perm_fianca', 'perm_aceitar']);
if (($user->role ?? '') !== 'imobiliaria') {
    json_response(['success' => false, 'message' => 'Quem aceita a proposta é o cliente, no detalhe da cotação.'], 403);
}
// Só proposta enviada pela MPS (aguardando) — ou a já aceita, pra tentar de novo.
$aceita = api_proposta_aceita((int) $ficha['id']);
if (!api_proposta_pendente((int) $ficha['id'], $seguradora) && ($aceita['seguradora'] ?? null) !== $seguradora) {
    json_response(['success' => false, 'message' => 'Essa proposta não está mais disponível. Atualize a página.'], 409);
}

if (api_emissao_presa((int) $ficha['id'], api_cotacao((int) $ficha['id'], $seguradora))) {
    json_response(['success' => false, 'message' => 'A MPS está finalizando a emissão com a seguradora. Você será avisado.'], 409);
}

$body  = json_decode(file_get_contents('php://input'), true) ?: [];

// Seguro Residencial: o segurado já está na ficha — o cliente só escolhe o
// pagamento (modal-pagamento-residencial.php). Vai pra emissão no lugar do proprietário.
if (($ficha['produto'] ?? '') === 'residencial') {
    $pagamento = ['parcelas' => min(11, max(1, (int) ($body['pagamento']['parcelas'] ?? 1)))];
    $cotacaoRes = api_cotacao((int) $ficha['id'], $seguradora);
    if (api_ficha_encerrada($ficha)) {
        json_response(['success' => false, 'message' => 'Essa cotação está encerrada (' . $ficha['status'] . ') e não pode mais emitir apólice.'], 409);
    }
    $escolhidaRes = api_seguradora_escolhida((int) $ficha['id']);
    if ($escolhidaRes !== null && $escolhidaRes !== $seguradora) {
        json_response(['success' => false, 'message' => 'A proposta da ' . api_seguradora_nome($escolhidaRes) . ' já foi escolhida para essa cotação.'], 409);
    }
    if (!$cotacaoRes) {
        json_response(['success' => false, 'message' => 'Cotação não encontrada pra essa seguradora.'], 404);
    }
    if (api_tem_apolice($cotacaoRes)) {
        json_response(['success' => false, 'message' => 'A apólice dessa cotação já foi emitida.'], 409);
    }
    // Parcela mínima de R$ 25 (Pottencial).
    if ((float) ($cotacaoRes['premio_total'] ?? 0) / $pagamento['parcelas'] < 25) {
        $pagamento['parcelas'] = max(1, (int) floor((float) ($cotacaoRes['premio_total'] ?? 0) / 25));
    }
    $ativoRes = api_fila_ativa((int) $ficha['id']);
    if ($ativoRes && $ativoRes['tipo'] !== 'emissao') {
        json_response(['success' => false, 'message' => 'A seguradora ainda está processando essa cotação. Aguarde terminar e tente de novo.'], 409);
    }
    api_fila_enfileirar('emissao', (int) $ficha['id'], $seguradora, ['proprietario' => $pagamento]);
    api_registrar_aceite((int) $ficha['id'], $seguradora, $user, '');
    json_response(['success' => true, 'enfileirado' => true]);
}

$campo = static fn(string $k): string => trim((string) ($body['proprietario'][$k] ?? ''));
$proprietario = [
    'nome_completo' => $campo('nome_completo'),
    'cpf_cnpj'      => preg_replace('/\D/', '', $campo('cpf_cnpj')),
    'email'         => $campo('email'),
    'tel_celular'   => preg_replace('/\D/', '', $campo('tel_celular')),
    'cep'           => preg_replace('/\D/', '', $campo('cep')),
    'endereco'      => $campo('endereco'),
    'numero'        => $campo('numero'),
    'complemento'   => $campo('complemento'),
    'bairro'        => $campo('bairro'),
    'cidade'        => $campo('cidade'),
    'uf'            => strtoupper($campo('uf')),
];

foreach ([
    'nome_completo' => 'o nome do proprietário',
    'cpf_cnpj'      => 'o CPF/CNPJ do proprietário',
    'cep'           => 'o CEP',
    'endereco'      => 'o endereço',
    'numero'        => 'o número',
    'bairro'        => 'o bairro',
    'cidade'        => 'a cidade',
    'uf'            => 'a UF',
] as $k => $rotulo) {
    if ($proprietario[$k] === '') {
        json_response(['success' => false, 'message' => "Informe {$rotulo}."], 422);
    }
}
if (!validar_cpf($proprietario['cpf_cnpj']) && !validar_cnpj($proprietario['cpf_cnpj'])) {
    json_response(['success' => false, 'message' => 'CPF/CNPJ do proprietário inválido.'], 422);
}
if (strlen($proprietario['cep']) !== 8) {
    json_response(['success' => false, 'message' => 'CEP do proprietário inválido.'], 422);
}
if (!preg_match('/^[A-Z]{2}$/', $proprietario['uf'])) {
    json_response(['success' => false, 'message' => 'UF do proprietário inválida.'], 422);
}
if ($proprietario['email'] !== '' && !filter_var($proprietario['email'], FILTER_VALIDATE_EMAIL)) {
    json_response(['success' => false, 'message' => 'E-mail do proprietário inválido.'], 422);
}

// Too Seguros: exige mais dados do proprietário (ver modal-proprietario.php).
if ($seguradora === 'too') {
    $pj = strlen($proprietario['cpf_cnpj']) === 14;
    if ($proprietario['email'] === '' || strlen($proprietario['tel_celular']) < 10) {
        json_response(['success' => false, 'message' => 'Informe o e-mail e o telefone do proprietário.'], 422);
    }
    $moeda = static fn(string $v): float => (float) str_replace(',', '.', preg_replace('/[^\d,.-]/', '', $v));
    if ($pj) {
        $proprietario['faturamento'] = $moeda($campo('faturamento'));
        if ($proprietario['faturamento'] <= 0) {
            json_response(['success' => false, 'message' => 'Informe o faturamento mensal da empresa proprietária.'], 422);
        }
    } else {
        $nascimento = date_from_br($campo('data_nascimento'));
        $estados    = ['Solteiro', 'Casado', 'Viuvo', 'SeparadoJudicialmente', 'Divorciado', 'UniaoEstavel', 'NaoInformado'];
        $proprietario += [
            'data_nascimento' => $nascimento ?: '',
            'estado_civil'    => in_array($campo('estado_civil'), $estados, true) ? $campo('estado_civil') : '',
            'nacionalidade'   => $campo('nacionalidade'),
            'renda'           => $moeda($campo('renda')),
            'pep'             => in_array($campo('pep'), ['Nao', 'Sim'], true) ? $campo('pep') : '',
        ];
        $faltando = array_keys(array_filter([
            'a data de nascimento' => $proprietario['data_nascimento'] === '',
            'o estado civil'       => $proprietario['estado_civil'] === '',
            'a nacionalidade'      => $proprietario['nacionalidade'] === '',
            'a renda mensal'       => $proprietario['renda'] <= 0,
            'se é pessoa politicamente exposta' => $proprietario['pep'] === '',
        ]));
        if ($faltando) {
            json_response(['success' => false, 'message' => 'Informe ' . implode(', ', $faltando) . ' do proprietário.'], 422);
        }
    }
}

if (api_ficha_encerrada($ficha)) {
    json_response(['success' => false, 'message' => 'Essa cotação está encerrada (' . $ficha['status'] . ') e não pode mais emitir apólice.'], 409);
}

// Escolha única: o cliente fecha com UMA seguradora.
$escolhida = api_seguradora_escolhida((int) $ficha['id']);
if ($escolhida !== null && $escolhida !== $seguradora) {
    json_response(['success' => false, 'message' => 'A proposta da ' . api_seguradora_nome($escolhida) . ' já foi escolhida para essa cotação.'], 409);
}

$cotacao = api_cotacao((int) $ficha['id'], $seguradora);
if (!$cotacao) {
    json_response(['success' => false, 'message' => 'Cotação não encontrada pra essa seguradora.'], 404);
}
if (api_tem_apolice($cotacao)) {
    json_response(['success' => false, 'message' => 'A apólice dessa cotação já foi emitida.'], 409);
}
// Cotação/reanálise ainda rodando: espera terminar (a emissão reconstrói a
// cotação com os dados atuais). Emissão já na fila é reaproveitada.
$ativo = api_fila_ativa((int) $ficha['id']);
if ($ativo && $ativo['tipo'] !== 'emissao') {
    json_response(['success' => false, 'message' => 'A seguradora ainda está processando essa cotação. Aguarde terminar e tente de novo.'], 409);
}

api_fila_enfileirar('emissao', (int) $ficha['id'], $seguradora, ['proprietario' => $proprietario]);
// Proposta aceita → 'Aprovado' (linha do tempo + aviso pra MPS) — só na 1ª vez.
api_registrar_aceite((int) $ficha['id'], $seguradora, $user, $proprietario['nome_completo']);

json_response(['success' => true, 'enfileirado' => true]);
