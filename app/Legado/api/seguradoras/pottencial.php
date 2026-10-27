<?php
/**
 * Adapter da Pottencial Seguradora na API das seguradoras (Fiança).
 *
 * Implementa o contrato de adapter de src/helpers/api/seguradoras.php:
 * recebe a ficha (linha de seguro_fianca) / a cotação (linha de
 * api_cotacoes) e devolve resultados NORMALIZADOS — status interno
 * (api_status_*), campos em português — pra camada genérica não conhecer
 * nada específico da Pottencial.
 *
 * Ambiente: homologação (api-hml.pottencial.com.br). Credenciais em .env
 * (POTTENCIAL_*), fornecidas pelo suporte Pottencial em 22/set/2026.
 *
 *   - Auth: POST {base}/oauth/v3/access-token, Basic base64(client_id:client_secret)
 *     → { access_token, expires_in }. As chamadas seguintes usam os headers
 *     literais `client_id` e `access_token` (não é Authorization: Bearer).
 *   - commissionedAgents: CNPJ da corretora (MPS), role "Broker",
 *     comissão 0.11, lead true — confirmado com o suporte em 22/set.
 *   - CPFs de teste em homologação (reenviados pelo suporte em out/2026):
 *     10864599625, 12738353606, 09992264608, 04085297686 e 77053311600 —
 *     aprovados. Qualquer outro CPF válido volta recusado. O mesmo CPF pode
 *     repetir entre cotações mudando só o número do endereço (regra de 30
 *     dias por objeto de risco).
 *   - O nome (SocialName) passa por uma lista de palavras proibidas —
 *     "Teste ..." volta HTTP 400.
 *   - A biometria está fora do ar em homologação desde 29/09/2026: cotações
 *     novas voltam "Approved" direto, sem passar por "ApprovedBiometry".
 *   - A geração de PDF (proposta/apólice) é lenta e instável: ~50s, e às
 *     vezes HTTP 500 após ~55s (gateway deles corta) — ver api_documento().
 */

/** Múltiplo e comissão fixos, conforme confirmado com o suporte Pottencial. */
const POTTENCIAL_MULTIPLO = 30;
const POTTENCIAL_COMISSAO = 0.11;

/** Fiança PF: só pretendente CPF — o CNPJ vai pela Pottencial PJ (pottencial_pj.php). */
function pottencial_atende(array $ficha): bool
{
    return strlen(preg_replace('/\D/', '', (string) ($ficha['cpf_cnpj'] ?? ''))) === 11;
}

/** Status da cotação na Pottencial → status interno (api_cotacoes.status). */
function pottencial_status_interno(string $status): string
{
    return [
        'PendingBiometry'  => 'aguardando_biometria',
        'ApprovedBiometry' => 'biometria_concluida',
        'RefusedBiometry'  => 'biometria_recusada',
        'UnderAnalysis'    => 'em_analise',
        'Pending'          => 'em_analise',
        'Approved'         => 'aprovada',
        'Issued'           => 'aprovada',
        'Refused'          => 'recusada',
        'Denied'           => 'recusada',
        // Cotação já usada numa proposta (fim do ciclo de vida dela).
        'Performed'        => 'proposta_escolhida',
        'Canceled'         => 'cancelada',
        'Cancelled'        => 'cancelada',
        'Expired'          => 'expirada',
    ][$status] ?? 'em_analise';
}

/** Status da apólice na Pottencial → status interno (api_cotacoes.apolice_status). */
function pottencial_apolice_status_interno(string $status): string
{
    return [
        'Creating'  => 'em_emissao',
        'Active'    => 'emitida',
        'Modifying' => 'emitida',
        'Canceling' => 'cancelada',
        'Cancelled' => 'cancelada',
        'Expired'   => 'expirada',
    ][$status] ?? 'em_emissao';
}

// ─── HTTP ────────────────────────────────────────────────────────────────

function pottencial_url_base(): string
{
    return rtrim((string) env('POTTENCIAL_API_URL', 'https://api-hml.pottencial.com.br'), '/');
}

/**
 * Token de acesso, com cache em arquivo (renovado ~1 min antes de expirar).
 * @return array{ok:bool, token?:string, erro?:string}
 */
function pottencial_token(): array
{
    $cacheFile = api_cache_dir() . '/pottencial_token.json';

    if (is_file($cacheFile)) {
        $cache = json_decode((string) file_get_contents($cacheFile), true);
        if (is_array($cache) && ($cache['expira_em'] ?? 0) > time() + 60) {
            return ['ok' => true, 'token' => $cache['access_token']];
        }
    }

    $clientId     = (string) env('POTTENCIAL_CLIENT_ID', '');
    $clientSecret = (string) env('POTTENCIAL_CLIENT_SECRET', '');
    if ($clientId === '' || $clientSecret === '') {
        return ['ok' => false, 'erro' => 'Credenciais da Pottencial não configuradas (.env).'];
    }

    db_fechar_temporariamente();

    $ch = curl_init(pottencial_url_base() . '/oauth/v3/access-token');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => '',
        CURLOPT_HTTPHEADER     => ['Authorization: Basic ' . base64_encode("{$clientId}:{$clientSecret}")],
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT        => 8,
    ]);
    $resp     = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($resp === false) {
        return ['ok' => false, 'erro' => 'Falha ao conectar na Pottencial' . ($curlErr ? " ({$curlErr})" : '.')];
    }

    $data = json_decode((string) $resp, true);
    if (($httpCode !== 200 && $httpCode !== 201) || empty($data['access_token'])) {
        return ['ok' => false, 'erro' => 'Autenticação Pottencial falhou (HTTP ' . $httpCode . '): ' . substr((string) $resp, 0, 300)];
    }

    // @: sem permissão de escrita só deixa de cachear o token.
    @file_put_contents($cacheFile, json_encode([
        'access_token' => $data['access_token'],
        'expira_em'    => time() + (int) ($data['expires_in'] ?? 3600),
    ]));

    return ['ok' => true, 'token' => $data['access_token']];
}

/**
 * Chamada JSON autenticada. A criação de cotação em homologação leva de 23s
 * a ~57s — daí o timeout padrão de 110s (no worker, fora do PHP-FPM). Quem
 * roda na requisição da tela (link de biometria) passa um timeout curto.
 *
 * @return array{ok:bool, status:int, data?:array, erro?:string}
 */
function pottencial_request(string $method, string $path, ?array $body = null, int $timeout = 110): array
{
    $auth = pottencial_token();
    if (!$auth['ok']) {
        return ['ok' => false, 'status' => 0, 'erro' => $auth['erro']];
    }

    db_fechar_temporariamente();

    $ch = curl_init(pottencial_url_base() . $path);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => strtoupper($method),
        CURLOPT_HTTPHEADER     => [
            'client_id: ' . (string) env('POTTENCIAL_CLIENT_ID', ''),
            'access_token: ' . $auth['token'],
            'Content-Type: application/json',
        ],
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT        => $timeout,
    ];
    if ($body !== null) {
        $opts[CURLOPT_POSTFIELDS] = json_encode($body);
    }
    curl_setopt_array($ch, $opts);

    $resp     = curl_exec($ch);
    $httpCode = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlErr  = curl_error($ch);
    curl_close($ch);

    if ($resp === false) {
        return ['ok' => false, 'status' => 0, 'erro' => 'Falha ao conectar na Pottencial' . ($curlErr ? " ({$curlErr})" : '.'), 'erro_codigo' => 'indisponivel'];
    }

    $data = json_decode((string) $resp, true);

    if ($httpCode >= 400) {
        $msg    = $data['errors'][0]['message'] ?? ($data['message'] ?? substr((string) $resp, 0, 300));
        $codigo = (string) ($data['errors'][0]['code'] ?? '');
        return ['ok' => false, 'status' => $httpCode, 'erro' => "Pottencial HTTP {$httpCode}: {$msg}"]
            + pottencial_classificar_erro($httpCode, $codigo, (string) $msg);
    }

    return ['ok' => true, 'status' => $httpCode, 'data' => is_array($data) ? $data : []];
}

// ─── Montagem do payload ─────────────────────────────────────────────────

/** expenses[] a partir dos valores da ficha (aluguel + encargos preenchidos). */
function pottencial_montar_despesas(array $ficha): array
{
    $despesas = [['value' => (float) ($ficha['valor_aluguel'] ?? 0), 'description' => 'VALOR_ALUGUEL']];
    foreach ([
        'valor_condominio' => 'VALOR_CONDOMINIO',
        'valor_iptu'       => 'VALOR_IPTU',
        'valor_agua'       => 'VALOR_AGUA',
        'valor_gas'        => 'VALOR_GAS',
        'valor_energia'    => 'VALOR_LUZ',
    ] as $campo => $chave) {
        $valor = (float) ($ficha[$campo] ?? 0);
        if ($valor > 0) {
            $despesas[] = ['value' => $valor, 'description' => $chave];
        }
    }

    return $despesas;
}

/** Endereço no formato da Pottencial a partir dos campos (endereco, numero, cep...). */
function pottencial_montar_endereco(array $e): array
{
    return [
        'street'     => (string) ($e['endereco'] ?? ''),
        'number'     => (string) (($e['numero'] ?? '') !== '' ? $e['numero'] : 'S/N'),
        'district'   => (string) ($e['bairro'] ?? ''),
        'city'       => (string) ($e['cidade'] ?? ''),
        'state'      => strtoupper((string) ($e['uf'] ?? '')),
        'zipCode'    => preg_replace('/\D/', '', (string) ($e['cep'] ?? '')),
        'complement' => (string) ($e['complemento'] ?? ''),
        'country'    => 'BRA',
        'type'       => 'Residential',
    ];
}

/** Solidários no formato tenants[] da Pottencial. @param array $solidarios [['cpf'=>..,'nome'=>..], ...] */
function pottencial_montar_tenants(array $solidarios): array
{
    return array_values(array_map(
        fn($s) => ['documentNumber' => preg_replace('/\D/', '', (string) $s['cpf']), 'composeIncome' => true],
        array_filter($solidarios, fn($s) => preg_replace('/\D/', '', (string) ($s['cpf'] ?? '')) !== '')
    ));
}

/**
 * Corpo da cotação (FiancaLocaticiaQuote) — serve pra criar (POST /quotes) e
 * pra atualizar (PUT /quotes/{id}). O PUT manda o objeto INTEIRO de novo
 * (não é patch), então os solidários atuais precisam ir junto.
 *
 * @param array|null $proprietario Locador (vai como Insured + Beneficiary,
 *                                  exigidos pra criar a proposta). Na cotação
 *                                  inicial ainda não temos, então vai sem.
 */
function pottencial_montar_cotacao(array $ficha, string $inicio, string $fim, array $solidarios = [], ?array $proprietario = null): array
{
    $documento = preg_replace('/\D/', '', (string) ($ficha['cpf_cnpj'] ?? ''));
    $endereco  = pottencial_montar_endereco($ficha);

    // PolicyHolder é sempre o locatário (CPF) — a Pottencial rejeita CNPJ
    // nesse papel. participationPercentage só é aceito no Beneficiary (o
    // PUT recusa no PolicyHolder).
    $participantes = [[
        'documentNumber' => $documento,
        'SocialName'     => (string) ($ficha['nome_completo'] ?? ''),
        'role'           => 'PolicyHolder',
        'address'        => $endereco,
        'contact'        => [
            'name'            => (string) ($ficha['nome_completo'] ?? ''),
            'email'           => (string) ($ficha['email'] ?? ''),
            'cellPhoneNumber' => preg_replace('/\D/', '', (string) ($ficha['tel_celular'] ?? '')),
        ],
        'main' => true,
    ]];

    // Na Fiança Locatícia o Segurado e o Beneficiário são a mesma pessoa:
    // o locador/proprietário do imóvel.
    if ($proprietario !== null) {
        $locador = [
            'documentNumber' => preg_replace('/\D/', '', (string) ($proprietario['cpf_cnpj'] ?? '')),
            'SocialName'     => (string) ($proprietario['nome_completo'] ?? ''),
            'address'        => pottencial_montar_endereco($proprietario),
            'contact'        => array_filter([
                'name'            => (string) ($proprietario['nome_completo'] ?? ''),
                'email'           => (string) ($proprietario['email'] ?? ''),
                'cellPhoneNumber' => preg_replace('/\D/', '', (string) ($proprietario['tel_celular'] ?? '')),
            ], fn($v) => $v !== ''),
        ];
        $participantes[] = $locador + ['role' => 'Insured'];
        $participantes[] = $locador + ['role' => 'Beneficiary', 'participationPercentage' => 1];
    }

    return [
        'policyPeriodStart'  => $inicio,
        'policyPeriodEnd'    => $fim,
        'policyType'         => 'Unico',
        'commissionedAgents' => [[
            'documentNumber'       => preg_replace('/\D/', '', (string) env('POTTENCIAL_CNPJ_BROKER', '')),
            'role'                 => 'Broker',
            'commissionPercentage' => POTTENCIAL_COMISSAO,
            'lead'                 => true,
        ]],
        'participants' => $participantes,
        // tenants = só CO-locatários (solidários) além do titular — repetir o
        // titular dá "The solidary cannot have any other role in the policy.".
        'riskObjects' => [[
            'type'                 => 'FiancaLocaticia',
            'tenantDocumentNumber' => $documento,
            'startLeaseContract'   => $inicio,
            'endLeaseContract'     => $fim,
            'coverages'            => [['key' => 'basica', 'insuredAmount' => round((float) ($ficha['valor_aluguel'] ?? 0) * POTTENCIAL_MULTIPLO, 2)]],
            'expenses'             => pottencial_montar_despesas($ficha),
            'tenants'              => pottencial_montar_tenants($solidarios),
            'planKey'              => 'Traditional',
            'multiple'             => POTTENCIAL_MULTIPLO,
            'occupation'           => ($ficha['tipo_ficha'] ?? 'residencial') === 'comercial' ? 'Comercial' : 'Residencial',
            'inhabited'            => false,
            'riskLocation'         => ['address' => $endereco, 'nationalCoverage' => true],
        ]],
        'paymentConditions' => ['paymentType' => 'Boleto', 'installments' => 1],
    ];
}

/** Resultado normalizado a partir do objeto "quote" (criação e consulta devolvem o mesmo formato). */
function pottencial_normalizar_cotacao(array $q): array
{
    $coberturas = $q['riskObjects'][0]['coverages'] ?? [];
    $nomes = [
        'basica' => 'Básica (aluguel)', 'condominio' => 'Condomínio', 'iptu' => 'IPTU',
        'agua' => 'Água', 'gas' => 'Gás', 'luz' => 'Luz', 'danos' => 'Danos ao imóvel',
        'pintura' => 'Pintura', 'multa-rescisao' => 'Multa por rescisão',
    ];

    $basica = null;
    $detalheCoberturas = [];
    foreach ($coberturas as $cov) {
        $valor = (float) ($cov['lmi'] ?? 0);
        if (($cov['key'] ?? '') === 'basica') {
            $basica = isset($cov['lmi']) ? $valor : null;
        }
        if ($valor > 0) {
            $detalheCoberturas[] = ['nome' => $nomes[$cov['key'] ?? ''] ?? (string) ($cov['key'] ?? ''), 'valor' => $valor];
        }
    }

    $statusSeguradora = (string) ($q['status'] ?? '');

    // Comissão da corretora (Financeiro): vem no agente "Broker" da cotação.
    $corretora = null;
    foreach ($q['commissionedAgents'] ?? [] as $agente) {
        if (($agente['role'] ?? '') === 'Broker') $corretora = $agente;
    }

    return [
        'ok'                 => true,
        'status'             => pottencial_status_interno($statusSeguradora),
        'status_seguradora'  => $statusSeguradora,
        'cotacao_externa_id' => (string) ($q['quoteId'] ?? ''),
        'numero_cotacao'     => isset($q['quoteNumber']) ? (string) $q['quoteNumber'] : null,
        'premio_total'       => isset($q['grossPremium']) ? (float) $q['grossPremium'] : null,
        'premio_liquido'     => isset($q['commercialPremium']) ? (float) $q['commercialPremium'] : null,
        'iof'                => isset($q['iof']) ? (float) $q['iof'] : null,
        'forma_pagamento'    => (string) ($q['paymentConditions']['paymentType'] ?? '') ?: null,
        'parcelas'           => isset($q['paymentConditions']['installments']) ? (int) $q['paymentConditions']['installments'] : null,
        'cobertura'          => $basica,
        'plano'              => 'Tradicional',
        'comissao_percentual' => isset($corretora['commissionPercentage']) ? round((float) $corretora['commissionPercentage'] * 100, 2) : null,
        'comissao_valor'     => isset($corretora['commissionAmount']) ? (float) $corretora['commissionAmount'] : null,
        'vigencia_inicio'    => isset($q['policyPeriodStart']) ? substr((string) $q['policyPeriodStart'], 0, 10) : null,
        'vigencia_fim'       => isset($q['policyPeriodEnd']) ? substr((string) $q['policyPeriodEnd'], 0, 10) : null,
        'detalhes_json'      => json_encode([
            'coberturas'   => $detalheCoberturas,
            'assistencias' => array_values(array_filter(array_map(fn($a) => (string) ($a['serviceName'] ?? ''), $q['assistanceServices'] ?? []))),
        ], JSON_UNESCAPED_UNICODE),
    ];
}

// ─── Contrato do adapter ─────────────────────────────────────────────────

/**
 * Cota a ficha. Se a Pottencial recusar e a ficha tiver pretendentes
 * adicionais, pede a reanálise na hora com eles como solidários (mecanismo
 * oficial — a API não tenta sozinha); a recusa inicial volta em
 * 'tentativas_anteriores' pro histórico.
 *
 * @param array $pretendentes Pretendentes adicionais [['nome'=>..,'cpf'=>..], ...]
 */
function pottencial_cotar(array $ficha, array $pretendentes): array
{
    if (preg_replace('/\D/', '', (string) env('POTTENCIAL_CNPJ_BROKER', '')) === '') {
        return ['ok' => false, 'erro' => 'POTTENCIAL_CNPJ_BROKER não configurado no .env.'];
    }
    if (!str_contains(trim((string) ($ficha['nome_completo'] ?? '')), ' ')) {
        return ['ok' => false, 'erro' => 'Informe o nome completo do pretendente (nome e sobrenome).', 'erro_codigo' => 'sobrenome'];
    }

    $meses  = max(1, (int) ($ficha['vigencia_contrato'] ?? 12));
    $inicio = new DateTimeImmutable('today');
    $fim    = $inicio->modify("+{$meses} months");

    $r = pottencial_request('POST', '/insurance/v1/fianca-locaticia/quotes', pottencial_montar_cotacao($ficha, $inicio->format('Y-m-d'), $fim->format('Y-m-d')));
    if (!$r['ok']) {
        $codigo = $r['erro_codigo'] ?? null;
        if ($codigo === 'cep' && ($r['erro_cep'] ?? '') !== preg_replace('/\D/', '', (string) ($ficha['cep'] ?? ''))) {
            $codigo = 'cadastro'; // CEP do cadastro do pretendente na Pottencial, não o do imóvel
        }
        return ['ok' => false, 'erro' => $r['erro'], 'erro_codigo' => $codigo];
    }

    $resultado = pottencial_normalizar_cotacao($r['data']);

    if ($resultado['status'] === 'recusada' && $pretendentes !== []) {
        $reanalise = pottencial_reanalisar(
            $resultado['cotacao_externa_id'],
            $inicio->format('Y-m-d'),
            $fim->format('Y-m-d'),
            $ficha,
            $pretendentes
        );
        if ($reanalise['ok']) {
            $reanalise['tentativas_anteriores'] = [$resultado];
            return $reanalise;
        }
        error_log('[pottencial] Falha ao pedir reanálise automática: ' . ($reanalise['erro'] ?? ''));
    }

    return $resultado;
}

/**
 * Traduz uma falha da Pottencial pro código interno de erro (contrato dos
 * adapters — ver api_erro_codigos() em api/status.php). É o ÚNICO lugar que
 * conhece as mensagens da Pottencial; o resto do sistema usa o código.
 *
 * @return array{erro_codigo:?string, erro_cep?:string}
 */
function pottencial_classificar_erro(int $http, string $codigo, string $mensagem): array
{
    if ($http === 0 || $http >= 500 || in_array($http, [408, 429], true)) {
        return ['erro_codigo' => 'indisponivel'];
    }
    if (in_array($http, [401, 403, 404], true) || $http < 400) {
        return ['erro_codigo' => null]; // credencial/rota nossa — não é culpa da ficha
    }

    // O imóvel (o "risco") já tem apólice ativa — a Pottencial não emite
    // outra pro mesmo imóvel enquanto aquela estiver vigente.
    if (preg_match("/already active in policy '([0-9a-f-]+)'/i", $mensagem, $m)) {
        return ['erro_codigo' => 'imovel_com_apolice', 'erro_apolice' => $m[1]];
    }

    // CEP: a Pottencial valida também o endereço CADASTRADO do CPF — quem
    // decide se é o da ficha ('cep') ou do cadastro ('cadastro') é o
    // pottencial_cotar, que conhece o CEP da ficha.
    if (preg_match("/zipcode:?\\s*'?(\\d{8})/i", $mensagem, $m)) {
        return ['erro_codigo' => 'cep', 'erro_cep' => $m[1]];
    }

    $regras = [
        'restricted list'                  => 'nome',
        'email must be informed'           => 'email',
        'must be a valid CPF'              => 'cpf',
        'cannot exceed 2x the rent'        => 'encargos',
        "'Insured Amount' must be greater" => 'aluguel',
    ];
    foreach ($regras as $trecho => $erroCodigo) {
        if (stripos($mensagem, $trecho) !== false) {
            return ['erro_codigo' => $erroCodigo];
        }
    }

    return ['erro_codigo' => 'dados']; // dado recusado sem dar pra saber o campo
}

/** Reconsulta uma cotação já criada. */
function pottencial_consultar(string $cotacaoId): array
{
    $r = pottencial_request('GET', '/insurance/v1/fianca-locaticia/quotes/' . rawurlencode($cotacaoId));
    return $r['ok'] ? pottencial_normalizar_cotacao($r['data']) : ['ok' => false, 'erro' => $r['erro'], 'erro_codigo' => $r['erro_codigo'] ?? null];
}

/**
 * Reanálise do subscritor de uma cotação recusada, substituindo os
 * solidários (POST /quotes/reanalysis). A resposta não é documentada, então
 * reconsulta a cotação em seguida pra saber o resultado.
 *
 * @param array $solidarios [['nome'=>..,'cpf'=>..], ...]
 */
function pottencial_reanalisar(string $cotacaoId, string $inicio, string $fim, array $ficha, array $solidarios): array
{
    $r = pottencial_request('POST', '/insurance/v1/fianca-locaticia/quotes/reanalysis', [
        'quoteId'                => $cotacaoId,
        'startInsuranceContract' => $inicio . 'T03:00:00Z',
        'endInsuranceContract'   => $fim . 'T02:59:00Z',
        'planKey'                => 'traditional',
        'multiple'               => POTTENCIAL_MULTIPLO,
        'expenses'               => pottencial_montar_despesas($ficha),
        'tenants'                => pottencial_montar_tenants($solidarios),
    ]);
    if (!$r['ok']) {
        return ['ok' => false, 'erro' => $r['erro'], 'erro_codigo' => $r['erro_codigo'] ?? null];
    }

    $resultado = pottencial_consultar($cotacaoId);
    $resultado['solidarios'] = $solidarios;

    return $resultado;
}

/**
 * Emite a apólice: inclui o locador na cotação (PUT — obrigatório desde
 * out/2026: sem ele a proposta volta "It should have at least one insured"),
 * envia a proposta e aceita (o aceite já emite a apólice na Fiança).
 *
 * Retomada: se a proposta já foi criada numa tentativa anterior e só o
 * aceite falhou, a cotação já está "Performed" (não aceita PUT/proposta
 * nova) — então só aceita.
 *
 * @param array $cotacao      Linha de api_cotacoes.
 * @param array $proprietario nome_completo, cpf_cnpj, email, tel_celular, cep,
 *                            endereco, numero, complemento, bairro, cidade, uf.
 */
function pottencial_emitir(array $cotacao, array $ficha, array $proprietario, string $inicio, string $fim): array
{
    $cotacaoId = (string) $cotacao['cotacao_externa_id'];

    if (!empty($cotacao['proposta_id'])) {
        return pottencial_aceitar_proposta((string) $cotacao['proposta_id']);
    }

    // Biometria fora do ar em homologação: cotação nova vem "Approved" sem
    // passar por "ApprovedBiometry" — os dois liberam a emissão.
    $liberados = ['aprovada', 'biometria_concluida'];

    $atual = pottencial_consultar($cotacaoId);
    if (!$atual['ok']) {
        return ['ok' => false, 'erro' => $atual['erro']];
    }
    if ($atual['status'] === 'proposta_escolhida') {
        return pottencial_erro_proposta_sem_retorno($cotacao);
    }
    if (!in_array($atual['status'], $liberados, true)) {
        return ['ok' => false, 'erro' => 'A cotação não está liberada para emissão — status atual: ' . api_status_label($atual['status'])];
    }

    $solidarios = json_decode((string) ($cotacao['solidarios_json'] ?? ''), true) ?: [];
    $put = pottencial_request('PUT', '/insurance/v1/fianca-locaticia/quotes/' . rawurlencode($cotacaoId), pottencial_montar_cotacao($ficha, $inicio, $fim, $solidarios, $proprietario));
    if (!$put['ok']) {
        return ['ok' => false, 'erro' => 'Falha ao incluir o proprietário na cotação: ' . $put['erro']]
            + array_intersect_key($put, array_flip(['erro_codigo', 'erro_apolice']));
    }
    // Atualizar a cotação reprocessa a análise — se sair do status liberado,
    // não dá pra seguir pra proposta agora.
    $statusPut = pottencial_status_interno((string) ($put['data']['status'] ?? ''));
    if (!in_array($statusPut, $liberados, true)) {
        return ['ok' => false, 'erro' => 'Proprietário incluído, mas a cotação ficou "' . api_status_label($statusPut) . '" — use "Atualizar" e tente de novo.'];
    }

    $proposta = pottencial_request('POST', '/insurance/v1/fianca-locaticia/proposals', [
        'quoteId'           => $cotacaoId,
        'policyPeriodStart' => $inicio,
        'policyPeriodEnd'   => $fim,
        'payment'           => [
            'paymentType'                  => 'Boleto',
            'installments'                 => 1,
            'firstInstallmentDueDateDelay' => 5,
            'paymentInstructions'          => 'Boleto bancário',
        ],
    ]);
    if (!$proposta['ok']) {
        // 502/timeout: a Pottencial pode ter criado a proposta mesmo assim
        // (a cotação vira "Performed") sem devolver o id — e não tem como
        // buscar a proposta pela cotação. Aí só a MPS resolve com a seguradora.
        if ((int) ($proposta['status'] ?? 0) === 0 || (int) $proposta['status'] >= 500) {
            $depois = pottencial_consultar($cotacaoId);
            if (($depois['status'] ?? '') === 'proposta_escolhida') {
                return pottencial_erro_proposta_sem_retorno($cotacao);
            }
        }
        return ['ok' => false, 'erro' => 'Falha ao enviar a proposta: ' . $proposta['erro']]
            + array_intersect_key($proposta, array_flip(['erro_codigo', 'erro_apolice']));
    }

    $propostaId = (string) ($proposta['data']['proposalId'] ?? '');
    $aceite = pottencial_aceitar_proposta($propostaId);
    $aceite['proposta_status'] = (string) ($proposta['data']['status'] ?? '');

    return $aceite;
}

/** Cotação já usada numa proposta cujo id não voltou (ver api_emissao_presa). */
function pottencial_erro_proposta_sem_retorno(array $cotacao): array
{
    return [
        'ok'          => false,
        'erro_codigo' => 'proposta_sem_retorno',
        'erro'        => 'A Pottencial registrou a proposta da cotação nº ' . ($cotacao['numero_cotacao'] ?: $cotacao['cotacao_externa_id'])
                       . ', mas não devolveu o número dela — finalize a emissão direto com a seguradora.',
    ];
}

/** Aceita a proposta — na Fiança o aceite já emite a apólice (que nasce "Creating"). */
function pottencial_aceitar_proposta(string $propostaId): array
{
    $r = pottencial_request('POST', '/insurance/v1/fianca-locaticia/proposals/' . rawurlencode($propostaId) . '/accept');
    if (!$r['ok']) {
        return ['ok' => false, 'erro' => 'Proposta criada, mas falhou ao aceitar: ' . $r['erro'], 'proposta_id' => $propostaId];
    }

    return [
        'ok'             => true,
        'proposta_id'    => $propostaId,
        'apolice_id'     => (string) ($r['data']['policyId'] ?? ''),
        'apolice_numero' => (string) ($r['data']['policyNumber'] ?? ''),
        'apolice_status' => pottencial_apolice_status_interno((string) ($r['data']['status'] ?? '')),
    ];
}

/** Reconsulta a apólice (a emissão é assíncrona: "Creating" → "Active"). */
function pottencial_consultar_apolice(string $apoliceId): array
{
    $r = pottencial_request('GET', '/insurance/v1/fianca-locaticia/policies/' . rawurlencode($apoliceId));
    if (!$r['ok']) {
        return ['ok' => false, 'erro' => $r['erro'], 'erro_codigo' => $r['erro_codigo'] ?? null];
    }

    return [
        'ok'             => true,
        'apolice_numero' => (string) ($r['data']['policyNumber'] ?? ''),
        'apolice_status' => pottencial_apolice_status_interno((string) ($r['data']['status'] ?? '')),
    ];
}

/** Link de biometria facial pra enviar ao pretendente. */
function pottencial_biometria(string $cotacaoId): array
{
    // Roda na tela (botão do link): não pode segurar a requisição por minutos.
    $r = pottencial_request('GET', '/insurance/v1/fianca-locaticia/quotes/facial-biometrics/' . rawurlencode($cotacaoId), null, 20);
    if (!$r['ok']) {
        return ['ok' => false, 'erro' => $r['erro'], 'erro_codigo' => $r['erro_codigo'] ?? null];
    }

    $url = (string) ($r['data']['url'] ?? '');
    return $url !== '' ? ['ok' => true, 'url' => $url] : ['ok' => false, 'erro' => 'Link de biometria não retornado pela Pottencial.'];
}

/**
 * PDF da proposta ou da apólice. Volta o PDF cru (não JSON). Apólice ainda
 * sem documento gerado responde 404 "does not have a generated document" —
 * tratado como pendente, não erro.
 *
 * @param string $tipo 'apolice' | 'proposta'
 * @return array{ok:bool, conteudo?:string, pendente?:bool, erro?:string}
 */
function pottencial_documento(string $tipo, string $id, string $produto = 'fianca-locaticia'): array
{
    $caminho = [
        'apolice'  => "/insurance/v1/{$produto}/policies/%s/document",
        'proposta' => "/insurance/v1/{$produto}/proposals/%s/document",
    ][$tipo] ?? null;
    if ($caminho === null || $id === '') {
        return ['ok' => false, 'erro' => 'Documento inválido.'];
    }

    $auth = pottencial_token();
    if (!$auth['ok']) {
        return ['ok' => false, 'erro' => $auth['erro']];
    }

    db_fechar_temporariamente();

    $ch = curl_init(pottencial_url_base() . sprintf($caminho, rawurlencode($id)));
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => [
            'client_id: ' . (string) env('POTTENCIAL_CLIENT_ID', ''),
            'access_token: ' . $auth['token'],
        ],
        CURLOPT_CONNECTTIMEOUT => 3,
        // ~50s pra gerar; 90s fica abaixo do limite de 100s do Cloudflare.
        CURLOPT_TIMEOUT        => 90,
    ]);
    $resp        = curl_exec($ch);
    $httpCode    = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentType = (string) curl_getinfo($ch, CURLINFO_CONTENT_TYPE);
    $curlErr     = curl_error($ch);
    curl_close($ch);

    if ($resp === false) {
        return ['ok' => false, 'erro' => 'Falha ao conectar na Pottencial' . ($curlErr ? " ({$curlErr})" : '.')];
    }

    if ($httpCode >= 400) {
        $data = json_decode((string) $resp, true);
        $msg  = (string) ($data['errors'][0]['message'] ?? substr((string) $resp, 0, 300));
        if ($httpCode === 404 && stripos($msg, 'generated document') !== false) {
            return ['ok' => false, 'pendente' => true, 'erro' => $msg];
        }
        return ['ok' => false, 'erro' => "Pottencial HTTP {$httpCode}: {$msg}"];
    }

    if (strncmp((string) $resp, '%PDF', 4) !== 0) {
        return ['ok' => false, 'erro' => 'A Pottencial não devolveu um PDF (' . ($contentType ?: 'sem content-type') . ').'];
    }

    return ['ok' => true, 'conteudo' => (string) $resp];
}
