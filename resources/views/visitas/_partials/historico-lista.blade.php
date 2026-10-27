{{-- HTML da lista do Histórico de Visitas — VisitasController@historicoListar (JSON { html, ... }). --}}
@if (empty($itens))
<div class="admin-cot-empty">Nenhuma visita encontrada para os filtros selecionados.</div>
@else
<div class="admin-cot-grid-wrap">
    <div class="admin-cot-row admin-cot-row-visitas admin-cot-head">
        <div class="admin-cot-cell">Data</div>
        <div class="admin-cot-cell admin-cot-cell-nome">Imobiliária</div>
        <div class="admin-cot-cell">Corretor</div>
        <div class="admin-cot-cell">Entrada</div>
        <div class="admin-cot-cell">Saída</div>
        <div class="admin-cot-cell">Gerente</div>
        <div class="admin-cot-cell">Atendente</div>
        <div class="admin-cot-cell">Motivo</div>
        <div class="admin-cot-cell">Motivo (desc.)</div>
        @foreach ($seguros_labels as $label)
        <div class="admin-cot-cell">{{ $label }}</div>
        @endforeach
        <div class="admin-cot-cell">Outros</div>
        <div class="admin-cot-cell">Outros (desc.)</div>
        <div class="admin-cot-cell">Falta material</div>
        <div class="admin-cot-cell">Materiais</div>
        <div class="admin-cot-cell">Observações</div>
    </div>
    @foreach ($itens as $item)
    @php
        $tem_falta   = !empty($item['falta_material']);
        $outros_txt  = trim((string) ($item['outros_seguros'] ?? ''));
        $extras_do_item = $extras_map[(int) $item['id']] ?? [];
        // Avatares empilhados: principal (quem registrou) primeiro, depois
        // os extras (mesmo padrão de .mnd-resp-av/.mnd-resp-mais usado na
        // lista do Agendar Visita).
        $avatares_visita = array_merge(
            [['username' => $item['corretor_nome'], 'avatar' => $item['corretor_avatar'] ?? null]],
            array_map(static fn($ex) => ['username' => $ex['username'], 'avatar' => $ex['avatar']], $extras_do_item)
        );
    @endphp
    <div class="admin-cot-row admin-cot-row-visitas">
        <div class="admin-cot-cell">{{ date_to_br($item['data_relatorio']) }}</div>
        <div class="admin-cot-cell admin-cot-cell-nome">{{ $item['imobiliaria_nome'] ?: '—' }}</div>
        <div class="admin-cot-cell">
            <span class="mnd-resp">
                @foreach (array_slice($avatares_visita, 0, 4) as $av)
                <span class="mnd-resp-av" title="{{ $av['username'] }}">{!! avatar_sm($av['avatar'] ?? null, $av['username']) !!}</span>
                @endforeach
            </span>
            @if (count($avatares_visita) > 4)<span class="mnd-resp-mais">+{{ count($avatares_visita) - 4 }}</span>@endif
        </div>
        <div class="admin-cot-cell">{{ substr($item['horario_entrada'] ?? '', 0, 5) ?: '—' }}</div>
        <div class="admin-cot-cell">{{ substr($item['horario_saida'] ?? '', 0, 5) ?: '—' }}</div>
        <div class="admin-cot-cell">{{ $item['gerente'] ?: '—' }}</div>
        <div class="admin-cot-cell">{{ $item['atendente'] ?: '—' }}</div>
        <div class="admin-cot-cell">{{ $item['motivo_visita'] ?: '—' }}</div>
        <div class="admin-cot-cell" title="{{ $item['motivo_descricao'] ?? '' }}">{{ $item['motivo_descricao'] ?: '—' }}</div>
        @foreach ($seguros_labels as $col => $label)
        <div class="admin-cot-cell">{!! $visita_sim_nao(!empty($item[$col])) !!}</div>
        @endforeach
        <div class="admin-cot-cell">{!! $visita_sim_nao($outros_txt !== '') !!}</div>
        <div class="admin-cot-cell" title="{{ $outros_txt }}">{{ $outros_txt ?: '—' }}</div>
        <div class="admin-cot-cell">{!! $visita_sim_nao($tem_falta) !!}</div>
        <div class="admin-cot-cell" title="{{ $item['materiais_faltantes'] ?? '' }}">{!! $tem_falta ? e($item['materiais_faltantes'] ?: '—') : '<span class="admin-cot-cell-vazio">—</span>' !!}</div>
        <div class="admin-cot-cell admin-cot-cell-obs">
            @if (trim((string) ($item['observacoes'] ?? '')) !== '')
            <button type="button" class="admin-cot-obs-btn" data-action="ver-observacao" data-obs="{{ $item['observacoes'] }}" title="Ver observação completa">{!! icon('eye') !!}</button>
            @else
            <span class="admin-cot-cell-vazio">—</span>
            @endif
        </div>
    </div>
    @endforeach
</div>
@endif
