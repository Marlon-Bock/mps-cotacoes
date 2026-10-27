{{-- Linhas de /usuarios (imobiliária) e /admin/usuarios (MPS) — era o ob_start de
     includes/acao/usuarios-listar.php e admin-usuarios-listar.php.
     Espera: $usuarios, $current_user, $mps (bool: lista da MPS → Painel de TV/Atendente e link /admin/usuarios). --}}
@if (empty($usuarios))
    <div class="admin-cot-empty">Nenhum usuário encontrado.</div>
@else
    @foreach ($usuarios as $u)
    @php
        $eh_admin     = $u['admin'] === 'S';
        $eh_painel    = $mps && ($u['painel'] ?? 'N') === 'S';
        $eh_voce      = (int) $u['id'] === (int) $current_user->uid;
        $ultimo_login = $u['last_login_at'] ? date('d/m/Y H:i', strtotime($u['last_login_at'])) : '—';
    @endphp
    <div class="admin-cot-row admin-cot-row-usuarios">
        <div class="admin-cot-cell admin-cot-cell-nome">
            <span class="atendente-chip">{!! avatar_sm($u['avatar'] ?? null, $u['username'], 'avatar-md') !!} {{ $u['username'] }}</span>
            @if ($eh_voce)<span class="badge">Você</span>@endif
        </div>
        <div class="admin-cot-cell">{{ $u['email'] }}</div>
        <div class="admin-cot-cell">{!! $u['telefone'] ? e($u['telefone']) : '—' !!}</div>
        <div class="admin-cot-cell">
            @if ($eh_painel)
                <span class="badge">Painel de TV</span>
            @elseif ($eh_admin)
                <span class="badge badge-primary">Administrador</span>
            @else
                <span class="badge">{{ $mps ? 'Atendente' : 'Operador' }}</span>
            @endif
        </div>
        <div class="admin-cot-cell" style="color:var(--color-gray-500)">{!! $ultimo_login !!}</div>
        <div class="admin-cot-cell">
            <div class="table-actions">
                <a href="{{ url(($mps ? '/admin/usuarios/' : '/usuarios/') . $u['slug'] . '/editar') }}" class="btn btn-secondary btn-sm">
                    {!! icon('note-pencil') !!} Editar
                </a>
                @if (!$eh_voce)
                <button type="button" class="btn btn-danger-outline btn-sm" data-action="excluir-usuario" data-id="{{ (int) $u['id'] }}">
                    {!! icon('x') !!} Excluir
                </button>
                @endif
            </div>
        </div>
    </div>
    @endforeach
@endif
