<?php
/**
 * AJAX endpoint — GET /api/admin/gerenciador/tempos
 * Lista paginada "Tempo por cotação": tempo (desde a criação) até cada etapa
 * do fluxo — pré-análise, aguardando análise, em análise, biometria,
 * aprovado/recusado, envio da cotação, concluído — e o tempo total de
 * resolução (ou "em andamento" se ainda aberta). Título/Condominial não têm
 * pré-análise/aguardando/biometria/envio (mostram "—"). Mesmos filtros de
 * período/atendente/imobiliária/status do resto do Gerenciador.
 * Retorna JSON { html, total, page, per_page, total_pages }
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');

$data_de       = trim($_GET['data_de']       ?? '');
$data_ate      = trim($_GET['data_ate']      ?? '');
$atendente_id  = max(0, (int) ($_GET['atendente_id'] ?? 0));
$imobiliaria   = trim($_GET['imobiliaria']   ?? '');
$status_rapido = trim($_GET['status_rapido'] ?? '');
$page          = max(1, (int) ($_GET['page'] ?? 1));
$per_page      = 20;

if ($data_de  && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_de))  $data_de  = '';
if ($data_ate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_ate)) $data_ate = '';

$rows = admin_cotacoes_buscar_tempos();

// ── Filtros (mesmo padrão de admin-gerenciador-stats.php) ─────────────────────
if ($data_de !== '')      $rows = array_values(array_filter($rows, fn($r) => substr($r['criado_em'] ?? '', 0, 10) >= $data_de));
if ($data_ate !== '')     $rows = array_values(array_filter($rows, fn($r) => substr($r['criado_em'] ?? '', 0, 10) <= $data_ate));
if ($atendente_id > 0)    $rows = array_values(array_filter($rows, fn($r) => (int) ($r['atendente_id'] ?? 0) === $atendente_id));
if ($imobiliaria !== '')  $rows = array_values(array_filter($rows, fn($r) => mb_stripos($r['imob_nome'] ?? '', $imobiliaria) !== false));
$rows = admin_cotacoes_filtrar_status($rows, $status_rapido);

usort($rows, fn($a, $b) => strcmp((string) ($b['criado_em'] ?? ''), (string) ($a['criado_em'] ?? '')));

/** Tempo decorrido da criação até um timestamp de etapa (null se a etapa não foi alcançada). */
function gerenciador_tempo_ate(?string $criado_em, ?string $etapa): ?int
{
    if (!$criado_em || !$etapa) return null;
    $segundos = strtotime($etapa) - strtotime($criado_em);
    return $segundos >= 0 ? $segundos : null;
}

$total       = count($rows);
$total_pages = max(1, (int) ceil($total / $per_page));
if ($page > $total_pages) $page = $total_pages;
$offset      = ($page - 1) * $per_page;
$rows_pagina = array_slice($rows, $offset, $per_page);

ob_start();

if (empty($rows_pagina)): ?>
<div class="admin-cot-empty">Nenhuma cotação encontrada.</div>
<?php else:
    foreach ($rows_pagina as $row):
        $criado_em = $row['criado_em'] ?? null;

        $tempo_pre_analise = gerenciador_tempo_ate($criado_em, $row['data_pre_analise'] ?? null);
        $tempo_aguardando  = gerenciador_tempo_ate($criado_em, $row['data_status_aguardando'] ?? null);
        $tempo_em_analise  = gerenciador_tempo_ate($criado_em, $row['data_status_em_analise'] ?? null);
        $tempo_biometria   = gerenciador_tempo_ate($criado_em, $row['biometria_link_lido_em'] ?? null);
        $tempo_envio       = gerenciador_tempo_ate($criado_em, $row['data_envio_cotacao'] ?? null);
        $tempo_concluido   = gerenciador_tempo_ate($criado_em, $row['data_status_concluido'] ?? null);

        // Aprovado/Recusado — só um dos dois acontece por cotação.
        $tempo_aprovado = gerenciador_tempo_ate($criado_em, $row['data_status_aprovado'] ?? null);
        $tempo_recusado = gerenciador_tempo_ate($criado_em, $row['data_status_recusado'] ?? null);
        $decisao_label  = $tempo_aprovado !== null ? 'Aprovado' : ($tempo_recusado !== null ? 'Recusado' : null);
        $tempo_decisao  = $tempo_aprovado ?? $tempo_recusado;

        $data_fim  = $row['data_status_concluido'] ?? $row['data_status_aprovado'] ?? $row['data_status_recusado'] ?? null;
        $em_aberto = $data_fim === null;

        $tempo_total = null;
        if ($criado_em) {
            $tempo_total = ($data_fim ? strtotime($data_fim) : time()) - strtotime($criado_em);
        }

        $status_raw = (string) ($row['status'] ?? '');
        $label      = admin_status_label($status_raw);
        $pill       = status_pill_class($status_raw);

        $vazio = '<span class="admin-cot-cell-vazio">—</span>';
?>
<div class="admin-cot-row admin-cot-row-tempos">
    <div class="admin-cot-cell"><span class="proto-code"><?= e($row['protocolo'] ?? '') ?></span></div>
    <div class="admin-cot-cell">
        <?php if (!empty($row['atendente_nome'])): ?>
        <span class="atendente-chip"><?= avatar_sm($row['atendente_avatar'] ?? null, $row['atendente_nome']) ?> <?= e($row['atendente_nome']) ?></span>
        <?php else: ?>
        <span class="admin-cot-cell-vazio">Não atribuído</span>
        <?php endif; ?>
    </div>
    <div class="admin-cot-cell"><span class="status-pill <?= e($pill) ?>"><?= e($label) ?></span></div>
    <div class="admin-cot-cell"><?= $tempo_pre_analise !== null ? e(formatar_duracao($tempo_pre_analise)) : $vazio ?></div>
    <div class="admin-cot-cell"><?= $tempo_aguardando  !== null ? e(formatar_duracao($tempo_aguardando))  : $vazio ?></div>
    <div class="admin-cot-cell"><?= $tempo_em_analise  !== null ? e(formatar_duracao($tempo_em_analise))  : $vazio ?></div>
    <div class="admin-cot-cell"><?= $tempo_biometria   !== null ? e(formatar_duracao($tempo_biometria))   : $vazio ?></div>
    <div class="admin-cot-cell"><?= $tempo_decisao     !== null ? e($decisao_label . ': ' . formatar_duracao($tempo_decisao)) : $vazio ?></div>
    <div class="admin-cot-cell"><?= $tempo_envio       !== null ? e(formatar_duracao($tempo_envio))       : $vazio ?></div>
    <div class="admin-cot-cell"><?= $tempo_concluido   !== null ? e(formatar_duracao($tempo_concluido))   : $vazio ?></div>
    <div class="admin-cot-cell">
        <?php if ($tempo_total === null): ?>
        <?= $vazio ?>
        <?php elseif ($em_aberto): ?>
        <span class="status-pill status-pill-andamento">Em andamento (<?= e(formatar_duracao($tempo_total)) ?>)</span>
        <?php else: ?>
        <?= e(formatar_duracao($tempo_total)) ?>
        <?php endif; ?>
    </div>
    <div class="admin-cot-cell admin-cot-cell-acoes">
        <div class="table-actions">
            <button type="button" class="btn btn-secondary btn-sm" data-action="ver-timeline" data-protocolo="<?= e($row['protocolo'] ?? '') ?>" title="Ver linha do tempo">
                <?= icon('eye') ?>
            </button>
        </div>
    </div>
</div>
<?php endforeach;
endif;

$html = ob_get_clean();

echo json_encode([
    'html'        => $html,
    'total'       => $total,
    'page'        => $page,
    'per_page'    => $per_page,
    'total_pages' => $total_pages,
]);
