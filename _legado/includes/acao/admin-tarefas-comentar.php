<?php
/**
 * POST /api/admin/tarefas/comentar  { id, mensagem, mencoes: [ids] }
 * Adiciona um comentário à tarefa e notifica os usuários mencionados.
 * Só aceita menção de quem enxerga a tarefa (gestor ou membro do setor).
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');
csrf_verify();

$ctx   = tarefas_contexto_usuario((int) $current_user->uid);
$input = json_decode(file_get_contents('php://input'), true) ?? [];

$id       = (int) ($input['id'] ?? 0);
$mensagem = trim((string) ($input['mensagem'] ?? ''));
$mencoes  = array_values(array_unique(array_map('intval', (array) ($input['mencoes'] ?? []))));

if ($id <= 0)        { echo json_encode(['ok' => false, 'message' => 'Tarefa inválida.']); exit; }
if ($mensagem === '') { echo json_encode(['ok' => false, 'message' => 'Escreva um comentário.']); exit; }
if (mb_strlen($mensagem) > 5000) $mensagem = mb_substr($mensagem, 0, 5000);

$tarefa = db_fetch_one('SELECT id, titulo, setor_id FROM tarefas WHERE id = ?', ['i', $id]);
if (!$tarefa) { echo json_encode(['ok' => false, 'message' => 'Tarefa não encontrada.']); exit; }

if (!$ctx['gestor'] && !in_array((int) $tarefa['setor_id'], $ctx['setores'], true)) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Sem permissão para esta tarefa.']);
    exit;
}

try {
    $uid = (int) $current_user->uid;

    $comentarioId = (int) db_execute(
        'INSERT INTO tarefas_comentarios (tarefa_id, usuario_id, mensagem, criado_em) VALUES (?, ?, ?, NOW())',
        ['iis', $id, $uid, $mensagem]
    );

    // Só menciona quem enxerga a tarefa: gestor ou membro do setor dela.
    $validos = [];
    if ($mencoes) {
        $ph   = implode(',', array_fill(0, count($mencoes), '?'));
        $rows = db_fetch_all(
            "SELECT u.id, u.username
               FROM usuario u
              WHERE u.id IN ({$ph})
                AND (u.tarefas_gestor = 'S'
                     OR EXISTS (SELECT 1 FROM tarefas_usuario_setor us
                                 WHERE us.usuario_id = u.id AND us.setor_id = ?))",
            [str_repeat('i', count($mencoes)) . 'i', ...[...$mencoes, (int) $tarefa['setor_id']]]
        );
        $validos = $rows;
    }

    $nomes_mencionados = [];
    foreach ($validos as $m) {
        $mid = (int) $m['id'];
        $nomes_mencionados[] = $m['username'];
        db_execute(
            'INSERT IGNORE INTO tarefas_comentarios_mencoes (comentario_id, usuario_id) VALUES (?, ?)',
            ['ii', $comentarioId, $mid]
        );
        if ($mid !== $uid) {
            notificar_mps(
                'admin',
                $mid,
                'tarefa_mencao',
                'Você foi mencionado em uma tarefa',
                ($current_user->nome ?? 'Alguém') . ' mencionou você em "' . $tarefa['titulo'] . '".'
            );
        }
    }

    tarefa_log($id, $uid, 'comentou');

    $autor = db_fetch_one('SELECT username, avatar FROM usuario WHERE id = ?', ['i', $uid]);

    echo json_encode([
        'ok'         => true,
        'comentario' => [
            'id'         => $comentarioId,
            'usuario_id' => $uid,
            'username'   => $autor['username'] ?? '',
            'avatar'     => $autor['avatar'] ?? null,
            'mensagem'   => $mensagem,
            'mencoes'    => $nomes_mencionados,
            'criado_em'  => date('d/m/Y H:i'),
        ],
    ], JSON_UNESCAPED_UNICODE);
} catch (\Throwable $e) {
    error_log('[admin-tarefas-comentar] ' . $e->getMessage());
    echo json_encode(['ok' => false, 'message' => 'Erro ao salvar o comentário.']);
}
