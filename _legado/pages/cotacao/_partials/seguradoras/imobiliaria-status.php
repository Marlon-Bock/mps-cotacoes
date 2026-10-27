<?php
/**
 * Coluna direita do detalhe da Fiança na API das seguradoras — visão da
 * IMOBILIÁRIA. Só a pré-análise (Em análise / Pré-aprovado), "Corrigir
 * dados" quando a seguradora recusou um dado conhecido, e o resumo das
 * propostas/apólice (que ficam na coluna principal — imobiliaria-propostas.php).
 * Nada de seguradora por seguradora: isso é da MPS.
 *
 * Espera: $ficha (seguro_fianca), $api = ['lote' => api_status_lote()[id], 'resumo' => api_status_resumo_fluxo(.., false)].
 * Carrega o JS das seguradoras (polling, corrigir, aceitar/recusar propostas, biometria, PDFs).
 */
$__etapa = $api['resumo']['etapa'];
$__label = $api['resumo']['label'];

// Um bloco só por estado (ícone + título + texto) — mesmo visual do dev-cotacoes-3.
$__estado = static function (string $tipo, string $icone, string $titulo, string $texto, string $acao = ''): void {
    echo '<div class="seg-processando seg-processando--solto seg-processando--' . e($tipo) . '">'
        . ($icone === 'spinner' ? '<span class="seg-processando-spinner" aria-hidden="true"></span>' : '<span class="seg-processando-icone">' . icon($icone) . '</span>')
        . '<div><span class="seg-processando-titulo">' . e($titulo) . '</span>'
        . '<span class="seg-processando-texto">' . e($texto) . '</span>'
        . ($acao !== '' ? '<div class="seg-processando-acao">' . $acao . '</div>' : '')
        . '</div></div>';
};
?>
<div class="panel" id="painel-api">
    <div class="panel-header">
        <h3 class="panel-title"><?= icon('magnifying-glass') ?> Análise da cotação</h3>
    </div>
    <div class="panel-body">
        <?php if ($__etapa === 'corrigir'): ?>
            <?php
            $correcoes      = api_correcoes_pendentes($ficha, $api['lote']['cotacoes']);
            $camposCorrigir = $correcoes !== [] ? array_values(array_unique(array_merge(...array_column($correcoes, 'campos')))) : [];
            $__estado('erro', 'warning', 'Corrija os dados para continuar',
                implode(' ', array_unique(array_column($correcoes, 'mensagem'))),
                '<button type="button" class="btn btn-primary btn-sm btn-corrigir-dados">' . icon('note-pencil') . ' Corrigir dados</button>');
            if ($correcoes) require __DIR__ . '/modal-corrigir.php';
            ?>
        <?php elseif ($__etapa === 'biometria'): ?>
            <?php
            $__biometrias   = api_biometrias($api['lote']['cotacoes']);
            $__faltam       = count(array_filter($__biometrias, 'api_cotacao_biometria_pendente'));
            $__telefone     = preg_replace('/\D/', '', (string) ($ficha['tel_celular'] ?? ''));
            $__comSeguradora = false;
            $__estado('alerta', 'fingerprint', 'Biometria pendente',
                count($__biometrias) > 1
                    ? 'A ficha foi pré-aprovada e as seguradoras pedem ' . count($__biometrias) . ' biometrias faciais — o pretendente precisa fazer todas (' . $__faltam . ' pendente' . ($__faltam > 1 ? 's' : '') . '). A MPS envia a proposta depois.'
                    : 'A ficha foi pré-aprovada e a seguradora pede a biometria facial do pretendente. Envie o link para ele — a MPS envia a proposta depois que ele concluir.');
            require __DIR__ . '/biometria-lista.php';
            ?>
        <?php elseif ($__etapa === 'proposta'): ?>
            <?php $__estado('ok', 'clipboard-text', 'Proposta disponível', 'A MPS enviou as propostas das seguradoras. Escolha a melhor para o cliente ou recuse.'); ?>
        <?php elseif ($__etapa === 'propostas_recusadas'): ?>
            <?php $__estado('alerta', 'clock', 'Propostas recusadas', 'A MPS foi avisada e pode enviar novas propostas.'); ?>
        <?php elseif (in_array($__etapa, ['contratada', 'emitida'], true)): ?>
            <?php $__estado('ok', 'check-circle', 'Aprovado', 'Proposta aceita. A MPS envia a proposta e a apólice assim que a seguradora gerar.'); ?>
        <?php elseif ($__label === 'Pré-aprovado'): ?>
            <?php $__estado('ok', 'check-circle', 'Pré-aprovado', 'A ficha foi pré-aprovada nas seguradoras. A MPS está preparando a proposta — você será avisado.'); ?>
        <?php else: ?>
            <?php $__estado('analise', 'spinner', 'Em análise', 'A ficha está em análise nas seguradoras. Você será avisado quando houver resultado.'); ?>
        <?php endif; ?>
    </div>
</div>

<script src="<?= e(asset_v('/assets/js/masks.js')) ?>"></script>
<script src="<?= e(asset_v('/assets/js/cotacao-fianca-seguradoras.js')) ?>"></script>
<script>
window.__SEGURADORAS_CONFIG = {
    protocolo: <?= json_encode($ficha['protocolo']) ?>,
    csrf: <?= json_encode(csrf_token()) ?>,
    // Detalhe da imobiliária: o polling compara a assinatura do que ela vê (seguradoras/situacao).
    aguardandoImobiliaria: <?= json_encode(api_imobiliaria_aguardando($ficha, $__etapa)) ?>,
    assinatura: <?= json_encode(api_assinatura_imobiliaria($ficha, $api['lote'])) ?>,
};
</script>
