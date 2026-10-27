{{-- Nova cotação (condominial) — CondominialController@index. Formulário em produtos.condominial._form; $campanha_popup_tipo vem do controller. --}}
@extends('layouts.app')

@section('content')
            <div class="cotacao-container">
                <section class="panel">
                    <div class="panel-body">
                        @include('produtos.condominial._form')
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
    var form = document.getElementById('form-cotacao-condominial');
    var btn  = document.getElementById('btn-salvar-rascunho');
    if (window.__RASCUNHO_DADOS) RascunhoCotacao.preencher(form, window.__RASCUNHO_DADOS);
    RascunhoCotacao.ligarBotaoSalvar(btn, form, 'condominial', btn.dataset.csrf);
});
</script>
@endpush
