@extends('layouts.app')

@section('content')
            <div class="alert alert-info">
                <span class="alert-icon">{!! icon('info') !!}</span>
                Monte o chat guiado do FAQ empilhando blocos. O bloco "Lista de Opções" ramifica: cada opção pode ter seu próprio sub-fluxo aninhado. Arraste pela alcinha pra reordenar.
            </div>

            <div class="admin-faq-toolbar">
                <a href="{{ url('/admin/faq/atendimentos') }}" class="btn btn-secondary btn-sm">
                    {!! icon('list') !!} Ver atendimentos
                </a>
                <button type="button" class="btn btn-secondary btn-sm" id="btn-testar-fluxo">
                    {!! icon('chat-circle') !!} Testar fluxo
                </button>
                <button type="button" class="btn btn-primary btn-sm" id="btn-salvar-fluxo">
                    {!! icon('check') !!} Salvar fluxo
                </button>
            </div>

            <div id="flow-list">
                @if (!$flow)
                <div class="flow-empty" id="flow-empty">
                    <div class="flow-empty-icon">{!! icon('chat-circle') !!}</div>
                    <div class="flow-empty-title">Nenhum bloco criado ainda</div>
                    <div class="flow-empty-sub">Use o botão abaixo para adicionar o primeiro bloco do fluxo</div>
                </div>
                @endif
                @php
                fluxoRenderAddZone();
                foreach ($flow as $bloco) {
                    fluxoRenderBloco($bloco);
                    fluxoRenderAddZone();
                }
                @endphp
            </div>

        
@endsection

@push('antes-scripts')
<!-- Modal: testar fluxo (roda o fluxo em edição, sem precisar salvar antes) -->
<div class="modal" id="modal-testar-fluxo" hidden>
    <div class="modal-backdrop" data-close="modal-testar-fluxo"></div>
    <div class="modal-dialog">
        <div class="modal-header">
            <h3>Testar fluxo</h3>
            <button type="button" class="modal-close" data-close="modal-testar-fluxo">{!! icon('x') !!}</button>
        </div>
        <div class="modal-body modal-body-flush">
            <div class="chamado-chat-box" id="testar-fluxo-box">
                <div class="chamado-chat-messages">
                    <div class="chamado-thread" id="testar-fluxo-thread"></div>
                </div>
                <div class="chamado-chat-input-bar" id="testar-fluxo-input-bar" hidden>
                    <input type="text" id="testar-fluxo-input" class="chamado-input-field" placeholder="Digite aqui..." maxlength="500" autocomplete="off">
                    <button type="button" class="chamado-send-btn" id="testar-fluxo-send">{!! icon('paper-plane-tilt') !!}</button>
                </div>
            </div>
        </div>
    </div>
</div>
@endpush
