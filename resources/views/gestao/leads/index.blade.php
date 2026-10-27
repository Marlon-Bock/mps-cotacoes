{{-- Leads do site — /admin/leads (LeadsController@index). Linhas via /api/admin/leads/listar. --}}
@extends('layouts.app')

@section('content')
            <div class="admin-cot-filterbar">

                <div class="admin-cot-toolbar">
                    <div class="status-kpi-grid">
                        <span class="status-kpi-card is-active">
                            <span class="status-kpi-icon">{!! icon('monitor') !!}</span>
                            <span class="status-kpi-value" id="count-todos">0</span>
                            <span class="status-kpi-label">Leads recebidos</span>
                        </span>
                    </div>

                    <div class="admin-cot-toolbar-actions">
                        <button type="button" id="btn-limpar" class="btn btn-secondary btn-sm" style="display:none">
                            Limpar filtros
                        </button>
                    </div>
                </div>

                <!-- Filtros -->
                <div class="cotacoes-filtros admin-filtros cotacao-container">
                    <div class="field">
                        <label class="field-label">Seguro</label>
                        <div class="field-input-wrap">
                            <span class="field-input-icon">{!! icon('clipboard-text') !!}</span>
                            {!! custom_select('f_seguro', $opcoes_seguro, 'Todos', null, 'has-icon', false, ['id' => 'f-seguro-wrap']) !!}
                        </div>
                    </div>
                    <div class="field">
                        <label class="field-label">Protocolo</label>
                        <div class="field-input-wrap">
                            <span class="field-input-icon">{!! icon('magnifying-glass') !!}</span>
                            <input type="text" id="f-protocolo" class="field-input has-icon" placeholder="Protocolo">
                        </div>
                    </div>
                    <div class="field field-search-wide">
                        <label class="field-label">Nome, e-mail, telefone ou CPF</label>
                        <div class="field-input-wrap">
                            <span class="field-input-icon">{!! icon('magnifying-glass') !!}</span>
                            <input type="text" id="f-busca" class="field-input has-icon" placeholder="Buscar por nome, e-mail, telefone ou CPF">
                        </div>
                    </div>
                    <div class="field">
                        <label class="field-label">Data de envio (de / até)</label>
                        <div class="field-date-range">
                            {!! date_picker('f-data-de') !!}
                            {!! date_picker('f-data-ate') !!}
                        </div>
                    </div>
                </div>

            </div>

            <!-- Lista (grid de divs — sem table) -->
            <div class="panel cot-panel">
                <div class="admin-cot-grid-wrap">
                    <div class="admin-cot-row admin-cot-head">
                        <div class="admin-cot-cell">
                            <span class="th-produto-so">Seguro</span>
                            <span class="th-produto-protocolo">Seguro/Protocolo</span>
                        </div>
                        <div class="admin-cot-cell">Protocolo</div>
                        <div class="admin-cot-cell admin-cot-cell-nome">Cliente</div>
                        <div class="admin-cot-cell">Telefone</div>
                        <div class="admin-cot-cell">E-mail</div>
                        <div class="admin-cot-cell">Perfil</div>
                        <div class="admin-cot-cell th-sortable" data-sort="criacao">Datas <span class="sort-arrow">▼</span></div>
                        <div class="admin-cot-cell">Ações</div>
                    </div>
                    <div id="leads-rows">
                        <div class="admin-cot-empty">Carregando...</div>
                    </div>
                </div>
                <div class="admin-cot-pagination">
                    <span id="pag-info" class="admin-cot-pagination-info"></span>
                    <div class="admin-cot-pagination-btns">
                        <button type="button" id="pag-anterior" class="btn btn-secondary btn-sm">{!! icon('arrow-left') !!} Anterior</button>
                        <button type="button" id="pag-proxima" class="btn btn-secondary btn-sm">Próxima {!! icon('arrow-right') !!}</button>
                    </div>
                </div>
            </div>

        
@endsection
