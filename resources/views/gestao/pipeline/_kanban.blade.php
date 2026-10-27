{{-- Colunas do kanban do pipeline — PipelineController@listar → JSON.html. Recebe $colunas (com 'cards'). --}}
@foreach ($colunas as $key => $col)
<div class="pipeline-col pipeline-col--{{ $key }}">
    <div class="pipeline-col-header">
        <span class="pipeline-col-icon">{!! icon($col['icon']) !!}</span>
        <span class="pipeline-col-label">{{ $col['label'] }}</span>
        <span class="pipeline-col-count">{{ count($col['cards']) }}</span>
    </div>

    <div class="pipeline-col-body">
        @if (empty($col['cards']))
        <div class="empty-state pipeline-empty">
            <div class="empty-state-icon">{!! icon('clipboard-text') !!}</div>
            <p>Nenhuma cotação nesta etapa.</p>
        </div>
        @else
        @foreach ($col['cards'] as $card)
        @php
            $origem = $card['origem'] ?? 'fianca';
            $icone_tipo = match ($origem) {
                'auto'        => 'car',
                'titulo'      => 'piggy-bank',
                'condominial' => 'buildings',
                'residencial' => 'house-line',
                default       => 'house',
            };
        @endphp
        <div class="pipeline-card">
            <div class="pipeline-card-top">
                <span class="pipeline-card-protocolo">{{ $card['protocolo'] }}</span>
                <span class="pipeline-card-tipo pipeline-card-tipo--{{ $origem }}">
                    {!! icon($icone_tipo) !!}
                </span>
            </div>
            <div class="pipeline-card-nome">{{ $card['pretendente'] ?? '—' }}</div>
            <div class="pipeline-card-meta">
                <span class="pipeline-card-imob">
                    {!! icon('buildings') !!}
                    {{ $card['imob_nome'] ?? '—' }}
                </span>
                <span class="pipeline-card-data">
                    {!! icon('calendar-blank') !!}
                    {{ date_to_br($card['data_cadastro']) }}
                </span>
            </div>
            <a
                href="{{ cotacao_admin_url($card['protocolo']) }}"
                class="btn btn-secondary btn-sm btn-block pipeline-card-btn"
            >
                {!! icon('eye') !!} Ver cotação
            </a>
        </div>
        @endforeach
        @endif
    </div>
</div>
@endforeach
