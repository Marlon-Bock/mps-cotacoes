{{-- Cards/linhas das campanhas na gestão — CampanhasController@adminListar (era includes/acao/admin-campanhas-listar.php). --}}
@if (empty($campanhas))
<div class="admin-cot-empty">Nenhuma campanha encontrada{{ ($busca !== '' || $status !== '' || $data_de !== '' || $data_ate !== '') ? ' para os filtros aplicados' : '' }}.</div>
@elseif ($view === 'grid')
@foreach ($campanhas as $c)
<div class="campaign-card" data-abrir-campanha data-imagem="{{ $c['imagem'] ?? '' }}" data-titulo="{{ $c['titulo'] }}" data-observacoes="{{ $c['observacoes'] ?? '' }}">
    @if (!empty($c['imagem']))
    <img src="{{ $c['imagem'] }}" alt="" loading="lazy">
    @endif
    <div class="campaign-card-overlay">
        <strong>{{ $c['titulo'] }}</strong>
        <span class="campaign-card-badge">
            {{ $c['status'] === 'ativo' ? 'Ativa' : 'Inativa' }} &middot; {{ $c['total_imobiliarias'] > 0 ? (int) $c['total_imobiliarias'] . ' imobiliária(s)' : 'Todas as imobiliárias' }} &middot; {!! date_to_br(substr((string) $c['data_inicio'], 0, 10)) !!} &rarr; {!! date_to_br(substr((string) $c['data_fim'], 0, 10)) !!}
        </span>
    </div>
    <div class="campaign-card-actions">
        <a href="{{ BASE_URI }}/admin/campanhas/{{ (int) $c['id'] }}/editar" class="btn btn-secondary btn-sm">{!! icon('note-pencil') !!} <span class="btn-label">Editar</span></a>
        <button type="button" class="btn btn-danger-outline btn-sm" data-action="excluir-campanha" data-id="{{ (int) $c['id'] }}">{!! icon('x') !!} <span class="btn-label">Excluir</span></button>
    </div>
</div>
@endforeach
@else
@foreach ($campanhas as $c)
<div class="admin-cot-row admin-cot-row-campanhas admin-cot-row-clickable" data-abrir-campanha data-imagem="{{ $c['imagem'] ?? '' }}" data-titulo="{{ $c['titulo'] }}" data-observacoes="{{ $c['observacoes'] ?? '' }}">
    <div class="admin-cot-cell admin-cot-cell-nome">{{ $c['titulo'] }}</div>
    <div class="admin-cot-cell">
        @if ($c['status'] === 'ativo')
            <span class="status-pill status-pill-aprovado">Ativa</span>
        @else
            <span class="status-pill status-pill-recusado">Inativa</span>
        @endif
    </div>
    <div class="admin-cot-cell">{{ $c['total_imobiliarias'] > 0 ? (int) $c['total_imobiliarias'] : 'Todas' }}</div>
    <div class="admin-cot-cell">
        {!! date_to_br(substr((string) $c['data_inicio'], 0, 10)) !!} &rarr; {!! date_to_br(substr((string) $c['data_fim'], 0, 10)) !!}
    </div>
    <div class="admin-cot-cell admin-cot-cell-acoes">
        <div class="table-actions">
            <a href="{{ BASE_URI }}/admin/campanhas/{{ (int) $c['id'] }}/editar" class="btn btn-secondary btn-sm">{!! icon('note-pencil') !!} Editar</a>
            <button type="button" class="btn btn-danger-outline btn-sm" data-action="excluir-campanha" data-id="{{ (int) $c['id'] }}">{!! icon('x') !!} Excluir</button>
        </div>
    </div>
</div>
@endforeach
@endif
