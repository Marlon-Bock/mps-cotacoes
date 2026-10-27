{{-- Histórico de visitas — VisitasController@historico. Lista via AJAX. --}}
@extends('layouts.app')

@section('content')
            <div class="cotacoes-filtros admin-filtros cotacao-container">
                <div class="field">
                    <label class="field-label">Corretor</label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon">{!! icon('headset') !!}</span>
                        {!! custom_select('corretor_id', $opcoes_corretor, 'Todos os corretores', null, 'has-icon') !!}
                    </div>
                </div>

                <div class="field">
                    <label class="field-label">Imobiliária</label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon">{!! icon('buildings') !!}</span>
                        <input type="text" id="f-imobiliaria" class="field-input has-icon" placeholder="Buscar por nome...">
                    </div>
                </div>

                <div class="field">
                    <label class="field-label">Data (de / até)</label>
                    <div class="field-date-range">
                        {!! date_picker('f-data-de') !!}
                        {!! date_picker('f-data-ate') !!}
                    </div>
                </div>
            </div>

            <div class="panel cot-panel">
                <div class="panel-header">
                    <h2>Visitas <span class="badge badge-primary" id="historico-total-badge" hidden>0</span></h2>
                </div>
                <div id="historico-lista">
                    <div class="admin-cot-empty">Carregando...</div>
                </div>
                <div class="admin-cot-pagination" id="historico-pagination" hidden>
                    <span id="historico-pag-info" class="admin-cot-pagination-info"></span>
                    <div class="admin-cot-pagination-btns">
                        <button type="button" id="historico-pag-anterior" class="btn btn-secondary btn-sm">{!! icon('arrow-left') !!} Anterior</button>
                        <button type="button" id="historico-pag-proxima" class="btn btn-secondary btn-sm">Próxima {!! icon('arrow-right') !!}</button>
                    </div>
                </div>
            </div>

        
@endsection
