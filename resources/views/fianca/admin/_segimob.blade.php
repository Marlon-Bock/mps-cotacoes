@php
/**
 * Coluna direita do detalhe da Fiança na Segimob — visão da MPS, durante a
 * análise ('Cotação API'). O resultado pré-aprova ou recusa a ficha sozinho
 * (segimob_verificar_analise_concluida / segimob_verificar_recusa_final); aí
 * segue o painel de documentos, igual ao manual. JS em cotacao-fianca-segimob.js.
 *
 * Espera: $cotacao (seguro_fianca), $protocolo, $status.
 */
$__lote  = segimob_status_lote([(int) $cotacao['id']])[(int) $cotacao['id']];
$__etapa = segimob_status_resumo_lote($__lote)['etapa'];
[$__tipo, $__icone, $__titulo, $__texto] = match ($__etapa) {
    'corrigir' => ['alerta', 'warning', 'Aguardando a imobiliária corrigir', segimob_erro_amigavel((string) ($__lote['envio']['erro'] ?? ''))],
    'erro'     => ['erro', 'warning', 'Falha no envio — reenvio automático', segimob_erro_amigavel((string) ($__lote['envio']['erro'] ?? ''))],
    default    => ['analise', 'spinner', 'Em análise nas seguradoras', 'O resultado pré-aprova ou recusa a cotação automaticamente.'],
};
@endphp
<div class="panel" id="painel-segimob">
    <div class="panel-header">
        <h3 class="panel-title">{!! icon('buildings') !!} Análise nas seguradoras</h3>
    </div>
    <div class="panel-body">
        <div class="seg-processando seg-processando--solto seg-processando--{{ $__tipo }} seg-mps-estado">
            @if ($__icone === 'spinner')
            <span class="seg-processando-spinner" aria-hidden="true"></span>
            @else
            <span class="seg-processando-icone">{!! icon($__icone) !!}</span>
            @endif
            <div>
                <span class="seg-processando-titulo">{{ $__titulo }}</span>
                @if ($__texto !== '')
                <span class="seg-processando-texto">{{ $__texto }}</span>
                @endif
            </div>
        </div>
        <button type="button" class="btn btn-secondary btn-block" id="btn-atualizar-seguradoras">{!! icon('arrows-clockwise') !!} Atualizar</button>
    </div>
</div>

<script src="{{ asset_v('/assets/js/cotacao-fianca-segimob.js') }}"></script>
<script>
window.__SEGIMOB_CONFIG = {
    protocolo: {!! json_encode($protocolo) !!},
    isAdmin: true,
    csrf: {!! json_encode(csrf_token()) !!},
    aguardando: {!! segimob_tela_aguardando($cotacao, $__lote['envio'], $__lote['cotacoes']) ? 'true' : 'false' !!},
    assinatura: {!! json_encode($cotacao['status'] . '|' . $__etapa) !!},
};
</script>
