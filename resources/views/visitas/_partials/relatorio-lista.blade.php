{{-- HTML das "Visitas de hoje" — VisitasController@relatorioListar (JSON { html, total }). --}}
@if (empty($itens))
<div class="panel-body">
    <div class="empty-state">
        <div class="empty-state-icon">{!! icon('clipboard-text') !!}</div>
        <p>Nenhuma visita registrada hoje ainda.</p>
    </div>
</div>
@else
<div class="visita-hoje-list">
    @foreach ($itens as $item)
    @php
        $seguros_marcados = [];
        foreach ($seguros_labels as $col => $label) {
            if (!empty($item[$col])) $seguros_marcados[] = $label;
        }
        $itemParaJson = $item;
        $itemParaJson['corretores_extra'] = $extras_map[(int) $item['id']] ?? [];
        $itemJson = json_encode($itemParaJson, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT);
    @endphp
    <div class="visita-hoje-card" data-item="{{ $itemJson }}">
        <div class="visita-hoje-card-top">
            <strong>{{ $item['imobiliaria_nome'] ?: '—' }}</strong>
            <span class="visita-hoje-card-horario">
                {!! icon('clock') !!}
                {{ substr($item['horario_entrada'] ?? '', 0, 5) }} – {{ substr($item['horario_saida'] ?? '', 0, 5) }}
            </span>
        </div>
        <div class="visita-hoje-card-meta">
            <span>{{ $item['motivo_visita'] ?: '—' }}</span>
            @if ($seguros_marcados)
                <span class="visita-hoje-card-seguros">{{ implode(', ', $seguros_marcados) }}</span>
            @endif
            @if (!empty($item['falta_material']))
                <span class="status-pill status-pill-recusado">Falta material</span>
            @endif
        </div>
        <div class="visita-hoje-card-acoes">
            <button type="button" class="btn btn-secondary btn-sm" data-action="visita-editar">
                {!! icon('note-pencil') !!} Editar
            </button>
            <button type="button" class="btn btn-danger-outline btn-sm" data-action="visita-excluir" data-id="{{ (int) $item['id'] }}">
                {!! icon('x') !!}
            </button>
        </div>
    </div>
    @endforeach
</div>
@endif
