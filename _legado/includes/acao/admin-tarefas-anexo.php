<?php
/**
 * POST /api/admin/tarefas/anexo   (multipart: id + arquivo)
 * Envia um arquivo para uma tarefa. Visibilidade por setor.
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');
csrf_verify();

if ($m = upload_erro_limite()) { echo json_encode(['ok' => false, 'message' => $m]); exit; }

$ctx = tarefas_contexto_usuario((int) $current_user->uid);
$id  = (int) ($_POST['id'] ?? 0);

if ($id <= 0) { echo json_encode(['ok' => false, 'message' => 'Tarefa inválida.']); exit; }

$tarefa = db_fetch_one('SELECT id, setor_id FROM tarefas WHERE id = ?', ['i', $id]);
if (!$tarefa) { echo json_encode(['ok' => false, 'message' => 'Tarefa não encontrada.']); exit; }
if (!$ctx['gestor'] && !in_array((int) $tarefa['setor_id'], $ctx['setores'], true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Sem permissão para esta tarefa.']);
    exit;
}

if (empty($_FILES['arquivo']) || $_FILES['arquivo']['error'] !== UPLOAD_ERR_OK) {
    $msg = (!empty($_FILES['arquivo']) && in_array($_FILES['arquivo']['error'], [UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE], true))
        ? 'Arquivo excede o tamanho máximo permitido pelo servidor.'
        : 'Selecione um arquivo para enviar.';
    echo json_encode(['ok' => false, 'message' => $msg]);
    exit;
}

$MB = 1024 * 1024;
$allowed = [
    'application/pdf' => 20 * $MB,
    'image/jpeg' => 20 * $MB, 'image/png' => 20 * $MB, 'image/webp' => 20 * $MB, 'image/gif' => 20 * $MB,
    'application/msword' => 20 * $MB,
    'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 20 * $MB,
    'application/vnd.ms-excel' => 20 * $MB,
    'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 20 * $MB,
    'application/vnd.ms-powerpoint' => 20 * $MB,
    'application/vnd.openxmlformats-officedocument.presentationml.presentation' => 20 * $MB,
    'application/zip' => 20 * $MB, 'application/x-zip-compressed' => 20 * $MB,
    'text/plain' => 20 * $MB, 'text/csv' => 20 * $MB,
];

try {
    $url = media_upload($_FILES['arquivo'], 'tarefas/' . $id, $allowed);
} catch (\Throwable $e) {
    echo json_encode(['ok' => false, 'message' => $e->getMessage()]);
    exit;
}

$nome    = mb_substr($_FILES['arquivo']['name'], 0, 255);
$tipo    = strtolower(pathinfo($nome, PATHINFO_EXTENSION));
$tamanho = (int) $_FILES['arquivo']['size'];

$anexoId = db_execute(
    'INSERT INTO tarefas_anexos (tarefa_id, url, nome, tipo, tamanho, criado_por) VALUES (?, ?, ?, ?, ?, ?)',
    ['isssii', $id, $url, $nome, $tipo, $tamanho, (int) $current_user->uid]
);

tarefa_log($id, (int) $current_user->uid, 'anexo_add', $nome);

echo json_encode([
    'ok'    => true,
    'anexo' => ['id' => (int) $anexoId, 'url' => $url, 'nome' => $nome, 'tipo' => $tipo, 'tamanho' => $tamanho],
], JSON_UNESCAPED_UNICODE);
