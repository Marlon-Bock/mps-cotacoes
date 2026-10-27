<?php
/**
 * POST /api/cotacao/incendio/{protocolo}/imovel/{imovel_id}/proposta-alteracao-solicitar
 * Fluxo antigo do Seguro Incêndio (status "Aprovado" = proposta enviada,
 * aguardando decisão): a imobiliária pede pra MPS ajustar valores da
 * proposta antes de aceitar ou recusar de vez. O imóvel vai pra status
 * próprio "Alteração solicitada" — a MPS vê o pedido no histórico e reenvia
 * (cotacao-incendio-imovel-proposta.php já aceita esse status como origem
 * pra reenvio, igual já fazia com o anexo "Recusada").
 */

$user = require_auth();
csrf_verify();

$protocolo = $params['protocolo'] ?? '';
$imovel_id = (int) ($params['imovel_id'] ?? 0);
if (!$protocolo || !$imovel_id) {
    json_response(['success' => false, 'message' => 'Parâmetros inválidos.'], 400);
}

$mensagem = trim(sanitize((string) ($_POST['mensagem'] ?? '')));
if ($mensagem === '') {
    json_response(['success' => false, 'message' => 'Descreva o que precisa ser alterado na proposta.'], 422);
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
    json_response(['success' => false, 'message' => 'Este imóvel não está aguardando decisão sobre a proposta.'], 422);
}

$anexo = db_fetch_one(
    "SELECT id FROM cotacoes_anexos_incendio WHERE seguro_incendio_imovel_id = ? AND status_aprovacao = 'Aguardando' ORDER BY data_envio DESC LIMIT 1",
    ['i', $imovel_id]
);
if (!$anexo) {
    json_response(['success' => false, 'message' => 'Nenhuma proposta aguardando decisão para este imóvel.'], 422);
}

if (!db_execute("UPDATE cotacoes_anexos_incendio SET status_aprovacao = 'Alteração solicitada' WHERE id = ?", ['i', (int) $anexo['id']])) {
    json_response(['success' => false, 'message' => 'Erro ao registrar o pedido de alteração.'], 500);
}

$status_anterior = $imovel['status'];
$status_novo     = 'Alteração solicitada';

db_execute('UPDATE seguro_incendio_imoveis SET status = ?, data_atualizacao = NOW() WHERE id = ?', ['si', $status_novo, $imovel_id]);

seguro_incendio_recalcular_status((int) $cotacao['id']);

historico_registrar(
    (int) $cotacao['id'], $protocolo, 'ALTERACAO_SOLICITADA',
    $status_anterior, $status_novo, (int) $user->uid, 'imobiliaria', $user->nome ?? 'Imobiliária',
    "Imóvel {$imovel['numero_imovel']}: solicitou alteração na proposta — \"{$mensagem}\""
);

// Também entra no chat da cotação — é a MPS quem acompanha o chat no dia a
// dia, não só o histórico de status.
try {
    $chat_msg_id = db_execute(
        "INSERT INTO cotacao_mensagens (protocolo, autor_tipo, autor_id, autor_nome, mensagem, criado_em) VALUES (?, ?, ?, ?, ?, NOW())",
        ['ssiss', $protocolo, 'imobiliaria', (int) $user->uid, $user->nome ?? 'Imobiliária',
         "Pedido de alteração na proposta do imóvel {$imovel['numero_imovel']}: {$mensagem}"]
    );
    db_execute(
        "INSERT INTO cotacao_mensagens_lidas (protocolo, usuario_id, papel, ultima_id_lida)
         VALUES (?, ?, 'imobiliaria', ?)
         ON DUPLICATE KEY UPDATE ultima_id_lida = GREATEST(ultima_id_lida, VALUES(ultima_id_lida)), atualizado_em = NOW()",
        ['sii', $protocolo, (int) $user->uid, $chat_msg_id]
    );
} catch (Throwable $e) {
    error_log('[incendio-imovel-proposta-alteracao-solicitar] Erro ao postar no chat: ' . $e->getMessage());
}

try {
    notificar_todos_admins(
        'acao_imob',
        'Alteração solicitada na proposta',
        "O imóvel {$imovel['numero_imovel']} da cotação {$protocolo} recebeu um pedido de alteração do cliente.",
        (int) $cotacao['id']
    );
} catch (Throwable $e) {
    error_log('[incendio-imovel-proposta-alteracao-solicitar] Erro notificação: ' . $e->getMessage());
}

json_response(['success' => true, 'message' => 'Pedido de alteração enviado. A MPS vai enviar uma nova proposta.', 'status' => $status_novo]);
