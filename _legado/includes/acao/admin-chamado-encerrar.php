<?php
/**
 * POST /api/admin/chamado/{protocolo}/encerrar
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');
csrf_verify();

$proto = $params['protocolo'] ?? '';
$id = chamado_id_do_protocolo($proto);
if ($id <= 0) { echo json_encode(['ok' => false, 'message' => 'Protocolo inválido.']); exit; }

$chamado = db_fetch_one('SELECT id, status FROM chamados_suporte WHERE id = ?', ['i', $id]);
if (!$chamado) { echo json_encode(['ok' => false, 'message' => 'Chamado não encontrado.']); exit; }
if ($chamado['status'] === 'encerrado') { echo json_encode(['ok' => true]); exit; }

db_execute(
    "UPDATE chamados_suporte SET status = 'encerrado', atualizado_em = NOW() WHERE id = ?",
    ['i', $id]
);

echo json_encode(['ok' => true]);
