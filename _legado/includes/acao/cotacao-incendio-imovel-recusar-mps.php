<?php
/**
 * POST /api/cotacao/incendio/{protocolo}/imovel/{imovel_id}/recusar-mps
 * MPS recusa UM imóvel específico em análise (espelha
 * includes/acao/cotacao-recusar-mps.php, a versão genérica).
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

if ($imovel['status'] !== 'Em Analise') {
    json_response(['success' => false, 'message' => 'Este imóvel ainda não está pronto para ser recusado.'], 422);
}

$status_anterior = $imovel['status'];
$status_novo     = 'Recusado';

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
    "Imóvel {$imovel['numero_imovel']}: recusado pela MPS."
);

try {
    notificar_imobiliaria(
        (int) $cotacao['imobiliaria_id'],
        'status_alterado',
        'Imóvel recusado',
        "O imóvel {$imovel['numero_imovel']} da cotação {$protocolo} foi recusado pela MPS.",
        (int) $cotacao['id'],
        null,
        (int) ($cotacao['usuario_id'] ?? 0)
    );
} catch (Throwable $e) {
    error_log('[incendio-imovel-recusar-mps] Erro notificação: ' . $e->getMessage());
}

json_response(['success' => true, 'message' => 'Imóvel recusado.', 'status' => $status_novo]);
