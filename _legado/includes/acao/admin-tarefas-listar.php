<?php
/**
 * GET /api/admin/tarefas/listar
 * Lista paginada e filtrável de tarefas. Retorna JSON
 * { html, total, page, per_page, total_pages, contadores }.
 *
 * Visibilidade: gestor vê tudo; não-gestor vê só tarefas dos setores a que
 * pertence (sem setor => nada).
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');

$ctx = tarefas_contexto_usuario((int) $current_user->uid);

// ── Filtros ──────────────────────────────────────────────────────────────────
$f_status     = trim($_GET['status'] ?? '');            // '' | 'atrasadas' | id numérico
$f_setor      = (int) ($_GET['setor'] ?? 0);
$f_responsavel= (int) ($_GET['responsavel'] ?? 0);
$f_etiqueta   = (int) ($_GET['etiqueta'] ?? 0);
$f_prioridade = trim($_GET['prioridade'] ?? '');
$f_busca      = trim($_GET['busca'] ?? '');
$f_data_de    = trim($_GET['data_de'] ?? '');
$f_data_ate   = trim($_GET['data_ate'] ?? '');
$sort_by      = trim($_GET['sort_by'] ?? '');   // '' | 'status' | 'prazo'
$sort_dir     = strtolower(trim($_GET['sort_dir'] ?? 'desc')) === 'asc' ? 'asc' : 'desc';
if (!in_array($sort_by, ['status', 'prazo'], true)) $sort_by = '';
if (!in_array($f_prioridade, ['baixa', 'media', 'alta'], true)) $f_prioridade = '';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_data_de))  $f_data_de  = '';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $f_data_ate)) $f_data_ate = '';

$page     = max(1, (int) ($_GET['page'] ?? 1));
$per_page = 20;
$offset   = ($page - 1) * $per_page;

// ── Cláusula de visibilidade (compartilhada por lista e contadores) ─────────────
$vis_sql   = '1=1';
$vis_tipos = '';
$vis_param = [];
if (!$ctx['gestor']) {
    if (empty($ctx['setores'])) {
        $vis_sql = '1=0';
    } else {
        $ph = implode(',', array_fill(0, count($ctx['setores']), '?'));
        $vis_sql   = "t.setor_id IN ({$ph})";
        $vis_tipos = str_repeat('i', count($ctx['setores']));
        $vis_param = $ctx['setores'];
    }
}

// ── WHERE dos filtros (sobre a base de visibilidade) ────────────────────────────
$where  = $vis_sql;
$tipos  = $vis_tipos;
$params = $vis_param;

$f_arquivadas = $f_status === 'arquivadas';
if ($f_arquivadas) {
    $where .= ' AND t.arquivada_em IS NOT NULL';
} else {
    $where .= ' AND t.arquivada_em IS NULL';
}

if ($f_status === 'atrasadas') {
    $where .= ' AND t.prazo IS NOT NULL AND t.prazo < CURDATE() AND t.concluida_em IS NULL';
} elseif ($f_status !== '' && $f_status !== 'arquivadas' && ctype_digit($f_status)) {
    $where   .= ' AND t.status_id = ?';
    $tipos   .= 'i';
    $params[] = (int) $f_status;
}
if ($f_setor > 0)      { $where .= ' AND t.setor_id = ?';    $tipos .= 'i'; $params[] = $f_setor; }
if ($f_prioridade !== ''){ $where .= ' AND t.prioridade = ?'; $tipos .= 's'; $params[] = $f_prioridade; }
if ($f_data_de !== '') { $where .= ' AND t.prazo >= ?';      $tipos .= 's'; $params[] = $f_data_de; }
if ($f_data_ate !== ''){ $where .= ' AND t.prazo <= ?';      $tipos .= 's'; $params[] = $f_data_ate; }
if ($f_busca !== '')   {
    $where   .= ' AND (t.titulo LIKE ? OR t.descricao LIKE ?)';
    $tipos   .= 'ss';
    $params[] = "%{$f_busca}%";
    $params[] = "%{$f_busca}%";
}
if ($f_responsavel > 0) {
    $where   .= ' AND EXISTS (SELECT 1 FROM tarefas_responsaveis tr WHERE tr.tarefa_id = t.id AND tr.usuario_id = ?)';
    $tipos   .= 'i';
    $params[] = $f_responsavel;
}
if ($f_etiqueta > 0) {
    $where   .= ' AND EXISTS (SELECT 1 FROM tarefas_etiquetas_vinculo tev WHERE tev.tarefa_id = t.id AND tev.etiqueta_id = ?)';
    $tipos   .= 'i';
    $params[] = $f_etiqueta;
}

$tarefas    = [];
$total      = 0;
$contadores = ['todos' => 0, 'atrasadas' => 0, 'arquivadas' => 0, 'status' => []];
$resp_map   = [];
$etq_map    = [];
$anexo_map  = [];
$sub_map    = [];   // tarefa_id => ['total' => n, 'feitos' => n]
$coment_map    = [];
$nao_lido_map  = [];   // tarefa_id => nº de comentários não lidos pelo usuário
$meu_interesse = [];   // tarefa_id => true quando ele é responsável ou foi mencionado

try {
    // Visão agrupada (estilo Monday): sem paginação, tudo carregado (teto de segurança).
    // Sem sort_by informado: ordem manual (drag-and-drop) via t.ordem. Com
    // sort_by, ordena por status/prazo (whitelist — nunca de $_GET direto).
    $order_sql = match ($sort_by) {
        'status' => 'st.nome ' . strtoupper($sort_dir) . ', t.ordem ASC',
        'prazo'  => '(t.prazo IS NULL), t.prazo ' . strtoupper($sort_dir) . ', t.ordem ASC',
        default  => 't.ordem ASC, t.id ASC',
    };

    $sql = "SELECT t.id, t.titulo, t.descricao, t.prioridade, t.prazo, t.concluida_em, t.atualizado_em, t.ordem,
                   t.setor_id, s.nome AS setor_nome, s.cor AS setor_cor,
                   t.status_id, st.nome AS status_nome, st.cor AS status_cor
              FROM tarefas t
              LEFT JOIN setores s ON s.id = t.setor_id
              LEFT JOIN tarefas_status st ON st.id = t.status_id
             WHERE {$where}
          ORDER BY {$order_sql}
             LIMIT 500";
    $tarefas = $tipos !== '' ? db_fetch_all($sql, [$tipos, ...$params]) : db_fetch_all($sql);
    $total = count($tarefas);

    // Escopo dos contadores = visibilidade + setor ativo (mas SEM o filtro de
    // status, senão os chips não mostrariam o total de cada status). Assim, num
    // setor os chips refletem só aquele setor; em "Todas", o geral.
    $cvSql   = $vis_sql;
    $cvTipos = $vis_tipos;
    $cvParam = $vis_param;
    if ($f_setor > 0) {
        $cvSql   .= ' AND t.setor_id = ?';
        $cvTipos .= 'i';
        $cvParam[] = $f_setor;
    }
    $contadores['todos'] = (int) (db_fetch_one(
        "SELECT COUNT(*) AS total FROM tarefas t WHERE {$cvSql} AND t.arquivada_em IS NULL",
        $cvTipos !== '' ? [$cvTipos, ...$cvParam] : []
    )['total'] ?? 0);
    $contadores['atrasadas'] = (int) (db_fetch_one(
        "SELECT COUNT(*) AS total FROM tarefas t WHERE {$cvSql} AND t.arquivada_em IS NULL AND t.prazo IS NOT NULL AND t.prazo < CURDATE() AND t.concluida_em IS NULL",
        $cvTipos !== '' ? [$cvTipos, ...$cvParam] : []
    )['total'] ?? 0);
    $contadores['arquivadas'] = (int) (db_fetch_one(
        "SELECT COUNT(*) AS total FROM tarefas t WHERE {$cvSql} AND t.arquivada_em IS NOT NULL",
        $cvTipos !== '' ? [$cvTipos, ...$cvParam] : []
    )['total'] ?? 0);
    $porStatus = db_fetch_all(
        "SELECT t.status_id, COUNT(*) AS total FROM tarefas t WHERE {$cvSql} AND t.arquivada_em IS NULL GROUP BY t.status_id",
        $cvTipos !== '' ? [$cvTipos, ...$cvParam] : []
    );
    foreach ($porStatus as $row) {
        if ($row['status_id'] !== null) {
            $contadores['status'][(string) (int) $row['status_id']] = (int) $row['total'];
        }
    }

    // Responsáveis e etiquetas das tarefas da página (batch).
    $ids = array_map(static fn($t) => (int) $t['id'], $tarefas);
    if (!empty($ids)) {
        $inPh = implode(',', array_fill(0, count($ids), '?'));
        $inTp = str_repeat('i', count($ids));

        $resp = db_fetch_all(
            "SELECT tr.tarefa_id, u.id AS usuario_id, u.username, u.avatar
               FROM tarefas_responsaveis tr
               JOIN usuario u ON u.id = tr.usuario_id
              WHERE tr.tarefa_id IN ({$inPh})
           ORDER BY u.username ASC",
            [$inTp, ...$ids]
        );
        foreach ($resp as $r) { $resp_map[(int) $r['tarefa_id']][] = $r; }

        $etq = db_fetch_all(
            "SELECT tev.tarefa_id, e.nome, e.cor
               FROM tarefas_etiquetas_vinculo tev
               JOIN tarefas_etiquetas e ON e.id = tev.etiqueta_id
              WHERE tev.tarefa_id IN ({$inPh})
           ORDER BY e.nome ASC",
            [$inTp, ...$ids]
        );
        foreach ($etq as $e) { $etq_map[(int) $e['tarefa_id']][] = $e; }

        $anx = db_fetch_all(
            "SELECT tarefa_id, COUNT(*) AS total FROM tarefas_anexos WHERE tarefa_id IN ({$inPh}) GROUP BY tarefa_id",
            [$inTp, ...$ids]
        );
        foreach ($anx as $a) { $anexo_map[(int) $a['tarefa_id']] = (int) $a['total']; }

        $cmt = db_fetch_all(
            "SELECT tarefa_id, COUNT(*) AS total FROM tarefas_comentarios WHERE tarefa_id IN ({$inPh}) GROUP BY tarefa_id",
            [$inTp, ...$ids]
        );
        foreach ($cmt as $c) { $coment_map[(int) $c['tarefa_id']] = (int) $c['total']; }

        $sub = db_fetch_all(
            "SELECT tarefa_id, COUNT(*) AS total, SUM(concluido = 'S') AS feitos
               FROM tarefas_subitens WHERE tarefa_id IN ({$inPh}) GROUP BY tarefa_id",
            [$inTp, ...$ids]
        );
        foreach ($sub as $s) { $sub_map[(int) $s['tarefa_id']] = ['total' => (int) $s['total'], 'feitos' => (int) $s['feitos']]; }

        // ── Não lidos: só valem para quem é responsável ou foi mencionado ─────
        $uid_atual = (int) $current_user->uid;

        foreach (db_fetch_all(
            "SELECT tarefa_id FROM tarefas_responsaveis WHERE usuario_id = ? AND tarefa_id IN ({$inPh})",
            ['i' . $inTp, $uid_atual, ...$ids]
        ) as $r) { $meu_interesse[(int) $r['tarefa_id']] = true; }

        foreach (db_fetch_all(
            "SELECT DISTINCT c.tarefa_id
               FROM tarefas_comentarios_mencoes m
               JOIN tarefas_comentarios c ON c.id = m.comentario_id
              WHERE m.usuario_id = ? AND c.tarefa_id IN ({$inPh})",
            ['i' . $inTp, $uid_atual, ...$ids]
        ) as $r) { $meu_interesse[(int) $r['tarefa_id']] = true; }

        // Comentários de outras pessoas posteriores à última leitura do usuário.
        foreach (db_fetch_all(
            "SELECT c.tarefa_id, COUNT(*) AS total
               FROM tarefas_comentarios c
               LEFT JOIN tarefas_leitura l ON l.tarefa_id = c.tarefa_id AND l.usuario_id = ?
              WHERE c.tarefa_id IN ({$inPh})
                AND c.usuario_id <> ?
                AND (l.lido_em IS NULL OR c.criado_em > l.lido_em)
              GROUP BY c.tarefa_id",
            ['i' . $inTp . 'i', $uid_atual, ...$ids, $uid_atual]
        ) as $r) { $nao_lido_map[(int) $r['tarefa_id']] = (int) $r['total']; }
    }
} catch (\Throwable $e) {
    error_log('[admin-tarefas-listar] ' . $e->getMessage());
}

$total_pages = 1;
$hoje = date('Y-m-d');

// Agrupamento estilo Monday: setor específico selecionado → agrupa por STATUS;
// em "Todas" → agrupa por SETOR (cada grupo é um setor).
$agrupar_por_status = $f_setor > 0;

$grupos_def = [];
try {
    $grupos_def = $agrupar_por_status
        ? db_fetch_all('SELECT id, nome, cor FROM tarefas_status ORDER BY ordem ASC, id ASC')
        : db_fetch_all('SELECT id, nome, cor FROM setores ORDER BY ordem ASC, nome ASC');
    if (!$agrupar_por_status && !$ctx['gestor']) {
        $grupos_def = array_values(array_filter($grupos_def, static fn($g) => in_array((int) $g['id'], $ctx['setores'], true)));
    }
} catch (\Throwable $e) {}

// Distribui as tarefas por grupo (chave = status_id ou setor_id; 0 = sem).
$por_grupo = [];
foreach ($tarefas as $t) {
    $chave = $agrupar_por_status ? (int) ($t['status_id'] ?? 0) : (int) ($t['setor_id'] ?? 0);
    $por_grupo[$chave][] = $t;
}

// Renderiza uma linha de tarefa (reutilizado em todos os grupos).
$render_row = static function (array $t) use ($resp_map, $etq_map, $anexo_map, $sub_map, $coment_map, $nao_lido_map, $meu_interesse, $hoje, $f_arquivadas) {
    $id           = (int) $t['id'];
    $atrasada     = $t['prazo'] && $t['prazo'] < $hoje && empty($t['concluida_em']);
    $prazoLabel   = $t['prazo'] ? date('d/m/Y', strtotime($t['prazo'])) : '—';
    $responsaveis = $resp_map[$id] ?? [];
    $etiquetas    = $etq_map[$id] ?? [];
    $n_anexos     = $anexo_map[$id] ?? 0;
    $n_coment     = $coment_map[$id] ?? 0;
    $sub          = $sub_map[$id] ?? ['total' => 0, 'feitos' => 0];
    // O número só aparece para quem é responsável ou foi mencionado, e some
    // assim que a pessoa abre a tarefa (a leitura é registrada no detalhe).
    $n_nao_lido   = !empty($meu_interesse[$id]) ? ($nao_lido_map[$id] ?? 0) : 0;
    ob_start(); ?>
<div class="mnd-row" data-id="<?= $id ?>">
    <div class="mnd-cell mnd-cell-drag">
        <span class="mnd-drag" title="Arraste para reordenar/mover"><?= icon('dots-six-vertical') ?></span>
    </div>
    <div class="mnd-cell mnd-cell-nome">
        <?php $semTitulo = trim((string) $t['titulo']) === ''; ?>
        <?php if ($atrasada): ?><span class="mnd-atraso" title="Prazo vencido e tarefa não concluída"><?= icon('warning') ?>Em atraso</span><?php endif; ?>
        <button type="button" class="mnd-titulo<?= $semTitulo ? ' is-vazio' : '' ?>" data-action="editar-tarefa" data-id="<?= $id ?>"><?= $semTitulo ? 'Sem título' : e($t['titulo']) ?></button>
    </div>
    <div class="mnd-cell mnd-cell-msg" data-msg="<?= $n_coment ?>" data-novo="<?= $n_nao_lido ?>" title="Abrir detalhes, comentários e histórico">
        <span class="mnd-msg<?= $n_coment > 0 ? ' has-msg' : '' ?>"><?= icon('chat-circle') ?><?php if ($n_nao_lido > 0): ?><span class="mnd-msg-novo"><?= $n_nao_lido ?></span><?php endif; ?></span>
    </div>
    <?php $resp_ids = array_map(static fn($r) => (int) $r['usuario_id'], $responsaveis); ?>
    <div class="mnd-cell mnd-cell-resp" data-setor-id="<?= (int) ($t['setor_id'] ?? 0) ?>" data-resp="<?= e(implode(',', $resp_ids)) ?>" title="Clique para atribuir responsáveis">
        <?php if (empty($responsaveis)): ?>
            <span class="mnd-resp-empty"><?= icon('user') ?></span>
        <?php else: ?>
            <span class="mnd-resp">
            <?php foreach (array_slice($responsaveis, 0, 4) as $r): ?>
                <span class="mnd-resp-av" title="<?= e($r['username']) ?>"><?= avatar_sm($r['avatar'] ?? null, $r['username']) ?></span>
            <?php endforeach; ?>
            <?php if (count($responsaveis) > 4): ?><span class="mnd-resp-mais">+<?= count($responsaveis) - 4 ?></span><?php endif; ?>
            </span>
        <?php endif; ?>
    </div>
    <div class="mnd-cell mnd-cell-status" data-status-id="<?= (int) ($t['status_id'] ?? 0) ?>">
        <?php if ($t['status_nome']): ?>
            <span class="tarefa-pill tarefa-pill-status mnd-status" data-cor="<?= e($t['status_cor'] ?: '#64748b') ?>"><?= e($t['status_nome']) ?></span>
        <?php else: ?>
            <span class="mnd-status-vazio">—</span>
        <?php endif; ?>
    </div>
    <div class="mnd-cell mnd-cell-prazo" data-prazo="<?= e($t['prazo'] ?? '') ?>" title="Clique para definir o prazo">
        <?php if ($t['prazo']): ?>
            <span class="mnd-prazo<?= $atrasada ? ' is-atrasada' : '' ?>"><?= icon('calendar-blank') ?><?= e($prazoLabel) ?></span>
        <?php else: ?><span class="mnd-prazo-vazio"><?= icon('calendar-blank') ?></span><?php endif; ?>
    </div>
    <div class="mnd-cell mnd-cell-arq" data-arq="<?= $n_anexos ?>" title="Arquivos da tarefa">
        <span class="mnd-arq<?= $n_anexos > 0 ? ' has-arq' : '' ?>"><?= icon('paperclip') ?><?php if ($n_anexos > 0): ?><span class="mnd-arq-n"><?= $n_anexos ?></span><?php endif; ?></span>
    </div>
    <div class="mnd-cell mnd-cell-sub" data-action="toggle-subitens" data-id="<?= $id ?>" data-total="<?= $sub['total'] ?>" data-feitos="<?= $sub['feitos'] ?>" title="Checklist da tarefa">
        <span class="mnd-sub<?= $sub['total'] > 0 ? ' has-sub' : '' ?>"><?= icon('list-checks') ?><?php if ($sub['total'] > 0): ?><span class="mnd-sub-n"><?= $sub['feitos'] ?>/<?= $sub['total'] ?></span><?php endif; ?></span>
    </div>
    <div class="mnd-cell mnd-cell-acoes">
        <?php if (!$f_arquivadas): ?>
        <button type="button" class="mnd-act" data-action="criar-abaixo" data-id="<?= $id ?>" title="Criar tarefa abaixo"><?= icon('plus') ?></button>
        <?php endif; ?>
        <button type="button" class="mnd-act" data-action="editar-tarefa" data-id="<?= $id ?>" title="Editar"><?= icon('note-pencil') ?></button>
        <button type="button" class="mnd-act" data-action="arquivar-tarefa" data-id="<?= $id ?>" data-arquivada="<?= $f_arquivadas ? 1 : 0 ?>" title="<?= $f_arquivadas ? 'Desarquivar' : 'Arquivar' ?>"><?= icon('eye-slash') ?></button>
        <button type="button" class="mnd-act" data-action="excluir-tarefa" data-id="<?= $id ?>" title="Excluir"><?= icon('trash') ?></button>
    </div>
</div>
<div class="mnd-sub-list is-hidden" data-sub-de="<?= $id ?>"></div>
<?php return ob_get_clean();
};

ob_start();

$temFiltro = $f_status !== '' || $f_responsavel > 0 || $f_etiqueta > 0
    || $f_prioridade !== '' || $f_busca !== '' || $f_data_de !== '' || $f_data_ate !== '';

// Em "Todas" (agrupado por setor) e sem filtro, exibe TODOS os setores — inclusive
// os sem tarefa — para permitir adicionar tarefa direto em qualquer um.
$mostrarVazios = !$agrupar_por_status && !$temFiltro;

$ordem = [];
foreach ($grupos_def as $g) {
    if (!empty($por_grupo[(int) $g['id']]) || $mostrarVazios) {
        $ordem[] = ['id' => (int) $g['id'], 'nome' => $g['nome'], 'cor' => $g['cor']];
    }
}
if (!empty($por_grupo[0])) {
    $ordem[] = ['id' => 0, 'nome' => $agrupar_por_status ? 'Sem status' : 'Sem setor', 'cor' => '#94a3b8'];
}

if (empty($ordem)):
?>
<div class="admin-cot-empty">Nenhuma tarefa encontrada<?= $temFiltro ? ' para os filtros aplicados' : '' ?>.</div>
<?php else:
    foreach ($ordem as $g):
        $rows       = $por_grupo[$g['id']] ?? [];
        $add_setor  = $agrupar_por_status ? (int) $f_setor : (int) $g['id'];
        $add_status = $agrupar_por_status ? (int) $g['id'] : 0;
?>
<div class="mnd-group" data-grupo="<?= (int) $g['id'] ?>" data-cor="<?= e($g['cor']) ?>">
    <div class="mnd-group-head">
        <button type="button" class="mnd-collapse" aria-label="Recolher/expandir"><?= icon('caret-down') ?></button>
        <span class="mnd-group-dot" data-cor="<?= e($g['cor']) ?>"></span>
        <span class="mnd-group-name"><?= e($g['nome']) ?></span>
        <span class="mnd-group-count"><?= count($rows) ?> <?= count($rows) === 1 ? 'tarefa' : 'tarefas' ?></span>
    </div>
    <div class="mnd-group-body">
        <div class="mnd-table">
            <div class="mnd-row mnd-head">
                <div class="mnd-cell mnd-cell-drag"></div>
                <div class="mnd-cell mnd-cell-nome">Tarefa</div>
                <div class="mnd-cell"></div>
                <div class="mnd-cell">Responsável</div>
                <div class="mnd-cell th-sortable" data-sort="status">Status<?= $sort_by === 'status' ? '<span class="sort-arrow">' . ($sort_dir === 'asc' ? '▲' : '▼') . '</span>' : '' ?></div>
                <div class="mnd-cell th-sortable" data-sort="prazo">Prazo<?= $sort_by === 'prazo' ? '<span class="sort-arrow">' . ($sort_dir === 'asc' ? '▲' : '▼') . '</span>' : '' ?></div>
                <div class="mnd-cell">Arquivos</div>
                <div class="mnd-cell">Checklist</div>
                <div class="mnd-cell mnd-cell-acoes"></div>
            </div>
            <?php if ($rows): ?>
            <?php foreach ($rows as $t) { echo $render_row($t); } ?>
            <?php else: ?>
            <div class="mnd-vazio-bar">Nenhuma tarefa no momento</div>
            <?php endif; ?>
            <div class="mnd-row mnd-add">
                <button type="button" class="mnd-add-btn" data-action="add-tarefa" data-setor="<?= $add_setor ?>" data-status="<?= $add_status ?>"><?= icon('plus') ?> Adicionar tarefa</button>
            </div>
        </div>
    </div>
</div>
<?php endforeach;
endif;

$html = ob_get_clean();
$per_page = max(1, $total);

echo json_encode([
    'html'        => $html,
    'agrupar_por' => $agrupar_por_status ? 'status' : 'setor',
    'total'       => $total,
    'page'        => $page,
    'per_page'    => $per_page,
    'total_pages' => $total_pages,
    'contadores'  => $contadores,
], JSON_UNESCAPED_UNICODE);
