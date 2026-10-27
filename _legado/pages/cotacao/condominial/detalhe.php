<?php
$current_user = require_perm('perm_cotar', 'perm_condo');

$protocolo      = $params['protocolo'] ?? '';
if (!$protocolo) redirect('/cotacoes');

$imobiliaria_id = (int)($current_user->imobiliaria_id ?? 0);

$cotacao = db_fetch_one(
    "SELECT sc.*, ua.username AS atendente_nome
     FROM seguro_condominial sc
     LEFT JOIN usuario ua ON ua.id = sc.atendente_id
     WHERE sc.protocolo = ? AND sc.imobiliaria_id = ?",
    ['si', $protocolo, $imobiliaria_id]
);
if (!$cotacao) redirect('/cotacoes');
// Operador comum só acessa a própria cotação (mesma regra de "Minhas Cotações").
if (empty($current_user->imob_admin) && (int) ($cotacao['usuario_id'] ?? 0) !== (int) $current_user->uid) {
    redirect('/cotacoes');
}

$status = $cotacao['status'] ?? '—';
$chat_unread = cotacao_chat_unread($protocolo, 'imobiliaria', (int) $current_user->uid);

// ── Histórico (tabela nativa) ──────────────────────────────────────────────────
$historico = [];
try {
    $historico = db_fetch_all(
        "SELECT * FROM historico WHERE protocolo = ? ORDER BY data_evento ASC",
        ['s', $protocolo]
    );
} catch (Throwable) {}

// ── Anexo ─────────────────────────────────────────────────────────────────────
$anexo = db_fetch_one(
    "SELECT * FROM cotacoes_anexos_condominial WHERE protocolo = ? ORDER BY data_envio DESC LIMIT 1",
    ['s', $protocolo]
);

// ── Timeline ──────────────────────────────────────────────────────────────────
$all_steps = [
    ['key' => 'aguardando', 'label' => 'Aguardando análise'],
    ['key' => 'em_analise', 'label' => 'Em Análise'],
    ['key' => 'aprovado',   'label' => 'Aprovado'],
    ['key' => 'concluido',  'label' => 'Concluído'],
];
$status_lower  = mb_strtolower($status);
$is_recusado    = str_contains($status_lower, 'recusado') || str_contains($status_lower, 'recusada');
$is_desistencia = cotacao_status_desistencia($status);
$current_index = 0;
if (str_contains($status_lower, 'análise') || str_contains($status_lower, 'analise')) $current_index = 1;
elseif (str_contains($status_lower, 'aprovad')) $current_index = 2;
elseif (str_contains($status_lower, 'concluíd') || str_contains($status_lower, 'concluido')) $current_index = 3;

if ($is_desistencia) {
    $timeline_steps = [
        ['key' => 'aguardando',  'label' => 'Aguardando análise',     'state' => 'is-done'],
        ['key' => 'desistencia', 'label' => 'Desistência do cliente', 'state' => 'is-desistencia'],
    ];
} elseif ($is_recusado) {
    $timeline_steps = [
        ['key' => 'aguardando', 'label' => 'Aguardando análise', 'state' => 'is-done'],
        ['key' => 'recusado',   'label' => $status,              'state' => 'is-recusado'],
    ];
} else {
    $timeline_steps = [];
    foreach ($all_steps as $i => $step) {
        $state = $i < $current_index ? 'is-done' : ($i === $current_index ? 'is-current' : 'is-pending');
        $timeline_steps[] = $step + ['state' => $state];
    }
}

$preAnalisePendente = false;
$podeCompletar      = false;
$urlCompletar       = '';

// ── Coberturas selecionadas ────────────────────────────────────────────────────
$cobertura_labels = [
    'cob_incendio'                    => 'Incêndio',
    'cob_danos_eletricos'             => 'Danos elétricos',
    'cob_vazamento'                   => 'Vazamento',
    'cob_rc_sindico'                  => 'RC Síndico',
    'cob_roubo_cond'                  => 'Roubo (condomínio)',
    'cob_rc_condominio'               => 'RC Condomínio',
    'cob_perda_aluguel'               => 'Perda de aluguel',
    'cob_rc_danos_morais'             => 'RC Danos morais',
    'cob_roubo_condominios'           => 'Roubo condominios',
    'cob_rc_empregador'               => 'RC Empregador',
    'cob_incendio_condominios'        => 'Incêndio condominios',
    'cob_rc_portoes'                  => 'RC Portões',
    'cob_equipamentos_eletronicos'    => 'Equipamentos eletrônicos',
    'cob_vendaval'                    => 'Vendaval',
    'cob_impacto_veiculos'            => 'Impacto de veículos',
    'cob_tumulto_greve'               => 'Tumulto/Greve',
    'cob_quebra_vidros'               => 'Quebra de vidros',
    'cob_despesas_fixas'              => 'Despesas fixas',
    'cob_rc_garagista_compreensiva'   => 'RC Garagista compreensiva',
    'cob_lucros_cessantes'            => 'Lucros cessantes',
    'cob_rc_garagista_exclusiva'      => 'RC Garagista exclusiva',
    'cob_desmoronamento'              => 'Desmoronamento',
    'cob_vida_grupo'                  => 'Vida em grupo',
    'cob_alagamento'                  => 'Alagamento',
    'cob_chuveiros'                   => 'Chuveiros',
    'cob_placas_solares'              => 'Placas solares',
];
$coberturas_estado = [];
foreach ($cobertura_labels as $key => $label) {
    $val = $cotacao[$key] ?? null;
    $ativo = $val !== null && $val !== '' && (float) $val > 0;
    $coberturas_estado[$label] = ['ativo' => $ativo, 'valor' => $ativo ? $val : null];
}

// ── Metadados ─────────────────────────────────────────────────────────────────
$page_title       = $protocolo;
$page_back        = ['url' => '/cotacoes', 'label' => 'Minhas Cotações', 'icon' => 'arrow-left'];
$extra_css        = ['dashboard.css', 'cotacao.css', 'cotacao-detalhe.css'];
$extra_js         = $anexo && ($anexo['status_aprovacao'] ?? '') === 'Aguardando' ? ['cotacao-detalhe.js'] : [];
$data_criacao     = $cotacao['criado_em'] ? date('d/m/Y H:i', strtotime($cotacao['criado_em'])) : 'Não preenchido';
$data_atualizacao = $cotacao['criado_em'] ? date('d/m/Y H:i', strtotime($cotacao['criado_em'])) : 'Não preenchido';

require __DIR__ . '/../_partials/detalhe-helpers.php';
require __DIR__ . '/../../partials/app-head.php';
require __DIR__ . '/../../partials/nav-imobiliaria.php';
?>
<div class="app-shell">
    <?php require __DIR__ . '/../../partials/sidebar.php'; ?>
    <div class="app-main">
        <?php require __DIR__ . '/../../partials/topbar.php'; ?>
        <main class="app-content">

            <!-- Header -->
                                    <div class="panel">
                <div class="panel-body detalhe-panel-header">
                    <div class="dph-top">
                        <span class="dph-tipo"><?= icon('buildings') ?> Seguro Condominial</span>
                        <span class="status-pill <?= e(status_pill_class($status)) ?>"><?= e($status) ?></span>
                    </div>
                    <span class="dph-proto">Cotação #<?= e($protocolo) ?></span>
                    <div class="dph-bar">
                        <?php $_cond_nome = $cotacao['imovel_nome'] ?? $cotacao['sindico_nome'] ?? ''; ?>
                        <?php if ($_cond_nome): ?>
                        <span class="dph-bar-item">
                            <?= icon('buildings') ?>
                            <span class="dph-bar-label">Condomínio</span>
                            <span class="dph-bar-value"><?= e($_cond_nome) ?></span>
                        </span>
                        <span class="dph-bar-sep">&middot;</span>
                        <?php endif; ?>
                        <?php if (!empty($cotacao['atendente_nome'])): ?>
                        <span class="dph-bar-item">
                            <?= icon('users') ?>
                            <span class="dph-bar-label">Atendente MPS</span>
                            <span class="dph-bar-value"><?= e($cotacao['atendente_nome']) ?></span>
                        </span>
                        <span class="dph-bar-sep">&middot;</span>
                        <?php endif; ?>
                        <span class="dph-bar-item">
                            <?= icon('clock') ?>
                            <span class="dph-bar-value"><?= e($data_criacao) ?></span>
                        </span>
                        <span class="dph-bar-spacer"></span>
                        <button type="button" class="btn btn-sm btn-chat-cotacao <?= $chat_unread > 0 ? ('btn-danger-outline') : ('btn-secondary') ?>" data-action="abrir-chat-cotacao" data-protocolo="<?= e($protocolo) ?>">
                            <?= icon('chat-circle') ?> Chat da cotação
                            <?php if ($chat_unread > 0): ?><span class="count-badge"><?= $chat_unread > 99 ? '99+' : e($chat_unread) ?></span><?php endif; ?>
                        </button>
                        <a href="<?= e(BASE_URI) ?>/api/cotacao/<?= e($protocolo) ?>/condominial-pdf" target="_blank" rel="noopener" class="btn btn-secondary btn-sm btn-pdf">
                            <?= icon('file-pdf') ?> Baixar PDF
                        </a>
                    </div>
                </div>
            </div>

            <div class="detalhe-layout cotacao-container">
                <div class="detalhe-left">

                    <div class="panel">
                        <div class="panel-body">

                            <!-- Dados do condomínio -->
                            <div class="form-section">
                                <h3 class="form-section-title"><?= icon('buildings') ?> Dados do condomínio</h3>
                                <div class="detalhe-fields">
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Tipo de condomínio</div>
                                        <?php dfv(cv($cotacao['tipo_condominio'])) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Nome</div>
                                        <?php dfv(cv($cotacao['imovel_nome'])) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">CNPJ</div>
                                        <?php dfv(cv_cpf_cnpj($cotacao['imovel_cnpj'])) ?>
                                    </div>
                                    <?php
                                    $cond_end = implode('', array_filter([
                                        $cotacao['imovel_endereco'] ?? '',
                                        ($cotacao['imovel_numero'] ?? '') ? ', ' . $cotacao['imovel_numero'] : '',
                                        ($cotacao['imovel_complemento'] ?? '') ? ' - ' . $cotacao['imovel_complemento'] : '',
                                        ($cotacao['imovel_bairro'] ?? '') ? ', ' . $cotacao['imovel_bairro'] : '',
                                        ($cotacao['imovel_cidade'] ?? '') ? ' - ' . $cotacao['imovel_cidade'] : '',
                                        ($cotacao['imovel_uf'] ?? '') ? '/' . $cotacao['imovel_uf'] : '',
                                    ]));
                                    ?>
                                    <div class="detalhe-field span-full">
                                        <div class="detalhe-field-label">Endereço</div>
                                        <?php dfv(cv($cond_end ?: null)) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">CEP</div>
                                        <?php dfv(cv_cep($cotacao['imovel_cep'])) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Ano de construção</div>
                                        <?php dfv(cv($cotacao['imovel_ano_construcao'])) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Nº de blocos</div>
                                        <?php dfv(cv($cotacao['imovel_blocos'])) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Nº de andares</div>
                                        <?php dfv(cv($cotacao['imovel_andares'])) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Elevadores</div>
                                        <?php dfv(cv($cotacao['imovel_elevadores'])) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Área (m²)</div>
                                        <?php dfv(cv($cotacao['imovel_area_m2'])) ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Características -->
                            <div class="form-section">
                                <h3 class="form-section-title"><?= icon('gear') ?> Características</h3>
                                <div class="detalhe-fields">
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Alarme</div>
                                        <?php dfv(cv_sim_nao($cotacao['tem_alarme'] ?? null)) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Câmeras</div>
                                        <?php dfv(cv_sim_nao($cotacao['tem_cameras'] ?? null)) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Central telefônica</div>
                                        <?php dfv(cv_sim_nao($cotacao['tem_central_telefonica'] ?? null)) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Funcionários</div>
                                        <?php dfv(cv_sim_nao($cotacao['tem_funcionarios'] ?? null)) ?>
                                    </div>
                                    <?php if (!empty($cotacao['qtd_funcionarios'])): ?>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Qtd. funcionários</div>
                                        <?php dfv(cv($cotacao['qtd_funcionarios'])) ?>
                                    </div>
                                    <?php endif; ?>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Seguro atual</div>
                                        <?php dfv(cv_sim_nao($cotacao['tem_seguro_atual'] ?? null)) ?>
                                    </div>
                                    <?php if (!empty($cotacao['seguro_atual_seguradora'])): ?>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Seguradora atual</div>
                                        <?php dfv(cv($cotacao['seguro_atual_seguradora'])) ?>
                                    </div>
                                    <?php endif; ?>
                                    <?php if (!empty($cotacao['seguro_atual_classe_bonus'])): ?>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Classe bônus</div>
                                        <?php dfv(cv($cotacao['seguro_atual_classe_bonus'])) ?>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <!-- Coberturas -->
                            <div class="form-section">
                                <h3 class="form-section-title"><?= icon('check-circle') ?> Coberturas solicitadas</h3>
                                <div class="coberturas-badges">
                                    <?php foreach ($coberturas_estado as $label => $c): ?>
                                    <span class="cob-badge <?= $c['ativo'] ? 'is-active' : '' ?>">
                                        <?= icon($c['ativo'] ? 'check-circle' : 'x') ?> <?= e($label) ?><?= $c['ativo'] ? ' — ' . cv_money($c['valor']) : '' ?>
                                    </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- Síndico -->
                            <div class="form-section">
                                <h3 class="form-section-title"><?= icon('user') ?> Dados do síndico</h3>
                                <div class="detalhe-fields">
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Nome</div>
                                        <?php dfv(cv($cotacao['sindico_nome'])) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Telefone</div>
                                        <?php dfv(cv_telefone($cotacao['sindico_telefone'])) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">E-mail</div>
                                        <?php dfv(cv($cotacao['sindico_email'])) ?>
                                    </div>
                                </div>
                            </div>

                        </div>
                    </div>

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

                <?php $usou_pre_analise = false;
                $tipo_key = 'condominial';
                require __DIR__ . '/../_partials/detalhe-acoes.php'; ?>

            </div><!-- /.detalhe-layout -->

        </main>
    </div>
</div>

<?php if ($anexo && ($anexo['status_aprovacao'] ?? '') === 'Aguardando'): ?>
<script>window.cotacaoProtocolo = <?= json_encode($protocolo) ?>;</script>
<?php endif; ?>

<?php require __DIR__ . '/../../partials/app-foot.php'; ?>