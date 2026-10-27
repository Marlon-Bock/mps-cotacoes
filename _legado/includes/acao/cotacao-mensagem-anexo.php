<?php
/**
 * POST /api/cotacao/{protocolo}/mensagem-anexo
 * Envia um documento (PDF/imagem) no chat da cotação, como uma mensagem com
 * anexo. Mesmas regras de acesso e bloqueio de cotacao-mensagem.php.
 */
$user = require_auth();
header('Content-Type: application/json; charset=utf-8');
csrf_verify();

if ($m = upload_erro_limite()) json_response(['success' => false, 'message' => $m], 413);

$protocolo = $params['protocolo'] ?? '';
if (!$protocolo) json_response(['success' => false, 'message' => 'Protocolo inválido.'], 400);

$resolvido = cotacao_resolver($protocolo);
if (!$resolvido) json_response(['success' => false, 'message' => 'Cotação não encontrada.'], 404);

$autor_tipo = $user->role === 'admin' ? 'admin' : 'imobiliaria';
if (!cotacao_pode_acessar($resolvido, $user)) {
    json_response(['success' => false, 'message' => 'Cotação não encontrada.'], 404);
}

if (cotacao_status_encerrado($resolvido['status'])) {
    json_response(['success' => false, 'message' => 'Esta cotação está encerrada, não é mais possível enviar mensagens.'], 422);
}

if (empty($_FILES['arquivo'])) {
    json_response(['success' => false, 'message' => 'Selecione um arquivo para enviar.'], 422);
}
if ($_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
    $erroUpload = in_array($_FILES['arquivo']['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true)
        ? 'Arquivo excede o tamanho máximo permitido pelo servidor.'
        : 'Erro no envio do arquivo. Tente novamente.';
    json_response(['success' => false, 'message' => $erroUpload], 422);
}

$legenda = mb_substr(trim((string) ($_POST['mensagem'] ?? '')), 0, 2000);

$tipos_permitidos = [
    'application/pdf' => 10 * 1024 * 1024,
    'image/jpeg' => 10 * 1024 * 1024,
    'image/png' => 10 * 1024 * 1024,
    'image/webp' => 10 * 1024 * 1024,
    'application/msword' => 10 * 1024 * 1024,
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 10 * 1024 * 1024,
];

try {
    $anexo_url = media_upload($_FILES['arquivo'], 'chat-cotacao/' . $protocolo, $tipos_permitidos);
} catch (Throwable $e) {
    json_response(['success' => false, 'message' => 'Erro ao enviar arquivo: ' . $e->getMessage()], 500);
}

$anexo_nome = $_FILES['arquivo']['name'];
$anexo_tipo = strtolower(pathinfo($anexo_nome, PATHINFO_EXTENSION));
$autor_nome = $user->nome ?? ($autor_tipo === 'admin' ? 'MPS' : 'Imobiliária');

$id = db_execute(
    "INSERT INTO cotacao_mensagens (protocolo, autor_tipo, autor_id, autor_nome, mensagem, anexo_url, anexo_nome, anexo_tipo, criado_em)
     VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())",
    ['ssisssss', $protocolo, $autor_tipo, (int) $user->uid, $autor_nome, $legenda, $anexo_url, $anexo_nome, $anexo_tipo]
);

// Mensagem/anexo no chat também conta como atividade da cotação — ver
// mesmo comentário em cotacao-mensagem.php.
db_execute(
    "UPDATE {$resolvido['tabela']} SET data_atualizacao = NOW() WHERE id = ?",
    ['i', $resolvido['id']]
);

// Quem envia já leu tudo até a própria mensagem — mesmo racional de cotacao-mensagem.php.
db_execute(
    "INSERT INTO cotacao_mensagens_lidas (protocolo, usuario_id, papel, ultima_id_lida)
     VALUES (?, ?, ?, ?)
     ON DUPLICATE KEY UPDATE ultima_id_lida = GREATEST(ultima_id_lida, VALUES(ultima_id_lida)), atualizado_em = NOW()",
    ['sisi', $protocolo, (int) $user->uid, $autor_tipo, $id]
);

$resumo_msg = 'Enviou um documento: ' . $anexo_nome;
if ($autor_tipo === 'admin') {
    notificar_imobiliaria(
        $resolvido['imobiliaria_id'],
        'chat_mensagem',
        "Nova mensagem: {$protocolo}",
        "{$autor_nome}: {$resumo_msg}",
        $resolvido['id'],
        null,
        (int) ($resolvido['usuario_id'] ?? 0)
    );
} else {
    notificar_todos_admins(
        'chat_mensagem',
        "Nova mensagem: {$protocolo}",
        "{$autor_nome}: {$resumo_msg}",
        $resolvido['id']
    );
}

json_response([
    'success' => true,
    'mensagem' => [
        'id'         => (int) $id,
        'autor_tipo' => $autor_tipo,
        'autor_nome' => $autor_nome,
        'mensagem'   => $legenda,
        'anexo_url'  => $anexo_url,
        'anexo_nome' => $anexo_nome,
        'anexo_tipo' => $anexo_tipo,
        'criado_em'  => date('Y-m-d H:i:s'),
        'hora'       => date('H:i'),
        'data_label' => date('d/m/Y'),
    ],
]);
