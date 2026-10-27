{{-- Nova cotação (auto) — AutoController@index. Formulário em produtos.auto._form; $campanha_popup_tipo vem do controller. --}}
@extends('layouts.app')

@section('content')
            <div class="cotacao-container">
                <section class="panel">
                    <div class="panel-body">
                        @include('produtos.auto._form')
                    </div>
                </section>
            </div>
@endsection

@push('antes-scripts')
@if ($rascunho_dados)
<script>window.__RASCUNHO_DADOS = {!! json_encode($rascunho_dados) !!};</script>
@endif

@include('partials.campanha-popup')
@endpush

@push('scripts')
<script>
document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('form-cotacao-auto');
    var btn  = document.getElementById('btn-salvar-rascunho');
    RascunhoCotacao.ligarBotaoSalvar(btn, form, 'auto', btn.dataset.csrf);

    if (window.__RASCUNHO_DADOS) {
        var dados = window.__RASCUNHO_DADOS;
        var fipeKeys = ['tipo_veiculo', 'marca_id', 'modelo_id', 'ano_id'];
        var resto = {};
        Object.keys(dados).forEach(function (k) {
            if (fipeKeys.indexOf(k) === -1) resto[k] = dados[k];
        });
        RascunhoCotacao.preencher(form, resto);
        RascunhoCotacao.preencherFipeCascata(form, dados);
    }
});
</script>
@endpush
