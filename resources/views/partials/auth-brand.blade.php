{{-- Painel de marca das telas de acesso. Opcional: $auth_brand_title, $auth_brand_text --}}
<aside class="auth-brand">
    <div class="auth-brand-logo">
        <img src="{{ url('/assets/img/logo-mark.png') }}" alt="" width="40" height="40">
        <span>MPS Cotações</span>
    </div>
    <div class="auth-brand-message">
        <h1>{{ $auth_brand_title ?? 'Cotações sem complicação.' }}</h1>
        <p>{{ $auth_brand_text ?? 'Gerencie suas cotações de fiança e seguro auto em um único lugar, com acompanhamento em tempo real.' }}</p>
    </div>
    <div class="auth-brand-footer">
        &copy; {!! date('Y') !!} Mesquita Prime e Sami Corretora de Seguros. Todos os direitos reservados.
    </div>
</aside>
