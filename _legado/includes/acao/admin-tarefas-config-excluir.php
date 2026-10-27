<?php
/**
 * POST /api/admin/tarefas/config/excluir  { entidade, id }
 * Exclui uma entidade de configuração (gestor-only) e limpa os vínculos.
 *   entidade = setor | status | etiqueta | campo
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');
csrf_verify();

$ctx = tarefas_contexto_usuario((int) $current_user->uid);
if (!$ctx['gestor']) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Apenas gestores de tarefas podem gerenciar isto.']);
    exit;
}

$input    = json_decode(file_get_contents('php://input'), true) ?? [];
$entidade = trim($input['entidade'] ?? '');
$id       = (int) ($input['id'] ?? 0);

if (!in_array($entidade, ['setor', 'status', 'etiqueta', 'campo'], true) || $id <= 0) {
    echo json_encode(['ok' => false, 'message' => 'Requisição inválida.']);
    exit;
}

try {
    switch ($entidade) {
        case 'setor':
            // Bloqueia se houver tarefas usando o setor (evita órfãs).
            $emUso = (int) (db_fetch_one('SELECT COUNT(*) AS n FROM tarefas WHERE setor_id = ?', ['i', $id])['n'] ?? 0);
            if ($emUso > 0) {
                echo json_encode(['ok' => false, 'message' => "Não é possível excluir: há {$emUso} tarefa(s) neste setor."]);
                exit;
            }
            db_execute('DELETE FROM tarefas_usuario_setor WHERE setor_id = ?', ['i', $id]);
            db_execute('DELETE FROM setores WHERE id = ?', ['i', $id]);
            break;

        case 'status':
            $emUso = (int) (db_fetch_one('SELECT COUNT(*) AS n FROM tarefas WHERE status_id = ?', ['i', $id])['n'] ?? 0);
            if ($emUso > 0) {
                echo json_encode(['ok' => false, 'message' => "Não é possível excluir: há {$emUso} tarefa(s) com este status."]);
                exit;
            }
            db_execute('DELETE FROM tarefas_status WHERE id = ?', ['i', $id]);
            break;

        case 'etiqueta':
            db_execute('DELETE FROM tarefas_etiquetas_vinculo WHERE etiqueta_id = ?', ['i', $id]);
            db_execute('DELETE FROM tarefas_etiquetas WHERE id = ?', ['i', $id]);
            break;

        case 'campo':
            db_execute('DELETE FROM tarefas_campos_valores WHERE campo_id = ?', ['i', $id]);
            db_execute('DELETE FROM tarefas_campos WHERE id = ?', ['i', $id]);
            break;
    }

    echo json_encode(['ok' => true]);
} catch (\Throwable $e) {
    error_log('[admin-tarefas-config-excluir] ' . $e->getMessage());
    echo json_encode(['ok' => false, 'message' => 'Erro ao excluir.']);
}
