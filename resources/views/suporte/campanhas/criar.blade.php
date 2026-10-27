@extends('layouts.app')

@section('content')
            <form
                method="POST"
                action="{{ url('/admin/campanhas/criar') }}"
                enctype="multipart/form-data"
                novalidate
                id="form-campanha"
                class="cotacao-container form-layout-70-30"
            >
                <input type="hidden" name="_csrf" value="{{ csrf_token() }}">

                <div class="form-layout-main">

                    <div class="panel">
                        <div class="panel-header">
                            <h2>{!! icon('megaphone') !!} Dados da campanha</h2>
                        </div>
                        <div class="panel-body">
                            <div class="form-section">

                                <div class="field">
                                    <label class="field-label required" for="titulo">
                                        Título
                                        @if (!empty($erros['titulo']))
                                            <span class="field-error">{{ $erros['titulo'] }}</span>
                                        @endif
                                    </label>
                                    <div class="field-input-wrap">
                                        <span class="field-input-icon">{!! icon('megaphone') !!}</span>
                                        <input
                                            class="field-input has-icon{!! !empty($erros['titulo']) ? ' is-error' : '' !!}"
                                            type="text"
                                            id="titulo"
                                            name="titulo"
                                            maxlength="150"
                                            value="{{ $valores['titulo'] }}"
                                            placeholder="Título da campanha"
                                        >
                                    </div>
                                </div>

                                <div class="form-grid">
                                    <div class="field">
                                        <label class="field-label required" for="data_inicio">
                                            Data de início
                                            @if (!empty($erros['data_inicio']))
                                                <span class="field-error">{{ $erros['data_inicio'] }}</span>
                                            @endif
                                        </label>
                                        {!! date_picker('data_inicio', 'DD/MM/AAAA', $valores['data_inicio'] ?: null) !!}
                                    </div>
                                    <div class="field">
                                        <label class="field-label required" for="data_fim">
                                            Data de fim
                                            @if (!empty($erros['data_fim']))
                                                <span class="field-error">{{ $erros['data_fim'] }}</span>
                                            @endif
                                        </label>
                                        {!! date_picker('data_fim', 'DD/MM/AAAA', $valores['data_fim'] ?: null) !!}
                                    </div>
                                </div>

                                <div class="field">
                                    <label class="field-label" for="status">Status</label>
                                    {!! custom_select('status', ['ativo' => 'Ativa', 'inativo' => 'Inativa'], 'Selecione o status', $valores['status']) !!}
                                </div>

                                <div class="field">
                                    <label class="field-label" for="imagem">
                                        Imagem / Banner
                                        @if (!empty($erros['imagem']))
                                            <span class="field-error">{{ $erros['imagem'] }}</span>
                                        @endif
                                    </label>
                                    <div class="field-file-picker" data-file-picker>
                                        <input type="file" id="imagem" name="imagem" accept="image/png,image/jpeg,image/webp" hidden>
                                        <button type="button" class="btn btn-secondary btn-sm" data-file-picker-trigger>{!! icon('clipboard-text') !!} Escolher imagem</button>
                                        <span class="field-file-picker-name" data-file-picker-name>Nenhum arquivo selecionado</span>
                                    </div>
                                    <span class="field-hint">PNG, JPEG ou WEBP — até 2MB. Recomendado: proporção retrato 17:24 (a imagem será cortada para preencher esse formato no popup).</span>
                                </div>

                                <div class="field">
                                    <label class="field-label" for="observacoes">Observações / detalhes</label>
                                    <textarea
                                        class="field-input"
                                        id="observacoes"
                                        name="observacoes"
                                        rows="4"
                                        placeholder="Observações internas sobre a campanha (não aparece para a imobiliária)"
                                    >{{ $valores['observacoes'] }}</textarea>
                                </div>

                            </div>
                        </div>
                    </div>

                    <div class="panel">
                        <div class="panel-header">
                            <h2>{!! icon('bell') !!} Popup</h2>
                        </div>
                        <div class="panel-body">
                            <div class="form-section">
                                <div class="field">
                                    <label class="switch">
                                        <input type="checkbox" id="popup-ativo" name="popup_ativo" value="1" {!! $valores['popup_ativo'] ? 'checked' : '' !!}>
                                        <span class="switch-track"></span>
                                        <span class="switch-label">Exibir como popup</span>
                                    </label>
                                    <span class="field-hint">Mostra a imagem da campanha automaticamente para a imobiliária ao abrir a tela escolhida abaixo.</span>
                                </div>

                                <div class="form-section no-margin-bottom" id="bloco-popup-tipos" {!! $valores['popup_ativo'] ? '' : 'hidden' !!}>
                                    @foreach ($campanha_popup_tipos as $tipo => $label)
                                    <div class="field">
                                        <label class="switch">
                                            <input type="checkbox" name="popup_{{ $tipo }}" value="1" {!! $valores["popup_{$tipo}"] ? 'checked' : '' !!}>
                                            <span class="switch-track"></span>
                                            <span class="switch-label">{{ $label }}</span>
                                        </label>
                                    </div>
                                    @endforeach
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="form-layout-side">

                    <div class="panel">
                        <div class="panel-header">
                            <h2>{!! icon('buildings') !!} Segmentação</h2>
                        </div>
                        <div class="panel-body campanha-imob-segmentacao">

                            <div class="alert alert-info">
                                <span class="alert-icon">{!! icon('info') !!}</span>
                                Selecione as imobiliárias que verão esta campanha. Deixe em branco para exibir a todas.
                            </div>

                            @if (empty($imobiliarias))
                                <div class="empty-state">
                                    <div class="empty-state-icon">{!! icon('buildings') !!}</div>
                                    <p>Nenhuma imobiliária ativa encontrada.</p>
                                </div>
                            @else
                                <div class="imob-tags" id="campanha-imob-tags" data-selecionadas="{{ implode(',', $imob_vinculadas) }}">
                                    <div class="imob-tags-chips" id="campanha-imob-chips"></div>
                                    <div class="imob-tags-search">
                                        <div class="field-input-wrap">
                                            <span class="field-input-icon">{!! icon('magnifying-glass') !!}</span>
                                            <input type="text" id="campanha-imob-busca" class="field-input has-icon" placeholder="Buscar imobiliária para adicionar..." autocomplete="off">
                                        </div>
                                        <ul class="imob-tags-suggestions" id="campanha-imob-sugestoes" hidden></ul>
                                    </div>
                                </div>
                                <script type="application/json" id="campanha-imob-dados">{!! json_encode(
                                    array_map(static fn($i) => ['id' => (int) $i['id'], 'nome' => $i['nome']], $imobiliarias),
                                    JSON_UNESCAPED_UNICODE
                                ) !!}</script>
                            @endif

                        </div>
                    </div>

                    <div class="form-actions form-actions-side">
                        <button type="submit" class="btn btn-primary">
                            {!! icon('paper-plane-tilt') !!}
                            Salvar
                        </button>
                        <a href="{{ url('/admin/campanhas') }}" class="btn btn-secondary">
                            Cancelar
                        </a>
                    </div>

                </div>

            </form>
        
@endsection

@push('antes-scripts')
<script>
(function () {
    var popupAtivo = document.getElementById('popup-ativo');
    var blocoTipos = document.getElementById('bloco-popup-tipos');
    if (popupAtivo && blocoTipos) {
        popupAtivo.addEventListener('change', function () {
            blocoTipos.hidden = !popupAtivo.checked;
        });
    }

})();
</script>
@endpush
