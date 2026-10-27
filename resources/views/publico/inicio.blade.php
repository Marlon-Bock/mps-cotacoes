{{--
    Página inicial pública — GET / para quem não está logado (DashboardController@imobiliaria).
    Explica o sistema sem exigir login: o Google pede uma home acessível e com link
    para a Política de Privacidade para verificar o app (integração com o Google Agenda).
--}}
@php
    $produtos = [
        ['icone' => 'house',      'nome' => 'Seguro Fiança',            'texto' => 'Garantia de aluguel residencial e comercial, com análise do inquilino nas seguradoras.'],
        ['icone' => 'house-line', 'nome' => 'Seguro Residencial',       'texto' => 'Proteção do imóvel e dos bens de quem mora nele.'],
        ['icone' => 'fire',       'nome' => 'Seguro Incêndio',          'texto' => 'Cotação para um ou vários imóveis de uma vez.'],
        ['icone' => 'buildings',  'nome' => 'Seguro Condominial',       'texto' => 'Seguro do condomínio, com os dados do prédio e do síndico.'],
        ['icone' => 'car',        'nome' => 'Seguro Auto',              'texto' => 'Cotação para carro, moto ou caminhão.'],
        ['icone' => 'piggy-bank', 'nome' => 'Título de Capitalização',  'texto' => 'Alternativa de garantia locatícia, do pedido ao certificado.'],
    ];
    $passos = [
        ['n' => '1', 'titulo' => 'A imobiliária envia a ficha', 'texto' => 'Pelo sistema ou por um formulário publicado no próprio site da imobiliária.'],
        ['n' => '2', 'titulo' => 'Análise nas seguradoras',     'texto' => 'A ficha segue para as seguradoras parceiras e a equipe da MPS acompanha cada etapa.'],
        ['n' => '3', 'titulo' => 'Proposta e apólice',          'texto' => 'Propostas, aceite, documentos e apólice ficam no mesmo lugar, com histórico completo.'],
    ];
@endphp
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>MPS Cotações — Mesquita Prime e Sami Corretora de Seguros</title>
    <meta name="description" content="Plataforma da Mesquita Prime e Sami Corretora de Seguros para imobiliárias parceiras solicitarem e acompanharem cotações de seguros.">
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
        <a class="legal-marca" href="{{ url('/') }}">
            <img src="{{ url('/assets/img/logo.webp') }}" alt="MPS Cotações" height="34">
        </a>
        <nav class="legal-nav" aria-label="Documentos">
            <a class="legal-nav-link" href="{{ url('/politica-de-privacidade') }}">Política de Privacidade</a>
            <a class="legal-nav-link" href="{{ url('/termos-de-servico') }}">Termos de Serviço</a>
        </nav>
        <a class="btn btn-primary btn-sm legal-entrar" href="{{ url('/login') }}">{!! icon('user') !!} Entrar no sistema</a>
    </div>
</header>

<section class="legal-hero inicio-hero">
    <div class="legal-hero-inner">
        <span class="legal-hero-selo">{!! icon('shield-check') !!} Mesquita Prime e Sami Corretora de Seguros</span>
        <h1 class="legal-hero-titulo inicio-titulo">Cotação de seguros para imobiliárias, do pedido à apólice.</h1>
        <p class="legal-hero-resumo">A <strong>MPS Cotações</strong> é a plataforma em que as imobiliárias parceiras da MPS pedem, acompanham e
            contratam seguros para os seus clientes — com a análise das seguradoras e o atendimento da equipe da MPS no mesmo lugar.</p>
        <div class="inicio-hero-acoes">
            <a class="btn btn-primary inicio-btn-claro" href="{{ url('/login') }}">{!! icon('user') !!} Entrar no sistema</a>
            <a class="btn btn-secondary inicio-btn-contorno" href="{{ url('/politica-de-privacidade') }}">{!! icon('shield-check') !!} Política de Privacidade</a>
        </div>
    </div>
</section>

<main class="inicio-corpo">

    <section class="inicio-bloco">
        <h2 class="inicio-bloco-titulo">Seguros que dá para cotar</h2>
        <p class="inicio-bloco-sub">Um só sistema para todos os produtos que a imobiliária oferece aos seus clientes.</p>
        <div class="inicio-produtos">
            @foreach ($produtos as $p)
            <div class="inicio-produto">
                <span class="inicio-produto-icone">{!! icon($p['icone']) !!}</span>
                <h3>{{ $p['nome'] }}</h3>
                <p>{{ $p['texto'] }}</p>
            </div>
            @endforeach
        </div>
    </section>

    <section class="inicio-bloco">
        <h2 class="inicio-bloco-titulo">Como funciona</h2>
        <ol class="inicio-passos">
            @foreach ($passos as $p)
            <li class="inicio-passo">
                <span class="inicio-passo-num">{{ $p['n'] }}</span>
                <div>
                    <h3>{{ $p['titulo'] }}</h3>
                    <p>{{ $p['texto'] }}</p>
                </div>
            </li>
            @endforeach
        </ol>
    </section>

    <section class="inicio-bloco inicio-google">
        <span class="inicio-google-icone">{!! icon('calendar-blank') !!}</span>
        <div>
            <h2 class="inicio-bloco-titulo">Agenda de visitas com o Google Agenda</h2>
            <p>A equipe comercial da MPS organiza as visitas às imobiliárias pela plataforma. Quem quiser pode conectar a própria conta
                Google para que cada visita agendada apareça automaticamente no Google Agenda. A plataforma usa esse acesso
                <strong>somente para criar, atualizar e excluir os eventos das visitas</strong> — não lê outros compromissos nem
                qualquer outro dado da conta, e a conexão pode ser desfeita a qualquer momento.</p>
            <p><a href="{{ url('/politica-de-privacidade') }}#dados-google">Veja como tratamos os dados do Google na Política de Privacidade.</a></p>
        </div>
    </section>

    <section class="inicio-bloco inicio-acesso">
        {!! icon('lock') !!}
        <p>O acesso é exclusivo das imobiliárias credenciadas pela MPS e da equipe da MPS. Os dados são protegidos conforme a
            <a href="{{ url('/politica-de-privacidade') }}">Política de Privacidade</a> e o uso segue os
            <a href="{{ url('/termos-de-servico') }}">Termos de Serviço</a>.</p>
    </section>

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

</body>
</html>
