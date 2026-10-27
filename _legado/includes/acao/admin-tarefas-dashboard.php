<?php
/**
 * GET /api/admin/tarefas/dashboard
 * KPIs e panoramas do módulo de Tarefas (gestor-only). Retorna JSON com
 * totais gerais + breakdown por status, por setor e por responsável.
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');

$ctx = tarefas_contexto_usuario((int) $current_user->uid);
if (!$ctx['gestor']) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Apenas gestores acessam o dashboard.']);
    exit;
}

$kpis        = ['total' => 0, 'pendentes' => 0, 'concluidas' => 0, 'atrasadas' => 0];
$por_status  = [];
$por_setor   = [];
$por_resp    = [];
$prazos      = ['vence_hoje' => 0, 'em_1_3' => 0, 'em_4_7' => 0, 'lista' => []];
$sem_inter   = ['b_3_7' => 0, 'b_8_14' => 0, 'b_15' => 0, 'lista' => []];

try {
    $kpis['total'] = (int) (db_fetch_one('SELECT COUNT(*) AS n FROM tarefas')['n'] ?? 0);
    $kpis['concluidas'] = (int) (db_fetch_one('SELECT COUNT(*) AS n FROM tarefas WHERE concluida_em IS NOT NULL')['n'] ?? 0);
    $kpis['pendentes']  = $kpis['total'] - $kpis['concluidas'];
    $kpis['atrasadas']  = (int) (db_fetch_one(
        'SELECT COUNT(*) AS n FROM tarefas WHERE prazo IS NOT NULL AND prazo < CURDATE() AND concluida_em IS NULL'
    )['n'] ?? 0);

    // Distribuição por status (donut).
    $por_status = db_fetch_all(
        "SELECT st.id, st.nome, st.cor, COUNT(t.id) AS total
           FROM tarefas_status st
           LEFT JOIN tarefas t ON t.status_id = st.id
       GROUP BY st.id, st.nome, st.cor
       ORDER BY st.ordem ASC, st.id ASC"
    );

    // Por setor detalhado: concluídas / em aberto / atrasadas (barra empilhada).
    $por_setor = db_fetch_all(
        "SELECT s.id, s.nome, s.cor,
                COUNT(t.id) AS total,
                SUM(t.concluida_em IS NOT NULL) AS concluidas,
                SUM(t.concluida_em IS NULL AND t.prazo IS NOT NULL AND t.prazo < CURDATE()) AS atrasadas,
                SUM(t.concluida_em IS NULL AND (t.prazo IS NULL OR t.prazo >= CURDATE())) AS abertas
           FROM setores s
           LEFT JOIN tarefas t ON t.setor_id = s.id
       GROUP BY s.id, s.nome, s.cor
       ORDER BY total DESC, s.nome ASC"
    );

    $por_resp = db_fetch_all(
        "SELECT u.id, u.username, u.avatar, COUNT(tr.tarefa_id) AS total
           FROM tarefas_responsaveis tr
           JOIN usuario u ON u.id = tr.usuario_id
       GROUP BY u.id, u.username, u.avatar
       ORDER BY total DESC, u.username ASC
          LIMIT 8"
    );

    // Prazos próximos (tarefas em aberto).
    $prazos['vence_hoje'] = (int) (db_fetch_one("SELECT COUNT(*) n FROM tarefas WHERE concluida_em IS NULL AND prazo = CURDATE()")['n'] ?? 0);
    $prazos['em_1_3']     = (int) (db_fetch_one("SELECT COUNT(*) n FROM tarefas WHERE concluida_em IS NULL AND prazo BETWEEN CURDATE() + INTERVAL 1 DAY AND CURDATE() + INTERVAL 3 DAY")['n'] ?? 0);
    $prazos['em_4_7']     = (int) (db_fetch_one("SELECT COUNT(*) n FROM tarefas WHERE concluida_em IS NULL AND prazo BETWEEN CURDATE() + INTERVAL 4 DAY AND CURDATE() + INTERVAL 7 DAY")['n'] ?? 0);
    $prazos['lista'] = db_fetch_all(
        "SELECT t.id, t.titulo, s.nome AS setor, s.cor AS setor_cor, t.prazo,
                DATEDIFF(t.prazo, CURDATE()) AS dias
           FROM tarefas t LEFT JOIN setores s ON s.id = t.setor_id
          WHERE t.concluida_em IS NULL AND t.prazo IS NOT NULL
            AND t.prazo BETWEEN CURDATE() AND CURDATE() + INTERVAL 7 DAY
       ORDER BY t.prazo ASC LIMIT 8"
    );

    // Tempo sem interação (última entre criação, último comentário e último evento).
    $sem_inter['lista'] = db_fetch_all(
        "SELECT t.id, t.titulo, s.nome AS setor, s.cor AS setor_cor,
                DATEDIFF(NOW(), GREATEST(
                    t.criado_em,
                    COALESCE((SELECT MAX(c.criado_em) FROM tarefas_comentarios c WHERE c.tarefa_id = t.id), t.criado_em),
                    COALESCE((SELECT MAX(h.criado_em) FROM tarefas_historico h WHERE h.tarefa_id = t.id), t.criado_em)
                )) AS dias
           FROM tarefas t LEFT JOIN setores s ON s.id = t.setor_id
          WHERE t.concluida_em IS NULL
       ORDER BY dias DESC LIMIT 8"
    );
    foreach ($sem_inter['lista'] as $r) {
        $d = (int) $r['dias'];
        if ($d >= 15) $sem_inter['b_15']++;
        elseif ($d >= 8) $sem_inter['b_8_14']++;
        elseif ($d >= 3) $sem_inter['b_3_7']++;
    }
} catch (\Throwable $e) {
    error_log('[admin-tarefas-dashboard] ' . $e->getMessage());
}

$norm = static function (array $rows): array {
    return array_map(static fn($r) => [
        'id'         => (int) ($r['id'] ?? 0),
        'nome'       => $r['nome'] ?? ($r['username'] ?? ''),
        'cor'        => $r['cor'] ?? null,
        'avatar'     => $r['avatar'] ?? null,
        'total'      => (int) ($r['total'] ?? 0),
        'concluidas' => (int) ($r['concluidas'] ?? 0),
        'atrasadas'  => (int) ($r['atrasadas'] ?? 0),
        'abertas'    => (int) ($r['abertas'] ?? 0),
    ], $rows);
};

$norm_tarefas = static function (array $rows): array {
    return array_map(static fn($r) => [
        'id'        => (int) $r['id'],
        'titulo'    => trim((string) $r['titulo']) !== '' ? $r['titulo'] : 'Sem título',
        'setor'     => $r['setor'] ?? 'Sem setor',
        'setor_cor' => $r['setor_cor'] ?: '#94a3b8',
        'prazo'     => isset($r['prazo']) && $r['prazo'] ? date('d/m/Y', strtotime($r['prazo'])) : '',
        'dias'      => (int) ($r['dias'] ?? 0),
    ], $rows);
};

echo json_encode([
    'ok'         => true,
    'kpis'       => $kpis,
    'por_status' => $norm($por_status),
    'por_setor'  => $norm($por_setor),
    'por_resp'   => $norm($por_resp),
    'prazos'     => [
        'vence_hoje' => $prazos['vence_hoje'],
        'em_1_3'     => $prazos['em_1_3'],
        'em_4_7'     => $prazos['em_4_7'],
        'lista'      => $norm_tarefas($prazos['lista']),
    ],
    'sem_interacao' => [
        'b_3_7'  => $sem_inter['b_3_7'],
        'b_8_14' => $sem_inter['b_8_14'],
        'b_15'   => $sem_inter['b_15'],
        'lista'  => $norm_tarefas($sem_inter['lista']),
    ],
], JSON_UNESCAPED_UNICODE);
