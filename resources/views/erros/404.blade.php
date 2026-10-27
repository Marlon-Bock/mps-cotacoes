<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Página não encontrada — MPS Cotações</title>
    <link rel="icon" href="{{ url('/assets/img/mesquita-prime-e-sami-corretora-de-seguros-favicon.png') }}">
    <meta name="base-path" content="{{ BASE_URI }}">
    <script src="{{ asset_v('/assets/js/base-path.js') }}"></script>
    <link rel="stylesheet" href="{{ asset_v('/assets/css/tokens.css') }}">
    <link rel="stylesheet" href="{{ asset_v('/assets/css/base.css') }}">
    <link rel="stylesheet" href="{{ asset_v('/assets/css/components.css') }}">
    <link rel="stylesheet" href="{{ asset_v('/assets/css/auth.css') }}">
</head>
<body>

<div class="auth-screen-centered">
    <div class="card auth-status-box">
        <div class="auth-status-icon">404</div>
        <h2>Página não encontrada</h2>
        <p>O endereço acessado não existe ou foi movido.</p>
        <a class="btn btn-primary btn-block" href="{{ url('/') }}">Voltar ao início</a>
    </div>
</div>

</body>
</html>
