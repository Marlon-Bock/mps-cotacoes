{{-- Horário de atendimento — HorariosController@pagina ($main_attrs = data-csrf). --}}
@extends('layouts.app')

@section('content')
            <div class="alert alert-info">
                <span class="alert-icon">{!! icon('info') !!}</span>
                Cotações enviadas fora do horário ou durante um bloqueio continuam sendo recebidas normalmente — o sistema só avisa a imobiliária que a equipe vai responder no próximo horário útil.
            </div>

            <div class="panel">
                <div class="panel-header">
                    <h2>Horário de atendimento por produto</h2>
                </div>
                <div class="admin-cot-grid-wrap">
                    <div class="admin-cot-row admin-cot-row-horarios admin-cot-head">
                        <div class="admin-cot-cell">Produto</div>
                        <div class="admin-cot-cell">Início</div>
                        <div class="admin-cot-cell">Fim</div>
                        <div class="admin-cot-cell">Dias</div>
                        <div class="admin-cot-cell">Ativo</div>
                        <div class="admin-cot-cell">Ações</div>
                    </div>
                    @foreach ($tipos_seguro as $tipo => $label)
                    @php $h = $horarios[$tipo] ?? null; @endphp
                    <div class="admin-cot-row admin-cot-row-horarios">
                        <div class="admin-cot-cell admin-cot-cell-nome">{{ $label }}</div>
                        <div class="admin-cot-cell">{!! $h ? e(substr($h['hora_inicio'], 0, 5)) : '—' !!}</div>
                        <div class="admin-cot-cell">{!! $h ? e(substr($h['hora_fim'], 0, 5)) : '—' !!}</div>
                        <div class="admin-cot-cell">{!! $h ? e($horarioFormatarDias($h['dias_semana'], $dias_semana_labels)) : '—' !!}</div>
                        <div class="admin-cot-cell">
                            @if ($h && $h['ativo'] === 'S')
                                <span class="status-pill status-pill-aprovado">Sim</span>
                            @elseif ($h)
                                <span class="status-pill status-pill-recusado">Não</span>
                            @else
                                <span class="admin-cot-cell-vazio">Não configurado</span>
                            @endif
                        </div>
                        <div class="admin-cot-cell">
                            <button type="button" class="btn btn-secondary btn-sm" data-action="editar-horario"
                                data-tipo="{{ $tipo }}"
                                data-label="{{ $label }}"
                                data-inicio="{{ $h['hora_inicio'] ?? '08:30' }}"
                                data-fim="{{ $h['hora_fim'] ?? '18:00' }}"
                                data-dias="{{ $h['dias_semana'] ?? '1,2,3,4,5' }}"
                                data-ativo="{{ $h['ativo'] ?? 'S' }}">
                                {!! icon('note-pencil') !!} Editar
                            </button>
                        </div>
                    </div>
                    @endforeach
                </div>
            </div>

            <div class="panel">
                <div class="panel-header">
                    <h2>Bloqueios temporários</h2>
                    <button type="button" class="btn btn-primary btn-sm" id="btn-novo-bloqueio">
                        {!! icon('plus') !!} Novo bloqueio
                    </button>
                </div>
                <div class="panel-body">
                    @if (!$bloqueios)
                        <div class="empty-state">
                            <div class="empty-state-icon">{!! icon('clock') !!}</div>
                            <p>Nenhum bloqueio cadastrado. Use "Novo bloqueio" pra avisar a imobiliária quando a equipe estiver indisponível (ex: reunião, feriado).</p>
                        </div>
                    @else
                    @foreach ($bloqueios as $b)
                    @php
                        $produtos = array_filter(array_map('trim', explode(',', $b['tipo_seguro'])));
                        $inicio   = date('d/m/Y H:i', strtotime($b['data_inicio']));
                        $fim      = date('d/m/Y H:i', strtotime($b['data_fim']));
                    @endphp
                    <div class="panel-list-item">
                        <div class="panel-list-item-info">
                            <strong>{{ $b['titulo'] }}</strong>
                            <span>{{ $inicio }} até {{ $fim }} — {{ implode(', ', array_map(fn($p) => $tipos_seguro[$p] ?? $p, $produtos)) }}</span>
                        </div>
                        <div class="horario-bloqueio-acoes">
                            @if ($b['ativo'] === 'S')
                                <span class="status-pill status-pill-aprovado">Ativo</span>
                            @else
                                <span class="status-pill status-pill-recusado">Inativo</span>
                            @endif
                            <button type="button" class="btn btn-secondary btn-sm" data-action="editar-bloqueio"
                                data-id="{!! (int) $b['id'] !!}"
                                data-titulo="{{ $b['titulo'] }}"
                                data-mensagem="{{ $b['mensagem'] }}"
                                data-data-inicio="{{ date('Y-m-d', strtotime($b['data_inicio'])) }}"
                                data-hora-inicio="{{ date('H:i', strtotime($b['data_inicio'])) }}"
                                data-data-fim="{{ date('Y-m-d', strtotime($b['data_fim'])) }}"
                                data-hora-fim="{{ date('H:i', strtotime($b['data_fim'])) }}"
                                data-produtos="{{ $b['tipo_seguro'] }}"
                                data-ativo="{{ $b['ativo'] }}">
                                {!! icon('note-pencil') !!}
                            </button>
                            <button type="button" class="btn btn-danger-outline btn-sm" data-action="excluir-bloqueio" data-id="{!! (int) $b['id'] !!}">
                                {!! icon('x') !!}
                            </button>
                        </div>
                    </div>
                    @endforeach
                    @endif
                </div>
            </div>

        
@endsection

@push('antes-scripts')
<!-- Modal: horário de atendimento -->
<div class="modal" id="modal-horario" hidden>
    <div class="modal-backdrop" data-close="modal-horario"></div>
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 id="modal-horario-titulo">Horário de atendimento</h3>
            <button type="button" class="modal-close" data-close="modal-horario">{!! icon('x') !!}</button>
        </div>
        <form id="form-horario" class="modal-body">
            <input type="hidden" id="horario-tipo-seguro" value="">
            <div class="field-row">
                <div class="field">
                    <label class="field-label">Início do atendimento</label>
                    {!! time_picker('horario-hora-inicio', '--:--', '08:30') !!}
                </div>
                <div class="field">
                    <label class="field-label">Fim do atendimento</label>
                    {!! time_picker('horario-hora-fim', '--:--', '18:00') !!}
                </div>
            </div>
            <div class="field">
                <label class="field-label">Dias da semana</label>
                <div class="switch-group">
                    @foreach ($dias_semana_labels as $num => $label)
                    <label class="switch">
                        <input type="checkbox" name="dias_semana[]" value="{!! $num !!}">
                        <span class="switch-track"></span>
                        <span class="switch-label">{{ $label }}</span>
                    </label>
                    @endforeach
                </div>
            </div>
            <label class="switch">
                <input type="checkbox" name="ativo" value="1" checked>
                <span class="switch-track"></span>
                <span class="switch-label">Horário ativo</span>
            </label>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close="modal-horario">Cancelar</button>
                <button type="submit" class="btn btn-primary">{!! icon('check') !!} Salvar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: bloqueio temporário -->
<div class="modal" id="modal-bloqueio" hidden>
    <div class="modal-backdrop" data-close="modal-bloqueio"></div>
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 id="modal-bloqueio-titulo">Novo bloqueio</h3>
            <button type="button" class="modal-close" data-close="modal-bloqueio">{!! icon('x') !!}</button>
        </div>
        <form id="form-bloqueio" class="modal-body">
            <input type="hidden" name="id" value="">
            <div class="field">
                <label class="field-label">Título</label>
                <div class="field-input-wrap">
                    <span class="field-input-icon">{!! icon('note-pencil') !!}</span>
                    <input class="field-input has-icon" type="text" name="titulo" placeholder="Ex: Equipe em reunião" required>
                </div>
            </div>
            <div class="field">
                <label class="field-label">Mensagem exibida pra imobiliária</label>
                <textarea class="field-input" name="mensagem" rows="3" placeholder="Ex: A equipe de Seguro Fiança está em reunião." required></textarea>
            </div>
            <div class="field-row">
                <div class="field">
                    <label class="field-label">Data de início</label>
                    {!! date_picker('bloqueio-data-inicio') !!}
                </div>
                <div class="field">
                    <label class="field-label">Hora de início</label>
                    {!! time_picker('bloqueio-hora-inicio') !!}
                </div>
            </div>
            <div class="field-row">
                <div class="field">
                    <label class="field-label">Data de fim</label>
                    {!! date_picker('bloqueio-data-fim') !!}
                </div>
                <div class="field">
                    <label class="field-label">Hora de fim</label>
                    {!! time_picker('bloqueio-hora-fim') !!}
                </div>
            </div>
            <div class="field">
                <label class="field-label">Produtos afetados</label>
                <div class="switch-group">
                    @foreach ($tipos_seguro as $tipo => $label)
                    @continue($tipo === 'todos')
                    <label class="switch">
                        <input type="checkbox" name="tipo_seguro[]" value="{{ $tipo }}">
                        <span class="switch-track"></span>
                        <span class="switch-label">{{ $label }}</span>
                    </label>
                    @endforeach
                </div>
            </div>
            <label class="switch">
                <input type="checkbox" name="ativo" value="1" checked>
                <span class="switch-track"></span>
                <span class="switch-label">Bloqueio ativo</span>
            </label>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close="modal-bloqueio">Cancelar</button>
                <button type="submit" class="btn btn-primary">{!! icon('check') !!} Salvar</button>
            </div>
        </form>
    </div>
</div>
@endpush
