{{-- Esqueci minha senha — /esqueceu-senha --}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Esqueci minha senha — MPS Cotações</title>
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
    @include('partials.auth-brand', ['auth_brand_title' => 'Recupere o acesso à sua conta.', 'auth_brand_text' => 'Vamos enviar um link seguro para o seu e-mail cadastrado.'])

    <main class="auth-form-side">
        <div class="auth-form-box">

            <a class="auth-back-link" href="{{ url('/login') }}">&larr; Voltar para o login</a>

            <div data-state="form">
                <div class="auth-form-box-header">
                    <h2>Esqueceu sua senha?</h2>
                    <p>Informe o e-mail cadastrado e enviaremos um link de redefinição.</p>
                </div>

                <form data-form="esqueceu-senha" data-csrf="{{ csrf_token() }}" data-loading-label="Enviando..." novalidate>
                    <div class="alert alert-danger" data-form-alert hidden></div>

                    <div class="field">
                        <label class="field-label" for="email">E-mail</label>
                        <div class="field-input-wrap">
                            <span class="field-input-icon">{!! icon('envelope-simple') !!}</span>
                            <input
                                class="field-input has-icon"
                                type="email"
                                id="email"
                                name="email"
                                placeholder="seu@email.com"
                                autocomplete="username"
                                required
                            >
                        </div>
                        <span class="field-error" data-error-for="email"></span>
                    </div>

                    <!-- Aparece só quando o e-mail tem mais de uma conta (preenchido por auth.js) -->
                    <div class="field" data-escolha-usuario hidden>
                        <span class="field-label">Qual é a sua conta?</span>
                        <div class="auth-contas-lista" data-escolha-lista></div>
                        <span class="field-error" data-error-for="usuario_id"></span>
                    </div>

                    <button type="submit" class="btn btn-primary btn-block">
                        <span data-label>Enviar link de recuperação</span>
                        <span class="spinner"></span>
                    </button>
                </form>
            </div>

            <div data-state="success" hidden>
                <div class="auth-form-box-header">
                    <h2>Link enviado!</h2>
                    <p>Verifique sua caixa de entrada (e a pasta de spam). O link expira em 15 minutos.</p>
                </div>
                <a class="btn btn-secondary btn-block" href="{{ url('/login') }}">Voltar para o login</a>
            </div>

        </div>
    </main>
</div>

<script src="{{ asset_v('/assets/js/mpsAlert.js') }}"></script>
<script src="{{ asset_v('/assets/js/auth.js') }}"></script>
</body>
</html>
