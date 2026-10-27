{{-- Redefinir senha — /recuperar-senha/{token} (AuthController@recuperarForm: $token, $token_valido) --}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redefinir senha — MPS Cotações</title>
    <link rel="icon" href="{{ url('/assets/img/mesquita-prime-e-sami-corretora-de-seguros-favicon.png') }}">
    <meta name="base-path" content="{{ BASE_URI }}">
    <script src="{{ asset_v('/assets/js/base-path.js') }}"></script>
    <link rel="stylesheet" href="{{ asset_v('/assets/css/tokens.css') }}">
    <link rel="stylesheet" href="{{ asset_v('/assets/css/base.css') }}">
    <link rel="stylesheet" href="{{ asset_v('/assets/css/components.css') }}">
    <link rel="stylesheet" href="{{ asset_v('/assets/css/auth.css') }}">
    <link rel="stylesheet" href="{{ asset_v('/assets/css/mps-alert.css') }}">
</head>
<body>

<div class="auth-screen">
    @include('partials.auth-brand', ['auth_brand_title' => 'Crie uma nova senha.', 'auth_brand_text' => 'Escolha uma senha forte para manter sua conta protegida.'])

    <main class="auth-form-side">
        <div class="auth-form-box">

            @if (!$token_valido)

                <div class="auth-form-box-header">
                    <h2>Link inválido ou expirado</h2>
                    <p>Esse link de redefinição não é mais válido. Solicite um novo.</p>
                </div>
                <a class="btn btn-primary btn-block" href="{{ url('/esqueceu-senha') }}">Solicitar novo link</a>

            @else

                <div class="auth-form-box-header">
                    <h2>Defina sua nova senha</h2>
                    <p>Use no mínimo 6 caracteres, com letras e números.</p>
                </div>

                <form data-form="recuperar-senha" data-csrf="{{ csrf_token() }}" data-loading-label="Salvando..." novalidate>
                    <input type="hidden" name="token" value="{{ $token }}">
                    <div class="alert alert-danger" data-form-alert hidden></div>

                    <div class="field">
                        <label class="field-label" for="senha">Nova senha</label>
                        <div class="field-input-wrap">
                            <span class="field-input-icon">{!! icon('key') !!}</span>
                            <input
                                class="field-input has-icon has-icon-both"
                                type="password"
                                id="senha"
                                name="senha"
                                placeholder="••••••••"
                                autocomplete="new-password"
                                minlength="6"
                                required
                            >
                            <button type="button" class="field-password-toggle" data-toggle="password" data-target="#senha" aria-label="Mostrar senha">
                                {!! icon('eye', 'icon-eye') !!}
                                {!! icon('eye-slash', 'icon-eye-slash') !!}
                            </button>
                        </div>
                        <span class="field-error" data-error-for="senha"></span>
                    </div>

                    <div class="field">
                        <label class="field-label" for="senha_confirmacao">Confirme a nova senha</label>
                        <div class="field-input-wrap">
                            <span class="field-input-icon">{!! icon('key') !!}</span>
                            <input
                                class="field-input has-icon has-icon-both"
                                type="password"
                                id="senha_confirmacao"
                                name="senha_confirmacao"
                                placeholder="••••••••"
                                autocomplete="new-password"
                                minlength="6"
                                required
                            >
                            <button type="button" class="field-password-toggle" data-toggle="password" data-target="#senha_confirmacao" aria-label="Mostrar confirmação de senha">
                                {!! icon('eye', 'icon-eye') !!}
                                {!! icon('eye-slash', 'icon-eye-slash') !!}
                            </button>
                        </div>
                        <span class="field-error" data-error-for="senha_confirmacao"></span>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">
                        <span data-label>Salvar nova senha</span>
                        <span class="spinner"></span>
                    </button>
                </form>

            @endif

        </div>
    </main>
</div>

<script src="{{ asset_v('/assets/js/mpsAlert.js') }}"></script>
<script src="{{ asset_v('/assets/js/auth.js') }}"></script>
</body>
</html>
