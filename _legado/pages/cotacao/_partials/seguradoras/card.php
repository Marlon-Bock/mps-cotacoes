<?php
/**
 * Card do resultado de uma seguradora (tela da MPS): status, prêmio,
 * pendências, erro, reanálise, emissão e PDFs; no "Fazer cotação", a chave
 * "Enviar ao cliente" (aprovada ou biometria). Quem aceita/emite é o cliente.
 *
 * Espera (do seguradoras-conteudo.php): $c (linha de api_cotacoes), $slug,
 * $ficha, $fiancaId, $is_admin, $fichaEncerrada, $escolhida, $correcoes,
 * $podeCorrigir, $jaHouveAprovada (por referência entre os cards),
 * $podeCotar, $etapa e $naPropostaAtual (slugs na proposta aguardando).
 */
$seguradora  = api_seguradora($slug) ?? ['nome' => $slug, 'sigla' => '??', 'cor' => '#6b7280', 'logo' => null];
$status      = (string) $c['status'];
$recusada    = api_status_recusado($status);
$liberada    = api_status_liberado($status);
$pendencia   = api_pendencia_texto($status);

$emissao          = api_fila_ultimo($fiancaId, 'emissao', $slug);
$emitindo         = api_fila_em_andamento($emissao);
$reanalise        = api_fila_ultimo($fiancaId, 'reanalise', $slug);
$reanalisando     = api_fila_em_andamento($reanalise);
$temApolice       = api_tem_apolice($c);
// Proposta criada mas o aceite não concluiu: "Concluir emissão"
// retoma só o aceite (ver api_emitir_apolice).
$emissaoPendente  = !empty($c['proposta_id']) && !$temApolice;

// A lista vem ordenada por prêmio — a 1ª liberada é a melhor oferta.
$melhor = ($liberada || $status === 'proposta_escolhida') && !$jaHouveAprovada;
if ($liberada || $status === 'proposta_escolhida') { $jaHouveAprovada = true; }
?>
<div class="seg-card <?= $melhor ? 'seg-card--melhor' : '' ?>">
    <?php if ($melhor): ?>
    <div class="seg-card-fita"><?= icon('check-circle') ?> Melhor oferta</div>
    <?php endif; ?>

    <div class="seg-card-marca">
        <?php require __DIR__ . '/logo.php'; ?>
    </div>

    <div class="seg-card-titulo">
        <span class="seg-nome"><?= e($seguradora['nome']) ?></span>
        <?php if ($c['numero_cotacao']): ?>
        <span class="seg-subtitulo">Cotação nº <?= e($c['numero_cotacao']) ?></span>
        <?php endif; ?>
        <span class="seg-subtitulo">Titular: <?= e((string) $ficha['nome_completo']) ?> (<?= e(api_formatar_documento((string) $ficha['cpf_cnpj'])) ?>)</span>
        <?php if (!empty($c['solidarios_json'])): ?>
        <span class="seg-subtitulo">Solidário: <?= e(api_solidarios_texto($c['solidarios_json'])) ?></span>
        <?php endif; ?>
        <span class="seg-status seg-status--<?= e(api_status_selo($status)) ?>"><?= e(api_status_label($status)) ?></span>
    </div>

    <?php if ($pendencia): ?>
    <?php $aviso = ['tipo' => 'alerta', 'texto' => $pendencia]; require __DIR__ . '/aviso.php'; ?>
    <?php endif; ?>

    <?php if (isset($correcoes[$slug])):
        $definicoes = api_campos_corrigiveis();
        // Mostra o dado que foi recusado (o 1º campo do grupo é o principal: CEP, nome...).
        $campoPrincipal = $correcoes[$slug]['campos'][0];
        $valorPrincipal = (string) ($ficha[$campoPrincipal] ?? '');
        if ($campoPrincipal === 'cep') $valorPrincipal = preg_replace('/(\d{5})(\d{3})/', '$1-$2', $valorPrincipal);
        if ($campoPrincipal === 'cpf_cnpj') $valorPrincipal = api_formatar_documento($valorPrincipal);
        if (str_starts_with($campoPrincipal, 'valor_')) $valorPrincipal = 'R$ ' . number_format((float) $valorPrincipal, 2, ',', '.');
    ?>
    <?php
        $aviso = [
            'tipo'   => 'erro',
            'titulo' => 'Dado recusado',
            'texto'  => $correcoes[$slug]['mensagem'],
            'extra'  => $valorPrincipal !== '' ? 'Informado: <strong>' . e($valorPrincipal) . '</strong>' : null,
            'acao'   => $podeCorrigir ? '<button type="button" class="btn btn-primary btn-sm btn-corrigir-dados">' . icon('note-pencil') . ' Corrigir dados</button>' : null,
        ];
        require __DIR__ . '/aviso.php';
    ?>
    <?php elseif ($c['erro']):
        $codigoErro = $c['erro_codigo'] ?? null;
        $tipoErro   = api_erro_tipo($codigoErro);
        $aviso = [
            'tipo'   => $tipoErro === 'temporario' ? 'alerta' : 'erro',
            'titulo' => match (true) {
                $codigoErro === 'imovel_com_apolice' => 'Imóvel já segurado',
                $codigoErro === 'fora_regras'        => 'Fora das regras da ' . $seguradora['nome'],
                $tipoErro === 'temporario'           => 'Não foi possível cotar agora',
                default                              => 'Dados recusados',
            },
            // fora_regras: a mensagem específica (em português) vem do adapter.
            'texto'  => $codigoErro === 'fora_regras' ? (string) $c['erro'] : api_erro_amigavel($codigoErro, $ficha),
            'dica'   => $is_admin ? (string) $c['erro'] : null,
        ];
        if ($codigoErro === 'imovel_com_apolice') {
            // Frase curta + os dados da apólice que ocupa o imóvel numa linha à parte.
            $aviso['texto'] = 'Esse imóvel já tem seguro fiança ativo na ' . $seguradora['nome'] . '. Não é possível contratar outro.';
            if (preg_match('/\(nº (\d+)(?:, cotação ([\w-]+))?\)/u', (string) $c['erro'], $m)) {
                $aviso['extra'] = 'Apólice <strong>nº ' . e($m[1]) . '</strong>' . (!empty($m[2]) ? ' · Cotação <strong>' . e($m[2]) . '</strong>' : '');
            }
        }
        require __DIR__ . '/aviso.php';
    ?>
    <?php endif; ?>

    <?php if ($c['premio_total']): ?>
    <div class="seg-card-premio">
        <span class="seg-premio-label">Prêmio total</span>
        <span class="seg-premio-valor">R$ <?= number_format((float) $c['premio_total'], 2, ',', '.') ?></span>
        <?php if ((int) $c['parcelas'] > 0): ?>
        <span class="seg-premio-parcela">
            <?= (int) $c['parcelas'] === 1 ? 'À vista' : $c['parcelas'] . 'x de R$ ' . number_format((float) $c['premio_total'] / (int) $c['parcelas'], 2, ',', '.') ?>
        </span>
        <?php endif; ?>
    </div>
    <?php elseif ($recusada): ?>
    <?php $aviso = ['tipo' => 'erro', 'titulo' => 'Cotação recusada', 'texto' => 'A seguradora não aprovou essa cotação.']; require __DIR__ . '/aviso.php'; ?>
    <?php endif; ?>

    <?php
        // Reanálise com outro pretendente: só se a seguradora oferece pela API
        // e não é imóvel já segurado (reanálise não resolve).
        $podeReanalisar = $recusada && api_adapter($slug, 'reanalisar') !== null && ($c['erro_codigo'] ?? null) !== 'imovel_com_apolice';
    ?>
    <?php if ($podeReanalisar): ?>
        <?php if ($reanalisando): ?>
        <div class="seg-processando">
            <span class="seg-processando-spinner" aria-hidden="true"></span>
            <div>
                <span class="seg-processando-titulo">Reanalisando na seguradora...</span>
                <span class="seg-processando-texto">Pode levar alguns minutos — a tela atualiza sozinha.</span>
            </div>
        </div>
        <?php elseif (($reanalise['status'] ?? '') === 'erro' && $is_admin): ?>
        <?php $aviso = ['tipo' => 'erro', 'titulo' => 'A última reanálise falhou', 'texto' => (string) $reanalise['erro']]; require __DIR__ . '/aviso.php'; ?>
        <?php endif; ?>

        <?php if ($is_admin && !$fichaEncerrada && !$reanalisando): ?>
        <div class="seg-reanalise">
            <label class="seg-reanalise-label" for="reanalise-nome-<?= e($slug) ?>">Tentar com outro pretendente</label>
            <div class="seg-reanalise-campo seg-reanalise-campo--nome">
                <div class="field-input-wrap">
                    <span class="field-input-icon"><?= icon('user') ?></span>
                    <input type="text" id="reanalise-nome-<?= e($slug) ?>" class="field-input has-icon" maxlength="100" placeholder="Nome como no documento">
                </div>
            </div>
            <div class="seg-reanalise-campo">
                <div class="field-input-wrap">
                    <span class="field-input-icon"><?= icon('identification-card') ?></span>
                    <input type="text" id="reanalise-cpf-<?= e($slug) ?>" class="field-input has-icon" data-mask="cpfcnpj" inputmode="numeric" autocomplete="off" placeholder="000.000.000-00">
                </div>
                <button type="button" class="btn btn-primary btn-sm btn-reanalisar" data-seguradora="<?= e($slug) ?>">
                    <?= icon('arrows-clockwise') ?> Reanalisar
                </button>
            </div>
        </div>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($c['premio_total']): ?>
    <div class="seg-detalhes">
        <?php foreach ([
            'Prêmio líquido' => $c['premio_liquido'] ? 'R$ ' . number_format((float) $c['premio_liquido'], 2, ',', '.') : null,
            'IOF'            => $c['iof'] ? 'R$ ' . number_format((float) $c['iof'], 2, ',', '.') : null,
            'Cobertura'      => $c['cobertura'] ? 'R$ ' . number_format((float) $c['cobertura'], 2, ',', '.') : null,
            'Pagamento'      => $c['forma_pagamento'] ?: null,
            'Plano'          => $c['plano'] ?: null,
        ] as $rotulo => $valor): ?>
        <?php if ($valor !== null): ?>
        <div class="seg-detalhe-item">
            <span class="seg-detalhe-label"><?= e($rotulo) ?></span>
            <span class="seg-detalhe-valor"><?= e($valor) ?></span>
        </div>
        <?php endif; ?>
        <?php endforeach; ?>
        <?php if ($c['vigencia_inicio'] && $c['vigencia_fim']): ?>
        <div class="seg-detalhe-item seg-detalhe-item--wide">
            <span class="seg-detalhe-label">Vigência</span>
            <span class="seg-detalhe-valor"><?= e(date('d/m/Y', strtotime($c['vigencia_inicio']))) ?> a <?= e(date('d/m/Y', strtotime($c['vigencia_fim']))) ?></span>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php $historico = $tentativas[$slug] ?? []; ?>
    <?php if (count($historico) > 1): ?>
    <div class="seg-historico">
        <span class="seg-detalhe-label">Histórico de tentativas</span>
        <?php foreach ($historico as $t): ?>
        <div class="seg-historico-linha">
            <span>
                <?= (int) $t['tentativa'] ?>ª tentativa
                <span class="seg-historico-desc">(<?= e((string) $t['pretendentes']) ?>)</span>
            </span>
            <span class="seg-historico-status"><?= e(api_status_label((string) $t['status'])) ?></span>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <?php if ($temApolice):
        // A emissão é assíncrona e a seguradora leva ~1 min pra gerar cada
        // PDF: o botão só aparece com o PDF já guardado aqui (abre na hora);
        // até lá, "Gerando o PDF...". O aviso sai quando ele chega.
        $apoliceEmitida = $c['apolice_status'] === 'emitida';
        $propostaPdf    = !empty($c['proposta_id']) && api_documento_em_cache($slug, 'proposta', (string) $c['proposta_id']);
        $apolicePdf     = $apoliceEmitida && !empty($c['apolice_id']) && api_documento_em_cache($slug, 'apolice', (string) $c['apolice_id']);
    ?>
    <div class="seg-apolice <?= $apoliceEmitida ? '' : 'seg-apolice--pendente' ?>">
        <?= icon($apoliceEmitida ? 'check-circle' : 'clock') ?>
        <div>
            <span class="seg-apolice-label"><?= $propostaPdf ? 'Proposta pronta' : 'Gerando o PDF da proposta...' ?></span>
            <?php if ($apoliceEmitida): ?>
            <span class="seg-apolice-status">Apólice emitida</span>
            <?php if ($c['apolice_numero']): ?>
            <span class="seg-apolice-aviso">Nº <?= e($c['apolice_numero']) ?></span>
            <?php endif; ?>
            <?php if (!$apolicePdf): ?>
            <span class="seg-apolice-aviso">Gerando o PDF da apólice — avisamos quando ficar pronto.</span>
            <?php endif; ?>
            <?php else: ?>
            <span class="seg-apolice-status">Apólice em emissão</span>
            <span class="seg-apolice-aviso">Avisamos quando ficar pronta.</span>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($propostaPdf || $apolicePdf): ?>
    <div class="seg-card-rodape seg-documentos">
        <?php if ($apolicePdf): ?>
        <button type="button" class="btn btn-primary btn-sm btn-documento" data-seguradora="<?= e($slug) ?>" data-tipo="apolice">
            <?= icon('file-pdf') ?> Apólice
        </button>
        <?php endif; ?>
        <?php if ($propostaPdf): ?>
        <button type="button" class="btn <?= $apolicePdf ? 'btn-secondary' : 'btn-primary' ?> btn-sm btn-documento" data-seguradora="<?= e($slug) ?>" data-tipo="proposta">
            <?= icon('file-pdf') ?> Proposta
        </button>
        <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php elseif ($fichaEncerrada): ?>
        <?php /* Ficha encerrada: sem ações. */ ?>
    <?php elseif ($status === 'aguardando_biometria'): ?>
    <div class="seg-card-rodape">
        <button type="button" class="btn btn-primary btn-sm btn-link-biometria" data-seguradora="<?= e($slug) ?>">
            <?= icon('fingerprint') ?> Link de biometria
        </button>
    </div>
    <?php elseif ($emitindo): ?>
    <div class="seg-processando">
        <span class="seg-processando-spinner" aria-hidden="true"></span>
        <div>
            <span class="seg-processando-titulo">Gerando a proposta...</span>
            <span class="seg-processando-texto">Leva cerca de 1 minuto. Avisamos quando ficar pronta.</span>
        </div>
    </div>
    <?php elseif ($escolhida !== null && $escolhida !== $slug && ($emissaoPendente || $liberada)): ?>
    <?php $aviso = ['tipo' => 'info', 'texto' => 'Proposta da ' . api_seguradora_nome($escolhida) . ' escolhida para esta cotação.']; require __DIR__ . '/aviso.php'; ?>
    <?php elseif (api_emissao_presa($fiancaId, $c)): ?>
    <?php $aviso = ['tipo' => 'erro', 'titulo' => 'Emissão para finalizar com a seguradora', 'texto' => 'A ' . $seguradora['nome'] . ' registrou a proposta da cotação nº ' . ($c['numero_cotacao'] ?: $c['cotacao_externa_id']) . ', mas não devolveu o número dela (falha de comunicação). Finalize a emissão direto com a seguradora — o cliente vê que a MPS está finalizando.']; require __DIR__ . '/aviso.php'; ?>
    <?php elseif ($emissaoPendente || $liberada): ?>
        <?php if (($emissao['status'] ?? '') === 'erro'): ?>
        <?php
            // Erro já traduzido (ex: imóvel com apólice ativa) aparece pra
            // todos; o técnico, só pra MPS.
            $erroEmissao = (string) $emissao['erro'];
            $erroTecnico = str_contains($erroEmissao, 'HTTP') || str_contains($erroEmissao, 'Falha ao');
        ?>
        <?php
            $aviso = [
                'tipo'   => 'erro',
                'titulo' => 'Não foi possível emitir a apólice',
                'texto'  => !$erroTecnico ? $erroEmissao : ($is_admin ? $erroEmissao : 'Confira os dados do proprietário e tente de novo.'),
            ];
            require __DIR__ . '/aviso.php';
        ?>
        <?php endif; ?>
    <?php if ($emissaoPendente && api_documento_em_cache($slug, 'proposta', (string) $c['proposta_id'])): ?>
    <div class="seg-card-rodape seg-documentos">
        <button type="button" class="btn btn-secondary btn-sm btn-documento" data-seguradora="<?= e($slug) ?>" data-tipo="proposta">
            <?= icon('file-pdf') ?> Proposta
        </button>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <?php if ($podeCotar && api_cotacao_ofertavel($c)): ?>
    <?php $__marcada = $etapa === 'proposta' ? in_array($slug, $naPropostaAtual, true) : true; ?>
    <label class="switch seg-card-incluir">
        <input type="checkbox" class="seg-incluir" value="<?= e($slug) ?>" data-nome="<?= e($seguradora['nome']) ?>"<?= $__marcada ? ' checked' : '' ?>>
        <span class="switch-track"></span>
        <span class="switch-label">Enviar ao cliente</span>
    </label>
    <?php elseif (in_array($slug, $naPropostaAtual, true)): ?>
    <span class="seg-card-situacao is-pendente seg-card-na-proposta"><?= icon('paper-plane-tilt') ?> Na proposta enviada ao cliente</span>
    <?php endif; ?>

    <?php if ($c['atualizado_em']): ?>
    <div class="seg-card-rodape-meta">
        <?= icon('clock') ?> Atualizado em <?= e(date('d/m/Y \à\s H:i', strtotime($c['atualizado_em']))) ?>
    </div>
    <?php endif; ?>
</div>
