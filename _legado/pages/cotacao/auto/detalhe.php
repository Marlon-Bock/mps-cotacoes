<?php
$current_user = require_perm('perm_cotar', 'perm_auto');

$protocolo      = $params['protocolo'] ?? '';
if (!$protocolo) redirect('/cotacoes');

$imobiliaria_id = (int)($current_user->imobiliaria_id ?? 0);

$cotacao = db_fetch_one(
    "SELECT sa.*, ua.username AS atendente_nome
     FROM seguro_auto sa
     LEFT JOIN usuario ua ON ua.id = sa.atendente_id
     WHERE sa.protocolo = ? AND sa.imobiliaria_id = ?",
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
$anexo = null;
try {
    $anexo = db_fetch_one(
        "SELECT * FROM cotacoes_anexos_auto WHERE protocolo = ? ORDER BY data_envio DESC LIMIT 1",
        ['s', $protocolo]
    );
} catch (Throwable) {}

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

// ── Metadados ─────────────────────────────────────────────────────────────────
$page_title       = $protocolo;
$page_back        = ['url' => '/cotacoes', 'label' => 'Minhas Cotações', 'icon' => 'arrow-left'];
$extra_css        = ['dashboard.css', 'cotacao.css', 'cotacao-detalhe.css'];
$extra_js         = $anexo && ($anexo['status_aprovacao'] ?? '') === 'Aguardando' ? ['cotacao-detalhe.js'] : [];
$data_criacao     = $cotacao['data_cadastro'] ? date('d/m/Y H:i', strtotime($cotacao['data_cadastro'])) : 'Não preenchido';
$data_atualizacao = $cotacao['data_atualizacao'] ? date('d/m/Y H:i', strtotime($cotacao['data_atualizacao'])) : $data_criacao;

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
                        <span class="dph-tipo"><?= icon('car') ?> Seguro Auto</span>
                        <span class="status-pill <?= e(status_pill_class($status)) ?>"><?= e($status) ?></span>
                    </div>
                    <span class="dph-proto">Cotação #<?= e($protocolo) ?></span>
                    <div class="dph-bar">
                        <?php if (!empty($cotacao['segurado_nome'])): ?>
                        <span class="dph-bar-item">
                            <?= icon('user') ?>
                            <span class="dph-bar-label">Segurado</span>
                            <span class="dph-bar-value"><?= e($cotacao['segurado_nome']) ?></span>
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
                        <button onclick="window.print()" class="btn btn-secondary btn-sm btn-pdf">
                            <?= icon('file-pdf') ?> Baixar PDF
                        </button>
                    </div>
                </div>
            </div>

            <div class="detalhe-layout cotacao-container">
                <div class="detalhe-left">

                    <div class="panel">
                        <div class="panel-body">

                            <!-- Dados do veículo -->
                            <div class="form-section">
                                <h3 class="form-section-title"><?= icon('car') ?> Dados do veículo</h3>
                                <div class="detalhe-fields">
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Tipo de veículo</div>
                                        <?php dfv(cv($cotacao['tipo_veiculo'])) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Marca</div>
                                        <?php dfv(cv($cotacao['marca_nome'])) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Modelo</div>
                                        <?php dfv(cv($cotacao['modelo_nome'])) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Ano fabricação</div>
                                        <?php dfv(cv($cotacao['ano_fabricacao'] ?? $cotacao['ano_nome'] ?? null)) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Ano modelo</div>
                                        <?php dfv(cv($cotacao['ano_modelo'] ?? null)) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Combustível</div>
                                        <?php dfv(cv($cotacao['combustivel'])) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Placa</div>
                                        <?php dfv(cv($cotacao['placa'])) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Chassi</div>
                                        <?php dfv(cv($cotacao['chassi'])) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Kit gás</div>
                                        <?php dfv(cv_sim_nao($cotacao['kit_gas'] ?? null)) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Tipo de seguro</div>
                                        <?php
                                        $tipo_seguro_label = (int)($cotacao['renovacao_cia'] ?? 0) === 1
                                            ? 'Renovação'
                                            : ((int)($cotacao['seguro_novo'] ?? 0) === 1 ? 'Seguro novo' : null);
                                        dfv(cv($tipo_seguro_label));
                                        ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Utilizado a trabalho</div>
                                        <?php dfv(cv_sim_nao($cotacao['utilizado_trabalho'] ?? null)) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Utilizado para ir ao trabalho</div>
                                        <?php dfv(cv_sim_nao($cotacao['utilizado_ir_trabalho'] ?? null)) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">KM distância trabalho</div>
                                        <?php dfv(cv($cotacao['km_distancia_trabalho'])) ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Dados do segurado -->
                            <div class="form-section">
                                <h3 class="form-section-title"><?= icon('user') ?> Dados do segurado</h3>
                                <div class="detalhe-fields">
                                    <div class="detalhe-field span-full">
                                        <div class="detalhe-field-label">Nome</div>
                                        <?php dfv(cv($cotacao['segurado_nome'])) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">CPF</div>
                                        <?php dfv(cv_cpf_cnpj($cotacao['segurado_cpf'])) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Data de nascimento</div>
                                        <?php dfv(cv_data($cotacao['segurado_data_nasc'] ?? null)) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Estado civil</div>
                                        <?php dfv(cv($cotacao['segurado_estado_civil'])) ?>
                                    </div>
                                    <?php
                                    $seg_end = implode('', array_filter([
                                        $cotacao['segurado_endereco'] ?? '',
                                        ($cotacao['segurado_numero'] ?? '') ? ', ' . $cotacao['segurado_numero'] : '',
                                        ($cotacao['segurado_complemento'] ?? '') ? ' - ' . $cotacao['segurado_complemento'] : '',
                                        ($cotacao['segurado_bairro'] ?? '') ? ', ' . $cotacao['segurado_bairro'] : '',
                                        ($cotacao['segurado_cidade'] ?? '') ? ' - ' . $cotacao['segurado_cidade'] : '',
                                        ($cotacao['segurado_uf'] ?? '') ? '/' . $cotacao['segurado_uf'] : '',
                                    ]));
                                    ?>
                                    <div class="detalhe-field span-full">
                                        <div class="detalhe-field-label">Endereço</div>
                                        <?php dfv(cv($seg_end ?: null)) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">CEP</div>
                                        <?php dfv(cv_cep($cotacao['segurado_cep'])) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Garagem residência</div>
                                        <?php dfv(cv_sim_nao($cotacao['garagem_residencia'] ?? null)) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Portão residência</div>
                                        <?php dfv(cv_sim_nao($cotacao['portao_residencia'] ?? null)) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Garagem trabalho</div>
                                        <?php dfv(cv_sim_nao($cotacao['garagem_trabalho'] ?? null)) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Portão trabalho</div>
                                        <?php dfv(cv_sim_nao($cotacao['portao_trabalho'] ?? null)) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Garagem faculdade</div>
                                        <?php dfv(cv_sim_nao($cotacao['garagem_faculdade'] ?? null)) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Portão faculdade</div>
                                        <?php dfv(cv_sim_nao($cotacao['portao_faculdade'] ?? null)) ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Dados do condutor -->
                            <div class="form-section">
                                <h3 class="form-section-title"><?= icon('identification-card') ?> Dados do condutor</h3>
                                <div class="detalhe-fields">
                                    <div class="detalhe-field span-full">
                                        <div class="detalhe-field-label">Nome</div>
                                        <?php dfv(cv($cotacao['condutor_nome'])) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">CPF</div>
                                        <?php dfv(cv_cpf_cnpj($cotacao['condutor_cpf'])) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Data de nascimento</div>
                                        <?php dfv(cv_data($cotacao['condutor_data_nasc'] ?? null)) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Estado civil</div>
                                        <?php dfv(cv($cotacao['condutor_estado_civil'])) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Relação com segurado</div>
                                        <?php dfv(cv($cotacao['condutor_relacao_segurado'])) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Condutor menor de 25</div>
                                        <?php dfv(cv($cotacao['condutor_menor_25'])) ?>
                                    </div>
                                    <?php if (!empty($cotacao['condutor_menor_25_sexo'])): ?>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Sexo (menor de 25)</div>
                                        <?php dfv(cv($cotacao['condutor_menor_25_sexo'])) ?>
                                    </div>
                                    <?php endif; ?>
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
                $tipo_key = 'auto';
                require __DIR__ . '/../_partials/detalhe-acoes.php'; ?>

            </div><!-- /.detalhe-layout -->

        </main>
    </div>
</div>

<?php if ($anexo && ($anexo['status_aprovacao'] ?? '') === 'Aguardando'): ?>
<script>window.cotacaoProtocolo = <?= json_encode($protocolo) ?>;</script>
<?php endif; ?>

<?php require __DIR__ . '/../../partials/app-foot.php'; ?>