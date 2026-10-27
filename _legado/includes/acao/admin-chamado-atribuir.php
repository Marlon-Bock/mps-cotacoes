<?php
/**
 * POST /api/admin/chamado/{protocolo}/atribuir
 * Atribui (ou remove) o atendente MPS responsável por um chamado.
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');
csrf_verify();

$proto = $params['protocolo'] ?? '';
$id = chamado_id_do_protocolo($proto);
if ($id <= 0) { echo json_encode(['ok' => false, 'message' => 'Protocolo inválido.']); exit; }

$chamado = db_fetch_one('SELECT id FROM chamados_suporte WHERE id = ?', ['i', $id]);
if (!$chamado) { echo json_encode(['ok' => false, 'message' => 'Chamado não encontrado.']); exit; }

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$atendente_id = (int) ($input['atendente_id'] ?? 0);

$atendente_nome = null;
if ($atendente_id > 0) {
    $atendente = db_fetch_one('SELECT id, username FROM usuario WHERE id = ?', ['i', $atendente_id]);
    if (!$atendente) { echo json_encode(['ok' => false, 'message' => 'Atendente não encontrado.']); exit; }
    $atendente_nome = $atendente['username'];
} else {
    $atendente_id = null;
}

db_execute(
    'UPDATE chamados_suporte SET atendente_id = ?, atendente_nome = ?, atualizado_em = NOW() WHERE id = ?',
    ['isi', $atendente_id, $atendente_nome, $id]
);

echo json_encode(['ok' => true, 'atendente_nome' => $atendente_nome]);
