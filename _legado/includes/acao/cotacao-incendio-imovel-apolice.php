<?php
/**
 * POST /api/cotacao/incendio/{protocolo}/imovel/{imovel_id}/apolice
 * Fluxo novo do Seguro Incêndio: MPS anexa o PDF da apólice de UM imóvel.
 * Anexar a apólice conclui o imóvel direto, sem etapa de confirmação.
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

if (($imovel['fluxo'] ?? 'antigo') !== 'novo' || $imovel['status'] !== 'Aguardando apólice') {
    json_response(['success' => false, 'message' => 'Este imóvel não está aguardando a apólice.'], 422);
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
    $arquivo_url = media_upload($_FILES['arquivo'], 'apolices/incendio');
} catch (Throwable $e) {
    json_response(['success' => false, 'message' => 'Erro ao enviar arquivo: ' . $e->getMessage()], 500);
}

db_execute(
    "INSERT INTO cotacoes_anexos_incendio (protocolo, seguro_incendio_imovel_id, nome_arquivo, caminho_arquivo, tipo_arquivo, data_envio, usuario_envio, status_aprovacao)
     VALUES (?, ?, ?, ?, 'apolice', NOW(), ?, 'Emitida')",
    ['sisss', $protocolo, $imovel_id, $_FILES['arquivo']['name'], $arquivo_url, $admin->nome ?? 'MPS']
);

$status_anterior = $imovel['status'];
$status_novo     = 'Concluída';

db_execute(
    "UPDATE seguro_incendio_imoveis SET status = ?, data_status_apolice_anexada = NOW(), data_status_concluido = NOW(), data_atualizacao = NOW() WHERE id = ?",
    ['si', $status_novo, $imovel_id]
);

seguro_incendio_recalcular_status((int) $cotacao['id']);

historico_registrar(
    (int) $cotacao['id'], $protocolo, 'ENVIO_COTACAO',
    $status_anterior, $status_novo, (int) $admin->uid, 'admin', $admin->nome ?? 'MPS',
    "Imóvel {$imovel['numero_imovel']}: PDF da apólice anexado. Processo concluído."
);

try {
    notificar_imobiliaria(
        (int) $cotacao['imobiliaria_id'],
        'status_alterado',
        'Apólice emitida',
        "A apólice do imóvel {$imovel['numero_imovel']} do protocolo {$protocolo} foi anexada. Processo concluído.",
        (int) $cotacao['id'],
        null,
        (int) ($cotacao['usuario_id'] ?? 0)
    );
} catch (Throwable $e) {
    error_log('[incendio-imovel-apolice] Erro notificação: ' . $e->getMessage());
}

json_response([
    'success'     => true,
    'message'     => 'Apólice anexada. Processo concluído.',
    'status'      => $status_novo,
    'arquivo_url' => $arquivo_url,
]);
