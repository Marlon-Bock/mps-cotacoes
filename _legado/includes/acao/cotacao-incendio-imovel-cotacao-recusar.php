<?php
/**
 * POST /api/cotacao/incendio/{protocolo}/imovel/{imovel_id}/cotacao-recusar
 * Imobiliária recusa, em nome do cliente, a cotação do imóvel (fluxo novo).
 * O status do imóvel permanece "Aguardando aprovação" — a MPS pode enviar
 * uma nova cotação ou encerrar o imóvel (cotacao-incendio-imovel-cotacao-recusar-cliente.php).
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

if (($imovel['fluxo'] ?? 'antigo') !== 'novo' || $imovel['status'] !== 'Aguardando aprovação') {
    json_response(['success' => false, 'message' => 'Este imóvel não está aguardando aprovação da cotação.'], 422);
}

$anexo = db_fetch_one(
    "SELECT id FROM cotacoes_anexos_incendio WHERE seguro_incendio_imovel_id = ? AND tipo_arquivo = 'cotacao' AND status_aprovacao = 'Aguardando' ORDER BY data_envio DESC LIMIT 1",
    ['i', $imovel_id]
);
if (!$anexo) {
    json_response(['success' => false, 'message' => 'Nenhuma cotação aguardando aprovação para este imóvel.'], 422);
}

if (!db_execute("UPDATE cotacoes_anexos_incendio SET status_aprovacao = 'Recusada' WHERE id = ?", ['i', (int) $anexo['id']])) {
    json_response(['success' => false, 'message' => 'Erro ao recusar a cotação.'], 500);
}

historico_registrar(
    (int) $cotacao['id'], $protocolo, 'RECUSA_COTACAO',
    'Aguardando aprovação', 'Aguardando aprovação', (int) $user->uid, 'imobiliaria', $user->nome ?? 'Imobiliária',
    "Imóvel {$imovel['numero_imovel']}: cliente recusou a cotação."
);

try {
    notificar_todos_admins(
        'acao_imob',
        'Cotação recusada pelo cliente',
        "O imóvel {$imovel['numero_imovel']} da cotação {$protocolo} teve a cotação recusada pelo cliente.",
        (int) $cotacao['id']
    );
} catch (Throwable $e) {
    error_log('[incendio-imovel-cotacao-recusar] Erro notificação: ' . $e->getMessage());
}

json_response(['success' => true, 'message' => 'Cotação recusada. A MPS será notificada.']);
