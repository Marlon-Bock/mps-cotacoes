{{--
    Layout das páginas legais (Política de Privacidade e Termos de Serviço).
    Espera:
      $titulo       título do documento
      $resumo       parágrafo curto do cabeçalho
      $atualizacao  "outubro de 2026"
      $atual        'privacidade' | 'termos' (link ativo no topo)
      $secoes       [['id' => 'ancora', 'titulo' => '...', 'html' => '<p>...</p>'], ...]
--}}
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $titulo }} — MPS Cotações</title>
    <link rel="icon" href="{{ url('/assets/img/mesquita-prime-e-sami-corretora-de-seguros-favicon.png') }}">
    <meta name="base-path" content="{{ BASE_URI }}">
    <script src="{{ asset_v('/assets/js/base-path.js') }}"></script>
    <link rel="stylesheet" href="{{ asset_v('/assets/css/tokens.css') }}">
    <link rel="stylesheet" href="{{ asset_v('/assets/css/base.css') }}">
    <link rel="stylesheet" href="{{ asset_v('/assets/css/components.css') }}">
    <link rel="stylesheet" href="{{ asset_v('/assets/css/legal.css') }}">
</head>
<body class="legal-body">

<header class="legal-topo">
    <div class="legal-topo-inner">
        <a class="legal-marca" href="{{ url('/login') }}">
            <img src="{{ url('/assets/img/logo.webp') }}" alt="MPS Cotações" height="34">
        </a>
        <nav class="legal-nav" aria-label="Documentos">
            <a class="legal-nav-link {{ $atual === 'privacidade' ? 'is-ativo' : '' }}" href="{{ url('/politica-de-privacidade') }}">Política de Privacidade</a>
            <a class="legal-nav-link {{ $atual === 'termos' ? 'is-ativo' : '' }}" href="{{ url('/termos-de-servico') }}">Termos de Serviço</a>
        </nav>
        <a class="btn btn-primary btn-sm legal-entrar" href="{{ url('/login') }}">{!! icon('user') !!} Entrar no sistema</a>
    </div>
</header>

<section class="legal-hero">
    <div class="legal-hero-inner">
        <span class="legal-hero-selo">{!! icon($atual === 'privacidade' ? 'shield-check' : 'file-text') !!} Documento legal · MPS Cotações</span>
        <h1 class="legal-hero-titulo">{{ $titulo }}</h1>
        <p class="legal-hero-resumo">{{ $resumo }}</p>
        <p class="legal-hero-data">{!! icon('clock') !!} Última atualização: {{ $atualizacao }}</p>
    </div>
</section>

<main class="legal-corpo">
    <aside class="legal-indice">
        <details class="legal-indice-box" open>
            <summary class="legal-indice-titulo">Nesta página</summary>
            <ol class="legal-indice-lista">
                @foreach ($secoes as $i => $s)
                <li><a href="#{{ $s['id'] }}" data-indice="{{ $s['id'] }}"><span class="legal-indice-num">{{ $i + 1 }}</span>{{ $s['titulo'] }}</a></li>
                @endforeach
            </ol>
        </details>
    </aside>

    <article class="legal-conteudo">
        @foreach ($secoes as $i => $s)
        <section class="legal-secao" id="{{ $s['id'] }}">
            <h2 class="legal-secao-titulo"><span class="legal-secao-num">{{ str_pad((string) ($i + 1), 2, '0', STR_PAD_LEFT) }}</span>{{ $s['titulo'] }}</h2>
            <div class="legal-texto">{!! $s['html'] !!}</div>
        </section>
        @endforeach

        <div class="legal-contato">
            <span class="legal-contato-icone">{!! icon('chat-circle') !!}</span>
            <div>
                <h3>Ficou alguma dúvida?</h3>
                <p>Fale com o encarregado de dados da MPS pelo e-mail <a href="mailto:privacidade@mesquitaprime.com.br">privacidade@mesquitaprime.com.br</a>.</p>
            </div>
        </div>
    </article>
</main>

<footer class="legal-rodape">
    <div class="legal-rodape-inner">
        <span>© {{ date('Y') }} Mesquita Prime e Sami Corretora de Seguros</span>
        <nav>
            <a href="{{ url('/politica-de-privacidade') }}">Política de Privacidade</a>
            <a href="{{ url('/termos-de-servico') }}">Termos de Serviço</a>
            <a href="{{ url('/login') }}">Entrar</a>
        </nav>
    </div>
</footer>

<script src="{{ asset_v('/assets/js/legal.js') }}"></script>
</body>
</html>
