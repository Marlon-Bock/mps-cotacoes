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

$status              = $cotacao['status'] ?? '�?"';
$liberado            = (int) ($cotacao['liberado'] ?? 1);
$formulario_completo = (int) ($cotacao['formulario_completo'] ?? 1);
$biometria_status    = $cotacao['biometria_status'] ?? null;
$tipo_ficha_lower     = strtolower((string) ($cotacao['tipo_ficha'] ?? 'residencial'));

$preAnalisePendente = $status === 'Aguardando liberação' || $status === 'Aguardando liberacao';
$fichaPronta        = in_array($status, ['Aguardando análise', 'Aguardando preenchimento'], true) && $formulario_completo === 1;

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
$extra_css    = ['dashboard.css', 'cotacao.css', 'cotacao-detalhe.css'];
$extra_js     = [];
$data_criacao     = $cotacao['data_cadastro']    ? date('d/m/Y H:i', strtotime($cotacao['data_cadastro']))    : '—';
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

            <div class="panel">
                <div class="panel-body">
                    <div class="detalhe-header">
                        <div class="detalhe-header-main">
                            <span class="detalhe-proto"><?= e($protocolo) ?></span>
                            <span class="tipo-pill tipo-pill-fianca"><?= icon('house') ?> Fiança <?= $tipo_ficha_lower === 'comercial' ? 'Comercial' : 'Residencial' ?></span>
                            <span class="status-pill <?= e(status_pill_class($status)) ?>"><?= e($status) ?></span>
                        </div>
                        <div class="detalhe-header-info">
                            <div class="detalhe-info-item">
                                <?= icon('buildings') ?>
                                <span class="detalhe-info-label">Imobiliária</span>
                                <span class="detalhe-info-value"><?= e($cotacao['imob_nome'] ?? '—') ?></span>
                            </div>
                            <div class="detalhe-info-item">
                                <?= icon('user') ?>
                                <span class="detalhe-info-label">Usuário</span>
                                <span class="detalhe-info-value"><?= e($cotacao['usuario_nome'] ?? '—') ?></span>
                            </div>
                            <div class="detalhe-info-item">
                                <?= icon('users') ?>
                                <span class="detalhe-info-label">Atendente</span>
                                <span class="detalhe-info-value"><?= e($cotacao['atendente_nome'] ?? 'Não atribuído') ?></span>
                            </div>
                            <div class="detalhe-info-item">
                                <?= icon('clock') ?>
                                <span class="detalhe-info-label">Atualizado em</span>
                                <span class="detalhe-info-value"><?= e($data_atualizacao) ?></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="detalhe-layout cotacao-container">
                <div class="detalhe-left">

                    <div class="panel">
                        <div class="panel-body">

                            <!-- �"?�"? Dados do pretendente �"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"? -->
                            <div class="form-section">
                                <h3 class="form-section-title"><?= icon('user') ?> Dados do pretendente</h3>
                                <div class="detalhe-fields">
                                    <div class="detalhe-field span-full">
                                        <div class="detalhe-field-label">Nome completo</div>
                                        <div class="detalhe-field-value"><?= cv($cotacao['nome_completo']) ?></div>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">CPF<?= $tipo_ficha_lower === 'comercial' ? '/CNPJ' : '' ?></div>
                                        <div class="detalhe-field-value"><?= cv_cpf_cnpj($cotacao['cpf_cnpj']) ?></div>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Data de nascimento</div>
                                        <div class="detalhe-field-value"><?= cv_data($cotacao['data_nascimento']) ?></div>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">WhatsApp</div>
                                        <div class="detalhe-field-value"><?= cv($cotacao['tel_celular']) ?></div>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Telefone residencial</div>
                                        <div class="detalhe-field-value"><?= cv($cotacao['tel_residencial']) ?></div>
                                    </div>
                                    <div class="detalhe-field span-full">
                                        <div class="detalhe-field-label">E-mail</div>
                                        <div class="detalhe-field-value"><?= cv($cotacao['email']) ?></div>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Vínculo empregatício</div>
                                        <div class="detalhe-field-value"><?= cv($cotacao['vinculo_empregaticio']) ?></div>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Salário bruto</div>
                                        <div class="detalhe-field-value"><?= cv_money($cotacao['salario_bruto'] ?? null) ?></div>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Estado civil</div>
                                        <div class="detalhe-field-value"><?= cv($cotacao['estado_civil']) ?></div>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Nº de dependentes</div>
                                        <div class="detalhe-field-value"><?= cv($cotacao['num_dependentes']) ?></div>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Reside no imóvel?</div>
                                        <div class="detalhe-field-value"><?= cv_sim_nao($cotacao['reside_imovel'] ?? null) ?></div>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Tempo de residência</div>
                                        <div class="detalhe-field-value"><?= cv($cotacao['tempo_residencia']) ?></div>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">CEP atual</div>
                                        <div class="detalhe-field-value"><?= cv($cotacao['cep_atual']) ?></div>
                                    </div>
                                    <?php if ($tipo_ficha_lower === 'residencial'): ?>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Sexo</div>
                                        <div class="detalhe-field-value"><?= cv($cotacao['sexo']) ?></div>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Nacionalidade</div>
                                        <div class="detalhe-field-value"><?= cv($cotacao['nacionalidade']) ?></div>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if ($tipo_ficha_lower === 'comercial'): ?>
                            <!-- �"?�"? Dados da empresa �"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"? -->
                            <div class="form-section">
                                <h3 class="form-section-title"><?= icon('buildings') ?> Dados da empresa</h3>
                                <div class="detalhe-fields">
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">CNPJ da empresa</div>
                                        <div class="detalhe-field-value"><?= cv($cotacao['cnpj_empresa']) ?></div>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">CNAE</div>
                                        <div class="detalhe-field-value"><?= cv($cotacao['cnae']) ?></div>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Ramo de atividade</div>
                                        <div class="detalhe-field-value"><?= cv($cotacao['ramo_atividade']) ?></div>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Opção tributária</div>
                                        <div class="detalhe-field-value"><?= cv($cotacao['opcao_tributaria']) ?></div>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Franquia</div>
                                        <div class="detalhe-field-value"><?= cv($cotacao['franquia']) ?></div>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- �"?�"? Imóvel e contrato �"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"? -->
                            <div class="form-section">
                                <h3 class="form-section-title"><?= icon('house') ?> Imóvel e contrato</h3>
                                <?php
                                $endereco_full = implode('', array_filter([
                                    $cotacao['endereco'] ?? '',
                                    ($cotacao['numero'] ?? '')      ? ', ' . $cotacao['numero'] : '',
                                    ($cotacao['complemento'] ?? '') ? ' �?" ' . $cotacao['complemento'] : '',
                                    ($cotacao['bairro'] ?? '')      ? ', ' . $cotacao['bairro'] : '',
                                    ($cotacao['cidade'] ?? '')      ? ' �?" ' . $cotacao['cidade'] : '',
                                    ($cotacao['uf'] ?? '')          ? '/' . $cotacao['uf'] : '',
                                ]));
                                ?>
                                <div class="detalhe-fields">
                                    <div class="detalhe-field span-full">
                                        <div class="detalhe-field-label">Endereço do imóvel</div>
                                        <div class="detalhe-field-value"><?= e($endereco_full ?: '�?"') ?></div>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">CEP</div>
                                        <div class="detalhe-field-value"><?= cv($cotacao['cep']) ?></div>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Vigência do contrato</div>
                                        <div class="detalhe-field-value"><?= ($cotacao['vigencia_contrato'] ?? '') ? cv($cotacao['vigencia_contrato']) . ' meses' : '�?"' ?></div>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Índice de reajuste</div>
                                        <div class="detalhe-field-value"><?= cv($cotacao['indice_reajuste']) ?></div>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Proprietário do imóvel</div>
                                        <div class="detalhe-field-value"><?= cv($cotacao['proprietario_imovel']) ?></div>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">CPF/CNPJ do proprietário</div>
                                        <div class="detalhe-field-value"><?= cv_cpf_cnpj($cotacao['cpf_cnpj_proprietario']) ?></div>
                                    </div>
                                </div>
                            </div>

                            <!-- �"?�"? Valores financeiros �"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"? -->
                            <div class="form-section">
                                <h3 class="form-section-title"><?= icon('currency-dollar') ?> Valores financeiros</h3>
                                <div class="valores-list">
                                    <div class="valores-row"><span>Aluguel</span><span><?= cv_money($cotacao['valor_aluguel'] ?? null) ?></span></div>
                                    <?php if (($cotacao['valor_agua'] ?? 0) > 0): ?>
                                    <div class="valores-row"><span>Água</span><span><?= cv_money($cotacao['valor_agua']) ?></span></div>
                                    <?php endif; ?>
                                    <?php if (($cotacao['valor_condominio'] ?? 0) > 0): ?>
                                    <div class="valores-row"><span>Condomínio</span><span><?= cv_money($cotacao['valor_condominio']) ?></span></div>
                                    <?php endif; ?>
                                    <?php if (($cotacao['valor_gas'] ?? 0) > 0): ?>
                                    <div class="valores-row"><span>Gás</span><span><?= cv_money($cotacao['valor_gas']) ?></span></div>
                                    <?php endif; ?>
                                    <?php if (($cotacao['valor_iptu'] ?? 0) > 0): ?>
                                    <div class="valores-row"><span>IPTU</span><span><?= cv_money($cotacao['valor_iptu']) ?></span></div>
                                    <?php endif; ?>
                                    <?php if (($cotacao['valor_energia'] ?? 0) > 0): ?>
                                    <div class="valores-row"><span>Energia</span><span><?= cv_money($cotacao['valor_energia']) ?></span></div>
                                    <?php endif; ?>
                                    <?php if ($val_encargos > 0): ?>
                                    <div class="valores-row valores-row--subtotal"><span>Total encargos</span><span><?= cv_money($val_encargos) ?></span></div>
                                    <?php endif; ?>
                                    <div class="valores-row valores-row--total"><span>Total geral</span><span><?= cv_money($val_total) ?></span></div>
                                </div>
                            </div>

                            <!-- �"?�"? Coberturas e plano �"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"? -->
                            <div class="form-section">
                                <h3 class="form-section-title"><?= icon('check-circle') ?> Coberturas e plano</h3>
                                <?php if ($cotacao['tipo_plano'] ?? ''): ?>
                                <div class="detalhe-fields" style="margin-bottom:var(--space-4)">
                                    <div class="detalhe-field span-full">
                                        <div class="detalhe-field-label">Tipo de plano</div>
                                        <div class="detalhe-field-value"><?= cv($cotacao['tipo_plano']) ?></div>
                                    </div>
                                </div>
                                <?php endif; ?>
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

                            <!-- �"?�"? Pretendentes adicionais �"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"?�"? -->
                            <?php foreach ($pretendentes_adicionais as $pret): ?>
                            <div class="form-section">
                                <h3 class="form-section-title"><?= icon('users') ?> Pretendente adicional <?= (int) $pret['numero_pretendente'] ?></h3>
                                <div class="detalhe-fields">
                                    <div class="detalhe-field span-full">
                                        <div class="detalhe-field-label">Nome completo</div>
                                        <div class="detalhe-field-value"><?= cv($pret['nome_completo']) ?></div>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">CPF/CNPJ</div>
                                        <div class="detalhe-field-value"><?= cv_cpf_cnpj($pret['cpf_cnpj']) ?></div>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Data de nascimento</div>
                                        <div class="detalhe-field-value"><?= cv_data($pret['data_nascimento']) ?></div>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">WhatsApp</div>
                                        <div class="detalhe-field-value"><?= cv($pret['tel_celular'] ?? null) ?></div>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">E-mail</div>
                                        <div class="detalhe-field-value"><?= cv($pret['email'] ?? null) ?></div>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Vínculo empregatício</div>
                                        <div class="detalhe-field-value"><?= cv($pret['vinculo_empregaticio'] ?? null) ?></div>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Salário bruto</div>
                                        <div class="detalhe-field-value"><?= cv_money($pret['salario_bruto'] ?? null) ?></div>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Estado civil</div>
                                        <div class="detalhe-field-value"><?= cv($pret['estado_civil'] ?? null) ?></div>
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
                                            <small style="color:var(--color-muted)"> · <?= e($h['tipo_evento']) ?></small>
                                        </span>
                                        <span class="timeline-meta"><?= e($h['usuario_nome'] ?? '�?"') ?> &middot; <?= date('d/m/Y H:i', strtotime($h['data_evento'])) ?></span>
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

                    <?php if ($preAnalisePendente): ?>
                    <div class="panel" id="painel-pre-analise">
                        <div class="panel-header"><h3 class="panel-title"><?= icon('magnifying-glass') ?> Pré-análise</h3></div>
                        <div class="panel-body">
                            <div class="alert alert-info" style="margin-bottom:var(--space-3)">
                                <?= icon('info') ?> Formulário parcial recebido. Avalie e libere (ou não) a ficha completa.
                            </div>
                            <div class="field" style="margin-bottom:var(--space-3)">
                                <label class="field-label">Observação</label>
                                <textarea id="pre-analise-obs" class="field-input" rows="3" placeholder="Observação (obrigatória para recusar)..."></textarea>
                            </div>
                            <div style="display:flex;gap:var(--space-2)">
                                <button type="button" class="btn btn-primary" style="flex:1" id="btn-pre-aceitar"><?= icon('check-circle') ?> Aceitar</button>
                                <button type="button" class="btn btn-danger-outline" style="flex:1" id="btn-pre-recusar"><?= icon('x') ?> Recusar</button>
                            </div>
                        </div>
                    </div>

                    <?php elseif (in_array($status, ['Pré-análise aceita', 'Pre-analise aceita'], true) && $formulario_completo === 0): ?>
                    <div class="panel"><div class="panel-body">
                        <div class="alert alert-success" style="margin-bottom:0">
                            <?= icon('check-circle') ?> Pré-análise aceita. Aguardando a imobiliária completar o formulário.
                        </div>
                    </div></div>

                    <?php elseif (in_array($status, ['Pré-análise recusada', 'Pre-analise recusada'], true)): ?>
                    <div class="panel"><div class="panel-body">
                        <div class="alert alert-danger" style="margin-bottom:0">
                            <?= icon('x') ?> Pré-análise recusada. Veja o histórico para o motivo.
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

                    <?php elseif ($status === 'Em Analise' || $status === 'Em Análise'): ?>
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
                        <div class="panel" style="margin-top:var(--space-3)">
                            <div class="panel-header"><h3 class="panel-title"><?= icon('x') ?> Recusar cotação</h3></div>
                            <div class="panel-body">
                                <div class="field" style="margin-bottom:var(--space-2)">
                                    <label class="field-label">Motivo da recusa</label>
                                    <textarea id="recusa-motivo" class="field-input" rows="3" placeholder="Descreva o motivo..."></textarea>
                                </div>
                                <button type="button" class="btn btn-danger-outline btn-block" id="btn-recusar-mps"><?= icon('x') ?> Recusar cotação</button>
                            </div>
                        </div>
                        <?php else: ?>
                        <div class="panel" id="painel-proposta">
                            <div class="panel-header"><h3 class="panel-title"><?= icon('clipboard-text') ?> Enviar proposta</h3></div>
                            <div class="panel-body">
                                <div class="alert alert-info" style="margin-bottom:var(--space-3)">
                                    <?= icon('info') ?> Biometria <?= $biometria_status === 'concluida' ? 'concluída' : 'dispensada' ?> (sem biometria). Envie a proposta para o cliente.
                                </div>
                                <div class="field" style="margin-bottom:var(--space-2)">
                                    <label class="field-label">Arquivo PDF da proposta</label>
                                    <input type="file" id="proposta-arquivo" class="field-input" accept=".pdf">
                                </div>
                                <div class="field" style="margin-bottom:var(--space-3)">
                                    <label class="field-label">Observação (opcional)</label>
                                    <textarea id="proposta-obs" class="field-input" rows="2"></textarea>
                                </div>
                                <button type="button" class="btn btn-primary btn-block" id="btn-enviar-proposta"><?= icon('paper-plane-tilt') ?> Enviar proposta</button>
                            </div>
                        </div>
                        <div class="panel" style="margin-top:var(--space-3)">
                            <div class="panel-header"><h3 class="panel-title"><?= icon('x') ?> Recusar cotação</h3></div>
                            <div class="panel-body">
                                <div class="field" style="margin-bottom:var(--space-2)">
                                    <label class="field-label">Motivo da recusa</label>
                                    <textarea id="recusa-motivo" class="field-input" rows="3"></textarea>
                                </div>
                                <button type="button" class="btn btn-danger-outline btn-block" id="btn-recusar-mps"><?= icon('x') ?> Recusar cotação</button>
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
                                    <input type="file" id="proposta-arquivo" class="field-input" accept=".pdf">
                                </div>
                                <div class="field" style="margin-bottom:var(--space-3)">
                                    <label class="field-label">Observação (opcional)</label>
                                    <textarea id="proposta-obs" class="field-input" rows="2"></textarea>
                                </div>
                                <button type="button" class="btn btn-primary btn-block" id="btn-enviar-proposta"><?= icon('paper-plane-tilt') ?> Enviar nova proposta</button>
                            </div>
                        </div>
                        <?php endif; ?>

                    <?php elseif ($status === 'Concluída' || $status === 'Concluida'): ?>
                    <div class="panel"><div class="panel-body">
                        <div class="alert alert-success" style="margin-bottom:0"><?= icon('check-circle') ?> <strong>Cotação concluída!</strong></div>
                    </div></div>

                    <?php elseif ($status === 'Recusado' || $status === 'Recusada'): ?>
                    <div class="panel"><div class="panel-body">
                        <div class="alert alert-danger" style="margin-bottom:0"><?= icon('x') ?> <strong>Cotação recusada.</strong></div>
                    </div></div>
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

    const btnPreAceitar = document.getElementById('btn-pre-aceitar');
    if (btnPreAceitar) btnPreAceitar.addEventListener('click', () => {
        const obs = document.getElementById('pre-analise-obs').value;
        apiPost('pre-analise', {resultado: 'aceita', observacao: obs}).then(d => handleResp(d, 'Pré-análise aceita!'));
    });
    const btnPreRecusar = document.getElementById('btn-pre-recusar');
    if (btnPreRecusar) btnPreRecusar.addEventListener('click', async () => {
        const obs = document.getElementById('pre-analise-obs').value;
        if (!obs.trim()) { mpsAlert.error('Informe a observação para recusar.'); return; }
        const ok = await mpsAlert.confirm({ message: 'Confirma a recusa da pré-análise?' });
        if (!ok) return;
        apiPost('pre-analise', {resultado: 'recusada', observacao: obs}).then(d => handleResp(d, 'Pré-análise recusada.'));
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
        const motivo = document.getElementById('recusa-motivo').value;
        if (!motivo.trim()) { mpsAlert.error('Informe o motivo da recusa.'); return; }
        const ok = await mpsAlert.confirm({ message: 'Recusar esta cotação?' });
        if (!ok) return;
        apiPost('recusar-mps', {motivo}).then(d => handleResp(d, 'Cotação recusada.'));
    });

    const btnEnviarProposta = document.getElementById('btn-enviar-proposta');
    if (btnEnviarProposta) btnEnviarProposta.addEventListener('click', () => {
        const arquivo = document.getElementById('proposta-arquivo').files[0];
        if (!arquivo) { mpsAlert.error('Selecione o arquivo PDF.'); return; }
        const obs = document.getElementById('proposta-obs').value;
        const fd = new FormData();
        fd.append('arquivo', arquivo);
        fd.append('observacao', obs);
        btnEnviarProposta.disabled = true;
        btnEnviarProposta.textContent = 'Enviando...';
        fetch(`/api/cotacao/${proto}/proposta`, {method: 'POST', body: fd})
            .then(r => r.json())
            .then(d => handleResp(d, 'Proposta enviada!'))
            .catch(() => mpsAlert.error('Erro ao enviar proposta.'))
            .finally(() => { btnEnviarProposta.disabled = false; btnEnviarProposta.textContent = 'Enviar proposta'; });
    });
})();
</script>

<?php require BASE_PATH . '/pages/partials/app-foot.php'; ?>

