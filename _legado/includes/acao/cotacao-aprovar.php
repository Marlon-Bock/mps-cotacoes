<?php
/**
 * POST /api/cotacao/{protocolo}/aprovar
 * Imobiliária aceita, em nome do cliente, a proposta enviada pela MPS.
 */
$user      = require_perm('perm_aceitar');
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

$ok1 = db_execute("UPDATE {$tbl_anexos} SET status_aprovacao = 'Aprovada' WHERE id = ?", ['i', (int) $anexo['id']]);
$ok2 = db_execute(
    "UPDATE {$tbl_cotacao} SET status = 'Concluída', data_status_concluido = NOW(), data_atualizacao = NOW() WHERE protocolo = ? LIMIT 1",
    ['s', $protocolo]
);

if (!$ok1 || !$ok2) json_response(['success' => false, 'message' => 'Erro ao aprovar cotação.'], 500);

historico_registrar(
    (int) $cotacao['id'], $protocolo, 'APROVACAO_COTACAO',
    'Aprovado', 'Concluída', (int) $user->uid, 'imobiliaria', $user->nome ?? 'Imobiliária',
    'Cliente aceitou a proposta.'
);

try {
    notificar_todos_admins(
        'cotacao_finalizada',
        'Cotação concluída',
        "Cotação {$protocolo} aceita pelo cliente. Concluída!",
        (int) $cotacao['id']
    );
} catch (Throwable $e) {
    error_log('[aprovar] Erro notificação: ' . $e->getMessage());
}

require_once BASE_PATH . '/src/helpers/zapi.php';
zapi_notificar_atendente(
    isset($cotacao['atendente_id']) ? (int) $cotacao['atendente_id'] : null,
    "✅ *Proposta aceita pelo cliente — " . zapi_imobiliaria_nome((int) $resolvido['imobiliaria_id']) . "*\n\n"
    . "📄 Protocolo: `{$protocolo}`",
    'proposta_aceita',
    $protocolo
);

json_response(['success' => true, 'message' => 'Cotação aprovada com sucesso!']);
