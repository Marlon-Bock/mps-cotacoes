<?php
/**
 * Recusa final da ficha: a Segimob já cota em TODAS as seguradoras
 * parceiras de uma vez — não existe "outra seguradora" pra MPS tentar. Se
 * todas recusaram (ou não aceitaram os dados da ficha), a cotação termina
 * como 'Recusado' e a imobiliária é avisada. Nada vai pra fila da MPS.
 *
 * O histórico diz só "Recusado" (recusada na análise das seguradoras) —
 * igual pra MPS e pra imobiliária, sem dizer quais seguradoras.
 */

/** Motivo da recusa que a imobiliária vê (sem nome de seguradora). */
const SEGIMOB_MOTIVO_RECUSA_IMOBILIARIA = 'Recusada na análise das seguradoras';

/**
 * 'Cotação API' → 'Recusado'. O UPDATE condicional garante que só um
 * processo recusa (cron e "Atualizar" podem disparar juntos).
 *
 * @return bool true se ESTA chamada recusou.
 */
function segimob_recusar_ficha(int $fiancaId): bool
{
    $mudou = db_execute(
        "UPDATE seguro_fianca
         SET status = 'Recusado', recusa_motivo = ?, data_status_recusado = NOW(), data_atualizacao = NOW()
         WHERE id = ? AND status = ?",
        ['sis', SEGIMOB_MOTIVO_RECUSA_IMOBILIARIA, $fiancaId, SEGIMOB_STATUS_FICHA]
    ) > 0;
    if (!$mudou) {
        return false;
    }

    segimob_historico($fiancaId, 'RECUSA_COTACAO', 'Recusado', SEGIMOB_MOTIVO_RECUSA_IMOBILIARIA . '.', null, SEGIMOB_STATUS_FICHA);
    segimob_notificar($fiancaId, 'cotacao_recusada', 'Cotação recusada',
        'A cotação %s não foi aprovada na análise das seguradoras.');

    return true;
}

/**
 * Todas as seguradoras recusaram → a ficha termina como 'Recusado'.
 * Chamado depois de cada consulta (cron, "Atualizar", envio).
 *
 * @return bool true se recusou agora.
 */
function segimob_verificar_recusa_final(int $fiancaId): bool
{
    $cotacoes = segimob_cotacoes_da_ficha($fiancaId);
    if ($cotacoes === []) {
        return false;
    }
    foreach ($cotacoes as $c) {
        if (segimob_situacao($c) !== 'recusada') {
            return false;
        }
    }

    return segimob_recusar_ficha($fiancaId);
}
