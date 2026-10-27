<?php
/**
 * POST /api/admin/chamado/{protocolo}/responder
 * Envia uma resposta da equipe MPS num chamado — move o status pra
 * "aguardando_imob" (espelho de chamado-responder.php, que move pra
 * "aguardando_mps" quando quem responde é a imobiliária).
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');
csrf_verify();

$proto = $params['protocolo'] ?? '';
$id = chamado_id_do_protocolo($proto);
if ($id <= 0) { echo json_encode(['ok' => false, 'message' => 'Protocolo inválido.']); exit; }

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$mensagem = trim($input['mensagem'] ?? '');
if ($mensagem === '') { echo json_encode(['ok' => false, 'message' => 'Mensagem não pode ser vazia.']); exit; }

$chamado = db_fetch_one('SELECT id, status FROM chamados_suporte WHERE id = ?', ['i', $id]);
if (!$chamado) { echo json_encode(['ok' => false, 'message' => 'Chamado não encontrado.']); exit; }
if ($chamado['status'] === 'encerrado') { echo json_encode(['ok' => false, 'message' => 'Chamado encerrado.']); exit; }

db_execute(
    "INSERT INTO chamados_respostas (chamado_id, autor_tipo, autor_id, autor_nome, mensagem, criado_em)
     VALUES (?, 'admin', ?, ?, ?, NOW())",
    ['iiss', $id, $current_user->uid, $current_user->nome, $mensagem]
);

db_execute(
    "UPDATE chamados_suporte SET status = 'aguardando_imob', atualizado_em = NOW() WHERE id = ?",
    ['i', $id]
);

echo json_encode(['ok' => true]);
