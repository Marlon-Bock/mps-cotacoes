<?php
/**
 * Adapter da Pottencial — Fiança Locatícia PJ (locatário CNPJ, plano
 * Tradicional). Produto separado da Fiança PF (pottencial.php), com rotas
 * próprias em /partners/rentalbond/v1; mesmas credenciais, token e host.
 * Doc: api-pottencial-pj.md (raiz do projeto).
 *
 * Diferenças pro adapter PF que moldam este arquivo:
 *   - A cotação é ASSÍNCRONA: o POST devolve só o id (requestId) e o
 *     resultado sai depois — no callbackUrl (obrigatório) ou consultando o
 *     GET. Aqui esperamos até ~40s no worker; o que não ficar pronto o
 *     cron/api-acompanhamento.php reconsulta (status interno 'em_analise'),
 *     e o callback (includes/acao/api-pottencial-pj-callback.php) só adianta
 *     essa reconsulta.
 *   - A emissão é uma chamada só (POST /Proposals/pj, com o proprietário em
 *     insureds) — sem aceite separado. Proposta e apólice são o MESMO PDF,
 *     buscado pelo id da COTAÇÃO; por isso proposta_id e apolice_id guardam
 *     o id da cotação. A API não devolve número de apólice.
 *   - Reanálise exige anexar documentos (extrato, IR) — fica fora daqui
 *     (sem 'reanalisar' no registro: o botão não aparece).
 *   - Sem biometria.
 *
 * Desligado até a Pottencial liberar o produto pro nosso client_id (hoje
 * responde 403 "You cannot consume this service"): POTTENCIAL_PJ_ATIVO=1 no
 * .env liga. Desligado, ficha com pretendente CNPJ vai direto pra MPS.
 */

const POTTENCIAL_PJ_BASE = '/partners/rentalbond/v1';

/** Múltiplos do aluguel por cobertura (limites da doc: aluguel 3–45, danos 1–6, pintura e multa 1–3). */
const POTTENCIAL_PJ_MULTIPLOS = [
    'BasicRental'        => POTTENCIAL_MULTIPLO,
    'encargos'           => POTTENCIAL_MULTIPLO,
    'PropertyDamage'     => 6,
    'Painting'           => 3,
    'FineForTermination' => 3,
];

function pottencial_pj_configurada(): bool
{
    return (string) env('POTTENCIAL_PJ_ATIVO', '') === '1'
        && (string) env('POTTENCIAL_CLIENT_ID', '') !== ''
        && (string) env('POTTENCIAL_CLIENT_SECRET', '') !== '';
}

/** Só pretendente CNPJ (o PF fica com pottencial.php). */
function pottencial_pj_atende(array $ficha): bool
{
    return strlen(preg_replace('/\D/', '', (string) ($ficha['cpf_cnpj'] ?? ''))) === 14;
}

/** Status da cotação PJ → status interno (api_cotacoes.status). */
function pottencial_pj_status_interno(string $status): string
{
    return [
        'Approved'        => 'aprovada',
        'Refused'         => 'recusada',
        // Pendência / pré-aprovada: a Pottencial manda seguir pelo Portal do
        // Parceiro — fica "em análise" com a orientação no detalhe.
        'InAnalysis'      => 'em_analise',
        'Pending'         => 'em_analise',
        'PreApproved'     => 'em_analise',
        'WaitingEmission' => 'proposta_escolhida',
        'Emitted'         => 'proposta_escolhida',
        'Expired'         => 'expirada',
    ][$status] ?? 'em_analise';
}

/** Token do callback (na URL): sem ele qualquer um poderia disparar reconsultas. */
function pottencial_pj_callback_token(): string
{
    return substr(hash_hmac('sha256', 'pottencial-pj-callback', (string) env('POTTENCIAL_CLIENT_SECRET', '')), 0, 32);
}

function pottencial_pj_callback_url(): string
{
    $url = (string) env('POTTENCIAL_PJ_CALLBACK_URL', '');
    if ($url === '') {
        $url = rtrim((string) env('APP_URL', ''), '/') . url('/api/webhook/pottencial-pj');
    }
    return $url . '/' . pottencial_pj_callback_token();
}

/** Data no formato da API PJ (datetime sem fuso). */
function pottencial_pj_data(DateTimeInterface $d): string
{
    return $d->format('Y-m-d\T00:00:00');
}

/** Endereço no formato QuoteAddress (o PF usa outro: state/country/type). */
function pottencial_pj_endereco(array $e): array
{
    return [
        'zipCode'    => preg_replace('/\D/', '', (string) ($e['cep'] ?? '')),
        'street'     => mb_substr((string) ($e['endereco'] ?? ''), 0, 200),
        'number'     => (string) (($e['numero'] ?? '') !== '' ? $e['numero'] : 'S/N'),
        'complement' => mb_substr((string) ($e['complemento'] ?? ''), 0, 50),
        'district'   => mb_substr((string) ($e['bairro'] ?? ''), 0, 100),
        'city'       => (string) ($e['cidade'] ?? ''),
        'uf'         => strtoupper((string) ($e['uf'] ?? '')),
    ];
}

/** Coberturas: aluguel (obrigatória) + encargos preenchidos + as adicionais marcadas na ficha. */
function pottencial_pj_coberturas(array $ficha): array
{
    $aluguel = round((float) ($ficha['valor_aluguel'] ?? 0), 2);
    $m = POTTENCIAL_PJ_MULTIPLOS;

    $coberturas = [['type' => 'BasicRental', 'multiple' => $m['BasicRental'], 'value' => $aluguel]];
    foreach ([
        'valor_condominio' => 'Condominium',
        'valor_iptu'       => 'Iptu',
        'valor_agua'       => 'Water',
        'valor_energia'    => 'Electricity',
        'valor_gas'        => 'Gas',
    ] as $campo => $tipo) {
        $valor = round((float) ($ficha[$campo] ?? 0), 2);
        if ($valor > 0) {
            $coberturas[] = ['type' => $tipo, 'multiple' => $m['encargos'], 'value' => $valor];
        }
    }
    if (!empty($ficha['danos_imovel'])) {
        $coberturas[] = ['type' => 'PropertyDamage', 'multiple' => $m['PropertyDamage'], 'value' => $aluguel];
    }
    if (!empty($ficha['pintura_interna']) || !empty($ficha['pintura_externa'])) {
        $coberturas[] = ['type' => 'Painting', 'multiple' => $m['Painting'], 'value' => $aluguel];
    }
    if (!empty($ficha['multa_contratual'])) {
        $coberturas[] = ['type' => 'FineForTermination', 'multiple' => $m['FineForTermination'], 'value' => $aluguel];
    }

    return $coberturas;
}

/**
 * Corpo da cotação (QuoteRequest). Locatário principal = o pretendente CNPJ;
 * os adicionais vão como solidários (PF ou PJ, até 5). O proprietário
 * (insureds) só é exigido na emissão.
 *
 * @param array $pretendentes [['nome'=>..,'cpf'=>..], ...]
 */
function pottencial_pj_montar_cotacao(array $ficha, array $pretendentes, DateTimeInterface $inicio, DateTimeInterface $fim): array
{
    $endereco = pottencial_pj_endereco($ficha);

    $tenants = [[
        'type'        => 'Principal',
        'name'        => (string) ($ficha['nome_completo'] ?? ''),
        'document'    => preg_replace('/\D/', '', (string) ($ficha['cpf_cnpj'] ?? '')),
        'email'       => (string) ($ficha['email'] ?? ''),
        'phoneNumber' => preg_replace('/\D/', '', (string) ($ficha['tel_celular'] ?? '')),
        'address'     => $endereco,
    ]];
    foreach (array_slice($pretendentes, 0, 5) as $p) {
        $doc = preg_replace('/\D/', '', (string) ($p['cpf'] ?? ''));
        if ($doc === '') continue;
        $tenants[] = ['type' => 'Solidary', 'name' => (string) ($p['nome'] ?? ''), 'document' => $doc, 'address' => $endereco];
    }

    $primeiroVencimento = (new DateTimeImmutable('today'))->modify('+5 days');

    return [
        'dateInsuranceStart' => pottencial_pj_data($inicio),
        'dateInsuranceEnd'   => pottencial_pj_data($fim),
        'dateRentalStart'    => pottencial_pj_data($inicio),
        'dateRentalEnd'      => pottencial_pj_data($fim),
        'planType'           => 'Traditional',
        'rentalType'         => ($ficha['tipo_ficha'] ?? '') === 'residencial' ? 'Residential' : 'Commercial',
        'isShoppingCenter'   => false,
        'callbackUrl'        => pottencial_pj_callback_url(),
        'broker'             => [
            'name'                 => (string) env('POTTENCIAL_RAZAO_BROKER', 'MPS Corretora de Seguros'),
            'document'             => preg_replace('/\D/', '', (string) env('POTTENCIAL_CNPJ_BROKER', '')),
            'commissionPercentage' => (float) env('POTTENCIAL_PJ_COMISSAO', (string) POTTENCIAL_COMISSAO),
        ],
        'tenants'   => $tenants,
        'address'   => $endereco,
        'coverages' => pottencial_pj_coberturas($ficha),
        'payment'   => ['type' => 'Bill', 'installments' => 1, 'firstDueDate' => pottencial_pj_data($primeiroVencimento)],
    ];
}

/** Resultado normalizado a partir da QuoteResponse. */
function pottencial_pj_normalizar_cotacao(array $q): array
{
    $nomes = [
        'BasicRental' => 'Básica (aluguel)', 'Condominium' => 'Condomínio', 'Iptu' => 'IPTU',
        'Water' => 'Água', 'Electricity' => 'Luz', 'Gas' => 'Gás', 'PropertyDamage' => 'Danos ao imóvel',
        'Painting' => 'Pintura', 'FineForTermination' => 'Multa por rescisão',
    ];

    $basica = null;
    $detalheCoberturas = [];
    foreach ($q['coverages'] ?? [] as $cov) {
        $valor = (float) ($cov['lmi'] ?? 0);
        if (($cov['type'] ?? '') === 'BasicRental') {
            $basica = isset($cov['lmi']) ? $valor : null;
        }
        if ($valor > 0) {
            $detalheCoberturas[] = ['nome' => $nomes[$cov['type'] ?? ''] ?? (string) ($cov['type'] ?? ''), 'valor' => $valor];
        }
    }

    $statusSeguradora = (string) ($q['status'] ?? '');
    $liquido  = isset($q['commercialPremium']) ? (float) $q['commercialPremium'] : null;
    $comissao = isset($q['broker']['commissionPercentage']) ? (float) $q['broker']['commissionPercentage'] : null;

    return [
        'ok'                  => true,
        'status'              => pottencial_pj_status_interno($statusSeguradora),
        'status_seguradora'   => $statusSeguradora,
        'cotacao_externa_id'  => (string) ($q['id'] ?? ''),
        'numero_cotacao'      => isset($q['quoteNumber']) ? (string) $q['quoteNumber'] : null,
        'premio_total'        => isset($q['totalPremium']) ? (float) $q['totalPremium'] : null,
        'premio_liquido'      => $liquido,
        'iof'                 => isset($q['iof']) ? (float) $q['iof'] : null,
        'forma_pagamento'     => ($q['payment']['type'] ?? '') === 'Invoice' ? 'Fatura' : 'Boleto',
        'parcelas'            => isset($q['payment']['installments']) ? (int) $q['payment']['installments'] : null,
        'cobertura'           => $basica,
        'plano'               => 'Tradicional PJ',
        'comissao_percentual' => $comissao !== null ? round($comissao * 100, 2) : null,
        'comissao_valor'      => $comissao !== null && $liquido !== null ? round($liquido * $comissao, 2) : null,
        'vigencia_inicio'     => isset($q['dateInsuranceStart']) ? substr((string) $q['dateInsuranceStart'], 0, 10) : null,
        'vigencia_fim'        => isset($q['dateInsuranceEnd']) ? substr((string) $q['dateInsuranceEnd'], 0, 10) : null,
        'detalhes_json'       => json_encode(array_filter([
            'coberturas'   => $detalheCoberturas,
            'assistencias' => [],
            // Pendência / pré-aprovação: o que a Pottencial pede (segue pelo portal dela).
            'observacao'   => (string) ($q['statusDescription'] ?? '') ?: null,
        ], fn($v) => $v !== null), JSON_UNESCAPED_UNICODE),
    ];
}

// ─── Contrato do adapter ─────────────────────────────────────────────────

/**
 * Cota a ficha. Assíncrono na Pottencial: cria e espera o resultado por até
 * ~40s; se não sair, devolve "em análise" com o id — o cron reconsulta.
 */
function pottencial_pj_cotar(array $ficha, array $pretendentes): array
{
    if (preg_replace('/\D/', '', (string) env('POTTENCIAL_CNPJ_BROKER', '')) === '') {
        return ['ok' => false, 'erro' => 'POTTENCIAL_CNPJ_BROKER não configurado no .env.'];
    }

    // Vigência de 3 a 60 meses (regra da API).
    $meses  = min(60, max(3, (int) ($ficha['vigencia_contrato'] ?? 12)));
    $inicio = new DateTimeImmutable('today');
    $fim    = $inicio->modify("+{$meses} months");

    $r = pottencial_request('POST', POTTENCIAL_PJ_BASE . '/Quotes', pottencial_pj_montar_cotacao($ficha, $pretendentes, $inicio, $fim));
    if (!$r['ok']) {
        return ['ok' => false, 'erro' => $r['erro'], 'erro_codigo' => $r['erro_codigo'] ?? null];
    }
    $id = (string) ($r['data']['id'] ?? '');
    if ($id === '') {
        return ['ok' => false, 'erro' => 'A Pottencial não devolveu o id da cotação PJ.'];
    }

    $ultimo = null;
    for ($i = 0; $i < 8; $i++) {
        sleep(5);
        $c = pottencial_pj_consultar($id);
        if ($c['ok']) {
            $ultimo = $c;
            if ($c['status_seguradora'] !== 'InAnalysis') break;
        }
    }

    return $ultimo ?? [
        'ok'                 => true,
        'status'             => 'em_analise',
        'status_seguradora'  => 'Processing',
        'cotacao_externa_id' => $id,
        'plano'              => 'Tradicional PJ',
    ];
}

/** Reconsulta uma cotação PJ (404 logo depois de criar = ainda processando). */
function pottencial_pj_consultar(string $cotacaoId): array
{
    $r = pottencial_request('GET', POTTENCIAL_PJ_BASE . '/Quotes/' . rawurlencode($cotacaoId), null, 30);
    if (!$r['ok'] && ($r['status'] ?? 0) === 404) {
        return ['ok' => true, 'status' => 'em_analise', 'status_seguradora' => 'Processing', 'cotacao_externa_id' => $cotacaoId, 'plano' => 'Tradicional PJ'];
    }
    return $r['ok'] ? pottencial_pj_normalizar_cotacao($r['data']) : ['ok' => false, 'erro' => $r['erro'], 'erro_codigo' => $r['erro_codigo'] ?? null];
}

/**
 * Emite proposta + apólice numa chamada (POST /Proposals/pj), com o
 * proprietário como segurado. Boleto, 1 parcela, 1º vencimento em 5 dias.
 * Retorna proposta_id/apolice_id = id da COTAÇÃO (é por ele que se busca o
 * PDF e se acompanha a emissão).
 */
function pottencial_pj_emitir(array $cotacao, array $ficha, array $proprietario, string $inicio, string $fim): array
{
    $cotacaoId = (string) $cotacao['cotacao_externa_id'];
    $emitida = static fn(): array => [
        'ok' => true, 'proposta_id' => $cotacaoId, 'apolice_id' => $cotacaoId,
        'apolice_numero' => '', 'apolice_status' => 'em_emissao',
    ];

    $atual = pottencial_pj_consultar($cotacaoId);
    if (!$atual['ok']) {
        return ['ok' => false, 'erro' => $atual['erro']];
    }
    // Já transmitida (tentativa anterior que caiu no meio): só segue.
    if (in_array($atual['status_seguradora'], ['WaitingEmission', 'Emitted'], true)) {
        return $emitida();
    }
    if ($atual['status'] !== 'aprovada') {
        return ['ok' => false, 'erro' => 'A cotação não está liberada para emissão — status atual: ' . api_status_label($atual['status'])];
    }

    $doc = preg_replace('/\D/', '', (string) ($proprietario['cpf_cnpj'] ?? ''));
    $r = pottencial_request('POST', POTTENCIAL_PJ_BASE . '/Proposals/pj', [
        'quoteId'      => $cotacaoId,
        'paymentType'  => 'Bill',
        'installments' => 1,
        'firstDueDate' => pottencial_pj_data((new DateTimeImmutable('today'))->modify('+5 days')),
        'insureds'     => [[
            'name'           => (string) ($proprietario['nome_completo'] ?? ''),
            'documentNumber' => $doc,
            'email'          => (string) ($proprietario['email'] ?? ''),
            // "Tipo do segurado" (não documentado além de integer): 1 = PF, 2 = PJ, como no rentalType.
            'type'           => strlen($doc) === 14 ? 2 : 1,
            'address'        => pottencial_pj_endereco($proprietario),
        ]],
        'callbackUrl'  => pottencial_pj_callback_url(),
    ]);

    if (!$r['ok']) {
        // 502/timeout: pode ter transmitido mesmo assim — confere na cotação.
        if ((int) ($r['status'] ?? 0) === 0 || (int) $r['status'] >= 500) {
            $depois = pottencial_pj_consultar($cotacaoId);
            if (in_array($depois['status_seguradora'] ?? '', ['WaitingEmission', 'Emitted'], true)) {
                return $emitida();
            }
        }
        return ['ok' => false, 'erro' => 'Falha ao emitir a proposta: ' . $r['erro']]
            + array_intersect_key($r, array_flip(['erro_codigo', 'erro_apolice']));
    }

    return $emitida();
}

/** Acompanha a emissão pela cotação: WaitingEmission → Emitted. */
function pottencial_pj_consultar_apolice(string $cotacaoId): array
{
    $r = pottencial_request('GET', POTTENCIAL_PJ_BASE . '/Quotes/' . rawurlencode($cotacaoId), null, 30);
    if (!$r['ok']) {
        return ['ok' => false, 'erro' => $r['erro'], 'erro_codigo' => $r['erro_codigo'] ?? null];
    }

    $status = (string) ($r['data']['status'] ?? '');
    return [
        'ok'             => true,
        // A doc não traz o número da apólice; se a consulta passar a trazer, usa.
        'apolice_numero' => (string) ($r['data']['policyNumber'] ?? ''),
        'apolice_status' => match ($status) {
            'Emitted' => 'emitida',
            'Expired' => 'expirada',
            default   => 'em_emissao',
        },
    ];
}

/** PDF da proposta/apólice (o mesmo documento) — vem em base64 dentro de JSON. */
function pottencial_pj_documento(string $tipo, string $cotacaoId): array
{
    if (!in_array($tipo, ['proposta', 'apolice'], true) || $cotacaoId === '') {
        return ['ok' => false, 'erro' => 'Documento inválido.'];
    }

    // ~50s pra gerar no PF; 90s fica abaixo do limite de 100s do Cloudflare.
    $r = pottencial_request('GET', POTTENCIAL_PJ_BASE . '/Proposals/pj/' . rawurlencode($cotacaoId) . '/document', null, 90);
    if (!$r['ok']) {
        if (($r['status'] ?? 0) === 404) {
            return ['ok' => false, 'pendente' => true, 'erro' => $r['erro']];
        }
        return ['ok' => false, 'erro' => $r['erro']];
    }

    $pdf = base64_decode((string) ($r['data']['pdf'] ?? ''), true);
    if ($pdf === false || strncmp($pdf, '%PDF', 4) !== 0) {
        return ['ok' => false, 'pendente' => true, 'erro' => 'A Pottencial ainda não devolveu o PDF.'];
    }

    return ['ok' => true, 'conteudo' => $pdf];
}
