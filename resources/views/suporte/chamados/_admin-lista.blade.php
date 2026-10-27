{{-- Linhas da lista de chamados da MPS — AdminChamadosController@listar (era includes/acao/admin-chamados-listar.php). --}}
@if (empty($chamados))
<div class="admin-cot-empty">Nenhum chamado encontrado{{ ($status_rapido !== '' || $imobiliaria_id > 0 || $atendente_id > 0 || $busca !== '') ? ' para os filtros aplicados' : '' }}.</div>
@else
@foreach ($chamados as $c)
@php
    $id         = (int) $c['id'];
    $protocolo  = chamado_protocolo($id, $c['criado_em']);
    $status     = (string) ($c['status'] ?? '');
    $atendente  = trim($c['atendente_nome'] ?? '');
    $atualizado = $c['atualizado_em'] ? date('d/m/Y H:i', strtotime($c['atualizado_em'])) : '—';
@endphp
<div class="admin-cot-row admin-cot-row-chamados-admin">
    <div class="admin-cot-cell"><span class="proto-code">{{ $protocolo }}</span></div>
    <div class="admin-cot-cell admin-cot-cell-nome">{{ $c['titulo'] ?? $c['assunto'] ?? '—' }}</div>
    <div class="admin-cot-cell">{{ $c['imobiliaria_nome'] ?? '—' }}</div>
    <div class="admin-cot-cell">
        @if ($atendente !== '')
            <span class="atendente-chip">{!! avatar_sm($c['atendente_avatar'] ?? null, $atendente) !!} {{ $atendente }}</span>
        @else
            <span class="admin-cot-cell-vazio">Não atribuído</span>
        @endif
    </div>
    <div class="admin-cot-cell"><span class="status-pill {!! chamado_status_pill($status) !!}">{!! chamado_status_label($status) !!}</span></div>
    <div class="admin-cot-cell">{{ $atualizado }}</div>
    <div class="admin-cot-cell admin-cot-cell-acoes">
        <div class="table-actions">
            <a href="{{ BASE_URI }}/admin/chamado/{{ $protocolo }}" class="btn btn-secondary btn-sm" title="Ver detalhes">{!! icon('eye') !!}</a>
        </div>
    </div>
</div>
@endforeach
@endif
