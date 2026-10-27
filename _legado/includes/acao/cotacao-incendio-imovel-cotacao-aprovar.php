<?php
/**
 * POST /api/cotacao/incendio/{protocolo}/imovel/{imovel_id}/cotacao-aprovar
 * Imobiliária aceita, em nome do cliente, a cotação do imóvel (fluxo novo).
 * Libera a MPS pra anexar o PDF da proposta.
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

$ok1 = db_execute("UPDATE cotacoes_anexos_incendio SET status_aprovacao = 'Aprovada' WHERE id = ?", ['i', (int) $anexo['id']]);
$ok2 = db_execute(
    "UPDATE seguro_incendio_imoveis SET status = 'Cotação aprovada', data_status_cotacao_aprovada = NOW(), data_atualizacao = NOW() WHERE id = ?",
    ['i', $imovel_id]
);
if (!$ok1 || !$ok2) {
    json_response(['success' => false, 'message' => 'Erro ao aprovar a cotação.'], 500);
}

seguro_incendio_recalcular_status((int) $cotacao['id']);

historico_registrar(
    (int) $cotacao['id'], $protocolo, 'APROVACAO_COTACAO',
    'Aguardando aprovação', 'Cotação aprovada', (int) $user->uid, 'imobiliaria', $user->nome ?? 'Imobiliária',
    "Imóvel {$imovel['numero_imovel']}: cliente aprovou a cotação."
);

try {
    notificar_todos_admins(
        'acao_imob',
        'Cotação aprovada',
        "O imóvel {$imovel['numero_imovel']} da cotação {$protocolo} foi aprovado pelo cliente. Já pode enviar a proposta.",
        (int) $cotacao['id']
    );
} catch (Throwable $e) {
    error_log('[incendio-imovel-cotacao-aprovar] Erro notificação: ' . $e->getMessage());
}

json_response(['success' => true, 'message' => 'Cotação aprovada!', 'status' => 'Cotação aprovada']);
