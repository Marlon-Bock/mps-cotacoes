<?php
/**
 * POST /api/cotacao/{protocolo}/recusar
 * Imobiliária recusa, em nome do cliente, a proposta enviada pela MPS.
 * Cotação volta para "Em análise" para a MPS poder enviar uma nova proposta.
 */
$user      = require_auth();
csrf_verify();
$protocolo = $params['protocolo'] ?? '';
if (!$protocolo) json_response(['success' => false, 'message' => 'Protocolo inválido.'], 400);

$resolvido = cotacao_resolver($protocolo);
if (!$resolvido) json_response(['success' => false, 'message' => 'Cotação não encontrada.'], 404);

$tbl_anexos  = $resolvido['tabela_anexos'];
$tbl_cotacao = $resolvido['tabela'];

if (!cotacao_pode_acessar($resolvido, $user)) {
    json_response(['success' => false, 'message' => 'Cotação não encontrada.'], 404);
}
$cotacao = $resolvido['row'];

if ($cotacao['status'] !== 'Aprovado') {
    json_response(['success' => false, 'message' => 'Esta cotação não está aguardando decisão do cliente.'], 422);
}

$anexo = db_fetch_one(
    "SELECT id FROM {$tbl_anexos} WHERE protocolo = ? AND status_aprovacao = 'Aguardando' ORDER BY data_envio DESC LIMIT 1",
    ['s', $protocolo]
);
if (!$anexo) json_response(['success' => false, 'message' => 'Nenhuma proposta aguardando decisão.'], 422);

// O status da cotação permanece "Aprovado" (igual ao legado) — só o anexo muda para
// "Recusada", o que libera a MPS para enviar uma nova proposta sem perder o histórico.
$ok1 = db_execute("UPDATE {$tbl_anexos} SET status_aprovacao = 'Recusada' WHERE id = ?", ['i', (int) $anexo['id']]);
if (!$ok1) json_response(['success' => false, 'message' => 'Erro ao recusar cotação.'], 500);

// Sem checar o retorno: affected_rows vem 0 quando data_atualizacao já é o
// NOW() deste segundo (outra ação acabou de mexer na cotação) — não é falha.
db_execute(
    "UPDATE {$tbl_cotacao} SET data_atualizacao = NOW() WHERE protocolo = ? LIMIT 1",
    ['s', $protocolo]
);

historico_registrar(
    (int) $cotacao['id'], $protocolo, 'RECUSA_COTACAO',
    'Aprovado', 'Aprovado', (int) $user->uid, 'imobiliaria', $user->nome ?? 'Imobiliária',
    'Cliente recusou a proposta.'
);

try {
    notificar_todos_admins(
        'acao_imob',
        'Proposta recusada pelo cliente',
        "Cotação {$protocolo} recusada pelo cliente.",
        (int) $cotacao['id']
    );
} catch (Throwable $e) {
    error_log('[recusar] Erro notificação: ' . $e->getMessage());
}

require_once BASE_PATH . '/src/helpers/zapi.php';
zapi_notificar_atendente(
    isset($cotacao['atendente_id']) ? (int) $cotacao['atendente_id'] : null,
    "❌ *Proposta recusada pelo cliente — " . zapi_imobiliaria_nome((int) $resolvido['imobiliaria_id']) . "*\n\n"
    . "📄 Protocolo: `{$protocolo}`",
    'proposta_recusada',
    $protocolo
);

json_response(['success' => true, 'message' => 'Cotação recusada. A MPS será notificada e poderá enviar uma nova proposta.']);
