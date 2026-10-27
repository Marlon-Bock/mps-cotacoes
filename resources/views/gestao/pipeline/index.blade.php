{{-- Pipeline (kanban) — /admin/pipeline (PipelineController@index). Colunas via /api/admin/pipeline/listar. --}}
@extends('layouts.app')

@section('content')
            <!-- ── Barra de filtros — mesmo padrão visual da listagem de
                 cotações (AJAX, nunca navega) ─────────────────────────── -->
            <div class="cotacoes-filtros admin-filtros cotacao-container">
                <div class="field">
                    <label class="field-label">Imobiliária</label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon">{!! icon('buildings') !!}</span>
                        {!! custom_select(
                            'imobiliaria_id',
                            $opcoes_imob,
                            'Todas',
                            null,
                            'has-icon'
                        ) !!}
                    </div>
                </div>

                <div class="field">
                    <label class="field-label">Atendente</label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon">{!! icon('headset') !!}</span>
                        {!! custom_select(
                            'atendente_id',
                            $opcoes_atend,
                            'Todos',
                            null,
                            'has-icon'
                        ) !!}
                    </div>
                </div>

                <div class="field">
                    <label class="field-label">Data de cadastro (de / até)</label>
                    <div class="field-date-range">
                        {!! date_picker('pipeline-data-inicio', 'DD/MM/AAAA', $data_inicio_padrao) !!}
                        {!! date_picker('pipeline-data-fim', 'DD/MM/AAAA', $data_fim_padrao) !!}
                    </div>
                </div>

                <div class="pipeline-filterbar-actions">
                    <button type="button" id="btn-limpar-pipeline" class="btn btn-secondary btn-sm" hidden>
                        Limpar filtros
                    </button>
                </div>
            </div>

            <!-- ── Kanban (preenchido via AJAX) ────────────────────────────── -->
            <div class="pipeline-kanban" id="pipeline-kanban">
                @foreach ($colunas as $key => $col)
                <div class="pipeline-col pipeline-col--{{ $key }}">
                    <div class="pipeline-col-header">
                        <span class="pipeline-col-icon">{!! icon($col['icon']) !!}</span>
                        <span class="pipeline-col-label">{{ $col['label'] }}</span>
                        <span class="pipeline-col-count">0</span>
                    </div>
                    <div class="pipeline-col-body">
                        <div class="empty-state pipeline-empty">
                            <p>Carregando...</p>
                        </div>
                    </div>
                </div>
                @endforeach
            </div>

        
@endsection
