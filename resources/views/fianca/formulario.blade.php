{{-- Nova cotação — Seguro Fiança Residencial/Comercial — Fianca\FiancaController@residencial|comercial.
     Era pages/cotacao/fianca/residencial.php e comercial.php (idênticas, só mudava $tipo_ficha). --}}
@extends('layouts.app')

@section('content')
            <div class="cotacao-container">

                <section class="panel">
                    <div class="panel-body">
                        @if ($_cotacao_protocolo_get !== '')
                        <input type="hidden" name="cotacao" value="{{ $_cotacao_protocolo_get }}" id="field_cotacao" form="form-fianca">
                        @endif
                        @include($form_view)
                    </div>
                </section>

            </div>
@endsection

@push('antes-scripts')
@if ($rascunho_dados)
<script>window.__RASCUNHO_DADOS = {!! json_encode($rascunho_dados) !!};</script>
@endif

@if ($pre_analise_dados)
<script>window.__PRE_ANALISE_DADOS = {!! json_encode($pre_analise_dados) !!};</script>
@endif

@include('partials.campanha-popup')
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('form-fianca');
    var btn  = document.getElementById('btn-salvar-rascunho');
    if (window.__PRE_ANALISE_DADOS) RascunhoCotacao.preencher(form, window.__PRE_ANALISE_DADOS);
    if (window.__RASCUNHO_DADOS) RascunhoCotacao.preencher(form, window.__RASCUNHO_DADOS);
    RascunhoCotacao.ligarBotaoSalvar(btn, form, 'fianca', btn.dataset.csrf);
});
</script>
@endpush
