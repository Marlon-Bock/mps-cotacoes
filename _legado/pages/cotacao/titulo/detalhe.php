<?php
$current_user = require_perm('perm_cotar', 'perm_titulo');

$protocolo      = $params['protocolo'] ?? '';
if (!$protocolo) redirect('/cotacoes');

$imobiliaria_id = (int)($current_user->imobiliaria_id ?? 0);

$cotacao = db_fetch_one(
    "SELECT tc.*, ua.username AS atendente_nome
     FROM titulo_capitalizacao tc
     LEFT JOIN usuario ua ON ua.id = tc.atendente_id
     WHERE tc.protocolo = ? AND tc.imobiliaria_id = ?",
    ['si', $protocolo, $imobiliaria_id]
);
if (!$cotacao) redirect('/cotacoes');
// Operador comum só acessa a própria cotação (mesma regra de "Minhas Cotações").
if (empty($current_user->imob_admin) && (int) ($cotacao['usuario_id'] ?? 0) !== (int) $current_user->uid) {
    redirect('/cotacoes');
}

$status = $cotacao['status'] ?? '—';
$chat_unread = cotacao_chat_unread($protocolo, 'imobiliaria', (int) $current_user->uid);

// Pra imobiliária, "Em Analise" aparece como "Aguardando Proposta" — nome mais
// claro do que está acontecendo de fato nesse momento do fluxo do título
// (a MPS ainda não enviou a proposta/boleto). Só muda o texto mostrado; o
// valor salvo no banco continua "Em Analise" (não mexe no fluxo de outros
// produtos nem em nada que dependa do valor bruto do status).
$status_label_imob = $status === 'Em Analise' ? 'Aguardando Proposta' : $status;

// ── Histórico (tabela nativa) ──────────────────────────────────────────────────
$historico = [];
try {
    $historico = db_fetch_all(
        "SELECT * FROM historico WHERE protocolo = ? ORDER BY data_evento ASC",
        ['s', $protocolo]
    );
} catch (Throwable) {}

// ── Anexos ────────────────────────────────────────────────────────────────────
// Título tem 4 tipos de anexo possíveis (proposta, boleto, comprovante de
// pagamento, certificado) — cada um buscado à parte pra tela de ações saber
// exatamente qual mostrar em cada etapa do fluxo (ver detalhe-acoes.php).
$anexo = db_fetch_one(
    "SELECT * FROM cotacoes_anexos_titulo WHERE protocolo = ? ORDER BY data_envio DESC LIMIT 1",
    ['s', $protocolo]
);
$titulo_anexo_proposta = db_fetch_one(
    "SELECT * FROM cotacoes_anexos_titulo WHERE protocolo = ? AND tipo_arquivo = 'proposta' ORDER BY data_envio DESC LIMIT 1",
    ['s', $protocolo]
);
$titulo_anexo_boleto = db_fetch_one(
    "SELECT * FROM cotacoes_anexos_titulo WHERE protocolo = ? AND tipo_arquivo = 'boleto' ORDER BY data_envio DESC LIMIT 1",
    ['s', $protocolo]
);
// Só existe quando a forma de pagamento escolhida foi "Cartão" — substitui o
// boleto por um link de pagamento avulso.
$titulo_anexo_link_pagamento = db_fetch_one(
    "SELECT * FROM cotacoes_anexos_titulo WHERE protocolo = ? AND tipo_arquivo = 'link_pagamento_cartao' ORDER BY data_envio DESC LIMIT 1",
    ['s', $protocolo]
);
$titulo_anexo_certificado = db_fetch_one(
    "SELECT * FROM cotacoes_anexos_titulo WHERE protocolo = ? AND tipo_arquivo = 'certificado' ORDER BY data_envio DESC LIMIT 1",
    ['s', $protocolo]
);

// ── Timeline ──────────────────────────────────────────────────────────────────
// Fluxo específico do título: aguardando análise -> aguardando proposta ->
// aguardando pagamento -> pago -> concluído (certificado enviado).
$all_steps = [
    ['key' => 'aguardando',  'label' => 'Aguardando análise'],
    ['key' => 'em_analise',  'label' => 'Aguardando Proposta'],
    ['key' => 'pagamento',   'label' => 'Aguardando Pagamento'],
    ['key' => 'pago',        'label' => 'Pago'],
    ['key' => 'concluido',   'label' => 'Concluído'],
];
$status_lower  = mb_strtolower($status);
$is_recusado    = str_contains($status_lower, 'recusado') || str_contains($status_lower, 'recusada');
$is_desistencia = cotacao_status_desistencia($status);
$current_index = 0;
if ($status === 'Aguardando Pagamento') $current_index = 2;
elseif ($status === 'Pago') $current_index = 3;
elseif (str_contains($status_lower, 'concluíd') || str_contains($status_lower, 'concluido')) $current_index = 4;
elseif (str_contains($status_lower, 'análise') || str_contains($status_lower, 'analise')) $current_index = 1;

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
$extra_js         = [];
$data_criacao     = $cotacao['criado_em'] ? date('d/m/Y H:i', strtotime($cotacao['criado_em'])) : 'Não preenchido';
$data_atualizacao = $cotacao['criado_em'] ? date('d/m/Y H:i', strtotime($cotacao['criado_em'])) : 'Não preenchido';

$inquilino_tipo = strtolower(trim($cotacao['inquilino_tipo'] ?? 'pf'));

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
                        <span class="dph-tipo"><?= icon('ticket') ?> Título de Capitalização</span>
                        <span class="status-pill <?= e(status_pill_class($status)) ?>"><?= e($status_label_imob) ?></span>
                    </div>
                    <span class="dph-proto">Cotação #<?= e($protocolo) ?></span>
                    <div class="dph-bar">
                        <?php $_inq = $cotacao['inq_nome'] ?? $cotacao['inq_razao_social'] ?? ''; ?>
                        <?php if ($_inq): ?>
                        <span class="dph-bar-item">
                            <?= icon('user') ?>
                            <span class="dph-bar-label">Inquilino</span>
                            <span class="dph-bar-value"><?= e($_inq) ?></span>
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
                        <a href="<?= e(BASE_URI) ?>/api/cotacao/<?= e($protocolo) ?>/titulo-pdf" target="_blank" rel="noopener" class="btn btn-secondary btn-sm btn-pdf">
                            <?= icon('file-pdf') ?> Baixar PDF
                        </a>
                    </div>
                </div>
            </div>

            <div class="detalhe-layout cotacao-container">
                <div class="detalhe-left">

                    <div class="panel">
                        <div class="panel-body">

                            <!-- Contrato e imóvel -->
                            <div class="form-section">
                                <h3 class="form-section-title"><?= icon('house') ?> Contrato e imóvel</h3>
                                <div class="detalhe-fields">
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Finalidade</div>
                                        <?php dfv(cv($cotacao['finalidade'])) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Prazo do contrato</div>
                                        <?php dfv(cv($cotacao['prazo_contrato'])) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Valor do aluguel</div>
                                        <?php dfv(cv_money($cotacao['valor_aluguel'] ?? null)) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Valor do título</div>
                                        <?php dfv(cv_money($cotacao['valor_titulo'] ?? null)) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Forma de pagamento</div>
                                        <?php dfv(cv($cotacao['forma_pagamento'] ?? null)) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">CEP do imóvel</div>
                                        <?php dfv(cv($cotacao['imovel_cep'])) ?>
                                    </div>
                                    <?php
                                    $imovel_end = implode('', array_filter([
                                        $cotacao['imovel_logradouro'] ?? '',
                                        ($cotacao['imovel_numero'] ?? '') ? ', ' . $cotacao['imovel_numero'] : '',
                                        ($cotacao['imovel_complemento'] ?? '') ? ' - ' . $cotacao['imovel_complemento'] : '',
                                        ($cotacao['imovel_bairro'] ?? '') ? ', ' . $cotacao['imovel_bairro'] : '',
                                        ($cotacao['imovel_cidade'] ?? '') ? ' - ' . $cotacao['imovel_cidade'] : '',
                                        ($cotacao['imovel_uf'] ?? '') ? '/' . $cotacao['imovel_uf'] : '',
                                    ]));
                                    ?>
                                    <div class="detalhe-field span-full">
                                        <div class="detalhe-field-label">Endereço do imóvel</div>
                                        <?php dfv(cv($imovel_end ?: null)) ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Inquilino -->
                            <div class="form-section">
                                <h3 class="form-section-title"><?= icon('user') ?> Dados do inquilino</h3>
                                <div class="detalhe-fields">
                                <?php if ($inquilino_tipo === 'pj'): ?>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Razão social</div>
                                        <?php dfv(cv($cotacao['inq_razao_social'])) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">CNPJ</div>
                                        <?php dfv(cv($cotacao['inq_cnpj'])) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Representante</div>
                                        <?php dfv(cv($cotacao['inq_representante'])) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">CPF do representante</div>
                                        <?php dfv(cv($cotacao['inq_cpf_representante'])) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">E-mail</div>
                                        <?php dfv(cv($cotacao['inq_pj_email'])) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Telefone</div>
                                        <?php dfv(cv($cotacao['inq_pj_telefone'])) ?>
                                    </div>
                                    <?php if (!empty($cotacao['pol_exposta'])): ?>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Pessoa politicamente exposta</div>
                                        <?php dfv(cv_sim_nao($cotacao['pol_exposta'])) ?>
                                    </div>
                                    <?php if (!empty($cotacao['pol_exposta_funcao'])): ?>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Função (PEP)</div>
                                        <?php dfv(cv($cotacao['pol_exposta_funcao'])) ?>
                                    </div>
                                    <?php endif; ?>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Nome</div>
                                        <?php dfv(cv($cotacao['inq_nome'])) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">CPF</div>
                                        <?php dfv(cv($cotacao['inq_cpf'])) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Data de nascimento</div>
                                        <?php dfv(cv_data($cotacao['inq_data_nasc'] ?? null)) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Estado civil</div>
                                        <?php dfv(cv($cotacao['inq_estado_civil'])) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">RG</div>
                                        <?php dfv(cv($cotacao['inq_rg'])) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Órgão emissor</div>
                                        <?php dfv(cv($cotacao['inq_orgao_emissor'])) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Profissão</div>
                                        <?php dfv(cv($cotacao['inq_profissao'])) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">E-mail</div>
                                        <?php dfv(cv($cotacao['inq_email'])) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Telefone 1</div>
                                        <?php dfv(cv($cotacao['inq_telefone1'])) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Telefone 2</div>
                                        <?php dfv(cv($cotacao['inq_telefone2'])) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">CEP</div>
                                        <?php dfv(cv($cotacao['inq_cep'])) ?>
                                    </div>
                                    <?php
                                    $inq_end = implode('', array_filter([
                                        $cotacao['inq_logradouro'] ?? '',
                                        ($cotacao['inq_numero'] ?? '') ? ', ' . $cotacao['inq_numero'] : '',
                                        ($cotacao['inq_complemento'] ?? '') ? ' - ' . $cotacao['inq_complemento'] : '',
                                        ($cotacao['inq_bairro'] ?? '') ? ', ' . $cotacao['inq_bairro'] : '',
                                        ($cotacao['inq_cidade'] ?? '') ? ' - ' . $cotacao['inq_cidade'] : '',
                                        ($cotacao['inq_uf'] ?? '') ? '/' . $cotacao['inq_uf'] : '',
                                    ]));
                                    ?>
                                    <div class="detalhe-field span-full">
                                        <div class="detalhe-field-label">Endereço do inquilino</div>
                                        <?php dfv(cv($inq_end ?: null)) ?>
                                    </div>
                                <?php endif; ?>
                                </div>
                            </div>

                            <!-- Proprietário -->
                            <div class="form-section">
                                <h3 class="form-section-title"><?= icon('briefcase') ?> Dados do proprietário</h3>
                                <div class="detalhe-fields">
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Nome</div>
                                        <?php dfv(cv($cotacao['prop_nome'])) ?>
                                    </div>
                                    <?php if (!empty($cotacao['prop_cnpj'])): ?>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">CNPJ</div>
                                        <?php dfv(cv($cotacao['prop_cnpj'])) ?>
                                    </div>
                                    <?php if (!empty($cotacao['prop_nome_socio'])): ?>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Nome do sócio</div>
                                        <?php dfv(cv($cotacao['prop_nome_socio'])) ?>
                                    </div>
                                    <?php endif; ?>
                                    <?php else: ?>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">CPF</div>
                                        <?php dfv(cv($cotacao['prop_cpf'])) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Data de nascimento</div>
                                        <?php dfv(cv_data($cotacao['prop_data_nasc'] ?? null)) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Estado civil</div>
                                        <?php dfv(cv($cotacao['prop_estado_civil'])) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">RG</div>
                                        <?php dfv(cv($cotacao['prop_rg'])) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Órgão emissor</div>
                                        <?php dfv(cv($cotacao['prop_orgao_emissor'])) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Profissão</div>
                                        <?php dfv(cv($cotacao['prop_profissao'])) ?>
                                    </div>
                                    <?php endif; ?>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">E-mail</div>
                                        <?php dfv(cv($cotacao['prop_email'])) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Telefone 1</div>
                                        <?php dfv(cv($cotacao['prop_telefone1'])) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Telefone 2</div>
                                        <?php dfv(cv($cotacao['prop_telefone2'])) ?>
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
                $tipo_key = 'titulo';
                require __DIR__ . '/../_partials/detalhe-acoes.php'; ?>

            </div><!-- /.detalhe-layout -->

        </main>
    </div>
</div>

<?php if ($anexo && ($anexo['status_aprovacao'] ?? '') === 'Aguardando'): ?>
<script>window.cotacaoProtocolo = <?= json_encode($protocolo) ?>;</script>
<?php endif; ?>

<?php require __DIR__ . '/../../partials/app-foot.php'; ?>