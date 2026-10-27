<?php
/**
 * POST /api/cotacao/{protocolo}/recusar-cliente
 * MPS encerra a cotação depois que o cliente recusou a(s) proposta(s) enviada(s).
 * A cotação fica com status "Recusado pelo cliente" (diferente de "Recusado",
 * que é a recusa da própria MPS na análise).
 */

$admin = require_admin();
csrf_verify();

$protocolo = $params['protocolo'] ?? '';
if (!$protocolo) {
    json_response(['success' => false, 'message' => 'Protocolo inválido.'], 400);
}

$resolvido = cotacao_resolver($protocolo);
if (!$resolvido) {
    json_response(['success' => false, 'message' => 'Cotação não encontrada.'], 404);
}
$tbl_cotacao = $resolvido['tabela'];
$tbl_anexos  = $resolvido['tabela_anexos'];
$cotacao     = $resolvido['row'];

if ($cotacao['status'] !== 'Aprovado') {
    json_response(['success' => false, 'message' => 'Esta cotação não está aguardando decisão do cliente.'], 422);
}

// Só encerra se a última proposta enviada foi mesmo recusada pelo cliente.
$ultimo_anexo = db_fetch_one(
    "SELECT status_aprovacao FROM {$tbl_anexos} WHERE protocolo = ? ORDER BY data_envio DESC LIMIT 1",
    ['s', $protocolo]
);
if (!$ultimo_anexo || ($ultimo_anexo['status_aprovacao'] ?? '') !== 'Recusada') {
    json_response(['success' => false, 'message' => 'Nenhuma proposta recusada pelo cliente.'], 422);
}

$status_anterior = $cotacao['status'];
$status_novo     = STATUS_RECUSADO_CLIENTE;

db_execute(
    "UPDATE {$tbl_cotacao}
     SET status = ?, atendente_id = IF(atendente_id IS NULL, ?, atendente_id),
         data_status_recusado = IF(data_status_recusado IS NULL, NOW(), data_status_recusado),
         data_atualizacao = NOW()
     WHERE protocolo = ?",
    ['sis', $status_novo, (int) $admin->uid, $protocolo]
);

historico_registrar(
    (int) $cotacao['id'], $protocolo, 'ALTERACAO_STATUS',
    $status_anterior, $status_novo, (int) $admin->uid, 'admin', $admin->nome ?? 'MPS',
    'Cliente recusou as propostas enviadas.'
);

try {
    notificar_imobiliaria(
        (int) $cotacao['imobiliaria_id'],
        'status_alterado',
        'Cotação recusada pelo cliente',
        "A cotação {$protocolo} foi encerrada como recusada pelo cliente.",
        (int) $cotacao['id'],
        null,
        (int) ($cotacao['usuario_id'] ?? 0)
    );
} catch (Throwable $e) {
    error_log('[recusar-cliente] Erro notificação: ' . $e->getMessage());
}

json_response(['success' => true, 'message' => 'Cotação marcada como recusada pelo cliente.', 'status' => $status_novo]);
