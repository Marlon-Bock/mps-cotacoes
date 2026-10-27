<?php
/**
 * Adapter da Too Seguros — Fiança Locatícia (API "Regras e Payloads V2").
 * Doc: https://developers.tooseguros.com.br/documentacao/api-fianca-locaticia-regras-e-payloads-v2
 * (cópia em mps/api-too-fianca.md; plano em docs/integracao-too-seguros.md).
 *
 * Só PESSOA FÍSICA RESIDENCIAL (too_atende). Fica desligada até as
 * credenciais estarem no .env (too_configurada):
 *   TOO_API_URL, TOO_CLIENT_ID, TOO_CLIENT_SECRET, TOO_CNPJ_CORRETOR,
 *   TOO_RAZAO_SOCIAL_CORRETOR, TOO_COMISSAO (0.10–0.40), TOO_PERIODO_INDENITARIO
 *   (12/15/18/24/30 meses).
 *
 * Fluxo na Too:
 *   ficha (POST /fianca/proposta/ficha) → análise de crédito (assíncrona, até
 *   2h úteis) → biometria → cálculo do prêmio (POST /fianca/proposta/cotacao)
 *   → formas de pagamento → dados do proprietário/pagamento (PUT ficha) →
 *   transmitir (gera a apólice).
 *
 * Identificadores guardados em api_cotacoes:
 *   cotacao_externa_id = número da proposta da Too;
 *   proposta_id        = número da proposta (PDF da proposta);
 *   apolice_id         = "P{proposta}" até a Too devolver o número da apólice
 *                        (o número fica em apolice_numero).
 * O CPF e a vigência (que várias rotas pedem) vêm da ficha — too_ficha().
 */

/** Credenciais e dados da corretora no .env? Sem isso a Too fica desligada. */
function too_configurada(): bool
{
    foreach (['TOO_CLIENT_ID', 'TOO_CLIENT_SECRET', 'TOO_CNPJ_CORRETOR', 'TOO_RAZAO_SOCIAL_CORRETOR'] as $chave) {
        if (trim((string) env($chave, '')) === '') {
            return false;
        }
    }
    return true;
}

/** A Too só cota Fiança de pessoa física residencial. */
function too_atende(array $ficha): bool
{
    return ($ficha['tipo_ficha'] ?? '') === 'residencial'
        && strlen(preg_replace('/\D/', '', (string) ($ficha['cpf_cnpj'] ?? ''))) === 11;
}

// ─── Cliente HTTP ─────────────────────────────────────────────────────────

function too_url(): string
{
    return rtrim((string) env('TOO_API_URL', 'https://openapi-uat.tooseguros.com.br'), '/');
}

/** Token client_credentials (vale 10h — cache até 30 min antes de vencer). */
function too_token(bool $renovar = false): array
{
    $cache = api_cache_dir() . '/too_token.json';
    if (!$renovar && is_file($cache)) {
        $dados = json_decode((string) file_get_contents($cache), true);
        if (!empty($dados['token']) && ($dados['expira'] ?? 0) > time() + 1800) {
            return ['ok' => true, 'token' => $dados['token']];
        }
    }

    $ch = curl_init(too_url() . '/authentication');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => 'grant_type=client_credentials',
        CURLOPT_HTTPHEADER     => [
            'Content-Type: application/x-www-form-urlencoded',
            'Authorization: Basic ' . base64_encode(env('TOO_CLIENT_ID', '') . ':' . env('TOO_CLIENT_SECRET', '')),
        ],
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => 20,
    ]);
    $resp = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $erroCurl = curl_error($ch);
    curl_close($ch);

    if ($resp === false) {
        return ['ok' => false, 'erro' => 'Falha ao conectar na Too' . ($erroCurl ? " ({$erroCurl})" : '.'), 'erro_codigo' => 'indisponivel'];
    }
    $data = json_decode((string) $resp, true);
    if ($http >= 400 || empty($data['access_token'])) {
        return ['ok' => false, 'erro' => "Autenticação Too falhou (HTTP {$http}): " . substr((string) $resp, 0, 300), 'erro_codigo' => null];
    }

    $expira = time() + (int) ($data['expires_in'] ?? 36000);
    @file_put_contents($cache, json_encode(['token' => $data['access_token'], 'expira' => $expira]));
    return ['ok' => true, 'token' => $data['access_token']];
}

/**
 * Chamada autenticada (JSON). Token recusado (401): renova uma vez.
 *
 * @return array{ok:bool, status:int, data?:mixed, erro?:string, erro_codigo?:?string}
 */
function too_request(string $metodo, string $caminho, ?array $corpo = null, int $timeout = 60, bool $renovou = false): array
{
    $auth = too_token($renovou);
    if (!$auth['ok']) {
        return ['status' => 0] + $auth;
    }

    $ch = curl_init(too_url() . $caminho);
    $opcoes = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => $metodo,
        // clientId/clientSecret também no header: o Residencial exige em toda
        // chamada (a doc da Fiança lista os dois como opcionais).
        CURLOPT_HTTPHEADER     => [
            'Authorization: Bearer ' . $auth['token'], 'Content-Type: application/json', 'Accept: application/json',
            'clientId: ' . env('TOO_CLIENT_ID', ''), 'clientSecret: ' . env('TOO_CLIENT_SECRET', ''),
        ],
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT        => $timeout,
    ];
    if ($corpo !== null) {
        $opcoes[CURLOPT_POSTFIELDS] = json_encode($corpo, JSON_UNESCAPED_UNICODE);
    } elseif ($metodo === 'POST') {
        $opcoes[CURLOPT_POSTFIELDS] = '';
    }
    curl_setopt_array($ch, $opcoes);

    $resp = curl_exec($ch);
    $http = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $erroCurl = curl_error($ch);
    curl_close($ch);

    if ($resp === false) {
        return ['ok' => false, 'status' => 0, 'erro' => 'Falha ao conectar na Too' . ($erroCurl ? " ({$erroCurl})" : '.'), 'erro_codigo' => 'indisponivel'];
    }
    if ($http === 401 && !$renovou) {
        return too_request($metodo, $caminho, $corpo, $timeout, true);
    }

    $data = json_decode((string) $resp, true);
    if ($http >= 400) {
        // Erros da Too: {"errors": ["mensagem", ...]}
        $msgs = is_array($data['errors'] ?? null) ? $data['errors'] : [$data['message'] ?? $data['title'] ?? substr((string) $resp, 0, 300)];
        $msg  = implode(' | ', array_map(fn($m) => is_array($m) ? json_encode($m, JSON_UNESCAPED_UNICODE) : (string) $m, $msgs));
        return ['ok' => false, 'status' => $http, 'erro' => "Too HTTP {$http}: {$msg}", 'erro_codigo' => too_classificar_erro($http)];
    }

    return ['ok' => true, 'status' => $http, 'data' => $data];
}

/** Falha da Too → código interno (ver api_erro_codigos()). */
function too_classificar_erro(int $http): ?string
{
    if ($http === 0 || $http >= 500 || in_array($http, [408, 429], true)) {
        return 'indisponivel';
    }
    if (in_array($http, [401, 403, 404], true)) {
        return null; // credencial/rota nossa
    }
    return 'dados';
}

// ─── Dados da ficha ───────────────────────────────────────────────────────

/** Ficha (seguro_fianca) da cotação da Too pelo número da proposta. */
function too_ficha(string $numeroProposta): ?array
{
    return db_fetch_one(
        "SELECT sf.* FROM api_cotacoes ac JOIN seguro_fianca sf ON sf.id = ac.seguro_fianca_id
          WHERE ac.seguradora = 'too' AND ac.cotacao_externa_id = ? LIMIT 1",
        ['s', $numeroProposta]
    ) ?: null;
}

function too_cpf(array $ficha): string
{
    return preg_replace('/\D/', '', (string) ($ficha['cpf_cnpj'] ?? ''));
}

/** Vínculo empregatício do formulário → opção da Too. */
function too_vinculo(?string $valor): ?string
{
    $v = strtolower((string) @iconv('UTF-8', 'ASCII//TRANSLIT', trim((string) $valor)));
    $mapa = [
        'clt' => 'Clt', 'autonomo' => 'Autonomo', 'empresario' => 'Empresario',
        'funcionario publico' => 'FuncionarioPublico', 'aposentado' => 'Aposentado',
        'renda de aluguel' => 'RendaProvenienteAluguel', 'estudante' => 'Estudante',
    ];
    foreach ($mapa as $trecho => $opcao) {
        if (str_contains($v, $trecho)) {
            return $opcao;
        }
    }
    return null;
}

/** Índice de reajuste do formulário → opção da Too (padrão IGP-M). */
function too_indice(?string $indice): string
{
    $i = strtoupper(preg_replace('/[^A-Z]/i', '', (string) $indice));
    return ['IGPM' => 'IGP_M', 'IPCA' => 'IPCA', 'INPC' => 'INPC', 'IGPDI' => 'IGP_DI', 'ICV' => 'ICV', 'IPC' => 'IP', 'IPG' => 'IP'][$i] ?? 'IGP_M';
}

/** Valor de encargo: a Too aceita vazio ou >= R$ 15. */
function too_valor(?string $valor): ?float
{
    $v = (float) $valor;
    return $v > 0 ? round($v, 2) : null;
}

/**
 * Regras da Too que dá pra checar antes de chamar (a API recusaria com 400).
 * Devolve a mensagem em português ou null se a ficha está ok.
 */
function too_validar(array $ficha, array $pretendentes): ?string
{
    $aluguel  = (float) ($ficha['valor_aluguel'] ?? 0);
    $encargos = ['Água' => 'valor_agua', 'Condomínio' => 'valor_condominio', 'Gás' => 'valor_gas', 'IPTU' => 'valor_iptu', 'Luz' => 'valor_energia'];
    $total    = $aluguel;

    if ($aluguel < 200) {
        return 'A Too exige aluguel de no mínimo R$ 200,00.';
    }
    foreach ($encargos as $rotulo => $campo) {
        $v = (float) ($ficha[$campo] ?? 0);
        if ($v > 0 && $v < 15) {
            return "A Too exige {$rotulo} de no mínimo R$ 15,00 (ou não informar).";
        }
        $total += $v;
    }
    if ($total > 24999) {
        return 'A Too aceita aluguel + encargos de até R$ 24.999,00.';
    }
    $meses = (int) ($ficha['vigencia_contrato'] ?? 0);
    if ($meses < 4 || $meses > 60) {
        return 'A Too exige vigência do contrato entre 4 e 60 meses.';
    }
    if (count($pretendentes) + 1 > 4) {
        return 'A Too aceita no máximo 4 pretendentes.';
    }
    foreach ($pretendentes as $p) {
        if (strlen(preg_replace('/\D/', '', (string) $p['cpf_cnpj'])) !== 11) {
            return 'A Too só aceita pretendentes pessoa física (CPF).';
        }
    }
    $faltando = array_filter([
        'data de nascimento'    => empty($ficha['data_nascimento']),
        'renda mensal'          => (float) ($ficha['salario_bruto'] ?? 0) <= 0,
        'vínculo empregatício'  => too_vinculo($ficha['vinculo_empregaticio'] ?? '') === null,
        'profissão'             => trim((string) ($ficha['profissao'] ?? '')) === '',
    ]);
    if ($faltando) {
        return 'Faltam dados do pretendente exigidos pela Too: ' . implode(', ', array_keys($faltando)) . '.';
    }
    foreach ($pretendentes as $p) {
        if (empty($p['data_nascimento'])) {
            return 'A Too exige a data de nascimento de todos os pretendentes.';
        }
    }
    return null;
}

// ─── Cotação (ficha + análise de crédito) ─────────────────────────────────

/**
 * Registra a ficha e pede a análise de crédito (assíncrona). O resultado
 * chega pela consulta (cron de acompanhamento / "Atualizar").
 */
function too_cotar(array $ficha, array $pretendentesIgnorados = []): array
{
    // Pretendentes adicionais com nascimento/renda (tabela pretendentes).
    $pretendentes = db_fetch_all('SELECT * FROM pretendentes WHERE seguro_fianca_id = ? ORDER BY numero_pretendente', ['i', (int) $ficha['id']]);

    $erro = too_validar($ficha, $pretendentes);
    if ($erro !== null) {
        return ['ok' => false, 'erro' => $erro, 'erro_codigo' => 'fora_regras'];
    }

    $lista = [[
        'nome'                            => (string) $ficha['nome_completo'],
        'nomeSocial'                      => ($ficha['nome_social'] ?? '') ?: null,
        'cpf'                             => too_cpf($ficha),
        'dataNascimento'                  => date('Y-m-d', strtotime((string) $ficha['data_nascimento'])),
        'residiraImovel'                  => true,
        'responsavelFinanceiroPeloImovel' => true,
        'rendaFixaMensal'                 => round((float) $ficha['salario_bruto'], 2),
        'vinculoEmpregaticio'             => too_vinculo($ficha['vinculo_empregaticio']),
        'profissao'                       => mb_substr(trim((string) $ficha['profissao']), 0, 40),
    ]];
    foreach ($pretendentes as $p) {
        $lista[] = [
            'nome'                            => (string) $p['nome_completo'],
            'cpf'                             => preg_replace('/\D/', '', (string) $p['cpf_cnpj']),
            'dataNascimento'                  => date('Y-m-d', strtotime((string) $p['data_nascimento'])),
            'residiraImovel'                  => false,
            'responsavelFinanceiroPeloImovel' => false,
            'rendaFixaMensal'                 => round((float) ($p['salario_bruto'] ?? 0), 2),
        ];
    }

    $r = too_request('POST', '/fianca/proposta/ficha', [
        'cnpjCorretor'        => preg_replace('/\D/', '', (string) env('TOO_CNPJ_CORRETOR', '')),
        'razaoSocialCorretor' => (string) env('TOO_RAZAO_SOCIAL_CORRETOR', ''),
        'pretendentes'        => $lista,
        'locacao' => [
            'finalidadeLocacao' => 'Residencial',
            'cep'               => preg_replace('/\D/', '', (string) $ficha['cep']),
            'logradouro'        => (string) $ficha['endereco'],
            'numero'            => (string) ($ficha['numero'] ?: 'S/N'),
            'complemento'       => ($ficha['complemento'] ?? '') ?: null,
            'bairro'            => (string) $ficha['bairro'],
            'cidade'            => (string) $ficha['cidade'],
            'uf'                => strtoupper((string) $ficha['uf']),
        ],
        'coberturas' => too_coberturas($ficha),
    ]);
    if (!$r['ok']) {
        return ['ok' => false, 'erro' => $r['erro'], 'erro_codigo' => $r['erro_codigo'] ?? null];
    }

    $numero = (string) ($r['data']['numeroProposta'] ?? $r['data']['numeroFicha'] ?? $r['data']['proposta']['numeroProposta'] ?? '');
    if ($numero === '') {
        return ['ok' => false, 'erro' => 'A Too não devolveu o número da proposta.', 'erro_codigo' => null];
    }

    $analise = too_request('POST', '/fianca/credito/' . too_cpf($ficha) . "/{$numero}/analisar");
    if (!$analise['ok']) {
        // Ficha criada mas a análise não foi pedida: a reconsulta mostra
        // "Proposta criada" e o "Atualizar" tenta de novo (too_consultar).
        error_log('[too] Falha ao pedir análise de crédito da proposta ' . $numero . ': ' . $analise['erro']);
    }

    return [
        'ok'                 => true,
        'status'             => 'em_analise',
        'status_seguradora'  => $analise['ok'] ? 'EmAnaliseCredito' : 'PropostaCriada',
        'cotacao_externa_id' => $numero,
        'numero_cotacao'     => $numero,
        'plano'              => 'Fiança Residencial',
        'detalhes_json'      => json_encode(['analise_pedida' => $analise['ok']]),
    ];
}

/** Coberturas da ficha no formato da Too (danos/multa/pintura: valor do aluguel ou zero). */
function too_coberturas(array $ficha): array
{
    $aluguel = round((float) $ficha['valor_aluguel'], 2);
    $adicional = static fn(string $campo): float => !empty($ficha[$campo]) ? $aluguel : 0.0;

    return array_filter([
        'valorAluguel'           => $aluguel,
        'valorAgua'              => too_valor($ficha['valor_agua'] ?? null),
        'valorCondominio'        => too_valor($ficha['valor_condominio'] ?? null),
        'valorGas'               => too_valor($ficha['valor_gas'] ?? null),
        'valorIptu'              => too_valor($ficha['valor_iptu'] ?? null),
        'valorLuz'               => too_valor($ficha['valor_energia'] ?? null),
        'valorDanosAoImovel'     => $adicional('danos_imovel'),
        'valorDanosAMoveis'      => $adicional('cobertura_moveis'),
        'valorMultasContratuais' => $adicional('multa_contratual'),
        'valorPinturaExterna'    => $adicional('pintura_externa'),
        'valorPinturaInterna'    => $adicional('pintura_interna'),
    ], static fn($v) => $v !== null);
}

/** Status da proposta na Too → status interno da cotação. */
function too_status_interno(int $status, ?int $biometria = null): string
{
    return match (true) {
        $status === 16                       => 'aguardando_biometria',
        $status === 8 && $biometria === 4    => 'biometria_recusada',
        $status === 8 && $biometria === 3    => 'biometria_concluida',
        $status === 8                        => 'aprovada',
        in_array($status, [6, 15], true)     => 'recusada',
        in_array($status, [9, 10, 13, 14], true) => 'proposta_escolhida',
        $status === 11                       => 'expirada',
        $status === 12                       => 'cancelada',
        default                              => 'em_analise', // 1, 2, 5, 7 (pendenciada)
    };
}

/** GET status da proposta (v3) — devolve o objeto "proposta". */
function too_status_proposta(array $ficha, string $numero): array
{
    $r = too_request('GET', '/fianca/proposta/v3/' . too_cpf($ficha) . "/status/{$numero}", null, 30);
    if (!$r['ok']) {
        return $r;
    }
    return ['ok' => true, 'proposta' => $r['data']['proposta'] ?? $r['data']];
}

/**
 * Reconsulta: status da análise; aprovada e ainda sem prêmio → calcula a
 * cotação (POST /fianca/proposta/cotacao) pra mostrar o valor.
 */
function too_consultar(string $numero): array
{
    $ficha = too_ficha($numero);
    if (!$ficha) {
        return ['ok' => false, 'erro' => 'Ficha da proposta Too não encontrada.', 'erro_codigo' => null];
    }

    $st = too_status_proposta($ficha, $numero);
    if (!$st['ok']) {
        return ['ok' => false, 'erro' => $st['erro'], 'erro_codigo' => $st['erro_codigo'] ?? null];
    }
    $p         = $st['proposta'];
    $codigo    = (int) ($p['status'] ?? 0);
    $biometria = isset($p['biometria']['status']['codigo']) ? (int) $p['biometria']['status']['codigo'] : null;

    // Ficha criada mas a análise ainda não foi pedida (falhou no envio).
    if ($codigo === 1) {
        too_request('POST', '/fianca/credito/' . too_cpf($ficha) . "/{$numero}/analisar");
    }

    $resultado = [
        'ok'                 => true,
        'status'             => too_status_interno($codigo, $biometria),
        'status_seguradora'  => (string) ($p['descricaoStatus'] ?? $codigo),
        'cotacao_externa_id' => $numero,
        'numero_cotacao'     => $numero,
    ];

    $atual    = db_fetch_one("SELECT premio_total, detalhes_json FROM api_cotacoes WHERE seguradora = 'too' AND cotacao_externa_id = ?", ['s', $numero]);
    $detalhes = json_decode((string) ($atual['detalhes_json'] ?? ''), true) ?: [];
    $detalhes['motivo'] = $p['motivo'] ?? null;
    if (!empty($p['biometria']['pretendente']['url'])) {
        $detalhes['biometria_url'] = $p['biometria']['pretendente']['url'];
    }
    if (!empty($p['numeroApolice']) && trim((string) $p['numeroApolice']) !== 'null') {
        $detalhes['numero_apolice'] = trim((string) $p['numeroApolice']);
    }

    // Aprovada e ainda sem prêmio: calcula a cotação.
    if (in_array($resultado['status'], ['aprovada', 'biometria_concluida'], true) && empty($atual['premio_total'])) {
        $calculo = too_calcular($ficha, $numero);
        if ($calculo['ok']) {
            $resultado += $calculo['valores'];
            $detalhes['numero_cotacao_too'] = $calculo['numero_cotacao'];
        } else {
            error_log('[too] Falha ao calcular a cotação da proposta ' . $numero . ': ' . $calculo['erro']);
        }
    }

    $resultado['detalhes_json'] = json_encode($detalhes, JSON_UNESCAPED_UNICODE);
    return $resultado;
}

/** Calcula o prêmio (POST /fianca/proposta/cotacao) com a vigência e o índice da ficha. */
function too_calcular(array $ficha, string $numero): array
{
    $meses  = max(4, min(60, (int) $ficha['vigencia_contrato']));
    $inicio = new DateTimeImmutable('today');

    $r = too_request('POST', '/fianca/proposta/cotacao', [
        'cnpjCorretor'                  => preg_replace('/\D/', '', (string) env('TOO_CNPJ_CORRETOR', '')),
        'razaoSocialCorretor'           => (string) env('TOO_RAZAO_SOCIAL_CORRETOR', ''),
        'numeroFicha'                   => (int) $numero,
        'inicioVigenciaContratoLocacao' => $inicio->format('Y-m-d'),
        'finalVigenciaContratoLocacao'  => $inicio->modify("+{$meses} months")->format('Y-m-d'),
        'indiceDeReajusteAluguel'       => too_indice($ficha['indice_reajuste'] ?? null),
        'periodoIndenitario'            => (int) env('TOO_PERIODO_INDENITARIO', 30),
        'percentualComissao'            => (float) env('TOO_COMISSAO', 0.20),
        'coberturas'                    => too_coberturas($ficha),
    ]);
    if (!$r['ok']) {
        return $r;
    }

    // A doc mostra uma lista de cotações; pega a primeira.
    $item = $r['data'];
    if (is_array($item) && array_is_list($item)) {
        $item = $item[0] ?? [];
    }
    $resp = $item['response'] ?? $item;
    $cp   = $resp['condicoesPagamento'] ?? [];

    $cobertura = 0.0;
    foreach ($resp['coberturas'] ?? [] as $c) {
        if (!empty($c['contratada'])) {
            $cobertura += (float) ($c['verba'] ?? 0) * (int) ($c['periodoIndenitario'] ?? 0);
        }
    }

    return [
        'ok'             => true,
        'numero_cotacao' => (string) ($item['numeroCotacao'] ?? ''),
        'valores'        => [
            'premio_total'    => (float) ($cp['valorVistaBruto'] ?? $cp['premioBrutoTotal'] ?? 0) ?: null,
            'premio_liquido'  => (float) ($cp['valorVistaLiquido'] ?? $cp['premioLiquido'] ?? 0) ?: null,
            'iof'             => (float) ($cp['iofVista'] ?? 0) ?: null,
            // Comissão (Financeiro): o percentual que mandamos no cálculo, sobre o prêmio líquido.
            'comissao_percentual' => round((float) env('TOO_COMISSAO', 0.20) * 100, 2),
            'comissao_valor'  => round((float) ($cp['valorVistaLiquido'] ?? $cp['premioLiquido'] ?? 0) * (float) env('TOO_COMISSAO', 0.20), 2) ?: null,
            'forma_pagamento' => 'Boleto',
            'parcelas'        => 1,
            'cobertura'       => $cobertura ?: null,
            'vigencia_inicio' => !empty($resp['inicioVigencia']) ? substr((string) $resp['inicioVigencia'], 0, 10) : null,
            'vigencia_fim'    => !empty($resp['fimVigencia']) ? substr((string) $resp['fimVigencia'], 0, 10) : null,
        ],
    ];
}

/** Link da biometria facial (vem na consulta de status). */
function too_biometria(string $numero): array
{
    $ficha = too_ficha($numero);
    if (!$ficha) {
        return ['ok' => false, 'erro' => 'Ficha da proposta Too não encontrada.'];
    }
    $st = too_status_proposta($ficha, $numero);
    if (!$st['ok']) {
        return ['ok' => false, 'erro' => $st['erro']];
    }
    $url = (string) ($st['proposta']['biometria']['pretendente']['url'] ?? '');
    return $url !== '' ? ['ok' => true, 'url' => $url] : ['ok' => false, 'erro' => 'A Too não devolveu o link de biometria.'];
}

// ─── Emissão ──────────────────────────────────────────────────────────────

/** Telefone "(47) 99999-0000" → formato da Too. */
function too_telefone(?string $tel): array
{
    $d = preg_replace('/\D/', '', (string) $tel);
    return [
        'ddd'              => substr($d, 0, 2),
        'numero'           => substr($d, 2),
        'tiposNotificacao' => ['SMS'],
        'tipoTelefone'     => strlen($d) === 11 ? 1 : 2, // 1 celular, 2 fixo
    ];
}

/**
 * Emite: escolhe pagamento à vista no boleto, envia proprietário e dados
 * de contato (PUT ficha) e transmite. A apólice sai assíncrona (status 9 →
 * 10); too_consultar_apolice acompanha.
 *
 * @param array $proprietario Campos do modal (inclui os da Too: data_nascimento,
 *                            estado_civil, nacionalidade, renda, pep, faturamento).
 */
function too_emitir(array $cotacao, array $ficha, array $proprietario, string $inicio, string $fim): array
{
    $numero = (string) $cotacao['cotacao_externa_id'];

    // Retomada: já transmitida numa tentativa anterior.
    if (!empty($cotacao['proposta_id'])) {
        return ['ok' => true, 'proposta_id' => $numero, 'apolice_id' => 'P' . $numero, 'apolice_numero' => null, 'apolice_status' => 'em_emissao'];
    }

    $detalhes = json_decode((string) ($cotacao['detalhes_json'] ?? ''), true) ?: [];
    $numeroCotacao = (string) ($detalhes['numero_cotacao_too'] ?? '');
    if ($numeroCotacao === '') {
        $calculo = too_calcular($ficha, $numero);
        if (!$calculo['ok']) {
            return ['ok' => false, 'erro' => 'Falha ao calcular a cotação na Too: ' . $calculo['erro'], 'erro_codigo' => $calculo['erro_codigo'] ?? null];
        }
        $numeroCotacao = $calculo['numero_cotacao'];
    }

    // Formas de pagamento: confirma que há boleto à vista pra essa cotação.
    $formas = too_request('GET', '/fianca/proposta/formaspagamento/' . rawurlencode($numeroCotacao), null, 30);
    if (!$formas['ok']) {
        return ['ok' => false, 'erro' => 'Falha ao consultar as formas de pagamento: ' . $formas['erro'], 'erro_codigo' => $formas['erro_codigo'] ?? null];
    }

    $pretendentes = array_merge(
        [['cpf' => too_cpf($ficha), 'email' => $ficha['email'], 'tel_celular' => $ficha['tel_celular']]],
        db_fetch_all('SELECT cpf_cnpj AS cpf, email, tel_celular FROM pretendentes WHERE seguro_fianca_id = ? ORDER BY numero_pretendente', ['i', (int) $ficha['id']])
    );
    $contatoTitular = ['email' => (string) $ficha['email'], 'tel' => (string) $ficha['tel_celular']];

    $doc   = preg_replace('/\D/', '', (string) $proprietario['cpf_cnpj']);
    $pj    = strlen($doc) === 14;
    $dono  = [
        'tipoPessoa'                 => $pj ? 'Juridica' : 'Fisica',
        'email'                      => (string) $proprietario['email'],
        'telefone'                   => too_telefone($proprietario['tel_celular']),
        'cep'                        => preg_replace('/\D/', '', (string) $proprietario['cep']),
        'logradouro'                 => (string) $proprietario['endereco'],
        'numero'                     => (string) $proprietario['numero'],
        'complemento'                => ($proprietario['complemento'] ?? '') ?: null,
        'bairro'                     => (string) $proprietario['bairro'],
        'cidade'                     => (string) $proprietario['cidade'],
        'uf'                         => strtoupper((string) $proprietario['uf']),
    ];
    $dono += $pj ? [
        'cnpj'        => $doc,
        'razaoSocial' => (string) $proprietario['nome_completo'],
        'faturamento' => round((float) ($proprietario['faturamento'] ?? 0), 2),
    ] : [
        'nome'                       => (string) $proprietario['nome_completo'],
        'cpf'                        => $doc,
        'dataNascimento'             => (string) $proprietario['data_nascimento'],
        'estadoCivil'                => (string) ($proprietario['estado_civil'] ?: 'NaoInformado'),
        'nacionalidade'              => (string) ($proprietario['nacionalidade'] ?: 'Brasil'),
        'informarRendaFixa'          => true,
        'rendaFixaMensal'            => round((float) ($proprietario['renda'] ?? 0), 2),
        'pessoaPoliticamenteExposta' => (string) ($proprietario['pep'] ?: 'Nao'),
    ];

    $imob = db_fetch_one('SELECT nome, cnpj FROM imobiliarias WHERE id = ?', ['i', (int) $ficha['imobiliaria_id']]);
    $cnpjImob = preg_replace('/\D/', '', (string) ($imob['cnpj'] ?? ''));

    $put = too_request('PUT', '/fianca/proposta/ficha/' . $numero, [
        'numeroCotacao' => $numeroCotacao,
        'formaPagamento' => ['tipoPagamento' => 1, 'tipoEntrada' => 2, 'tipoOpcaoParcelamento' => 1, 'quantidadeParcelas' => 1],
        'pretendentes' => array_map(static fn(array $p) => [
            'cpf'                        => preg_replace('/\D/', '', (string) $p['cpf']),
            'cep'                        => preg_replace('/\D/', '', (string) ($ficha['cep_atual'] ?: $ficha['cep'])),
            'telefone'                   => too_telefone($p['tel_celular'] ?: $contatoTitular['tel']),
            'email'                      => (string) ($p['email'] ?: $contatoTitular['email']),
            'pessoaPoliticamenteExposta' => 'NaoInformado',
        ], $pretendentes),
        'locacao' => strlen($cnpjImob) === 14 ? ['imobiliaria' => ['razaoSocial' => (string) $imob['nome'], 'cnpj' => $cnpjImob]] : new stdClass(),
        'proprietario' => $dono,
    ]);
    if (!$put['ok']) {
        return ['ok' => false, 'erro' => 'Falha ao enviar o proprietário e o pagamento: ' . $put['erro'], 'erro_codigo' => $put['erro_codigo'] ?? null];
    }

    $tx = too_request('POST', "/fianca/proposta/{$numero}/transmitir");
    if (!$tx['ok']) {
        return ['ok' => false, 'erro' => 'Falha ao transmitir a proposta: ' . $tx['erro'], 'erro_codigo' => $tx['erro_codigo'] ?? null];
    }

    // A apólice sai depois (status 9 → 10). Até lá o id é a referência da proposta.
    return [
        'ok'              => true,
        'proposta_id'     => $numero,
        'proposta_status' => 'Transmitida',
        'apolice_id'      => 'P' . $numero,
        'apolice_numero'  => null,
        'apolice_status'  => 'em_emissao',
    ];
}

/**
 * Situação da apólice. $id = "P{proposta}" (antes do número sair) ou o
 * número da apólice.
 */
function too_consultar_apolice(string $id): array
{
    $numeroProposta = str_starts_with($id, 'P') ? substr($id, 1) : null;
    if ($numeroProposta === null) {
        $r = too_request('GET', '/fianca/apolice/' . rawurlencode($id), null, 30);
        return $r['ok'] ? ['ok' => true, 'apolice_numero' => $id, 'apolice_status' => 'emitida'] : ['ok' => false, 'erro' => $r['erro']];
    }

    $ficha = too_ficha($numeroProposta);
    if (!$ficha) {
        return ['ok' => false, 'erro' => 'Ficha da proposta Too não encontrada.'];
    }
    $st = too_status_proposta($ficha, $numeroProposta);
    if (!$st['ok']) {
        return ['ok' => false, 'erro' => $st['erro']];
    }
    $p       = $st['proposta'];
    $apolice = trim((string) ($p['numeroApolice'] ?? ''));
    $emitida = (int) ($p['status'] ?? 0) === 10 && $apolice !== '' && $apolice !== 'null';

    return [
        'ok'             => true,
        'apolice_numero' => $emitida ? $apolice : '',
        'apolice_status' => $emitida ? 'emitida' : ((int) ($p['status'] ?? 0) === 12 ? 'cancelada' : 'em_emissao'),
    ];
}

/**
 * PDF (a Too devolve em base64 dentro do JSON).
 *
 * @param string $tipo 'proposta' (id = número da proposta) | 'apolice' (id = "P{proposta}" ou número da apólice)
 */
function too_documento(string $tipo, string $id): array
{
    if ($tipo === 'proposta') {
        $ficha = too_ficha($id);
        if (!$ficha) {
            return ['ok' => false, 'erro' => 'Ficha da proposta Too não encontrada.'];
        }
        $r = too_request('GET', '/fianca/proposta/' . too_cpf($ficha) . "/{$id}/pdf", null, 90);
        $base64 = $r['ok'] ? (string) ($r['data']['proposta'] ?? '') : '';
    } else {
        $numero = $id;
        if (str_starts_with($id, 'P')) {
            $numero = (string) (db_fetch_one("SELECT apolice_numero FROM api_cotacoes WHERE seguradora = 'too' AND apolice_id = ?", ['s', $id])['apolice_numero'] ?? '');
            if ($numero === '') {
                return ['ok' => false, 'pendente' => true, 'erro' => 'A apólice ainda não foi emitida pela Too.'];
            }
        }
        $r = too_request('GET', '/fianca/apolice/' . rawurlencode($numero) . '/pdf', null, 90);
        $base64 = $r['ok'] ? (string) ($r['data']['apolice'] ?? '') : '';
    }

    if (!$r['ok']) {
        return ['ok' => false, 'erro' => $r['erro']];
    }
    $pdf = base64_decode($base64, true);
    if ($pdf === false || $pdf === '') {
        return ['ok' => false, 'pendente' => true, 'erro' => 'A Too ainda não gerou o PDF.'];
    }
    return ['ok' => true, 'conteudo' => $pdf];
}
