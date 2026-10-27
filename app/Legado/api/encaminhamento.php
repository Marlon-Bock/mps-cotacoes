<?php
/**
 * Fim da análise na API das seguradoras: recusa final da ficha (todas
 * recusaram, ou o imóvel já tem apólice ativa) e o selo do motivo. A
 * aprovação (Fiança → 'Pré-aprovado') fica em api_apos_resultado().
 *
 * Fichas antigas podem ter sido encaminhadas pra fila da MPS
 * (encaminhado_mps_em) — o encaminhamento não existe mais, mas
 * api_concluir_encaminhada() ainda trata as que estão nesse estado.
 */

/**
 * Texto do selo mostrado pra MPS/imobiliária — ex: "Recusada na Pottencial"
 * ou "Aprovada na Pottencial · cliente quer ver outras seguradoras".
 */
function api_encaminhamento_detalhe(int $fiancaId, string $motivo): string
{
    $cotacoes = api_cotacoes_da_ficha($fiancaId);
    $nomes = array_map(fn($c) => api_seguradora_nome((string) $c['seguradora']), $cotacoes);

    if ($motivo === 'recusada_api') {
        $partes = [];
        foreach ($cotacoes as $i => $c) {
            $partes[] = api_status_recusado((string) $c['status']) && empty($c['erro_codigo'])
                ? "Recusada na {$nomes[$i]}"
                : "Recusada na {$nomes[$i]}: " . rtrim(api_erro_amigavel($c['erro_codigo'] ?? null, $c), '.');
        }
        return $partes ? implode('; ', $partes) : 'Recusada na seguradora';
    }

    $partes = [];
    foreach ($cotacoes as $i => $c) {
        $partes[] = api_status_liberado((string) $c['status'])
            ? "Aprovada na {$nomes[$i]}"
            : "{$nomes[$i]}: " . api_status_label((string) $c['status']);
    }

    return ($partes ? implode(', ', $partes) . ' · ' : '') . 'cliente quer ver outras seguradoras';
}

/**
 * Recusa FINAL: todas as seguradoras recusaram (a cotação ou os dados da
 * ficha) e não há cotação rodando na fila → a cotação termina como
 * 'Recusado' (mesmo fluxo do dev-cotacoes-3). Chamado depois de cada
 * cotação, reconsulta (cron/"Atualizar") e reanálise. Falha passageira
 * (seguradora fora do ar, timeout) NÃO conta.
 *
 * A imobiliária vê só "recusada na análise das seguradoras"; quais e por
 * quê fica num evento interno, pra MPS.
 *
 * @return bool true se recusou agora.
 */
function api_verificar_recusa_final(int $fiancaId): bool
{
    $ficha = api_ficha($fiancaId);
    if (!$ficha || $ficha['status'] !== API_STATUS_FICHA || api_etapa_da_ficha($fiancaId) !== 'recusada') {
        return false;
    }

    $mudou = db_execute(
        "UPDATE " . api_ficha_tabela($fiancaId) . "
         SET status = 'Recusado', recusa_motivo = ?, data_status_recusado = NOW(), data_atualizacao = NOW()
         WHERE id = ? AND status = ?",
        ['sis', API_MOTIVO_RECUSA_IMOBILIARIA, $fiancaId, API_STATUS_FICHA]
    ) > 0;
    if (!$mudou) {
        return false;
    }

    // Residencial: quais seguradoras recusaram, só pra MPS. Fiança: só "Recusado".
    if (api_ficha_produto($fiancaId) !== 'fianca') {
        api_historico($fiancaId, 'RECUSA_COTACAO', 'Recusada por todas as seguradoras', api_encaminhamento_detalhe($fiancaId, 'recusada_api'), null, null, true);
    }
    api_historico($fiancaId, 'RECUSA_COTACAO', 'Recusado', API_MOTIVO_RECUSA_IMOBILIARIA . '.', null, API_STATUS_FICHA);
    api_notificar($fiancaId, 'cotacao_recusada', 'Cotação recusada', 'A cotação %s não foi aprovada na análise das seguradoras.', false);

    return true;
}

/** Motivo da recusa que a imobiliária vê (sem nome de seguradora). */
const API_MOTIVO_RECUSA_IMOBILIARIA = 'Recusada na análise das seguradoras';

/**
 * O cliente fechou com uma seguradora da API DEPOIS de a ficha ter ido pra
 * MPS ("Cotar em outras seguradoras" e voltou atrás): tira a ficha da fila
 * da MPS (→ 'Concluída') e avisa a equipe, pra ninguém seguir cotando à
 * toa. Ficha que nunca saiu da API ('Cotação API') não muda — o status de
 * tela dela já vem da apólice (api_status_exibicao).
 *
 * @return bool true se ESTA chamada concluiu a ficha.
 */
function api_concluir_encaminhada(int $fiancaId, string $seguradora): bool
{
    $ficha = api_ficha($fiancaId);
    // Só ficha que foi mesmo encaminhada pra MPS (fluxo antigo). No fluxo
    // novo a ficha fica 'Aprovado' até a MPS enviar a apólice.
    if (!$ficha || empty($ficha['encaminhado_mps_em']) || in_array($ficha['status'], [API_STATUS_FICHA, API_STATUS_APROVADO], true)
        || api_ficha_encerrada($ficha)) {
        return false;
    }

    $anterior = (string) $ficha['status'];
    $mudou = db_execute(
        "UPDATE " . api_ficha_tabela($fiancaId) . " SET status = 'Concluída', data_status_concluido = NOW(), data_atualizacao = NOW()
         WHERE id = ? AND status = ?",
        ['is', $fiancaId, $anterior]
    ) > 0;
    if (!$mudou) {
        return false;
    }

    $protocolo = (string) $ficha['protocolo'];
    $nome      = api_seguradora_nome($seguradora);
    api_historico($fiancaId, 'APROVACAO_COTACAO', 'Concluída', "Cliente fechou com a {$nome} pela API — a MPS não precisa mais cotar.", null, $anterior);

    notificar_todos_admins(
        'status_alterado',
        (api_ficha_produto($fiancaId) === 'residencial' ? 'Seguro Residencial' : 'Fiança') . " fechada com a {$nome}",
        "Protocolo {$protocolo}: o cliente escolheu a proposta da {$nome} pela API. A cotação foi concluída e saiu da fila da MPS.",
        $fiancaId,
        cotacao_notif_url(api_ficha_produto($fiancaId), $protocolo, 'admin')
    );

    return true;
}

/**
 * O imóvel já tem apólice de fiança ativa: não dá pra contratar outra em
 * nenhuma seguradora (nem pela MPS). A ficha é recusada e encerrada na hora
 * — sem "Cotar em outras seguradoras" e sem ir pra fila da MPS.
 *
 * @return bool true se ESTA chamada recusou a ficha.
 */
function api_recusar_ficha_imovel_segurado(int $fiancaId, string $motivo): bool
{
    $ficha = api_ficha($fiancaId);
    if (!$ficha || api_ficha_encerrada($ficha)) {
        return false;
    }

    $anterior = (string) $ficha['status'];
    $mudou = db_execute(
        "UPDATE " . api_ficha_tabela($fiancaId) . "
            SET status = 'Recusado', recusa_motivo = ?, data_atualizacao = NOW(),
                data_status_recusado = COALESCE(data_status_recusado, NOW())
          WHERE id = ? AND status = ?",
        ['sis', $motivo, $fiancaId, $anterior]
    ) > 0;
    if (!$mudou) {
        return false;
    }

    api_historico($fiancaId, 'RECUSA_COTACAO', 'Recusado', $motivo, null, $anterior);
    api_notificar($fiancaId, 'cotacao_recusada', 'Cotação recusada', 'A cotação %s foi recusada: o imóvel já tem apólice de seguro fiança ativa.', false);

    return true;
}
