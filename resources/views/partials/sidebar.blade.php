{{-- Menu lateral (MPS e imobiliária). Espera $nav_groups — ver app/Legado/navegacao.php. --}}
@php
    // Ativo = o link mais específico (mais longo) que bate com o caminho atual —
    // evita /admin ficar marcado em todas as subpáginas.
    $__path = request_path();
    $__active_url = null;
    foreach ($nav_groups as $__g) {
        foreach ($__g['itens'] as $__it) {
            $__urls = !empty($__it['submenu']) ? array_column($__it['submenu'], 'url') : (array) ($__it['url'] ?? null);
            foreach (array_filter($__urls) as $__u) {
                $__bate = $__path === $__u || ($__u !== '/' && str_starts_with($__path, $__u . '/'));
                if ($__bate && ($__active_url === null || strlen($__u) > strlen($__active_url))) {
                    $__active_url = $__u;
                }
            }
        }
    }
    $__seq = 0;
@endphp
<div class="app-sidebar-overlay" data-toggle="sidebar"></div>
<aside class="app-sidebar">
    <div class="app-sidebar-logo">
        <img src="{{ url('/assets/img/logo-mark.png') }}" alt="" width="32" height="32">
        <strong>MPS Cotações</strong>
    </div>

    <nav class="app-sidebar-scroll">
        @foreach ($nav_groups as $group)
        <div class="app-nav-group">
            @if (!empty($group['titulo']))
            <div class="app-nav-group-title">{{ $group['titulo'] }}</div>
            @endif

            @foreach ($group['itens'] as $item)
                @if (!empty($item['disabled']))
                    <span class="app-nav-link is-disabled">
                        <span class="app-nav-icon">{!! icon($item['icone']) !!}</span>
                        <span>{{ $item['label'] }}</span>
                    </span>
                @elseif (!empty($item['submenu']))
                    @php
                        $__seq++;
                        $__ativo_filho = in_array($__active_url, array_column($item['submenu'], 'url'), true);
                    @endphp
                    <button type="button" class="app-nav-link app-nav-submenu-toggle {{ $__ativo_filho ? 'is-active' : '' }}"
                            data-toggle="submenu" aria-expanded="{{ $__ativo_filho ? 'true' : 'false' }}" aria-controls="submenu-{{ $__seq }}">
                        <span class="app-nav-icon">{!! icon($item['icone']) !!}</span>
                        <span>{{ $item['label'] }}</span>
                        {!! icon('caret-down', 'app-nav-submenu-caret') !!}
                    </button>
                    <div class="app-nav-submenu {{ $__ativo_filho ? 'is-open' : '' }}" id="submenu-{{ $__seq }}">
                        @foreach ($item['submenu'] as $sub)
                        <a class="app-nav-submenu-item {{ $sub['url'] === $__active_url ? 'is-active' : '' }}" href="{{ url($sub['url']) }}">{{ $sub['label'] }}</a>
                        @endforeach
                    </div>
                @else
                    <a class="app-nav-link {{ $item['url'] === $__active_url ? 'is-active' : '' }}" href="{{ url($item['url']) }}">
                        <span class="app-nav-icon">{!! icon($item['icone']) !!}</span>
                        <span>{{ $item['label'] }}</span>
                    </a>
                @endif
            @endforeach
        </div>
        @endforeach
    </nav>

    <div class="app-sidebar-footer">
        &copy; {{ date('Y') }} Mesquita Prime e Sami
    </div>
</aside>
