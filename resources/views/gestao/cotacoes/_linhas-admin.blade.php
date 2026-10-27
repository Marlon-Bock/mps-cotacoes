{{-- Linhas da "Todas as Cotações" da MPS (grid — sem <table>).
     CotacoesController@adminListar → JSON.html. Recebe $rows_pagina, $status_api, $status_seg (Fiança da Segimob), $unread_map, $novidades_api. --}}
@if (empty($rows_pagina))
<div class="admin-cot-empty">Nenhuma cotação encontrada.</div>
@else
@foreach ($rows_pagina as $row)
@php
    $origem      = $row['origem'] ?? 'fianca';
    $status_raw  = (string) ($row['status'] ?? '');
    $label       = admin_status_label($status_raw, $origem);
    $pill        = status_pill_class($status_raw);

    $is_api       = in_array($origem, ['fianca', 'residencial'], true) && ($row['origem_ficha'] ?? '') === 'api';
    $cotacoes_api = $is_api ? ($status_api[(int) $row['id']]['cotacoes'] ?? []) : [];
    if ($is_api && $status_raw === API_STATUS_FICHA) {
        ['label' => $label, 'pill' => $pill] = api_status_resumo_fluxo($status_api[(int) $row['id']]);
    }
    $lote_seg = $origem === 'fianca' && ($row['origem_ficha'] ?? '') === SEGIMOB_ORIGEM ? ($status_seg[(int) $row['id']] ?? null) : null;
    if ($lote_seg !== null && $status_raw === SEGIMOB_STATUS_FICHA) {
        ['label' => $label, 'pill' => $pill] = segimob_status_resumo_lote($lote_seg);
    }
    // Fiança na API/Segimob só diz pré-aprovado ou recusado — o resultado de
    // cada seguradora (info + "Seguradoras") fica só no Residencial da API.
    $mostra_seguradoras = $is_api && $origem === 'residencial';

    $produto_label = match ($origem) {
        'auto'        => 'Seguro Auto',
        'titulo'      => 'Título de Capitalização',
        'condominial' => 'Seguro Condomínio',
        'incendio'    => 'Seguro Incêndio',
        'residencial' => 'Seguro Residencial',
        default       => 'Fiança ' . (strtolower($row['tipo_ficha'] ?? '') === 'comercial' ? 'Comercial' : 'Residencial'),
    };
    $tipo_pill_key = match ($origem) { 'auto' => 'auto', 'titulo' => 'titulo', 'condominial' => 'condominial', 'incendio' => 'incendio', 'residencial' => 'residencial', default => 'fianca' };

    $protocolo_val    = (string) ($row['protocolo']      ?? '');
    $pretendente_val  = (string) ($row['pretendente']    ?? '—');
    $imob_nome_val    = (string) ($row['imob_nome']      ?? '');
    $atendente_val    = (string) ($row['atendente_nome'] ?? '');
    $data_criacao_val = (string) ($row['data_cadastro']  ?? '');
    $data_atual_val   = (string) ($row['atualizado_em']  ?? '');

    $link        = cotacao_admin_url($protocolo_val);
    $unread_chat = $unread_map[$protocolo_val] ?? 0;
@endphp
<div class="admin-cot-row">
    <div class="admin-cot-cell">
        <span class="tipo-pill tipo-pill-{{ $tipo_pill_key }}">{{ $produto_label }}</span>
    </div>
    <div class="admin-cot-cell"><span class="proto-code">{{ $protocolo_val }}</span></div>
    <div class="admin-cot-cell admin-cot-cell-nome">
        {{ $pretendente_val }}
        @if (!empty($row['incendio_imoveis']))
        <span class="api-status-hover" tabindex="0" aria-label="Pretendentes por imóvel">
            {!! icon('info') !!}
            <span class="api-status-panel">
                @foreach ($row['incendio_imoveis'] as $im)
                <span class="api-status-panel-item"><strong>Imóvel {{ $im['numero_imovel'] }}:</strong> {{ $im['locatario'] !== '' ? $im['locatario'] : '—' }}{{ $im['status'] !== null ? ' — ' . $im['status'] : '' }}</span>
                @endforeach
            </span>
        </span>
        @endif
    </div>
    <div class="admin-cot-cell">
        <span class="status-pill {{ $pill }}">{{ $label }}</span>
        @if ($mostra_seguradoras)
        <span class="api-status-hover" tabindex="0" aria-label="Status por seguradora">
            {!! icon('info') !!}
            <span class="api-status-panel api-status-panel--seguradoras">{!! api_status_painel_html($cotacoes_api, (string) ($row['encaminhado_mps_detalhe'] ?? ''), $status_raw, $status_api[(int) $row['id']]['propostas'] ?? []) !!}</span>
        </span>
        @endif
    </div>
    <div class="admin-cot-cell">{{ $imob_nome_val ?: '—' }}</div>
    <div class="admin-cot-cell">
        @if ($atendente_val !== '')
        <span class="atendente-chip">{!! avatar_sm($row['atendente_avatar'] ?? null, $atendente_val) !!} {{ $atendente_val }}</span>
        @else
        <span style="color:var(--color-gray-400)">Não atribuído</span>
        @endif
    </div>
    <div class="admin-cot-cell admin-cot-cell-datas">
        <span><span class="admin-cot-data-label">Criada</span> {{ $data_criacao_val ? date('d/m/Y H:i', strtotime($data_criacao_val)) : '—' }}</span>
        <span><span class="admin-cot-data-label">Atual.</span> {{ $data_atual_val ? date('d/m/Y H:i', strtotime($data_atual_val)) : '—' }}</span>
    </div>
    <div class="admin-cot-cell admin-cot-cell-acoes">
        <div class="table-actions">
            <a href="{{ $link }}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm">{!! icon('eye') !!} Ver detalhes</a>
            @if ($mostra_seguradoras)
            @php $novas_api = $novidades_api[(int) $row['id']] ?? 0; @endphp
            <a href="{{ url('/admin/cotacao/' . $origem . '/' . rawurlencode($protocolo_val) . '/seguradoras') }}" target="_blank" rel="noopener" class="btn btn-sm btn-chat-cotacao {{ $novas_api > 0 ? 'btn-danger-outline' : 'btn-secondary' }}" title="Seguradoras" aria-label="Seguradoras">
                {!! icon('shield-check') !!}
                @if ($novas_api > 0)
                    <span class="count-badge">{{ $novas_api > 99 ? '99+' : $novas_api }}</span>
                @endif
            </a>
            @endif
            <button type="button" class="btn btn-sm btn-chat-cotacao {{ $unread_chat > 0 ? 'btn-danger-outline' : 'btn-secondary' }}" data-action="abrir-chat-cotacao" data-protocolo="{{ $protocolo_val }}" title="Chat da cotação">
                {!! icon('chat-circle') !!}
                @if ($unread_chat > 0)
                    <span class="count-badge">{{ $unread_chat > 99 ? '99+' : $unread_chat }}</span>
                @endif
            </button>
        </div>
    </div>
</div>
@endforeach
@endif
