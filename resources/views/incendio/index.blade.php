@extends('layouts.app')

@section('content')
            <div class="cotacao-container">
                @include('incendio._partials.form')
            </div>
@endsection

@push('antes-scripts')
@if ($rascunho_dados)
<script>window.__RASCUNHO_INCENDIO = {!! json_encode([
    'nome'    => $rascunho_dados['nome_rascunho'],
    'imoveis' => json_decode((string) $rascunho_dados['imoveis_json'], true) ?: [],
], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!};</script>
@endif
@endpush
