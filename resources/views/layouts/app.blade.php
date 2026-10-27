{{--
    Layout das telas autenticadas (era app-head.php + app-foot.php + sidebar/topbar).
    Variáveis (todas opcionais, passadas pelo controller):
      $page_title, $page_heading, $page_subheading, $page_back (['url','label','icon']),
      $extra_css / $extra_js (arquivos em /assets/css e /assets/js), $main_attrs (atributos extras do <main>).
    Seções: content (miolo), e os stacks 'head', 'antes-scripts' e 'scripts'.
--}}
@php
    $current_user ??= auth_user();
    $extra_css ??= [];
    $extra_js ??= [];
    $nav_groups ??= ($current_user->role ?? '') === 'admin' ? nav_admin_grupos($current_user) : nav_imobiliaria_grupos($current_user);
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $page_title ?? '' }} — MPS Cotações</title>
    <meta name="base-path" content="{{ BASE_URI }}">
    <script src="{{ asset_v('/assets/js/base-path.js') }}"></script>
    <link rel="icon" href="{{ url('/assets/img/mesquita-prime-e-sami-corretora-de-seguros-favicon.png') }}">
    <link rel="manifest" href="{{ url('/manifest.webmanifest') }}">
    <link rel="apple-touch-icon" href="{{ url('/assets/img/logo-mark.png') }}">
    <meta name="theme-color" content="#7F1B1B">
    @foreach (['tokens', 'base', 'components', 'mps-alert', 'layout', 'chamados', 'cotacao-chat'] as $__css)
    <link rel="stylesheet" href="{{ asset_v('/assets/css/' . $__css . '.css') }}">
    @endforeach
    @foreach ($extra_css as $__css)
    <link rel="stylesheet" href="{{ asset_v('/assets/css/' . $__css) }}">
    @endforeach
    {{-- Fim da sessão (watchdog JS avisa antes de expirar). --}}
    <meta name="session-exp" content="{{ time() + (int) config('session.lifetime') * 60 }}">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    @stack('head')
</head>
<body data-role="{{ $current_user->role ?? '' }}" data-uid="{{ (int) ($current_user->uid ?? 0) }}">
<div class="app-shell">
    @include('partials.sidebar')
    <div class="app-main">
        @include('partials.topbar')
        <main class="app-content"{!! $main_attrs ?? '' !!}>
            @yield('content')
        </main>
    </div>
</div>
@stack('antes-scripts')
@include('partials.chat-offcanvas')
@foreach (['csrf-fetch', 'mpsAlert', 'custom-select', 'date-picker', 'time-picker', 'app', 'session-watchdog', 'notify-sound', 'cotacao-chat', 'notificacoes-bell', 'vendor/sortable.min', 'pwa-install'] as $__js)
<script src="{{ asset_v('/assets/js/' . $__js . '.js') }}"></script>
@endforeach
@foreach ($extra_js as $__js)
<script src="{{ asset_v('/assets/js/' . $__js) }}"></script>
@endforeach
@stack('scripts')
</body>
</html>
