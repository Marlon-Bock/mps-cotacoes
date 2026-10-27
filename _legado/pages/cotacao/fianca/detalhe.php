<?php
$current_user = require_perm('perm_cotar', 'perm_fianca');

$protocolo      = $params['protocolo'] ?? '';
if (!$protocolo) redirect('/cotacoes');

$imobiliaria_id = (int)($current_user->imobiliaria_id ?? 0);

$cotacao = db_fetch_one(
    "SELECT sf.*, im.nome AS imob_nome,
            u.username AS usuario_nome,
            ua.username AS atendente_nome
     FROM seguro_fianca sf
     LEFT JOIN imobiliarias im ON im.id = sf.imobiliaria_id
     LEFT JOIN users u ON u.id = sf.usuario_id
     LEFT JOIN usuario ua ON ua.id = sf.atendente_id
     WHERE sf.protocolo = ? AND sf.imobiliaria_id = ?",
    ['si', $protocolo, $imobiliaria_id]
);
if (!$cotacao) redirect('/cotacoes');
// Operador comum só acessa a própria cotação (mesma regra de "Minhas Cotações").
if (empty($current_user->imob_admin) && (int) ($cotacao['usuario_id'] ?? 0) !== (int) $current_user->uid) {
    redirect('/cotacoes');
}

$status = $cotacao['status'] ?? '—';
$chat_unread = cotacao_chat_unread($protocolo, 'imobiliaria', (int) $current_user->uid);

// ── Anexo ─────────────────────────────────────────────────────────────────────
$anexo = null;
try {
    $anexo = db_fetch_one(
        "SELECT * FROM cotacoes_anexos WHERE protocolo = ? ORDER BY data_envio DESC LIMIT 1",
        ['s', $protocolo]
    );
} catch (Throwable) {}

// ── Pretendentes adicionais ───────────────────────────────────────────────────
$pretendentes_adicionais = [];
if (($cotacao['pretendenteAdicional'] ?? 'N') === 'S') {
    try {
        $pretendentes_adicionais = db_fetch_all(
            "SELECT * FROM pretendentes WHERE seguro_fianca_id = ? ORDER BY numero_pretendente ASC",
            ['i', (int)$cotacao['id']]
        );
    } catch (Throwable) {}
}

// ── Histórico (tabela nativa) e anexos/propostas (cotacoes_anexos) ────────────
$historico = [];
try {
    $historico = db_fetch_all(
        // Eventos internos (resultado de cada seguradora na API) são só da MPS.
        "SELECT * FROM historico WHERE protocolo = ? AND interno = 0 ORDER BY data_evento ASC",
        ['s', $protocolo]
    );
} catch (Throwable) {}

$propostas = [];
try {
    $propostas = db_fetch_all(
        "SELECT * FROM cotacoes_anexos WHERE protocolo = ? ORDER BY data_envio DESC",
        ['s', $protocolo]
    );
} catch (Throwable) {}
$ultima_proposta = $propostas[0] ?? null;

// ── Pré-análise / sub-estados (usando apenas colunas nativas) ────────────────
$liberado            = (int)($cotacao['liberado'] ?? 1);
$formulario_completo = (int)($cotacao['formulario_completo'] ?? 1);
$biometria_status    = $cotacao['biometria_status'] ?? null;
$tipo_ficha_lower    = strtolower((string)($cotacao['tipo_ficha'] ?? 'residencial'));

$preAnalisePendente  = $status === 'Aguardando liberação' || $status === 'Aguardando liberacao';
$podeCompletar       = $status === 'Pré-análise aceita' || $status === 'Pre-analise aceita';
$urlCompletar  = $tipo_ficha_lower === 'comercial'
    ? '/cotacao/fianca/comercial?cotacao=' . urlencode($protocolo)
    : '/cotacao/fianca/residencial?cotacao=' . urlencode($protocolo);

// ── Timeline ──────────────────────────────────────────────────────────────────
$is_recusado_pre   = in_array($status, ['Pré-análise recusada', 'Pre-analise recusada'], true);
$is_recusado_final = cotacao_status_recusado($status);
$is_concluido      = in_array($status, ['Concluída', 'Concluida', 'Concluído', 'Concluido'], true);

// Detecta pré-análise pela coluna dedicada ou pelo histórico (compatível com dados antigos)
$usou_pre_analise = !empty($cotacao['data_pre_analise'])
    || $preAnalisePendente || $podeCompletar || $is_recusado_pre;
if (!$usou_pre_analise) {
    foreach ($historico as $_h) {
        $_snl = strtolower($_h['status_novo'] ?? '');
        if (str_contains($_snl, 'pr') && str_contains($_snl, 'anali')) {
            $usou_pre_analise = true; break;
        }
    }
}

// Datas vindas diretamente das colunas da tabela
$_d_pre_envio    = $cotacao['data_pre_analise']          ?: ($cotacao['data_cadastro'] ?? null);
$_d_pre_aceita   = $cotacao['data_pre_analise_aceita']   ?: null;
$_d_pre_recusada = $cotacao['data_pre_analise_recusada'] ?: null;
$_d_aguardando   = $cotacao['data_status_aguardando']   ?: null;
// Datas de estados posteriores ainda vêm do histórico
$_hmap = [];
foreach ($historico as $_hh) { $_hmap[strtolower($_hh['status_novo'] ?? '')] = $_hh['data_evento'] ?? null; }
function _hdate(array $m, string ...$keys): ?string {
    foreach ($keys as $k) { foreach ($m as $sl => $dt) { if (str_contains($sl, strtolower($k))) return $dt; } }
    return null;
}
$_d_em_analise = _hdate($_hmap, 'em analise', 'em análise');
$_d_biometria  = _hdate($_hmap, 'biometria');
$_d_aprovado   = _hdate($_hmap, 'aprovado');
$_d_recusado   = _hdate($_hmap, 'recusado', 'recusada');
$_d_concluido  = _hdate($_hmap, 'concluíd', 'concluida');

// ── Timeline (histórico real — só o que aconteceu, com datas) ────────────────
$timeline_steps = [];

// Passo inicial sintético: nunca está no historico (é o estado do INSERT)
if ($usou_pre_analise && !empty($_d_pre_envio)) {
    $timeline_steps[] = ['key'=>'pre_envio', 'label'=>'Pré-análise enviada', 'date'=>$_d_pre_envio, 'state'=>'is-done'];
} elseif (!$usou_pre_analise) {
    $_d_ini = $cotacao['data_status_aguardando'] ?: ($cotacao['data_cadastro'] ?? null);
    // Evita duplicata caso o historico já tenha um "Aguardando" como primeiro evento
    // status_novo pode ser NULL (ex.: evento de TRANSFERENCIA no histórico)
    $_hist_lower = array_map(fn($s) => strtolower((string) $s), array_column($historico, 'status_novo'));
    $_has_aguard = false;
    foreach ($_hist_lower as $_hs) { if (str_contains($_hs, 'aguardando')) { $_has_aguard = true; break; } }
    if (!$_has_aguard) {
        $timeline_steps[] = ['key'=>'aguardando', 'label'=>'Aguardando análise', 'date'=>$_d_ini, 'state'=>'is-done'];
    }
}

// Cada entrada do histórico vira um passo real com data real
foreach ($historico as $_hr) {
    $_sn = $_hr['status_novo'] ?? '';
    if (empty($_sn)) continue;
    $_sl     = strtolower($_sn);
    $_is_rec = str_contains($_sl, 'recusad');
    $_is_des = str_contains($_sl, 'desist');
    $timeline_steps[] = [
        'key'   => preg_replace('/[^a-z0-9]/', '_', $_sl),
        'label' => $_sn,
        'date'  => $_hr['data_evento'] ?? null,
        'state' => $_is_des ? 'is-desistencia' : ($_is_rec ? 'is-recusado' : 'is-done'),
        'obs'   => !empty($_hr['observacao']) ? $_hr['observacao'] : null,
    ];
}

// Último passo = estado atual → is-current (exceto se for recusado ou desistência)
if (!empty($timeline_steps)) {
    $_li = count($timeline_steps) - 1;
    if (!in_array($timeline_steps[$_li]['state'], ['is-recusado', 'is-desistencia'], true)) {
        $timeline_steps[$_li]['state'] = 'is-current';
    }
}
// ── Valores financeiros ───────────────────────────────────────────────────────
$val_aluguel  = (float)($cotacao['valor_aluguel']    ?? 0);
$val_agua     = (float)($cotacao['valor_agua']       ?? 0);
$val_cond     = (float)($cotacao['valor_condominio'] ?? 0);
$val_gas      = (float)($cotacao['valor_gas']        ?? 0);
$val_iptu     = (float)($cotacao['valor_iptu']       ?? 0);
$val_energia  = (float)($cotacao['valor_energia']    ?? 0);
$val_encargos = $val_agua + $val_cond + $val_gas + $val_iptu + $val_energia;
$val_total    = $val_aluguel + $val_encargos;

// ── API das seguradoras — a imobiliária vê só a pré-análise, as propostas
// que a MPS enviar e, depois do aceite, a apólice (também já concluída) ───────
require_once BASE_PATH . '/src/helpers/api.php';
$api = null;
if (($cotacao['origem'] ?? '') === 'api' && !empty($cotacao['api_enviado_em'])
    && in_array($status, [API_STATUS_FICHA, API_STATUS_APROVADO, 'Concluída'], true)) {
    $api = ['lote' => api_status_lote([(int) $cotacao['id']])[(int) $cotacao['id']]];
    $api['resumo'] = api_status_resumo_fluxo($api['lote'], false);
}

// ── Metadados ─────────────────────────────────────────────────────────────────
$page_title   = $protocolo;
$page_back    = ['url' => '/cotacoes', 'label' => 'Minhas Cotações', 'icon' => 'arrow-left'];
$extra_css    = ['dashboard.css', 'cotacao.css', 'cotacao-detalhe.css', 'cotacao-fianca-seguradoras.css', 'cotacao-fianca-fluxo.css'];
$extra_js     = [];
$data_criacao     = $cotacao['data_cadastro']    ? date('d/m/Y H:i', strtotime($cotacao['data_cadastro']))    : 'Não preenchido';
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

            <!-- Header da cotação -->
                        <div class="panel">
                <div class="panel-body detalhe-panel-header">
                    <div class="dph-top">
                        <span class="dph-tipo"><?= icon('house') ?> Fiança <?= ucfirst($tipo_ficha_lower) ?> &middot; Plano <?= e($cotacao['tipo_plano'] ?? 'Básico') ?></span>
                        <?php require_once BASE_PATH . '/src/helpers/api.php'; $__st = api_status_exibicao((string) $status, (string) $protocolo, 'fianca', false); ?>
                        <span class="status-pill <?= e($__st['pill']) ?>"><?= e($__st['label']) ?></span>
                    </div>
                    <span class="dph-proto">Cotação #<?= e($protocolo) ?></span>
                    <div class="dph-bar">
                        <span class="dph-bar-item">
                            <?= icon('user') ?>
                            <span class="dph-bar-label">Cliente</span>
                            <span class="dph-bar-value"><?= e($cotacao['nome_completo'] ?? '—') ?></span>
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
                    </div>
                </div>
            </div>


            <div class="detalhe-layout cotacao-container">
                <div class="detalhe-left">

                    <?php if ($api) { $ficha = $cotacao; require __DIR__ . '/../_partials/seguradoras/imobiliaria-propostas.php'; } ?>

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
                                        <?php dfv(cv_telefone($cotacao['tel_celular'])) ?>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">E-mail</div>
                                        <?php dfv(cv($cotacao['email'])) ?>
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
                                <h3 class="form-section-title"><?= icon('users') ?> Pretendente adicional <?= (int)$pret['numero_pretendente'] ?></h3>
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

                <?php require __DIR__ . '/../_partials/detalhe-acoes.php'; ?>

            </div><!-- /.detalhe-layout -->

        </main>
    </div>
</div>

<?php require __DIR__ . '/../../partials/app-foot.php'; ?>
