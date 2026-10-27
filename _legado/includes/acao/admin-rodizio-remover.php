<?php
/**
 * POST /api/admin/rodizio/remover
 * Remove um atendente de um funil de rodízio e reordena os que restaram
 * (mantém a coluna "Ordem" sempre contígua: 1, 2, 3...).
 * Body JSON: { "id": 42 }  ← id da linha em funil_corretores
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');
csrf_verify();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$id    = (int) ($input['id'] ?? 0);

if ($id <= 0) {
    json_response(['success' => false, 'message' => 'Requisição inválida.'], 400);
}

$linha = db_fetch_one('SELECT id, funil_id FROM funil_corretores WHERE id = ?', ['i', $id]);
if (!$linha) {
    json_response(['success' => false, 'message' => 'Atendente não encontrado neste funil.'], 404);
}

$conn = db();
try {
    $conn->begin_transaction();

    db_execute('DELETE FROM funil_corretores WHERE id = ?', ['i', $id]);

    $restantes = db_fetch_all(
        'SELECT id FROM funil_corretores WHERE funil_id = ? ORDER BY ordem ASC',
        ['i', $linha['funil_id']]
    );
    foreach ($restantes as $posicao => $r) {
        db_execute('UPDATE funil_corretores SET ordem = ? WHERE id = ?', ['ii', $posicao + 1, (int) $r['id']]);
    }

    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    error_log('[rodizio-remover] ' . $e->getMessage());
    json_response(['success' => false, 'message' => 'Erro ao remover atendente.'], 500);
}

json_response(['success' => true, 'message' => 'Atendente removido do funil.']);
