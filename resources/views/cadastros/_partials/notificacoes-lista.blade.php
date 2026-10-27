{{-- Lista da central de notificações (era o ob_start de includes/acao/notificacoes-listar.php e
     admin-notificacoes-listar.php). Espera: $notificacoes, $tipo_filtro, $icone_tipo (nome da função de ícone). --}}
@if (empty($notificacoes))
<div class="admin-cot-empty">
    @if ($tipo_filtro !== '')
        Nenhuma notificação por aqui.
    @else
        Você não tem nenhuma notificação ainda.
    @endif
</div>
@else
<ul class="notif-list" id="notif-list">
    @foreach ($notificacoes as $notif)
    @php
        $lida     = (int) ($notif['lida'] ?? 0);
        $notif_id = (int) $notif['id'];
        $data_fmt = !empty($notif['criado_em'])
            ? date('d/m/Y \à\s H:i', strtotime($notif['criado_em']))
            : '—';
    @endphp
        <li class="notif-item{{ $lida ? ' is-read' : '' }}" data-id="{{ $notif_id }}">
            <span class="notif-item-icon notif-item-icon--{{ $notif['tipo'] ?? 'geral' }}">
                {!! icon($icone_tipo($notif['tipo'] ?? '')) !!}
            </span>
            <div class="notif-item-body">
                <div class="notif-item-header">
                    <strong class="notif-item-titulo">{{ $notif['titulo'] ?? '' }}</strong>
                    @if (!$lida)
                        <span class="badge badge-primary">Nova</span>
                    @endif
                </div>
                <p class="notif-item-mensagem">{{ $notif['mensagem'] ?? '' }}</p>
                <span class="notif-item-data">{{ $data_fmt }}</span>
            </div>
            @if (!$lida)
                <button type="button"
                        class="btn btn-link btn-sm notif-marcar-btn"
                        data-action="marcar-lida"
                        data-id="{{ $notif_id }}">
                    Marcar como lida
                </button>
            @endif
        </li>
    @endforeach
</ul>
@endif
