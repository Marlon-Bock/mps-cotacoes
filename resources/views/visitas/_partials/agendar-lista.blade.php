{{-- HTML da lista de agendamentos — VisitasController@agendarListar (JSON { html, contadores }). --}}
@if (empty($rows))
<div class="admin-cot-empty">Nenhum agendamento encontrado.</div>
@else
<div class="admin-cot-grid-wrap">
    <div class="admin-cot-row admin-cot-row-agendar admin-cot-head">
        <div class="admin-cot-cell">Data</div>
        <div class="admin-cot-cell">Horário</div>
        <div class="admin-cot-cell admin-cot-cell-nome">Título</div>
        <div class="admin-cot-cell">Região</div>
        <div class="admin-cot-cell">Imobiliária</div>
        <div class="admin-cot-cell">Corretor</div>
        <div class="admin-cot-cell">Status</div>
        <div class="admin-cot-cell">Ações</div>
    </div>
    @foreach ($rows as $r)
    @php
        $concluida = $r['status'] === 'concluida';
        $endereco_completo = trim((string) ($r['local_visita'] ?? ''));
        $maps_url = $endereco_completo ? 'https://www.google.com/maps/search/?api=1&query=' . urlencode($endereco_completo) : null;
        $extras_do_item = $extras_map[(int) $r['id']] ?? [];
        $itemParaJson = $r;
        $itemParaJson['corretores_extra'] = array_map(static fn($ex) => [
            'id'       => (int) $ex['usuario_id'],
            'username' => $ex['username'],
            'avatar'   => $ex['avatar'],
        ], $extras_do_item);
        $itemJson = json_encode($itemParaJson, JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT);

        // Avatares empilhados: principal primeiro, depois os extras (mesmo
        // padrão de .mnd-resp-av/.mnd-resp-mais da coluna "Responsável" de tarefas).
        $avatares_visita = array_merge(
            [['username' => $r['corretor_nome'], 'avatar' => $r['corretor_avatar'] ?? null]],
            array_map(static fn($ex) => ['username' => $ex['username'], 'avatar' => $ex['avatar']], $extras_do_item)
        );
    @endphp
    <div class="admin-cot-row admin-cot-row-agendar" data-item="{{ $itemJson }}">
        <div class="admin-cot-cell">{{ date_to_br($r['data_visita']) }}</div>
        <div class="admin-cot-cell">{{ substr($r['hora_inicio'], 0, 5) }}–{{ substr($r['hora_fim'], 0, 5) }}</div>
        <div class="admin-cot-cell admin-cot-cell-nome">{{ $r['titulo'] }}</div>
        <div class="admin-cot-cell">{!! $r['regiao_nome'] ? e($r['regiao_nome']) : '<span class="admin-cot-cell-vazio">—</span>' !!}</div>
        <div class="admin-cot-cell">{{ $r['imobiliaria_nome'] ?: '—' }}</div>
        <div class="admin-cot-cell">
            <span class="mnd-resp">
                @foreach (array_slice($avatares_visita, 0, 4) as $av)
                <span class="mnd-resp-av" title="{{ $av['username'] }}">{!! avatar_sm($av['avatar'] ?? null, $av['username']) !!}</span>
                @endforeach
            </span>
            @if (count($avatares_visita) > 4)<span class="mnd-resp-mais">+{{ count($avatares_visita) - 4 }}</span>@endif
        </div>
        <div class="admin-cot-cell">
            <span class="status-pill {{ $concluida ? 'status-pill-aprovado' : 'status-pill-pendente' }}">
                {{ $concluida ? 'Concluída' : 'Pendente' }}
            </span>
        </div>
        <div class="admin-cot-cell admin-cot-cell-acoes">
            <div class="table-actions">
                @if ($concluida)
                    <a href="{{ url('/admin/visitas/relatorio') }}" class="btn btn-secondary btn-sm" title="Ver relatório">{!! icon('clipboard-text') !!}</a>
                @else
                    <a href="{{ url('/admin/visitas/relatorio') }}?ag_id={{ (int) $r['id'] }}" class="btn btn-primary btn-sm" title="Registrar visita">{!! icon('note-pencil') !!}</a>
                @endif
                @if (!empty($r['google_event_link']))
                    <a href="{{ $r['google_event_link'] }}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm" title="Abrir no Google Agenda">{!! icon('calendar-blank') !!}</a>
                @endif
                @if (!empty($r['link_reuniao']))
                    <a href="{{ $r['link_reuniao'] }}" target="_blank" rel="noopener" class="btn btn-primary btn-sm" title="Acessar a reunião online">{!! icon('monitor') !!}</a>
                @endif
                @if ($maps_url)
                    <a href="{{ $maps_url }}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm" title="Ver no mapa">{!! icon('map-pin') !!}</a>
                @endif
                @if (trim((string) ($r['descricao'] ?? '')) !== '')
                    <button type="button" class="btn btn-secondary btn-sm" data-action="agendamento-descricao" title="Ver descrição">{!! icon('file-text') !!}</button>
                @endif
                <button type="button" class="btn btn-secondary btn-sm" data-action="agendamento-editar" title="Editar">{!! icon('note-pencil') !!}</button>
                <button type="button" class="btn btn-danger-outline btn-sm" data-action="agendamento-excluir" data-id="{{ (int) $r['id'] }}" title="Excluir">{!! icon('x') !!}</button>
            </div>
        </div>
    </div>
    @endforeach
</div>
@endif
