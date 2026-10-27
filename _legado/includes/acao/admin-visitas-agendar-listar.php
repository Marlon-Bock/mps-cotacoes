<?php
/**
 * AJAX endpoint — GET /api/admin/visitas-agendar/listar
 * Lista os agendamentos de visita. Admin MPS vê de todos os corretores
 * (com filtro opcional); demais usuários só veem os próprios.
 * Retorna JSON { html, contadores }
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');

$is_mps_admin = !empty($current_user->mps_admin);

$status_filtro = $_GET['status'] ?? '';
if (!in_array($status_filtro, ['pendente', 'concluida'], true)) {
    $status_filtro = '';
}
$data_filtro = $_GET['data'] ?? '';
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_filtro)) {
    $data_filtro = '';
}
$corretor_filtro = $is_mps_admin ? (int) ($_GET['corretor_id'] ?? 0) : (int) $current_user->uid;

$where  = '1=1';
$tipos  = '';
$params = [];

// Casa tanto quem é o corretor principal quanto quem é corretor adicional
// (agendamentos_visita_corretores) — vale pro filtro manual do admin e pra
// visão forçada do não-admin (que só vê os próprios).
if (!$is_mps_admin || $corretor_filtro > 0) {
    $where   .= ' AND (av.para_usuario_id = ? OR EXISTS (SELECT 1 FROM agendamentos_visita_corretores c WHERE c.agendamento_id = av.id AND c.usuario_id = ?))';
    $tipos   .= 'ii';
    $params[] = $corretor_filtro;
    $params[] = $corretor_filtro;
}
if ($data_filtro !== '') {
    $where   .= ' AND av.data_visita = ?';
    $tipos   .= 's';
    $params[] = $data_filtro;
}

$rows = [];
try {
    $sql = "SELECT av.*, u.username AS corretor_nome, u.avatar AS corretor_avatar, r.nome AS regiao_nome
            FROM agendamentos_visita av
            JOIN usuario u ON u.id = av.para_usuario_id
            LEFT JOIN regioes r ON r.id = av.regiao_id
            WHERE {$where}
            ORDER BY av.criado_em DESC, av.id DESC";
    // mysqli não aceita bind_param() com zero variáveis — só passa o array de
    // parâmetros se houver algum filtro de fato aplicado.
    $rows = $tipos !== '' ? db_fetch_all($sql, [$tipos, ...$params]) : db_fetch_all($sql);
} catch (Throwable $e) {
    error_log('[visitas-agendar-listar] ' . $e->getMessage());
}

$contadores = ['todos' => count($rows), 'pendente' => 0, 'concluida' => 0];
foreach ($rows as $r) {
    if (isset($contadores[$r['status']])) $contadores[$r['status']]++;
}

if ($status_filtro !== '') {
    $rows = array_values(array_filter($rows, fn($r) => $r['status'] === $status_filtro));
}

// Corretores adicionais por agendamento (batch, mesmo padrão de $resp_map
// em admin-tarefas-listar.php).
$extras_map = [];
$ag_ids = array_map(static fn($r) => (int) $r['id'], $rows);
if (!empty($ag_ids)) {
    $inPh = implode(',', array_fill(0, count($ag_ids), '?'));
    $inTp = str_repeat('i', count($ag_ids));
    try {
        $extras = db_fetch_all(
            "SELECT c.agendamento_id, u.id AS usuario_id, u.username, u.avatar
               FROM agendamentos_visita_corretores c
               JOIN usuario u ON u.id = c.usuario_id
              WHERE c.agendamento_id IN ({$inPh})
           ORDER BY u.username ASC",
            [$inTp, ...$ag_ids]
        );
        foreach ($extras as $ex) { $extras_map[(int) $ex['agendamento_id']][] = $ex; }
    } catch (Throwable $e) {
        error_log('[visitas-agendar-listar] extras: ' . $e->getMessage());
    }
}

ob_start();

if (empty($rows)): ?>
<div class="admin-cot-empty">Nenhum agendamento encontrado.</div>
<?php else: ?>
<div class="admin-cot-grid-wrap">
    <div class="admin-cot-row admin-cot-row-agendar admin-cot-head">
        <div class="admin-cot-cell">Data</div>
        <div class="admin-cot-cell">Horário</div>
        <div class="admin-cot-cell admin-cot-cell-nome">Título</div>
        <div class="admin-cot-cell">Região</div>
        <div class="admin-cot-cell">Imobiliária</div>
        <div class="admin-cot-cell">Corretor</div>
        <div class="admin-cot-cell">Status</div>
        <div class="admin-cot-cell">Ações</div>
    </div>
    <?php foreach ($rows as $r):
        $concluida = $r['status'] === 'concluida';
        $endereco_completo = trim((string) ($r['local_visita'] ?? ''));
        $maps_url = $endereco_completo ? 'https://www.google.com/maps/search/?api=1&query=' . urlencode($endereco_completo) : null;
        $extras_do_item = $extras_map[(int) $r['id']] ?? [];
        $itemParaJson = $r;
        $itemParaJson['corretores_extra'] = array_map(static fn($ex) => [
            'id'       => (int) $ex['usuario_id'],
            'username' => $ex['username'],
            'avatar'   => $ex['avatar'],
        ], $extras_do_item);
        $itemJson = json_encode($itemParaJson, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT);

        // Avatares empilhados: principal primeiro, depois os extras (mesmo
        // padrão de .mnd-resp-av/.mnd-resp-mais da coluna "Responsável" de tarefas).
        $avatares_visita = array_merge(
            [['username' => $r['corretor_nome'], 'avatar' => $r['corretor_avatar'] ?? null]],
            array_map(static fn($ex) => ['username' => $ex['username'], 'avatar' => $ex['avatar']], $extras_do_item)
        );
    ?>
    <div class="admin-cot-row admin-cot-row-agendar" data-item="<?= e($itemJson) ?>">
        <div class="admin-cot-cell"><?= e(date_to_br($r['data_visita'])) ?></div>
        <div class="admin-cot-cell"><?= e(substr($r['hora_inicio'], 0, 5)) ?>–<?= e(substr($r['hora_fim'], 0, 5)) ?></div>
        <div class="admin-cot-cell admin-cot-cell-nome"><?= e($r['titulo']) ?></div>
        <div class="admin-cot-cell"><?= $r['regiao_nome'] ? e($r['regiao_nome']) : '<span class="admin-cot-cell-vazio">—</span>' ?></div>
        <div class="admin-cot-cell"><?= e($r['imobiliaria_nome'] ?: '—') ?></div>
        <div class="admin-cot-cell">
            <span class="mnd-resp">
                <?php foreach (array_slice($avatares_visita, 0, 4) as $av): ?>
                <span class="mnd-resp-av" title="<?= e($av['username']) ?>"><?= avatar_sm($av['avatar'] ?? null, $av['username']) ?></span>
                <?php endforeach; ?>
            </span>
            <?php if (count($avatares_visita) > 4): ?><span class="mnd-resp-mais">+<?= count($avatares_visita) - 4 ?></span><?php endif; ?>
        </div>
        <div class="admin-cot-cell">
            <span class="status-pill <?= $concluida ? 'status-pill-aprovado' : 'status-pill-pendente' ?>">
                <?= $concluida ? 'Concluída' : 'Pendente' ?>
            </span>
        </div>
        <div class="admin-cot-cell admin-cot-cell-acoes">
            <div class="table-actions">
                <?php if ($concluida): ?>
                    <a href="<?= e(url('/admin/visitas/relatorio')) ?>" class="btn btn-secondary btn-sm" title="Ver relatório"><?= icon('clipboard-text') ?></a>
                <?php else: ?>
                    <a href="<?= e(BASE_URI) ?>/admin/visitas/relatorio?ag_id=<?= (int) $r['id'] ?>" class="btn btn-primary btn-sm" title="Registrar visita"><?= icon('note-pencil') ?></a>
                <?php endif; ?>
                <?php if (!empty($r['google_event_link'])): ?>
                    <a href="<?= e($r['google_event_link']) ?>" target="_blank" rel="noopener" class="btn btn-secondary btn-sm" title="Abrir no Google Agenda"><?= icon('calendar-blank') ?></a>
                <?php endif; ?>
                <?php if (!empty($r['link_reuniao'])): ?>
                    <a href="<?= e($r['link_reuniao']) ?>" target="_blank" rel="noopener" class="btn btn-primary btn-sm" title="Acessar a reunião online"><?= icon('monitor') ?></a>
                <?php endif; ?>
                <?php if ($maps_url): ?>
                    <a href="<?= e($maps_url) ?>" target="_blank" rel="noopener" class="btn btn-secondary btn-sm" title="Ver no mapa"><?= icon('map-pin') ?></a>
                <?php endif; ?>
                <?php if (trim((string) ($r['descricao'] ?? '')) !== ''): ?>
                    <button type="button" class="btn btn-secondary btn-sm" data-action="agendamento-descricao" title="Ver descrição"><?= icon('file-text') ?></button>
                <?php endif; ?>
                <button type="button" class="btn btn-secondary btn-sm" data-action="agendamento-editar" title="Editar"><?= icon('note-pencil') ?></button>
                <button type="button" class="btn btn-danger-outline btn-sm" data-action="agendamento-excluir" data-id="<?= (int) $r['id'] ?>" title="Excluir"><?= icon('x') ?></button>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif;

$html = ob_get_clean();
echo json_encode(['html' => $html, 'contadores' => $contadores]);
