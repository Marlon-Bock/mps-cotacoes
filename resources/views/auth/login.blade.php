{{-- Login — /login (AuthController@loginForm) --}}
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Entrar — MPS Cotações</title>
    <link rel="icon" href="{{ url('/assets/img/mesquita-prime-e-sami-corretora-de-seguros-favicon.png') }}">
    <link rel="manifest" href="{{ url('/manifest.webmanifest') }}">
    <link rel="apple-touch-icon" href="{{ url('/assets/img/logo-mark.png') }}">
    <meta name="theme-color" content="#7F1B1B">
    <meta name="base-path" content="{{ BASE_URI }}">
    <script src="{{ asset_v('/assets/js/base-path.js') }}"></script>
    <link rel="stylesheet" href="{{ asset_v('/assets/css/tokens.css') }}">
    <link rel="stylesheet" href="{{ asset_v('/assets/css/base.css') }}">
    <link rel="stylesheet" href="{{ asset_v('/assets/css/components.css') }}">
    <link rel="stylesheet" href="{{ asset_v('/assets/css/auth.css') }}">
    <link rel="stylesheet" href="{{ asset_v('/assets/css/mps-alert.css') }}">
    @if ($turnstile_site_key !== '')
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js?render=explicit" async defer></script>
    @endif
</head>

<body>

    <div class="auth-screen">
            @include('partials.auth-brand')

        <main class="auth-form-side">
            <div class="auth-form-box" data-etapa-login>
                <div class="auth-form-box-header">
                    <h2>Acessar conta</h2>
                    <p>Informe seus dados de acesso para continuar.</p>
                </div>

                <div class="alert alert-success" data-form-alert-success {!! $redefinida ? '' : 'hidden' !!}>
                    <span class="alert-icon">{!! icon('check') !!}</span>
                    <span>Senha redefinida com sucesso. Faça login com sua nova senha.</span>
                </div>

                <form data-form="login" data-csrf="{{ csrf_token() }}" data-loading-label="Validando..."@if ($turnstile_site_key !== '') data-turnstile="{{ $turnstile_site_key }}"@endif novalidate>
                    <div class="alert alert-danger" data-form-alert hidden></div>

                    <div class="field">
                        <label class="field-label" for="email">E-mail</label>
                        <div class="field-input-wrap">
                            <span class="field-input-icon">{!! icon('envelope-simple') !!}</span>
                            <input class="field-input has-icon" type="email" id="email" name="email" placeholder="seu@email.com"
                                autocomplete="username" required>
                        </div>
                        <span class="field-error" data-error-for="email"></span>
                    </div>

                    <div class="field">
                        <label class="field-label" for="senha">Senha</label>
                        <div class="field-input-wrap">
                            <span class="field-input-icon">{!! icon('key') !!}</span>
                            <input class="field-input has-icon has-icon-both" type="password" id="senha" name="senha" placeholder="••••••••"
                                autocomplete="current-password" required>
                            <button type="button" class="field-password-toggle" data-toggle="password" data-target="#senha"
                                aria-label="Mostrar senha">
                                {!! icon('eye', 'icon-eye') !!}
                                {!! icon('eye-slash', 'icon-eye-slash') !!}
                            </button>
                        </div>
                        <span class="field-error" data-error-for="senha"></span>
                    </div>

                    <div class="auth-form-options">
                        <label class="switch">
                            <input type="checkbox" name="lembrar" value="1">
                            <span class="switch-track"></span>
                            <span class="switch-label">Lembrar de mim</span>
                        </label>
                        <a class="btn-link" href="{{ url('/esqueceu-senha') }}">Esqueceu a senha?</a>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">
                        <span data-label>Entrar</span>
                        <span class="spinner"></span>
                    </button>
                </form>
            </div>

            {{-- Segunda etapa: dois fatores (auth.js — Auth\DoisFatoresController). --}}
            <div class="auth-form-box auth-2fa" data-dois-fatores data-csrf="{{ csrf_token() }}" hidden>
                <div class="auth-form-box-header">
                    <h2 data-2fa-titulo>Confirme seu acesso</h2>
                    <p data-2fa-texto></p>
                </div>
                <div class="alert alert-danger" data-2fa-alerta hidden></div>

                {{-- MPS, primeiro acesso: escolhe o método. --}}
                <div class="auth-2fa-metodos" data-2fa-metodos hidden>
                    <button type="button" class="auth-2fa-metodo" data-2fa-escolher="totp">
                        {!! icon('device-mobile') !!}
                        <span><strong>Google Authenticator</strong><small>Código no aplicativo do celular</small></span>
                    </button>
                    <button type="button" class="auth-2fa-metodo" data-2fa-escolher="email">
                        {!! icon('envelope-simple') !!}
                        <span><strong>Código por e-mail</strong><small>Um código chega no seu e-mail a cada acesso</small></span>
                    </button>
                </div>

                {{-- Primeiro acesso por e-mail: o e-mail que vai receber os códigos. --}}
                <form data-2fa-form-email hidden novalidate>
                    <div class="field">
                        <label class="field-label" for="df-email">E-mail para receber os códigos</label>
                        <div class="field-input-wrap">
                            <span class="field-input-icon">{!! icon('envelope-simple') !!}</span>
                            <input class="field-input has-icon" type="email" id="df-email" name="email" placeholder="seu@email.com" autocomplete="email" required>
                        </div>
                        <span class="field-error" data-2fa-erro="email"></span>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">
                        <span data-label>{!! icon('paper-plane-tilt') !!} Enviar código</span>
                        <span class="spinner"></span>
                    </button>
                </form>

                {{-- Authenticator no primeiro acesso: QR code. --}}
                <div class="auth-2fa-qr" data-2fa-qr hidden>
                    <img alt="QR code para o Google Authenticator" data-2fa-qr-img>
                    <span class="auth-2fa-segredo">Ou digite a chave: <code data-2fa-segredo></code></span>
                </div>

                {{-- O código (do e-mail ou do app). --}}
                <form data-2fa-form-codigo hidden novalidate>
                    <div class="field">
                        <label class="field-label" for="df-codigo">Código de 6 dígitos</label>
                        <div class="field-input-wrap">
                            <span class="field-input-icon">{!! icon('lock') !!}</span>
                            <input class="field-input has-icon auth-2fa-codigo" type="text" id="df-codigo" name="codigo" inputmode="numeric"
                                   autocomplete="one-time-code" maxlength="6" placeholder="000000" required>
                        </div>
                        <span class="field-error" data-2fa-erro="codigo"></span>
                    </div>
                    <button type="submit" class="btn btn-primary btn-block">
                        <span data-label>{!! icon('check') !!} Confirmar</span>
                        <span class="spinner"></span>
                    </button>
                    <button type="button" class="btn btn-secondary btn-block" data-2fa-reenviar hidden>{!! icon('arrows-clockwise') !!} Reenviar código</button>
                </form>

                <button type="button" class="btn-link auth-2fa-voltar" data-2fa-voltar>{!! icon('arrow-left') !!} Voltar para o login</button>
            </div>
        </main>
    </div>

    <script src="{{ asset_v('/assets/js/mpsAlert.js') }}"></script>
    <script src="{{ asset_v('/assets/js/auth.js') }}"></script>
    <script src="{{ asset_v('/assets/js/pwa-install.js') }}"></script>
</body>

</html>