{{-- Painel de exibição (TV) — /painel (PainelController@index). $current_user pode ser null. --}}
<!DOCTYPE html>
<html lang="pt-BR">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Painel de cotações — MPS</title>
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

<div class="painel">

    <header class="painel-topo">
        <div class="painel-topo-marca">
            <span class="painel-topo-icone">{!! icon('monitor') !!}</span>
            <div>
                <h1 class="painel-topo-titulo">Cotações MPS</h1>
                <span class="painel-topo-sub">Acompanhamento em tempo real</span>
            </div>
        </div>
        <div class="painel-topo-info">
            <div class="painel-hoje">
                <span class="painel-hoje-valor" data-painel="hoje">0</span>
                <span class="painel-hoje-label">novas hoje</span>
            </div>
            <div class="painel-relogio">
                <span class="painel-relogio-hora" data-painel="relogio">--:--</span>
                <span class="painel-relogio-data" data-painel="data">—</span>
            </div>
        </div>
    </header>

    <section class="painel-cards">
        @foreach ($cards as $c)
        <div class="painel-card{!! $c['destaque'] ? '' : ' painel-card-secundario' !!}">
            <span class="painel-card-icone">{!! icon($c['icon']) !!}</span>
            <span class="painel-card-valor" data-painel-total="{{ $c['key'] }}">0</span>
            <span class="painel-card-label">{{ $c['label'] }}</span>
        </div>
        @endforeach
    </section>

    <section class="painel-baixo">
        <div class="painel-bloco">
            <h2 class="painel-bloco-titulo">{!! icon('chart-bar') !!} Por produto</h2>
            <table class="painel-tabela">
                <thead>
                    <tr>
                        <th>Produto</th>
                        <th>Pré-análise</th>
                        <th>Aguardando</th>
                        <th>Em análise</th>
                        <th>Aprovadas</th>
                        <th>Hoje</th>
                    </tr>
                </thead>
                <tbody data-painel="produtos">
                    <tr><td colspan="6" class="painel-vazio">Carregando...</td></tr>
                </tbody>
            </table>
        </div>

        <div class="painel-bloco">
            <h2 class="painel-bloco-titulo">{!! icon('list') !!} Últimas cotações</h2>
            <ul class="painel-recentes" data-painel="recentes">
                <li class="painel-vazio">Carregando...</li>
            </ul>
        </div>
    </section>

    <footer class="painel-rodape">
        <span class="painel-rodape-item">{!! icon('arrows-clockwise') !!} Atualizado às <span data-painel="atualizado">--:--:--</span></span>
        <span class="painel-rodape-item painel-rodape-erro" data-painel="erro" hidden>{!! icon('warning') !!} Sem conexão com o servidor</span>
        <button type="button" class="painel-sair" data-painel="testar">{!! icon('bell') !!} Testar aviso</button>
        @if ($current_user)
        <form class="painel-rodape-item" method="POST" action="{{ url('/logout') }}">
            <input type="hidden" name="_csrf" value="{{ csrf_token() }}">
            <span>{{ $current_user->nome ?? 'Painel' }}</span>
            <button type="submit" class="painel-sair">{!! icon('sign-out') !!} Sair</button>
        </form>
        @endif
    </footer>

</div>

<!-- Sobreposição inicial: o navegador só libera som depois de um clique. -->
<div class="painel-inicio" data-painel="inicio">
    <div class="painel-inicio-box">
        <span class="painel-inicio-icone">{!! icon('bell') !!}</span>
        <h2 class="painel-inicio-titulo">Iniciar painel</h2>
        <p class="painel-inicio-texto">Clique para liberar o aviso falado de cotação nova e exibir em tela cheia. Deixe o volume da TV alto.</p>
        <button type="button" class="btn btn-primary btn-block" data-painel="iniciar">
            {!! icon('monitor') !!} Iniciar exibição
        </button>
    </div>
</div>

<script src="{{ asset_v('/assets/js/mpsAlert.js') }}"></script>
<script src="{{ asset_v('/assets/js/painel.js') }}"></script>
</body>
</html>
