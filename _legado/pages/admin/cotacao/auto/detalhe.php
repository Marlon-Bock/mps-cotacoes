<?php
/**
 * GET /admin/cotacao/auto/{protocolo}
 * Detalhe de cotação de seguro auto para a equipe MPS.
 */

$current_user = require_admin();

$protocolo = $params['protocolo'] ?? '';
if (!$protocolo) redirect('/admin/cotacoes');

$cotacao = db_fetch_one(
    "SELECT sa.*, im.nome AS imob_nome,
            u.username AS usuario_nome,
            ua.username AS atendente_nome
     FROM seguro_auto sa
     LEFT JOIN imobiliarias im ON im.id = sa.imobiliaria_id
     LEFT JOIN users u ON u.id = sa.usuario_id
     LEFT JOIN usuario ua ON ua.id = sa.atendente_id
     WHERE sa.protocolo = ?",
    ['s', $protocolo]
);
if (!$cotacao) redirect('/admin/cotacoes');

$status = $cotacao['status'] ?? '—';
$chat_unread = cotacao_chat_unread($protocolo, 'admin', (int) $current_user->uid);

// ── Anexos/propostas ──────────────────────────────────────────────────────────
$anexos = [];
try {
    $anexos = db_fetch_all(
        "SELECT * FROM cotacoes_anexos_auto WHERE protocolo = ? ORDER BY data_envio DESC",
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

// ── Timeline ──────────────────────────────────────────────────────────────────
$status_lower  = mb_strtolower($status);
$is_recusado   = str_contains($status_lower, 'recusado') || str_contains($status_lower, 'recusada');
$is_desistencia = cotacao_status_desistencia($status);
$fichaPronta   = str_contains($status_lower, 'aguardando') && str_contains($status_lower, 'análise');

$page_title   = 'Cotação ' . $protocolo;
$page_back    = ['url' => '/admin/cotacoes', 'label' => 'Cotações', 'icon' => 'arrow-left'];
$extra_css    = ['dashboard.css', 'cotacao.css', 'cotacao-detalhe.css'];
$extra_js     = [];
$data_criacao = $cotacao['data_cadastro'] ? date('d/m/Y H:i', strtotime($cotacao['data_cadastro'])) : 'Não preenchido';

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
                                        ($cotacao['segurado_complemento'] ?? '') ? ' — ' . $cotacao['segurado_complemento'] : '',
                                        ($cotacao['segurado_bairro'] ?? '') ? ', ' . $cotacao['segurado_bairro'] : '',
                                        ($cotacao['segurado_cidade'] ?? '') ? ' — ' . $cotacao['segurado_cidade'] : '',
                                        ($cotacao['segurado_uf'] ?? '') ? '/' . $cotacao['segurado_uf'] : '',
                                    ]));
                                    ?>
                                    <div class="detalhe-field span-full">
                                        <div class="detalhe-field-label">Endereço</div>
                                        <?php dfv($seg_end !== '' ? e($seg_end) : 'Não preenchido') ?>
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
