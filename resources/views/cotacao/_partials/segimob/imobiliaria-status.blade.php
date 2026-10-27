@php
/**
 * Coluna direita do detalhe da Fiança na Segimob — visão da IMOBILIÁRIA,
 * durante a análise ('Cotação API'): "Em análise" ou "Corrigir dados" quando
 * as seguradoras não aceitaram algum dado. O resultado pré-aprova ou recusa a
 * ficha sozinho; aí segue o painel de documentos. Nada de seguradora por
 * seguradora.
 *
 * Espera: $ficha (seguro_fianca), $seg = ['envio', 'cotacoes', 'resumo'].
 */
$__etapa = $seg['resumo']['etapa'];
@endphp
<div class="panel" id="painel-segimob">
    <div class="panel-header">
        <h3 class="panel-title">{!! icon('magnifying-glass') !!} Análise da cotação</h3>
    </div>
    <div class="panel-body">
        @if ($__etapa === 'corrigir')
            @php $correcoes = segimob_correcoes((int) $ficha['id']); @endphp
            <div class="seg-processando seg-processando--solto seg-processando--erro">
                <span class="seg-processando-icone">{!! icon('warning') !!}</span>
                <div>
                    <span class="seg-processando-titulo">{{ $correcoes ? 'Corrija os dados para continuar' : 'As seguradoras não aceitaram a ficha' }}</span>
                    <span class="seg-processando-texto">{{ segimob_erro_amigavel((string) ($seg['envio']['erro'] ?? '')) }}</span>
                    @if ($correcoes)
                    <div class="seg-processando-acao">
                        <button type="button" class="btn btn-primary btn-sm" id="btn-corrigir-dados">{!! icon('note-pencil') !!} Corrigir dados</button>
                    </div>
                    @endif
                </div>
            </div>
            @if ($correcoes)
            @include('cotacao._partials.segimob.modal-corrigir', ['correcoes' => $correcoes])
            @endif
        @else
            <div class="seg-processando seg-processando--solto">
                <span class="seg-processando-spinner" aria-hidden="true"></span>
                <div>
                    <span class="seg-processando-titulo">Em análise</span>
                    <span class="seg-processando-texto">A ficha está em análise nas seguradoras. Você será avisado quando houver resultado.</span>
                </div>
            </div>
        @endif
    </div>
</div>

<script src="{{ asset_v('/assets/js/masks.js') }}"></script>
<script src="{{ asset_v('/assets/js/cotacao-fianca-segimob.js') }}"></script>
<script>
window.__SEGIMOB_CONFIG = {
    protocolo: {!! json_encode($ficha['protocolo']) !!},
    isAdmin: false,
    csrf: {!! json_encode(csrf_token()) !!},
    aguardando: {!! segimob_tela_aguardando($ficha, $seg['envio'], $seg['cotacoes']) ? 'true' : 'false' !!},
    assinatura: {!! json_encode($ficha['status'] . '|' . $__etapa) !!},
};
</script>
