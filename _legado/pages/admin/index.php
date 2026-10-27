<?php
/**
 * Dashboard Admin (MPS) — GET /admin
 * Equivalente ao painel do sistema antigo: KPIs, gráfico mensal,
 * panorama de status, fila de pendentes e concluídos por tipo.
 */

$current_user = require_admin();

// Popup de "cotações em análise sob minha responsabilidade": a saudação com
// nome só aparece uma vez, no primeiro dashboard depois do login. Depois
// disso, o JS (admin-popup-cotacoes-em-analise.php) cuida de repetir o
// popup a cada 30 minutos com uma mensagem diferente, enquanto a aba
// continuar aberta — ver public/assets/js/dashboard.js.
$mostrar_popup_em_analise = !empty($_SESSION['popup_cotacoes_em_analise']);
unset($_SESSION['popup_cotacoes_em_analise']);

$minhas_em_analise = 0;
if ($mostrar_popup_em_analise) {
    db_execute('UPDATE usuario SET popup_cotacoes_visto_em = NOW() WHERE id = ?', ['i', (int) $current_user->uid]);
    $minhas_em_analise = safe_count(
        "SELECT COUNT(*) AS total FROM (
            SELECT id FROM seguro_fianca WHERE status IN ('Em Analise','Em Análise') AND atendente_id = ?
            UNION ALL
            SELECT id FROM seguro_auto   WHERE status IN ('Em Analise','Em Análise') AND atendente_id = ?
         ) t",
        ['ii', (int) $current_user->uid, (int) $current_user->uid]
    ) + safe_count(
        "SELECT COUNT(*) AS total FROM (
            SELECT id FROM titulo_capitalizacao WHERE status IN ('Em Analise','Em Análise') AND atendente_id = ?
            UNION ALL
            SELECT id FROM seguro_condominial   WHERE status IN ('Em Analise','Em Análise') AND atendente_id = ?
         ) t",
        ['ii', (int) $current_user->uid, (int) $current_user->uid]
    );
}

$ano_atual = (int) date('Y');
$mes_nome  = [
    1 => 'Janeiro', 2 => 'Fevereiro', 3 => 'Março',     4 => 'Abril',
    5 => 'Maio',    6 => 'Junho',     7 => 'Julho',      8 => 'Agosto',
    9 => 'Setembro',10 => 'Outubro', 11 => 'Novembro',  12 => 'Dezembro',
][(int) date('m')];

$inicio_mes = date('Y-m-01');

// Operador MPS restrito a só alguns produtos não vê números dos demais aqui
// — mesma regra de "Todas as Cotações" (admin_produtos_permitidos()).
$produtos_permitidos = admin_produtos_permitidos($current_user);

// Tabelas por produto, separadas pela coluna de data que cada uma usa:
// fiança/auto/incêndio têm data_atualizacao própria; título/condomínio só
// têm criado_em (sem coluna de atualização) — por isso dois grupos.
$tabelas_data_atualizacao = array_filter([
    'fianca'   => ['tabela' => 'seguro_fianca',   'data_col' => 'data_atualizacao'],
    'auto'     => ['tabela' => 'seguro_auto',     'data_col' => 'data_atualizacao'],
    'incendio' => ['tabela' => 'seguro_incendio', 'data_col' => 'data_atualizacao'],
    'residencial' => ['tabela' => 'seguro_residencial', 'data_col' => 'data_atualizacao'],
]);
$tabelas_criado_em = array_filter([
    'titulo'      => ['tabela' => 'titulo_capitalizacao', 'data_col' => 'criado_em'],
    'condominial' => ['tabela' => 'seguro_condominial',   'data_col' => 'criado_em'],
]);
$tabelas_todas = $tabelas_data_atualizacao + $tabelas_criado_em;

// KPIs — uma régua só pro atendimento manual e a API das seguradoras (a MPS
// está no meio dos dois): onde cada cotação está hoje (admin_cotacao_etapa_painel)
// + concluídas/recusadas no mês. Mesmas linhas (e permissões por produto) da
// "Todas as Cotações", pra o número bater com o filtro do link.
$linhas_painel = admin_cotacoes_buscar_todas();
if ($produtos_permitidos !== null) {
    $linhas_painel = array_values(array_filter($linhas_painel, fn($r) => in_array($r['origem'] ?? '', $produtos_permitidos, true)));
}
$etapas_painel = array_count_values(array_filter(array_map('admin_cotacao_etapa_painel', admin_cotacoes_marcar_api($linhas_painel))));

$kpis = [
    'acao_mps'       => $etapas_painel['acao_mps'] ?? 0,
    'andamento'      => $etapas_painel['andamento'] ?? 0,
    'com_cliente'    => $etapas_painel['com_cliente'] ?? 0,
    'concluidas_mes' => admin_kpi_total($tabelas_todas, ['Concluída', 'Concluida', 'Finalizado'], $produtos_permitidos, $inicio_mes),
    'recusadas_mes'  => admin_kpi_total($tabelas_todas, ['Recusado', 'Recusada', 'Recusado pelo cliente'], $produtos_permitidos, $inicio_mes),
];

$link_cotacoes = static fn (string $status, bool $mes = false) =>
    e(url('/admin/cotacoes') . '?status=' . $status . ($mes ? '&data_de=' . $inicio_mes : ''));

$meses_label    = ['Jan','Fev','Mar','Abr','Mai','Jun','Jul','Ago','Set','Out','Nov','Dez'];
$grafico_mensal = admin_monthly_quotes_count($ano_atual, $produtos_permitidos);
$grafico_max    = max(1, ...array_values($grafico_mensal));

$panorama       = admin_status_panorama($produtos_permitidos);
$concluidos     = admin_concluidos_por_tipo($produtos_permitidos);
$fila_pendentes = admin_pending_queue(8, $produtos_permitidos);

$page_title      = 'Dashboard';
$page_heading    = 'Painel Administrativo';
$page_subheading = 'Visão geral das operações da MPS.';
$extra_css = ['dashboard.css', 'cotacoes.css'];
$extra_js  = ['dashboard.js'];

require __DIR__ . '/../partials/app-head.php';
require __DIR__ . '/../partials/nav-admin.php';
?>

<div class="app-shell">
    <?php require __DIR__ . '/../partials/sidebar.php'; ?>

    <div class="app-main">
        <?php require __DIR__ . '/../partials/topbar.php'; ?>

        <main class="app-content" data-corretor-id="<?= (int) $current_user->uid ?>"<?= $mostrar_popup_em_analise ? ' data-popup-em-analise="' . (int) $minhas_em_analise . '" data-popup-nome="' . e($current_user->nome) . '"' : '' ?>>

            <!-- KPIs — atendimento manual e API juntos: onde as cotações estão
                 hoje e o resultado do mês. Cada card abre a listagem filtrada. -->
            <div class="kpi-grid kpi-grid-5">
                <a href="<?= $link_cotacoes('acao_mps') ?>" class="kpi-card">
                    <div class="kpi-card-info">
                        <span>Aguardando a MPS</span>
                        <div class="kpi-card-value"><?= $kpis['acao_mps'] ?></div>
                        <small class="kpi-card-detalhe">Análise, cotação ou envio de documentos</small>
                    </div>
                    <div class="kpi-card-icon is-neutral"><?= icon('clock') ?></div>
                </a>
                <a href="<?= $link_cotacoes('andamento') ?>" class="kpi-card">
                    <div class="kpi-card-info">
                        <span>Em andamento</span>
                        <div class="kpi-card-value"><?= $kpis['andamento'] ?></div>
                        <small class="kpi-card-detalhe">Em análise, nas seguradoras ou em emissão</small>
                    </div>
                    <div class="kpi-card-icon is-neutral"><?= icon('magnifying-glass') ?></div>
                </a>
                <a href="<?= $link_cotacoes('com_cliente') ?>" class="kpi-card">
                    <div class="kpi-card-info">
                        <span>Com o cliente</span>
                        <div class="kpi-card-value"><?= $kpis['com_cliente'] ?></div>
                        <small class="kpi-card-detalhe">Propostas ou fichas aguardando o cliente</small>
                    </div>
                    <div class="kpi-card-icon is-neutral"><?= icon('paper-plane-tilt') ?></div>
                </a>
                <a href="<?= $link_cotacoes('concluidos', true) ?>" class="kpi-card">
                    <div class="kpi-card-info">
                        <span>Concluídas em <?= e($mes_nome) ?></span>
                        <div class="kpi-card-value"><?= $kpis['concluidas_mes'] ?></div>
                        <small class="kpi-card-detalhe">Cotações fechadas no mês</small>
                    </div>
                    <div class="kpi-card-icon is-neutral"><?= icon('check-circle') ?></div>
                </a>
                <a href="<?= $link_cotacoes('reprovados', true) ?>" class="kpi-card">
                    <div class="kpi-card-info">
                        <span>Recusadas em <?= e($mes_nome) ?></span>
                        <div class="kpi-card-value"><?= $kpis['recusadas_mes'] ?></div>
                        <small class="kpi-card-detalhe">Pela MPS, seguradoras ou cliente</small>
                    </div>
                    <div class="kpi-card-icon is-neutral"><?= icon('x') ?></div>
                </a>
            </div>

            <!-- Fila de pendentes (prioridade) + Panorama de status -->
            <div class="dashboard-grid">
                <section class="panel cot-panel">
                    <div class="panel-header">
                        <h2>Fila de atendimento</h2>
                        <a class="btn-link" href="<?= e(url('/admin/cotacoes')) ?>">Ver todas</a>
                    </div>
                    <?php if (!$fila_pendentes): ?>
                    <div class="panel-body">
                        <div class="empty-state">
                            <div class="empty-state-icon"><?= icon('check-circle') ?></div>
                            <p>Nenhuma cotação aguardando atendimento.</p>
                        </div>
                    </div>
                    <?php else: ?>
                    <div class="fila-scroll">
                        <?php foreach ($fila_pendentes as $item):
                            $tipo_key   = $item['tipo'];
                            $icon_name  = match ($tipo_key) {
                                'auto'        => 'car',
                                'condominial' => 'buildings',
                                'titulo'      => 'piggy-bank',
                                'incendio'    => 'fire',
                                'residencial' => 'house-line',
                                default       => $item['tipo_ficha'] === 'comercial' ? 'buildings' : 'house',
                            };
                            $tipo_label = match ($tipo_key) {
                                'auto'        => 'Seguro Auto',
                                'condominial' => 'Seguro Condomínio',
                                'titulo'      => 'Título de Capitalização',
                                'incendio'    => 'Seguro Incêndio',
                                'residencial' => 'Seguro Residencial',
                                default       => $item['tipo_ficha'] === 'comercial' ? 'Fiança Comercial' : 'Fiança Residencial',
                            };
                        ?>
                        <div class="cot-row">
                            <div class="cot-row-icon cot-row-icon--<?= e($tipo_key) ?>">
                                <?= icon($icon_name) ?>
                            </div>
                            <div class="cot-row-info">
                                <span class="cot-row-nome"><?= e($item['pretendente'] ?? '—') ?></span>
                                <span class="cot-row-sub"><?= e($tipo_label) ?> · <?= e($item['protocolo']) ?> · <?= e($item['imobiliaria_nome'] ?? '—') ?></span>
                            </div>
                            <span class="cot-row-data"><?= e(date_to_br($item['data_cadastro'])) ?></span>
                            <a href="<?= e(cotacao_admin_url($item['protocolo'])) ?>" class="btn btn-secondary btn-sm cot-row-btn"><?= icon('eye') ?> Ver</a>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </section>

                <section class="panel">
                    <div class="panel-header">
                        <h2>Panorama — <?= $mes_nome ?></h2>
                    </div>
                    <div class="panel-body">
                        <?php if ($panorama['total'] === 0): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon"><?= icon('chart-pie') ?></div>
                            <p>Nenhuma cotação registrada este mês.</p>
                        </div>
                        <?php else: ?>
                        <div class="panorama-total"><?= $panorama['total'] ?> <span>cotações no mês</span></div>
                        <div class="panorama-bar">
                            <?php foreach ($panorama['statuses'] as $slot): ?>
                            <?php if ($slot['pct'] > 0): ?>
                            <div class="panorama-segment <?= $slot['key'] ?>" data-width="<?= $slot['pct'] ?>"></div>
                            <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                        <ul class="panorama-legend">
                            <?php foreach ($panorama['statuses'] as $slot): ?>
                            <li class="panorama-legend-item">
                                <span class="panorama-legend-dot <?= $slot['key'] ?>"></span>
                                <span class="panorama-legend-label"><?= e($slot['label']) ?></span>
                                <span class="panorama-legend-count"><?= $slot['total'] ?></span>
                                <span class="panorama-legend-pct"><?= $slot['pct'] ?>%</span>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                        <?php endif; ?>
                    </div>
                </section>
            </div>

            <!-- Gráfico mensal + Concluídos por tipo -->
            <div class="dashboard-grid">
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

                <section class="panel">
                    <div class="panel-header">
                        <h2>Concluídas — <?= $mes_nome ?></h2>
                    </div>
                    <div class="panel-body">
                        <ul class="concluidos-list">
                            <?php foreach ($concluidos as $tipo): ?>
                            <li class="concluidos-item">
                                <div class="concluidos-item-icon"><?= icon($tipo['icone']) ?></div>
                                <div class="concluidos-item-info">
                                    <strong><?= e($tipo['label']) ?></strong>
                                </div>
                                <div class="concluidos-item-right">
                                    <span class="concluidos-item-count"><?= $tipo['atual'] ?></span>
                                    <?php
                                        $d = $tipo['delta'];
                                        $badge_class = $d > 0 ? 'is-up' : ($d < 0 ? 'is-down' : 'is-neutral');
                                        $badge_text  = $d > 0 ? "+{$d}" : (string) $d;
                                    ?>
                                    <span class="delta-badge <?= $badge_class ?>"><?= $badge_text ?></span>
                                </div>
                            </li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                </section>
            </div>

        </main>
    </div>
</div>

<?php require __DIR__ . '/../partials/app-foot.php'; ?>
