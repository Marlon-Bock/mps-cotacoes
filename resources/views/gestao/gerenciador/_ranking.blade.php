{{-- Ranking de imobiliárias ($atendentes=false) ou de atendentes ($atendentes=true) —
     GerenciadorController@stats → JSON.ranking_*_html. Recebe $itens, $total, $atendentes. --}}
@php $max = $itens[0]['total'] ?? 0; @endphp
@if (empty($itens))
    <li class="tk-empty">Nenhum dado no período selecionado.</li>
@else
    @foreach ($itens as $pos => $item)
    @php
        $percentual = $total > 0 ? ($item['total'] / $total) * 100 : 0;
        $largura    = $max > 0 ? ($item['total'] / $max) * 100 : 0;
    @endphp
    <li class="tk-rank-item">
        <span class="tk-rank-pos">{{ $pos + 1 }}</span>
        <div class="tk-rank-body">
            <div class="tk-rank-top">
                @if ($atendentes)
                <span class="tk-rank-nome tk-rank-nome-av">{!! avatar_sm($item['avatar'] ?? null, $item['nome']) !!}{{ $item['nome'] }}</span>
                @else
                <span class="tk-rank-nome">{{ $item['nome'] }}</span>
                @endif
                <span class="tk-rank-val">{{ number_format($item['total'], 0, ',', '.') }} · {{ number_format($percentual, 1, ',', '.') }}%</span>
            </div>
            <div class="tk-dash-bar"><span class="tk-dash-fill" data-w="{{ round($largura, 1) }}"></span></div>
            @if ($atendentes)
            <span class="tk-rank-meta">{!! icon('clock') !!} Tempo médio: {{ $item['tempo_medio_segundos'] !== null ? formatar_duracao($item['tempo_medio_segundos']) : '—' }}</span>
            @endif
        </div>
    </li>
    @endforeach
@endif
