<?php
/**
 * POST /api/admin/tarefas/responsaveis  { id, usuarios: [ids] }
 * Define (substitui) os responsáveis de uma tarefa — usado pelo popup inline.
 * Respeita a visibilidade por setor.
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');
csrf_verify();

$ctx   = tarefas_contexto_usuario((int) $current_user->uid);
$input = json_decode(file_get_contents('php://input'), true) ?? [];
$id    = (int) ($input['id'] ?? 0);
$usuarios = array_values(array_unique(array_map('intval', (array) ($input['usuarios'] ?? []))));

if ($id <= 0) { echo json_encode(['ok' => false, 'message' => 'Tarefa inválida.']); exit; }

$tarefa = db_fetch_one(
    'SELECT t.id, t.titulo, t.setor_id, t.prazo, s.nome AS setor_nome
       FROM tarefas t LEFT JOIN setores s ON s.id = t.setor_id
      WHERE t.id = ?',
    ['i', $id]
);
if (!$tarefa) { echo json_encode(['ok' => false, 'message' => 'Tarefa não encontrada.']); exit; }

if (!$ctx['gestor'] && !in_array((int) $tarefa['setor_id'], $ctx['setores'], true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Sem permissão para esta tarefa.']);
    exit;
}

try {
    $rows_antes = db_fetch_all('SELECT usuario_id FROM tarefas_responsaveis WHERE tarefa_id = ?', ['i', $id]);
    $antes      = array_map(static fn($r) => (int) $r['usuario_id'], $rows_antes);

    db_execute('DELETE FROM tarefas_responsaveis WHERE tarefa_id = ?', ['i', $id]);
    foreach ($usuarios as $uid) {
        if ($uid > 0) {
            db_execute('INSERT IGNORE INTO tarefas_responsaveis (tarefa_id, usuario_id) VALUES (?, ?)', ['ii', $id, $uid]);
        }
    }

    $depois = array_values(array_filter($usuarios, static fn($u) => $u > 0));
    $frase  = tarefa_resp_diff_frase($antes, $depois);
    if ($frase !== '') tarefa_log($id, (int) $current_user->uid, 'responsaveis', $frase);

    // Avisa (sino + e-mail) quem acabou de ser atribuído à tarefa.
    $titulo_tarefa = trim((string) $tarefa['titulo']) !== '' ? $tarefa['titulo'] : 'Tarefa sem título';
    foreach (array_diff($depois, $antes) as $novo) {
        if ((int) $novo === (int) $current_user->uid) continue;   // não notifica quem fez a ação
        tarefa_notificar(
            (int) $novo,
            'tarefa_atribuida',
            'Nova tarefa atribuída a você',
            ($current_user->nome ?? 'Alguém') . ' atribuiu a você a tarefa "' . $titulo_tarefa . '".',
            true,
            tarefa_email_detalhes([
                'Tarefa' => $titulo_tarefa,
                'Setor'  => $tarefa['setor_nome'] ?? 'Sem setor',
                'Prazo'  => $tarefa['prazo'] ? date('d/m/Y', strtotime($tarefa['prazo'])) : 'Sem prazo',
            ])
        );
    }

    echo json_encode(['ok' => true]);
} catch (\Throwable $e) {
    error_log('[admin-tarefas-responsaveis] ' . $e->getMessage());
    echo json_encode(['ok' => false, 'message' => 'Erro ao salvar responsáveis.']);
}
