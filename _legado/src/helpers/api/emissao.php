<?php
/**
 * Emissão de apólice na API das seguradoras: "Escolher proposta" (dados do
 * proprietário → proposta → aceite), acompanhamento da apólice (a emissão é
 * assíncrona na seguradora), PDFs da proposta/apólice e link de biometria.
 */

/**
 * Emite a apólice da cotação de uma seguradora (roda no worker, trabalho
 * 'emissao'). Rechecado aqui além do endpoint: a ficha pode ter sido
 * encerrada ou já emitida entre o clique e o trabalho rodar.
 *
 * @param array $proprietario nome_completo, cpf_cnpj, email, tel_celular, cep,
 *                            endereco, numero, complemento, bairro, cidade, uf.
 */
function api_emitir_apolice(int $fiancaId, string $seguradora, array $proprietario): array
{
    $ficha   = api_ficha($fiancaId);
    $cotacao = api_cotacao($fiancaId, $seguradora);
    if (!$ficha || !$cotacao || empty($cotacao['cotacao_externa_id'])) {
        return ['ok' => false, 'erro' => 'Cotação não encontrada pra essa seguradora.'];
    }
    if (api_ficha_encerrada($ficha)) {
        return ['ok' => false, 'erro' => 'Cotação encerrada (' . $ficha['status'] . ') — emissão cancelada.'];
    }
    if (!empty($cotacao['apolice_id'])) {
        return ['ok' => false, 'erro' => 'A apólice dessa cotação já foi emitida.'];
    }
    // Escolha única: outra seguradora já tem proposta/apólice nesta ficha.
    foreach (api_cotacoes_da_ficha($fiancaId) as $outra) {
        if ($outra['seguradora'] !== $seguradora && (!empty($outra['proposta_id']) || api_tem_apolice($outra))) {
            return ['ok' => false, 'erro' => 'A proposta da ' . api_seguradora_nome((string) $outra['seguradora']) . ' já foi escolhida — emissão cancelada.'];
        }
    }

    $funcao = api_adapter($seguradora, 'emitir');
    if (!$funcao) {
        return ['ok' => false, 'erro' => 'Essa seguradora não emite apólice por aqui ainda.'];
    }

    [$inicio, $fim] = api_vigencia_emissao((string) $cotacao['vigencia_inicio'], (string) $cotacao['vigencia_fim']);

    try {
        $r = $funcao($cotacao, $ficha, $proprietario, $inicio, $fim);
    } catch (Throwable $e) {
        $r = ['ok' => false, 'erro' => $e->getMessage()];
    }

    // Proposta criada (mesmo que o aceite tenha falhado) fica gravada: a
    // próxima tentativa só aceita ("Concluir emissão").
    if (!empty($r['proposta_id'])) {
        db_execute(
            'UPDATE api_cotacoes SET proposta_id = ?, proposta_status = COALESCE(?, proposta_status), atualizado_em = NOW()
             WHERE seguro_fianca_id = ? AND seguradora = ?',
            ['ssis', $r['proposta_id'], ($r['proposta_status'] ?? '') ?: null, $fiancaId, $seguradora]
        );
    }

    if (empty($r['ok'])) {
        if (($r['erro_codigo'] ?? null) === 'imovel_com_apolice') {
            error_log('[api] Emissão recusada (imóvel com apólice ativa): ' . $r['erro']);
            $r['erro'] = api_texto_imovel_com_apolice($seguradora, (string) ($r['erro_apolice'] ?? ''));
            // Recusa definitiva: essa seguradora nunca vai emitir pra esse
            // imóvel — a cotação dela vira "recusada" e não volta (ver
            // api_gravar_resultado / api_aplicar_status_cotacao).
            db_execute(
                "UPDATE api_cotacoes SET status = 'recusada', erro = ?, erro_codigo = 'imovel_com_apolice', atualizado_em = NOW()
                 WHERE seguro_fianca_id = ? AND seguradora = ?",
                ['sis', $r['erro'], $fiancaId, $seguradora]
            );
        }
        if (($r['erro_codigo'] ?? null) === 'proposta_sem_retorno') {
            db_execute(
                "UPDATE api_cotacoes SET status = 'proposta_escolhida', atualizado_em = NOW() WHERE seguro_fianca_id = ? AND seguradora = ?",
                ['is', $fiancaId, $seguradora]
            );
            api_notificar_mps($fiancaId, 'api_emissao_presa', 'Emissão para finalizar',
                'A ' . api_seguradora_nome($seguradora) . ' registrou a proposta da cotação %s mas não devolveu o número dela. Finalize a emissão direto com a seguradora.');
        }
        return $r;
    }

    // "emitida" não é gravado direto: passa por api_gravar_status_apolice,
    // que é quem notifica a emissão (uma vez só) — normalmente nasce
    // "em_emissao".
    db_execute(
        "UPDATE api_cotacoes SET apolice_id = ?, apolice_numero = ?, apolice_status = 'em_emissao', contratado_em = NOW(), atualizado_em = NOW()
         WHERE seguro_fianca_id = ? AND seguradora = ?",
        ['ssis', $r['apolice_id'] ?: null, $r['apolice_numero'] ?: null, $fiancaId, $seguradora]
    );

    // Ficha que já estava com a MPS sai da fila dela: o cliente fechou aqui.
    api_concluir_encaminhada($fiancaId, $seguradora);

    // Proprietário só fica na ficha quando a emissão deu certo (só Fiança —
    // no Residencial o segurado já está na ficha).
    if (api_ficha_produto($fiancaId) === 'fianca') {
        db_execute(
            'UPDATE seguro_fianca SET proprietario_imovel = ?, cpf_cnpj_proprietario = ? WHERE id = ?',
            ['ssi', (string) ($proprietario['nome_completo'] ?? ''), preg_replace('/\D/', '', (string) ($proprietario['cpf_cnpj'] ?? '')), $fiancaId]
        );
    }

    // Incluir o proprietário reprocessa a cotação na seguradora (prêmio,
    // vigência, status "proposta escolhida") — reconsulta pra tela mostrar
    // o que foi de fato contratado.
    $consultar = api_adapter($seguradora, 'consultar');
    if ($consultar) {
        try {
            api_gravar_resultado($fiancaId, $seguradora, $consultar((string) $cotacao['cotacao_externa_id']));
        } catch (Throwable $e) {
            error_log('[api] Reconsulta pós-emissão: ' . $e->getMessage());
        }
    }

    // Interno: a imobiliária só recebe a apólice quando a MPS enviar.
    api_historico(
        $fiancaId, 'ALTERACAO_STATUS', 'Proposta enviada à ' . api_seguradora_nome($seguradora),
        'Apólice em emissão na seguradora' . (!empty($r['apolice_numero']) ? ' (nº ' . $r['apolice_numero'] . ')' : '') . '.',
        null, null, true
    );

    // O aviso "Proposta pronta" sai quando o PDF chegar (api_documento).
    api_fila_enfileirar('pdf_proposta', $fiancaId, $seguradora);

    if (($r['apolice_status'] ?? '') === 'emitida') {
        api_gravar_status_apolice($fiancaId, $seguradora, (string) $r['apolice_numero'], 'emitida');
    }

    return $r;
}

/**
 * Vigência pra emissão: [início, fim] (Y-m-d). A cotação nasce com início =
 * dia da cotação; emitindo dias depois, o início vira hoje (não manda data
 * no passado), mantendo a mesma duração em meses.
 */
function api_vigencia_emissao(string $inicioCotado, string $fimCotado): array
{
    $hoje   = new DateTimeImmutable('today');
    $inicio = $inicioCotado !== '' ? new DateTimeImmutable($inicioCotado) : $hoje;
    $fim    = $fimCotado !== '' ? new DateTimeImmutable($fimCotado) : $inicio->modify('+12 months');

    if ($inicio >= $hoje) {
        return [$inicio->format('Y-m-d'), $fim->format('Y-m-d')];
    }

    $duracao = $inicio->diff($fim);
    $meses   = max(1, $duracao->y * 12 + $duracao->m);

    return [$hoje->format('Y-m-d'), $hoje->modify("+{$meses} months")->format('Y-m-d')];
}

/** Reconsulta a apólice na seguradora e grava o status. */
function api_atualizar_apolice(int $fiancaId, string $seguradora, string $apoliceId): void
{
    $funcao = api_adapter($seguradora, 'consultar_apolice');
    if (!$funcao) {
        return;
    }

    try {
        $r = $funcao($apoliceId);
    } catch (Throwable $e) {
        $r = ['ok' => false, 'erro' => $e->getMessage()];
    }

    if ($r['ok']) {
        api_gravar_status_apolice($fiancaId, $seguradora, (string) $r['apolice_numero'], (string) $r['apolice_status']);
    } else {
        error_log('[api] Falha ao reconsultar apólice: ' . ($r['erro'] ?? ''));
    }
}

/**
 * Grava o status da apólice e, quando ela passa a "emitida" (com PDF
 * disponível), notifica imobiliária e MPS. O UPDATE condicional garante uma
 * notificação só, mesmo com cron e "Atualizar" ao mesmo tempo. Sem mudança
 * de status não mexe em atualizado_em (que a tela mostra).
 *
 * @return bool true se a apólice ACABOU de ficar emitida nesta chamada.
 */
function api_gravar_status_apolice(int $fiancaId, string $seguradora, string $numero, string $status): bool
{
    $mudou = db_execute(
        'UPDATE api_cotacoes SET apolice_numero = COALESCE(?, apolice_numero), apolice_status = ?, atualizado_em = NOW()
         WHERE seguro_fianca_id = ? AND seguradora = ? AND NOT (apolice_status <=> ?)',
        ['ssiss', $numero ?: null, $status, $fiancaId, $seguradora, $status]
    ) > 0;

    if ($mudou && $status === 'emitida') {
        // O aviso "Apólice emitida" sai quando o PDF chegar (api_documento).
        api_fila_enfileirar('pdf_apolice', $fiancaId, $seguradora);
        // Interno: a imobiliária recebe a apólice quando a MPS enviar.
        api_historico(
            $fiancaId, 'APROVACAO_COTACAO', 'Apólice emitida pela ' . api_seguradora_nome($seguradora),
            $numero !== '' ? 'Apólice nº ' . $numero . '.' : null, null, null, true
        );
        return true;
    }

    return false;
}

/**
 * PDF da proposta ou da apólice, com cache local: a seguradora leva ~50s
 * pra gerar e o documento não muda depois de pronto — só a 1ª consulta paga
 * a espera (o cron de acompanhamento já baixa antes).
 *
 * A geração na seguradora pode ficar instável (em homologação a Pottencial
 * chegou a devolver HTTP 500 após ~55s em toda chamada). Falhou há pouco →
 * responde na hora ('instavel') em vez de prender o usuário mais 1 min; o
 * cron segue tentando ($ignorarFalhaRecente).
 *
 * @param string $tipo 'proposta' | 'apolice'
 * @return array{ok:bool, conteudo?:string, pendente?:bool, instavel?:bool, erro?:string}
 */
function api_documento(int $fiancaId, string $seguradora, string $tipo, bool $ignorarFalhaRecente = false): array
{
    $cotacao = api_cotacao($fiancaId, $seguradora);
    $id = (string) ($cotacao[$tipo === 'apolice' ? 'apolice_id' : 'proposta_id'] ?? '');
    if ($id === '') {
        return ['ok' => false, 'erro' => $tipo === 'apolice' ? 'Apólice ainda não emitida.' : 'Proposta ainda não enviada.'];
    }

    $funcao = api_adapter($seguradora, 'documento');
    if (!$funcao) {
        return ['ok' => false, 'erro' => 'Essa seguradora não disponibiliza documentos por aqui ainda.'];
    }

    $arquivo = api_cache_dir('api-documentos') . "/{$seguradora}-{$tipo}-" . preg_replace('/[^A-Za-z0-9-]/', '', $id) . '.pdf';
    if (is_file($arquivo) && filesize($arquivo) > 0) {
        return ['ok' => true, 'conteudo' => (string) file_get_contents($arquivo)];
    }

    $marcaFalha = $arquivo . '.falha';
    if (!$ignorarFalhaRecente && is_file($marcaFalha) && time() - (int) filemtime($marcaFalha) < 180) {
        return ['ok' => false, 'instavel' => true, 'erro' => 'Falha recente ao gerar o documento na seguradora.'];
    }

    try {
        $r = $funcao($tipo, $id);
    } catch (Throwable $e) {
        return ['ok' => false, 'erro' => $e->getMessage()];
    }

    if ($r['ok']) {
        @unlink($marcaFalha);
        // 'x': só quem CRIA o arquivo avisa — cron, fila e site podem baixar
        // ao mesmo tempo, mas o aviso sai uma vez só.
        $novo = @fopen($arquivo . '.tmp-' . getmypid(), 'w');
        if ($novo) {
            fwrite($novo, $r['conteudo']);
            fclose($novo);
            if (@link($arquivo . '.tmp-' . getmypid(), $arquivo)) {
                api_avisar_documento_pronto($fiancaId, $seguradora, $tipo);
            }
            @unlink($arquivo . '.tmp-' . getmypid());
        }
    } elseif (empty($r['pendente'])) {
        // Apaga antes: o arquivo pode ser de outro usuário (cron x site).
        @unlink($marcaFalha);
        @file_put_contents($marcaFalha, (string) time());
        $r['instavel'] = true;
    }

    return $r;
}

/**
 * O PDF da proposta/apólice acabou de chegar: avisa a MPS pra enviar à
 * imobiliária ("Enviar proposta" / "Enviar apólice" — api_enviar_proposta,
 * api_enviar_apolice). A imobiliária só recebe quando a MPS envia.
 */
function api_avisar_documento_pronto(int $fiancaId, string $seguradora, string $tipo): void
{
    if ($tipo === 'apolice') {
        api_notificar_mps($fiancaId, 'apolice_emitida', 'Apólice emitida — enviar',
            'A apólice da cotação %s foi emitida pela ' . api_seguradora_nome($seguradora) . '. Envie para a imobiliária.');
        return;
    }
    api_notificar_mps($fiancaId, 'proposta_pronta', 'Proposta pronta — enviar',
        'A proposta da ' . api_seguradora_nome($seguradora) . ' da cotação %s está pronta. Envie para a imobiliária.');
}

/** Arquivo do PDF já está em cache? (o cron usa pra não baixar de novo) */
function api_documento_em_cache(string $seguradora, string $tipo, string $id): bool
{
    return is_file(api_cache_dir('api-documentos') . "/{$seguradora}-{$tipo}-" . preg_replace('/[^A-Za-z0-9-]/', '', $id) . '.pdf');
}

/** Link de biometria facial da cotação pra enviar ao pretendente. */
function api_biometria(int $fiancaId, string $seguradora): array
{
    $cotacao = api_cotacao($fiancaId, $seguradora);
    if (!$cotacao || empty($cotacao['cotacao_externa_id'])) {
        return ['ok' => false, 'erro' => 'Cotação não encontrada pra essa seguradora.'];
    }

    $funcao = api_adapter($seguradora, 'biometria');
    if (!$funcao) {
        return ['ok' => false, 'erro' => 'Essa seguradora não tem link de biometria.'];
    }

    try {
        return $funcao((string) $cotacao['cotacao_externa_id']);
    } catch (Throwable $e) {
        return ['ok' => false, 'erro' => $e->getMessage()];
    }
}

/**
 * Emissão parada na seguradora: a cotação foi consumida numa proposta
 * ("Performed" na Pottencial) mas o id dela não voltou (502/timeout) e a API
 * não permite buscá-lo — tentar de novo nunca vai dar certo. A imobiliária
 * vê "a MPS está finalizando"; a MPS resolve direto com a seguradora.
 */
function api_emissao_presa(int $fiancaId, ?array $cotacao): bool
{
    return $cotacao !== null
        && ($cotacao['status'] ?? '') === 'proposta_escolhida'
        && empty($cotacao['proposta_id'])
        && !api_tem_apolice($cotacao)
        && (api_fila_ultimo($fiancaId, 'emissao', (string) $cotacao['seguradora'])['status'] ?? '') === 'erro';
}

/**
 * Seguradora cuja proposta o cliente já escolheu nesta ficha (proposta
 * criada, apólice emitida ou emissão na fila), ou null. O cliente fecha com
 * UMA seguradora só: escolhida uma, as outras não podem mais emitir.
 */
function api_seguradora_escolhida(int $fiancaId): ?string
{
    foreach (api_cotacoes_da_ficha($fiancaId) as $c) {
        if (!empty($c['proposta_id']) || api_tem_apolice($c)) {
            return (string) $c['seguradora'];
        }
    }

    $emissao = api_fila_ativa($fiancaId, 'emissao');
    return $emissao ? (string) $emissao['seguradora'] : null;
}

/**
 * O imóvel já tem apólice ativa na seguradora (outra ficha, ou contratada
 * fora do sistema): a seguradora não emite outra pro mesmo imóvel. Mostra o
 * número da apólice e, se ela é nossa, a cotação dela.
 */
function api_texto_imovel_com_apolice(string $seguradora, string $apoliceId): string
{
    $nossa = $apoliceId !== '' ? db_fetch_one(
        'SELECT ac.apolice_numero, COALESCE(sf.protocolo, sr.protocolo) AS protocolo FROM api_cotacoes ac
           LEFT JOIN seguro_fianca sf ON sf.id = ac.seguro_fianca_id
           LEFT JOIN seguro_residencial sr ON sr.id = ac.seguro_fianca_id
          WHERE ac.seguradora = ? AND ac.apolice_id = ?',
        ['ss', $seguradora, $apoliceId]
    ) : null;

    $numero = (string) ($nossa['apolice_numero'] ?? '');
    if ($numero === '' && $apoliceId !== '' && ($consultar = api_adapter($seguradora, 'consultar_apolice'))) {
        try {
            $numero = (string) ($consultar($apoliceId)['apolice_numero'] ?? '');
        } catch (Throwable $e) {
            $numero = '';
        }
    }

    $detalhes = array_filter([$numero !== '' ? "nº {$numero}" : null, !empty($nossa['protocolo']) ? 'cotação ' . $nossa['protocolo'] : null]);

    return 'Esse imóvel já tem apólice ativa na ' . api_seguradora_nome($seguradora)
        . ($detalhes ? ' (' . implode(', ', $detalhes) . ')' : '')
        . '. Não é possível contratar outro seguro fiança pra esse imóvel.';
}
