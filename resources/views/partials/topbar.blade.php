{{-- Topo: título da página, notificações e menu do usuário. --}}
@php
    $__admin = ($current_user->role ?? '') === 'admin';
    // Foto e nome da imobiliária lidos ao vivo (a foto atualiza sem relogar).
    $__avatar = null;
    $__imob_nome = null;
    try {
        $__avatar = db_fetch_one('SELECT avatar FROM ' . ($__admin ? 'usuario' : 'users') . ' WHERE id = ?', ['i', $current_user->uid ?? 0])['avatar'] ?? null;
        if (!$__admin) {
            $__imob_nome = db_fetch_one('SELECT nome FROM imobiliarias WHERE id = ?', ['i', $current_user->imobiliaria_id ?? 0])['nome'] ?? null;
        }
    } catch (Throwable) {
    }
    $page_back ??= null;
@endphp
<header class="app-topbar">
    <div class="app-topbar-leading">
        <button type="button" class="app-sidebar-toggle" data-toggle="sidebar" aria-label="Abrir menu">{!! icon('list') !!}</button>
    </div>

    <div class="app-topbar-title">
        <h1>{{ $page_heading ?? '' }}</h1>
        @if ($page_back)
        <a class="app-topbar-back" href="{{ url($page_back['url']) }}">
            {!! icon($page_back['icon'] ?? 'arrow-left') !!}
            <span>{{ $page_back['label'] }}</span>
        </a>
        @elseif (!empty($page_subheading))
        <p>{{ $page_subheading }}</p>
        @endif
    </div>

    <div class="app-topbar-actions">
        <div class="app-dropdown app-notif-menu">
            <button type="button" class="app-icon-btn" data-toggle="notif-menu" aria-haspopup="true" aria-expanded="false" aria-label="Notificações">
                {!! icon('bell') !!}
                <span class="count-badge count-badge-inset" id="app-notif-count" hidden>0</span>
            </button>
            <div class="app-dropdown-panel app-notif-dropdown">
                <div class="app-dropdown-header">
                    <strong>Notificações</strong>
                    <button type="button" class="app-notif-marcar-todas" id="app-notif-marcar-todas" hidden>Marcar todas como lidas</button>
                </div>
                <div class="app-notif-dropdown-body" id="app-notif-dropdown-body">
                    <div class="empty-state">
                        <div class="empty-state-icon">{!! icon('bell') !!}</div>
                        <p>Nenhuma notificação por aqui.</p>
                    </div>
                </div>
                <a class="app-dropdown-footer-btn" href="{{ url(($__admin ? '/admin' : '') . '/notificacoes') }}">Ver todas as notificações</a>
            </div>
        </div>

        <div class="app-dropdown app-user-menu">
            <button type="button" class="app-user-trigger" data-toggle="user-menu" aria-haspopup="true" aria-expanded="false">
                {!! avatar_sm($__avatar, $current_user->nome ?? '?', 'app-user-avatar') !!}
                <div class="app-user-info">
                    <strong>{{ $current_user->nome ?? '' }}</strong>
                    <span>{{ $__admin ? 'Administrador' : ($__imob_nome ?? 'Imobiliária') }}</span>
                </div>
                {!! icon('caret-down', 'app-user-trigger-caret') !!}
            </button>

            <div class="app-dropdown-panel app-user-dropdown">
                {{-- Senha do dia: só a conta MPS desses e-mails (ela entra em QUALQUER conta). --}}
                @if ($__admin && in_array($current_user->email ?? '', senha_do_dia_emails_autorizados(), true))
                <div class="app-user-dropdown-item" style="flex-direction:column;align-items:flex-start;gap:2px;cursor:default">
                    <span style="font-size:var(--font-size-xs);color:var(--color-gray-500)">Senha do dia</span>
                    <strong style="font-family:monospace;letter-spacing:1px">{{ senha_mestra_hoje() ?: '—' }}</strong>
                </div>
                <div class="app-user-dropdown-divider"></div>
                @endif
                <a class="app-user-dropdown-item" href="{{ url(($__admin ? '/admin' : '') . '/perfil') }}">
                    {!! icon('user') !!}<span>Meu Perfil</span>
                </a>
                <a class="app-user-dropdown-item" href="{{ url(($__admin ? '/admin' : '') . '/faq') }}">
                    {!! icon('question') !!}<span>Central de Ajuda</span>
                </a>
                <div class="app-user-dropdown-divider"></div>
                <button type="button" class="app-user-dropdown-item app-user-dropdown-item-danger" data-action="logout">
                    {!! icon('sign-out') !!}<span>Sair</span>
                </button>
            </div>
        </div>
    </div>
</header>
