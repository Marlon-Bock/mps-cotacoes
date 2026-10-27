<?php
/**
 * POST /api/admin/chamado/{protocolo}/mensagem-anexo
 * Envia um documento no chat do chamado, como resposta da equipe MPS.
 * Mesmas regras de admin-chamado-responder.php.
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');
csrf_verify();

if ($m = upload_erro_limite()) json_response(['ok' => false, 'message' => $m], 413);

$proto = $params['protocolo'] ?? '';
$id = chamado_id_do_protocolo($proto);
if ($id <= 0) json_response(['ok' => false, 'message' => 'Protocolo inválido.'], 400);

$chamado = db_fetch_one('SELECT id, status FROM chamados_suporte WHERE id = ?', ['i', $id]);
if (!$chamado) json_response(['ok' => false, 'message' => 'Chamado não encontrado.'], 404);
if ($chamado['status'] === 'encerrado') {
    json_response(['ok' => false, 'message' => 'Este chamado está encerrado, não é mais possível enviar mensagens.'], 422);
}

if (empty($_FILES['arquivo'])) {
    json_response(['ok' => false, 'message' => 'Selecione um arquivo para enviar.'], 422);
}
if ($_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
    $erroUpload = in_array($_FILES['arquivo']['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
        ? 'Arquivo excede o tamanho máximo permitido pelo servidor.'
        : 'Erro no envio do arquivo. Tente novamente.';
    json_response(['ok' => false, 'message' => $erroUpload], 422);
}

$legenda = mb_substr(trim((string) ($_POST['mensagem'] ?? '')), 0, 2000);

try {
    $anexo_url = media_upload($_FILES['arquivo'], 'chat-chamado/' . $proto);
} catch (Throwable $e) {
    json_response(['ok' => false, 'message' => 'Erro ao enviar arquivo: ' . $e->getMessage()], 500);
}

$anexo_nome = $_FILES['arquivo']['name'];
$anexo_tipo = strtolower(pathinfo($anexo_nome, PATHINFO_EXTENSION));
$autor_nome = $current_user->nome ?? 'Equipe MPS';

$msg_id = db_execute(
    "INSERT INTO chamados_respostas (chamado_id, autor_tipo, autor_id, autor_nome, mensagem, anexo_url, anexo_nome, anexo_tipo, criado_em)
     VALUES (?, 'admin', ?, ?, ?, ?, ?, ?, NOW())",
    ['iisssss', $id, $current_user->uid, $autor_nome, $legenda, $anexo_url, $anexo_nome, $anexo_tipo]
);

db_execute(
    "UPDATE chamados_suporte SET status = 'aguardando_imob', atualizado_em = NOW() WHERE id = ?",
    ['i', $id]
);

json_response([
    'ok'        => true,
    'mensagem'  => [
        'id'         => (int) $msg_id,
        'autor_tipo' => 'admin',
        'autor_id'   => (int) $current_user->uid,
        'autor_nome' => $autor_nome,
        'mensagem'   => $legenda,
        'anexo_url'  => $anexo_url,
        'anexo_nome' => $anexo_nome,
        'anexo_tipo' => $anexo_tipo,
        'criado_em'  => date('Y-m-d H:i:s'),
        'hora'       => date('H:i'),
        'data'       => date('Y-m-d'),
        'data_label' => date('d/m/Y'),
    ],
]);
