{{-- Linhas da listagem de leads do site (grid — sem <table>). LeadsController@listar → JSON.html. --}}
@if (empty($rows_pagina))
<div class="admin-cot-empty">Nenhum lead encontrado.</div>
@else
@foreach ($rows_pagina as $row)
@php $link = url('/admin/leads/' . rawurlencode($row['protocolo'])); @endphp
<div class="admin-cot-row">
    <div class="admin-cot-cell"><span class="tipo-pill {{ lead_site_pill_class($row['seguro']) }}">{{ $row['produto_label'] }}</span>@if ($row['origem_site'] === 'suspeito') <span class="tipo-pill tipo-pill-lead-suspeito" title="Envio suspeito de spam (isca preenchida ou rápido demais) — não gera aviso">Suspeito</span>@endif</div>
    <div class="admin-cot-cell"><span class="proto-code">{{ $row['protocolo'] }}</span></div>
    <div class="admin-cot-cell admin-cot-cell-nome">{{ $row['nome'] ?: '—' }}</div>
    <div class="admin-cot-cell">{{ $row['telefone'] ?: '—' }}</div>
    <div class="admin-cot-cell">{{ $row['email'] ?: '—' }}</div>
    <div class="admin-cot-cell">{{ $row['perfil'] ? lead_site_perfil_label($row['perfil']) : '—' }}</div>
    <div class="admin-cot-cell admin-cot-cell-datas">
        <span><span class="admin-cot-data-label">Enviado</span> {{ date('d/m/Y H:i', strtotime($row['criado_em'])) }}</span>
    </div>
    <div class="admin-cot-cell admin-cot-cell-acoes">
        <div class="table-actions">
            <a href="{{ $link }}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm">{!! icon('eye') !!} Ver detalhes</a>
        </div>
    </div>
</div>
@endforeach
@endif
