{{-- Linhas de /admin/imobiliarias (era o ob_start de includes/acao/admin-imobiliarias-listar.php). --}}
@if (empty($imobiliarias))
<div class="admin-cot-empty">Nenhuma imobiliária encontrada{{ ($busca !== '' || $status !== '') ? ' para os filtros aplicados' : '' }}.</div>
@else
@foreach ($imobiliarias as $imob)
<div class="admin-cot-row admin-cot-row-imobiliarias">
    <div class="admin-cot-cell admin-cot-cell-nome">{{ $imob['nome'] }}</div>
    <div class="admin-cot-cell">{{ $imob['cnpj'] ?: '—' }}</div>
    <div class="admin-cot-cell">{{ $imob['email'] ?: '—' }}</div>
    <div class="admin-cot-cell">{{ $imob['telefone'] ?: '—' }}</div>
    <div class="admin-cot-cell">
        @if (!empty($imob['ativo']))
            <span class="status-pill status-pill-aprovado">Ativa</span>
        @else
            <span class="status-pill status-pill-recusado">Inativa</span>
        @endif
    </div>
    <div class="admin-cot-cell">{{ $imob['criado_em'] ? date_to_br(substr($imob['criado_em'], 0, 10)) : '—' }}</div>
    <div class="admin-cot-cell admin-cot-cell-acoes">
        <div class="table-actions">
            <a href="{{ url('/admin/imobiliarias/' . $imob['slug'] . '/editar') }}" class="btn btn-secondary btn-sm" title="Editar">{!! icon('note-pencil') !!}</a>
            <a href="{{ url('/admin/imobiliarias/' . $imob['slug'] . '/usuarios') }}" class="btn btn-secondary btn-sm" title="Usuários">{!! icon('users') !!}</a>
        </div>
    </div>
</div>
@endforeach
@endif
