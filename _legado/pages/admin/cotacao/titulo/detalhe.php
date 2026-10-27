<?php
/**
 * GET /admin/cotacao/titulo/{protocolo}
 * Detalhe de cotação de título de capitalização para a equipe MPS.
 */

$current_user = require_admin();

$protocolo = $params['protocolo'] ?? '';
if (!$protocolo) redirect('/admin/cotacoes');

$cotacao = db_fetch_one(
    "SELECT tc.*, im.nome AS imob_nome,
            u.username AS usuario_nome,
            ua.username AS atendente_nome
     FROM titulo_capitalizacao tc
     LEFT JOIN imobiliarias im ON im.id = tc.imobiliaria_id
     LEFT JOIN users u ON u.id = tc.usuario_id
     LEFT JOIN usuario ua ON ua.id = tc.atendente_id
     WHERE tc.protocolo = ?",
    ['s', $protocolo]
);
if (!$cotacao) redirect('/admin/cotacoes');

$status = $cotacao['status'] ?? '—';
$chat_unread = cotacao_chat_unread($protocolo, 'admin', (int) $current_user->uid);

// ── Anexos/propostas ──────────────────────────────────────────────────────────
$anexos = [];
try {
    $anexos = db_fetch_all(
        "SELECT * FROM cotacoes_anexos_titulo WHERE protocolo = ? ORDER BY data_envio DESC",
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

$status_lower = mb_strtolower($status);
$is_recusado  = str_contains($status_lower, 'recusado') || str_contains($status_lower, 'recusada');
$is_desistencia = cotacao_status_desistencia($status);
$fichaPronta  = str_contains($status_lower, 'aguardando') && str_contains($status_lower, 'análise');
$inquilino_tipo = strtolower(trim($cotacao['inquilino_tipo'] ?? 'pf'));

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
                        <span class="dph-tipo"><?= icon('ticket') ?> Título de Capitalização</span>
                        <span class="status-pill <?= e(status_pill_class($status)) ?>"><?= e(admin_status_label($status, 'titulo')) ?></span>
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
                                        ($cotacao['imovel_complemento'] ?? '') ? ' — ' . $cotacao['imovel_complemento'] : '',
                                        ($cotacao['imovel_bairro'] ?? '') ? ', ' . $cotacao['imovel_bairro'] : '',
                                        ($cotacao['imovel_cidade'] ?? '') ? ' — ' . $cotacao['imovel_cidade'] : '',
                                        ($cotacao['imovel_uf'] ?? '') ? '/' . $cotacao['imovel_uf'] : '',
                                    ]));
                                    ?>
                                    <div class="detalhe-field span-full">
                                        <div class="detalhe-field-label">Endereço do imóvel</div>
                                        <?php dfv($imovel_end !== '' ? e($imovel_end) : 'Não preenchido') ?>
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
                                        ($cotacao['inq_complemento'] ?? '') ? ' — ' . $cotacao['inq_complemento'] : '',
                                        ($cotacao['inq_bairro'] ?? '') ? ', ' . $cotacao['inq_bairro'] : '',
                                        ($cotacao['inq_cidade'] ?? '') ? ' — ' . $cotacao['inq_cidade'] : '',
                                        ($cotacao['inq_uf'] ?? '') ? '/' . $cotacao['inq_uf'] : '',
                                    ]));
                                    ?>
                                    <div class="detalhe-field span-full">
                                        <div class="detalhe-field-label">Endereço</div>
                                        <?php dfv($inq_end !== '' ? e($inq_end) : 'Não preenchido') ?>
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

                    <?php elseif (str_contains($status_lower, 'em an')):
                        $eh_cartao = $cotacao['forma_pagamento'] === 'Cartão';
                    ?>
                    <div class="panel" id="painel-proposta-boleto">
                        <div class="panel-header"><h3 class="panel-title"><?= icon('clipboard-text') ?> <?= $eh_cartao ? 'Enviar proposta e link de pagamento' : 'Enviar proposta e boleto' ?></h3></div>
                        <div class="panel-body">
                            <div class="alert alert-info" style="margin-bottom:var(--space-3)">
                                <?= icon('info') ?>
                                <?= $eh_cartao
                                    ? 'Cliente escolheu pagar por cartão. Envie o PDF da proposta e o link de pagamento.'
                                    : 'Cliente escolheu pagar por boleto. Envie o PDF da proposta e o boleto.' ?>
                            </div>
                            <div class="field" style="margin-bottom:var(--space-3)">
                                <label class="field-label">Arquivo PDF da proposta</label>
                                <div class="field-file-picker" data-file-picker>
                                    <input type="file" id="proposta-arquivo" accept=".pdf" hidden>
                                    <button type="button" class="btn btn-secondary btn-sm" data-file-picker-trigger><?= icon('clipboard-text') ?> Escolher PDF da proposta</button>
                                    <span class="field-file-picker-name" data-file-picker-name>Nenhum arquivo selecionado</span>
                                </div>
                            </div>
                            <?php if ($eh_cartao): ?>
                            <div class="field" style="margin-bottom:var(--space-3)">
                                <label class="field-label" for="link-pagamento">Link de pagamento (cartão)</label>
                                <div class="field-input-wrap">
                                    <span class="field-input-icon"><?= icon('paperclip') ?></span>
                                    <input class="field-input has-icon" type="url" id="link-pagamento"
                                           placeholder="https://...">
                                </div>
                            </div>
                            <?php else: ?>
                            <div class="field" style="margin-bottom:var(--space-3)">
                                <label class="field-label">Arquivo PDF do boleto</label>
                                <div class="field-file-picker" data-file-picker>
                                    <input type="file" id="boleto-arquivo" accept=".pdf" hidden>
                                    <button type="button" class="btn btn-secondary btn-sm" data-file-picker-trigger><?= icon('clipboard-text') ?> Escolher PDF do boleto</button>
                                    <span class="field-file-picker-name" data-file-picker-name>Nenhum arquivo selecionado</span>
                                </div>
                            </div>
                            <?php endif; ?>
                            <button type="button" class="btn btn-primary btn-block" id="btn-enviar-proposta-boleto" disabled><?= icon('paper-plane-tilt') ?> <?= $eh_cartao ? 'Enviar proposta e link' : 'Enviar proposta e boleto' ?></button>
                        </div>
                    </div>

                    <?php elseif ($status === 'Aguardando Pagamento'): ?>
                    <div class="panel">
                        <div class="panel-header"><h3 class="panel-title"><?= icon('clock') ?> Aguardando pagamento</h3></div>
                        <div class="panel-body">
                            <div class="alert alert-info" style="margin-bottom:0"><?= icon('info') ?> Proposta e boleto enviados. Aguardando a imobiliária confirmar o pagamento.</div>
                        </div>
                    </div>

                    <?php elseif ($status === 'Pago'): ?>
                    <div class="panel" id="painel-certificado">
                        <div class="panel-header"><h3 class="panel-title"><?= icon('clipboard-text') ?> Enviar certificado</h3></div>
                        <div class="panel-body">
                            <div class="alert alert-success" style="margin-bottom:var(--space-3)"><?= icon('check-circle') ?> Pagamento confirmado pela imobiliária. Envie o certificado do título.</div>
                            <div class="field" style="margin-bottom:var(--space-3)">
                                <label class="field-label">Arquivo do certificado</label>
                                <div class="field-file-picker" data-file-picker>
                                    <input type="file" id="certificado-arquivo" accept=".pdf" hidden>
                                    <button type="button" class="btn btn-secondary btn-sm" data-file-picker-trigger><?= icon('clipboard-text') ?> Escolher certificado</button>
                                    <span class="field-file-picker-name" data-file-picker-name>Nenhum arquivo selecionado</span>
                                </div>
                            </div>
                            <button type="button" class="btn btn-primary btn-block" id="btn-enviar-certificado" disabled><?= icon('paper-plane-tilt') ?> Enviar certificado</button>
                        </div>
                    </div>

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

                    <?php if (!empty($anexos)):
                        $tipo_arquivo_label = [
                            'proposta'               => 'Proposta',
                            'boleto'                 => 'Boleto',
                            'link_pagamento_cartao'  => 'Link de pagamento (cartão)',
                            'comprovante_pagamento'  => 'Comprovante de pagamento',
                            'certificado'            => 'Certificado',
                        ];
                    ?>
                    <div class="panel" style="margin-top:var(--space-3)">
                        <div class="panel-header"><h3 class="panel-title"><?= icon('list') ?> Anexos enviados</h3></div>
                        <div class="panel-body">
                            <?php foreach ($anexos as $a): ?>
                            <div style="display:flex;align-items:center;justify-content:space-between;padding:var(--space-2) 0;border-bottom:1px solid var(--color-border)">
                                <div>
                                    <strong style="font-size:.85rem"><?= e($tipo_arquivo_label[$a['tipo_arquivo']] ?? ucfirst((string) $a['tipo_arquivo'])) ?></strong>
                                    <br><small><?= date('d/m/Y H:i', strtotime($a['data_envio'])) ?> &middot; <?= e($a['usuario_envio'] ?? '') ?></small>
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

    const csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

    // ── Proposta + boleto/link de pagamento (específico do título) ──────────
    // O boleto vira um input de arquivo (Boleto) ou um input de link/URL
    // (Cartão), dependendo da forma de pagamento escolhida pelo cliente.
    const inputProposta = document.getElementById('proposta-arquivo');
    const inputBoleto    = document.getElementById('boleto-arquivo');
    const inputLinkPagamento = document.getElementById('link-pagamento');
    const btnEnviarPropostaBoleto = document.getElementById('btn-enviar-proposta-boleto');
    if (inputProposta && btnEnviarPropostaBoleto && (inputBoleto || inputLinkPagamento)) {
        const atualizarEstado = () => {
            const segundoCampoOk = inputBoleto
                ? inputBoleto.files.length > 0
                : inputLinkPagamento.value.trim() !== '';
            btnEnviarPropostaBoleto.disabled = inputProposta.files.length === 0 || !segundoCampoOk;
        };
        inputProposta.addEventListener('change', atualizarEstado);
        if (inputBoleto) inputBoleto.addEventListener('change', atualizarEstado);
        if (inputLinkPagamento) inputLinkPagamento.addEventListener('input', atualizarEstado);

        btnEnviarPropostaBoleto.addEventListener('click', () => {
            const fd = new FormData();
            fd.append('proposta', inputProposta.files[0]);
            if (inputBoleto) {
                fd.append('boleto', inputBoleto.files[0]);
            } else {
                fd.append('link_pagamento', inputLinkPagamento.value.trim());
            }
            btnEnviarPropostaBoleto.disabled = true;
            btnEnviarPropostaBoleto.textContent = 'Enviando...';
            fetch(`/api/cotacao/titulo/${proto}/proposta-boleto`, {
                method: 'POST', headers: { 'X-CSRF-Token': csrfToken }, body: fd,
            })
                .then(r => r.json())
                .then(d => handleResp(d, inputBoleto ? 'Proposta e boleto enviados!' : 'Proposta e link enviados!'))
                .catch(() => mpsAlert.error('Erro ao enviar.'))
                .finally(() => { btnEnviarPropostaBoleto.disabled = false; btnEnviarPropostaBoleto.textContent = inputBoleto ? 'Enviar proposta e boleto' : 'Enviar proposta e link'; });
        });
    }

    // ── Certificado (específico do título) ──────────────────────────────────
    const inputCertificado = document.getElementById('certificado-arquivo');
    const btnEnviarCertificado = document.getElementById('btn-enviar-certificado');
    if (inputCertificado && btnEnviarCertificado) {
        inputCertificado.addEventListener('change', () => {
            btnEnviarCertificado.disabled = inputCertificado.files.length === 0;
        });
        btnEnviarCertificado.addEventListener('click', () => {
            const fd = new FormData();
            fd.append('certificado', inputCertificado.files[0]);
            btnEnviarCertificado.disabled = true;
            btnEnviarCertificado.textContent = 'Enviando...';
            fetch(`/api/cotacao/titulo/${proto}/certificado`, {
                method: 'POST', headers: { 'X-CSRF-Token': csrfToken }, body: fd,
            })
                .then(r => r.json())
                .then(d => handleResp(d, 'Certificado enviado!'))
                .catch(() => mpsAlert.error('Erro ao enviar o certificado.'))
                .finally(() => { btnEnviarCertificado.disabled = false; btnEnviarCertificado.textContent = 'Enviar certificado'; });
        });
    }

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
