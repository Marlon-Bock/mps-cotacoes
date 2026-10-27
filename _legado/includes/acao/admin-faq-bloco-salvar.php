<?php
/**
 * POST /api/admin/faq/bloco-salvar
 * Cria ou atualiza um bloco de FAQ (conforme `id` vier vazio ou não).
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');
csrf_verify();

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$id     = (int) ($input['id'] ?? 0);
$titulo = trim((string) ($input['titulo'] ?? ''));
$ordem  = (int) ($input['ordem'] ?? 0);
$ativo  = !empty($input['ativo']) ? 1 : 0;

if ($titulo === '') {
    json_response(['ok' => false, 'message' => 'Informe o título do bloco.'], 422);
}

if ($id > 0) {
    $existente = db_fetch_one('SELECT id FROM faq_blocos WHERE id = ?', ['i', $id]);
    if (!$existente) json_response(['ok' => false, 'message' => 'Bloco não encontrado.'], 404);

    db_execute('UPDATE faq_blocos SET titulo = ?, ordem = ?, ativo = ? WHERE id = ?', ['siii', $titulo, $ordem, $ativo, $id]);
} else {
    $id = db_execute('INSERT INTO faq_blocos (titulo, ordem, ativo, criado_em) VALUES (?, ?, ?, NOW())', ['sii', $titulo, $ordem, $ativo]);
}

json_response(['ok' => true, 'id' => (int) $id]);
