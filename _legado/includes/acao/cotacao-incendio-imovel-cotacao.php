<?php
/**
 * POST /api/cotacao/incendio/{protocolo}/imovel/{imovel_id}/cotacao
 * Fluxo novo do Seguro Incêndio: MPS anexa o PDF da cotação de UM imóvel e
 * ele passa a aguardar a aprovação da imobiliária/cliente.
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

if (($imovel['fluxo'] ?? 'antigo') !== 'novo') {
    json_response(['success' => false, 'message' => 'Esta cotação está no fluxo anterior. Use o envio de proposta.'], 422);
}
$ultimaCotacao = db_fetch_one(
    "SELECT status_aprovacao FROM cotacoes_anexos_incendio WHERE seguro_incendio_imovel_id = ? AND tipo_arquivo = 'cotacao' ORDER BY data_envio DESC LIMIT 1",
    ['i', $imovel_id]
);
// Reenvio depois de recusa (status continua "Aguardando aprovação", anexo
// "Recusada") ou depois de pedido de alteração (status vira "Aguardando
// nova cotação", ver cotacao-incendio-imovel-alteracao-solicitar.php).
$podeReenviarCotacao = ($imovel['status'] === 'Aguardando aprovação' && ($ultimaCotacao['status_aprovacao'] ?? '') === 'Recusada')
    || $imovel['status'] === 'Aguardando nova cotação';

if ($imovel['status'] !== 'Em Analise' && !$podeReenviarCotacao) {
    json_response(['success' => false, 'message' => 'O imóvel precisa estar em análise para enviar a cotação.'], 422);
}

if ($m = upload_erro_limite()) {
    json_response(['success' => false, 'message' => $m], 413);
}
if (empty($_FILES['arquivo']) || ($_FILES['arquivo']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
    json_response(['success' => false, 'message' => 'Arquivo PDF é obrigatório.'], 422);
}
if ($_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
    $msg = in_array($_FILES['arquivo']['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
        ? 'O arquivo excede o limite do servidor (' . upload_limite_label() . '). Envie um arquivo menor.'
        : 'Falha no upload do arquivo. Tente novamente.';
    json_response(['success' => false, 'message' => $msg], 422);
}

try {
    $arquivo_url = media_upload($_FILES['arquivo'], 'cotacoes/incendio');
} catch (Throwable $e) {
    json_response(['success' => false, 'message' => 'Erro ao enviar arquivo: ' . $e->getMessage()], 500);
}

db_execute(
    "INSERT INTO cotacoes_anexos_incendio (protocolo, seguro_incendio_imovel_id, nome_arquivo, caminho_arquivo, tipo_arquivo, data_envio, usuario_envio, status_aprovacao)
     VALUES (?, ?, ?, ?, 'cotacao', NOW(), ?, 'Aguardando')",
    ['sisss', $protocolo, $imovel_id, $_FILES['arquivo']['name'], $arquivo_url, $admin->nome ?? 'MPS']
);

$status_anterior = $imovel['status'];
$status_novo     = 'Aguardando aprovação';

db_execute(
    'UPDATE seguro_incendio_imoveis SET status = ?, data_status_cotacao_anexada = NOW(), data_atualizacao = NOW() WHERE id = ?',
    ['si', $status_novo, $imovel_id]
);

seguro_incendio_recalcular_status((int) $cotacao['id']);

historico_registrar(
    (int) $cotacao['id'], $protocolo, 'ENVIO_COTACAO',
    $status_anterior, $status_novo, (int) $admin->uid, 'admin', $admin->nome ?? 'MPS',
    "Imóvel {$imovel['numero_imovel']}: PDF da cotação anexado."
);

try {
    notificar_imobiliaria(
        (int) $cotacao['imobiliaria_id'],
        'status_alterado',
        'Cotação disponível',
        "A cotação do imóvel {$imovel['numero_imovel']} do protocolo {$protocolo} está pronta. Aguardando sua aprovação.",
        (int) $cotacao['id'],
        null,
        (int) ($cotacao['usuario_id'] ?? 0)
    );
} catch (Throwable $e) {
    error_log('[incendio-imovel-cotacao] Erro notificação: ' . $e->getMessage());
}

json_response([
    'success'     => true,
    'message'     => 'Cotação enviada. Aguardando aprovação da imobiliária.',
    'status'      => $status_novo,
    'arquivo_url' => $arquivo_url,
]);
