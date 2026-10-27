<?php
/**
 * POST /api/cotacao/titulo/{protocolo}/pagar
 * Específico do Título de Capitalização — a imobiliária confirma que o
 * cliente pagou, anexando o comprovante. Muda o status pra "Pago", que
 * libera a MPS a enviar o certificado (cotacao-titulo-certificado.php).
 */

$user = require_auth();
csrf_verify();

if (($user->role ?? '') !== 'imobiliaria') {
    json_response(['success' => false, 'message' => 'Acesso não permitido.'], 403);
}
if (empty($user->imob_admin) && (($user->perm_cotar ?? 'N') !== 'S' || ($user->perm_titulo ?? 'N') !== 'S')) {
    json_response(['success' => false, 'message' => 'Sem permissão para Título de Capitalização.'], 403);
}

$protocolo = $params['protocolo'] ?? '';
if (!$protocolo) {
    json_response(['success' => false, 'message' => 'Protocolo inválido.'], 400);
}

$cotacao = db_fetch_one(
    'SELECT * FROM titulo_capitalizacao WHERE protocolo = ? AND imobiliaria_id = ?',
    ['si', $protocolo, (int) ($user->imobiliaria_id ?? 0)]
);
if (!$cotacao) {
    json_response(['success' => false, 'message' => 'Cotação não encontrada.'], 404);
}
if (empty($user->imob_admin) && (int) ($cotacao['usuario_id'] ?? 0) !== (int) $user->uid) {
    json_response(['success' => false, 'message' => 'Cotação não encontrada.'], 404);
}

if ($cotacao['status'] !== 'Aguardando Pagamento') {
    json_response(['success' => false, 'message' => 'Status atual não permite confirmar pagamento.'], 422);
}

if ($m = upload_erro_limite()) {
    json_response(['success' => false, 'message' => $m], 413);
}
if (empty($_FILES['comprovante']) || ($_FILES['comprovante']['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
    json_response(['success' => false, 'message' => 'Anexe o comprovante de pagamento.'], 422);
}
if ($_FILES['comprovante']['error'] !== UPLOAD_ERR_OK) {
    $msg = in_array($_FILES['comprovante']['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
        ? 'O comprovante excede o limite do servidor (' . upload_limite_label() . '). Envie um arquivo menor.'
        : 'Falha no upload do comprovante. Tente novamente.';
    json_response(['success' => false, 'message' => $msg], 422);
}

try {
    $url_comprovante = media_upload($_FILES['comprovante'], 'titulo/comprovante', media_allowed_mimes());
} catch (Throwable $e) {
    json_response(['success' => false, 'message' => 'Erro ao enviar arquivo: ' . $e->getMessage()], 500);
}

db_execute(
    "INSERT INTO cotacoes_anexos_titulo (protocolo, nome_arquivo, caminho_arquivo, tipo_arquivo, data_envio, usuario_envio, status_aprovacao)
     VALUES (?, ?, ?, 'comprovante_pagamento', NOW(), ?, 'Enviada')",
    ['ssss', $protocolo, $_FILES['comprovante']['name'], $url_comprovante, $user->nome ?? 'Imobiliária']
);

$status_anterior = $cotacao['status'];
$status_novo      = 'Pago';

db_execute(
    "UPDATE titulo_capitalizacao SET status = ?, data_atualizacao = NOW() WHERE protocolo = ?",
    ['ss', $status_novo, $protocolo]
);

historico_registrar(
    (int) $cotacao['id'], $protocolo, 'ALTERACAO_STATUS',
    $status_anterior, $status_novo, (int) $user->uid, 'imobiliaria', $user->nome ?? 'Imobiliária',
    'Pagamento confirmado pela imobiliária, comprovante anexado.'
);

try {
    notificar_todos_admins(
        'status_alterado',
        'Pagamento confirmado',
        "Pagamento confirmado no protocolo {$protocolo}. Envie o certificado.",
        (int) $cotacao['id'],
        cotacao_notif_url('titulo', $protocolo, 'admin')
    );
} catch (Throwable $e) {
    error_log('[titulo-pagar] Erro notificação: ' . $e->getMessage());
}

json_response([
    'success' => true,
    'message' => 'Pagamento confirmado com sucesso.',
    'status'  => $status_novo,
]);
