{{-- Linhas dos atendimentos do chat guiado — FaqController@atendimentosListar (era includes/acao/admin-faq-atendimentos-listar.php). --}}
@if (empty($atendimentos))
<div class="admin-cot-empty">Nenhum atendimento encontrado{{ ($status_rapido !== '' || $imobiliaria_id > 0 || $busca !== '') ? ' para os filtros aplicados' : '' }}.</div>
@else
@foreach ($atendimentos as $a)
@php
    $usuarioTipoLabel = $a['usuario_tipo'] === 'admin' ? 'MPS' : ($a['imobiliaria_nome'] ?? 'Imobiliária');
    $atualizado = $a['atualizado_em'] ? date('d/m/Y H:i', strtotime($a['atualizado_em'])) : '—';

    if ((int) $a['concluido'] === 1) {
        $statusLabel = 'Concluído';
        $statusPill  = 'status-pill-aprovado';
    } elseif ((int) $a['chamado_aberto'] === 1) {
        $statusLabel = 'Abriu chamado';
        $statusPill  = 'status-pill-pendente';
    } else {
        $statusLabel = 'Em andamento';
        $statusPill  = 'status-pill-andamento';
    }

    $dados = array_filter([
        $a['nome_informado']        ? 'Nome: ' . $a['nome_informado']        : null,
        $a['telefone_informado']    ? 'Tel: ' . $a['telefone_informado']     : null,
        $a['imobiliaria_informada'] ? 'Imob: ' . $a['imobiliaria_informada'] : null,
    ]);
@endphp
<div class="admin-cot-row admin-cot-row-faq-atendimentos">
    <div class="admin-cot-cell">
        <span class="admin-cot-cell-nome">{{ $a['usuario_nome'] ?? '—' }}</span><br>
        <span class="admin-cot-cell-vazio">{{ $usuarioTipoLabel }}</span>
    </div>
    <div class="admin-cot-cell">
        @if ($dados)
            {!! implode('<br>', array_map('e', $dados)) !!}
        @else
            <span class="admin-cot-cell-vazio">Nada informado</span>
        @endif
    </div>
    <div class="admin-cot-cell admin-cot-cell-nome">
        @if (!empty($a['caminho']))
            {{ $a['caminho'] }}
        @else
            <span class="admin-cot-cell-vazio">—</span>
        @endif
    </div>
    <div class="admin-cot-cell"><span class="status-pill {!! $statusPill !!}">{!! $statusLabel !!}</span></div>
    <div class="admin-cot-cell">{{ $atualizado }}</div>
</div>
@endforeach
@endif
