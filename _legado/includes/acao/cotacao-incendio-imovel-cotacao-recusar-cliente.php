<?php
/**
 * POST /api/cotacao/incendio/{protocolo}/imovel/{imovel_id}/cotacao-recusar-cliente
 * MPS encerra UM imóvel (fluxo novo) depois que o cliente recusou a cotação.
 */

$admin = require_admin();
csrf_verify();

$protocolo = $params['protocolo'] ?? '';
$imovel_id = (int) ($params['imovel_id'] ?? 0);
if (!$protocolo || !$imovel_id) {
    json_response(['success' => false, 'message' => 'Parâmetros inválidos.'], 400);
}

$cotacao = db_fetch_one('SELECT * FROM seguro_incendio WHERE protocolo = ?', ['s', $protocolo]);
if (!$cotacao) {
    json_response(['success' => false, 'message' => 'Cotação não encontrada.'], 404);
}

$imovel = db_fetch_one(
    'SELECT * FROM seguro_incendio_imoveis WHERE id = ? AND seguro_incendio_id = ?',
    ['ii', $imovel_id, $cotacao['id']]
);
if (!$imovel) {
    json_response(['success' => false, 'message' => 'Imóvel não encontrado.'], 404);
}

if (($imovel['fluxo'] ?? 'antigo') !== 'novo' || $imovel['status'] !== 'Aguardando aprovação') {
    json_response(['success' => false, 'message' => 'Este imóvel não está aguardando decisão sobre a cotação.'], 422);
}

$ultimo_anexo = db_fetch_one(
    "SELECT status_aprovacao FROM cotacoes_anexos_incendio WHERE seguro_incendio_imovel_id = ? AND tipo_arquivo = 'cotacao' ORDER BY data_envio DESC LIMIT 1",
    ['i', $imovel_id]
);
if (!$ultimo_anexo || ($ultimo_anexo['status_aprovacao'] ?? '') !== 'Recusada') {
    json_response(['success' => false, 'message' => 'Nenhuma cotação recusada pelo cliente para este imóvel.'], 422);
}

$status_anterior = $imovel['status'];
$status_novo     = STATUS_RECUSADO_CLIENTE;

db_execute(
    'UPDATE seguro_incendio_imoveis
     SET status = ?, data_status_recusado = IF(data_status_recusado IS NULL, NOW(), data_status_recusado),
         data_atualizacao = NOW()
     WHERE id = ?',
    ['si', $status_novo, $imovel_id]
);

seguro_incendio_recalcular_status((int) $cotacao['id']);

historico_registrar(
    (int) $cotacao['id'], $protocolo, 'ALTERACAO_STATUS',
    $status_anterior, $status_novo, (int) $admin->uid, 'admin', $admin->nome ?? 'MPS',
    "Imóvel {$imovel['numero_imovel']}: cliente recusou a cotação."
);

try {
    notificar_imobiliaria(
        (int) $cotacao['imobiliaria_id'],
        'status_alterado',
        'Imóvel recusado pelo cliente',
        "O imóvel {$imovel['numero_imovel']} da cotação {$protocolo} foi encerrado como recusado pelo cliente.",
        (int) $cotacao['id'],
        null,
        (int) ($cotacao['usuario_id'] ?? 0)
    );
} catch (Throwable $e) {
    error_log('[incendio-imovel-cotacao-recusar-cliente] Erro notificação: ' . $e->getMessage());
}

json_response(['success' => true, 'message' => 'Imóvel marcado como recusado pelo cliente.', 'status' => $status_novo]);
