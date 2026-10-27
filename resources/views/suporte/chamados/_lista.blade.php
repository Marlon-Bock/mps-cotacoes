{{-- Linhas da lista de chamados da imobiliária — ChamadosController@listar (era includes/acao/chamados-listar.php). --}}
@if (empty($rows))
<div class="admin-cot-empty">Nenhum chamado encontrado.</div>
@else
@foreach ($rows as $c)
@php
    $id         = (int) $c['id'];
    $protocolo  = chamado_protocolo($id, $c['criado_em']);
    $status     = (string) ($c['status'] ?? '');
    $atualizado = $c['atualizado_em'] ? date('d/m/Y H:i', strtotime($c['atualizado_em'])) : '—';
    $atendente  = trim($c['atendente_nome'] ?? '');
@endphp
<div class="admin-cot-row admin-cot-row-chamados">
    <div class="admin-cot-cell"><span class="proto-code">{!! $protocolo !!}</span></div>
    <div class="admin-cot-cell admin-cot-cell-nome">{{ $c['titulo'] ?? '—' }}</div>
    <div class="admin-cot-cell">{{ $c['assunto'] ?? '—' }}</div>
    <div class="admin-cot-cell"><span class="status-pill {!! chamado_status_pill($status) !!}">{!! chamado_status_label($status) !!}</span></div>
    <div class="admin-cot-cell">
        @if ($atendente !== '')
            <span class="atendente-chip">{!! avatar_sm($c['atendente_avatar'] ?? null, $atendente) !!} {{ $atendente }}</span>
        @else
            <span class="admin-cot-cell-vazio">Não atribuído</span>
        @endif
    </div>
    <div class="admin-cot-cell" style="color:var(--color-gray-500)">{!! $atualizado !!}</div>
    <div class="admin-cot-cell">
        <a href="{{ BASE_URI }}/chamado/{!! $protocolo !!}" class="btn btn-secondary btn-sm">{!! icon('eye') !!} Ver detalhes</a>
    </div>
</div>
@endforeach
@endif
