<?php
/**
 * GET /api/admin/tarefas/detalhe?id=N
 * Devolve tudo que o offcanvas da tarefa precisa numa chamada só:
 * dados da tarefa, responsáveis, anexos, comentários (com menções) e histórico.
 * Respeita a visibilidade por setor.
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');

$ctx = tarefas_contexto_usuario((int) $current_user->uid);
$id  = (int) ($_GET['id'] ?? 0);

if ($id <= 0) { echo json_encode(['ok' => false, 'message' => 'Tarefa inválida.']); exit; }

try {
    $t = db_fetch_one(
        "SELECT t.id, t.titulo, t.descricao, t.setor_id, t.status_id, t.prazo,
                t.criado_em, t.concluida_em, t.criado_por,
                s.nome AS setor_nome, s.cor AS setor_cor,
                st.nome AS status_nome, st.cor AS status_cor,
                u.username AS criado_por_nome
           FROM tarefas t
           LEFT JOIN setores s          ON s.id  = t.setor_id
           LEFT JOIN tarefas_status st  ON st.id = t.status_id
           LEFT JOIN usuario u          ON u.id  = t.criado_por
          WHERE t.id = ?",
        ['i', $id]
    );
    if (!$t) { echo json_encode(['ok' => false, 'message' => 'Tarefa não encontrada.']); exit; }

    if (!$ctx['gestor'] && !in_array((int) $t['setor_id'], $ctx['setores'], true)) {
        http_response_code(403);
        echo json_encode(['ok' => false, 'message' => 'Sem permissão para esta tarefa.']);
        exit;
    }

    $responsaveis = db_fetch_all(
        "SELECT u.id, u.username, u.avatar
           FROM tarefas_responsaveis tr JOIN usuario u ON u.id = tr.usuario_id
          WHERE tr.tarefa_id = ? ORDER BY u.username ASC",
        ['i', $id]
    );

    $anexos = db_fetch_all(
        'SELECT id, url, nome, tipo, tamanho, criado_em FROM tarefas_anexos WHERE tarefa_id = ? ORDER BY id DESC',
        ['i', $id]
    );

    $comentarios = db_fetch_all(
        "SELECT c.id, c.mensagem, c.criado_em, c.usuario_id, u.username, u.avatar
           FROM tarefas_comentarios c LEFT JOIN usuario u ON u.id = c.usuario_id
          WHERE c.tarefa_id = ? ORDER BY c.id ASC",
        ['i', $id]
    );

    // Menções de cada comentário (para destacar no texto).
    $mencoes = [];
    if ($comentarios) {
        $ids = array_map(static fn($c) => (int) $c['id'], $comentarios);
        $ph  = implode(',', array_fill(0, count($ids), '?'));
        $rows = db_fetch_all(
            "SELECT m.comentario_id, u.username
               FROM tarefas_comentarios_mencoes m JOIN usuario u ON u.id = m.usuario_id
              WHERE m.comentario_id IN ({$ph})",
            [str_repeat('i', count($ids)), ...$ids]
        );
        foreach ($rows as $r) { $mencoes[(int) $r['comentario_id']][] = $r['username']; }
    }
    foreach ($comentarios as &$c) {
        $c['id']        = (int) $c['id'];
        $c['mencoes']   = $mencoes[$c['id']] ?? [];
        $c['criado_em'] = date('d/m/Y H:i', strtotime($c['criado_em']));
    }
    unset($c);

    // Abrir a tarefa marca os comentários como lidos (zera o badge do balão).
    db_execute(
        'INSERT INTO tarefas_leitura (tarefa_id, usuario_id, lido_em) VALUES (?, ?, NOW())
         ON DUPLICATE KEY UPDATE lido_em = NOW()',
        ['ii', $id, (int) $current_user->uid]
    );

    $hist = db_fetch_all(
        "SELECT h.acao, h.detalhe, h.criado_em, h.usuario_id, u.username, u.avatar
           FROM tarefas_historico h LEFT JOIN usuario u ON u.id = h.usuario_id
          WHERE h.tarefa_id = ? ORDER BY h.id DESC",
        ['i', $id]
    );
    $historico = array_map(static fn($h) => [
        'usuario'   => $h['username'] ?: 'Sistema',
        'avatar'    => $h['avatar'] ?? null,
        'acao'      => $h['acao'],
        'icone'     => tarefa_historico_icone($h['acao']),
        'frase'     => tarefa_historico_frase($h['acao'], $h['detalhe']),
        'criado_em' => date('d/m/Y H:i', strtotime($h['criado_em'])),
    ], $hist);

    echo json_encode([
        'ok'    => true,
        'tarefa' => [
            'id'           => (int) $t['id'],
            'titulo'       => $t['titulo'],
            'descricao'    => $t['descricao'] ?? '',
            'setor_id'     => (int) $t['setor_id'],
            'setor_nome'   => $t['setor_nome'] ?? '',
            'setor_cor'    => $t['setor_cor'] ?: '#94a3b8',
            'status_id'    => (int) ($t['status_id'] ?? 0),
            'status_nome'  => $t['status_nome'] ?? '',
            'status_cor'   => $t['status_cor'] ?: '#64748b',
            'prazo'        => $t['prazo'] ?? '',
            'prazo_label'  => $t['prazo'] ? date('d/m/Y', strtotime($t['prazo'])) : '',
            'criado_em'    => $t['criado_em'] ? date('d/m/Y H:i', strtotime($t['criado_em'])) : '',
            'criado_por'   => $t['criado_por_nome'] ?? '',
            'concluida_em' => $t['concluida_em'] ? date('d/m/Y H:i', strtotime($t['concluida_em'])) : '',
        ],
        'responsaveis' => array_map(static fn($r) => [
            'id'       => (int) $r['id'],
            'username' => $r['username'],
            'avatar'   => $r['avatar'] ?? null,
        ], $responsaveis),
        'anexos'      => array_map(static fn($a) => [
            'id'     => (int) $a['id'],
            'url'    => $a['url'],
            'nome'   => $a['nome'],
            'tipo'   => $a['tipo'],
        ], $anexos),
        'comentarios' => $comentarios,
        'historico'   => $historico,
    ], JSON_UNESCAPED_UNICODE);
} catch (\Throwable $e) {
    error_log('[admin-tarefas-detalhe] ' . $e->getMessage());
    echo json_encode(['ok' => false, 'message' => 'Erro ao carregar a tarefa.']);
}
