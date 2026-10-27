<?php
/**
 * GET /admin/cotacao/fianca/{protocolo}
 * Detalhe e ações de cotação de fiança para a equipe MPS.
 * Usa somente o schema nativo: status (5 valores), liberado, formulario_completo,
 * biometria_url/biometria_status, tabela `historico` e `cotacoes_anexos`.
 */

$current_user = require_admin();

$protocolo = $params['protocolo'] ?? '';
if (!$protocolo) redirect('/admin/cotacoes');

$cotacao = db_fetch_one(
    "SELECT sf.*, im.nome AS imob_nome,
            u.username AS usuario_nome,
            ua.username AS atendente_nome
     FROM seguro_fianca sf
     LEFT JOIN imobiliarias im ON im.id = sf.imobiliaria_id
     LEFT JOIN users u ON u.id = sf.usuario_id
     LEFT JOIN usuario ua ON ua.id = sf.atendente_id
     WHERE sf.protocolo = ?",
    ['s', $protocolo]
);
if (!$cotacao) redirect('/admin/cotacoes');

$status              = $cotacao['status'] ?? '—';
$liberado            = (int) ($cotacao['liberado'] ?? 1);
$formulario_completo = (int) ($cotacao['formulario_completo'] ?? 1);
$biometria_status    = $cotacao['biometria_status'] ?? null;
$tipo_ficha_lower     = strtolower((string) ($cotacao['tipo_ficha'] ?? 'residencial'));

$preAnalisePendente = $status === 'Aguardando liberação' || $status === 'Aguardando liberacao';
$fichaPronta        = in_array($status, ['Aguardando análise', 'Aguardando preenchimento'], true) && $formulario_completo === 1;

$chat_unread = cotacao_chat_unread($protocolo, 'admin', (int) $current_user->uid);

// Pretendentes adicionais
$pretendentes_adicionais = [];
if (($cotacao['pretendenteAdicional'] ?? 'N') === 'S') {
    try {
        $pretendentes_adicionais = db_fetch_all(
            "SELECT * FROM pretendentes WHERE seguro_fianca_id = ? ORDER BY numero_pretendente ASC",
            ['i', (int) $cotacao['id']]
        );
    } catch (Throwable) {}
}

// Histórico (tabela nativa)
$historico = [];
try {
    $historico = db_fetch_all(
        "SELECT * FROM historico WHERE protocolo = ? ORDER BY data_evento ASC",
        ['s', $protocolo]
    );
} catch (Throwable) {}

// Anexos/propostas (tabela nativa cotacoes_anexos)
$anexos = [];
try {
    $anexos = db_fetch_all(
        "SELECT * FROM cotacoes_anexos WHERE protocolo = ? ORDER BY data_envio DESC",
        ['s', $protocolo]
    );
} catch (Throwable) {}
$ultimo_anexo = $anexos[0] ?? null;

// Valores financeiros
$val_aluguel  = (float) ($cotacao['valor_aluguel']    ?? 0);
$val_agua     = (float) ($cotacao['valor_agua']       ?? 0);
$val_cond     = (float) ($cotacao['valor_condominio'] ?? 0);
$val_gas      = (float) ($cotacao['valor_gas']        ?? 0);
$val_iptu     = (float) ($cotacao['valor_iptu']       ?? 0);
$val_energia  = (float) ($cotacao['valor_energia']    ?? 0);
$val_encargos = $val_agua + $val_cond + $val_gas + $val_iptu + $val_energia;
$val_total    = $val_aluguel + $val_encargos;

$page_title   = 'Cotação ' . $protocolo;
$page_back    = ['url' => '/admin/cotacoes', 'label' => 'Cotações', 'icon' => 'arrow-left'];
$extra_css    = ['dashboard.css', 'cotacao.css', 'cotacao-detalhe.css', 'cotacao-fianca-seguradoras.css', 'cotacao-fianca-fluxo.css'];
$extra_js     = [];
$data_criacao     = $cotacao['data_cadastro']    ? date('d/m/Y H:i', strtotime($cotacao['data_cadastro']))    : 'Não preenchido';
$data_atualizacao = $cotacao['data_atualizacao'] ? date('d/m/Y H:i', strtotime($cotacao['data_atualizacao'])) : $data_criacao;

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
                        <span class="dph-tipo"><?= icon('house') ?> Fiança <?= $tipo_ficha_lower === 'comercial' ? 'Comercial' : 'Residencial' ?> &middot; Plano <?= e($cotacao['tipo_plano'] ?? 'Básico') ?></span>
                        <?php require_once BASE_PATH . '/src/helpers/api.php'; $__st = api_status_exibicao((string) $status, (string) $protocolo); ?>
                        <span class="status-pill <?= e($__st['pill']) ?>"><?= e($__st['label']) ?></span>
                    </div>
                    <span class="dph-proto">Cotação #<?= e($protocolo) ?></span>
                    <div class="dph-bar">
                        <span class="dph-bar-item">
                            <?= icon('user') ?>
                            <span class="dph-bar-label">Cliente</span>
                            <span class="dph-bar-value"><?= e($cotacao['nome_completo'] ?? '—') ?></span>
                        </span>
                        <span class="dph-bar-sep">&middot;</span>
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
                            <span class="dph-bar-value"><?= e($data_atualizacao) ?></span>
                        </span>
                        <span class="dph-bar-spacer"></span>
                        <button type="button" class="btn btn-sm btn-chat-cotacao <?= $chat_unread > 0 ? ('btn-danger-outline') : ('btn-secondary') ?>" data-action="abrir-chat-cotacao" data-protocolo="<?= e($protocolo) ?>">
                            <?= icon('chat-circle') ?> Chat da cotação
                            <?php if ($chat_unread > 0): ?><span class="count-badge"><?= $chat_unread > 99 ? '99+' : e($chat_unread) ?></span><?php endif; ?>
                        </button>
                        <a href="<?= e(BASE_URI) ?>/api/cotacao/<?= e($protocolo) ?>/pdf" target="_blank" rel="noopener" class="btn btn-secondary btn-sm btn-pdf">
                            <?= icon('file-pdf') ?> Baixar PDF
                        </a>
                        <?php if (($cotacao['origem'] ?? 'manual') === 'api' && !empty($cotacao['api_enviado_em'])): ?>
                        <a href="<?= e(url('/admin/cotacao/fianca/' . $protocolo . '/seguradoras')) ?>" class="btn btn-secondary btn-sm">
                            <?= icon('buildings') ?> Seguradoras
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <?php $is_admin_aviso = true; require BASE_PATH . '/pages/cotacao/_partials/encaminhamento-mps-aviso.php'; ?>

            <div class="detalhe-layout cotacao-container">
                <div class="detalhe-left">

                    <div class="panel">
                        <div class="panel-body">

                            <!-- ── Dados do pretendente ──────────────────── -->
                            <div class="form-section">
                                <h3 class="form-section-title"><?= icon('user') ?> Dados do pretendente</h3>
                                <div class="detalhe-fields">
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Nome completo</div>
                                        <?php dfv(cv($cotacao['nome_completo'])) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">CPF<?= $tipo_ficha_lower === 'comercial' ? '/CNPJ' : '' ?></div>
                                        <?php dfv(cv_cpf_cnpj($cotacao['cpf_cnpj'])) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Telefone</div>
                                        <?php dfv(cv($cotacao['tel_celular'] ?? null)) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">E-mail</div>
                                        <?php dfv(cv($cotacao['email'] ?? null)) ?>
                                    </div>
                                </div>
                            </div>

                            <!-- ── Imóvel e contrato ─────────────────────── -->
                            <div class="form-section">
                                <h3 class="form-section-title"><?= icon('house') ?> Imóvel e contrato</h3>
                                <?php
                                $endereco_full = implode('', array_filter([
                                    $cotacao['endereco'] ?? '',
                                    ($cotacao['numero'] ?? '')      ? ', ' . $cotacao['numero'] : '',
                                    ($cotacao['complemento'] ?? '') ? ' — ' . $cotacao['complemento'] : '',
                                    ($cotacao['bairro'] ?? '')      ? ', ' . $cotacao['bairro'] : '',
                                    ($cotacao['cidade'] ?? '')      ? ' — ' . $cotacao['cidade'] : '',
                                    ($cotacao['uf'] ?? '')          ? '/' . $cotacao['uf'] : '',
                                ]));
                                ?>
                                <div class="detalhe-fields">
                                    <div class="detalhe-field span-full">
                                        <div class="detalhe-field-label">Endereço do imóvel</div>
                                        <?php dfv($endereco_full !== '' ? e($endereco_full) : 'Não preenchido') ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">CEP</div>
                                        <?php dfv(cv_cep($cotacao['cep'])) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Vigência do contrato</div>
                                        <?php dfv(($cotacao['vigencia_contrato'] ?? '') !== '' ? cv($cotacao['vigencia_contrato']) . ' meses' : 'Não preenchido') ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Índice de reajuste</div>
                                        <?php dfv(cv($cotacao['indice_reajuste'])) ?>
                                    </div>
                                </div>
                            </div>

                            <!-- ── Valores financeiros ───────────────────── -->
                            <div class="form-section">
                                <h3 class="form-section-title"><?= icon('currency-dollar') ?> Valores financeiros</h3>
                                <div class="detalhe-fields">
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Aluguel</div>
                                        <?php dfv(cv_money($cotacao['valor_aluguel'] ?? null)) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Água</div>
                                        <?php dfv(cv_money($cotacao['valor_agua'] ?? null)) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Condomínio</div>
                                        <?php dfv(cv_money($cotacao['valor_condominio'] ?? null)) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Gás</div>
                                        <?php dfv(cv_money($cotacao['valor_gas'] ?? null)) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">IPTU</div>
                                        <?php dfv(cv_money($cotacao['valor_iptu'] ?? null)) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Energia</div>
                                        <?php dfv(cv_money($cotacao['valor_energia'] ?? null)) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Total encargos</div>
                                        <div class="detalhe-field-value detalhe-field-value--strong"><?= cv_money($val_encargos) ?></div>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Total geral</div>
                                        <div class="detalhe-field-value detalhe-field-value--total"><?= cv_money($val_total) ?></div>
                                    </div>
                                </div>
                            </div>

                            <!-- ── Coberturas e plano ────────────────────── -->
                            <div class="form-section">
                                <h3 class="form-section-title"><?= icon('check-circle') ?> Coberturas e plano</h3>
                                <?php
                                $coberturas_map = [
                                    'danos_imovel'     => 'Danos ao imóvel',
                                    'pintura_interna'  => 'Pintura interna',
                                    'pintura_externa'  => 'Pintura externa',
                                    'multa_contratual' => 'Multa contratual',
                                    'cobertura_moveis' => 'Cobertura de móveis',
                                ];
                                ?>
                                <div class="coberturas-badges">
                                    <?php foreach ($coberturas_map as $key => $label):
                                        $ativo = !empty($cotacao[$key]) && (int)$cotacao[$key] === 1;
                                    ?>
                                    <span class="cob-badge <?= $ativo ? 'is-active' : '' ?>">
                                        <?= icon($ativo ? 'check-circle' : 'x') ?> <?= e($label) ?>
                                    </span>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <!-- ── Pretendentes adicionais ──────────────── -->
                            <?php foreach ($pretendentes_adicionais as $pret): ?>
                            <div class="form-section">
                                <h3 class="form-section-title"><?= icon('users') ?> Pretendente adicional <?= (int) $pret['numero_pretendente'] ?></h3>
                                <div class="detalhe-fields">
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Nome completo</div>
                                        <?php dfv(cv($pret['nome_completo'])) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">CPF/CNPJ</div>
                                        <?php dfv(cv_cpf_cnpj($pret['cpf_cnpj'])) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">WhatsApp</div>
                                        <?php dfv(cv($pret['tel_celular'] ?? null)) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">E-mail</div>
                                        <?php dfv(cv($pret['email'] ?? null)) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Vínculo empregatício</div>
                                        <?php dfv(cv($pret['vinculo_empregaticio'] ?? null)) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Salário bruto</div>
                                        <?php dfv(cv_money($pret['salario_bruto'] ?? null)) ?>
                                    </div>
                                </div>
                            </div>
                            <?php endforeach; ?>

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

                <div class="detalhe-right">

                    <?php
                    // Fiança na API das seguradoras: do envio até a apólice ('Cotação API' → 'Aprovado').
                    $__naApi = ($cotacao['origem'] ?? '') === 'api' && !empty($cotacao['api_enviado_em'])
                        && in_array($status, ['Cotação API', 'Aprovado'], true);
                    ?>
                    <?php if ($__naApi): ?>
                    <?php require __DIR__ . '/_api.php'; ?>

                    <?php elseif ($preAnalisePendente): ?>
                    <div class="panel" id="painel-pre-analise">
                        <div class="panel-header"><h3 class="panel-title"><?= icon('magnifying-glass') ?> Pré-análise</h3></div>
                        <div class="panel-body">
                            <div class="alert alert-info" style="margin-bottom:var(--space-3)">
                                <?= icon('info') ?> Formulário parcial recebido. Avalie e libere (ou não) a ficha completa.
                            </div>
                            <div style="display:flex;gap:var(--space-2)">
                                <button type="button" class="btn btn-primary" style="flex:1" id="btn-pre-aceitar"><?= icon('check-circle') ?> Aceitar</button>
                                <button type="button" class="btn btn-danger-outline" style="flex:1" id="btn-pre-recusar"><?= icon('x') ?> Recusar</button>
                            </div>
                        </div>
                    </div>

                    <?php elseif ($status === 'Aguardando preenchimento' && $formulario_completo === 0): ?>
                    <div class="panel"><div class="panel-body">
                        <div class="alert alert-success" style="margin-bottom:0">
                            <?= icon('check-circle') ?> Pré-análise aceita. Aguardando a imobiliária completar o formulário.
                        </div>
                    </div></div>

                    <?php elseif (in_array($status, ['Pré-análise recusada', 'Pre-analise recusada'], true)): ?>
                    <div class="panel"><div class="panel-body">
                        <div class="alert alert-danger" style="margin-bottom:0">
                            <?= icon('x') ?> Pré-análise recusada.
                        </div>
                    </div></div>

                    <?php elseif ($fichaPronta): ?>
                    <div class="panel">
                        <div class="panel-header"><h3 class="panel-title"><?= icon('clipboard-text') ?> Ficha completa recebida</h3></div>
                        <div class="panel-body">
                            <div class="alert alert-info" style="margin-bottom:var(--space-3)"><?= icon('info') ?> Pronta para iniciar a análise.</div>
                            <button type="button" class="btn btn-primary btn-block" id="btn-iniciar-analise"><?= icon('arrow-right') ?> Iniciar análise</button>
                        </div>
                    </div>

                    <?php elseif (in_array($status, ['Em Analise', 'Em Análise', 'Pré-aprovado'], true)): ?>
                        <?php if (!in_array($biometria_status, ['concluida', 'sem_biometria'], true)): ?>
                        <div class="panel" id="painel-biometria">
                            <div class="panel-header"><h3 class="panel-title"><?= icon('magnifying-glass') ?> Biometria</h3></div>
                            <div class="panel-body">
                                <?php if ($biometria_status === 'pendente' && $cotacao['biometria_url']): ?>
                                <div class="alert alert-info" style="margin-bottom:var(--space-3)">
                                    <?= icon('clipboard-text') ?> <a href="<?= e($cotacao['biometria_url']) ?>" target="_blank" style="word-break:break-all"><?= e($cotacao['biometria_url']) ?></a>
                                </div>
                                <button type="button" class="btn btn-primary btn-block" id="btn-concluir-bio" style="margin-bottom:var(--space-2)"><?= icon('check-circle') ?> Marcar como concluída</button>
                                <?php else: ?>
                                <div class="field" style="margin-bottom:var(--space-3)">
                                    <label class="field-label">Link da biometria</label>
                                    <input type="url" id="biometria-link" class="field-input" placeholder="https://...">
                                </div>
                                <button type="button" class="btn btn-primary btn-block" id="btn-enviar-link" style="margin-bottom:var(--space-2)"><?= icon('clipboard-text') ?> Enviar link de biometria</button>
                                <?php endif; ?>
                                <button type="button" class="btn btn-secondary btn-block" id="btn-dispensar"><?= icon('x') ?> Dispensar biometria</button>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="panel" id="painel-proposta">
                            <div class="panel-header"><h3 class="panel-title"><?= icon('clipboard-text') ?> Decisão da cotação</h3></div>
                            <div class="panel-body">
                                <div class="alert alert-info" style="margin-bottom:var(--space-3)">
                                    <?php if ($biometria_status === 'concluida'): ?>
                                        <?= icon('info') ?> Biometria concluída. Aprove para enviar a proposta ou recuse a cotação.
                                    <?php else: ?>
                                        <?= icon('info') ?> Biometria dispensada (sem biometria). Aprove para enviar a proposta ou recuse a cotação.
                                    <?php endif; ?>
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
                        <?php endif; ?>

                    <?php elseif ($status === 'Aprovado'): ?>
                        <?php if ($ultimo_anexo && $ultimo_anexo['status_aprovacao'] === 'Aguardando'): ?>
                        <div class="panel"><div class="panel-header"><h3 class="panel-title"><?= icon('clock') ?> Aguardando decisão</h3></div>
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

                    <?php elseif ($status === 'Concluída' || $status === 'Concluida'): ?>
                    <div class="panel"><div class="panel-body">
                        <div class="alert alert-success" style="margin-bottom:0"><?= icon('check-circle') ?> <strong>Cotação concluída!</strong></div>
                    </div></div>

                    <?php elseif (cotacao_status_desistencia($status)): ?>
                    <div class="panel"><div class="panel-body">
                        <div class="alert alert-desistencia"><?= icon('sign-out') ?> <strong>Cliente desistiu da cotação.</strong></div>
                    </div></div>

                    <?php elseif (cotacao_status_recusado($status)): ?>
                    <div class="panel"><div class="panel-body">
                        <div class="alert alert-danger" style="margin-bottom:0"><?= icon('x') ?> <strong><?= $status === STATUS_RECUSADO_CLIENTE ? 'Cotação recusada pelo cliente.' : 'Cotação recusada.' ?></strong></div>
                    </div></div>
                    <?php endif; ?>

                    <?php if (!cotacao_status_encerrado($status) && !($__naApi && $status === 'Aprovado')): ?>
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
                                    <small><?= !empty($a['data_envio']) ? date('d/m/Y H:i', strtotime($a['data_envio'])) : '—' ?></small>
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

    const btnPreAceitar = document.getElementById('btn-pre-aceitar');
    if (btnPreAceitar) btnPreAceitar.addEventListener('click', () => {
        apiPost('pre-analise', {resultado: 'aceita'}).then(d => handleResp(d, 'Pré-análise aceita!'));
    });
    const btnPreRecusar = document.getElementById('btn-pre-recusar');
    if (btnPreRecusar) btnPreRecusar.addEventListener('click', async () => {
        const ok = await mpsAlert.confirm({ message: 'Confirma a recusa da pré-análise?' });
        if (!ok) return;
        apiPost('pre-analise', {resultado: 'recusada'}).then(d => handleResp(d, 'Pré-análise recusada.'));
    });

    const btnIniciar = document.getElementById('btn-iniciar-analise');
    if (btnIniciar) btnIniciar.addEventListener('click', async () => {
        const ok = await mpsAlert.confirm({ message: 'Iniciar análise desta cotação?' });
        if (!ok) return;
        apiPost('iniciar-analise', {}).then(d => handleResp(d, 'Análise iniciada!'));
    });

    const btnEnviarLink = document.getElementById('btn-enviar-link');
    if (btnEnviarLink) btnEnviarLink.addEventListener('click', () => {
        const link = document.getElementById('biometria-link').value;
        if (!link.trim()) { mpsAlert.error('Informe o link.'); return; }
        apiPost('biometria', {acao: 'enviar_link', link}).then(d => handleResp(d, 'Link de biometria enviado!'));
    });
    const btnDispensar = document.getElementById('btn-dispensar');
    if (btnDispensar) btnDispensar.addEventListener('click', async () => {
        const ok = await mpsAlert.confirm({ message: 'Dispensar biometria?' });
        if (!ok) return;
        apiPost('biometria', {acao: 'dispensar'}).then(d => handleResp(d, 'Biometria dispensada.'));
    });
    const btnConcluirBio = document.getElementById('btn-concluir-bio');
    if (btnConcluirBio) btnConcluirBio.addEventListener('click', async () => {
        const ok = await mpsAlert.confirm({ message: 'Marcar biometria como concluída?' });
        if (!ok) return;
        apiPost('biometria', {acao: 'concluir'}).then(d => handleResp(d, 'Biometria concluída!'));
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

