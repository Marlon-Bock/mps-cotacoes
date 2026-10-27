<?php
/**
 * GET /admin/cotacao/condominial/{protocolo}
 * Detalhe de cotação de seguro condominial para a equipe MPS.
 */

$current_user = require_admin();

$protocolo = $params['protocolo'] ?? '';
if (!$protocolo) redirect('/admin/cotacoes');

$cotacao = db_fetch_one(
    "SELECT sc.*, im.nome AS imob_nome,
            u.username AS usuario_nome,
            ua.username AS atendente_nome
     FROM seguro_condominial sc
     LEFT JOIN imobiliarias im ON im.id = sc.imobiliaria_id
     LEFT JOIN users u ON u.id = sc.usuario_id
     LEFT JOIN usuario ua ON ua.id = sc.atendente_id
     WHERE sc.protocolo = ?",
    ['s', $protocolo]
);
if (!$cotacao) redirect('/admin/cotacoes');

$status = $cotacao['status'] ?? '—';
$chat_unread = cotacao_chat_unread($protocolo, 'admin', (int) $current_user->uid);

// ── Anexos/propostas ──────────────────────────────────────────────────────────
$anexos = [];
try {
    $anexos = db_fetch_all(
        "SELECT * FROM cotacoes_anexos_condominial WHERE protocolo = ? ORDER BY data_envio DESC",
        ['s', $protocolo]
    );
} catch (Throwable) {}
$ultimo_anexo = $anexos[0] ?? null;

// ── Histórico ─────────────────────────────────────────────────────────────────
$historico = [];
try {
    $historico = db_fetch_all(
        "SELECT * FROM historico WHERE protocolo = ? ORDER BY data_evento ASC",
        ['s', $protocolo]
    );
} catch (Throwable) {}

// ── Coberturas ────────────────────────────────────────────────────────────────
$cobertura_labels = [
    'cob_incendio'                  => 'Incêndio',
    'cob_danos_eletricos'           => 'Danos elétricos',
    'cob_vazamento'                 => 'Vazamento',
    'cob_rc_sindico'                => 'RC Síndico',
    'cob_roubo_cond'                => 'Roubo (condomínio)',
    'cob_rc_condominio'             => 'RC Condomínio',
    'cob_perda_aluguel'             => 'Perda de aluguel',
    'cob_rc_danos_morais'           => 'RC Danos morais',
    'cob_roubo_condominios'         => 'Roubo condominios',
    'cob_rc_empregador'             => 'RC Empregador',
    'cob_incendio_condominios'      => 'Incêndio condominios',
    'cob_rc_portoes'                => 'RC Portões',
    'cob_equipamentos_eletronicos'  => 'Equipamentos eletrônicos',
    'cob_vendaval'                  => 'Vendaval',
    'cob_impacto_veiculos'          => 'Impacto de veículos',
    'cob_tumulto_greve'             => 'Tumulto/Greve',
    'cob_quebra_vidros'             => 'Quebra de vidros',
    'cob_despesas_fixas'            => 'Despesas fixas',
    'cob_rc_garagista_compreensiva' => 'RC Garagista compreensiva',
    'cob_lucros_cessantes'          => 'Lucros cessantes',
    'cob_rc_garagista_exclusiva'    => 'RC Garagista exclusiva',
    'cob_desmoronamento'            => 'Desmoronamento',
    'cob_vida_grupo'                => 'Vida em grupo',
    'cob_alagamento'                => 'Alagamento',
    'cob_chuveiros'                 => 'Chuveiros',
    'cob_placas_solares'            => 'Placas solares',
];
$coberturas_estado = [];
foreach ($cobertura_labels as $key => $label) {
    $val = $cotacao[$key] ?? null;
    $ativo = $val !== null && $val !== '' && (float) $val > 0;
    $coberturas_estado[$label] = ['ativo' => $ativo, 'valor' => $ativo ? $val : null];
}

$status_lower = mb_strtolower($status);
$is_recusado  = str_contains($status_lower, 'recusado') || str_contains($status_lower, 'recusada');
$is_desistencia = cotacao_status_desistencia($status);
$fichaPronta  = str_contains($status_lower, 'aguardando') && str_contains($status_lower, 'análise');

$page_title   = 'Cotação ' . $protocolo;
$page_back    = ['url' => '/admin/cotacoes', 'label' => 'Cotações', 'icon' => 'arrow-left'];
$extra_css    = ['dashboard.css', 'cotacao.css', 'cotacao-detalhe.css'];
$extra_js     = [];
$data_criacao = $cotacao['criado_em'] ? date('d/m/Y H:i', strtotime($cotacao['criado_em'])) : 'Não preenchido';

require BASE_PATH . '/pages/cotacao/_partials/detalhe-helpers.php';
require BASE_PATH . '/pages/partials/app-head.php';
require BASE_PATH . '/pages/partials/nav-admin.php';
?>
<div class="app-shell">
    <?php require BASE_PATH . '/pages/partials/sidebar.php'; ?>
    <div class="app-main">
        <?php require BASE_PATH . '/pages/partials/topbar.php'; ?>
        <main class="app-content">

            <!-- Header da cotação -->
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
                        <span class="dph-bar-item">
                            <?= icon('buildings') ?>
                            <span class="dph-bar-label">Imobiliária</span>
                            <span class="dph-bar-value"><?= e($cotacao['imob_nome'] ?? '—') ?></span>
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
                                        ($cotacao['imovel_complemento'] ?? '') ? ' — ' . $cotacao['imovel_complemento'] : '',
                                        ($cotacao['imovel_bairro'] ?? '') ? ', ' . $cotacao['imovel_bairro'] : '',
                                        ($cotacao['imovel_cidade'] ?? '') ? ' — ' . $cotacao['imovel_cidade'] : '',
                                        ($cotacao['imovel_uf'] ?? '') ? '/' . $cotacao['imovel_uf'] : '',
                                    ]));
                                    ?>
                                    <div class="detalhe-field span-full">
                                        <div class="detalhe-field-label">Endereço</div>
                                        <?php dfv($cond_end !== '' ? e($cond_end) : 'Não preenchido') ?>
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
                                        <span class="timeline-meta"><?= e($h['usuario_nome'] ?? '—') ?> · <?= date('d/m/Y H:i', strtotime($h['data_evento'])) ?></span>
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

                <div class="detalhe-right">

                    <?php if ($fichaPronta): ?>
                    <div class="panel">
                        <div class="panel-header"><h3 class="panel-title"><?= icon('clipboard-text') ?> Ficha recebida</h3></div>
                        <div class="panel-body">
                            <div class="alert alert-info" style="margin-bottom:var(--space-3)"><?= icon('info') ?> Pronta para iniciar a análise.</div>
                            <button type="button" class="btn btn-primary btn-block" id="btn-iniciar-analise"><?= icon('arrow-right') ?> Iniciar análise</button>
                        </div>
                    </div>

                    <?php elseif (str_contains($status_lower, 'em an')): ?>
                    <div class="panel" id="painel-proposta">
                        <div class="panel-header"><h3 class="panel-title"><?= icon('clipboard-text') ?> Decisão da cotação</h3></div>
                        <div class="panel-body">
                            <div class="alert alert-info" style="margin-bottom:var(--space-3)">
                                <?= icon('info') ?> Aprove para enviar a proposta ou recuse a cotação.
                            </div>

                            <div id="decisao-escolha">
                                <button type="button" class="btn btn-primary btn-block" id="btn-ir-aprovar" style="margin-bottom:var(--space-2)"><?= icon('check-circle') ?> Aprovar cotação</button>
                                <button type="button" class="btn btn-danger-outline btn-block" id="btn-recusar-mps"><?= icon('x') ?> Recusar cotação</button>
                            </div>

                            <div id="decisao-proposta" hidden>
                                <div class="field" style="margin-bottom:var(--space-3)">
                                    <label class="field-label">Arquivo PDF da proposta</label>
                                    <div class="field-file-picker" data-file-picker>
                                        <input type="file" id="proposta-arquivo" accept=".pdf" hidden>
                                        <button type="button" class="btn btn-secondary btn-sm" data-file-picker-trigger><?= icon('clipboard-text') ?> Escolher arquivo PDF</button>
                                        <span class="field-file-picker-name" data-file-picker-name>Nenhum arquivo selecionado</span>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-primary btn-block" id="btn-enviar-proposta" disabled><?= icon('paper-plane-tilt') ?> Enviar proposta</button>
                            </div>
                        </div>
                    </div>

                    <?php elseif (str_contains($status_lower, 'aprovad')): ?>
                        <?php if ($ultimo_anexo && $ultimo_anexo['status_aprovacao'] === 'Aguardando'): ?>
                        <div class="panel">
                            <div class="panel-header"><h3 class="panel-title"><?= icon('clock') ?> Aguardando decisão</h3></div>
                            <div class="panel-body">
                                <div class="alert alert-info" style="margin-bottom:var(--space-3)"><?= icon('info') ?> Proposta enviada. Aguardando decisão da imobiliária/cliente.</div>
                                <a href="<?= e($ultimo_anexo['caminho_arquivo']) ?>" target="_blank" class="btn btn-secondary btn-block"><?= icon('arrow-right') ?> Ver proposta enviada</a>
                            </div>
                        </div>
                        <?php elseif ($ultimo_anexo && $ultimo_anexo['status_aprovacao'] === 'Recusada'): ?>
                        <div class="panel" id="painel-proposta">
                            <div class="panel-header"><h3 class="panel-title"><?= icon('clipboard-text') ?> Enviar nova proposta</h3></div>
                            <div class="panel-body">
                                <div class="alert alert-warning" style="margin-bottom:var(--space-3)"><?= icon('warning') ?> O cliente recusou a proposta anterior.</div>
                                <div class="field" style="margin-bottom:var(--space-2)">
                                    <label class="field-label">Novo arquivo PDF</label>
                                    <div class="field-file-picker" data-file-picker>
                                    <input type="file" id="proposta-arquivo" accept=".pdf" hidden>
                                    <button type="button" class="btn btn-secondary btn-sm" data-file-picker-trigger><?= icon('clipboard-text') ?> Escolher arquivo PDF</button>
                                    <span class="field-file-picker-name" data-file-picker-name>Nenhum arquivo selecionado</span>
                                </div>
                                </div>
                                <button type="button" class="btn btn-primary btn-block" id="btn-enviar-proposta"><?= icon('paper-plane-tilt') ?> Enviar nova proposta</button>
                                <button type="button" class="btn btn-danger-outline btn-block proposta-acao-secundaria" id="btn-recusado-cliente"><?= icon('x') ?> Recusado pelo cliente</button>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="panel" id="painel-proposta">
                            <div class="panel-header"><h3 class="panel-title"><?= icon('clipboard-text') ?> Enviar proposta</h3></div>
                            <div class="panel-body">
                                <div class="alert alert-info" style="margin-bottom:var(--space-3)"><?= icon('info') ?> Cotação aprovada. Envie o arquivo da proposta.</div>
                                <div class="field" style="margin-bottom:var(--space-3)">
                                    <label class="field-label">Arquivo PDF da proposta</label>
                                    <div class="field-file-picker" data-file-picker>
                                        <input type="file" id="proposta-arquivo" accept=".pdf" hidden>
                                        <button type="button" class="btn btn-secondary btn-sm" data-file-picker-trigger><?= icon('clipboard-text') ?> Escolher arquivo PDF</button>
                                        <span class="field-file-picker-name" data-file-picker-name>Nenhum arquivo selecionado</span>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-primary btn-block" id="btn-enviar-proposta"><?= icon('paper-plane-tilt') ?> Enviar proposta</button>
                            </div>
                        </div>
                        <?php endif; ?>

                    <?php elseif (str_contains($status_lower, 'concluí') || str_contains($status_lower, 'concluido')): ?>
                    <div class="panel"><div class="panel-body">
                        <div class="alert alert-success" style="margin-bottom:0"><?= icon('check-circle') ?> <strong>Cotação concluída!</strong></div>
                    </div></div>

                    <?php elseif ($is_desistencia): ?>
                    <div class="panel"><div class="panel-body">
                        <div class="alert alert-desistencia"><?= icon('sign-out') ?> <strong>Cliente desistiu da cotação.</strong></div>
                    </div></div>

                    <?php elseif ($is_recusado): ?>
                    <div class="panel"><div class="panel-body">
                        <div class="alert alert-danger" style="margin-bottom:0"><?= icon('x') ?> <strong><?= $status === STATUS_RECUSADO_CLIENTE ? 'Cotação recusada pelo cliente.' : 'Cotação recusada.' ?></strong></div>
                    </div></div>
                    <?php endif; ?>

                    <?php if (!cotacao_status_encerrado($status)): ?>
                    <div class="panel panel-desistencia">
                        <div class="panel-body">
                            <p class="desistencia-texto">Cliente avisou que desistiu? Encerre a cotação para tirá-la da fila de análise.</p>
                            <button type="button" class="btn btn-secondary btn-block" id="btn-desistir"><?= icon('sign-out') ?> Cliente desistiu da cotação</button>
                        </div>
                    </div>
                    <?php endif; ?>

                    <?php if (!empty($anexos)): ?>
                    <div class="panel" style="margin-top:var(--space-3)">
                        <div class="panel-header"><h3 class="panel-title"><?= icon('list') ?> Propostas enviadas</h3></div>
                        <div class="panel-body">
                            <?php foreach ($anexos as $a): ?>
                            <div style="display:flex;align-items:center;justify-content:space-between;padding:var(--space-2) 0;border-bottom:1px solid var(--color-border)">
                                <div>
                                    <small><?= date('d/m/Y H:i', strtotime($a['data_envio'])) ?></small>
                                    <br><span class="status-pill <?= e(status_pill_class($a['status_aprovacao'])) ?>" style="font-size:.7rem"><?= e($a['status_aprovacao']) ?></span>
                                </div>
                                <a href="<?= e($a['caminho_arquivo']) ?>" target="_blank" class="btn btn-secondary" style="padding:var(--space-1) var(--space-2)"><?= icon('arrow-right') ?></a>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <?php endif; ?>

                </div><!-- /.detalhe-right -->

            </div><!-- /.detalhe-layout -->

        </main>
    </div>
</div>

<script>
(function () {
    const proto = <?= json_encode($protocolo) ?>;

    function apiPost(endpoint, body) {
        return fetch(`/api/cotacao/${proto}/${endpoint}`, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams(body),
        }).then(r => r.json());
    }
    function handleResp(data, successMsg) {
        if (data.success) { mpsAlert.success(successMsg || data.message); setTimeout(() => location.reload(), 1200); }
        else { mpsAlert.error(data.message || 'Tente novamente.'); }
    }

    const btnIniciar = document.getElementById('btn-iniciar-analise');
    if (btnIniciar) btnIniciar.addEventListener('click', async () => {
        const ok = await mpsAlert.confirm({ message: 'Iniciar análise desta cotação?' });
        if (!ok) return;
        apiPost('iniciar-analise', {}).then(d => handleResp(d, 'Análise iniciada!'));
    });

    const btnRecusarMPS = document.getElementById('btn-recusar-mps');
    if (btnRecusarMPS) btnRecusarMPS.addEventListener('click', async () => {
        const ok = await mpsAlert.confirm({ message: 'Recusar esta cotação?' });
        if (!ok) return;
        apiPost('recusar-mps', {}).then(d => handleResp(d, 'Cotação recusada.'));
    });

    // "Aprovar cotação" só troca a etapa visível — a aprovação de fato só
    // acontece quando a proposta é enviada (POST /proposta muda o status).
    const btnIrAprovar     = document.getElementById('btn-ir-aprovar');
    const decisaoEscolha   = document.getElementById('decisao-escolha');
    const decisaoProposta  = document.getElementById('decisao-proposta');
    if (btnIrAprovar && decisaoEscolha && decisaoProposta) {
        btnIrAprovar.addEventListener('click', () => {
            decisaoEscolha.hidden  = true;
            decisaoProposta.hidden = false;
        });
    }

    const inputPropostaArquivo = document.getElementById('proposta-arquivo');
    const btnEnviarProposta    = document.getElementById('btn-enviar-proposta');
    if (inputPropostaArquivo && btnEnviarProposta) {
        inputPropostaArquivo.addEventListener('change', () => {
            btnEnviarProposta.disabled = inputPropostaArquivo.files.length === 0;
        });
    }
    if (btnEnviarProposta) btnEnviarProposta.addEventListener('click', () => {
        const arquivo = document.getElementById('proposta-arquivo').files[0];
        if (!arquivo) { mpsAlert.error('Selecione o arquivo PDF.'); return; }
        const fd = new FormData();
        fd.append('arquivo', arquivo);
        btnEnviarProposta.disabled = true;
        btnEnviarProposta.textContent = 'Enviando...';
        fetch(`/api/cotacao/${proto}/proposta`, {method: 'POST', body: fd})
            .then(r => r.json())
            .then(d => handleResp(d, 'Proposta enviada!'))
            .catch(() => mpsAlert.error('Erro ao enviar proposta.'))
            .finally(() => { btnEnviarProposta.disabled = false; btnEnviarProposta.textContent = 'Enviar proposta'; });
    });

    // Cliente recusou a proposta e a imobiliária não quer outra: encerra a
    // cotação com status "Recusado pelo cliente".
    const btnRecusadoCliente = document.getElementById('btn-recusado-cliente');
    if (btnRecusadoCliente) btnRecusadoCliente.addEventListener('click', async () => {
        const ok = await mpsAlert.confirm({ message: 'Encerrar esta cotação como recusada pelo cliente?' });
        if (!ok) return;
        apiPost('recusar-cliente', {}).then(d => handleResp(d, 'Cotação recusada pelo cliente.'));
    });

    // Cliente desistiu (avisou a MPS direto): encerra a cotação em qualquer etapa.
    const btnDesistir = document.getElementById('btn-desistir');
    if (btnDesistir) btnDesistir.addEventListener('click', async () => {
        const ok = await mpsAlert.confirm({ message: 'Encerrar esta cotação por desistência do cliente? Essa ação não pode ser desfeita.' });
        if (!ok) return;
        apiPost('desistir', {}).then(d => handleResp(d, 'Cotação encerrada por desistência.'));
    });
})();
</script>

<?php require BASE_PATH . '/pages/partials/app-foot.php'; ?>
