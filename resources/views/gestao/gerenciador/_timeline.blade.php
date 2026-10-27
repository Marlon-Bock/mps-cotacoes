{{-- Linha do tempo de uma cotação — GerenciadorController@timeline → JSON.html. Recebe $historico, $tempo_total. --}}
@if (empty($historico))
<div class="admin-cot-empty">Nenhum evento encontrado para esta cotação.</div>
@else
<div class="status-timeline">
    @foreach ($historico as $i => $h)
    <div class="timeline-item is-done">
        <div class="timeline-dot"></div>
        <div class="timeline-content">
            <span class="timeline-label">
                @if (!empty($h['status_anterior']) && !empty($h['status_novo']) && $h['status_anterior'] !== $h['status_novo'])
                    <strong>{{ $h['status_anterior'] }}</strong> &rarr; <strong>{{ $h['status_novo'] }}</strong>
                @elseif (!empty($h['status_novo']))
                    <strong>{{ $h['status_novo'] }}</strong>
                @else
                    <strong>{{ $h['tipo_evento'] }}</strong>
                @endif
            </span>
            @php $delta = $i > 0 ? strtotime($h['data_evento']) - strtotime($historico[$i - 1]['data_evento']) : 0; @endphp
            <span class="timeline-meta">
                {{ $h['usuario_nome'] ?? '—' }} · {{ date('d/m/Y H:i', strtotime($h['data_evento'])) }}{{ $delta > 0 ? ' · ' . formatar_duracao($delta) . ' depois' : '' }}
            </span>
            @if (!empty($h['observacao']))
            <span class="timeline-obs">{{ $h['observacao'] }}</span>
            @endif
        </div>
    </div>
    @endforeach
</div>
@if ($tempo_total > 0)
<p class="field-hint timeline-tempo-total">Tempo total entre o primeiro e o último evento: <strong>{{ formatar_duracao($tempo_total) }}</strong></p>
@endif
@endif
