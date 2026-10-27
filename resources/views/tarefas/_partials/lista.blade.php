{{-- HTML da lista agrupada (estilo Monday) devolvido em JSON por TarefasController@listar. --}}
@if (empty($ordem))
<div class="admin-cot-empty">Nenhuma tarefa encontrada{{ $temFiltro ? ' para os filtros aplicados' : '' }}.</div>
@else
@foreach ($ordem as $g)
@php
    $rows       = $por_grupo[$g['id']] ?? [];
    $add_setor  = $agrupar_por_status ? (int) $f_setor : (int) $g['id'];
    $add_status = $agrupar_por_status ? (int) $g['id'] : 0;
@endphp
<div class="mnd-group" data-grupo="{{ (int) $g['id'] }}" data-cor="{{ $g['cor'] }}">
    <div class="mnd-group-head">
        <button type="button" class="mnd-collapse" aria-label="Recolher/expandir">{!! icon('caret-down') !!}</button>
        <span class="mnd-group-dot" data-cor="{{ $g['cor'] }}"></span>
        <span class="mnd-group-name">{{ $g['nome'] }}</span>
        <span class="mnd-group-count">{{ count($rows) }} {{ count($rows) === 1 ? 'tarefa' : 'tarefas' }}</span>
    </div>
    <div class="mnd-group-body">
        <div class="mnd-table">
            <div class="mnd-row mnd-head">
                <div class="mnd-cell mnd-cell-drag"></div>
                <div class="mnd-cell mnd-cell-nome">Tarefa</div>
                <div class="mnd-cell"></div>
                <div class="mnd-cell">Responsável</div>
                <div class="mnd-cell th-sortable" data-sort="status">Status{!! $sort_by === 'status' ? '<span class="sort-arrow">' . ($sort_dir === 'asc' ? '▲' : '▼') . '</span>' : '' !!}</div>
                <div class="mnd-cell th-sortable" data-sort="prazo">Prazo{!! $sort_by === 'prazo' ? '<span class="sort-arrow">' . ($sort_dir === 'asc' ? '▲' : '▼') . '</span>' : '' !!}</div>
                <div class="mnd-cell">Arquivos</div>
                <div class="mnd-cell">Checklist</div>
                <div class="mnd-cell mnd-cell-acoes"></div>
            </div>
            @if ($rows)
            @foreach ($rows as $t)
            @include('tarefas._partials.lista-linha', ['t' => $t])
            @endforeach
            @else
            <div class="mnd-vazio-bar">Nenhuma tarefa no momento</div>
            @endif
            <div class="mnd-row mnd-add">
                <button type="button" class="mnd-add-btn" data-action="add-tarefa" data-setor="{{ $add_setor }}" data-status="{{ $add_status }}">{!! icon('plus') !!} Adicionar tarefa</button>
            </div>
        </div>
    </div>
</div>
@endforeach
@endif
