{{-- HTML dos painéis de funis — RodizioController@listar (JSON { html }). --}}
@if (empty($funis))
<div class="panel">
    <div class="panel-body">
        <div class="empty-state">
            <div class="empty-state-icon">{!! icon('arrows-clockwise') !!}</div>
            <p>Nenhum funil de rodízio cadastrado.</p>
        </div>
    </div>
</div>
@endif

@foreach ($funis as $funil)
@php
    $corretores         = $funil['corretores'] ?? [];
    $opcoes_disponiveis = $funil['_opcoes_disponiveis'];
@endphp
<section class="panel cot-panel rodizio-card">
    <div class="panel-header">
        <h2>
            {!! icon($funil['_icone']) !!}
            {{ $funil['nome'] }}
            @if ($funil['ativo'] === 'S')
                <span class="status-pill status-pill-aprovado">Ativo</span>
            @else
                <span class="status-pill status-pill-recusado">Inativo</span>
            @endif
        </h2>
    </div>

    @if (empty($corretores))
        <div class="panel-body">
            <div class="empty-state">
                <div class="empty-state-icon">{!! icon('users') !!}</div>
                <p>Nenhum atendente cadastrado neste funil.</p>
            </div>
        </div>
    @else
        <div class="admin-cot-grid-wrap">
            <div class="admin-cot-row admin-cot-row-rodizio admin-cot-head">
                <div class="admin-cot-cell">Ordem</div>
                <div class="admin-cot-cell admin-cot-cell-nome">Atendente</div>
                <div class="admin-cot-cell">Status</div>
                <div class="admin-cot-cell">Próximo</div>
                <div class="admin-cot-cell">Ações</div>
            </div>
            <div class="rodizio-row-list" data-funil-id="{{ (int) $funil['id'] }}">
                @foreach ($corretores as $corretor)
                <div class="admin-cot-row admin-cot-row-rodizio rodizio-row" draggable="true" data-id="{{ (int) $corretor['id'] }}">
                    <div class="admin-cot-cell admin-cot-cell-ordem">
                        <span class="rodizio-row-handle" title="Arraste para reordenar">{!! icon('list') !!}</span>
                        {{ (int) $corretor['ordem'] }}
                    </div>
                    <div class="admin-cot-cell admin-cot-cell-nome">
                        <span class="atendente-chip">
                            {!! avatar_sm($corretor['avatar'] ?? null, $corretor['username']) !!}
                            <span class="rodizio-atendente-info">
                                <strong class="rodizio-atendente-nome">{{ $corretor['username'] }}</strong>
                                <span class="rodizio-atendente-email">{{ $corretor['email'] }}</span>
                            </span>
                        </span>
                    </div>
                    <div class="admin-cot-cell">
                        <label class="switch rodizio-row-switch">
                            <input type="checkbox" class="rodizio-status-toggle" data-id="{{ (int) $corretor['id'] }}" {{ $corretor['_eh_ativo'] ? 'checked' : '' }}>
                            <span class="switch-track"></span>
                            <span class="switch-label">{{ $corretor['_eh_ativo'] ? 'Ativo' : 'Inativo' }}</span>
                        </label>
                    </div>
                    <div class="admin-cot-cell">
                        @if ($corretor['_eh_proximo'])
                            <span class="status-pill status-pill-aprovado">Próximo</span>
                        @else
                            <span class="admin-cot-cell-vazio">—</span>
                        @endif
                    </div>
                    <div class="admin-cot-cell">
                        <button type="button" class="btn btn-danger-outline btn-sm" data-action="rodizio-remover" data-id="{{ (int) $corretor['id'] }}" title="Remover do funil">
                            {!! icon('x') !!}
                        </button>
                    </div>
                </div>
                @endforeach
            </div>
        </div>
    @endif

    <div class="rodizio-add-row" data-funil-id="{{ (int) $funil['id'] }}">
        <div class="field rodizio-add-select">
            {!! custom_select(
                "add_atendente_{$funil['id']}",
                $opcoes_disponiveis,
                $opcoes_disponiveis ? 'Selecionar atendente...' : 'Todos já estão neste funil',
                null,
                '',
                false,
                ['data-rodizio-add-select' => '1']
            ) !!}
        </div>
        <button type="button" class="btn btn-primary btn-sm" data-action="rodizio-adicionar" data-funil-id="{{ (int) $funil['id'] }}" title="Adicionar atendente" {{ $opcoes_disponiveis ? '' : 'disabled' }}>
            {!! icon('plus') !!} Adicionar
        </button>
    </div>
</section>
@endforeach
