<?php
/**
 * POST /api/cotacao/incendio/{protocolo}/imovel/{imovel_id}/recusar
 * Imobiliária recusa, em nome do cliente, a proposta enviada pela MPS pra
 * UM imóvel específico. O imóvel volta pra "Aprovado" (a MPS pode enviar
 * nova proposta) — espelha includes/acao/cotacao-recusar.php.
 */

$user = require_perm('perm_aceitar');
csrf_verify();

$protocolo = $params['protocolo'] ?? '';
$imovel_id = (int) ($params['imovel_id'] ?? 0);
if (!$protocolo || !$imovel_id) {
    json_response(['success' => false, 'message' => 'Parâmetros inválidos.'], 400);
}

$cotacao = db_fetch_one('SELECT * FROM seguro_incendio WHERE protocolo = ?', ['s', $protocolo]);
if (!$cotacao || (int) $cotacao['imobiliaria_id'] !== (int) ($user->imobiliaria_id ?? 0)) {
    json_response(['success' => false, 'message' => 'Cotação não encontrada.'], 404);
}
if (empty($user->imob_admin) && (int) ($cotacao['usuario_id'] ?? 0) !== (int) $user->uid) {
    json_response(['success' => false, 'message' => 'Cotação não encontrada.'], 404);
}

$imovel = db_fetch_one(
    'SELECT * FROM seguro_incendio_imoveis WHERE id = ? AND seguro_incendio_id = ?',
    ['ii', $imovel_id, $cotacao['id']]
);
if (!$imovel) {
    json_response(['success' => false, 'message' => 'Imóvel não encontrado.'], 404);
}

if ($imovel['status'] !== 'Aprovado') {
    json_response(['success' => false, 'message' => 'Este imóvel não está aguardando decisão do cliente.'], 422);
}

$anexo = db_fetch_one(
    "SELECT id FROM cotacoes_anexos_incendio WHERE seguro_incendio_imovel_id = ? AND status_aprovacao = 'Aguardando' ORDER BY data_envio DESC LIMIT 1",
    ['i', $imovel_id]
);
if (!$anexo) {
    json_response(['success' => false, 'message' => 'Nenhuma proposta aguardando decisão para este imóvel.'], 422);
}

// O status do imóvel permanece "Aprovado" (igual ao fluxo genérico) — só o
// anexo muda pra "Recusada", liberando a MPS pra enviar nova proposta.
$ok1 = db_execute("UPDATE cotacoes_anexos_incendio SET status_aprovacao = 'Recusada' WHERE id = ?", ['i', (int) $anexo['id']]);
if (!$ok1) {
    json_response(['success' => false, 'message' => 'Erro ao recusar proposta.'], 500);
}
// Sem checar o retorno: affected_rows vem 0 quando data_atualizacao já é o
// NOW() deste segundo (outra ação acabou de mexer no imóvel) — não é falha.
db_execute('UPDATE seguro_incendio_imoveis SET data_atualizacao = NOW() WHERE id = ?', ['i', $imovel_id]);

historico_registrar(
    (int) $cotacao['id'], $protocolo, 'RECUSA_COTACAO',
    'Aprovado', 'Aprovado', (int) $user->uid, 'imobiliaria', $user->nome ?? 'Imobiliária',
    "Imóvel {$imovel['numero_imovel']}: cliente recusou a proposta."
);

try {
    notificar_todos_admins(
        'acao_imob',
        'Proposta recusada pelo cliente',
        "O imóvel {$imovel['numero_imovel']} da cotação {$protocolo} teve a proposta recusada pelo cliente.",
        (int) $cotacao['id']
    );
} catch (Throwable $e) {
    error_log('[incendio-imovel-recusar] Erro notificação: ' . $e->getMessage());
}

require_once BASE_PATH . '/src/helpers/zapi.php';
zapi_notificar_atendente(
    isset($cotacao['atendente_id']) ? (int) $cotacao['atendente_id'] : null,
    "❌ *Proposta recusada pelo cliente — " . zapi_imobiliaria_nome((int) $cotacao['imobiliaria_id']) . "*\n\n"
    . "📄 Protocolo: `{$protocolo}` — Imóvel {$imovel['numero_imovel']}",
    'proposta_recusada',
    $protocolo
);

json_response(['success' => true, 'message' => 'Proposta recusada. A MPS será notificada e poderá enviar uma nova proposta.']);
