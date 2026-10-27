{{-- Gerenciador — /admin/gerenciador (GerenciadorController@index). Dados via /api/admin/gerenciador/*. --}}
@extends('layouts.app')

@section('content')
            <div class="admin-cot-filterbar">

                <div class="admin-cot-toolbar">
                    <!-- Status — KPI cards clicáveis (filtram os rankings abaixo) -->
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
                        <a href="#" class="status-kpi-card" data-status="em_analise">
                            <span class="status-kpi-icon">{!! icon('magnifying-glass') !!}</span>
                            <span class="status-kpi-value" id="count-em-analise">0</span>
                            <span class="status-kpi-label">Em análise</span>
                        </a>
                        <a href="#" class="status-kpi-card" data-status="aguardando">
                            <span class="status-kpi-icon">{!! icon('clock') !!}</span>
                            <span class="status-kpi-value" id="count-aguardando">0</span>
                            <span class="status-kpi-label">Aguardando análise</span>
                        </a>
                        <a href="#" class="status-kpi-card" data-status="aprovados">
                            <span class="status-kpi-icon">{!! icon('check-circle') !!}</span>
                            <span class="status-kpi-value" id="count-aprovados">0</span>
                            <span class="status-kpi-label">Aprovados</span>
                        </a>
                        <a href="#" class="status-kpi-card" data-status="reprovados">
                            <span class="status-kpi-icon">{!! icon('x') !!}</span>
                            <span class="status-kpi-value" id="count-recusados">0</span>
                            <span class="status-kpi-label">Recusados</span>
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

                <!-- Filtros -->
                <div class="cotacoes-filtros admin-filtros cotacao-container">
                    <div class="field">
                        <label class="field-label">Canal</label>
                        <div class="field-input-wrap">
                            <span class="field-input-icon">{!! icon('paper-plane-tilt') !!}</span>
                            {!! custom_select('f_canal', $opcoes_canal, 'Todos', null, 'has-icon', false, ['id' => 'f-canal-wrap']) !!}
                        </div>
                    </div>
                    <div class="field">
                        <label class="field-label">Atendente</label>
                        <div class="field-input-wrap">
                            <span class="field-input-icon">{!! icon('headset') !!}</span>
                            {!! custom_select('f_atendente', $opcoes_atendente, 'Todos', null, 'has-icon', false, ['id' => 'f-atendente-wrap']) !!}
                        </div>
                    </div>
                    <div class="field">
                        <label class="field-label">Imobiliária</label>
                        <div class="field-input-wrap">
                            <span class="field-input-icon">{!! icon('buildings') !!}</span>
                            <input type="text" id="f-imobiliaria" class="field-input has-icon" placeholder="Nome da imobiliária">
                        </div>
                    </div>
                    <div class="field">
                        <label class="field-label">Período (data de criação)</label>
                        <div class="field-date-range">
                            {!! date_picker('f-data-de', 'DD/MM/AAAA', $data_inicio_padrao) !!}
                            {!! date_picker('f-data-ate', 'DD/MM/AAAA', $data_fim_padrao) !!}
                        </div>
                    </div>
                </div>

            </div>

            <!-- KPIs por produto -->
            <div class="tk-kpis tk-kpis-7">
                <div class="tk-kpi">
                    <span class="tk-kpi-ic tk-kpi-ic-total">{!! icon('house') !!}</span>
                    <div><div class="tk-kpi-num" id="total-fianca">0</div><span class="tk-kpi-lbl" title="Seguro Fiança">Fiança</span></div>
                </div>
                <div class="tk-kpi">
                    <span class="tk-kpi-ic tk-kpi-ic-info">{!! icon('car') !!}</span>
                    <div><div class="tk-kpi-num" id="total-auto">0</div><span class="tk-kpi-lbl">Auto</span></div>
                </div>
                <div class="tk-kpi">
                    <span class="tk-kpi-ic tk-kpi-ic-warn">{!! icon('piggy-bank') !!}</span>
                    <div><div class="tk-kpi-num" id="total-titulo">0</div><span class="tk-kpi-lbl" title="Título de Capitalização">Título</span></div>
                </div>
                <div class="tk-kpi">
                    <span class="tk-kpi-ic tk-kpi-ic-ok">{!! icon('buildings') !!}</span>
                    <div><div class="tk-kpi-num" id="total-condominial">0</div><span class="tk-kpi-lbl">Condominial</span></div>
                </div>
                <div class="tk-kpi">
                    <span class="tk-kpi-ic tk-kpi-ic-atr">{!! icon('fire') !!}</span>
                    <div><div class="tk-kpi-num" id="total-incendio">0</div><span class="tk-kpi-lbl" title="Seguro Incêndio">Incêndio</span></div>
                </div>
                <div class="tk-kpi">
                    <span class="tk-kpi-ic tk-kpi-ic-info">{!! icon('house-line') !!}</span>
                    <div><div class="tk-kpi-num" id="total-residencial">0</div><span class="tk-kpi-lbl" title="Seguro Residencial">Residencial</span></div>
                </div>
                <div class="tk-kpi">
                    <span class="tk-kpi-ic tk-kpi-ic-pend">{!! icon('chart-bar') !!}</span>
                    <div><div class="tk-kpi-num" id="total-geral">0</div><span class="tk-kpi-lbl">Total Geral</span></div>
                </div>
            </div>

            <!-- Rankings -->
            <div class="dashboard-grid">

                <div class="panel">
                    <div class="panel-header">
                        <h2>{!! icon('buildings') !!} Ranking de imobiliárias</h2>
                    </div>
                    <div class="panel-body">
                        <ul class="tk-rank-list" id="ranking-imobiliarias">
                            <li class="tk-empty">Carregando...</li>
                        </ul>
                    </div>
                </div>

                <div class="panel">
                    <div class="panel-header">
                        <h2>{!! icon('headset') !!} Desempenho de atendentes</h2>
                    </div>
                    <div class="panel-body">
                        <ul class="tk-rank-list" id="ranking-atendentes">
                            <li class="tk-empty">Carregando...</li>
                        </ul>
                    </div>
                </div>

            </div>

            <!-- Tempo por cotação -->
            <div class="panel cot-panel">
                <div class="panel-header">
                    <h2>{!! icon('clock') !!} Tempo por cotação</h2>
                </div>
                <div class="admin-cot-grid-wrap">
                    <div class="admin-cot-row admin-cot-row-tempos admin-cot-head">
                        <div class="admin-cot-cell">Protocolo</div>
                        <div class="admin-cot-cell">Atendente</div>
                        <div class="admin-cot-cell">Status</div>
                        <div class="admin-cot-cell">Pré-análise</div>
                        <div class="admin-cot-cell">Aguardando</div>
                        <div class="admin-cot-cell">Em análise</div>
                        <div class="admin-cot-cell">Biometria</div>
                        <div class="admin-cot-cell">Aprovado/Recusado</div>
                        <div class="admin-cot-cell">Envio cotação</div>
                        <div class="admin-cot-cell">Concluído</div>
                        <div class="admin-cot-cell">Tempo total</div>
                        <div class="admin-cot-cell">Ações</div>
                    </div>
                    <div id="tempos-rows">
                        <div class="admin-cot-empty">Carregando...</div>
                    </div>
                </div>
                <div class="admin-cot-pagination">
                    <span id="tempos-pag-info" class="admin-cot-pagination-info"></span>
                    <div class="admin-cot-pagination-btns">
                        <button type="button" id="tempos-pag-anterior" class="btn btn-secondary btn-sm">{!! icon('arrow-left') !!} Anterior</button>
                        <button type="button" id="tempos-pag-proxima" class="btn btn-secondary btn-sm">Próxima {!! icon('arrow-right') !!}</button>
                    </div>
                </div>
            </div>

        
@endsection
