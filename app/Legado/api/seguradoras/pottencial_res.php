<?php
/**
 * Adapter da Pottencial — Seguro Residencial (/insurance/v1/residencial).
 * Doc: api-pottencial-residencial.md (raiz do projeto). Mesmas credenciais,
 * token, host e headers da Fiança (pottencial_request).
 *
 * Fluxo: cotação síncrona (POST /quotes devolve o prêmio; pode vir
 * "UnderAnalysis" → o cron reconsulta) → proposta (POST /proposals, com a
 * forma de pagamento) → aceite (POST /proposals/{id}/accept) → apólice
 * (POST /policies com o proposalId; nasce "Creating" → "Active").
 * Sem biometria e sem reanálise (mudou cobertura = cotação nova).
 *
 * Coberturas por importância segurada (cob_* da ficha), sem planos prontos.
 * Pagamento: boleto, 1 a 11 parcelas de no mínimo R$ 25.
 *
 * Desligado até a Pottencial liberar o produto pro nosso client_id (hoje
 * "App not Authorized"): POTTENCIAL_RES_ATIVO=1 no .env liga.
 */

const POTTENCIAL_RES_BASE = '/insurance/v1/residencial';

/** cob_* da ficha → key da cobertura na Pottencial. */
const POTTENCIAL_RES_COBERTURAS = [
    'cob_incendio'         => 'basica',
    'cob_danos_eletricos'  => 'danos-eletricos',
    'cob_vendaval'         => 'vendaval',
    'cob_roubo'            => 'roubo-furto',
    'cob_vidros'           => 'quebra-vidros',
    'cob_vazamento'        => 'ruptura',
    'cob_rc_familiar'      => 'rc-familiar',
    'cob_perda_aluguel'    => 'perda-aluguel',
    'cob_impacto_veiculos' => 'impacto-veiculos',
];

function pottencial_res_configurada(): bool
{
    return (string) env('POTTENCIAL_RES_ATIVO', '') === '1'
        && (string) env('POTTENCIAL_CLIENT_ID', '') !== ''
        && (string) env('POTTENCIAL_CLIENT_SECRET', '') !== '';
}

/** Segurado pessoa física (o formulário só aceita CPF). */
function pottencial_res_atende(array $ficha): bool
{
    return strlen(preg_replace('/\D/', '', (string) ($ficha['cpf_cnpj'] ?? ''))) === 11;
}

/** Status da cotação residencial → status interno. */
function pottencial_res_status_interno(string $status): string
{
    return [
        'Approved'      => 'aprovada',
        'Denied'        => 'recusada',
        'Creating'      => 'em_analise',
        'UnderAnalysis' => 'em_analise',
        // Não estão no enum do Residencial, mas existem na Fiança — mesmo tratamento.
        'Performed'     => 'proposta_escolhida',
        'Expired'       => 'expirada',
    ][$status] ?? 'em_analise';
}

/** Endereço no formato da Pottencial a partir de campos com prefixo ('' = imóvel, 'seg_' = segurado). */
function pottencial_res_endereco(array $f, string $prefixo = ''): array
{
    return pottencial_montar_endereco([
        'endereco'    => $f[$prefixo . 'endereco'] ?? '',
        'numero'      => $f[$prefixo . 'numero'] ?? '',
        'bairro'      => $f[$prefixo . 'bairro'] ?? '',
        'cidade'      => $f[$prefixo . 'cidade'] ?? '',
        'uf'          => $f[$prefixo . 'uf'] ?? '',
        'cep'         => $f[$prefixo . 'cep'] ?? '',
        'complemento' => $f[$prefixo . 'complemento'] ?? '',
    ]);
}

function pottencial_res_montar_cotacao(array $ficha, string $inicio, string $fim): array
{
    $cpf      = preg_replace('/\D/', '', (string) ($ficha['cpf_cnpj'] ?? ''));
    $contato  = [
        'name'        => (string) ($ficha['nome_completo'] ?? ''),
        'email'       => (string) ($ficha['email'] ?? ''),
        'phoneNumber' => preg_replace('/\D/', '', (string) ($ficha['tel_celular'] ?? '')),
    ];
    $imovel   = pottencial_res_endereco($ficha);
    $segurado = !empty($ficha['endereco_segurado_igual']) ? $imovel : pottencial_res_endereco($ficha, 'seg_');

    $coberturas = [];
    foreach (POTTENCIAL_RES_COBERTURAS as $campo => $key) {
        $valor = round((float) ($ficha[$campo] ?? 0), 2);
        if ($valor <= 0) continue;
        $cob = ['key' => $key, 'insuredAmount' => $valor];
        if ($key === 'perda-aluguel') {
            $cob['indemnityPeriod'] = 6; // meses (único valor citado em exemplos; confirmar com o suporte)
        }
        $coberturas[] = $cob;
    }

    $risco = [
        'type'             => 'Property',
        'coverages'        => $coberturas,
        'address'          => $imovel,
        'insuredOwner'     => !empty($ficha['segurado_proprietario']),
        'constructionType' => ($ficha['tipo_construcao'] ?? '') === 'madeira' ? 'Wood' : 'Brick',
        'propertyType'     => [
            'casa' => 'House', 'apartamento' => 'Apartment', 'casa_condominio' => 'CondominiumHouse', 'chacara' => 'CountrySideHouse',
        ][$ficha['tipo_imovel'] ?? ''] ?? 'House',
        'propertyUseType'  => ['habitual' => 'Usual', 'veraneio' => 'Summer', 'desocupado' => 'Vacate'][$ficha['uso_imovel'] ?? ''] ?? 'Usual',
        'paymentConditions' => ['paymentType' => 'Boleto', 'installments' => 1],
    ];
    if ((float) ($ficha['cob_rc_familiar'] ?? 0) > 0) {
        $risco['documentNumber'] = $cpf; // RC Familiar exige o CPF do segurado
    }

    return [
        'policyPeriodStart'  => $inicio,
        'policyPeriodEnd'    => $fim,
        'commissionedAgents' => [[
            'documentNumber'       => preg_replace('/\D/', '', (string) env('POTTENCIAL_CNPJ_BROKER', '')),
            'role'                 => 'Broker',
            'commissionPercentage' => (float) env('POTTENCIAL_RES_COMISSAO', (string) POTTENCIAL_COMISSAO),
            'lead'                 => true,
        ]],
        // Segurado paga e é o beneficiário (100%).
        'participants' => [
            ['documentNumber' => $cpf, 'role' => 'Insured', 'type' => 'Individual', 'isPayer' => true, 'address' => $segurado, 'contact' => $contato],
            ['documentNumber' => $cpf, 'role' => 'Beneficiary', 'type' => 'Individual', 'participationPercentage' => 1, 'main' => true, 'contact' => $contato],
        ],
        'riskObjects' => [$risco],
    ];
}

/** Resultado normalizado a partir do objeto Quote. */
function pottencial_res_normalizar_cotacao(array $q): array
{
    $nomes = [
        'basica' => 'Incêndio (básica)', 'danos-eletricos' => 'Danos elétricos', 'vendaval' => 'Vendaval',
        'roubo-furto' => 'Roubo e furto', 'quebra-vidros' => 'Quebra de vidros', 'ruptura' => 'Vazamento de tubulações',
        'rc-familiar' => 'RC familiar', 'perda-aluguel' => 'Perda de aluguel', 'impacto-veiculos' => 'Impacto de veículos',
    ];

    $basica = null;
    $detalheCoberturas = [];
    foreach ($q['riskObjects'] ?? [] as $risco) {
        foreach ($risco['coverages'] ?? [] as $cov) {
            $valor = (float) ($cov['insuredAmount'] ?? $cov['lmi'] ?? 0);
            if (($cov['key'] ?? '') === 'basica') $basica = $valor ?: null;
            if ($valor > 0) {
                $detalheCoberturas[] = ['nome' => $nomes[$cov['key'] ?? ''] ?? (string) ($cov['key'] ?? ''), 'valor' => $valor];
            }
        }
    }

    $corretora = null;
    foreach ($q['commissionedAgents'] ?? [] as $agente) {
        if (($agente['role'] ?? '') === 'Broker') $corretora = $agente;
    }
    $pagamento = $q['riskObjects'][0]['paymentConditions'] ?? [];
    $statusSeguradora = (string) ($q['status'] ?? '');

    return [
        'ok'                  => true,
        'status'              => pottencial_res_status_interno($statusSeguradora),
        'status_seguradora'   => $statusSeguradora,
        'cotacao_externa_id'  => (string) ($q['quoteId'] ?? ''),
        'numero_cotacao'      => isset($q['quoteNumber']) ? (string) $q['quoteNumber'] : null,
        'premio_total'        => isset($q['grossPremium']) ? (float) $q['grossPremium'] : null,
        'premio_liquido'      => isset($q['commercialPremium']) ? (float) $q['commercialPremium'] : null,
        'iof'                 => isset($q['iof']) ? (float) $q['iof'] : null,
        'forma_pagamento'     => (string) ($pagamento['paymentType'] ?? 'Boleto') ?: null,
        'parcelas'            => isset($pagamento['installments']) ? (int) $pagamento['installments'] : null,
        'cobertura'           => $basica,
        'plano'               => 'Residencial',
        'comissao_percentual' => isset($corretora['commissionPercentage']) ? round((float) $corretora['commissionPercentage'] * 100, 2) : null,
        'comissao_valor'      => isset($corretora['commissionAmount']) ? (float) $corretora['commissionAmount'] : null,
        'vigencia_inicio'     => isset($q['policyPeriodStart']) ? substr((string) $q['policyPeriodStart'], 0, 10) : null,
        'vigencia_fim'        => isset($q['policyPeriodEnd']) ? substr((string) $q['policyPeriodEnd'], 0, 10) : null,
        'detalhes_json'       => json_encode(['coberturas' => $detalheCoberturas, 'assistencias' => []], JSON_UNESCAPED_UNICODE),
    ];
}

/** Vigência da ficha: início informado (ou hoje) + vigencia_meses. */
function api_residencial_vigencia(array $ficha): array
{
    $hoje   = new DateTimeImmutable('today');
    $inicio = !empty($ficha['vigencia_inicio']) ? new DateTimeImmutable((string) $ficha['vigencia_inicio']) : $hoje;
    if ($inicio < $hoje) $inicio = $hoje;
    $meses = max(1, (int) ($ficha['vigencia_meses'] ?? 12));
    return [$inicio->format('Y-m-d'), $inicio->modify("+{$meses} months")->format('Y-m-d')];
}

// ─── Contrato do adapter ─────────────────────────────────────────────────

function pottencial_res_cotar(array $ficha, array $pretendentes): array
{
    if (preg_replace('/\D/', '', (string) env('POTTENCIAL_CNPJ_BROKER', '')) === '') {
        return ['ok' => false, 'erro' => 'POTTENCIAL_CNPJ_BROKER não configurado no .env.'];
    }
    [$inicio, $fim] = api_residencial_vigencia($ficha);

    $r = pottencial_request('POST', POTTENCIAL_RES_BASE . '/quotes', pottencial_res_montar_cotacao($ficha, $inicio, $fim));
    if (!$r['ok']) {
        return ['ok' => false, 'erro' => $r['erro'], 'erro_codigo' => $r['erro_codigo'] ?? null];
    }
    return pottencial_res_normalizar_cotacao($r['data']);
}

function pottencial_res_consultar(string $cotacaoId): array
{
    $r = pottencial_request('GET', POTTENCIAL_RES_BASE . '/quotes/' . rawurlencode($cotacaoId), null, 30);
    return $r['ok'] ? pottencial_res_normalizar_cotacao($r['data']) : ['ok' => false, 'erro' => $r['erro'], 'erro_codigo' => $r['erro_codigo'] ?? null];
}

/**
 * Proposta → aceite → apólice. Retoma de onde parou: com proposta_id já
 * gravado (aceite ou apólice falharam antes), não cria outra proposta.
 *
 * @param array $pagamento ['parcelas' => int] (escolhido pelo cliente no aceite)
 */
function pottencial_res_emitir(array $cotacao, array $ficha, array $pagamento, string $inicio, string $fim): array
{
    $propostaId = (string) ($cotacao['proposta_id'] ?? '');

    if ($propostaId === '') {
        $parcelas = min(11, max(1, (int) ($pagamento['parcelas'] ?? 1)));
        $r = pottencial_request('POST', POTTENCIAL_RES_BASE . '/proposals', [
            'quoteId'           => (string) $cotacao['cotacao_externa_id'],
            'policyPeriodStart' => $inicio,
            'policyPeriodEnd'   => $fim,
            'payment'           => [
                'paymentType'                  => 'Boleto',
                'installments'                 => $parcelas,
                'firstInstallmentDueDateDelay' => 5,
            ],
        ]);
        if (!$r['ok']) {
            return ['ok' => false, 'erro' => 'Falha ao enviar a proposta: ' . $r['erro']] + array_intersect_key($r, array_flip(['erro_codigo']));
        }
        $propostaId = (string) ($r['data']['proposalId'] ?? '');
        if ($propostaId === '') {
            return ['ok' => false, 'erro' => 'A Pottencial não devolveu o id da proposta.'];
        }
    }

    $aceite = pottencial_request('POST', POTTENCIAL_RES_BASE . '/proposals/' . rawurlencode($propostaId) . '/accept');
    // Já aceita (tentativa anterior): segue pra apólice.
    if (!$aceite['ok'] && stripos((string) $aceite['erro'], 'accept') === false) {
        return ['ok' => false, 'erro' => 'Proposta criada, mas falhou ao aceitar: ' . $aceite['erro'], 'proposta_id' => $propostaId];
    }

    $apolice = pottencial_request('POST', POTTENCIAL_RES_BASE . '/policies', ['proposalId' => $propostaId]);
    if (!$apolice['ok']) {
        return ['ok' => false, 'erro' => 'Proposta aceita, mas falhou ao emitir a apólice: ' . $apolice['erro'], 'proposta_id' => $propostaId];
    }

    return [
        'ok'             => true,
        'proposta_id'    => $propostaId,
        'apolice_id'     => (string) ($apolice['data']['policyId'] ?? ''),
        'apolice_numero' => (string) ($apolice['data']['policyNumber'] ?? ''),
        'apolice_status' => pottencial_apolice_status_interno((string) ($apolice['data']['status'] ?? '')),
    ];
}

function pottencial_res_consultar_apolice(string $apoliceId): array
{
    $r = pottencial_request('GET', POTTENCIAL_RES_BASE . '/policies/' . rawurlencode($apoliceId), null, 30);
    if (!$r['ok']) {
        return ['ok' => false, 'erro' => $r['erro'], 'erro_codigo' => $r['erro_codigo'] ?? null];
    }
    return [
        'ok'             => true,
        'apolice_numero' => (string) ($r['data']['policyNumber'] ?? ''),
        'apolice_status' => pottencial_apolice_status_interno((string) ($r['data']['status'] ?? '')),
    ];
}

function pottencial_res_documento(string $tipo, string $id): array
{
    return pottencial_documento($tipo, $id, 'residencial');
}
