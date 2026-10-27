<?php
/**
 * Dashboard da Imobiliária — GET /
 * Widgets equivalentes aos do sistema antigo: KPIs, gráfico mensal,
 * últimas cotações e campanhas ativas — agora com dados reais.
 */

$current_user = require_auth();

if (($current_user->role ?? '') === 'admin') {
    redirect('/admin');
}

$imobiliaria_id = (int) ($current_user->imobiliaria_id ?? 0);
$ano_atual      = (int) date('Y');

$is_imob_admin = (bool) ($current_user->imob_admin ?? false);
$tem_perm      = fn(string $perm): bool => $is_imob_admin || ($current_user->{$perm} ?? 'N') === 'S';
$tem_cotar     = $tem_perm('perm_cotar');

// Produtos que o usuário tem permissão de ver — restringe os agregados do
// dashboard (KPIs, gráfico, últimas cotações) pra não somar/listar cotação
// de produto que ele não pode acessar.
$produtos_permitidos = $is_imob_admin ? null : array_values(array_filter([
    $tem_perm('perm_fianca')   ? 'fianca'      : null,
    $tem_perm('perm_auto')     ? 'auto'        : null,
    $tem_perm('perm_titulo')   ? 'titulo'      : null,
    $tem_perm('perm_condo')    ? 'condominial' : null,
    $tem_perm('perm_incendio') ? 'incendio'    : null,
    $tem_perm('perm_seguro_residencial') ? 'residencial' : null,
]));

// Operador comum só vê as próprias cotações (mesma regra de
// includes/acao/cotacoes-listar.php); admin da imobiliária vê de todos.
$usuario_id_filtro = $is_imob_admin ? null : (int) ($current_user->uid ?? 0);

$kpis = [
    'em_analise'  => count_seguros_by_status($imobiliaria_id, ['Em Analise', 'Em Análise'], $produtos_permitidos, $usuario_id_filtro),
    'aguardando'  => count_seguros_by_status($imobiliaria_id, ['Aguardando análise', 'Aguardando analise'], $produtos_permitidos, $usuario_id_filtro),
    'aprovadas'   => count_seguros_by_status($imobiliaria_id, ['Aprovado'], $produtos_permitidos, $usuario_id_filtro),
    'concluidas'  => count_seguros_by_status($imobiliaria_id, ['Concluída', 'Concluida'], $produtos_permitidos, $usuario_id_filtro),
    'recusadas'   => count_seguros_by_status($imobiliaria_id, statuses_recusados(), $produtos_permitidos, $usuario_id_filtro),
];

// Fianças ainda com as seguradoras ('Cotação API') entram nos cards pela
// etapa real nelas (cotando/em análise, aprovada, apólice emitida, recusada).
if ($produtos_permitidos === null || in_array('fianca', $produtos_permitidos, true)) {
    require_once BASE_PATH . '/src/helpers/api.php';
    $kpis_api = api_contagem_painel($imobiliaria_id, $usuario_id_filtro);
    $kpis['em_analise'] += $kpis_api['em_analise'];
    $kpis['aprovadas']  += $kpis_api['aprovados'];
    $kpis['concluidas'] += $kpis_api['concluidos'];
    $kpis['recusadas']  += $kpis_api['recusados'];
}

$meses_label   = ['Jan', 'Fev', 'Mar', 'Abr', 'Mai', 'Jun', 'Jul', 'Ago', 'Set', 'Out', 'Nov', 'Dez'];
$grafico_mensal = monthly_quotes_count($imobiliaria_id, $ano_atual, $produtos_permitidos, $usuario_id_filtro);
$grafico_max    = max(1, ...array_values($grafico_mensal));

$ultimas_cotacoes  = recent_quotes($imobiliaria_id, 4, $produtos_permitidos, $usuario_id_filtro);
$campanhas_ativas  = active_campaigns($imobiliaria_id, 10);
$notificacoes_recentes = recent_notifications((int) ($current_user->uid ?? 0), 'imob', 3);

// Popup de pendências — só no primeiro carregamento após o login, uma única vez.
$popup_pendencias = [];
if (!empty($_SESSION['popup_pendencias_imob'])) {
    unset($_SESSION['popup_pendencias_imob']);
    $popup_pendencias = imob_pendencias($imobiliaria_id, $produtos_permitidos, $usuario_id_filtro);
}

// Popup "Novidade! Seguro Incêndio" — permanente (não por login), controlado
// por users.novidade_incendio_visto_em (NULL = ainda não viu). Try/catch pra
// não quebrar em ambiente sem a migration 075 aplicada ainda.
$popup_novidade_incendio = false;
try {
    $ja_viu = db_fetch_one(
        'SELECT novidade_incendio_visto_em FROM users WHERE id = ?',
        ['i', (int) ($current_user->uid ?? 0)]
    );
    $popup_novidade_incendio = ($ja_viu['novidade_incendio_visto_em'] ?? null) === null;
} catch (Throwable $e) {
    $popup_novidade_incendio = false;
}

$page_title       = 'Dashboard';
$page_heading     = 'Olá, ' . ($current_user->nome ?? '') . '!';
$page_subheading  = 'Aqui está um resumo das suas cotações.';
$extra_css = ['dashboard.css', 'cotacoes.css'];
$extra_js  = ['dashboard.js'];

require __DIR__ . '/partials/app-head.php';
require __DIR__ . '/partials/nav-imobiliaria.php';
?>

<div class="app-shell">
    <?php require __DIR__ . '/partials/sidebar.php'; ?>

    <div class="app-main">
        <?php require __DIR__ . '/partials/topbar.php'; ?>

        <main class="app-content"<?= $popup_pendencias ? ' data-popup-pendencias="' . e(json_encode($popup_pendencias)) . '"' : '' ?><?= $popup_novidade_incendio ? ' data-popup-novidade-incendio="1" data-popup-novidade-incendio-perm="' . ($tem_cotar && $tem_perm('perm_incendio') ? '1' : '0') . '"' : '' ?>>

            <div class="kpi-grid kpi-grid-5">
                <div class="kpi-card">
                    <div class="kpi-card-info">
                        <span>Em análise</span>
                        <div class="kpi-card-value"><?= $kpis['em_analise'] ?></div>
                    </div>
                    <div class="kpi-card-icon is-neutral"><?= icon('clock') ?></div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-card-info">
                        <span>Aguardando análise</span>
                        <div class="kpi-card-value"><?= $kpis['aguardando'] ?></div>
                    </div>
                    <div class="kpi-card-icon is-neutral"><?= icon('clipboard-text') ?></div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-card-info">
                        <span>Aprovadas</span>
                        <div class="kpi-card-value"><?= $kpis['aprovadas'] ?></div>
                    </div>
                    <div class="kpi-card-icon is-neutral"><?= icon('check') ?></div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-card-info">
                        <span>Concluídas</span>
                        <div class="kpi-card-value"><?= $kpis['concluidas'] ?></div>
                    </div>
                    <div class="kpi-card-icon is-neutral"><?= icon('check') ?></div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-card-info">
                        <span>Recusadas</span>
                        <div class="kpi-card-value"><?= $kpis['recusadas'] ?></div>
                    </div>
                    <div class="kpi-card-icon is-neutral"><?= icon('x') ?></div>
                </div>
            </div>

            <div class="quick-actions">
                <?php if ($tem_cotar && $tem_perm('perm_fianca')): ?>
                <a class="quick-action-card" href="<?= e(url('/cotacao/fianca/residencial')) ?>">
                    <div class="quick-action-icon"><?= icon('house') ?></div>
                    <div>
                        <strong>Fiança Residencial</strong>
                        <span>Nova cotação</span>
                    </div>
                </a>
                <a class="quick-action-card" href="<?= e(url('/cotacao/fianca/comercial')) ?>">
                    <div class="quick-action-icon"><?= icon('buildings') ?></div>
                    <div>
                        <strong>Fiança Comercial</strong>
                        <span>Nova cotação</span>
                    </div>
                </a>
                <?php endif; ?>
                <?php if ($tem_cotar && $tem_perm('perm_auto')): ?>
                <a class="quick-action-card" href="<?= e(url('/cotacao/auto')) ?>">
                    <div class="quick-action-icon"><?= icon('car') ?></div>
                    <div>
                        <strong>Seguro Auto</strong>
                        <span>Nova cotação</span>
                    </div>
                </a>
                <?php endif; ?>
                <?php if ($tem_cotar && $tem_perm('perm_titulo')): ?>
                <a class="quick-action-card" href="<?= e(url('/cotacao/titulo')) ?>">
                    <div class="quick-action-icon"><?= icon('piggy-bank') ?></div>
                    <div>
                        <strong>Título de Capitalização</strong>
                        <span>Nova cotação</span>
                    </div>
                </a>
                <?php endif; ?>
                <?php if ($tem_cotar && $tem_perm('perm_condo')): ?>
                <a class="quick-action-card" href="<?= e(url('/cotacao/condominial')) ?>">
                    <div class="quick-action-icon"><?= icon('buildings') ?></div>
                    <div>
                        <strong>Seguro Condomínio</strong>
                        <span>Nova cotação</span>
                    </div>
                </a>
                <?php endif; ?>
                <?php if ($tem_cotar && $tem_perm('perm_incendio')): ?>
                <a class="quick-action-card" href="<?= e(url('/cotacao/incendio')) ?>">
                    <div class="quick-action-icon"><?= icon('fire') ?></div>
                    <div>
                        <strong>Seguro Incêndio</strong>
                        <span>Nova cotação</span>
                    </div>
                </a>
                <?php endif; ?>
                <?php if ($tem_cotar && $tem_perm('perm_seguro_residencial')): ?>
                <a class="quick-action-card" href="<?= e(url('/cotacao/residencial')) ?>">
                    <div class="quick-action-icon"><?= icon('house-line') ?></div>
                    <div>
                        <strong>Seguro Residencial</strong>
                        <span>Nova cotação</span>
                    </div>
                </a>
                <?php endif; ?>
            </div>

            <div class="dashboard-grid">
                <section class="panel cot-panel">
                    <div class="panel-header">
                        <h2>Últimas cotações</h2>
                        <a class="btn-link" href="<?= e(url('/cotacoes')) ?>">Ver todas</a>
                    </div>
                    <?php if (!$ultimas_cotacoes): ?>
                    <div class="panel-body">
                        <div class="empty-state">
                            <div class="empty-state-icon"><?= icon('clipboard-text') ?></div>
                            <p>Você ainda não enviou nenhuma cotação.</p>
                        </div>
                    </div>
                    <?php else: ?>
                    <?php foreach ($ultimas_cotacoes as $cot):
                        $tipo_key  = $cot['tipo'] ?? 'fianca';
                        $icon_name = match($tipo_key) {
                            'auto'        => 'car',
                            'condominial' => 'buildings',
                            'titulo'      => 'piggy-bank',
                            'incendio'    => 'fire',
                            'residencial' => 'house-line',
                            default       => 'house',
                        };
                        $tipo_ficha  = $cot['tipo_ficha'] ?? null;
                        $tipo_label  = match($tipo_key) {
                            'auto'        => 'Seguro Auto',
                            'condominial' => 'Seguro Condomínio',
                            'titulo'      => 'Título de Capitalização',
                            'incendio'    => 'Seguro Incêndio',
                            'residencial' => 'Seguro Residencial',
                            default       => $tipo_ficha === 'comercial' ? 'Fiança Comercial' : 'Fiança Residencial',
                        };
                        $data_fmt = !empty($cot['data_atualizacao']) ? date('d/m/Y', strtotime($cot['data_atualizacao'])) : '—';
                        $href     = '/cotacoes?protocolo=' . urlencode($cot['protocolo']);
                    ?>
                    <div class="cot-row">
                        <div class="cot-row-icon cot-row-icon--<?= e($tipo_key) ?>">
                            <?= icon($icon_name) ?>
                        </div>
                        <div class="cot-row-info">
                            <span class="cot-row-nome"><?= e($cot['pretendente'] ?? '—') ?></span>
                            <span class="cot-row-sub"><?= e($tipo_label) ?> · <?= e($cot['protocolo']) ?></span>
                        </div>
                        <?php require_once BASE_PATH . '/src/helpers/api.php'; $__st = api_status_exibicao((string) $cot['status'], (string) $cot['protocolo'], (string) $tipo_key, false); ?>
                        <span class="status-pill <?= e($__st['pill']) ?>"><?= e($__st['label']) ?></span>
                        <span class="cot-row-data"><?= e($data_fmt) ?></span>
                        <a href="<?= e(url($href)) ?>" class="btn btn-secondary btn-sm cot-row-btn"><?= icon('eye') ?> Ver</a>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </section>

                <section class="panel chart-panel">
                    <div class="panel-header">
                        <h2>Cotações por mês — <?= $ano_atual ?></h2>
                    </div>
                    <div class="panel-body">
                        <div class="chart-bars">
                            <?php foreach ($meses_label as $i => $label): ?>
                            <?php
                                $valor = $grafico_mensal[$i + 1];
                                $altura_pct = max(4, (int) round($valor / $grafico_max * 100));
                            ?>
                            <div class="chart-bar-col">
                                <span class="chart-bar-value"><?= $valor ?></span>
                                <div class="chart-bar-track">
                                    <div class="chart-bar" data-height="<?= $altura_pct ?>"></div>
                                </div>
                                <span class="chart-bar-label"><?= $label ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </section>
            </div>

            <div class="dashboard-grid">
                <section class="panel">
                    <div class="panel-header">
                        <h2>Campanhas ativas</h2>
                        <a class="btn-link" href="<?= e(url('/campanhas')) ?>">Ver todas</a>
                    </div>
                    <div class="panel-body">
                        <?php if (!$campanhas_ativas): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon"><?= icon('megaphone') ?></div>
                            <p>Nenhuma campanha ativa no momento.</p>
                        </div>
                        <?php else: ?>
                        <div class="campaign-grid">
                            <?php foreach ($campanhas_ativas as $campanha): ?>
                            <?php
                                // media_upload() já retorna o path pronto pra uso direto (URL completa do
                                // media server em produção, ou "/uploads/..." local em dev) — nunca precisa
                                // passar por media_url().
                                $imagem_url = $campanha['imagem'] ?? '';
                                $dias_restantes = max(0, (int) ceil((strtotime((string) $campanha['data_fim']) - time()) / 86400));
                            ?>
                            <button type="button" class="campaign-card campaign-card-clickable" data-abrir-campanha data-imagem="<?= e($imagem_url) ?>" data-titulo="<?= e($campanha['titulo']) ?>" data-observacoes="<?= e($campanha['observacoes'] ?? '') ?>">
                                <?php if ($imagem_url): ?>
                                <img src="<?= e($imagem_url) ?>" alt="" loading="lazy">
                                <?php endif; ?>
                                <div class="campaign-card-overlay">
                                    <strong><?= e($campanha['titulo']) ?></strong>
                                    <span class="campaign-card-badge">
                                        <?= $dias_restantes > 0 ? "Termina em {$dias_restantes} dia" . ($dias_restantes > 1 ? 's' : '') : 'Encerra hoje' ?>
                                    </span>
                                </div>
                            </button>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="panel">
                    <div class="panel-header">
                        <h2>Notificações</h2>
                        <a class="btn-link" href="<?= e(url('/notificacoes')) ?>">Ver todas</a>
                    </div>
                    <?php if (!$notificacoes_recentes): ?>
                    <div class="panel-body">
                        <div class="empty-state">
                            <div class="empty-state-icon"><?= icon('bell') ?></div>
                            <p>Nenhuma notificação por aqui.</p>
                        </div>
                    </div>
                    <?php else: ?>
                    <ul class="notif-list">
                        <?php foreach ($notificacoes_recentes as $notif):
                            $notif_tipo  = $notif['tipo'] ?? '';
                            $notif_icone = notif_tipo_icon($notif_tipo);
                            $notif_lida = (int) ($notif['lida'] ?? 0);
                        ?>
                        <li class="notif-item<?= $notif_lida ? ' is-read' : '' ?>">
                            <span class="notif-item-icon notif-item-icon--<?= e($notif_tipo ?: 'geral') ?>">
                                <?= icon($notif_icone) ?>
                            </span>
                            <div class="notif-item-body">
                                <div class="notif-item-header">
                                    <strong class="notif-item-titulo"><?= e($notif['titulo'] ?? '') ?></strong>
                                    <?php if (!$notif_lida): ?>
                                        <span class="badge badge-primary">Nova</span>
                                    <?php endif; ?>
                                </div>
                                <p class="notif-item-mensagem"><?= e($notif['mensagem'] ?? '') ?></p>
                                <span class="notif-item-data"><?= e(tempo_decorrido($notif['criado_em'] ?? '')) ?></span>
                            </div>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                    <?php endif; ?>
                </section>
            </div>

        </main>
    </div>
</div>

<div class="modal modal-campanha-popup" id="modal-campanha-detalhe" hidden>
    <div class="modal-backdrop" data-close="modal-campanha-detalhe"></div>
    <div class="modal-dialog">
        <button type="button" class="modal-close" data-close="modal-campanha-detalhe" aria-label="Fechar"><?= icon('x') ?></button>
        <button type="button" class="modal-campanha-popup-obs-btn" id="campanha-detalhe-obs-btn" data-open="modal-campanha-obs" hidden><?= icon('file-text') ?> Observações</button>
        <img id="campanha-detalhe-img" src="" alt="">
    </div>
</div>

<div class="modal modal-campanha-obs" id="modal-campanha-obs" hidden>
    <div class="modal-backdrop" data-close="modal-campanha-obs"></div>
    <div class="modal-dialog">
        <div class="modal-header">
            <h3><?= icon('file-text') ?> Observações</h3>
            <button type="button" class="modal-close" data-close="modal-campanha-obs" aria-label="Fechar"><?= icon('x') ?></button>
        </div>
        <div class="modal-body">
            <p id="campanha-detalhe-obs-texto"></p>
        </div>
    </div>
</div>

<?php require __DIR__ . '/partials/app-foot.php'; ?>
