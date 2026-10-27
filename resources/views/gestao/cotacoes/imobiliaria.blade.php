{{-- Minhas Cotações (imobiliária) — /cotacoes (CotacoesController@imobiliaria). Linhas via /api/cotacoes/listar. --}}
@extends('layouts.app')

@section('content')
            <div class="admin-cot-filterbar">

                <!-- Status — KPI cards clicáveis -->
                <div class="admin-cot-toolbar">
                    <div class="status-kpi-grid">
                        <a href="#" class="status-kpi-card is-active" data-status="">
                            <span class="status-kpi-icon">{!! icon('list') !!}</span>
                            <span class="status-kpi-value" id="count-todos">0</span>
                            <span class="status-kpi-label">Todos</span>
                        </a>
                        <a href="#" class="status-kpi-card" data-status="pre_analise">
                            <span class="status-kpi-icon">{!! icon('lock') !!}</span>
                            <span class="status-kpi-value" id="count-pre-analise">0</span>
                            <span class="status-kpi-label">Pré-análise</span>
                        </a>
                        <a href="#" class="status-kpi-card" data-status="Aguardando an">
                            <span class="status-kpi-icon">{!! icon('clock') !!}</span>
                            <span class="status-kpi-value" id="count-aguardando">0</span>
                            <span class="status-kpi-label">Aguardando análise</span>
                        </a>
                        <a href="#" class="status-kpi-card" data-status="Em an">
                            <span class="status-kpi-icon">{!! icon('magnifying-glass') !!}</span>
                            <span class="status-kpi-value" id="count-em-analise">0</span>
                            <span class="status-kpi-label">Em análise</span>
                        </a>
                        <a href="#" class="status-kpi-card" data-status="Aprovad">
                            <span class="status-kpi-icon">{!! icon('check-circle') !!}</span>
                            <span class="status-kpi-value" id="count-aprovados">0</span>
                            <span class="status-kpi-label">Aprovados</span>
                        </a>
                        <a href="#" class="status-kpi-card" data-status="Recusad">
                            <span class="status-kpi-icon">{!! icon('x') !!}</span>
                            <span class="status-kpi-value" id="count-recusados">0</span>
                            <span class="status-kpi-label">Recusados</span>
                        </a>
                        <a href="#" class="status-kpi-card" data-status="Desist">
                            <span class="status-kpi-icon">{!! icon('sign-out') !!}</span>
                            <span class="status-kpi-value" id="count-desistencias">0</span>
                            <span class="status-kpi-label">Desistências</span>
                        </a>
                        <a href="#" class="status-kpi-card" data-status="concluidos">
                            <span class="status-kpi-icon">{!! icon('check') !!}</span>
                            <span class="status-kpi-value" id="count-concluidos">0</span>
                            <span class="status-kpi-label">Concluídas</span>
                        </a>
                    </div>

                    <div class="admin-cot-toolbar-actions">
                        <button type="button" id="btn-limpar" class="btn btn-secondary btn-sm" style="display:none">
                            Limpar filtros
                        </button>
                    </div>
                </div>

            <!-- Filtros — usa cotacao-container para herdar os estilos de campo do sistema -->
            <div class="cotacoes-filtros admin-filtros cotacao-container">
                <div class="field">
                    <label class="field-label">Pretendente</label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon">{!! icon('user') !!}</span>
                        <input type="text" id="f-pretendente" class="field-input has-icon" placeholder="Nome do pretendente">
                    </div>
                </div>
                <div class="field">
                    <label class="field-label">Protocolo</label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon">{!! icon('magnifying-glass') !!}</span>
                        <input type="text" id="f-protocolo" class="field-input has-icon" placeholder="Protocolo">
                    </div>
                </div>
                <div class="field">
                    <label class="field-label">Produto</label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon">{!! icon('clipboard-text') !!}</span>
                        {!! custom_select('f_tipo', [
                            'fianca'      => 'Fiança',
                            'auto'        => 'Seguro Auto',
                            'titulo'      => 'Título de Capitalização',
                            'condominial' => 'Seguro Condomínio',
                            'incendio'    => 'Seguro Incêndio',
                            'residencial' => 'Seguro Residencial',
                        ], 'Todos', null, 'has-icon', false, ['id' => 'f-tipo-wrap']) !!}
                    </div>
                </div>
                <div class="field">
                    <label class="field-label">Data atualização (de / até)</label>
                    <div class="field-date-range">
                        {!! date_picker('f-data-de') !!}
                        {!! date_picker('f-data-ate') !!}
                    </div>
                </div>
            </div>
            </div>

            <!-- Lista de cotações (grid de divs — mesma apresentação do admin) -->
            <div class="panel cot-panel">
                <div class="admin-cot-grid-wrap">
                    <div class="admin-cot-row admin-cot-row-imob admin-cot-head">
                        <div class="admin-cot-cell">
                            <span class="th-produto-so">Produto</span>
                            <span class="th-produto-protocolo">Produto/Protocolo</span>
                        </div>
                        <div class="admin-cot-cell">Protocolo</div>
                        <div class="admin-cot-cell admin-cot-cell-nome">Pretendente / Segurado</div>
                        <div class="admin-cot-cell">Status</div>
                        <div class="admin-cot-cell">Atendente</div>
                        <div class="admin-cot-cell">Datas</div>
                        <div class="admin-cot-cell">Ações</div>
                    </div>
                    <div id="cotacoes-lista">
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
