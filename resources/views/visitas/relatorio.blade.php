{{-- Relatório de visitas (aceita ?ag_id=N) — VisitasController@relatorio. Lista via AJAX. --}}
@extends('layouts.app')

@section('content')
            @if ($agendamento)
            <div class="alert alert-info">
                <span class="alert-icon">{!! icon('calendar-blank') !!}</span>
                Completando o agendamento <strong>{{ $agendamento['titulo'] }}</strong>{!! $agendamento['imobiliaria_nome'] ? ' — ' . e($agendamento['imobiliaria_nome']) : '' !!}.
                Ao salvar, ele será marcado como concluído.
            </div>
            @endif

            <div class="form-layout-70-30">
                <div class="form-layout-main">
                    <section class="panel">
                        <div class="panel-header">
                            <h2>{!! icon('note-pencil') !!} <span id="visita-form-titulo">Nova visita</span></h2>
                        </div>
                        <div class="panel-body">
                            <form id="form-visita" class="cotacao-container" data-csrf="{{ csrf_token() }}">
                                <input type="hidden" id="f-item-id" value="">
                                <input type="hidden" id="f-ag-id" value="{!! $agendamento ? (int) $agendamento['id'] : '' !!}">

                                <div class="form-section">
                                <div class="field visita-imob-field">
                                    <label class="field-label">Imobiliária <span class="required">*</span></label>
                                    <div class="field-input-wrap">
                                        <span class="field-input-icon">{!! icon('buildings') !!}</span>
                                        <input type="text" id="f-imobiliaria-nome" class="field-input has-icon" placeholder="Buscar imobiliária..." autocomplete="off"
                                               value="{!! $agendamento ? e($agendamento['imobiliaria_nome']) : '' !!}">
                                    </div>
                                    <input type="hidden" id="f-imobiliaria-id" value="{!! $agendamento ? (int) $agendamento['imobiliaria_id'] : '' !!}">
                                    <ul class="visita-imob-sugestoes" id="visita-imob-sugestoes" hidden></ul>
                                </div>

                                <div class="form-grid">
                                    <div class="field">
                                        <label class="field-label">Gerente <span class="required">*</span></label>
                                        <div class="field-input-wrap">
                                            <span class="field-input-icon">{!! icon('user') !!}</span>
                                            <input type="text" id="f-gerente" class="field-input has-icon" placeholder="Nome do gerente" required maxlength="120">
                                        </div>
                                    </div>
                                    <div class="field">
                                        <label class="field-label">Atendente</label>
                                        <div class="field-input-wrap">
                                            <span class="field-input-icon">{!! icon('headset') !!}</span>
                                            <input type="text" id="f-atendente" class="field-input has-icon" placeholder="Nome do atendente" maxlength="120">
                                        </div>
                                    </div>
                                </div>

                                <div class="form-grid">
                                    <div class="field">
                                        <label class="field-label">Horário de entrada <span class="required">*</span></label>
                                        {!! time_picker('f-entrada') !!}
                                    </div>
                                    <div class="field">
                                        <label class="field-label">Horário de saída <span class="required">*</span></label>
                                        {!! time_picker('f-saida') !!}
                                    </div>
                                </div>

                                <div class="form-grid">
                                    <div class="field">
                                        <label class="field-label">Motivo da visita <span class="required">*</span></label>
                                        {!! custom_select('motivo_visita', [
                                            'Prospecção'          => 'Prospecção',
                                            'Acompanhamento'      => 'Acompanhamento',
                                            'Entrega de material' => 'Entrega de material',
                                            'Reunião'             => 'Reunião',
                                            'Outro'               => 'Outro',
                                        ], 'Selecione...') !!}
                                    </div>
                                    <div class="field">
                                        <label class="field-label">Detalhe do motivo</label>
                                        <div class="field-input-wrap">
                                            <span class="field-input-icon">{!! icon('note-pencil') !!}</span>
                                            <input type="text" id="f-motivo-descricao" class="field-input has-icon" placeholder="Detalhe opcional" maxlength="255">
                                        </div>
                                    </div>
                                </div>

                                <div class="field">
                                    <label class="field-label">Corretores que foram junto</label>
                                    <div class="field-input-wrap">
                                        <span class="field-input-icon">{!! icon('headset') !!}</span>
                                        @php
                                        $wrapperAttrsExtra = [
                                            'id'              => 'corretores-extra-select',
                                            'data-multi'      => '1',
                                            'data-selecionados' => implode(',', $corretores_extra_default),
                                        ];
                                        @endphp
                                        {!! custom_select('corretores_extra', $opcoes_corretor_extra, 'Selecione os corretores...', null, 'has-icon', false, $wrapperAttrsExtra) !!}
                                    </div>
                                </div>

                                <div class="field">
                                    <label class="field-label">Seguros trabalhados na visita</label>
                                    <div class="visita-seguros-grid" id="visita-seguros-grid">
                                        @foreach (['fianca' => 'Fiança', 'cap' => 'Título de Capitalização', 'incendio' => 'Incêndio', 'vida' => 'Vida', 'auto' => 'Auto'] as $tipo => $label)
                                        <div class="visita-seguro-item">
                                            <label class="switch">
                                                <input type="checkbox" class="visita-seguro-check" data-tipo="{{ $tipo }}">
                                                <span class="switch-track"></span>
                                                <span class="switch-label">{{ $label }}</span>
                                            </label>
                                            <div class="visita-comissao-wrap" data-tipo="{{ $tipo }}" hidden>
                                                <div class="visita-comissao-modo">
                                                    <label class="field-radio"><input type="radio" name="modo-{{ $tipo }}" value="percentual" checked> %</label>
                                                    <label class="field-radio"><input type="radio" name="modo-{{ $tipo }}" value="valor"> R$</label>
                                                </div>
                                                <div class="field-input-wrap visita-comissao-valor-wrap">
                                                    <span class="field-input-prefix" hidden>R$</span>
                                                    <input type="text" inputmode="decimal" class="field-input has-suffix visita-comissao-valor" data-tipo="{{ $tipo }}" data-mask="percentual" placeholder="0,00">
                                                    <span class="field-input-suffix">%</span>
                                                </div>
                                            </div>
                                        </div>
                                        @endforeach
                                    </div>
                                    <div class="field visita-outros-seguros">
                                        <label class="field-label">Outros seguros</label>
                                        <div class="field-input-wrap">
                                            <span class="field-input-icon">{!! icon('clipboard-text') !!}</span>
                                            <input type="text" id="f-outros-seguros" class="field-input has-icon" placeholder="Ex: Residencial, Empresarial..." maxlength="180">
                                        </div>
                                    </div>
                                </div>

                                <div class="field">
                                    <label class="switch">
                                        <input type="checkbox" id="f-falta-material">
                                        <span class="switch-track"></span>
                                        <span class="switch-label">Faltou material nesta visita</span>
                                    </label>
                                    <div class="field visita-materiais-wrap" id="f-materiais-wrap" hidden>
                                        <label class="field-label">Quais materiais faltaram?</label>
                                        <div class="field-input-wrap">
                                            <span class="field-input-icon">{!! icon('warning') !!}</span>
                                            <input type="text" id="f-materiais-faltantes" class="field-input has-icon" placeholder="Ex: Folder, cartão de visita..." maxlength="255">
                                        </div>
                                    </div>
                                </div>

                                <div class="field">
                                    <label class="field-label">Observações</label>
                                    <textarea id="f-observacoes" class="field-input" placeholder="Alguma observação sobre a visita..." rows="3" maxlength="5000"></textarea>
                                </div>
                                </div>

                                <div class="visita-form-actions">
                                    <button type="submit" class="btn btn-primary" id="btn-salvar-visita">
                                        {!! icon('check') !!} <span id="btn-salvar-visita-label">Salvar visita</span>
                                    </button>
                                    <button type="button" class="btn btn-secondary" id="btn-limpar-visita">Limpar</button>
                                </div>
                            </form>
                        </div>
                    </section>
                </div>

                <div class="panel cot-panel">
                    <div class="panel-header">
                        <h2>Visitas de hoje <span class="badge badge-primary" id="visitas-hoje-badge" hidden>0</span></h2>
                    </div>
                    <div id="visitas-hoje-lista">
                        <div class="panel-body">
                            <div class="empty-state">
                                <div class="empty-state-icon">{!! icon('clipboard-text') !!}</div>
                                <p>Carregando...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        
@endsection

@push('antes-scripts')
<!-- Corretores (id/username/avatar) — para o multi-select "Corretores que foram junto" -->
<script type="application/json" id="relatorio-usuarios-json">{!! json_encode(
    array_map(static fn($c) => [
        'id'       => (int) $c['id'],
        'username' => $c['username'],
        'avatar'   => $c['avatar'] ?: null,
    ], $corretores),
    JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT
) !!}</script>
@endpush
