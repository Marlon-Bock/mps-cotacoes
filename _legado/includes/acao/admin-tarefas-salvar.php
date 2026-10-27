<?php
/**
 * POST /api/admin/tarefas/salvar
 * Cria ou atualiza uma tarefa (JSON no corpo). Também sincroniza responsáveis,
 * etiquetas e os valores dos campos customizados.
 *
 * Permissão: gestor cria/edita qualquer tarefa; não-gestor só dentro dos seus
 * setores (a tarefa precisa pertencer a um setor a que ele pertence).
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');
csrf_verify();

$ctx   = tarefas_contexto_usuario((int) $current_user->uid);
$input = json_decode(file_get_contents('php://input'), true) ?? [];

$id         = (int) ($input['id'] ?? 0);
$titulo     = trim($input['titulo'] ?? '');
$descricao  = trim($input['descricao'] ?? '');
$setor_id   = (int) ($input['setor_id'] ?? 0);
$status_id  = (int) ($input['status_id'] ?? 0);
$prioridade = trim($input['prioridade'] ?? '');
$prazo      = trim($input['prazo'] ?? '');
$responsaveis = array_values(array_unique(array_map('intval', (array) ($input['responsaveis'] ?? []))));
$etiquetas    = array_values(array_unique(array_map('intval', (array) ($input['etiquetas'] ?? []))));
$campos       = (array) ($input['campos'] ?? []);

if (!in_array($prioridade, ['baixa', 'media', 'alta'], true)) $prioridade = '';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $prazo)) $prazo = '';

if ($titulo === '') {
    echo json_encode(['ok' => false, 'message' => 'Informe o título da tarefa.']);
    exit;
}
if ($setor_id <= 0) {
    echo json_encode(['ok' => false, 'message' => 'Selecione o setor da tarefa.']);
    exit;
}

// Setor precisa existir e (se não-gestor) pertencer ao usuário.
$setor = db_fetch_one('SELECT id FROM setores WHERE id = ?', ['i', $setor_id]);
if (!$setor) {
    echo json_encode(['ok' => false, 'message' => 'Setor inválido.']);
    exit;
}
if (!$ctx['gestor'] && !in_array($setor_id, $ctx['setores'], true)) {
    echo json_encode(['ok' => false, 'message' => 'Você não pode criar tarefas neste setor.']);
    exit;
}

// concluida_em depende do status escolhido (is_final).
$statusFinal = false;
if ($status_id > 0) {
    $st = db_fetch_one('SELECT is_final FROM tarefas_status WHERE id = ?', ['i', $status_id]);
    if (!$st) { $status_id = 0; } else { $statusFinal = (int) $st['is_final'] === 1; }
}

try {
    $uid_log  = (int) $current_user->uid;
    $eh_novo  = $id <= 0;
    $antes    = null;
    $resp_antes = [];

    if ($id > 0) {
        $tarefa = db_fetch_one(
            'SELECT id, titulo, descricao, setor_id, status_id, prazo, concluida_em FROM tarefas WHERE id = ?',
            ['i', $id]
        );
        $antes = $tarefa;
        if (!$tarefa) {
            echo json_encode(['ok' => false, 'message' => 'Tarefa não encontrada.']);
            exit;
        }
        if (!$ctx['gestor'] && !in_array((int) $tarefa['setor_id'], $ctx['setores'], true)) {
            echo json_encode(['ok' => false, 'message' => 'Sem permissão para editar esta tarefa.']);
            exit;
        }

        // Mantém concluida_em existente; só define/limpa conforme o status final.
        $concluidaSql = $statusFinal
            ? (empty($tarefa['concluida_em']) ? 'NOW()' : 'concluida_em')
            : 'NULL';

        db_execute(
            "UPDATE tarefas
                SET titulo = ?, descricao = ?, setor_id = ?, status_id = ?, prioridade = ?, prazo = ?,
                    concluida_em = {$concluidaSql}
              WHERE id = ?",
            ['ssiissi',
                $titulo,
                $descricao !== '' ? $descricao : null,
                $setor_id,
                $status_id ?: null,
                $prioridade !== '' ? $prioridade : null,
                $prazo !== '' ? $prazo : null,
                $id,
            ]
        );
    } else {
        $concluidaSql = $statusFinal ? 'NOW()' : 'NULL';
        db_execute(
            "INSERT INTO tarefas (titulo, descricao, setor_id, status_id, prioridade, prazo, criado_por, concluida_em)
             VALUES (?, ?, ?, ?, ?, ?, ?, {$concluidaSql})",
            ['ssiissi',
                $titulo,
                $descricao !== '' ? $descricao : null,
                $setor_id,
                $status_id ?: null,
                $prioridade !== '' ? $prioridade : null,
                $prazo !== '' ? $prazo : null,
                (int) $current_user->uid,
            ]
        );
        $id = (int) db()->insert_id;
    }

    // ── Histórico: criação ou diff dos campos alterados ────────────────────────
    if ($eh_novo) {
        tarefa_log($id, $uid_log, 'criou');
    } elseif ($antes) {
        if (trim((string) $antes['titulo']) !== $titulo) {
            tarefa_log($id, $uid_log, 'titulo', $titulo);
        }
        if (trim((string) ($antes['descricao'] ?? '')) !== $descricao) {
            tarefa_log($id, $uid_log, 'descricao');
        }
        if ((int) $antes['setor_id'] !== $setor_id) {
            $s = db_fetch_one('SELECT nome FROM setores WHERE id = ?', ['i', $setor_id]);
            tarefa_log($id, $uid_log, 'setor', $s['nome'] ?? '');
        }
        if ((int) ($antes['status_id'] ?? 0) !== $status_id) {
            $nome_st = '';
            if ($status_id > 0) {
                $s = db_fetch_one('SELECT nome FROM tarefas_status WHERE id = ?', ['i', $status_id]);
                $nome_st = $s['nome'] ?? '';
            }
            tarefa_log($id, $uid_log, 'status', $nome_st);
        }
        $prazo_antes = (string) ($antes['prazo'] ?? '');
        if ($prazo_antes !== $prazo) {
            tarefa_log($id, $uid_log, 'prazo', $prazo !== '' ? date('d/m/Y', strtotime($prazo)) : '');
        }
    }

    // ── Responsáveis (apaga + reinsere) ────────────────────────────────────────
    if (!$eh_novo) {
        $rows_antes = db_fetch_all('SELECT usuario_id FROM tarefas_responsaveis WHERE tarefa_id = ?', ['i', $id]);
        $resp_antes = array_map(static fn($r) => (int) $r['usuario_id'], $rows_antes);
    }
    db_execute('DELETE FROM tarefas_responsaveis WHERE tarefa_id = ?', ['i', $id]);
    foreach ($responsaveis as $uid) {
        if ($uid > 0) {
            db_execute('INSERT IGNORE INTO tarefas_responsaveis (tarefa_id, usuario_id) VALUES (?, ?)', ['ii', $id, $uid]);
        }
    }

    if (!$eh_novo) {
        $frase = tarefa_resp_diff_frase($resp_antes, array_filter($responsaveis, static fn($u) => $u > 0));
        if ($frase !== '') tarefa_log($id, $uid_log, 'responsaveis', $frase);
    }

    // ── Etiquetas (apaga + reinsere) ───────────────────────────────────────────
    db_execute('DELETE FROM tarefas_etiquetas_vinculo WHERE tarefa_id = ?', ['i', $id]);
    foreach ($etiquetas as $eid) {
        if ($eid > 0) {
            db_execute('INSERT IGNORE INTO tarefas_etiquetas_vinculo (tarefa_id, etiqueta_id) VALUES (?, ?)', ['ii', $id, $eid]);
        }
    }

    // ── Campos customizados (só os campos ativos existentes) ───────────────────
    db_execute('DELETE FROM tarefas_campos_valores WHERE tarefa_id = ?', ['i', $id]);
    $camposValidos = db_fetch_all('SELECT id FROM tarefas_campos WHERE ativo = 1');
    $idsValidos = array_map(static fn($c) => (int) $c['id'], $camposValidos);
    foreach ($campos as $campo_id => $valor) {
        $cid = (int) $campo_id;
        if (!in_array($cid, $idsValidos, true)) continue;
        $valor = is_array($valor) ? implode(', ', $valor) : (string) $valor;
        if (trim($valor) === '') continue;
        db_execute(
            'INSERT INTO tarefas_campos_valores (tarefa_id, campo_id, valor) VALUES (?, ?, ?)',
            ['iis', $id, $cid, $valor]
        );
    }

    echo json_encode(['ok' => true, 'id' => $id]);
} catch (\Throwable $e) {
    error_log('[admin-tarefas-salvar] ' . $e->getMessage());
    echo json_encode(['ok' => false, 'message' => 'Erro ao salvar a tarefa.']);
}
