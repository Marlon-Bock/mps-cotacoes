{{-- Verificação Turnstile (acesso de fora do Brasil) — /verificacao (VerificacaoController: $erro, $site_key) --}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Verificação de segurança — MPS Cotações</title>
    <link rel="icon" href="{{ url('/assets/img/mesquita-prime-e-sami-corretora-de-seguros-favicon.png') }}">
    <meta name="base-path" content="{{ BASE_URI }}">
    <script src="{{ asset_v('/assets/js/base-path.js') }}"></script>
    <link rel="stylesheet" href="{{ asset_v('/assets/css/tokens.css') }}">
    <link rel="stylesheet" href="{{ asset_v('/assets/css/base.css') }}">
    <link rel="stylesheet" href="{{ asset_v('/assets/css/components.css') }}">
    <link rel="stylesheet" href="{{ asset_v('/assets/css/auth.css') }}">
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
</head>
<body>
    <div class="auth-screen">
        @include('partials.auth-brand')

        <main class="auth-form-side">
            <div class="auth-form-box">
                <div class="auth-form-box-header">
                    <h2>Verificação de segurança</h2>
                    <p>Detectamos um acesso de fora do Brasil. Confirme que você não é um robô para continuar.</p>
                </div>

                @if ($erro !== '')
                <div class="alert alert-danger">{{ $erro }}</div>
                @endif

                <form method="POST" action="{{ url('/verificacao') }}" class="verificacao-form">
                    <div class="cf-turnstile" data-sitekey="{{ $site_key }}" data-callback="onTurnstileOk"></div>
                    <button type="submit" class="btn btn-primary btn-block verificacao-btn" id="btn-verificar" disabled>
                        Continuar
                    </button>
                </form>
            </div>
        </main>
    </div>

    <script>
        // Habilita o botão só quando o Turnstile resolver o desafio.
        function onTurnstileOk() {
            var b = document.getElementById('btn-verificar');
            if (b) b.disabled = false;
        }
    </script>
</body>
</html>
