<?php
/**
 * AJAX endpoint — POST /api/admin/visitas/regiao-criar
 * Cadastra um novo tipo de região (ex: "Online"), usado no modal de cadastro
 * de regiões do Agendar Visita.
 * Body: { nome, requer_link }
 * Retorna JSON { success, message, item: { id, nome, requer_link } }
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');
csrf_verify();

$body        = json_decode(file_get_contents('php://input'), true) ?? [];
$nome        = trim((string) ($body['nome'] ?? ''));
$requer_link = !empty($body['requer_link']);

if ($nome === '') {
    json_response(['success' => false, 'message' => 'Informe o nome da região.'], 422);
}

$existente = db_fetch_one('SELECT id FROM regioes WHERE nome = ?', ['s', $nome]);
if ($existente) {
    json_response(['success' => false, 'message' => 'Já existe uma região com esse nome.'], 422);
}

try {
    db_execute(
        'INSERT INTO regioes (nome, requer_link) VALUES (?, ?)',
        ['si', $nome, $requer_link ? 1 : 0]
    );
} catch (Throwable $e) {
    error_log('[visitas-regiao-criar] ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'Não foi possível cadastrar a região.'], 500);
}

$item = db_fetch_one('SELECT id, nome, requer_link FROM regioes WHERE nome = ?', ['s', $nome]);

json_response(['success' => true, 'message' => 'Região cadastrada com sucesso.', 'item' => $item]);
