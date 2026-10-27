{{--
    <head> enxuto para as páginas de embed (cotação sem login, dentro de um
    iframe no site do cliente) — era pages/partials/embed-head.php. Versão
    reduzida do layout: mesmos CSS base do formulário, mas sem
    sidebar/topbar/chat/notificações (que dependem de sessão autenticada e não
    fazem sentido dentro do iframe).
    @include('partials.embed-head')  … miolo …  @include('partials.embed-foot')
    (abre <html>, <body> e <main class="app-content">; o embed-foot fecha.)

    Espera:
      $page_title
      $extra_css     (opcional — arquivos em /assets/css/)
      $current_user  (stub do embed_resolver())
      $embed_token   (CSRF stateless via meta tag, lido pelo csrf-fetch.js)
--}}
@php
    $extra_css ??= [];
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $page_title }} — MPS Cotações</title>
    <meta name="base-path" content="{{ BASE_URI }}">
    <script src="{{ asset_v('/assets/js/base-path.js') }}"></script>
    <link rel="stylesheet" href="{{ asset_v('/assets/css/tokens.css') }}">
    <link rel="stylesheet" href="{{ asset_v('/assets/css/base.css') }}">
    <link rel="stylesheet" href="{{ asset_v('/assets/css/components.css') }}">
    <link rel="stylesheet" href="{{ asset_v('/assets/css/mps-alert.css') }}">
    <link rel="stylesheet" href="{{ asset_v('/assets/css/layout.css') }}">
    @foreach ($extra_css as $css)
    <link rel="stylesheet" href="{{ asset_v('/assets/css/' . $css) }}">
    @endforeach
    <meta name="csrf-token" content="{{ embed_csrf_token($embed_token) }}">
    <style>
        /* Dentro do iframe não existe app-shell (sidebar/topbar) — só o form. */
        body { background: transparent; }
        .app-content { padding: 16px; max-width: 1100px; margin: 0 auto; }
    </style>
</head>
<body data-role="imobiliaria" data-embed-token="{{ $embed_token }}">
<main class="app-content">
