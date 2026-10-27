<?php
/**
 * Notificações (sininho) da API das seguradoras.
 */

/**
 * Notifica a imobiliária dona da ficha e (se $paraMps) a equipe MPS, com
 * link pro detalhe da cotação. $mensagem recebe o protocolo no lugar de %s.
 */
function api_notificar(int $fiancaId, string $tipo, string $titulo, string $mensagem, bool $paraMps = true): void
{
    $ficha = db_fetch_one('SELECT protocolo, imobiliaria_id, usuario_id FROM ' . api_ficha_tabela($fiancaId) . ' WHERE id = ?', ['i', $fiancaId]);
    if (!$ficha) {
        return;
    }

    $protocolo = (string) $ficha['protocolo'];
    $texto     = sprintf($mensagem, $protocolo);

    notificar_imobiliaria((int) $ficha['imobiliaria_id'], $tipo, $titulo, $texto, $fiancaId,
        cotacao_notif_url(api_ficha_produto($fiancaId), $protocolo, 'imobiliaria'), (int) ($ficha['usuario_id'] ?? 0) ?: null);
    if ($paraMps) {
        notificar_todos_admins($tipo, $titulo, $texto, $fiancaId, cotacao_notif_url(api_ficha_produto($fiancaId), $protocolo, 'admin'));
    }
}

/** Sininho só da MPS (ex: "Fazer cotação"), com link pro detalhe da cotação. */
function api_notificar_mps(int $fiancaId, string $tipo, string $titulo, string $mensagem): void
{
    $ficha = db_fetch_one('SELECT protocolo FROM ' . api_ficha_tabela($fiancaId) . ' WHERE id = ?', ['i', $fiancaId]);
    if (!$ficha) {
        return;
    }
    notificar_todos_admins($tipo, $titulo, sprintf($mensagem, (string) $ficha['protocolo']), $fiancaId,
        cotacao_notif_url(api_ficha_produto($fiancaId), (string) $ficha['protocolo'], 'admin'));
}

/**
 * Uma seguradora respondeu: a imobiliária não recebe o resultado de cada
 * uma (só a pré-análise) — aqui só roda a pré-análise e o "Fazer cotação"
 * da MPS (api_apos_resultado).
 */
function api_avisar_status_cotacao(int $fiancaId, string $status): void
{
    api_apos_resultado($fiancaId);
}
