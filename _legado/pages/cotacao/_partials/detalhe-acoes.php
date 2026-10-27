<?php
/**
 * Coluna direita da página de detalhe de cotação (imobiliária).
 * Usa apenas o schema nativo: status (5 valores), liberado, formulario_completo,
 * biometria_url/biometria_status, historico, cotacoes_anexos.
 *
 * Variáveis esperadas do contexto pai:
 *   $cotacao, $protocolo, $status, $liberado, $formulario_completo, $biometria_status,
 *   $preAnalisePendente, $podeCompletar, $urlCompletar, $timeline_steps, $tipo_key,
 *   $ultima_proposta (linha de cotacoes_anexos|null), $historico (linhas de `historico`)
 *
 * Biometria e proposta em PDF só existem no fluxo de Fiança — os outros produtos
 * (auto, título, condominial) não definem $biometria_status/$ultima_proposta.
 */

$tipo_key          ??= 'fianca';
$biometria_status  ??= null;
$ultima_proposta   ??= null;
// Só usadas pelo fluxo específico do Título de Capitalização (ver título abaixo).
$titulo_anexo_proposta        ??= null;
$titulo_anexo_boleto          ??= null;
$titulo_anexo_link_pagamento  ??= null;
$titulo_anexo_certificado     ??= null;
$_tel_whatsapp = preg_replace('/\D/', '', $cotacao['tel_celular'] ?? '');
// "Cotar com outro pretendente": a profissão é obrigatória quando a Too está
// ligada e a ficha é residencial (mesma regra do formulário — _form.php).
$_recotar_profissao_obrigatoria = false;
if ($tipo_key === 'fianca' && strtolower((string) ($cotacao['tipo_ficha'] ?? '')) === 'residencial') {
    require_once BASE_PATH . '/src/helpers/api.php';
    $_recotar_profissao_obrigatoria = in_array('too', array_keys(api_seguradoras()), true);
}
?>
<div class="detalhe-right" id="detalhe-acoes">

    <?php if (!empty($api) && in_array($status, ['Cotação API', 'Aprovado'], true)): /* Fiança na API das seguradoras */ ?>
    <?php $ficha = $cotacao + ['produto' => $tipo_key]; require __DIR__ . '/seguradoras/imobiliaria-status.php'; ?>

    <?php elseif ($preAnalisePendente): ?>
    <div class="panel">
        <div class="panel-body">
            <div class="alert alert-info" style="margin-bottom:0">
                <span class="alert-icon"><?= icon('clock') ?></span>
                <div>
                    <strong>Cotação em pré-análise</strong><br>
                    Aguardando retorno da equipe MPS sobre a pré-análise enviada.
                </div>
            </div>
        </div>
    </div>

    <?php elseif ($podeCompletar): ?>
    <div class="panel">
        <div class="panel-body">
            <div class="alert alert-success" style="margin-bottom:var(--space-3)">
                <span class="alert-icon"><?= icon('check-circle') ?></span>
                <div>
                    <strong>Pré-análise aceita!</strong><br>
                    Complete os dados da cotação para prosseguir.
                </div>
            </div>
            <a href="<?= e(url($urlCompletar)) ?>" class="btn btn-primary btn-block">
                <?= icon('note-pencil') ?> Completar cotação
            </a>
        </div>
    </div>

    <?php elseif (in_array($status, ['Pré-análise recusada', 'Pre-analise recusada'], true)): ?>
    <div class="panel">
        <div class="panel-body">
            <div class="alert alert-danger" style="margin-bottom:var(--space-3)">
                <span class="alert-icon"><?= icon('x') ?></span>
                <div>
                    <strong>Pré-análise recusada</strong><br>
                    A MPS recusou a pré-análise. Você ainda pode enviar a ficha completa para nova avaliação.
                </div>
            </div>
            <a href="<?= e(url($urlCompletar)) ?>" class="btn btn-primary btn-block">
                <?= icon('note-pencil') ?> Enviar ficha completa mesmo assim
            </a>
        </div>
    </div>

    <?php elseif ($status === 'Aguardando preenchimento'): ?>
    <div class="panel">
        <div class="panel-body">
            <div class="alert alert-info" style="margin-bottom:0">
                <span class="alert-icon"><?= icon('clock') ?></span>
                <div>
                    <strong>Aguardando preenchimento</strong><br>
                    A equipe MPS está aguardando o preenchimento completo da cotação.
                </div>
            </div>
        </div>
    </div>

    <?php elseif ($status === 'Aguardando análise'): ?>
    <div class="panel">
        <div class="panel-body">
            <div class="alert alert-info" style="margin-bottom:0">
                <span class="alert-icon"><?= icon('clock') ?></span>
                <div>
                    <strong>Aguardando análise</strong><br>
                    Sua cotação está na fila de análise da equipe MPS.
                </div>
            </div>
        </div>
    </div>

    <?php elseif ($tipo_key === 'titulo' && $status === 'Em Analise'): ?>
    <div class="panel">
        <div class="panel-body">
            <div class="alert alert-info" style="margin-bottom:0">
                <span class="alert-icon"><?= icon('magnifying-glass') ?></span>
                <div>
                    <strong>Aguardando Proposta</strong><br>
                    A equipe MPS está preparando a proposta e o boleto do seu título.
                </div>
            </div>
        </div>
    </div>

    <?php elseif ($tipo_key === 'titulo' && $status === 'Aguardando Pagamento'): ?>
    <div class="panel" id="painel-titulo-pagamento">
        <div class="panel-header"><h3 class="panel-title"><?= icon('clipboard-text') ?> <?= $titulo_anexo_link_pagamento ? 'Proposta e link de pagamento' : 'Proposta e boleto' ?></h3></div>
        <div class="panel-body">
            <div class="alert alert-info" style="margin-bottom:var(--space-3)">
                <?= icon('info') ?>
                <?= $titulo_anexo_link_pagamento
                    ? 'Baixe a proposta e envie o link de pagamento ao cliente. Confirme assim que ele pagar.'
                    : 'Baixe a proposta e o boleto, e confirme o pagamento assim que o cliente pagar.' ?>
            </div>
            <?php if ($titulo_anexo_proposta): ?>
            <a href="<?= e($titulo_anexo_proposta['caminho_arquivo']) ?>" target="_blank" class="btn btn-secondary btn-block" style="margin-bottom:var(--space-2)">
                <?= icon('arrow-right') ?> Baixar proposta
            </a>
            <?php endif; ?>
            <?php if ($titulo_anexo_link_pagamento): ?>
            <a href="<?= e($titulo_anexo_link_pagamento['caminho_arquivo']) ?>" target="_blank" class="btn btn-secondary btn-block" style="margin-bottom:var(--space-3)">
                <?= icon('arrow-right') ?> Abrir link de pagamento
            </a>
            <?php elseif ($titulo_anexo_boleto): ?>
            <a href="<?= e($titulo_anexo_boleto['caminho_arquivo']) ?>" target="_blank" class="btn btn-secondary btn-block" style="margin-bottom:var(--space-3)">
                <?= icon('arrow-right') ?> Baixar boleto
            </a>
            <?php endif; ?>
            <div class="field" style="margin-bottom:var(--space-3)">
                <label class="field-label">Comprovante de pagamento</label>
                <div class="field-file-picker" data-file-picker>
                    <input type="file" id="titulo-comprovante-arquivo" accept=".pdf,image/*" hidden>
                    <button type="button" class="btn btn-secondary btn-sm" data-file-picker-trigger><?= icon('clipboard-text') ?> Escolher comprovante</button>
                    <span class="field-file-picker-name" data-file-picker-name>Nenhum arquivo selecionado</span>
                </div>
            </div>
            <button type="button" class="btn btn-primary btn-block" id="btn-titulo-pago" disabled><?= icon('check-circle') ?> Confirmar pagamento</button>
        </div>
    </div>

    <?php elseif ($tipo_key === 'titulo' && $status === 'Pago'): ?>
    <div class="panel">
        <div class="panel-body">
            <div class="alert alert-success" style="margin-bottom:0">
                <span class="alert-icon"><?= icon('check-circle') ?></span>
                <div>
                    <strong>Pagamento confirmado!</strong><br>
                    Aguardando a equipe MPS enviar o certificado do título.
                </div>
            </div>
        </div>
    </div>

    <?php elseif ($status === 'Em Analise' || $status === 'Em Análise'): ?>
    <div class="panel">
        <div class="panel-body">
            <div class="alert alert-info" style="margin-bottom:<?= $tipo_key === 'fianca' ? 'var(--space-3)' : '0' ?>">
                <span class="alert-icon"><?= icon('magnifying-glass') ?></span>
                <div>
                    <strong>Em análise</strong><br>
                    Sua cotação está sendo analisada pela equipe MPS.
                </div>
            </div>
            <?php if ($tipo_key === 'fianca'): ?>
            <?php if ($biometria_status === 'pendente' && $cotacao['biometria_url']): ?>
            <div class="alert alert-info" style="margin-bottom:var(--space-3)">
                <span class="alert-icon"><?= icon('clipboard-text') ?></span>
                <div>
                    <strong>Link de biometria disponível</strong><br>
                    <a href="<?= e($cotacao['biometria_url']) ?>" target="_blank" style="word-break:break-all"><?= e($cotacao['biometria_url']) ?></a>
                </div>
            </div>
            <button type="button" class="btn btn-primary btn-block" id="btn-concluir-bio">
                <?= icon('check-circle') ?> Marcar biometria como concluída
            </button>
            <?php elseif (!$biometria_status): ?>
            <div class="alert alert-warning" style="margin-bottom:0">
                <span class="alert-icon"><?= icon('warning') ?></span>
                Aguardando a equipe MPS enviar o link de biometria (se necessário).
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>

    <?php elseif ($tipo_key === 'fianca' && $status === 'Aguardando biometria'): ?>
    <div class="panel">
        <div class="panel-body">
            <div class="alert alert-info" style="margin-bottom:<?= $cotacao['biometria_url'] ? 'var(--space-3)' : '0' ?>">
                <span class="alert-icon"><?= icon('fingerprint') ?></span>
                <div>
                    <strong>Aguardando biometria</strong><br>
                    <?php if ($cotacao['biometria_url']): ?>
                    Acesse o link de biometria para concluir esta etapa.
                    <?php else: ?>
                    A equipe MPS preparará o link de biometria em breve.
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($cotacao['biometria_url']): ?>
            <a href="<?= e($cotacao['biometria_url']) ?>" target="_blank" class="btn btn-primary btn-block" style="margin-bottom:var(--space-2)">
                <?= icon('arrow-square-out') ?> Acessar link de biometria
            </a>
            <button type="button" class="btn btn-secondary btn-block" id="btn-concluir-bio">
                <?= icon('check-circle') ?> Marcar biometria como concluída
            </button>
            <?php endif; ?>
        </div>
    </div>

    <?php elseif ($status === 'Biometria concluída' || $status === 'Biometria concluida'): ?>
    <div class="panel">
        <div class="panel-body">
            <div class="alert alert-success" style="margin-bottom:0">
                <span class="alert-icon"><?= icon('check-circle') ?></span>
                <div>
                    <strong>Biometria concluída</strong><br>
                    Aguardando resultado final da análise pela equipe MPS.
                </div>
            </div>
        </div>
    </div>

    <?php elseif ($status === 'Aprovado'): ?>
    <div class="panel" id="painel-aprovacao">
        <div class="panel-header">
            <h3 class="panel-title"><?= icon('clipboard-text') ?> Proposta da MPS</h3>
        </div>
        <div class="panel-body">
            <?php if ($ultima_proposta && $ultima_proposta['status_aprovacao'] === 'Aguardando'): ?>
            <div class="alert alert-info" style="margin-bottom:var(--space-3)">
                <?= icon('info') ?> Proposta enviada em <?= !empty($ultima_proposta['data_envio']) ? date('d/m/Y H:i', strtotime($ultima_proposta['data_envio'])) : '—' ?>.
            </div>
            <a href="<?= e($ultima_proposta['caminho_arquivo']) ?>" target="_blank" class="btn btn-secondary btn-block" id="btn-baixar-proposta" style="margin-bottom:var(--space-2)">
                <?= icon('arrow-right') ?> Baixar proposta
            </a>
            <?php if ($_tel_whatsapp): ?>
            <?php $link_wa = 'https://wa.me/55' . $_tel_whatsapp . '?text=' . urlencode('Segue sua cotação: ' . $ultima_proposta['caminho_arquivo']); ?>
            <a href="<?= e($link_wa) ?>" target="_blank" class="btn btn-secondary btn-block" id="btn-whatsapp" style="margin-bottom:var(--space-3)">
                <?= icon('whatsapp-logo') ?> Compartilhar via WhatsApp
            </a>
            <?php endif; ?>
            <div id="area-decisao" style="display:none">
                <?php if (!empty($current_user->imob_admin) || ($current_user->perm_aceitar ?? 'N') === 'S'): ?>
                <button type="button" class="btn btn-primary btn-block" id="btn-aceitar-proposta" style="margin-bottom:var(--space-2)">
                    <?= icon('check-circle') ?> Aceitar proposta
                </button>
                <?php endif; ?>
                <button type="button" class="btn btn-danger-outline btn-block" id="btn-recusar-proposta">
                    <?= icon('x') ?> Recusar proposta
                </button>
            </div>
            <?php else: ?>
            <div class="empty-state"><p>Proposta não encontrada.</p></div>
            <?php endif; ?>
        </div>
    </div>

    <?php elseif ($tipo_key === 'titulo' && ($status === 'Concluída' || $status === 'Concluida')): ?>
    <div class="panel">
        <div class="panel-body">
            <div class="alert alert-success" style="margin-bottom:0">
                <span class="alert-icon"><?= icon('check-circle') ?></span>
                <div>
                    <strong>Título concluído!</strong><br>
                    Obrigado por escolher a MPS.
                </div>
            </div>
        </div>
        <?php if ($titulo_anexo_certificado): ?>
        <div class="panel-body" style="padding-top:0">
            <a href="<?= e($titulo_anexo_certificado['caminho_arquivo']) ?>" target="_blank" class="btn btn-secondary btn-block">
                <?= icon('arrow-right') ?> Baixar certificado
            </a>
        </div>
        <?php endif; ?>
    </div>

    <?php elseif ($status === 'Concluída' || $status === 'Concluida'): ?>
    <div class="panel">
        <div class="panel-body">
            <div class="alert alert-success" style="margin-bottom:0">
                <span class="alert-icon"><?= icon('check-circle') ?></span>
                <div>
                    <strong>Cotação concluída!</strong><br>
                    Obrigado por escolher a MPS.
                </div>
            </div>
        </div>
        <?php if ($ultima_proposta): ?>
        <div class="panel-body" style="padding-top:0">
            <a href="<?= e($ultima_proposta['caminho_arquivo']) ?>" target="_blank" class="btn btn-secondary btn-block">
                <?= icon('arrow-right') ?> Baixar proposta aceita
            </a>
        </div>
        <?php endif; ?>
    </div>

    <?php elseif (cotacao_status_desistencia($status)): ?>
    <div class="panel">
        <div class="panel-body">
            <div class="alert alert-desistencia">
                <span class="alert-icon"><?= icon('sign-out') ?></span>
                <div>
                    <strong>Cliente desistiu da cotação</strong><br>
                    A cotação foi encerrada por desistência.
                </div>
            </div>
        </div>
    </div>

    <?php elseif (cotacao_status_recusado($status)): ?>
    <div class="panel">
        <div class="panel-body">
            <div class="alert alert-danger" style="margin-bottom:0">
                <span class="alert-icon"><?= icon('x') ?></span>
                <div>
                    <?php if ($status === STATUS_RECUSADO_CLIENTE): ?>
                    <strong>Cotação recusada pelo cliente</strong><br>
                    As propostas enviadas foram recusadas e a cotação foi encerrada.
                    <?php else: ?>
                    <strong>Cotação recusada pela MPS</strong><br>
                    Entre em contato para mais informações.
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($tipo_key === 'fianca'): ?>
            <button type="button" class="btn btn-secondary btn-block btn-recotar-outro-pretendente" id="btn-cotar-outro-pretendente">
                <?= icon('user-plus') ?> Cotar com outro pretendente
            </button>
            <?php endif; ?>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!cotacao_status_encerrado($status) && $status !== 'Rascunho' && !in_array($api['resumo']['etapa'] ?? '', ['contratada', 'emitida'], true)): ?>
    <div class="panel panel-desistencia">
        <div class="panel-body">
            <p class="desistencia-texto">Se o cliente desistiu, encerre a cotação para tirá-la da fila de análise.</p>
            <button type="button" class="btn btn-secondary btn-block" id="btn-desistir">
                <?= icon('sign-out') ?> Cliente desistiu da cotação
            </button>
        </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($timeline_steps)): ?>
    <div class="panel" style="margin-top:var(--space-3)">
        <div class="panel-header">
            <h3 class="panel-title"><?= icon('clock') ?> Status</h3>
        </div>
        <div class="panel-body">
            <div class="status-timeline">
                <?php foreach ($timeline_steps as $_ts): ?>
                <div class="timeline-item <?= e($_ts['state']) ?>">
                    <div class="timeline-dot"></div>
                    <div class="timeline-content">
                        <span class="timeline-label"><?= e($_ts['label']) ?></span>
                        <?php if (!empty($_ts['date'])): ?>
                        <span class="timeline-meta"><?= date('d/m/Y H:i', strtotime($_ts['date'])) ?></span>
                        <?php endif; ?>
                        <?php if (!empty($_ts['has_button'])): ?>
                        <a href="<?= e(url($urlCompletar)) ?>" class="btn btn-primary btn-sm" style="margin-top:var(--space-2)">
                            <?= icon('note-pencil') ?> Completar cotação
                        </a>
                        <?php endif; ?>
                        <?php if (!empty($_ts['obs'])): ?>
                        <span class="timeline-obs"><?= e($_ts['obs']) ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    <?php endif; ?>

</div><!-- /.detalhe-right -->

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

    function handleResp(data, msg) {
        if (data.success) {
            mpsAlert.success(msg || data.message);
            setTimeout(() => location.reload(), 1200);
        } else {
            mpsAlert.error(data.message || 'Tente novamente.');
        }
    }

    const btnConcluirBio = document.getElementById('btn-concluir-bio');
    if (btnConcluirBio) {
        btnConcluirBio.addEventListener('click', async () => {
            const ok = await mpsAlert.confirm({ message: 'Confirmar biometria como concluída?' });
            if (!ok) return;
            apiPost('biometria', {acao: 'concluir'}).then(d => handleResp(d, 'Biometria concluída!'));
        });
    }

    const btnBaixar = document.getElementById('btn-baixar-proposta');
    const btnWa     = document.getElementById('btn-whatsapp');
    const areaDec   = document.getElementById('area-decisao');

    function mostrarDecisao() {
        if (areaDec) areaDec.style.display = '';
    }
    if (btnBaixar) btnBaixar.addEventListener('click', mostrarDecisao);
    if (btnWa)     btnWa.addEventListener('click',     mostrarDecisao);

    const btnAceitar = document.getElementById('btn-aceitar-proposta');
    if (btnAceitar) {
        btnAceitar.addEventListener('click', async () => {
            const ok = await mpsAlert.confirm({ message: 'Confirmar aceitação da proposta?' });
            if (!ok) return;
            apiPost('aprovar', {}).then(d => handleResp(d, 'Proposta aceita! Cotação concluída.'));
        });
    }

    const btnRecusar = document.getElementById('btn-recusar-proposta');
    if (btnRecusar) {
        btnRecusar.addEventListener('click', async () => {
            const ok = await mpsAlert.confirm({ message: 'Confirmar recusa da proposta?' });
            if (!ok) return;
            apiPost('recusar', {}).then(d => handleResp(d, 'Proposta recusada. A MPS será notificada.'));
        });
    }

    // ── Cotar com outro pretendente (Fiança recusada — Residencial ou Comercial) ──
    const btnRecotar = document.getElementById('btn-cotar-outro-pretendente');
    if (btnRecotar) {
        btnRecotar.addEventListener('click', () => {
            mpsAlert.custom(`
                <h3 class="mps-alert-title">Cotar com outro pretendente</h3>
                <p class="mps-alert-message">Cria uma nova cotação para o mesmo imóvel, com os dados de um novo pretendente.</p>
                <div class="recotar-pretendente-form">
                    <div class="field">
                        <label class="field-label" for="recotar-nome">
                            Nome ou razão social <span class="required">*</span>
                            <span class="field-error" hidden></span>
                        </label>
                        <div class="field-input-wrap">
                            <span class="field-input-icon"><?= icon('user') ?></span>
                            <input type="text" id="recotar-nome" class="field-input has-icon" placeholder="Nome completo ou razão social do pretendente" autocomplete="name" required>
                        </div>
                    </div>
                    <div class="field">
                        <label class="field-label" for="recotar-cpf">
                            CPF/CNPJ <span class="required">*</span>
                            <span class="field-error" hidden></span>
                        </label>
                        <div class="field-input-wrap">
                            <span class="field-input-icon"><?= icon('identification-card') ?></span>
                            <input type="text" id="recotar-cpf" class="field-input has-icon has-icon-both" placeholder="000.000.000-00" inputmode="numeric" maxlength="18" autocomplete="off" required>
                            <span class="field-input-status" id="recotar-cpf-status" hidden></span>
                        </div>
                    </div>
                    <div class="field">
                        <label class="field-label" for="recotar-tel">
                            Telefone <span class="required">*</span>
                            <span class="field-error" hidden></span>
                        </label>
                        <div class="field-input-wrap">
                            <span class="field-input-icon"><?= icon('phone') ?></span>
                            <input type="tel" id="recotar-tel" class="field-input has-icon" placeholder="(00) 00000-0000" autocomplete="tel" required>
                        </div>
                    </div>
                    <div class="field">
                        <label class="field-label" for="recotar-email">
                            E-mail <span class="required">*</span>
                            <span class="field-error" hidden></span>
                        </label>
                        <div class="field-input-wrap">
                            <span class="field-input-icon"><?= icon('envelope-simple') ?></span>
                            <input type="email" id="recotar-email" class="field-input has-icon" placeholder="email@exemplo.com" autocomplete="email" required>
                        </div>
                    </div>
                    <div class="field">
                        <label class="field-label" for="recotar-nasc">
                            Data de nascimento <span class="required">*</span>
                            <span class="field-error" hidden></span>
                        </label>
                        <div class="field-input-wrap">
                            <span class="field-input-icon"><?= icon('calendar-blank') ?></span>
                            <input type="text" id="recotar-nasc" class="field-input has-icon" placeholder="DD/MM/AAAA" inputmode="numeric" maxlength="10" required>
                        </div>
                    </div>
                    <div class="field">
                        <label class="field-label" for="recotar-renda">
                            Renda mensal <span class="required">*</span>
                            <span class="field-error" hidden></span>
                        </label>
                        <div class="field-input-wrap">
                            <span class="field-input-icon"><?= icon('currency-dollar') ?></span>
                            <input type="text" id="recotar-renda" class="field-input has-icon" placeholder="R$ 0,00" inputmode="numeric" required>
                        </div>
                    </div>
                    <div class="field">
                        <label class="field-label">
                            Vínculo empregatício <span class="required">*</span>
                            <span class="field-error" hidden></span>
                        </label>
                        <div class="field-input-wrap" id="recotar-vinculo">
                            <span class="field-input-icon"><?= icon('identification-card') ?></span>
                            <?= custom_select('recotar_vinculo', array_combine(fianca_vinculos_empregaticios(), fianca_vinculos_empregaticios()), 'Selecione...', null, 'has-icon', true) ?>
                        </div>
                    </div>
                    <div class="field">
                        <label class="field-label" for="recotar-profissao">
                            Profissão<?= $_recotar_profissao_obrigatoria ? ' <span class="required">*</span>' : '' ?>
                            <span class="field-error" hidden></span>
                        </label>
                        <div class="field-input-wrap">
                            <span class="field-input-icon"><?= icon('briefcase') ?></span>
                            <input type="text" id="recotar-profissao" class="field-input has-icon" placeholder="Ex: Analista administrativo" maxlength="40">
                        </div>
                    </div>
                </div>
                <div class="mps-alert-actions">
                    <button type="button" class="btn btn-secondary" data-recotar-cancelar>Cancelar</button>
                    <button type="button" class="btn btn-primary" data-recotar-confirmar>Criar cotação</button>
                </div>
            `, {
                onMount: (card, close) => {
                    const inputNome  = card.querySelector('#recotar-nome');
                    const inputCpf   = card.querySelector('#recotar-cpf');
                    const inputTel   = card.querySelector('#recotar-tel');
                    const inputEmail = card.querySelector('#recotar-email');
                    const cpfStatus  = card.querySelector('#recotar-cpf-status');

                    const SVG_OK  = '<svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256"><path d="M173.66,98.34a8,8,0,0,1,0,11.32l-56,56a8,8,0,0,1-11.32,0l-24-24a8,8,0,0,1,11.32-11.32L112,148.69l50.34-50.35A8,8,0,0,1,173.66,98.34ZM232,128A104,104,0,1,1,128,24,104.11,104.11,0,0,1,232,128Zm-16,0a88,88,0,1,0-88,88A88.1,88.1,0,0,0,216,128Z"/></svg>';
                    const SVG_ERR = '<svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256"><path d="M165.66,101.66,139.31,128l26.35,26.34a8,8,0,0,1-11.32,11.32L128,139.31l-26.34,26.35a8,8,0,0,1-11.32-11.32L116.69,128,90.34,101.66a8,8,0,0,1,11.32-11.32L128,116.69l26.34-26.35a8,8,0,0,1,11.32,11.32ZM232,128A104,104,0,1,1,128,24,104.11,104.11,0,0,1,232,128Zm-16,0a88,88,0,1,0-88,88A88.1,88.1,0,0,0,216,128Z"/></svg>';

                    function maskCpfCnpj(v) {
                        const n = v.replace(/\D/g, '').slice(0, 14);
                        if (n.length <= 11) {
                            return n.replace(/(\d{3})(\d)/, '$1.$2')
                                    .replace(/(\d{3})(\d)/, '$1.$2')
                                    .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
                        }
                        return n.replace(/(\d{2})(\d)/, '$1.$2')
                                .replace(/(\d{3})(\d)/, '$1.$2')
                                .replace(/(\d{3})(\d{1,4})/, '$1/$2')
                                .replace(/(\d{4})(\d{1,2})$/, '$1-$2');
                    }
                    function maskPhone(v) {
                        const n = v.replace(/\D/g, '').slice(0, 11);
                        if (n.length <= 10) {
                            return n.replace(/(\d{2})(\d)/, '($1) $2').replace(/(\d{4})(\d{1,4})$/, '$1-$2');
                        }
                        return n.replace(/(\d{2})(\d)/, '($1) $2').replace(/(\d{5})(\d{1,4})$/, '$1-$2');
                    }
                    function cpfValido(cpf) {
                        cpf = cpf.replace(/\D/g, '');
                        if (cpf.length !== 11 || /^(\d)\1{10}$/.test(cpf)) return false;
                        const calc = (len) => {
                            let soma = 0;
                            for (let i = 0; i < len; i++) soma += parseInt(cpf[i], 10) * (len + 1 - i);
                            const resto = (soma * 10) % 11;
                            return resto === 10 ? 0 : resto;
                        };
                        return calc(9) === parseInt(cpf[9], 10) && calc(10) === parseInt(cpf[10], 10);
                    }
                    function cnpjValido(cnpj) {
                        cnpj = cnpj.replace(/\D/g, '');
                        if (cnpj.length !== 14 || /^(\d)\1{13}$/.test(cnpj)) return false;
                        const calc = (str, weights) => {
                            const sum = str.split('').reduce((acc, d, i) => acc + parseInt(d, 10) * weights[i], 0);
                            const rem = sum % 11;
                            return rem < 2 ? 0 : 11 - rem;
                        };
                        const w1 = [5,4,3,2,9,8,7,6,5,4,3,2];
                        const w2 = [6,5,4,3,2,9,8,7,6,5,4,3,2];
                        return calc(cnpj.slice(0,12), w1) === parseInt(cnpj[12], 10) && calc(cnpj.slice(0,13), w2) === parseInt(cnpj[13], 10);
                    }
                    function cpfCnpjValido(v) {
                        const digits = v.replace(/\D/g, '');
                        if (digits.length === 11) return cpfValido(digits);
                        if (digits.length === 14) return cnpjValido(digits);
                        return false;
                    }

                    inputCpf.addEventListener('input', () => {
                        inputCpf.value = maskCpfCnpj(inputCpf.value);
                        const digits = inputCpf.value.replace(/\D/g, '');
                        if (digits.length !== 11 && digits.length !== 14) {
                            cpfStatus.hidden = true;
                            cpfStatus.className = 'field-input-status';
                            return;
                        }
                        const ok = cpfCnpjValido(inputCpf.value);
                        cpfStatus.hidden = false;
                        cpfStatus.className = `field-input-status ${ok ? 'is-valid' : 'is-error'}`;
                        cpfStatus.innerHTML = ok ? SVG_OK : SVG_ERR;
                    });
                    inputTel.addEventListener('input', () => { inputTel.value = maskPhone(inputTel.value); });

                    function clearFieldError(input) {
                        input.classList.remove('has-error');
                        const err = input.closest('.field')?.querySelector('.field-error');
                        if (err) { err.textContent = ''; err.hidden = true; }
                    }
                    function setFieldError(input, msg) {
                        input.classList.add('has-error');
                        const err = input.closest('.field')?.querySelector('.field-error');
                        if (err) { err.textContent = msg; err.hidden = false; }
                    }
                    // Dados que as seguradoras analisam do novo pretendente.
                    const inputNasc      = card.querySelector('#recotar-nasc');
                    const inputRenda     = card.querySelector('#recotar-renda');
                    const inputProfissao = card.querySelector('#recotar-profissao');
                    const wrapVinculo    = card.querySelector('#recotar-vinculo');
                    const inputVinculo   = wrapVinculo.querySelector('input[type="hidden"]');
                    const profissaoObrigatoria = <?= $_recotar_profissao_obrigatoria ? 'true' : 'false' ?>;
                    window.customSelectInit?.(card);
                    inputNasc.addEventListener('input', () => {
                        const d = inputNasc.value.replace(/\D/g, '').slice(0, 8);
                        inputNasc.value = d.replace(/^(\d{2})(\d)/, '$1/$2').replace(/^(\d{2})\/(\d{2})(\d)/, '$1/$2/$3');
                    });
                    const rendaNumero = () => parseInt(inputRenda.value.replace(/\D/g, '') || '0', 10) / 100;
                    inputRenda.addEventListener('input', () => {
                        const digitos = inputRenda.value.replace(/\D/g, '');
                        inputRenda.value = digitos ? 'R$ ' + rendaNumero().toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '';
                    });
                    inputVinculo.addEventListener('change', () => clearFieldError(inputVinculo));

                    [inputNome, inputCpf, inputTel, inputEmail, inputNasc, inputRenda, inputProfissao].forEach(input => {
                        input.addEventListener('input', () => clearFieldError(input));
                    });

                    function validar() {
                        let valid = true;

                        if (!inputNome.value.trim()) { setFieldError(inputNome, 'Campo obrigatório.'); valid = false; }
                        else clearFieldError(inputNome);

                        if (!inputCpf.value.trim()) { setFieldError(inputCpf, 'Campo obrigatório.'); valid = false; }
                        else if (!cpfCnpjValido(inputCpf.value)) { setFieldError(inputCpf, 'CPF/CNPJ inválido.'); valid = false; }
                        else clearFieldError(inputCpf);

                        if (!inputTel.value.trim()) { setFieldError(inputTel, 'Campo obrigatório.'); valid = false; }
                        else clearFieldError(inputTel);

                        const email = inputEmail.value.trim();
                        if (!email) { setFieldError(inputEmail, 'Campo obrigatório.'); valid = false; }
                        else if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) { setFieldError(inputEmail, 'E-mail inválido.'); valid = false; }
                        else clearFieldError(inputEmail);

                        const nasc = inputNasc.value.trim().match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
                        const dataNasc = nasc ? new Date(+nasc[3], +nasc[2] - 1, +nasc[1]) : null;
                        if (!dataNasc || dataNasc.getDate() !== +nasc[1] || dataNasc > new Date()) { setFieldError(inputNasc, 'Use uma data válida (DD/MM/AAAA).'); valid = false; }
                        else clearFieldError(inputNasc);

                        if (rendaNumero() <= 0) { setFieldError(inputRenda, 'Informe a renda mensal.'); valid = false; }
                        else clearFieldError(inputRenda);

                        if (!inputVinculo.value) { setFieldError(inputVinculo, 'Selecione o vínculo.'); valid = false; }
                        else clearFieldError(inputVinculo);

                        if (profissaoObrigatoria && !inputProfissao.value.trim()) { setFieldError(inputProfissao, 'Campo obrigatório.'); valid = false; }
                        else clearFieldError(inputProfissao);

                        if (!valid) card.querySelector('.has-error')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        return valid;
                    }

                    card.querySelector('[data-recotar-cancelar]').addEventListener('click', close);
                    card.querySelector('[data-recotar-confirmar]').addEventListener('click', async () => {
                        if (!validar()) return;

                        const nome_completo = inputNome.value.trim();
                        const cpf_cnpj      = inputCpf.value.trim();
                        const tel_celular   = inputTel.value.trim();
                        const email         = inputEmail.value.trim();

                        mpsAlert.loading('Criando cotação...');
                        try {
                            const data = await apiPost('recotar', {
                                nome_completo, cpf_cnpj, tel_celular, email,
                                data_nascimento: inputNasc.value.trim(),
                                salario_bruto: rendaNumero().toFixed(2),
                                vinculo_empregaticio: inputVinculo.value,
                                profissao: inputProfissao.value.trim(),
                            });
                            if (data.success) {
                                await mpsAlert.success(data.em_analise ? 'Cotação enviada para análise!' : 'Cotação criada! Redirecionando...');
                                window.location.href = appUrl(`/cotacao/fianca/${data.protocolo}`);
                            } else {
                                mpsAlert.error(data.message || 'Não foi possível criar a cotação.');
                            }
                        } catch (e) {
                            mpsAlert.error('Erro ao criar a cotação. Tente novamente.');
                        }
                    });
                },
            });
        });
    }

    const btnDesistir = document.getElementById('btn-desistir');
    if (btnDesistir) {
        btnDesistir.addEventListener('click', async () => {
            const ok = await mpsAlert.confirm({ message: 'Encerrar esta cotação por desistência do cliente? Essa ação não pode ser desfeita.' });
            if (!ok) return;
            apiPost('desistir', {}).then(d => handleResp(d, 'Cotação encerrada por desistência.'));
        });
    }

    // ── Confirmar pagamento (específico do Título de Capitalização) ────────
    const inputComprovante = document.getElementById('titulo-comprovante-arquivo');
    const btnTituloPago    = document.getElementById('btn-titulo-pago');
    if (inputComprovante && btnTituloPago) {
        const csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
        inputComprovante.addEventListener('change', () => {
            btnTituloPago.disabled = inputComprovante.files.length === 0;
        });
        btnTituloPago.addEventListener('click', async () => {
            const ok = await mpsAlert.confirm({ message: 'Confirmar que o cliente pagou o boleto/título?' });
            if (!ok) return;
            const fd = new FormData();
            fd.append('comprovante', inputComprovante.files[0]);
            btnTituloPago.disabled = true;
            btnTituloPago.textContent = 'Enviando...';
            fetch(`/api/cotacao/titulo/${proto}/pagar`, {
                method: 'POST', headers: { 'X-CSRF-Token': csrfToken }, body: fd,
            })
                .then(r => r.json())
                .then(d => handleResp(d, 'Pagamento confirmado!'))
                .catch(() => mpsAlert.error('Erro ao confirmar pagamento.'))
                .finally(() => { btnTituloPago.disabled = false; btnTituloPago.textContent = 'Confirmar pagamento'; });
        });
    }
})();
</script>
