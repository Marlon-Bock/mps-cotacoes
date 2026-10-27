<?php
/**
 * POST /api/admin/faq/item-salvar
 * Cria ou atualiza uma pergunta/resposta de FAQ (conforme `id` vier vazio ou não).
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');
csrf_verify();

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$id       = (int) ($input['id'] ?? 0);
$bloco_id = (int) ($input['bloco_id'] ?? 0);
$pergunta = trim((string) ($input['pergunta'] ?? ''));
$resposta = trim((string) ($input['resposta'] ?? ''));
$ordem    = (int) ($input['ordem'] ?? 0);

if ($bloco_id <= 0) {
    json_response(['ok' => false, 'message' => 'Bloco inválido.'], 422);
}
if ($pergunta === '' || $resposta === '') {
    json_response(['ok' => false, 'message' => 'Preencha a pergunta e a resposta.'], 422);
}

$bloco = db_fetch_one('SELECT id FROM faq_blocos WHERE id = ?', ['i', $bloco_id]);
if (!$bloco) {
    json_response(['ok' => false, 'message' => 'Bloco não encontrado.'], 404);
}

if ($id > 0) {
    $existente = db_fetch_one('SELECT id FROM faq_itens WHERE id = ?', ['i', $id]);
    if (!$existente) json_response(['ok' => false, 'message' => 'Pergunta não encontrada.'], 404);

    db_execute('UPDATE faq_itens SET bloco_id = ?, pergunta = ?, resposta = ?, ordem = ? WHERE id = ?', ['issii', $bloco_id, $pergunta, $resposta, $ordem, $id]);
} else {
    $id = db_execute('INSERT INTO faq_itens (bloco_id, pergunta, resposta, ordem, criado_em) VALUES (?, ?, ?, ?, NOW())', ['issi', $bloco_id, $pergunta, $resposta, $ordem]);
}

json_response(['ok' => true, 'id' => (int) $id]);
