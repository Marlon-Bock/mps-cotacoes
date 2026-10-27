{{-- Login do painel de TV — /painel/login (PainelController@loginForm). --}}
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel de exibição — MPS Cotações</title>
    <link rel="icon" href="{{ url('/assets/img/mesquita-prime-e-sami-corretora-de-seguros-favicon.png') }}">
    <meta name="base-path" content="{{ BASE_URI }}">
    <script src="{{ asset_v('/assets/js/base-path.js') }}"></script>
    <link rel="stylesheet" href="{{ asset_v('/assets/css/tokens.css') }}">
    <link rel="stylesheet" href="{{ asset_v('/assets/css/base.css') }}">
    <link rel="stylesheet" href="{{ asset_v('/assets/css/components.css') }}">
    <link rel="stylesheet" href="{{ asset_v('/assets/css/mps-alert.css') }}">
    <link rel="stylesheet" href="{{ asset_v('/assets/css/painel.css') }}">
</head>

<body class="painel-body">

    <main class="painel-login">
        <div class="painel-login-box">
            <span class="painel-login-icon">{!! icon('monitor') !!}</span>
            <h1 class="painel-login-title">Painel de exibição</h1>
            <p class="painel-login-sub">Entre com a conta do painel para iniciar a exibição na TV.</p>

            <form data-form="login" data-csrf="{{ csrf_token() }}" data-loading-label="Validando..." novalidate>
                <div class="alert alert-danger" data-form-alert hidden></div>

                <div class="field">
                    <label class="field-label" for="email">E-mail</label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon">{!! icon('envelope-simple') !!}</span>
                        <input class="field-input has-icon" type="email" id="email" name="email"
                               placeholder="painel@mps.com.br" autocomplete="username" required>
                    </div>
                    <span class="field-error" data-error-for="email"></span>
                </div>

                <div class="field">
                    <label class="field-label" for="senha">Senha</label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon">{!! icon('key') !!}</span>
                        <input class="field-input has-icon has-icon-both" type="password" id="senha" name="senha"
                               placeholder="••••••••" autocomplete="current-password" required>
                        <button type="button" class="field-password-toggle" data-toggle="password" data-target="#senha"
                                aria-label="Mostrar senha">
                            {!! icon('eye', 'icon-eye') !!}
                            {!! icon('eye-slash', 'icon-eye-slash') !!}
                        </button>
                    </div>
                    <span class="field-error" data-error-for="senha"></span>
                </div>

                <button type="submit" class="btn btn-primary btn-block">
                    <span data-label>Entrar no painel</span>
                    <span class="spinner"></span>
                </button>
            </form>
        </div>
    </main>

    <script src="{{ asset_v('/assets/js/mpsAlert.js') }}"></script>
    <script src="{{ asset_v('/assets/js/auth.js') }}"></script>
</body>
</html>
