<?php
/**
 * Cotações na API das seguradoras (tabela api_cotacoes): cotar a ficha em
 * todas as seguradoras, reconsultar, reanalisar e gravar os resultados.
 * Roda no worker (cron/api-fila.php) ou no "Atualizar" da tela.
 */

/**
 * Produtos cotados na API: Fiança (seguro_fianca) e Seguro Residencial
 * (seguro_residencial). As tabelas da API guardam o id da ficha em
 * seguro_fianca_id ("id da ficha", de qualquer produto): o Residencial usa
 * ids a partir de API_RESIDENCIAL_ID_INICIO (migration 094), então o id
 * nunca colide e diz sozinho de qual produto/tabela a ficha é.
 */
const API_RESIDENCIAL_ID_INICIO = 50000001;

/** 'fianca' | 'residencial' — produto da ficha pelo id. */
function api_ficha_produto(int $fichaId): string
{
    return $fichaId >= API_RESIDENCIAL_ID_INICIO ? 'residencial' : 'fianca';
}

/** Tabela da ficha pelo id (seguro_fianca | seguro_residencial). */
function api_ficha_tabela(int $fichaId): string
{
    return api_ficha_produto($fichaId) === 'residencial' ? 'seguro_residencial' : 'seguro_fianca';
}

/** Linha da ficha (seguro_fianca ou seguro_residencial) + 'produto'. */
function api_ficha(int $fiancaId): ?array
{
    $ficha = db_fetch_one('SELECT * FROM ' . api_ficha_tabela($fiancaId) . ' WHERE id = ?', ['i', $fiancaId]);
    return $ficha ? $ficha + ['produto' => api_ficha_produto($fiancaId)] : null;
}

/** Cotação de uma seguradora pra ficha (linha de api_cotacoes), ou null. */
function api_cotacao(int $fiancaId, string $seguradora): ?array
{
    return db_fetch_one(
        'SELECT * FROM api_cotacoes WHERE seguro_fianca_id = ? AND seguradora = ?',
        ['is', $fiancaId, $seguradora]
    ) ?: null;
}

/** Todas as cotações da ficha, mais barata primeiro (sem prêmio por último). */
function api_cotacoes_da_ficha(int $fiancaId): array
{
    return db_fetch_all(
        'SELECT ac.*, COALESCE(sf.cep, sr.cep) AS ficha_cep
           FROM api_cotacoes ac
           LEFT JOIN seguro_fianca sf ON sf.id = ac.seguro_fianca_id
           LEFT JOIN seguro_residencial sr ON sr.id = ac.seguro_fianca_id
          WHERE ac.seguro_fianca_id = ? ORDER BY ac.premio_total IS NULL, ac.premio_total ASC',
        ['i', $fiancaId]
    );
}

/** Pretendentes adicionais da ficha, no formato [['nome'=>..,'cpf'=>..], ...]. */
function api_pretendentes_adicionais(int $fiancaId): array
{
    return array_map(
        fn($p) => ['nome' => (string) $p['nome_completo'], 'cpf' => preg_replace('/\D/', '', (string) $p['cpf_cnpj'])],
        db_fetch_all(
            "SELECT nome_completo, cpf_cnpj FROM pretendentes
             WHERE seguro_fianca_id = ? AND cpf_cnpj IS NOT NULL AND cpf_cnpj <> '' ORDER BY numero_pretendente",
            ['i', $fiancaId]
        )
    );
}

/**
 * Enfileira a cotação da ficha — UM trabalho por seguradora, pra rodarem em
 * paralelo no worker (cada uma leva de 20s a 1 min; em sequência, 4
 * seguradoras seriam ~3 min) e cada card aparecer quando a sua ficar pronta.
 *
 * @param string[]|null $seguradoras Só essas (ex: recotar as que falharam); null = todas.
 */
function api_enfileirar_cotacoes(int $fiancaId, ?array $seguradoras = null): void
{
    foreach ($seguradoras ?? api_seguradoras_da_ficha(api_ficha($fiancaId) ?? []) as $seguradora) {
        api_fila_enfileirar('cotacao', $fiancaId, $seguradora);
    }
}

/**
 * Cota a ficha numa seguradora (ou em todas, se null) e grava o resultado
 * (+ histórico de tentativas). Nunca lança exceção — uma seguradora que
 * falhar fica registrada com o próprio erro, sem travar as outras.
 *
 * @return array<string, array> Resultado por seguradora.
 */
function api_cotar_ficha(int $fiancaId, ?string $somente = null): array
{
    $ficha = api_ficha($fiancaId);
    if (!$ficha) {
        return [];
    }

    $pretendentes = api_pretendentes_adicionais($fiancaId);
    $resultados   = [];

    foreach ($somente !== null ? [$somente] : api_seguradoras_da_ficha($ficha) as $seguradora) {
        if (api_seguradora($seguradora) === null) {
            continue;
        }
        $funcao = api_adapter($seguradora, 'cotar');
        try {
            $r = $funcao($ficha, $pretendentes);
        } catch (Throwable $e) {
            $r = ['ok' => false, 'erro' => $e->getMessage()];
        }

        foreach ($r['tentativas_anteriores'] ?? [] as $anterior) {
            api_registrar_tentativa($fiancaId, $seguradora, $anterior);
            api_historico_resultado($fiancaId, $seguradora, $anterior);
        }
        api_gravar_resultado($fiancaId, $seguradora, $r, true);
        api_historico_resultado($fiancaId, $seguradora, $r);
        $resultados[$seguradora] = $r;
    }

    return $resultados;
}

/**
 * Grava (insere ou atualiza) o resultado de uma seguradora em api_cotacoes.
 * Com $registrarTentativa (cotação nova ou reanálise — não no "Atualizar"),
 * também grava no histórico de tentativas.
 *
 * Falha (timeout na reconsulta, reanálise recusada pela API...) NÃO derruba
 * um status bom já conhecido — só grava o erro. O status só muda num
 * sucesso ou quando a linha ainda nem tem cotação na seguradora.
 */
function api_gravar_resultado(int $fiancaId, string $seguradora, array $r, bool $registrarTentativa = false): void
{
    $ok = !empty($r['ok']);

    db_execute(
        'INSERT INTO api_cotacoes
            (seguro_fianca_id, seguradora, status, status_seguradora, erro, erro_codigo, cotacao_externa_id, numero_cotacao,
             premio_total, premio_liquido, iof, forma_pagamento, parcelas, cobertura, plano,
             vigencia_inicio, vigencia_fim, detalhes_json, comissao_percentual, comissao_valor, respondido_em, criado_em, atualizado_em)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW(), NOW())
         ON DUPLICATE KEY UPDATE
            -- Estas duas vêm antes do cotacao_externa_id = COALESCE(...) de
            -- propósito, pra enxergar o valor ANTIGO.
            -- Recusa definitiva (imóvel já com apólice ativa na seguradora):
            -- a reconsulta continua dizendo "aprovada" lá, mas não volta.
            status = IF(erro_codigo <=> \'imovel_com_apolice\', status,
                        IF(VALUES(erro) IS NULL OR cotacao_externa_id IS NULL, VALUES(status), status)),
            status_seguradora = IF(VALUES(erro) IS NULL OR cotacao_externa_id IS NULL, VALUES(status_seguradora), status_seguradora),
            erro = IF(erro_codigo <=> \'imovel_com_apolice\', erro, VALUES(erro)),
            erro_codigo = IF(erro_codigo <=> \'imovel_com_apolice\', erro_codigo, VALUES(erro_codigo)),
            respondido_em = NOW(),
            atualizado_em = NOW(),
            cotacao_externa_id = COALESCE(VALUES(cotacao_externa_id), cotacao_externa_id),
            numero_cotacao = COALESCE(VALUES(numero_cotacao), numero_cotacao),
            premio_total = COALESCE(VALUES(premio_total), premio_total),
            premio_liquido = COALESCE(VALUES(premio_liquido), premio_liquido),
            iof = COALESCE(VALUES(iof), iof),
            forma_pagamento = COALESCE(VALUES(forma_pagamento), forma_pagamento),
            parcelas = COALESCE(VALUES(parcelas), parcelas),
            cobertura = COALESCE(VALUES(cobertura), cobertura),
            plano = COALESCE(VALUES(plano), plano),
            vigencia_inicio = COALESCE(VALUES(vigencia_inicio), vigencia_inicio),
            vigencia_fim = COALESCE(VALUES(vigencia_fim), vigencia_fim),
            detalhes_json = COALESCE(VALUES(detalhes_json), detalhes_json),
            comissao_percentual = COALESCE(VALUES(comissao_percentual), comissao_percentual),
            comissao_valor = COALESCE(VALUES(comissao_valor), comissao_valor)',
        ['isssssssdddsidssssdd',
         $fiancaId,
         $seguradora,
         $ok ? (string) $r['status'] : 'erro',
         $ok ? ($r['status_seguradora'] ?? null) : null,
         $ok ? null : (string) ($r['erro'] ?? 'Erro desconhecido'),
         $ok ? null : ($r['erro_codigo'] ?? null),
         ($r['cotacao_externa_id'] ?? '') ?: null,
         $r['numero_cotacao'] ?? null,
         $r['premio_total'] ?? null,
         $r['premio_liquido'] ?? null,
         $r['iof'] ?? null,
         $r['forma_pagamento'] ?? null,
         $r['parcelas'] ?? null,
         $r['cobertura'] ?? null,
         $r['plano'] ?? null,
         $r['vigencia_inicio'] ?? null,
         $r['vigencia_fim'] ?? null,
         $r['detalhes_json'] ?? null,
         $r['comissao_percentual'] ?? null,
         $r['comissao_valor'] ?? null]
    );

    // Solidários só mudam quando a seguradora aceitou a reanálise.
    if ($ok && isset($r['solidarios'])) {
        db_execute(
            'UPDATE api_cotacoes SET solidarios_json = ? WHERE seguro_fianca_id = ? AND seguradora = ?',
            ['sis', json_encode($r['solidarios'], JSON_UNESCAPED_UNICODE), $fiancaId, $seguradora]
        );
    }

    if ($registrarTentativa) {
        api_registrar_tentativa($fiancaId, $seguradora, $r);
    }
}

/**
 * Registra uma tentativa no histórico (api_cotacoes_tentativas) — pra tela
 * mostrar "1ª tentativa (somente titular): Recusada → 2ª (titular + Fulano)".
 */
function api_registrar_tentativa(int $fiancaId, string $seguradora, array $r): void
{
    $solidarios = array_filter(array_column($r['solidarios'] ?? [], 'nome'));
    $pretendentes = $solidarios ? 'Titular + ' . implode(', ', $solidarios) : 'Somente titular';

    $ultima = (int) (db_fetch_one(
        'SELECT COALESCE(MAX(tentativa), 0) AS n FROM api_cotacoes_tentativas WHERE seguro_fianca_id = ? AND seguradora = ?',
        ['is', $fiancaId, $seguradora]
    )['n'] ?? 0);

    db_execute(
        'INSERT INTO api_cotacoes_tentativas (seguro_fianca_id, seguradora, tentativa, pretendentes, status, premio_total, erro, erro_codigo, criado_em)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())',
        ['isissdss',
         $fiancaId,
         $seguradora,
         $ultima + 1,
         $pretendentes,
         !empty($r['ok']) ? (string) $r['status'] : 'erro',
         $r['premio_total'] ?? null,
         !empty($r['ok']) ? null : (string) ($r['erro'] ?? 'Erro desconhecido'),
         !empty($r['ok']) ? null : ($r['erro_codigo'] ?? null)]
    );
}

/** Histórico de tentativas da ficha, agrupado por seguradora. */
function api_tentativas_da_ficha(int $fiancaId): array
{
    $porSeguradora = [];
    foreach (db_fetch_all(
        'SELECT * FROM api_cotacoes_tentativas WHERE seguro_fianca_id = ? ORDER BY tentativa',
        ['i', $fiancaId]
    ) as $t) {
        $porSeguradora[$t['seguradora']][] = $t;
    }
    return $porSeguradora;
}

/**
 * "Atualizar": reconsulta cada cotação (e a apólice, se já emitida). As
 * seguradoras que nunca chegaram a criar a cotação (timeout/5xx na 1ª
 * tentativa, ou o worker morreu) são cotadas de novo.
 */
function api_atualizar_ficha(int $fiancaId): array
{
    $porSeguradora = [];
    foreach (api_cotacoes_da_ficha($fiancaId) as $c) {
        $porSeguradora[$c['seguradora']] = $c;
    }

    // Recota só as seguradoras pra onde a ficha foi enviada (tem linha ou
    // trabalho de cotação) e que ainda não criaram a cotação — uma
    // seguradora ligada depois não passa a cotar fichas antigas.
    $recotar = array_values(array_filter(
        api_seguradoras_da_ficha(api_ficha($fiancaId) ?? []),
        fn($s) => empty($porSeguradora[$s]['cotacao_externa_id'])
            && (isset($porSeguradora[$s]) || api_fila_ultimo($fiancaId, 'cotacao', $s) !== null)
    ));
    if ($recotar !== []) {
        $r = api_recotar($fiancaId, $recotar);
        if (!$r['ok']) {
            return $r;
        }
    }

    foreach ($porSeguradora as $seguradora => $c) {
        $funcao = api_adapter((string) $seguradora, 'consultar');
        if (!$funcao || empty($c['cotacao_externa_id'])) {
            continue;
        }

        try {
            $r = $funcao((string) $c['cotacao_externa_id']);
        } catch (Throwable $e) {
            $r = ['ok' => false, 'erro' => $e->getMessage()];
        }
        api_aplicar_status_cotacao($fiancaId, (string) $seguradora, $r);

        // A emissão é assíncrona na seguradora — reconsulta a apólice junto.
        if (!empty($c['apolice_id'])) {
            api_atualizar_apolice($fiancaId, (string) $seguradora, (string) $c['apolice_id']);
        }
    }

    return ['ok' => true];
}

/**
 * Cota de novo nas seguradoras indicadas (as que nunca chegaram a ter
 * cotação criada) — se a ficha não estiver encerrada. Seguradora com
 * cotação já na fila não é enfileirada de novo.
 *
 * @param string[]|null $seguradoras null = todas.
 */
function api_recotar(int $fiancaId, ?array $seguradoras = null): array
{
    $ficha = api_ficha($fiancaId);
    if (!$ficha || api_ficha_encerrada($ficha)) {
        return ['ok' => false, 'erro' => 'Cotação encerrada.'];
    }

    api_enfileirar_cotacoes($fiancaId, $seguradoras);
    return ['ok' => true, 'reenfileirado' => true];
}

/**
 * Grava o resultado de uma RECONSULTA (botão "Atualizar" ou cron de
 * acompanhamento) e, quando o status muda, avisa a imobiliária ou
 * encaminha pra MPS (recusa final). O UPDATE condicional só "ganha" num
 * processo, então o aviso sai uma vez mesmo com cron e botão juntos.
 */
function api_aplicar_status_cotacao(int $fiancaId, string $seguradora, array $r): void
{
    if (empty($r['ok'])) {
        api_gravar_resultado($fiancaId, $seguradora, $r);
        return;
    }

    $mudou = db_execute(
        "UPDATE api_cotacoes SET status = ? WHERE seguro_fianca_id = ? AND seguradora = ? AND status <> ?
            AND NOT (erro_codigo <=> 'imovel_com_apolice')",
        ['siss', $r['status'], $fiancaId, $seguradora, $r['status']]
    ) > 0;

    api_gravar_resultado($fiancaId, $seguradora, $r);

    if ($mudou) {
        api_historico_resultado($fiancaId, $seguradora, $r);
    }
    if ($mudou && !api_verificar_recusa_final($fiancaId)) {
        api_apos_resultado($fiancaId);
    }
}

/**
 * Reanálise de uma cotação recusada com um pretendente adicional como
 * solidário (pedida pelo admin — roda no worker, trabalho 'reanalise').
 */
function api_reanalisar(int $fiancaId, string $seguradora, string $cpf, string $nome): array
{
    $cpf = preg_replace('/\D/', '', $cpf);
    if ($cpf === '') {
        return ['ok' => false, 'erro' => 'Informe um CPF/CNPJ válido.'];
    }

    $ficha   = api_ficha($fiancaId);
    $cotacao = api_cotacao($fiancaId, $seguradora);
    if (!$ficha || !$cotacao || empty($cotacao['cotacao_externa_id'])) {
        return ['ok' => false, 'erro' => 'Cotação não encontrada pra essa seguradora.'];
    }

    $funcao = api_adapter($seguradora, 'reanalisar');
    if (!$funcao) {
        return ['ok' => false, 'erro' => 'Essa seguradora não aceita reanálise por aqui.'];
    }

    try {
        $r = $funcao(
            (string) $cotacao['cotacao_externa_id'],
            (string) $cotacao['vigencia_inicio'],
            (string) $cotacao['vigencia_fim'],
            $ficha,
            [['nome' => $nome, 'cpf' => $cpf]]
        );
    } catch (Throwable $e) {
        $r = ['ok' => false, 'erro' => $e->getMessage()];
    }
    // Pro histórico mostrar com quem foi tentado, mesmo quando falhou (o
    // solidarios_json da cotação só muda num sucesso — ver api_gravar_resultado).
    $r['solidarios'] ??= [['nome' => $nome, 'cpf' => $cpf]];

    api_gravar_resultado($fiancaId, $seguradora, $r, true);
    api_historico_resultado($fiancaId, $seguradora, $r);

    if (!api_verificar_recusa_final($fiancaId) && !empty($r['ok'])) {
        api_apos_resultado($fiancaId);
    }

    return $r;
}
