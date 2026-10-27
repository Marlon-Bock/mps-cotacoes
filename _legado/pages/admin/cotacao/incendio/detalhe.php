<?php
/**
 * GET /admin/cotacao/incendio/{protocolo}
 * Detalhe de cotação de Seguro Incêndio para a equipe MPS. 1 protocolo
 * pode ter N imóveis (seguro_incendio_imoveis) — cada um é um risco
 * segurado independente, com seu próprio ciclo de vida/status/proposta
 * (ver seguro_incendio_recalcular_status() em src/helpers/functions.php).
 * As ações (iniciar análise, aprovar/recusar, enviar proposta, desistir)
 * ficam dentro do card de cada imóvel, não numa coluna lateral única.
 */

$current_user = require_admin();

$protocolo = $params['protocolo'] ?? '';
if (!$protocolo) redirect('/admin/cotacoes');

$cotacao = db_fetch_one(
    "SELECT si.*, im.nome AS imob_nome,
            u.username AS usuario_nome,
            ua.username AS atendente_nome
     FROM seguro_incendio si
     LEFT JOIN imobiliarias im ON im.id = si.imobiliaria_id
     LEFT JOIN users u ON u.id = si.usuario_id
     LEFT JOIN usuario ua ON ua.id = si.atendente_id
     WHERE si.protocolo = ?",
    ['s', $protocolo]
);
if (!$cotacao) redirect('/admin/cotacoes');

$imoveis = db_fetch_all(
    'SELECT * FROM seguro_incendio_imoveis WHERE seguro_incendio_id = ? ORDER BY numero_imovel',
    ['i', $cotacao['id']]
);

// Último anexo de cada tipo (cotacao/proposta/apolice) por imóvel, em lote.
// $anexos sempre definido (mesmo remessa com 0 imóveis) — o partial de
// documentos (documentos-incendio.php) itera ela sem checar isset.
$anexos = [];
$anexos_por_imovel = [];
if ($imoveis) {
    $ids = array_column($imoveis, 'id');
    $placeholders = implode(',', array_fill(0, count($ids), '?'));
    $anexos = db_fetch_all(
        "SELECT * FROM cotacoes_anexos_incendio WHERE seguro_incendio_imovel_id IN ({$placeholders}) ORDER BY data_envio DESC",
        [str_repeat('i', count($ids)), ...$ids]
    );
    foreach ($anexos as $a) {
        $imId = (int) $a['seguro_incendio_imovel_id'];
        $tipo = (string) ($a['tipo_arquivo'] ?? 'proposta');
        if (!isset($anexos_por_imovel[$imId][$tipo])) $anexos_por_imovel[$imId][$tipo] = $a;
    }
}

$status      = $cotacao['status'] ?? '—';
$chat_unread = cotacao_chat_unread($protocolo, 'admin', (int) $current_user->uid);
$qtd_aguardando = count(array_filter($imoveis, fn($im) => ($im['status'] ?? 'Aguardando análise') === 'Aguardando análise'));

// ── Histórico ─────────────────────────────────────────────────────────────────
$historico = [];
try {
    $historico = db_fetch_all(
        "SELECT * FROM historico WHERE protocolo = ? ORDER BY data_evento ASC",
        ['s', $protocolo]
    );
} catch (Throwable) {}

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
                        <span class="dph-tipo"><?= icon('house') ?> Seguro Incêndio</span>
                        <span class="status-pill <?= e(status_pill_class($status)) ?>"><?= e($status) ?></span>
                    </div>
                    <span class="dph-proto">Cotação #<?= e($protocolo) ?></span>
                    <div class="dph-bar">
                        <span class="dph-bar-item">
                            <?= icon('buildings') ?>
                            <span class="dph-bar-label">Imobiliária</span>
                            <span class="dph-bar-value"><?= e($cotacao['imob_nome'] ?? '—') ?></span>
                        </span>
                        <span class="dph-bar-sep">&middot;</span>
                        <span class="dph-bar-item">
                            <?= icon('house') ?>
                            <span class="dph-bar-label">Imóveis</span>
                            <span class="dph-bar-value"><?= count($imoveis) ?></span>
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
                        <?php if ($qtd_aguardando > 1): ?>
                        <button type="button" class="btn btn-sm btn-primary" id="btn-iniciar-analise-geral">
                            <?= icon('arrow-right') ?> Iniciar análise de todos (<?= $qtd_aguardando ?>)
                        </button>
                        <?php endif; ?>
                        <button type="button" class="btn btn-sm btn-chat-cotacao <?= $chat_unread > 0 ? ('btn-danger-outline') : ('btn-secondary') ?>" data-action="abrir-chat-cotacao" data-protocolo="<?= e($protocolo) ?>">
                            <?= icon('chat-circle') ?> Chat da cotação
                            <?php if ($chat_unread > 0): ?><span class="count-badge"><?= $chat_unread > 99 ? '99+' : e($chat_unread) ?></span><?php endif; ?>
                        </button>
                    </div>
                </div>
            </div>

            <div class="detalhe-layout cotacao-container">
                <div class="detalhe-left">

                <?php foreach ($imoveis as $i => $im):
                    $im_status       = $im['status'] ?? 'Aguardando análise';
                    $im_status_lower = mb_strtolower($im_status);
                    $im_is_recusado    = str_contains($im_status_lower, 'recusado') || str_contains($im_status_lower, 'recusada');
                    $im_is_desistencia = cotacao_status_desistencia($im_status);
                    $im_ficha_pronta   = $im_status === 'Aguardando análise';
                    $anexos_do_imovel  = $anexos_por_imovel[$im['id']] ?? [];
                    $ultimo_anexo      = $anexos_do_imovel['proposta'] ?? null;
                    $anexo_cotacao     = $anexos_do_imovel['cotacao'] ?? null;
                    $anexo_apolice     = $anexos_do_imovel['apolice'] ?? null;
                    $eh_fluxo_novo     = ($im['fluxo'] ?? 'antigo') === 'novo';
                    $im_resumo = trim(implode(' — ', array_filter([
                        $im['locador'] ?? '',
                        implode('', array_filter([$im['endereco'] ?? '', ($im['numero'] ?? '') ? ', ' . $im['numero'] : ''])),
                    ])));
                ?>
                <div class="panel" data-imovel-card data-imovel-id="<?= (int) $im['id'] ?>">
                    <button type="button" class="extra-pretendente-toggle" data-imovel-toggle>
                        <span style="display:flex;align-items:center;gap:var(--space-2);min-width:0">
                            <?= icon('house') ?>
                            <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">Imóvel <?= $i + 1 ?><?= $im_resumo ? ' — ' . e($im_resumo) : '' ?></span>
                        </span>
                        <span style="display:flex;align-items:center;gap:var(--space-3);flex-shrink:0">
                            <span class="status-pill <?= e(status_pill_class($im_status)) ?>"><?= e($im_status) ?></span>
                            <?= icon('caret-down', 'extra-pretendente-caret') ?>
                        </span>
                    </button>
                    <div class="extra-pretendente-body" data-imovel-body>
                    <div class="extra-pretendente-body-inner">
                        <div class="detalhe-fields">
                            <div class="detalhe-field">
                                <div class="detalhe-field-label">Proprietário</div>
                                <?php dfv(cv($im['locador'])) ?>
                            </div>
                            <div class="detalhe-field">
                                <div class="detalhe-field-label">CPF/CNPJ do Proprietário</div>
                                <?php dfv(cv_cpf_cnpj($im['cpf_cnpj_locador'])) ?>
                            </div>
                            <div class="detalhe-field">
                                <div class="detalhe-field-label">Inquilino</div>
                                <?php dfv(cv($im['locatario'])) ?>
                            </div>
                            <div class="detalhe-field">
                                <div class="detalhe-field-label">CPF/CNPJ do Inquilino</div>
                                <?php dfv(cv_cpf_cnpj($im['cpf_cnpj_locatario'])) ?>
                            </div>
                            <div class="detalhe-field">
                                <div class="detalhe-field-label">Tipo de imóvel</div>
                                <?php dfv(cv($im['tipo_imovel'])) ?>
                            </div>
                            <div class="detalhe-field">
                                <div class="detalhe-field-label">Atividade exercida</div>
                                <?php dfv(cv($im['atividade_imovel'] ?? null)) ?>
                            </div>
                            <?php
                            $end = implode('', array_filter([
                                $im['endereco'] ?? '',
                                ($im['numero'] ?? '') ? ', ' . $im['numero'] : '',
                                ($im['complemento'] ?? '') ? ' - ' . $im['complemento'] : '',
                                ($im['bairro'] ?? '') ? ', ' . $im['bairro'] : '',
                                ($im['cidade'] ?? '') ? ' - ' . $im['cidade'] : '',
                                ($im['uf'] ?? '') ? '/' . $im['uf'] : '',
                            ]));
                            ?>
                            <div class="detalhe-field span-full">
                                <div class="detalhe-field-label">Endereço</div>
                                <?php dfv(cv($end ?: null)) ?>
                            </div>
                            <div class="detalhe-field">
                                <div class="detalhe-field-label">CEP</div>
                                <?php dfv(cv_cep($im['cep'])) ?>
                            </div>
                            <div class="detalhe-field">
                                <div class="detalhe-field-label">Metragem</div>
                                <?php dfv($im['metragem'] ? e($im['metragem']) . ' m²' : null) ?>
                            </div>
                            <div class="detalhe-field">
                                <div class="detalhe-field-label">Valor do aluguel</div>
                                <?php dfv(cv_money($im['valor_aluguel'])) ?>
                            </div>
                            <div class="detalhe-field">
                                <div class="detalhe-field-label">Parcelas</div>
                                <?php dfv($im['parcelas'] ? $im['parcelas'] . 'x' : null) ?>
                            </div>
                            <div class="detalhe-field">
                                <div class="detalhe-field-label">Valor do Incêndio</div>
                                <?php dfv(cv_money($im['valor_incendio'] ?? null)) ?>
                            </div>
                            <?php if (!empty($im['codigo_imovel_sami'])): ?>
                            <div class="detalhe-field">
                                <div class="detalhe-field-label">Código Sami</div>
                                <?php dfv(cv($im['codigo_imovel_sami'])) ?>
                            </div>
                            <?php endif; ?>
                        </div>

                        <?php
                        $coberturas_incendio_detalhe = [
                            'valor_danos_eletricos'        => 'Danos Elétricos',
                            'valor_impacto_veiculos'       => 'Impacto de Veículos Terrestres e Aéreos',
                            'valor_vendaval'                => 'Vendaval',
                            'valor_perda_aluguel'           => 'Perda de Aluguel',
                            'valor_incendio_conteudo'       => 'Incêndio do conteúdo (inquilino)',
                            'valor_responsabilidade_civil'  => 'Responsabilidade Civil',
                        ];
                        $tem_cobertura_informada = false;
                        foreach ($coberturas_incendio_detalhe as $campo_cob => $rotulo_cob) {
                            if (!empty($im[$campo_cob])) { $tem_cobertura_informada = true; break; }
                        }
                        ?>
                        <?php if ($tem_cobertura_informada): ?>
                        <div class="detalhe-fields" style="margin-top:var(--space-4)">
                            <?php foreach ($coberturas_incendio_detalhe as $campo_cob => $rotulo_cob): ?>
                            <div class="detalhe-field">
                                <div class="detalhe-field-label"><?= e($rotulo_cob) ?></div>
                                <?php dfv(cv_money($im[$campo_cob] ?? null)) ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <p class="field-hint">A imobiliária não informou os valores de cobertura — definir na análise.</p>
                        <?php endif; ?>
                    </div><!-- /.extra-pretendente-body-inner -->
                    </div><!-- /.extra-pretendente-body -->

                    <!-- Ação deste imóvel — fica FORA do colapsável, sempre visível -->
                    <div class="imovel-acoes">
                            <?php if ($im_ficha_pronta): ?>
                            <div class="imovel-acoes-hint"><?= icon('info') ?> Pronto para iniciar a análise.</div>
                            <div class="imovel-acoes-row">
                                <button type="button" class="btn btn-sm btn-primary" data-acao="iniciar-analise"><?= icon('arrow-right') ?> Iniciar análise</button>
                                <button type="button" class="btn btn-sm btn-secondary imovel-acoes-secundaria" data-acao="desistir"><?= icon('sign-out') ?> Desistiu</button>
                            </div>

                            <?php elseif ($im_status === 'Em Analise' && $eh_fluxo_novo): ?>
                            <div class="imovel-acoes-hint"><?= icon('info') ?> Envie o PDF da cotação para a imobiliária aprovar, ou recuse este imóvel.</div>
                            <div class="imovel-acoes-row" data-decisao-escolha>
                                <button type="button" class="btn btn-sm btn-primary" data-acao="ir-cotacao"><?= icon('clipboard-text') ?> Enviar cotação</button>
                                <button type="button" class="btn btn-sm btn-danger-outline" data-acao="recusar-mps"><?= icon('x') ?> Recusar imóvel</button>
                                <button type="button" class="btn btn-sm btn-secondary imovel-acoes-secundaria" data-acao="desistir"><?= icon('sign-out') ?> Desistiu</button>
                            </div>
                            <div data-decisao-proposta hidden>
                                <div class="field" style="margin-bottom:var(--space-3)">
                                    <label class="field-label">Arquivo PDF da cotação</label>
                                    <div class="field-file-picker" data-file-picker>
                                        <input type="file" data-proposta-arquivo accept=".pdf" hidden>
                                        <button type="button" class="btn btn-secondary btn-sm" data-file-picker-trigger><?= icon('clipboard-text') ?> Escolher arquivo PDF</button>
                                        <span class="field-file-picker-name" data-file-picker-name>Nenhum arquivo selecionado</span>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-sm btn-primary" data-acao="enviar-cotacao" disabled><?= icon('paper-plane-tilt') ?> Enviar cotação</button>
                            </div>

                            <?php elseif ($im_status === 'Aguardando aprovação'): ?>
                            <?php if ($anexo_cotacao && $anexo_cotacao['status_aprovacao'] === 'Recusada'): ?>
                                <div class="imovel-acoes-hint is-warning"><?= icon('warning') ?> O cliente recusou a cotação deste imóvel. Envie uma nova ou encerre o imóvel.</div>
                                <div class="field" style="margin-bottom:var(--space-2)">
                                    <label class="field-label">Novo arquivo PDF da cotação</label>
                                    <div class="field-file-picker" data-file-picker>
                                        <input type="file" data-proposta-arquivo accept=".pdf" hidden>
                                        <button type="button" class="btn btn-secondary btn-sm" data-file-picker-trigger><?= icon('clipboard-text') ?> Escolher arquivo PDF</button>
                                        <span class="field-file-picker-name" data-file-picker-name>Nenhum arquivo selecionado</span>
                                    </div>
                                </div>
                                <div class="imovel-acoes-row">
                                    <button type="button" class="btn btn-sm btn-primary" data-acao="enviar-cotacao"><?= icon('paper-plane-tilt') ?> Enviar nova cotação</button>
                                    <button type="button" class="btn btn-sm btn-danger-outline" data-acao="recusar-cotacao-cliente"><?= icon('x') ?> Recusado pelo cliente</button>
                                    <button type="button" class="btn btn-sm btn-secondary imovel-acoes-secundaria" data-acao="desistir"><?= icon('sign-out') ?> Desistiu</button>
                                </div>
                            <?php else: ?>
                                <div class="imovel-acoes-hint"><?= icon('info') ?> Cotação enviada. Aguardando aprovação da imobiliária.</div>
                                <div class="imovel-acoes-row">
                                    <?php if ($anexo_cotacao): ?>
                                    <a href="<?= e($anexo_cotacao['caminho_arquivo']) ?>" target="_blank" class="btn btn-sm btn-secondary"><?= icon('arrow-right') ?> Ver cotação enviada</a>
                                    <?php endif; ?>
                                    <button type="button" class="btn btn-sm btn-secondary imovel-acoes-secundaria" data-acao="desistir"><?= icon('sign-out') ?> Desistiu</button>
                                </div>
                            <?php endif; ?>

                            <?php elseif ($im_status === 'Aguardando nova cotação'):
                                $__pedido_alteracao = db_fetch_one(
                                    "SELECT observacao FROM historico WHERE protocolo = ? AND tipo_evento = 'ALTERACAO_SOLICITADA' AND observacao LIKE ? ORDER BY data_evento DESC LIMIT 1",
                                    ['ss', $protocolo, "Imóvel {$im['numero_imovel']}:%"]
                                );
                            ?>
                            <div class="imovel-acoes-hint is-warning"><?= icon('warning') ?> A imobiliária pediu alteração nesta cotação.</div>
                            <?php if ($__pedido_alteracao):
                                preg_match('/—\s*"(.+)"\s*$/s', (string) $__pedido_alteracao['observacao'], $__m);
                                $__texto_pedido = $__m[1] ?? $__pedido_alteracao['observacao'];
                            ?>
                            <div class="pedido-alteracao-box">
                                <span class="pedido-alteracao-box-label">Pedido de alteração da imobiliária</span>
                                <span class="pedido-alteracao-box-texto"><?= e($__texto_pedido) ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="field" style="margin-bottom:var(--space-2)">
                                <label class="field-label">Novo arquivo PDF da cotação</label>
                                <div class="field-file-picker" data-file-picker>
                                    <input type="file" data-proposta-arquivo accept=".pdf" hidden>
                                    <button type="button" class="btn btn-secondary btn-sm" data-file-picker-trigger><?= icon('clipboard-text') ?> Escolher arquivo PDF</button>
                                    <span class="field-file-picker-name" data-file-picker-name>Nenhum arquivo selecionado</span>
                                </div>
                            </div>
                            <div class="imovel-acoes-row">
                                <button type="button" class="btn btn-sm btn-primary" data-acao="enviar-cotacao"><?= icon('paper-plane-tilt') ?> Enviar nova cotação</button>
                                <button type="button" class="btn btn-sm btn-secondary imovel-acoes-secundaria" data-acao="desistir"><?= icon('sign-out') ?> Desistiu</button>
                            </div>

                            <?php elseif ($im_status === 'Cotação aprovada'): ?>
                            <div class="imovel-acoes-hint"><?= icon('check-circle') ?> Cotação aprovada pelo cliente. Envie o PDF da proposta.</div>
                            <div class="field" style="margin-bottom:var(--space-3)">
                                <label class="field-label">Arquivo PDF da proposta</label>
                                <div class="field-file-picker" data-file-picker>
                                    <input type="file" data-proposta-arquivo accept=".pdf" hidden>
                                    <button type="button" class="btn btn-secondary btn-sm" data-file-picker-trigger><?= icon('clipboard-text') ?> Escolher arquivo PDF</button>
                                    <span class="field-file-picker-name" data-file-picker-name>Nenhum arquivo selecionado</span>
                                </div>
                            </div>
                            <div class="imovel-acoes-row">
                                <button type="button" class="btn btn-sm btn-primary" data-acao="enviar-proposta" disabled><?= icon('paper-plane-tilt') ?> Enviar proposta</button>
                                <button type="button" class="btn btn-sm btn-secondary imovel-acoes-secundaria" data-acao="desistir"><?= icon('sign-out') ?> Desistiu</button>
                            </div>

                            <?php elseif ($im_status === 'Aguardando apólice'): ?>
                            <div class="imovel-acoes-hint"><?= icon('info') ?> Proposta enviada. Aguardando emissão da apólice — anexe o PDF quando estiver pronta.</div>
                            <?php $__docs_imovel = array_filter(['Cotação enviada' => $anexo_cotacao, 'Proposta enviada' => $ultimo_anexo, 'Apólice emitida' => $anexo_apolice]); ?>
                            <?php if ($__docs_imovel): ?>
                            <div style="display:flex;flex-wrap:wrap;gap:var(--space-2);margin-top:var(--space-3)">
                                <?php foreach ($__docs_imovel as $__rotulo => $__doc): ?>
                                <a href="<?= e($__doc['caminho_arquivo']) ?>" target="_blank" class="btn btn-sm btn-secondary"><?= icon('arrow-right') ?> <?= e($__rotulo) ?></a>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                            <div class="field" style="margin-top:var(--space-4)">
                                <label class="field-label">Arquivo PDF da apólice</label>
                                <div class="field-file-picker" data-file-picker>
                                    <input type="file" data-proposta-arquivo accept=".pdf" hidden>
                                    <button type="button" class="btn btn-secondary btn-sm" data-file-picker-trigger><?= icon('clipboard-text') ?> Escolher arquivo PDF</button>
                                    <span class="field-file-picker-name" data-file-picker-name>Nenhum arquivo selecionado</span>
                                </div>
                            </div>
                            <div class="imovel-acoes-row" style="margin-top:var(--space-4);justify-content:space-between">
                                <button type="button" class="btn btn-sm btn-primary" data-acao="enviar-apolice" disabled><?= icon('paper-plane-tilt') ?> Enviar apólice</button>
                                <button type="button" class="btn btn-sm btn-secondary imovel-acoes-secundaria" data-acao="desistir"><?= icon('sign-out') ?> Desistir deste imóvel</button>
                            </div>

                            <?php elseif ($im_status === 'Em Analise'): ?>
                            <div class="imovel-acoes-hint"><?= icon('info') ?> Aprove para enviar a proposta ou recuse este imóvel.</div>
                            <div class="imovel-acoes-row" data-decisao-escolha>
                                <button type="button" class="btn btn-sm btn-primary" data-acao="ir-aprovar"><?= icon('check-circle') ?> Aprovar imóvel</button>
                                <button type="button" class="btn btn-sm btn-danger-outline" data-acao="recusar-mps"><?= icon('x') ?> Recusar imóvel</button>
                                <button type="button" class="btn btn-sm btn-secondary imovel-acoes-secundaria" data-acao="desistir"><?= icon('sign-out') ?> Desistiu</button>
                            </div>
                            <div data-decisao-proposta hidden>
                                <div class="field" style="margin-bottom:var(--space-3)">
                                    <label class="field-label">Arquivo PDF da proposta</label>
                                    <div class="field-file-picker" data-file-picker>
                                        <input type="file" data-proposta-arquivo accept=".pdf" hidden>
                                        <button type="button" class="btn btn-secondary btn-sm" data-file-picker-trigger><?= icon('clipboard-text') ?> Escolher arquivo PDF</button>
                                        <span class="field-file-picker-name" data-file-picker-name>Nenhum arquivo selecionado</span>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-sm btn-primary" data-acao="enviar-proposta" disabled><?= icon('paper-plane-tilt') ?> Enviar proposta</button>
                            </div>

                            <?php elseif ($im_status === 'Alteração solicitada'):
                                $__pedido_alteracao_proposta = db_fetch_one(
                                    "SELECT observacao FROM historico WHERE protocolo = ? AND tipo_evento = 'ALTERACAO_SOLICITADA' AND observacao LIKE ? ORDER BY data_evento DESC LIMIT 1",
                                    ['ss', $protocolo, "Imóvel {$im['numero_imovel']}:%"]
                                );
                            ?>
                            <div class="imovel-acoes-hint is-warning"><?= icon('warning') ?> A imobiliária pediu alteração nesta proposta.</div>
                            <?php if ($__pedido_alteracao_proposta):
                                preg_match('/—\s*"(.+)"\s*$/s', (string) $__pedido_alteracao_proposta['observacao'], $__m2);
                                $__texto_pedido_proposta = $__m2[1] ?? $__pedido_alteracao_proposta['observacao'];
                            ?>
                            <div class="pedido-alteracao-box">
                                <span class="pedido-alteracao-box-label">Pedido de alteração da imobiliária</span>
                                <span class="pedido-alteracao-box-texto"><?= e($__texto_pedido_proposta) ?></span>
                            </div>
                            <?php endif; ?>
                            <div class="field" style="margin-bottom:var(--space-2)">
                                <label class="field-label">Novo arquivo PDF</label>
                                <div class="field-file-picker" data-file-picker>
                                    <input type="file" data-proposta-arquivo accept=".pdf" hidden>
                                    <button type="button" class="btn btn-secondary btn-sm" data-file-picker-trigger><?= icon('clipboard-text') ?> Escolher arquivo PDF</button>
                                    <span class="field-file-picker-name" data-file-picker-name>Nenhum arquivo selecionado</span>
                                </div>
                            </div>
                            <div class="imovel-acoes-row">
                                <button type="button" class="btn btn-sm btn-primary" data-acao="enviar-proposta"><?= icon('paper-plane-tilt') ?> Enviar nova proposta</button>
                                <button type="button" class="btn btn-sm btn-secondary imovel-acoes-secundaria" data-acao="desistir"><?= icon('sign-out') ?> Desistiu</button>
                            </div>

                            <?php elseif ($im_status === 'Aprovado'):
                                if ($ultimo_anexo && $ultimo_anexo['status_aprovacao'] === 'Aguardando'): ?>
                                <div class="imovel-acoes-hint"><?= icon('info') ?> Proposta enviada. Aguardando decisão da imobiliária/cliente.</div>
                                <div class="imovel-acoes-row">
                                    <a href="<?= e($ultimo_anexo['caminho_arquivo']) ?>" target="_blank" class="btn btn-sm btn-secondary"><?= icon('arrow-right') ?> Ver proposta enviada</a>
                                    <button type="button" class="btn btn-sm btn-secondary imovel-acoes-secundaria" data-acao="desistir"><?= icon('sign-out') ?> Desistiu</button>
                                </div>
                                <?php elseif ($ultimo_anexo && $ultimo_anexo['status_aprovacao'] === 'Recusada'): ?>
                                <div class="imovel-acoes-hint is-warning"><?= icon('warning') ?> O cliente recusou a proposta anterior deste imóvel.</div>
                                <div class="field" style="margin-bottom:var(--space-2)">
                                    <label class="field-label">Novo arquivo PDF</label>
                                    <div class="field-file-picker" data-file-picker>
                                        <input type="file" data-proposta-arquivo accept=".pdf" hidden>
                                        <button type="button" class="btn btn-secondary btn-sm" data-file-picker-trigger><?= icon('clipboard-text') ?> Escolher arquivo PDF</button>
                                        <span class="field-file-picker-name" data-file-picker-name>Nenhum arquivo selecionado</span>
                                    </div>
                                </div>
                                <div class="imovel-acoes-row">
                                    <button type="button" class="btn btn-sm btn-primary" data-acao="enviar-proposta"><?= icon('paper-plane-tilt') ?> Enviar nova proposta</button>
                                    <button type="button" class="btn btn-sm btn-danger-outline" data-acao="recusar-cliente"><?= icon('x') ?> Recusado pelo cliente</button>
                                    <button type="button" class="btn btn-sm btn-secondary imovel-acoes-secundaria" data-acao="desistir"><?= icon('sign-out') ?> Desistiu</button>
                                </div>
                                <?php else: ?>
                                <div class="imovel-acoes-hint"><?= icon('info') ?> Imóvel aprovado. Envie o arquivo da proposta.</div>
                                <div class="field" style="margin-bottom:var(--space-3)">
                                    <label class="field-label">Arquivo PDF da proposta</label>
                                    <div class="field-file-picker" data-file-picker>
                                        <input type="file" data-proposta-arquivo accept=".pdf" hidden>
                                        <button type="button" class="btn btn-secondary btn-sm" data-file-picker-trigger><?= icon('clipboard-text') ?> Escolher arquivo PDF</button>
                                        <span class="field-file-picker-name" data-file-picker-name>Nenhum arquivo selecionado</span>
                                    </div>
                                </div>
                                <div class="imovel-acoes-row">
                                    <button type="button" class="btn btn-sm btn-primary" data-acao="enviar-proposta"><?= icon('paper-plane-tilt') ?> Enviar proposta</button>
                                    <button type="button" class="btn btn-sm btn-secondary imovel-acoes-secundaria" data-acao="desistir"><?= icon('sign-out') ?> Desistiu</button>
                                </div>
                                <?php endif; ?>

                            <?php elseif (in_array($im_status, ['Concluída', 'Concluida', 'Concluído', 'Concluido'], true)): ?>
                            <div class="imovel-status-final is-concluido">
                                <span class="imovel-status-final-icon"><?= icon('check-circle') ?></span>
                                <span><strong><?= $eh_fluxo_novo ? 'Processo concluído.' : 'Proposta aceita pelo cliente! Imóvel concluído.' ?></strong></span>
                            </div>
                            <?php $__docs_imovel = array_filter(['Cotação enviada' => $anexo_cotacao, 'Proposta enviada' => $ultimo_anexo, 'Apólice emitida' => $anexo_apolice]); ?>
                            <?php if ($__docs_imovel): ?>
                            <div style="display:flex;flex-wrap:wrap;gap:var(--space-2);margin-top:var(--space-3)">
                                <?php foreach ($__docs_imovel as $__rotulo => $__doc): ?>
                                <a href="<?= e($__doc['caminho_arquivo']) ?>" target="_blank" class="btn btn-sm btn-secondary"><?= icon('arrow-right') ?> <?= e($__rotulo) ?></a>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>

                            <?php elseif ($im_is_desistencia): ?>
                            <div class="imovel-status-final is-desistencia">
                                <span class="imovel-status-final-icon"><?= icon('sign-out') ?></span>
                                <span><strong>Cliente desistiu deste imóvel.</strong></span>
                            </div>

                            <?php elseif ($im_is_recusado): ?>
                            <div class="imovel-status-final is-recusado">
                                <span class="imovel-status-final-icon"><?= icon('x') ?></span>
                                <span><strong><?= $im_status === STATUS_RECUSADO_CLIENTE ? 'Recusado pelo cliente.' : 'Recusado pela MPS.' ?></strong></span>
                            </div>
                            <?php endif; ?>
                    </div><!-- /.imovel-acoes -->
                </div><!-- /.panel -->
                <?php endforeach; ?>

                </div><!-- /.detalhe-left -->

                <div class="detalhe-right">
                    <?php require BASE_PATH . '/pages/cotacao/_partials/documentos-incendio.php'; ?>
                    <?php if (!empty($historico)): ?>
                    <div class="panel">
                        <div class="panel-header"><h3 class="panel-title"><?= icon('clock') ?> Histórico</h3></div>
                        <div class="panel-body">
                            <div class="status-timeline" style="max-height:420px;overflow-y:auto;padding-right:var(--space-2)">
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
                </div><!-- /.detalhe-right -->

            </div><!-- /.detalhe-layout -->

        </main>
    </div>
</div>

<script>
(function () {
    const proto = <?= json_encode($protocolo) ?>;

    function apiPost(imovelId, endpoint, body) {
        return fetch(`/api/cotacao/incendio/${proto}/imovel/${imovelId}/${endpoint}`, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams(body),
        }).then(r => r.json());
    }
    function handleResp(data, successMsg) {
        if (data.success) { mpsAlert.success(successMsg || data.message); setTimeout(() => location.reload(), 1200); }
        else { mpsAlert.error(data.message || 'Tente novamente.'); }
    }

    const btnIniciarGeral = document.getElementById('btn-iniciar-analise-geral');
    btnIniciarGeral?.addEventListener('click', async () => {
        const ok = await mpsAlert.confirm({ message: 'Iniciar análise de todos os imóveis ainda aguardando?' });
        if (!ok) return;

        const ids = Array.from(document.querySelectorAll('[data-imovel-card] [data-acao="iniciar-analise"]'))
            .map(btn => btn.closest('[data-imovel-card]').dataset.imovelId);

        btnIniciarGeral.disabled = true;
        btnIniciarGeral.textContent = 'Iniciando...';

        const resultados = await Promise.all(ids.map(id => apiPost(id, 'iniciar-analise', {})));
        const falhas = resultados.filter(r => !r.success);

        if (falhas.length === 0) {
            mpsAlert.success('Análise iniciada em todos os imóveis!');
        } else {
            mpsAlert.error(`${falhas.length} de ${ids.length} imóveis não puderam ser iniciados. Recarregando...`);
        }
        setTimeout(() => location.reload(), 1200);
    });

    document.querySelectorAll('[data-imovel-card]').forEach(card => {
        const imovelId = card.dataset.imovelId;

        const toggle = card.querySelector('[data-imovel-toggle]');
        const body   = card.querySelector('[data-imovel-body]');
        toggle?.addEventListener('click', () => {
            toggle.classList.toggle('is-open');
            body?.classList.toggle('is-open');
        });

        card.querySelector('[data-acao="iniciar-analise"]')?.addEventListener('click', async () => {
            const ok = await mpsAlert.confirm({ message: 'Iniciar análise deste imóvel?' });
            if (!ok) return;
            apiPost(imovelId, 'iniciar-analise', {}).then(d => handleResp(d, 'Análise iniciada!'));
        });

        card.querySelector('[data-acao="recusar-mps"]')?.addEventListener('click', async () => {
            const ok = await mpsAlert.confirm({ message: 'Recusar este imóvel?' });
            if (!ok) return;
            apiPost(imovelId, 'recusar-mps', {}).then(d => handleResp(d, 'Imóvel recusado.'));
        });

        const decisaoEscolha  = card.querySelector('[data-decisao-escolha]');
        const decisaoProposta = card.querySelector('[data-decisao-proposta]');
        const revelarUpload = () => {
            if (decisaoEscolha)  decisaoEscolha.hidden  = true;
            if (decisaoProposta) decisaoProposta.hidden = false;
        };
        card.querySelector('[data-acao="ir-aprovar"]')?.addEventListener('click', revelarUpload);
        card.querySelector('[data-acao="ir-cotacao"]')?.addEventListener('click', revelarUpload);

        const inputArquivo = card.querySelector('[data-proposta-arquivo]');
        const btnEnviar    = card.querySelector('[data-acao="enviar-proposta"], [data-acao="enviar-cotacao"], [data-acao="enviar-apolice"]');
        const endpointUpload = {
            'enviar-proposta': 'proposta',
            'enviar-cotacao':  'cotacao',
            'enviar-apolice':  'apolice',
        };
        const textoUpload = {
            'enviar-proposta': 'Enviar proposta',
            'enviar-cotacao':  'Enviar cotação',
            'enviar-apolice':  'Enviar apólice',
        };
        if (inputArquivo && btnEnviar) {
            inputArquivo.addEventListener('change', () => {
                btnEnviar.disabled = inputArquivo.files.length === 0;
            });
        }
        card.querySelectorAll('[data-acao="enviar-proposta"], [data-acao="enviar-cotacao"], [data-acao="enviar-apolice"]').forEach(botao => {
            const acao = botao.dataset.acao;
            botao.addEventListener('click', () => {
                const input = botao.closest('[data-imovel-card]').querySelector('[data-proposta-arquivo]');
                const arquivo = input?.files[0];
                if (!arquivo) { mpsAlert.error('Selecione o arquivo PDF.'); return; }
                const fd = new FormData();
                fd.append('arquivo', arquivo);
                botao.disabled = true;
                botao.textContent = 'Enviando...';
                fetch(`/api/cotacao/incendio/${proto}/imovel/${imovelId}/${endpointUpload[acao]}`, {method: 'POST', body: fd})
                    .then(r => r.json())
                    .then(d => handleResp(d, 'Arquivo enviado!'))
                    .catch(() => mpsAlert.error('Erro ao enviar arquivo.'))
                    .finally(() => { botao.disabled = false; botao.textContent = textoUpload[acao]; });
            });
        });

        card.querySelector('[data-acao="recusar-cotacao-cliente"]')?.addEventListener('click', async () => {
            const ok = await mpsAlert.confirm({ message: 'Encerrar este imóvel como recusado pelo cliente?' });
            if (!ok) return;
            apiPost(imovelId, 'cotacao-recusar-cliente', {}).then(d => handleResp(d, 'Imóvel recusado pelo cliente.'));
        });

        card.querySelector('[data-acao="recusar-cliente"]')?.addEventListener('click', async () => {
            const ok = await mpsAlert.confirm({ message: 'Encerrar este imóvel como recusado pelo cliente?' });
            if (!ok) return;
            apiPost(imovelId, 'recusar-cliente', {}).then(d => handleResp(d, 'Imóvel recusado pelo cliente.'));
        });

        card.querySelector('[data-acao="desistir"]')?.addEventListener('click', async () => {
            const ok = await mpsAlert.confirm({ message: 'Encerrar este imóvel por desistência do cliente? Essa ação não pode ser desfeita.' });
            if (!ok) return;
            apiPost(imovelId, 'desistir', {}).then(d => handleResp(d, 'Imóvel encerrado por desistência.'));
        });
    });
})();
</script>

<?php require BASE_PATH . '/pages/partials/app-foot.php'; ?>
