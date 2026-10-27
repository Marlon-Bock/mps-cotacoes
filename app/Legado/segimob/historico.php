<?php
/**
 * Linha do tempo da cotação (tabela historico — a mesma que o chat da
 * cotação mostra) pra análise nas seguradoras: envio, resposta de cada
 * seguradora e o resultado (pré-aprovada ou recusada). Mesmo formato do
 * dev-cotacoes (src/helpers/api/historico.php).
 *
 * O chat mostra "<quem>: <status> — <detalhe>" e a linha do tempo do
 * detalhe mostra só o <status>. "Quem" é o usuário que agiu ou, nos eventos
 * automáticos, SEGIMOB_HISTORICO_AUTOR. O nome "Segimob" nunca aparece pro
 * usuário (é só recurso interno), e o resultado é só "Pré-aprovado" ou
 * "Recusado" — sem dizer qual seguradora (igual pra MPS e imobiliária).
 */

const SEGIMOB_HISTORICO_AUTOR = 'Seguradoras';

/**
 * @param string     $tipoEvento ENVIO_COTACAO | APROVACAO_COTACAO | RECUSA_COTACAO | ALTERACAO_STATUS
 * @param array|null $ator       ['id' => int, 'tipo' => 'admin'|'imobiliaria', 'nome' => string]; null = sistema
 * @param bool       $interno    true = só a MPS vê.
 */
function segimob_historico(int $fiancaId, string $tipoEvento, string $oQue, ?string $detalhe = null, ?array $ator = null, ?string $statusAnterior = null, bool $interno = false): void
{
    $ficha = db_fetch_one('SELECT protocolo FROM seguro_fianca WHERE id = ?', ['i', $fiancaId]);
    if (!$ficha) {
        return;
    }
    [$statusAnterior, $oQue] = historico_status_visiveis($statusAnterior, $oQue);

    try {
        db_execute(
            'INSERT INTO historico
                (seguro_fianca_id, protocolo, tipo_evento, status_anterior, status_novo,
                 observacao, usuario_id, usuario_tipo, usuario_nome, api_evento, interno, data_evento)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, NOW())',
            ['isssssissi',
             $fiancaId, (string) $ficha['protocolo'], $tipoEvento, $statusAnterior, $oQue, $detalhe,
             (int) ($ator['id'] ?? 0), $ator['tipo'] ?? 'admin', $ator['nome'] ?? SEGIMOB_HISTORICO_AUTOR, $interno ? 1 : 0]
        );
        // Toda etapa nas seguradoras conta como atualização da ficha — é a
        // data que as listagens mostram ("Atual.") e usam pra ordenar.
        db_execute('UPDATE seguro_fianca SET data_atualizacao = NOW() WHERE id = ?', ['i', $fiancaId]);
    } catch (Throwable $e) {
        error_log('[segimob] Histórico: ' . $e->getMessage());
    }
}

/** Quem fez a ação, no formato de $ator de segimob_historico(). */
function segimob_historico_ator(object $user): array
{
    $admin = ($user->role ?? '') === 'admin';
    return [
        'id'   => (int) ($user->uid ?? 0),
        'tipo' => $admin ? 'admin' : 'imobiliaria',
        'nome' => (string) ($user->nome ?? ($admin ? 'MPS' : 'Imobiliária')),
    ];
}

/**
 * Sininho da imobiliária dona da ficha (e da MPS, se $paraMps), com link
 * pro detalhe da cotação. $mensagem recebe o protocolo no lugar de %s.
 */
function segimob_notificar(int $fiancaId, string $tipo, string $titulo, string $mensagem, bool $paraMps = false): void
{
    $ficha = db_fetch_one('SELECT protocolo, imobiliaria_id, usuario_id FROM seguro_fianca WHERE id = ?', ['i', $fiancaId]);
    if (!$ficha) {
        return;
    }
    $protocolo = (string) $ficha['protocolo'];
    $texto     = sprintf($mensagem, $protocolo);

    try {
        notificar_imobiliaria((int) $ficha['imobiliaria_id'], $tipo, $titulo, $texto, $fiancaId,
            cotacao_notif_url('fianca', $protocolo, 'imobiliaria'), (int) ($ficha['usuario_id'] ?? 0) ?: null);
        if ($paraMps) {
            notificar_todos_admins($tipo, $titulo, $texto, $fiancaId, cotacao_notif_url('fianca', $protocolo, 'admin'));
        }
    } catch (Throwable $e) {
        error_log('[segimob] Notificação: ' . $e->getMessage());
    }
}

/** Sininho só da MPS (ex: "Fazer cotação"), com link pro detalhe da cotação. */
function segimob_notificar_mps(int $fiancaId, string $tipo, string $titulo, string $mensagem): void
{
    $ficha = db_fetch_one('SELECT protocolo FROM seguro_fianca WHERE id = ?', ['i', $fiancaId]);
    if (!$ficha) {
        return;
    }
    try {
        notificar_todos_admins($tipo, $titulo, sprintf($mensagem, (string) $ficha['protocolo']), $fiancaId,
            cotacao_notif_url('fianca', (string) $ficha['protocolo'], 'admin'));
    } catch (Throwable $e) {
        error_log('[segimob] Notificação MPS: ' . $e->getMessage());
    }
}
