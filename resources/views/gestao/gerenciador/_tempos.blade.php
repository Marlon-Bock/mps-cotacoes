{{-- Linhas de "Tempo por cotação" — GerenciadorController@tempos → JSON.html. Recebe $linhas (tempos já calculados). --}}
@php $vazio = '<span class="admin-cot-cell-vazio">—</span>'; @endphp
@if (empty($linhas))
<div class="admin-cot-empty">Nenhuma cotação encontrada.</div>
@else
@foreach ($linhas as $l)
@php $row = $l['row']; @endphp
<div class="admin-cot-row admin-cot-row-tempos">
    <div class="admin-cot-cell"><span class="proto-code">{{ $row['protocolo'] ?? '' }}</span></div>
    <div class="admin-cot-cell">
        @if (!empty($row['atendente_nome']))
        <span class="atendente-chip">{!! avatar_sm($row['atendente_avatar'] ?? null, $row['atendente_nome']) !!} {{ $row['atendente_nome'] }}</span>
        @else
        <span class="admin-cot-cell-vazio">Não atribuído</span>
        @endif
    </div>
    <div class="admin-cot-cell"><span class="status-pill {{ $l['pill'] }}">{{ $l['label'] }}</span></div>
    <div class="admin-cot-cell">{!! $l['tempo_pre_analise'] !== null ? e(formatar_duracao($l['tempo_pre_analise'])) : $vazio !!}</div>
    <div class="admin-cot-cell">{!! $l['tempo_aguardando']  !== null ? e(formatar_duracao($l['tempo_aguardando']))  : $vazio !!}</div>
    <div class="admin-cot-cell">{!! $l['tempo_em_analise']  !== null ? e(formatar_duracao($l['tempo_em_analise']))  : $vazio !!}</div>
    <div class="admin-cot-cell">{!! $l['tempo_biometria']   !== null ? e(formatar_duracao($l['tempo_biometria']))   : $vazio !!}</div>
    <div class="admin-cot-cell">{!! $l['tempo_decisao']     !== null ? e($l['decisao_label'] . ': ' . formatar_duracao($l['tempo_decisao'])) : $vazio !!}</div>
    <div class="admin-cot-cell">{!! $l['tempo_envio']       !== null ? e(formatar_duracao($l['tempo_envio']))       : $vazio !!}</div>
    <div class="admin-cot-cell">{!! $l['tempo_concluido']   !== null ? e(formatar_duracao($l['tempo_concluido']))   : $vazio !!}</div>
    <div class="admin-cot-cell">
        @if ($l['tempo_total'] === null)
        {!! $vazio !!}
        @elseif ($l['em_aberto'])
        <span class="status-pill status-pill-andamento">Em andamento ({{ formatar_duracao($l['tempo_total']) }})</span>
        @else
        {{ formatar_duracao($l['tempo_total']) }}
        @endif
    </div>
    <div class="admin-cot-cell admin-cot-cell-acoes">
        <div class="table-actions">
            <button type="button" class="btn btn-secondary btn-sm" data-action="ver-timeline" data-protocolo="{{ $row['protocolo'] ?? '' }}" title="Ver linha do tempo">
                {!! icon('eye') !!}
            </button>
        </div>
    </div>
</div>
@endforeach
@endif
