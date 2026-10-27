{{-- Cards/linhas das campanhas da imobiliária — CampanhasController@listar (era includes/acao/campanhas-listar.php). --}}
@if (empty($campanhas))
<div class="admin-cot-empty">Nenhuma campanha encontrada{{ ($busca !== '' || $statusRapido !== '' || $data_de !== '' || $data_ate !== '') ? ' para os filtros aplicados' : '' }}.</div>
@else
@foreach ($campanhas as $campanha)
@php
    $agora  = time();
    $inicio = strtotime((string) $campanha['data_inicio']);
    $fim    = strtotime((string) $campanha['data_fim']);

    if ($fim < $agora) {
        $badge = 'Encerrada em ' . date_to_br(substr((string) $campanha['data_fim'], 0, 10));
        $statusLabel = 'Encerrada';
        $statusPill  = 'status-pill-recusado';
    } elseif ($inicio > $agora) {
        $badge = 'Começa em ' . date_to_br(substr((string) $campanha['data_inicio'], 0, 10));
        $statusLabel = 'Futura';
        $statusPill  = 'status-pill-pendente';
    } else {
        $dias_restantes = max(0, (int) ceil(($fim - $agora) / 86400));
        $badge = $dias_restantes > 0 ? "Termina em {$dias_restantes} dia" . ($dias_restantes > 1 ? 's' : '') : 'Encerra hoje';
        $statusLabel = 'Ativa';
        $statusPill  = 'status-pill-aprovado';
    }
@endphp
@if ($view === 'list')
<div class="admin-cot-row admin-cot-row-campanhas admin-cot-row-campanhas-imob admin-cot-row-clickable" data-abrir-campanha data-imagem="{{ $campanha['imagem'] ?? '' }}" data-titulo="{{ $campanha['titulo'] }}" data-observacoes="{{ $campanha['observacoes'] ?? '' }}">
    <div class="admin-cot-cell admin-cot-cell-nome">{{ $campanha['titulo'] }}</div>
    <div class="admin-cot-cell"><span class="status-pill {!! $statusPill !!}">{{ $statusLabel }}</span></div>
    <div class="admin-cot-cell">{!! date_to_br(substr((string) $campanha['data_inicio'], 0, 10)) !!} &rarr; {!! date_to_br(substr((string) $campanha['data_fim'], 0, 10)) !!}</div>
</div>
@else
<button type="button" class="campaign-card campaign-card-clickable" data-abrir-campanha data-imagem="{{ $campanha['imagem'] ?? '' }}" data-titulo="{{ $campanha['titulo'] }}" data-observacoes="{{ $campanha['observacoes'] ?? '' }}">
    @if (!empty($campanha['imagem']))
    <img src="{{ $campanha['imagem'] }}" alt="" loading="lazy">
    @endif
    <div class="campaign-card-overlay">
        <strong>{{ $campanha['titulo'] }}</strong>
        <span class="campaign-card-badge">{{ $badge }}</span>
    </div>
</button>
@endif
@endforeach
@endif
