{{-- Uma linha de tarefa da lista estilo Monday (TarefasController@listar). Recebe $t e os mapas do lote. --}}
@php
    $id           = (int) $t['id'];
    $atrasada     = $t['prazo'] && $t['prazo'] < $hoje && empty($t['concluida_em']);
    $prazoLabel   = $t['prazo'] ? date('d/m/Y', strtotime($t['prazo'])) : '—';
    $responsaveis = $resp_map[$id] ?? [];
    $n_anexos     = $anexo_map[$id] ?? 0;
    $n_coment     = $coment_map[$id] ?? 0;
    $sub          = $sub_map[$id] ?? ['total' => 0, 'feitos' => 0];
    // O número só aparece para quem é responsável ou foi mencionado, e some
    // assim que a pessoa abre a tarefa (a leitura é registrada no detalhe).
    $n_nao_lido   = !empty($meu_interesse[$id]) ? ($nao_lido_map[$id] ?? 0) : 0;
    $semTitulo    = trim((string) $t['titulo']) === '';
    $resp_ids     = array_map(static fn($r) => (int) $r['usuario_id'], $responsaveis);
@endphp
<div class="mnd-row" data-id="{{ $id }}">
    <div class="mnd-cell mnd-cell-drag">
        <span class="mnd-drag" title="Arraste para reordenar/mover">{!! icon('dots-six-vertical') !!}</span>
    </div>
    <div class="mnd-cell mnd-cell-nome">
        @if ($atrasada)<span class="mnd-atraso" title="Prazo vencido e tarefa não concluída">{!! icon('warning') !!}Em atraso</span>@endif
        <button type="button" class="mnd-titulo{{ $semTitulo ? ' is-vazio' : '' }}" data-action="editar-tarefa" data-id="{{ $id }}">{{ $semTitulo ? 'Sem título' : $t['titulo'] }}</button>
    </div>
    <div class="mnd-cell mnd-cell-msg" data-msg="{{ $n_coment }}" data-novo="{{ $n_nao_lido }}" title="Abrir detalhes, comentários e histórico">
        <span class="mnd-msg{{ $n_coment > 0 ? ' has-msg' : '' }}">{!! icon('chat-circle') !!}@if ($n_nao_lido > 0)<span class="mnd-msg-novo">{{ $n_nao_lido }}</span>@endif</span>
    </div>
    <div class="mnd-cell mnd-cell-resp" data-setor-id="{{ (int) ($t['setor_id'] ?? 0) }}" data-resp="{{ implode(',', $resp_ids) }}" title="Clique para atribuir responsáveis">
        @if (empty($responsaveis))
            <span class="mnd-resp-empty">{!! icon('user') !!}</span>
        @else
            <span class="mnd-resp">
            @foreach (array_slice($responsaveis, 0, 4) as $r)
                <span class="mnd-resp-av" title="{{ $r['username'] }}">{!! avatar_sm($r['avatar'] ?? null, $r['username']) !!}</span>
            @endforeach
            @if (count($responsaveis) > 4)<span class="mnd-resp-mais">+{{ count($responsaveis) - 4 }}</span>@endif
            </span>
        @endif
    </div>
    <div class="mnd-cell mnd-cell-status" data-status-id="{{ (int) ($t['status_id'] ?? 0) }}">
        @if ($t['status_nome'])
            <span class="tarefa-pill tarefa-pill-status mnd-status" data-cor="{{ $t['status_cor'] ?: '#64748b' }}">{{ $t['status_nome'] }}</span>
        @else
            <span class="mnd-status-vazio">—</span>
        @endif
    </div>
    <div class="mnd-cell mnd-cell-prazo" data-prazo="{{ $t['prazo'] ?? '' }}" title="Clique para definir o prazo">
        @if ($t['prazo'])
            <span class="mnd-prazo{{ $atrasada ? ' is-atrasada' : '' }}">{!! icon('calendar-blank') !!}{{ $prazoLabel }}</span>
        @else<span class="mnd-prazo-vazio">{!! icon('calendar-blank') !!}</span>@endif
    </div>
    <div class="mnd-cell mnd-cell-arq" data-arq="{{ $n_anexos }}" title="Arquivos da tarefa">
        <span class="mnd-arq{{ $n_anexos > 0 ? ' has-arq' : '' }}">{!! icon('paperclip') !!}@if ($n_anexos > 0)<span class="mnd-arq-n">{{ $n_anexos }}</span>@endif</span>
    </div>
    <div class="mnd-cell mnd-cell-sub" data-action="toggle-subitens" data-id="{{ $id }}" data-total="{{ $sub['total'] }}" data-feitos="{{ $sub['feitos'] }}" title="Checklist da tarefa">
        <span class="mnd-sub{{ $sub['total'] > 0 ? ' has-sub' : '' }}">{!! icon('list-checks') !!}@if ($sub['total'] > 0)<span class="mnd-sub-n">{{ $sub['feitos'] }}/{{ $sub['total'] }}</span>@endif</span>
    </div>
    <div class="mnd-cell mnd-cell-acoes">
        @if (!$f_arquivadas)
        <button type="button" class="mnd-act" data-action="criar-abaixo" data-id="{{ $id }}" title="Criar tarefa abaixo">{!! icon('plus') !!}</button>
        @endif
        <button type="button" class="mnd-act" data-action="editar-tarefa" data-id="{{ $id }}" title="Editar">{!! icon('note-pencil') !!}</button>
        <button type="button" class="mnd-act" data-action="arquivar-tarefa" data-id="{{ $id }}" data-arquivada="{{ $f_arquivadas ? 1 : 0 }}" title="{{ $f_arquivadas ? 'Desarquivar' : 'Arquivar' }}">{!! icon('eye-slash') !!}</button>
        <button type="button" class="mnd-act" data-action="excluir-tarefa" data-id="{{ $id }}" title="Excluir">{!! icon('trash') !!}</button>
    </div>
</div>
<div class="mnd-sub-list is-hidden" data-sub-de="{{ $id }}"></div>
