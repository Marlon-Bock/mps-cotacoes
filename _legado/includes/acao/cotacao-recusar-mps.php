<?php
/**
 * POST /api/cotacao/{protocolo}/recusar-mps
 * MPS recusa uma cotação em análise (após avaliação completa).
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
$cotacao     = $resolvido['row'];

$prontoParaRecusar = $tbl_cotacao === 'seguro_fianca'
    ? $cotacao['status'] === 'Pré-aprovado'
    : $cotacao['status'] === 'Em Analise';

if (!$prontoParaRecusar) {
    json_response(['success' => false, 'message' => 'Esta cotação ainda não está pronta para ser recusada.'], 422);
}

$status_anterior = $cotacao['status'];
$status_novo     = 'Recusado';

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
    $status_anterior, $status_novo, (int) $admin->uid, 'admin', $admin->nome ?? 'MPS', null
);

try {
    notificar_imobiliaria(
        (int) $cotacao['imobiliaria_id'],
        'status_alterado',
        'Cotação recusada',
        "Sua cotação {$protocolo} foi recusada pela MPS.",
        (int) $cotacao['id'],
        null,
        (int) ($cotacao['usuario_id'] ?? 0)
    );
} catch (Throwable $e) {
    error_log('[recusar-mps] Erro notificação: ' . $e->getMessage());
}

json_response(['success' => true, 'message' => 'Cotação recusada.', 'status' => $status_novo]);
