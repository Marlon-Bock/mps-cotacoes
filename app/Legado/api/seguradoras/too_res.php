<?php
/**
 * Adapter da Too Seguros — Seguro Residencial (/seguros/residenciais).
 * Doc: api-too-residencial.md (raiz do projeto). Mesmo login/host da Fiança
 * (too_token/too_request); o client precisa ter as roles do residencial.
 *
 * Modalidade usada: cotação por importância segurada (POST /cotacao/is —
 * é a que casa com as coberturas da ficha e com a Pottencial) e emissão por
 * BILHETE (POST /bilhete/is — só pessoa física). Preço instantâneo, sem
 * análise; o bilhete nasce "Criado"/"EmissaoSolicitada" e vira "Emitido"
 * em seguida (o cron acompanha); o PDF ("papelaria") sai depois.
 *
 * Identificadores em api_cotacoes:
 *   cotacao_externa_id = codigoCotacao; proposta_id e apolice_id = numeroBilhete
 *   (o bilhete é o documento único — proposta e apólice).
 *
 * A doc NÃO traz o formato da resposta da cotação/bilhete: os valores são
 * lidos por nome tolerante (too_res_valor) — confirmar no 1º teste real.
 *
 * Desligado até ter credenciais + dados de contrato no .env:
 *   TOO_CLIENT_ID, TOO_CLIENT_SECRET, TOO_RES_CODIGO_PRODUTO (contrato),
 *   TOO_SUSEP_CORRETORA, TOO_CNPJ_CORRETOR, TOO_RAZAO_SOCIAL_CORRETOR;
 *   opcionais: TOO_RES_ASSISTENCIA (SemAssistencia|Basica|Completa),
 *   TOO_RES_COMISSAO (%), TOO_RES_PARCELAS, TOO_RES_CODIGO_PRODUTO_BILHETE,
 *   TOO_RES_CNPJ_AGENTE_ARRECADADOR.
 */

const TOO_RES_BASE = '/seguros/residenciais';

/** cob_* da ficha → campo da cotação por IS (grafias exatas da doc) e limite máximo. */
const TOO_RES_COBERTURAS = [
    'cob_incendio'        => ['Incendio', 1000000],
    'cob_rc_familiar'     => ['Rc', 300000],
    'cob_perda_aluguel'   => ['PerdadeAluguel', 50000],
    'cob_vendaval'        => ['Vendaval', 300000],
    'cob_danos_eletricos' => ['DanosEletricos', 100000],
    'cob_roubo'           => ['RouboOuFurto', null],
    'cob_vidros'          => ['VidrosEspelhosEMarmores', null],
    'cob_vazamento'       => ['VazamentoDeTubulacoes', null],
];

/** Status do bilhete (código numérico) → status interno da apólice. */
const TOO_RES_STATUS_BILHETE = [
    1 => 'em_emissao', // EmissaoSolicitada
    2 => 'emitida',    // Emitido
    3 => 'cancelada',  // Cancelado
    4 => 'cancelada',  // CancelamentoSolicitado
    5 => 'em_emissao', // Criado
    6 => 'em_emissao', // ErroEmissao (a Too reprocessa / suporte)
    7 => 'emitida',    // EmitidoComErro
    8 => 'em_emissao', // ErroEmissaoSolicitada
];

function too_res_configurada(): bool
{
    foreach (['TOO_CLIENT_ID', 'TOO_CLIENT_SECRET', 'TOO_RES_CODIGO_PRODUTO', 'TOO_SUSEP_CORRETORA', 'TOO_CNPJ_CORRETOR', 'TOO_RAZAO_SOCIAL_CORRETOR'] as $chave) {
        if (trim((string) env($chave, '')) === '') {
            return false;
        }
    }
    return true;
}

/** Bilhete só pessoa física, e a Too só cota Casa/Apartamento. */
function too_res_atende(array $ficha): bool
{
    return strlen(preg_replace('/\D/', '', (string) ($ficha['cpf_cnpj'] ?? ''))) === 11
        && ($ficha['uso_imovel'] ?? 'habitual') === 'habitual';
}

/**
 * Valor de um campo da resposta procurando por vários nomes, sem diferenciar
 * maiúsculas e em qualquer nível (a doc não traz o schema da resposta).
 */
function too_res_valor(mixed $dados, array $nomes): mixed
{
    if (!is_array($dados)) return null;
    $alvos = array_map('strtolower', $nomes);
    foreach ($dados as $chave => $valor) {
        if (is_string($chave) && in_array(strtolower($chave), $alvos, true) && !is_array($valor)) {
            return $valor;
        }
    }
    foreach ($dados as $valor) {
        if (is_array($valor) && ($achou = too_res_valor($valor, $nomes)) !== null) {
            return $achou;
        }
    }
    return null;
}

/** Tipo do imóvel na Too (só Casa/Apartamento). */
function too_res_tipo_imovel(array $ficha): string
{
    return ($ficha['tipo_imovel'] ?? '') === 'apartamento' ? 'Apartamento' : 'Casa';
}

function too_res_normalizar(array $d, string $codigoCotacao): array
{
    $total   = too_res_valor($d, ['valorPremio', 'premioTotal', 'custoBrutoTotal', 'premioBruto', 'valorTotal']);
    $liquido = too_res_valor($d, ['custoLiquidoTotal', 'premioLiquido', 'valorLiquido']);
    $iof     = too_res_valor($d, ['iof', 'valorIof']);

    $coberturas = [];
    foreach ((array) (too_res_valor_lista($d, 'coberturas') ?? []) as $c) {
        if (!is_array($c)) continue;
        $nome  = (string) (too_res_valor($c, ['descricao', 'nome']) ?? '');
        $valor = (float) (too_res_valor($c, ['importanciaSegurada', 'limiteMaximoIndenizacao', 'valor']) ?? 0);
        if ($nome !== '' && $valor > 0) $coberturas[] = ['nome' => $nome, 'valor' => $valor];
    }

    $comissaoPct = (float) env('TOO_RES_COMISSAO', '0');
    return [
        'ok'                  => true,
        // Preço na hora, sem análise: cotação com prêmio = aprovada.
        'status'              => $total !== null ? 'aprovada' : 'em_analise',
        'status_seguradora'   => $total !== null ? 'Cotada' : 'SemPremio',
        'cotacao_externa_id'  => $codigoCotacao,
        'numero_cotacao'      => $codigoCotacao,
        'premio_total'        => $total !== null ? (float) $total : null,
        'premio_liquido'      => $liquido !== null ? (float) $liquido : null,
        'iof'                 => $iof !== null ? (float) $iof : null,
        'forma_pagamento'     => 'Fatura',
        'parcelas'            => (int) env('TOO_RES_PARCELAS', '1') ?: 1,
        'cobertura'           => null,
        'plano'               => 'Residencial',
        'comissao_percentual' => $comissaoPct ?: null,
        'comissao_valor'      => $comissaoPct && $liquido !== null ? round((float) $liquido * $comissaoPct / 100, 2) : null,
        'detalhes_json'       => json_encode(['coberturas' => $coberturas, 'assistencias' => array_filter([(string) env('TOO_RES_ASSISTENCIA', 'Basica') !== 'SemAssistencia' ? 'Assistência ' . env('TOO_RES_ASSISTENCIA', 'Basica') : null])], JSON_UNESCAPED_UNICODE),
    ];
}

/** Lista (array) de um campo da resposta, por nome sem diferenciar maiúsculas, em qualquer nível. */
function too_res_valor_lista(mixed $dados, string $nome): ?array
{
    if (!is_array($dados)) return null;
    foreach ($dados as $chave => $valor) {
        if (is_string($chave) && strtolower($chave) === strtolower($nome) && is_array($valor)) {
            return $valor;
        }
    }
    foreach ($dados as $valor) {
        if (is_array($valor) && ($achou = too_res_valor_lista($valor, $nome)) !== null) {
            return $achou;
        }
    }
    return null;
}

/** Endereço no formato da Too ('' = imóvel, 'seg_' = segurado). CEP com hífen, como nos exemplos. */
function too_res_endereco(array $f, string $prefixo = ''): array
{
    $cep = preg_replace('/\D/', '', (string) ($f[$prefixo . 'cep'] ?? ''));
    return [
        'bairro'      => (string) ($f[$prefixo . 'bairro'] ?? ''),
        'cep'         => strlen($cep) === 8 ? substr($cep, 0, 5) . '-' . substr($cep, 5) : $cep,
        'cidade'      => (string) ($f[$prefixo . 'cidade'] ?? ''),
        'estado'      => strtoupper((string) ($f[$prefixo . 'uf'] ?? '')),
        'logradouro'  => (string) ($f[$prefixo . 'endereco'] ?? ''),
        'numero'      => (string) (($f[$prefixo . 'numero'] ?? '') !== '' ? $f[$prefixo . 'numero'] : 'S/N'),
        'complemento' => (string) ($f[$prefixo . 'complemento'] ?? ''),
    ];
}

// ─── Contrato do adapter ─────────────────────────────────────────────────

function too_res_cotar(array $ficha, array $pretendentes): array
{
    $corpo = [
        'Assistencia'   => (string) env('TOO_RES_ASSISTENCIA', 'Basica'),
        'CodigoProduto' => (int) env('TOO_RES_CODIGO_PRODUTO', '0'),
        'ValorComissao' => (float) env('TOO_RES_COMISSAO', '0'),
        'TipoImovel'    => too_res_tipo_imovel($ficha),
    ];
    foreach (TOO_RES_COBERTURAS as $campo => [$nome, $maximo]) {
        $valor = round((float) ($ficha[$campo] ?? 0), 2);
        if ($valor <= 0 && $campo !== 'cob_incendio') continue;
        if ($maximo !== null && $valor > $maximo) {
            return ['ok' => false, 'erro' => "Cobertura acima do limite da Too ({$nome}: máximo R$ " . number_format($maximo, 2, ',', '.') . ').', 'erro_codigo' => 'dados'];
        }
        $corpo[$nome] = $valor;
    }

    $r = too_request('POST', TOO_RES_BASE . '/cotacao/is', $corpo);
    if (!$r['ok']) {
        return ['ok' => false, 'erro' => $r['erro'], 'erro_codigo' => $r['erro_codigo'] ?? null];
    }
    $codigo = (string) (too_res_valor($r['data'], ['codigoCotacao', 'codigo', 'id']) ?? '');
    if ($codigo === '') {
        return ['ok' => false, 'erro' => 'A Too não devolveu o código da cotação.'];
    }

    // O POST pode já trazer o prêmio; se não, consulta.
    $resultado = too_res_normalizar(is_array($r['data']) ? $r['data'] : [], $codigo);
    return $resultado['premio_total'] !== null ? $resultado : too_res_consultar($codigo);
}

function too_res_consultar(string $codigoCotacao): array
{
    $r = too_request('GET', TOO_RES_BASE . '/cotacao/is/' . rawurlencode($codigoCotacao), null, 30);
    return $r['ok'] ? too_res_normalizar(is_array($r['data']) ? $r['data'] : [], $codigoCotacao)
                    : ['ok' => false, 'erro' => $r['erro'], 'erro_codigo' => $r['erro_codigo'] ?? null];
}

/** Emite o bilhete (é a contratação — não há aceite/transmitir separado). */
function too_res_emitir(array $cotacao, array $ficha, array $pagamento, string $inicio, string $fim): array
{
    // Bilhete já emitido numa tentativa anterior: só segue.
    if (!empty($cotacao['proposta_id'])) {
        return ['ok' => true, 'proposta_id' => $cotacao['proposta_id'], 'apolice_id' => $cotacao['proposta_id'], 'apolice_numero' => $cotacao['proposta_id'], 'apolice_status' => 'em_emissao'];
    }

    $celular = preg_replace('/\D/', '', (string) ($ficha['tel_celular'] ?? ''));
    $imovel  = too_res_endereco($ficha);
    $r = too_request('POST', TOO_RES_BASE . '/bilhete/is', [
        'codigoCotacao' => (string) $cotacao['cotacao_externa_id'],
        'codigoProduto' => (string) env('TOO_RES_CODIGO_PRODUTO_BILHETE', (string) env('TOO_RES_CODIGO_PRODUTO', '')),
        'codigoPlano'   => '001',
        'enderecoRisco' => ['tipoResidencia' => too_res_tipo_imovel($ficha)] + $imovel,
        'segurado'      => [
            'autorizaComunicacao'        => true,
            'emails'                     => array_values(array_filter([(string) ($ficha['email'] ?? '')])),
            'telefones'                  => [[
                'ddd'              => substr($celular, 0, 2),
                'numero'           => substr($celular, 2),
                'tipoTelefone'     => 'Celular',
                'tiposNotificacao' => ['SMS'],
            ]],
            'nome'                       => (string) ($ficha['nome_completo'] ?? ''),
            'sexo'                       => ($ficha['sexo'] ?? '') === 'feminino' ? 'Feminino' : 'Masculino',
            'dataNascimento'             => (string) ($ficha['data_nascimento'] ?? ''),
            'cpf'                        => str_pad(preg_replace('/\D/', '', (string) ($ficha['cpf_cnpj'] ?? '')), 11, '0', STR_PAD_LEFT),
            'pessoaPoliticamenteExposta' => !empty($ficha['pessoa_politicamente_exposta']),
            'endereco'                   => !empty($ficha['endereco_segurado_igual']) ? $imovel : too_res_endereco($ficha, 'seg_'),
        ],
        'pagamento'             => ['formaPagamento' => 'Fatura', 'numeroParcelas' => (int) env('TOO_RES_PARCELAS', '1') ?: 1],
        'AgenteArrecadador'     => trim((string) env('TOO_RES_CNPJ_AGENTE_ARRECADADOR', '')) !== '',
        'CNPJAgenteArrecadador' => preg_replace('/\D/', '', (string) env('TOO_RES_CNPJ_AGENTE_ARRECADADOR', '')),
        'CNPJCorretora'         => preg_replace('/\D/', '', (string) env('TOO_CNPJ_CORRETOR', '')),
        'CodigoSusepCorretora'  => (int) env('TOO_SUSEP_CORRETORA', '0'),
        'RazaoSocialCorretora'  => (string) env('TOO_RAZAO_SOCIAL_CORRETOR', ''),
    ], 90);
    if (!$r['ok']) {
        return ['ok' => false, 'erro' => 'Falha ao emitir o bilhete: ' . $r['erro']] + array_intersect_key($r, array_flip(['erro_codigo']));
    }

    $bilhete = (string) (too_res_valor($r['data'], ['numeroBilhete', 'numero']) ?? '');
    if ($bilhete === '') {
        return ['ok' => false, 'erro' => 'A Too não devolveu o número do bilhete.'];
    }
    return ['ok' => true, 'proposta_id' => $bilhete, 'apolice_id' => $bilhete, 'apolice_numero' => $bilhete, 'apolice_status' => 'em_emissao'];
}

function too_res_consultar_apolice(string $numeroBilhete): array
{
    $r = too_request('GET', TOO_RES_BASE . '/bilhete/is/' . rawurlencode($numeroBilhete), null, 30);
    if (!$r['ok']) {
        return ['ok' => false, 'erro' => $r['erro'], 'erro_codigo' => $r['erro_codigo'] ?? null];
    }
    $status = (int) (too_res_valor($r['data'], ['status']) ?? 0);
    return [
        'ok'             => true,
        'apolice_numero' => (string) (too_res_valor($r['data'], ['numeroBilhete']) ?? $numeroBilhete),
        'apolice_status' => TOO_RES_STATUS_BILHETE[$status] ?? 'em_emissao',
    ];
}

/** PDF do bilhete (proposta e apólice são o mesmo documento): GET do bilhete → linkPdf → arquivo. */
function too_res_documento(string $tipo, string $numeroBilhete): array
{
    $r = too_request('GET', TOO_RES_BASE . '/bilhete/is/' . rawurlencode($numeroBilhete), null, 30);
    if (!$r['ok']) {
        return ['ok' => false, 'erro' => $r['erro']];
    }
    $link = (string) (too_res_valor($r['data'], ['linkPdf']) ?? '');
    if ($link === '') {
        return ['ok' => false, 'pendente' => true, 'erro' => 'O PDF do bilhete ainda não foi gerado.'];
    }

    $auth = too_token();
    if (!$auth['ok']) {
        return ['ok' => false, 'erro' => $auth['erro']];
    }
    $url = str_starts_with($link, 'http') ? $link : too_url() . '/' . ltrim($link, '/');
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER     => ['Authorization: Bearer ' . $auth['token'], 'clientId: ' . env('TOO_CLIENT_ID', ''), 'clientSecret: ' . env('TOO_CLIENT_SECRET', '')],
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 60,
    ]);
    $pdf  = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($pdf === false || $http >= 400 || strncmp((string) $pdf, '%PDF', 4) !== 0) {
        return ['ok' => false, 'pendente' => $http === 404, 'erro' => "Falha ao baixar o PDF do bilhete (HTTP {$http})."];
    }
    return ['ok' => true, 'conteudo' => (string) $pdf];
}
