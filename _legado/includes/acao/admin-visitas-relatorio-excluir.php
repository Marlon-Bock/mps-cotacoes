<?php
/**
 * AJAX endpoint — POST /api/admin/visitas-relatorio/excluir
 * Exclui um item do relatório de visitas do próprio usuário.
 * Body JSON: { "item_id": 42 }
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');
csrf_verify();

$input   = json_decode(file_get_contents('php://input'), true) ?? [];
$item_id = (int) ($input['item_id'] ?? 0);

if ($item_id <= 0) {
    json_response(['success' => false, 'message' => 'Requisição inválida.'], 400);
}

$existente = db_fetch_one(
    "SELECT rvi.id FROM relatorio_visitas_itens rvi
     JOIN relatorios_visitas rv ON rv.id = rvi.relatorio_id
     WHERE rvi.id = ? AND rv.usuario_id = ?",
    ['ii', $item_id, (int) $current_user->uid]
);
if (!$existente) {
    json_response(['success' => false, 'message' => 'Visita não encontrada.'], 404);
}

try {
    db_execute('DELETE FROM relatorio_visitas_itens WHERE id = ?', ['i', $item_id]);
} catch (Throwable $e) {
    error_log('[visitas-relatorio-excluir] ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'Erro ao excluir a visita.'], 500);
}

json_response(['success' => true, 'message' => 'Visita excluída.']);
