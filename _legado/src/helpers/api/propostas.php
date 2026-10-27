<?php
/**
 * Fluxo da API das seguradoras com a MPS no meio (mesmo fluxo do
 * dev-cotacoes-3 com a Segimob):
 *
 *   imobiliária envia ─► seguradoras analisam (fila) ─► imobiliária só vê a
 *   pré-análise (Em análise / Pré-aprovado / Recusado)
 *        │
 *        ├─ seguradora pede biometria ─► "Biometria pendente": a imobiliária
 *        │  envia o link ao pretendente (antes de qualquer proposta)
 *        └─ análise concluída ─► MPS "Fazer cotação": escolhe as seguradoras
 *           aprovadas e envia as propostas
 *             ─► o cliente (imobiliária) aceita a melhor: dados do proprietário
 *                ─► emissão na seguradora ─► ficha 'Aprovado'
 *             ─► a apólice sai ─► a MPS "Enviar apólice" ─► 'Concluída'
 *
 * api_propostas — 1 linha por seguradora por envio. status:
 *   Aguardando (cliente decide) · Aceita · Não escolhida (aceitou outra) ·
 *   Recusada (cliente recusou todas) · Substituída (a MPS enviou de novo).
 */

/** Etapas em que a MPS faz/refaz a cotação. */
const API_ETAPAS_COTAR = ['cotar', 'propostas_recusadas'];

/** Etapas em que a MPS precisa agir: fazer/refazer a cotação, enviar a apólice emitida. */
const API_ETAPAS_MPS_AGIR = ['cotar', 'propostas_recusadas', 'emitida'];

/** Status da ficha depois que o cliente aceita a proposta (até a MPS enviar a apólice). */
const API_STATUS_APROVADO = 'Aprovado';

/** Propostas da ficha, mais recentes primeiro (filtra pelo status, se vier). */
function api_propostas_da_ficha(int $fiancaId, ?string $status = null): array
{
    $sql  = 'SELECT * FROM api_propostas WHERE seguro_fianca_id = ?';
    $bind = ['i', $fiancaId];
    if ($status !== null) {
        $sql .= ' AND status = ?';
        $bind[0] .= 's';
        $bind[] = $status;
    }
    return db_fetch_all($sql . ' ORDER BY enviado_em DESC, premio_total IS NULL, premio_total ASC', $bind);
}

/** Proposta aguardando o cliente pra essa seguradora, ou null. */
function api_proposta_pendente(int $fiancaId, string $seguradora): ?array
{
    return db_fetch_one(
        "SELECT * FROM api_propostas WHERE seguro_fianca_id = ? AND seguradora = ? AND status = 'Aguardando'",
        ['is', $fiancaId, $seguradora]
    ) ?: null;
}

/** Proposta aceita pelo cliente, ou null. */
function api_proposta_aceita(int $fiancaId): ?array
{
    return db_fetch_one(
        "SELECT * FROM api_propostas WHERE seguro_fianca_id = ? AND status = 'Aceita' ORDER BY decidido_em DESC LIMIT 1",
        ['i', $fiancaId]
    ) ?: null;
}

/**
 * A seguradora pode ir na proposta pro cliente? Só aprovada (ou com a
 * biometria concluída) — biometria pendente tem que ser feita antes.
 */
function api_cotacao_ofertavel(array $cotacao): bool
{
    return api_status_liberado((string) ($cotacao['status'] ?? ''));
}

/** A seguradora aprovou mas espera a biometria facial do pretendente? */
function api_cotacao_biometria_pendente(array $cotacao): bool
{
    return ($cotacao['status'] ?? '') === 'aguardando_biometria';
}

/**
 * Seguradoras que pediram biometria (pendente, concluída ou recusada), em
 * ordem fixa (slug) — é a numeração "Biometria 1, 2..." das telas e o
 * índice do endpoint do link. Pode ter mais de uma (cada seguradora tem o
 * seu link).
 */
function api_biometrias(array $cotacoes): array
{
    $lista = array_values(array_filter($cotacoes, fn($c) => in_array($c['status'] ?? '', ['aguardando_biometria', 'biometria_concluida', 'biometria_recusada'], true)));
    usort($lista, fn($a, $b) => strcmp((string) $a['seguradora'], (string) $b['seguradora']));
    return $lista;
}

/** Alguma seguradora já aprovou (ou só espera a biometria)? — o "Pré-aprovado" da imobiliária. */
function api_tem_aprovada(array $cotacoes): bool
{
    return (bool) array_filter($cotacoes, fn($c) => api_cotacao_ofertavel($c) || api_cotacao_biometria_pendente($c));
}

/** A seguradora já deu a resposta final? Aprovada, recusada (ou expirada/cancelada). */
function api_cotacao_final(array $cotacao): bool
{
    return api_cotacao_ofertavel($cotacao) || api_cotacao_recusada($cotacao)
        || in_array($cotacao['status'] ?? '', ['expirada', 'cancelada'], true);
}

/** A seguradora recusou de vez (a cotação ou os dados, sem dar pra corrigir)? */
function api_cotacao_recusada(array $cotacao): bool
{
    $status = (string) ($cotacao['status'] ?? '');
    return api_status_recusado($status)
        || ($status === 'erro' && empty($cotacao['cotacao_externa_id']) && api_erro_tipo($cotacao['erro_codigo'] ?? null) === 'recusa');
}

/**
 * Em que pé a ficha está — uma etapa só, usada pelas telas, rótulos e
 * contadores dos dois lados:
 *
 *   enviando                  ainda sem resposta de nenhuma seguradora
 *   analise                   alguma seguradora ainda analisando (ou na fila)
 *   biometria                 seguradora espera a biometria do pretendente — antes das propostas
 *   corrigir                  dado recusado que a imobiliária corrige (segura as outras)
 *   erro                      falha temporária numa seguradora ("Atualizar" tenta de novo — segura as outras)
 *   recusada                  todas recusaram
 *   cotar                     TODAS com resposta final (aprovada/recusada) — a MPS faz a cotação
 *   proposta                  propostas com o cliente
 *   propostas_recusadas       o cliente recusou — a MPS reenvia ou encerra
 *   contratada · emitida      o cliente aceitou ('Aprovado'): emissão / apólice emitida
 *
 * @param string[] $fila      Tipos com trabalho ativo na fila.
 * @param array    $propostas Linhas de api_propostas da ficha (qualquer status).
 */
function api_etapa(array $cotacoes, array $fila = [], array $propostas = []): string
{
    $tem    = static fn(callable $f): bool => array_filter($cotacoes, $f) !== [];
    $status = array_column($propostas, 'status');

    if ($tem(fn($c) => ($c['apolice_status'] ?? '') === 'emitida')) {
        return 'emitida';
    }
    if (in_array('Aceita', $status, true) || in_array('emissao', $fila, true)
        || $tem(fn($c) => !empty($c['proposta_id']) || api_tem_apolice($c))) {
        return 'contratada';
    }

    $cotando = in_array('cotacao', $fila, true) || in_array('reanalise', $fila, true);
    if ($cotacoes === []) {
        return 'enviando';
    }
    if (in_array('Aguardando', $status, true)) {
        return 'proposta';
    }
    if ($cotando || $tem(fn($c) => ($c['status'] ?? '') === 'em_analise')) {
        return 'analise';
    }
    // Biometria primeiro: a MPS só faz a cotação depois que o pretendente concluir.
    if ($tem('api_cotacao_biometria_pendente')) {
        return 'biometria';
    }
    // Proposta só com TODAS as seguradoras com resposta final (aprovada ou
    // recusada): dado pra corrigir ou falha temporária numa delas segura as
    // outras — o cliente compara tudo de uma vez.
    if ($tem(fn($c) => !api_cotacao_final($c))) {
        return $tem(fn($c) => ($c['status'] ?? '') === 'erro' && empty($c['cotacao_externa_id']) && api_erro_correcao($c) !== null)
            ? 'corrigir'
            : 'erro';
    }
    if (!$tem('api_cotacao_ofertavel')) {
        return 'recusada';
    }
    // Mais recente primeiro: o último envio foi recusado pelo cliente.
    return ($status[0] ?? null) === 'Recusada' ? 'propostas_recusadas' : 'cotar';
}

/** Etapa de uma ficha direto do banco. */
function api_etapa_da_ficha(int $fiancaId): string
{
    $lote = api_status_lote([$fiancaId])[$fiancaId] ?? ['cotacoes' => [], 'fila' => [], 'propostas' => []];
    return api_etapa($lote['cotacoes'], $lote['fila'], $lote['propostas']);
}

/**
 * MPS envia as propostas ao cliente: uma por seguradora escolhida. Envio
 * anterior que ainda aguardava vira 'Substituída'.
 *
 * @param string[] $seguradoras Slugs (api_cotacoes.seguradora).
 */
function api_enviar_propostas(int $fiancaId, array $seguradoras, object $user, string $observacao = ''): array
{
    $etapa = api_etapa_da_ficha($fiancaId);
    if (!in_array($etapa, [...API_ETAPAS_COTAR, 'proposta'], true)) {
        return ['ok' => false, 'erro' => match ($etapa) {
            'analise'   => 'As seguradoras ainda estão analisando a ficha.',
            'biometria' => 'A seguradora aguarda a biometria facial do pretendente — envie o link a ele antes das propostas.',
            default     => 'Essa cotação não está pronta para enviar propostas.',
        }];
    }

    $seguradoras = array_values(array_unique(array_map('strval', $seguradoras)));
    $escolhidas  = array_values(array_filter(api_cotacoes_da_ficha($fiancaId), fn($c) => in_array((string) $c['seguradora'], $seguradoras, true)));
    if ($escolhidas === [] || count($escolhidas) !== count($seguradoras)) {
        return ['ok' => false, 'erro' => 'Escolha ao menos uma seguradora da cotação.'];
    }
    foreach ($escolhidas as $c) {
        if (!api_cotacao_ofertavel($c)) {
            return ['ok' => false, 'erro' => api_seguradora_nome((string) $c['seguradora']) . ' não aprovou a ficha — não pode ir na proposta.'];
        }
    }

    db_execute(
        "UPDATE api_propostas SET status = 'Substituída', decidido_em = NOW() WHERE seguro_fianca_id = ? AND status = 'Aguardando'",
        ['i', $fiancaId]
    );

    $agora  = date('Y-m-d H:i:s');
    $resumo = [];
    foreach ($escolhidas as $c) {
        $biometria = ($c['status'] ?? '') === 'aguardando_biometria' ? 1 : 0;
        $premio    = $c['premio_total'] !== null ? (float) $c['premio_total'] : null;
        db_execute(
            "INSERT INTO api_propostas (seguro_fianca_id, seguradora, biometria, premio_total, status, enviado_em, enviado_por, enviado_por_nome)
             VALUES (?, ?, ?, ?, 'Aguardando', ?, ?, ?)",
            ['isidsis', $fiancaId, (string) $c['seguradora'], $biometria, $premio, $agora, (int) ($user->uid ?? 0), (string) ($user->nome ?? 'MPS')]
        );
        $resumo[] = api_seguradora_nome((string) $c['seguradora'])
            . ($premio ? ' (R$ ' . number_format($premio, 2, ',', '.') . ')' : '')
            . ($biometria ? ' — requer biometria' : '');
    }

    $detalhe = implode(' · ', $resumo) . (trim($observacao) !== '' ? ' — ' . trim($observacao) : '');
    api_historico($fiancaId, 'ENVIO_COTACAO', 'Proposta enviada', $detalhe, api_historico_ator($user));
    api_notificar($fiancaId, 'proposta_pronta', 'Proposta disponível',
        'A MPS enviou ' . (count($escolhidas) > 1 ? count($escolhidas) . ' propostas' : 'a proposta') . ' da cotação %s. Escolha a melhor para o cliente.', false);

    return ['ok' => true, 'enviadas' => count($escolhidas)];
}

/** Cliente recusou todas as propostas — a ficha volta pra MPS (reenviar ou encerrar). */
function api_recusar_propostas(int $fiancaId, object $user, string $motivo = ''): array
{
    $mudou = db_execute(
        "UPDATE api_propostas SET status = 'Recusada', decidido_em = NOW() WHERE seguro_fianca_id = ? AND status = 'Aguardando'",
        ['i', $fiancaId]
    ) > 0;
    if (!$mudou) {
        return ['ok' => false, 'erro' => 'Não há propostas aguardando decisão.'];
    }

    $motivo = trim(mb_substr($motivo, 0, 500));
    api_historico($fiancaId, 'RECUSA_COTACAO', 'Propostas recusadas pelo cliente', $motivo !== '' ? $motivo : null, api_historico_ator($user));
    api_notificar_mps($fiancaId, 'status_alterado', 'Propostas recusadas',
        'O cliente recusou as propostas da cotação %s.' . ($motivo !== '' ? ' Motivo: ' . $motivo : ''));

    return ['ok' => true];
}

/**
 * O cliente aceitou a proposta (com os dados do proprietário — a emissão já
 * foi pra fila): a proposta vira 'Aceita', as outras 'Não escolhida', e a
 * ficha vai pra 'Aprovado'. A MPS envia a apólice quando sair.
 */
function api_registrar_aceite(int $fiancaId, string $seguradora, object $user, string $proprietario): void
{
    db_execute(
        "UPDATE api_propostas SET status = IF(seguradora = ?, 'Aceita', 'Não escolhida'), decidido_em = NOW()
         WHERE seguro_fianca_id = ? AND status = 'Aguardando'",
        ['si', $seguradora, $fiancaId]
    );
    $mudou = db_execute(
        'UPDATE ' . api_ficha_tabela($fiancaId) . ' SET status = ?, data_status_aprovado = NOW(), data_atualizacao = NOW() WHERE id = ? AND status = ?',
        ['sis', API_STATUS_APROVADO, $fiancaId, API_STATUS_FICHA]
    ) > 0;
    if (!$mudou) {
        return;
    }

    $cotacao = api_cotacao($fiancaId, $seguradora) ?? [];
    $nome    = api_seguradora_nome($seguradora);
    $detalhe = implode(' · ', array_filter([
        "Proposta da {$nome}",
        !empty($cotacao['premio_total']) ? 'R$ ' . number_format((float) $cotacao['premio_total'], 2, ',', '.') : null,
        $proprietario !== '' ? 'Proprietário: ' . $proprietario : null,
    ]));
    api_historico($fiancaId, 'APROVACAO_COTACAO', API_STATUS_APROVADO, $detalhe, api_historico_ator($user), API_STATUS_FICHA);
    api_notificar_mps($fiancaId, 'proposta_aceita', 'Proposta aceita pelo cliente',
        "O cliente aceitou a proposta da {$nome} na cotação %s. Aguarde a apólice e envie para a imobiliária.");
}

/**
 * A apólice já foi enviada pra imobiliária? Também conta como entregue a
 * ficha do fluxo antigo: concluída, ou contratada direto pela imobiliária
 * (sem propostas da MPS) — ela já tinha acesso aos documentos.
 */
function api_apolice_enviada(array $ficha): bool
{
    if (!empty($ficha['api_apolice_enviada_em']) || in_array($ficha['status'] ?? '', ['Concluída', 'Concluida'], true)) {
        return true;
    }
    return !db_fetch_one('SELECT 1 FROM api_propostas WHERE seguro_fianca_id = ? LIMIT 1', ['i', (int) ($ficha['id'] ?? 0)])
        && (bool) db_fetch_one('SELECT 1 FROM api_cotacoes WHERE seguro_fianca_id = ? AND apolice_id IS NOT NULL LIMIT 1', ['i', (int) ($ficha['id'] ?? 0)]);
}

/** A proposta (PDF da seguradora) já foi enviada pra imobiliária? (A apólice enviada leva a proposta junto.) */
function api_proposta_enviada(array $ficha): bool
{
    return !empty($ficha['api_proposta_enviada_em']) || api_apolice_enviada($ficha);
}

/** Cotação da seguradora que o cliente aceitou (com proposta/apólice), ou null. */
function api_cotacao_aceita(int $fiancaId): ?array
{
    $slug = api_proposta_aceita($fiancaId)['seguradora'] ?? api_seguradora_escolhida($fiancaId);
    return $slug ? api_cotacao($fiancaId, (string) $slug) : null;
}

/**
 * A MPS envia a PROPOSTA (PDF) da seguradora pra imobiliária — assim que a
 * seguradora gera, antes da apólice. Só com o PDF já disponível.
 */
function api_enviar_proposta(int $fiancaId, object $user): array
{
    $ficha   = api_ficha($fiancaId);
    $cotacao = api_cotacao_aceita($fiancaId);
    if (!$ficha || !$cotacao || empty($cotacao['proposta_id'])) {
        return ['ok' => false, 'erro' => 'A seguradora ainda não gerou a proposta. Tente em alguns minutos.'];
    }
    if (api_proposta_enviada($ficha)) {
        return ['ok' => false, 'erro' => 'A proposta já foi enviada.'];
    }
    $seguradora = (string) $cotacao['seguradora'];
    if (!api_documento($fiancaId, $seguradora, 'proposta', true)['ok']) {
        return ['ok' => false, 'erro' => 'A seguradora ainda está gerando o PDF da proposta. Tente em alguns minutos.'];
    }

    db_execute('UPDATE ' . api_ficha_tabela($fiancaId) . ' SET api_proposta_enviada_em = NOW(), data_atualizacao = NOW() WHERE id = ? AND api_proposta_enviada_em IS NULL', ['i', $fiancaId]);
    $nome = api_seguradora_nome($seguradora);
    api_historico($fiancaId, 'ENVIO_COTACAO', 'Proposta da seguradora enviada', "Proposta da {$nome} disponível para download.", api_historico_ator($user));
    api_notificar($fiancaId, 'proposta_pronta', 'Proposta disponível',
        "A proposta da {$nome} da cotação %s já está disponível para download. A apólice está em emissão.", false);

    return ['ok' => true];
}

/**
 * A MPS envia a apólice (e a proposta, se ainda não foi) pra imobiliária —
 * a cotação fica 'Concluída'. Só com a apólice emitida e o PDF já disponível.
 */
function api_enviar_apolice(int $fiancaId, object $user): array
{
    $cotacao = null;
    foreach (api_cotacoes_da_ficha($fiancaId) as $c) {
        if (($c['apolice_status'] ?? '') === 'emitida') $cotacao = $c;
    }
    if ($cotacao === null) {
        return ['ok' => false, 'erro' => 'A apólice ainda não foi emitida pela seguradora. Tente em alguns minutos.'];
    }
    $seguradora = (string) $cotacao['seguradora'];
    $pdf = api_documento($fiancaId, $seguradora, 'apolice', true);
    if (!$pdf['ok']) {
        return ['ok' => false, 'erro' => 'A seguradora ainda está gerando o PDF da apólice. Tente em alguns minutos.'];
    }

    // Só uma vez: a apólice enviada conclui a cotação.
    $concluiu = db_execute(
        "UPDATE " . api_ficha_tabela($fiancaId) . " SET status = 'Concluída', api_apolice_enviada_em = NOW(), api_proposta_enviada_em = COALESCE(api_proposta_enviada_em, NOW()),
             data_status_concluido = NOW(), data_atualizacao = NOW()
         WHERE id = ? AND status IN (?, ?)",
        ['iss', $fiancaId, API_STATUS_APROVADO, API_STATUS_FICHA]
    ) > 0;
    if (!$concluiu) {
        return ['ok' => false, 'erro' => 'Essa cotação já foi concluída.'];
    }

    $nome = api_seguradora_nome($seguradora);
    api_historico($fiancaId, 'APROVACAO_COTACAO', 'Concluída',
        'Apólice' . (!empty($cotacao['apolice_numero']) ? ' nº ' . $cotacao['apolice_numero'] : '') . " da {$nome} enviada.",
        api_historico_ator($user), API_STATUS_APROVADO);
    api_notificar($fiancaId, 'apolice_emitida', 'Apólice disponível',
        "A apólice da cotação %s ({$nome}) já está disponível para download. Cotação concluída.", false);

    return ['ok' => true];
}

/**
 * Depois de cada resultado das seguradoras (cotação, reconsulta,
 * reanálise): pré-análise da imobiliária ("Pré-aprovado") e, com a análise
 * concluída, "Fazer cotação" pra MPS. Um processo por vez por ficha (os
 * trabalhos de cada seguradora rodam em paralelo).
 */
function api_apos_resultado(int $fiancaId): void
{
    $lock = 'api_pos_' . $fiancaId;
    db_fetch_one('SELECT GET_LOCK(?, 10) AS ok', ['s', $lock]);
    try {
        $ficha = api_ficha($fiancaId);
        if (!$ficha || $ficha['status'] !== API_STATUS_FICHA) {
            return;
        }
        $lote  = api_status_lote([$fiancaId])[$fiancaId];
        $etapa = api_etapa($lote['cotacoes'], $lote['fila'], $lote['propostas']);

        // Imobiliária: "Pré-aprovado" quando a 1ª seguradora aprova — sem dizer qual.
        $jaPreAprovado = db_fetch_one(
            "SELECT 1 FROM historico WHERE seguro_fianca_id = ? AND status_novo = 'Pré-aprovado' AND interno = 0 LIMIT 1",
            ['i', $fiancaId]
        );
        if (!$jaPreAprovado && api_tem_aprovada($lote['cotacoes'])) {
            api_historico($fiancaId, 'ALTERACAO_STATUS', 'Pré-aprovado', 'Pré-análise aprovada pelas seguradoras. A MPS vai preparar a proposta.');
            api_notificar($fiancaId, 'cotacao_aprovada', 'Cotação pré-aprovada', 'A cotação %s foi pré-aprovada. A MPS vai preparar a proposta.', false);
        }

        // Imobiliária: seguradora pediu biometria → envia o link ao pretendente (uma vez só).
        $jaAvisouBiometria = db_fetch_one(
            "SELECT 1 FROM historico WHERE seguro_fianca_id = ? AND status_novo = 'Biometria pendente' AND interno = 0 LIMIT 1",
            ['i', $fiancaId]
        );
        if ($etapa === 'biometria' && !$jaAvisouBiometria) {
            api_historico($fiancaId, 'ALTERACAO_STATUS', 'Biometria pendente', 'Envie o link da biometria facial ao pretendente — a proposta sai depois que ele concluir.');
            api_notificar($fiancaId, 'cotacao_biometria', 'Biometria pendente',
                'A cotação %s aguarda a biometria facial do pretendente. Abra a cotação e envie o link para ele.', false);
        }

        // MPS: análise concluída → "Fazer cotação" (uma vez só).
        if ($etapa === 'cotar' && empty($ficha['api_analise_concluida_em'])) {
            db_execute('UPDATE ' . api_ficha_tabela($fiancaId) . ' SET api_analise_concluida_em = NOW() WHERE id = ?', ['i', $fiancaId]);
            $linhas = array_map(fn($c) => api_seguradora_nome((string) $c['seguradora']) . ': ' . mb_strtolower(api_status_label((string) $c['status'])), $lote['cotacoes']);
            api_historico($fiancaId, 'ALTERACAO_STATUS', 'Análise das seguradoras concluída', implode(' · ', $linhas), null, null, true);
            api_notificar_mps($fiancaId, 'nova_cotacao', 'Fazer cotação',
                'A análise das seguradoras da cotação %s foi concluída. Faça a cotação e envie as propostas ao cliente.');
        }
    } finally {
        db_fetch_one('SELECT RELEASE_LOCK(?) AS ok', ['s', $lock]);
    }
}

/**
 * A tela da imobiliária ainda espera algo? (polling do detalhe) — pré-análise
 * ou a apólice depois de aceitar. Corrigir dados e proposta na mão dependem dela.
 */
function api_imobiliaria_aguardando(array $ficha, string $etapa): bool
{
    return in_array($ficha['status'] ?? '', [API_STATUS_FICHA, API_STATUS_APROVADO], true)
        && !in_array($etapa, ['corrigir', 'proposta', 'recusada'], true);
}

/** Resumo do que a imobiliária vê — muda só quando a tela dela precisa recarregar. */
function api_assinatura_imobiliaria(array $ficha, array $lote): string
{
    return md5(json_encode([
        (string) ($ficha['status'] ?? ''),
        api_status_resumo_fluxo($lote, false)['label'],
        array_map(fn($p) => [$p['id'], $p['status']], $lote['propostas']),
        in_array('emissao', $lote['fila'], true),
        array_map(fn($c) => [$c['seguradora'], $c['status']], $lote['cotacoes']),
        api_apolice_enviada($ficha),
        api_proposta_enviada($ficha),
    ]));
}
