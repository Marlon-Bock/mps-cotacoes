<?php
/**
 * GET /api/admin/popup-cotacoes-em-analise
 * Chamado periodicamente (a cada 30min) pelo JS do dashboard admin enquanto
 * a aba fica aberta. Devolve show=true no máximo 1x a cada 30 minutos por
 * usuário (throttle em usuario.popup_cotacoes_visto_em) — evita repetir o
 * popup se o usuário atualizar a página ou tiver várias abas abertas.
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');

const POPUP_EM_ANALISE_INTERVALO_MINUTOS = 30;

$uid = (int) $current_user->uid;

$ultima_vez = db_fetch_one('SELECT popup_cotacoes_visto_em FROM usuario WHERE id = ?', ['i', $uid]);
$visto_em   = $ultima_vez['popup_cotacoes_visto_em'] ?? null;
$pode_mostrar = $visto_em === null
    || strtotime($visto_em) <= strtotime('-' . POPUP_EM_ANALISE_INTERVALO_MINUTOS . ' minutes');

if (!$pode_mostrar) {
    json_response(['show' => false]);
}

db_execute('UPDATE usuario SET popup_cotacoes_visto_em = NOW() WHERE id = ?', ['i', $uid]);

$total = safe_count(
    "SELECT COUNT(*) AS total FROM (
        SELECT id FROM seguro_fianca WHERE status IN ('Em Analise','Em Análise') AND atendente_id = ?
        UNION ALL
        SELECT id FROM seguro_auto   WHERE status IN ('Em Analise','Em Análise') AND atendente_id = ?
        UNION ALL
        SELECT id FROM seguro_residencial WHERE status IN ('Em Analise','Em Análise') AND atendente_id = ?
     ) t",
    ['iii', $uid, $uid, $uid]
) + safe_count(
    "SELECT COUNT(*) AS total FROM (
        SELECT id FROM titulo_capitalizacao WHERE status IN ('Em Analise','Em Análise') AND atendente_id = ?
        UNION ALL
        SELECT id FROM seguro_condominial   WHERE status IN ('Em Analise','Em Análise') AND atendente_id = ?
     ) t",
    ['ii', $uid, $uid]
);

json_response(['show' => true, 'total' => $total]);
