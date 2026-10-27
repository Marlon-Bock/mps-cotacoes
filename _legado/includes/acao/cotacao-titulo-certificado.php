<?php
/**
 * POST /api/cotacao/titulo/{protocolo}/certificado
 * Específico do Título de Capitalização — depois do pagamento confirmado, a
 * MPS envia o certificado do título, o que conclui a cotação.
 */

$admin = require_admin();
csrf_verify();

$protocolo = $params['protocolo'] ?? '';
if (!$protocolo) {
    json_response(['success' => false, 'message' => 'Protocolo inválido.'], 400);
}

$cotacao = db_fetch_one('SELECT * FROM titulo_capitalizacao WHERE protocolo = ?', ['s', $protocolo]);
if (!$cotacao) {
    json_response(['success' => false, 'message' => 'Cotação não encontrada.'], 404);
}

if ($cotacao['status'] !== 'Pago') {
    json_response(['success' => false, 'message' => 'Status atual não permite enviar o certificado.'], 422);
}

if ($m = upload_erro_limite()) {
    json_response(['success' => false, 'message' => $m], 413);
}
if (empty($_FILES['certificado']) || ($_FILES['certificado']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
    json_response(['success' => false, 'message' => 'Arquivo do certificado é obrigatório.'], 422);
}
if ($_FILES['certificado']['error'] !== UPLOAD_ERR_OK) {
    $msg = in_array($_FILES['certificado']['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
        ? 'O certificado excede o limite do servidor (' . upload_limite_label() . '). Envie um arquivo menor.'
        : 'Falha no upload do certificado. Tente novamente.';
    json_response(['success' => false, 'message' => $msg], 422);
}

try {
    $url_certificado = media_upload($_FILES['certificado'], 'titulo/certificado');
} catch (Throwable $e) {
    json_response(['success' => false, 'message' => 'Erro ao enviar arquivo: ' . $e->getMessage()], 500);
}

db_execute(
    "INSERT INTO cotacoes_anexos_titulo (protocolo, nome_arquivo, caminho_arquivo, tipo_arquivo, data_envio, usuario_envio, status_aprovacao)
     VALUES (?, ?, ?, 'certificado', NOW(), ?, 'Enviada')",
    ['ssss', $protocolo, $_FILES['certificado']['name'], $url_certificado, $admin->nome ?? 'MPS']
);

$status_anterior = $cotacao['status'];
$status_novo      = 'Concluída';

db_execute(
    "UPDATE titulo_capitalizacao SET status = ?, data_atualizacao = NOW() WHERE protocolo = ?",
    ['ss', $status_novo, $protocolo]
);

historico_registrar(
    (int) $cotacao['id'], $protocolo, 'ALTERACAO_STATUS',
    $status_anterior, $status_novo, (int) $admin->uid, 'admin', $admin->nome ?? 'MPS',
    'Certificado enviado. Cotação concluída.'
);

try {
    notificar_imobiliaria(
        (int) $cotacao['imobiliaria_id'],
        'status_alterado',
        'Certificado disponível',
        "O certificado do título do protocolo {$protocolo} já está disponível.",
        (int) $cotacao['id'],
        null,
        (int) ($cotacao['usuario_id'] ?? 0)
    );
} catch (Throwable $e) {
    error_log('[titulo-certificado] Erro notificação: ' . $e->getMessage());
}

json_response([
    'success' => true,
    'message' => 'Certificado enviado com sucesso.',
    'status'  => $status_novo,
]);
