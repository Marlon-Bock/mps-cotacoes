<?php
/**
 * GET /cotacao/residencial/{protocolo} — detalhe do Seguro Residencial (imobiliária).
 * Na API das seguradoras: pré-análise, propostas da MPS e apólice (mesmos
 * partials da Fiança). No fluxo manual: as ações genéricas (detalhe-acoes.php).
 */
$current_user = require_perm('perm_cotar', 'perm_seguro_residencial');

$protocolo = $params['protocolo'] ?? '';
if (!$protocolo) redirect('/cotacoes');

$imobiliaria_id = (int) ($current_user->imobiliaria_id ?? 0);

$cotacao = db_fetch_one(
    "SELECT sr.*, ua.username AS atendente_nome
     FROM seguro_residencial sr
     LEFT JOIN usuario ua ON ua.id = sr.atendente_id
     WHERE sr.protocolo = ? AND sr.imobiliaria_id = ?",
    ['si', $protocolo, $imobiliaria_id]
);
if (!$cotacao) redirect('/cotacoes');
// Operador comum só acessa a própria cotação (mesma regra de "Minhas Cotações").
if (empty($current_user->imob_admin) && (int) ($cotacao['usuario_id'] ?? 0) !== (int) $current_user->uid) {
    redirect('/cotacoes');
}

$status      = $cotacao['status'] ?? '—';
$chat_unread = cotacao_chat_unread($protocolo, 'imobiliaria', (int) $current_user->uid);

// Eventos internos (resultado de cada seguradora na API) são só da MPS.
$historico = [];
try {
    $historico = db_fetch_all("SELECT * FROM historico WHERE protocolo = ? AND interno = 0 ORDER BY data_evento ASC", ['s', $protocolo]);
} catch (Throwable) {}

// Fluxo manual: proposta em PDF enviada pela MPS.
$ultima_proposta = null;
try {
    $ultima_proposta = db_fetch_one("SELECT * FROM cotacoes_anexos_residencial WHERE protocolo = ? ORDER BY data_envio DESC LIMIT 1", ['s', $protocolo]);
} catch (Throwable) {}

// ── API das seguradoras ───────────────────────────────────────────────────────
require_once BASE_PATH . '/src/helpers/api.php';
$api = null;
if (($cotacao['origem'] ?? '') === 'api' && !empty($cotacao['api_enviado_em'])
    && in_array($status, [API_STATUS_FICHA, API_STATUS_APROVADO, 'Concluída'], true)) {
    $api = ['lote' => api_status_lote([(int) $cotacao['id']])[(int) $cotacao['id']]];
    $api['resumo'] = api_status_resumo_fluxo($api['lote'], false);
}
$ficha = $cotacao + ['produto' => 'residencial'];

// ── Timeline (fluxo manual) ───────────────────────────────────────────────────
$status_lower   = mb_strtolower($status);
$is_recusado    = str_contains($status_lower, 'recusad');
$is_desistencia = cotacao_status_desistencia($status);
$timeline_steps = [];
if (!$api) {
    $etapas = [['aguardando', 'Aguardando análise'], ['em_analise', 'Em Análise'], ['aprovado', 'Aprovado'], ['concluido', 'Concluído']];
    $atual  = match (true) {
        str_contains($status_lower, 'anális') || str_contains($status_lower, 'analise') => 1,
        str_contains($status_lower, 'aprovad')                                          => 2,
        str_contains($status_lower, 'concluíd') || str_contains($status_lower, 'concluid') => 3,
        default                                                                         => 0,
    };
    if ($is_desistencia || $is_recusado) {
        $timeline_steps = [
            ['key' => 'aguardando', 'label' => 'Aguardando análise', 'state' => 'is-done'],
            ['key' => 'fim', 'label' => $is_desistencia ? 'Desistência do cliente' : $status, 'state' => $is_desistencia ? 'is-desistencia' : 'is-recusado'],
        ];
    } else {
        foreach ($etapas as $i => [$k, $rotulo]) {
            $timeline_steps[] = ['key' => $k, 'label' => $rotulo, 'state' => $i < $atual ? 'is-done' : ($i === $atual ? 'is-current' : 'is-pending')];
        }
    }
}

// detalhe-acoes.php — sem pré-análise no Residencial.
$preAnalisePendente  = false;
$podeCompletar       = false;
$urlCompletar        = '';
$liberado            = 1;
$formulario_completo = 1;
$tipo_key            = 'residencial';

$page_title   = $protocolo;
$page_back    = ['url' => '/cotacoes', 'label' => 'Minhas Cotações', 'icon' => 'arrow-left'];
$extra_css    = ['dashboard.css', 'cotacao.css', 'cotacao-detalhe.css', 'cotacao-fianca-seguradoras.css', 'cotacao-fianca-fluxo.css'];
$extra_js     = !$api && $ultima_proposta && ($ultima_proposta['status_aprovacao'] ?? '') === 'Aguardando' ? ['cotacao-detalhe.js'] : [];
$data_atualizacao = date('d/m/Y H:i', strtotime($cotacao['data_atualizacao'] ?: $cotacao['data_cadastro']));

require __DIR__ . '/../_partials/detalhe-helpers.php';
require __DIR__ . '/../../partials/app-head.php';
require __DIR__ . '/../../partials/nav-imobiliaria.php';
?>
<div class="app-shell">
    <?php require __DIR__ . '/../../partials/sidebar.php'; ?>
    <div class="app-main">
        <?php require __DIR__ . '/../../partials/topbar.php'; ?>
        <main class="app-content">

            <div class="panel">
                <div class="panel-body detalhe-panel-header">
                    <div class="dph-top">
                        <span class="dph-tipo"><?= icon('house') ?> Seguro Residencial</span>
                        <?php $__st = api_status_exibicao((string) $status, (string) $protocolo, 'residencial', false); ?>
                        <span class="status-pill <?= e($__st['pill']) ?>"><?= e($__st['label']) ?></span>
                    </div>
                    <span class="dph-proto">Cotação #<?= e($protocolo) ?></span>
                    <div class="dph-bar">
                        <span class="dph-bar-item">
                            <?= icon('user') ?>
                            <span class="dph-bar-label">Segurado</span>
                            <span class="dph-bar-value"><?= e($cotacao['nome_completo'] ?? '—') ?></span>
                        </span>
                        <?php if (!empty($cotacao['atendente_nome'])): ?>
                        <span class="dph-bar-sep">&middot;</span>
                        <span class="dph-bar-item">
                            <?= icon('users') ?>
                            <span class="dph-bar-label">Atendente MPS</span>
                            <span class="dph-bar-value"><?= e($cotacao['atendente_nome']) ?></span>
                        </span>
                        <?php endif; ?>
                        <span class="dph-bar-sep">&middot;</span>
                        <span class="dph-bar-item">
                            <?= icon('clock') ?>
                            <span class="dph-bar-value"><?= e($data_atualizacao) ?></span>
                        </span>
                        <span class="dph-bar-spacer"></span>
                        <button type="button" class="btn btn-sm btn-chat-cotacao <?= $chat_unread > 0 ? 'btn-danger-outline' : 'btn-secondary' ?>" data-action="abrir-chat-cotacao" data-protocolo="<?= e($protocolo) ?>">
                            <?= icon('chat-circle') ?> Chat da cotação
                            <?php if ($chat_unread > 0): ?><span class="count-badge"><?= $chat_unread > 99 ? '99+' : e($chat_unread) ?></span><?php endif; ?>
                        </button>
                    </div>
                </div>
            </div>

            <div class="detalhe-layout cotacao-container">
                <div class="detalhe-left">

                    <?php if ($api) { require __DIR__ . '/../_partials/seguradoras/imobiliaria-propostas.php'; } ?>

                    <?php require __DIR__ . '/../_partials/residencial-dados.php'; ?>

                    <?php if (!empty($historico)): ?>
                    <div class="panel">
                        <div class="panel-header"><h3 class="panel-title"><?= icon('clock') ?> Histórico</h3></div>
                        <div class="panel-body">
                            <div class="status-timeline">
                                <?php foreach ($historico as $h): ?>
                                <div class="timeline-item is-done">
                                    <div class="timeline-dot"></div>
                                    <div class="timeline-content">
                                        <span class="timeline-label">
                                            <?php if ($h['status_anterior'] && $h['status_novo'] && $h['status_anterior'] !== $h['status_novo']): ?>
                                                <strong><?= e($h['status_anterior']) ?></strong> &rarr; <strong><?= e($h['status_novo']) ?></strong>
                                            <?php elseif ($h['status_novo']): ?>
                                                <strong><?= e($h['status_novo']) ?></strong>
                                            <?php endif; ?>
                                        </span>
                                        <span class="timeline-meta"><?= e($h['usuario_nome'] ?? 'MPS') ?> &middot; <?= date('d/m/Y H:i', strtotime($h['data_evento'])) ?></span>
                                        <?php if ($h['observacao']): ?>
                                        <span class="timeline-obs"><?= e($h['observacao']) ?></span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                </div><!-- /.detalhe-left -->

                <?php $usou_pre_analise = false; require __DIR__ . '/../_partials/detalhe-acoes.php'; ?>

            </div><!-- /.detalhe-layout -->

        </main>
    </div>
</div>

<?php if (!$api && $ultima_proposta && ($ultima_proposta['status_aprovacao'] ?? '') === 'Aguardando'): ?>
<script>window.cotacaoProtocolo = <?= json_encode($protocolo) ?>;</script>
<?php endif; ?>

<?php require __DIR__ . '/../../partials/app-foot.php'; ?>
