<?php
/**
 * Linha do tempo da cotação (tabela historico — a mesma que o chat da
 * cotação mostra) pras etapas da API das seguradoras: envio, resultado de
 * cada seguradora, reanálise, proposta escolhida, emissão da apólice.
 *
 * O chat mostra "<quem>: <status> — <detalhe>" e a linha do tempo do
 * detalhe mostra só o <status> — por isso a seguradora vai no próprio
 * status ("Aprovada na Pottencial"). "Quem" é o usuário que agiu ou, nos
 * eventos automáticos, API_HISTORICO_AUTOR.
 *
 * Evento $interno (resultado de cada seguradora, reanálise, análise
 * concluída...) é só da MPS: a imobiliária acompanha só a pré-análise até
 * receber as propostas (historico.interno).
 */

const API_HISTORICO_AUTOR = 'API das seguradoras';

/**
 * @param string     $tipoEvento ENVIO_COTACAO | APROVACAO_COTACAO | RECUSA_COTACAO | ALTERACAO_STATUS
 * @param array|null $ator       ['id' => int, 'tipo' => 'admin'|'imobiliaria', 'nome' => string]; null = sistema
 * @param bool       $interno    true = só a MPS vê.
 */
function api_historico(int $fiancaId, string $tipoEvento, string $oQue, ?string $detalhe = null, ?array $ator = null, ?string $statusAnterior = null, bool $interno = false): void
{
    $ficha = db_fetch_one('SELECT protocolo FROM ' . api_ficha_tabela($fiancaId) . ' WHERE id = ?', ['i', $fiancaId]);
    if (!$ficha) {
        return;
    }
    [$statusAnterior, $oQue] = historico_status_visiveis($statusAnterior, $oQue);

    // Mesmo INSERT do historico_registrar(), com api_evento = 1: é o que o
    // contador do botão "Seguradoras" nas listagens conta.
    try {
        db_execute(
            'INSERT INTO historico
                (seguro_fianca_id, protocolo, tipo_evento, status_anterior, status_novo,
                 observacao, usuario_id, usuario_tipo, usuario_nome, api_evento, interno, data_evento)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, NOW())',
            ['isssssissi',
             $fiancaId, (string) $ficha['protocolo'], $tipoEvento, $statusAnterior, $oQue, $detalhe,
             (int) ($ator['id'] ?? 0), $ator['tipo'] ?? 'admin', $ator['nome'] ?? API_HISTORICO_AUTOR, $interno ? 1 : 0]
        );
        // Toda etapa da API conta como atualização da ficha — é a data que
        // as listagens mostram ("Atual.") e usam pra ordenar.
        db_execute('UPDATE ' . api_ficha_tabela($fiancaId) . ' SET data_atualizacao = NOW() WHERE id = ?', ['i', $fiancaId]);
    } catch (Throwable $e) {
        error_log('[api] Histórico: ' . $e->getMessage());
    }
}

/** Evento (interno — só a MPS) com o resultado de uma cotação/reanálise/reconsulta numa seguradora. */
function api_historico_resultado(int $fiancaId, string $seguradora, array $r): void
{
    $nome = api_seguradora_nome($seguradora);

    if (empty($r['ok'])) {
        $codigo = $r['erro_codigo'] ?? null;
        $texto  = api_erro_amigavel($codigo, api_ficha($fiancaId) ?? []);
        api_erro_de_dados($codigo)
            ? api_historico($fiancaId, 'RECUSA_COTACAO', 'Dados recusados pela ' . $nome, $texto, null, null, true)
            : api_historico($fiancaId, 'ALTERACAO_STATUS', 'Erro na cotação da ' . $nome, $texto, null, null, true);
        return;
    }

    $status  = (string) $r['status'];
    $detalhe = array_filter([
        !empty($r['numero_cotacao']) ? 'Cotação nº ' . $r['numero_cotacao'] : null,
        !empty($r['premio_total']) ? 'Prêmio R$ ' . number_format((float) $r['premio_total'], 2, ',', '.') : null,
        !empty($r['solidarios']) ? 'Solidário: ' . implode(', ', array_filter(array_column($r['solidarios'], 'nome'))) : null,
    ]);
    $tipo = api_status_liberado($status) ? 'APROVACAO_COTACAO' : (api_status_recusado($status) ? 'RECUSA_COTACAO' : 'ALTERACAO_STATUS');

    api_historico($fiancaId, $tipo, api_status_label($status) . ' na ' . $nome, $detalhe ? implode(' · ', $detalhe) : null, null, null, true);
}

/** Quem fez a ação, no formato de $ator de api_historico(). */
function api_historico_ator(object $user): array
{
    $admin = ($user->role ?? '') !== 'imobiliaria';
    return [
        'id'   => (int) ($user->uid ?? 0),
        'tipo' => $admin ? 'admin' : 'imobiliaria',
        'nome' => (string) ($user->nome ?? ($admin ? 'MPS' : 'Imobiliária')),
    ];
}

// ─── Novidades na tela das seguradoras (contador no botão da listagem) ───
// Mesma ideia do chat: conta os eventos da API no histórico desde a última
// vez que o usuário abriu a tela (api_vistos). Os próprios eventos do
// usuário não contam.

/** Abriu a tela das seguradoras: zera o contador desse usuário. */
function api_marcar_visto(int $fiancaId, string $papel, int $usuarioId): void
{
    db_execute(
        'INSERT INTO api_vistos (seguro_fianca_id, usuario_id, papel, visto_em) VALUES (?, ?, ?, NOW())
         ON DUPLICATE KEY UPDATE visto_em = NOW()',
        ['iis', $fiancaId, $usuarioId, $papel]
    );
}

/**
 * Novidades nas seguradoras por ficha (listagens — 1 query pra página toda).
 *
 * @param int[]  $fiancaIds
 * @param string $papel 'admin' | 'imobiliaria'
 * @return array<int, int> [seguro_fianca_id => quantidade]
 */
function api_novidades_lote(array $fiancaIds, string $papel, int $usuarioId): array
{
    $fiancaIds = array_values(array_unique(array_filter(array_map('intval', $fiancaIds))));
    if ($fiancaIds === []) {
        return [];
    }

    $ph = implode(',', array_fill(0, count($fiancaIds), '?'));
    try {
        $linhas = db_fetch_all(
            "SELECT h.seguro_fianca_id, COUNT(*) AS total
               FROM historico h
               LEFT JOIN api_vistos v
                 ON v.seguro_fianca_id = h.seguro_fianca_id AND v.usuario_id = ? AND v.papel = ?
              WHERE h.seguro_fianca_id IN ({$ph})
                AND h.data_evento > COALESCE(v.visto_em, '1970-01-01 00:00:00')
                AND h.api_evento = 1
                AND NOT (h.usuario_tipo <=> ? AND h.usuario_id <=> ?)
              GROUP BY h.seguro_fianca_id",
            ['is' . str_repeat('i', count($fiancaIds)) . 'si', $usuarioId, $papel, ...$fiancaIds, $papel, $usuarioId]
        );
    } catch (Throwable $e) {
        error_log('[api] Novidades das seguradoras: ' . $e->getMessage());
        return [];
    }

    return array_column(array_map(fn($l) => ['id' => (int) $l['seguro_fianca_id'], 'n' => (int) $l['total']], $linhas), 'n', 'id');
}
