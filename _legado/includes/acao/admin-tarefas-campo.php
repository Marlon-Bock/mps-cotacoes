<?php
/**
 * POST /api/admin/tarefas/campo  { id, campo, valor }
 * Salva um campo de texto da tarefa direto do offcanvas (título ou descrição),
 * sem passar pelo salvar completo — assim não mexe em responsáveis/campos custom.
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');
csrf_verify();

$ctx   = tarefas_contexto_usuario((int) $current_user->uid);
$input = json_decode(file_get_contents('php://input'), true) ?? [];

$id    = (int) ($input['id'] ?? 0);
$campo = trim((string) ($input['campo'] ?? ''));
$valor = trim((string) ($input['valor'] ?? ''));

if ($id <= 0) { echo json_encode(['ok' => false, 'message' => 'Tarefa inválida.']); exit; }
if (!in_array($campo, ['titulo', 'descricao'], true)) {
    echo json_encode(['ok' => false, 'message' => 'Campo inválido.']);
    exit;
}

$limite = $campo === 'titulo' ? 255 : 20000;
if (mb_strlen($valor) > $limite) $valor = mb_substr($valor, 0, $limite);

$tarefa = db_fetch_one('SELECT id, setor_id, titulo, descricao FROM tarefas WHERE id = ?', ['i', $id]);
if (!$tarefa) { echo json_encode(['ok' => false, 'message' => 'Tarefa não encontrada.']); exit; }

if (!$ctx['gestor'] && !in_array((int) $tarefa['setor_id'], $ctx['setores'], true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Sem permissão para esta tarefa.']);
    exit;
}

try {
    if ($campo === 'titulo') {
        db_execute('UPDATE tarefas SET titulo = ? WHERE id = ?', ['si', $valor, $id]);
        if (trim((string) $tarefa['titulo']) !== $valor && $valor !== '') {
            tarefa_log($id, (int) $current_user->uid, 'titulo', $valor);
        }
    } else {
        db_execute('UPDATE tarefas SET descricao = ? WHERE id = ?', ['si', $valor !== '' ? $valor : null, $id]);
        if (trim((string) ($tarefa['descricao'] ?? '')) !== $valor) {
            tarefa_log($id, (int) $current_user->uid, 'descricao');
        }
    }
    echo json_encode(['ok' => true]);
} catch (\Throwable $e) {
    error_log('[admin-tarefas-campo] ' . $e->getMessage());
    echo json_encode(['ok' => false, 'message' => 'Erro ao salvar.']);
}
