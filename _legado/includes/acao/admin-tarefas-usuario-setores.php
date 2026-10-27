<?php
/**
 * POST /api/admin/tarefas/usuario-setores  { usuario_id, gestor, setores: [ids] }
 * Define o flag "Gestor de Tarefas" e os setores de um usuário MPS (gestor-only).
 * Espelha o que o cadastro de usuário faz, mas direto pela aba Gerenciar.
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');
csrf_verify();

$ctx = tarefas_contexto_usuario((int) $current_user->uid);
if (!$ctx['gestor']) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Apenas gestores de tarefas podem atribuir setores.']);
    exit;
}

$input      = json_decode(file_get_contents('php://input'), true) ?? [];
$usuario_id = (int) ($input['usuario_id'] ?? 0);
$gestor     = !empty($input['gestor']) ? 'S' : 'N';
$setores    = array_values(array_unique(array_map('intval', (array) ($input['setores'] ?? []))));

$usuario = db_fetch_one('SELECT id FROM usuario WHERE id = ?', ['i', $usuario_id]);
if (!$usuario) {
    echo json_encode(['ok' => false, 'message' => 'Usuário não encontrado.']);
    exit;
}

try {
    db_execute('UPDATE usuario SET tarefas_gestor = ? WHERE id = ?', ['si', $gestor, $usuario_id]);

    db_execute('DELETE FROM tarefas_usuario_setor WHERE usuario_id = ?', ['i', $usuario_id]);
    if ($gestor !== 'S') {
        // Gestor vê tudo — não precisa de setores; só grava vínculos para não-gestores.
        foreach ($setores as $sid) {
            if ($sid <= 0) continue;
            $existe = db_fetch_one('SELECT id FROM setores WHERE id = ?', ['i', $sid]);
            if ($existe) {
                db_execute('INSERT IGNORE INTO tarefas_usuario_setor (usuario_id, setor_id) VALUES (?, ?)', ['ii', $usuario_id, $sid]);
            }
        }
    }

    echo json_encode(['ok' => true]);
} catch (\Throwable $e) {
    error_log('[admin-tarefas-usuario-setores] ' . $e->getMessage());
    echo json_encode(['ok' => false, 'message' => 'Erro ao salvar atribuição.']);
}
