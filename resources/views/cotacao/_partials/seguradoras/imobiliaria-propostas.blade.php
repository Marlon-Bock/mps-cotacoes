{{--
    Coluna principal do detalhe da Fiança na API das seguradoras — visão da
    IMOBILIÁRIA, depois que a MPS fez a cotação:
      - propostas aguardando: lista comparativa, uma linha por seguradora
        (prêmio, se pede biometria — com o link pro pretendente) com "Aceitar"
        (abre o modal do proprietário → emissão → 'Aprovado') e "Recusar propostas";
      - proposta aceita: a seguradora escolhida e o que a MPS já enviou — a
        proposta (assim que a seguradora gera) e a apólice (que conclui a cotação).
    @include('cotacao._partials.seguradoras.imobiliaria-propostas', ['ficha' => $cotacao])               (fiança)
    @include('cotacao._partials.seguradoras.imobiliaria-propostas')  com $ficha = $cotacao + ['produto' => 'residencial'] (residencial)

    Espera: $ficha (seguro_fianca/seguro_residencial; 'produto' => 'residencial' troca o modal de aceite),
    $api = ['lote', 'resumo'], $current_user (imob_admin / perm_aceitar).
    O JS é o de imobiliaria-status (cotacao-fianca-seguradoras.js); se a cotação
    já saiu de 'Cotação API'/'Aprovado' este partial carrega o JS ele mesmo.
--}}
@php
$__etapa       = $api['resumo']['etapa'];
$__reais       = static fn($v): string => 'R$ ' . number_format((float) $v, 2, ',', '.');
$__porSlug     = array_column($api['lote']['cotacoes'], null, 'seguradora');
$__podeAceitar = !empty($current_user->imob_admin) || ($current_user->perm_aceitar ?? 'N') === 'S';
$__enviada     = api_apolice_enviada($ficha);
@endphp
@if ($__etapa === 'proposta' && $ficha['status'] === API_STATUS_FICHA)
@php
$__pendentes = array_values(array_filter($api['lote']['propostas'], fn($p) => $p['status'] === 'Aguardando'));
// Uma linha por seguradora, do menor prêmio pro maior (biometria pendente no fim);
// a mais barata disponível ganha o destaque.
$__ofertas = array_map(fn($p) => [
    'proposta'  => $p,
    'cotacao'   => $__porSlug[$p['seguradora']] ?? [],
    'biometria' => (($__porSlug[$p['seguradora']]['status'] ?? '') === 'aguardando_biometria'),
    'premio'    => $__porSlug[$p['seguradora']]['premio_total'] ?? $p['premio_total'],
], $__pendentes);
usort($__ofertas, fn($a, $b) => [$a['biometria'], $a['premio'] === null, (float) $a['premio']] <=> [$b['biometria'], $b['premio'] === null, (float) $b['premio']]);
$__destaque = ($__ofertas[0]['premio'] ?? null) !== null && !$__ofertas[0]['biometria'] ? 0 : null;
@endphp
<div class="panel" id="painel-propostas-api">
    <div class="panel-header seg-ofertas-cabeca">
        <h3 class="panel-title">{!! icon('clipboard-text') !!} Propostas das seguradoras</h3>
        <span class="seg-ofertas-data">Enviadas em {{ date('d/m/Y \à\s H:i', strtotime((string) $__pendentes[0]['enviado_em'])) }}</span>
    </div>
    <div class="panel-body">
        <p class="seg-propostas-intro">Escolha a melhor para o cliente. Ao aceitar, você {!! ($ficha['produto'] ?? '') === 'residencial' ? 'escolhe a forma de pagamento' : 'informa os dados do proprietário' !!}.</p>

        <div class="seg-ofertas">
            @foreach ($__ofertas as $__i => $__o)
            @php
            $__slug     = (string) $__o['proposta']['seguradora'];
            $seguradora = api_seguradora($__slug) ?? ['nome' => $__slug, 'sigla' => '??', 'cor' => '#6b7280', 'logo' => null];
            $__melhor   = $__i === $__destaque;
            @endphp
            <div class="seg-oferta{!! $__melhor ? ' seg-oferta--melhor' : '' !!}">
                <div class="seg-oferta-marca">@include('cotacao._partials.seguradoras.logo')</div>

                <div class="seg-oferta-info">
                    <span class="seg-oferta-nome">{{ $seguradora['nome'] }}</span>
                    <span class="seg-oferta-selos">
                        @if ($__o['biometria'])
                        <span class="seg-status seg-status--pendente">{!! icon('fingerprint') !!} Requer biometria</span>
                        @else
                        <span class="seg-status seg-status--ok">{!! icon('check-circle') !!} Aprovada</span>
                        @endif
                        @if ($__melhor && count($__ofertas) > 1)
                        <span class="seg-oferta-menor">{!! icon('tag') !!} Menor valor</span>
                        @endif
                    </span>
                </div>

                <div class="seg-oferta-planos">
                    @if (!empty($__o['cotacao']['forma_pagamento']) || (int) ($__o['cotacao']['parcelas'] ?? 0) > 0)
                    <span class="seg-oferta-plano-unico">
                        {{ implode(' · ', array_filter([
                            $__o['cotacao']['plano'] ?? null,
                            (int) ($__o['cotacao']['parcelas'] ?? 0) > 1 ? $__o['cotacao']['parcelas'] . 'x de ' . $__reais((float) $__o['premio'] / (int) $__o['cotacao']['parcelas']) : ((int) ($__o['cotacao']['parcelas'] ?? 0) === 1 ? 'À vista' : null),
                        ])) }}
                    </span>
                    @endif
                    @if ($__o['biometria'])
                    <span class="seg-oferta-aviso">O pretendente precisa concluir a biometria facial antes da contratação.</span>
                    <button type="button" class="btn btn-secondary btn-sm btn-link-biometria" data-seguradora="{{ $__slug }}">{!! icon('fingerprint') !!} Copiar link da biometria</button>
                    @endif
                </div>

                <div class="seg-oferta-preco">
                    @if ($__o['premio'] !== null)
                    <span class="seg-oferta-valor">{{ $__reais($__o['premio']) }}</span>
                    <span class="seg-oferta-valor-sub">prêmio total</span>
                    @else
                    <span class="seg-oferta-valor-sub">Valor após a biometria</span>
                    @endif
                </div>

                <div class="seg-oferta-acao">
                    @if (!$__podeAceitar)
                    <span class="seg-oferta-valor-sub">Sem permissão para aceitar</span>
                    @elseif ($__o['biometria'])
                    <button type="button" class="btn btn-secondary btn-sm" disabled>{!! icon('clock') !!} Aguardando biometria</button>
                    @else
                    <button type="button" class="btn {!! $__melhor ? 'btn-primary' : 'btn-secondary' !!} btn-sm btn-escolher-proposta" data-seguradora="{{ $__slug }}">
                        {!! icon('check-circle') !!} Aceitar
                    </button>
                    @endif
                </div>
            </div>
            @endforeach
        </div>

        @if ($__podeAceitar)
        <div class="seg-propostas-rodape">
            <span class="seg-propostas-rodape-texto">Nenhuma serve para o cliente?</span>
            <button type="button" class="btn btn-secondary btn-sm" id="btn-recusar-propostas">{!! icon('x') !!} Recusar propostas</button>
        </div>
        @endif
    </div>
</div>
@if ($__podeAceitar)
@include(($ficha['produto'] ?? '') === 'residencial' ? 'cotacao._partials.seguradoras.modal-pagamento-residencial' : 'cotacao._partials.seguradoras.modal-proprietario')
@endif

@elseif (in_array($__etapa, ['contratada', 'emitida'], true) || $__enviada)
@php
$__aceita   = api_proposta_aceita((int) $ficha['id']);
$__slug     = (string) ($__aceita['seguradora'] ?? api_seguradora_escolhida((int) $ficha['id']) ?? '');
$__cotacao  = $__slug !== '' ? api_cotacao((int) $ficha['id'], $__slug) : null;
$seguradora = api_seguradora($__slug) ?? ['nome' => $__slug, 'sigla' => '??', 'cor' => '#6b7280', 'logo' => null];
$__emissao  = $__slug !== '' ? api_fila_ultimo((int) $ficha['id'], 'emissao', $__slug) : null;
// Emissão falhou e nada foi criado na seguradora: o cliente confere os dados e tenta de novo.
$__falhou   = ($__emissao['status'] ?? '') === 'erro' && empty($__cotacao['apolice_id']) && !$__enviada;
// ...ou a seguradora registrou a proposta sem devolver o número: tentar de novo não resolve, quem finaliza é a MPS.
$__presa    = $__falhou && api_emissao_presa((int) $ficha['id'], $__cotacao);
@endphp
<div class="panel" id="painel-aceita-api">
    <div class="panel-header">
        <h3 class="panel-title">{!! icon('shield-check') !!} Proposta aceita</h3>
    </div>
    <div class="panel-body">
        <div class="seg-ofertas">
            @if ($__cotacao)
            <!-- Mesma linha da lista de propostas: marca · seguradora e detalhes · prêmio. -->
            <div class="seg-oferta seg-oferta--aceita">
                <div class="seg-oferta-marca">@include('cotacao._partials.seguradoras.logo')</div>
                <div class="seg-oferta-info">
                    <span class="seg-oferta-nome">{{ $seguradora['nome'] }}</span>
                    <span class="seg-oferta-detalhes">
                        @foreach (array_filter([
                            !empty($__cotacao['numero_cotacao']) ? 'Cotação nº ' . $__cotacao['numero_cotacao'] : null,
                            !empty($__cotacao['forma_pagamento']) ? $__cotacao['forma_pagamento'] : null,
                            !empty($__aceita['decidido_em']) ? 'Aceita em ' . date('d/m/Y \à\s H:i', strtotime((string) $__aceita['decidido_em'])) : null,
                        ]) as $__d)
                        <span>{{ $__d }}</span>
                        @endforeach
                    </span>
                </div>
                @if (!empty($__cotacao['premio_total']))
                <div class="seg-oferta-preco">
                    <span class="seg-oferta-valor">{{ $__reais($__cotacao['premio_total']) }}</span>
                    <span class="seg-oferta-valor-sub">prêmio total</span>
                </div>
                @endif
            </div>
            @endif

            <!-- Situação: emissão falhou / aguardando a MPS enviar / documentos. -->
            @if ($__presa)
            <div class="seg-aceita-apolice">
                <span class="seg-aceita-apolice-texto">{!! icon('clock') !!} <strong>A MPS está finalizando com a seguradora</strong> — você será avisado quando a apólice estiver disponível.</span>
            </div>
            @elseif ($__falhou)
            <div class="seg-aceita-apolice is-erro">
                <span class="seg-aceita-apolice-texto">{!! icon('warning') !!} <strong>Não foi possível emitir</strong> — {!! ($ficha['produto'] ?? '') === 'residencial' ? 'tente de novo.' : 'confira os dados do proprietário e tente de novo.' !!}</span>
                @if ($__podeAceitar)
                <button type="button" class="btn btn-primary btn-sm btn-escolher-proposta" data-seguradora="{{ $__slug }}">{!! icon('arrows-clockwise') !!} Tentar de novo</button>
                @endif
            </div>
            @elseif ($__enviada)
            <div class="seg-aceita-apolice is-enviada">
                <span class="seg-aceita-apolice-texto">{!! icon('check-circle') !!} Apólice{!! !empty($__cotacao['apolice_numero']) ? ' nº ' . e($__cotacao['apolice_numero']) : '' !!} disponível</span>
                <span class="seg-aceita-apolice-docs">
                    <button type="button" class="btn btn-primary btn-sm btn-documento" data-seguradora="{{ $__slug }}" data-tipo="apolice">{!! icon('file-pdf') !!} Apólice</button>
                    @if (!empty($__cotacao['proposta_id']))
                    <button type="button" class="btn btn-secondary btn-sm btn-documento" data-seguradora="{{ $__slug }}" data-tipo="proposta">{!! icon('file-pdf') !!} Proposta</button>
                    @endif
                </span>
            </div>
            @elseif (api_proposta_enviada($ficha) && !empty($__cotacao['proposta_id']))
            <div class="seg-aceita-apolice is-proposta">
                <span class="seg-aceita-apolice-texto"><span class="seg-processando-spinner" aria-hidden="true"></span> <strong>Proposta disponível</strong> — a apólice está em emissão; a MPS envia quando sair.</span>
                <span class="seg-aceita-apolice-docs">
                    <button type="button" class="btn btn-primary btn-sm btn-documento" data-seguradora="{{ $__slug }}" data-tipo="proposta">{!! icon('file-pdf') !!} Proposta</button>
                </span>
            </div>
            @else
            <div class="seg-aceita-apolice">
                <span class="seg-processando-spinner" aria-hidden="true"></span>
                <span class="seg-aceita-apolice-texto"><strong>Aguardando a proposta e a apólice</strong> — a MPS envia assim que a seguradora gerar.</span>
            </div>
            @endif
        </div>
    </div>
</div>
@if ($__falhou && $__podeAceitar)
@include(($ficha['produto'] ?? '') === 'residencial' ? 'cotacao._partials.seguradoras.modal-pagamento-residencial' : 'cotacao._partials.seguradoras.modal-proprietario')
@endif
@if (!in_array($ficha['status'], [API_STATUS_FICHA, API_STATUS_APROVADO], true))
{{-- Cotação concluída: o painel da direita (imobiliaria-status, que carrega o JS)
     não aparece — os botões dos PDFs precisam do script aqui. --}}
<script src="{{ asset_v('/assets/js/cotacao-fianca-seguradoras.js') }}"></script>
<script>
window.__SEGURADORAS_CONFIG = { protocolo: {!! json_encode($ficha['protocolo']) !!}, csrf: {!! json_encode(csrf_token()) !!} };
</script>
@endif
@endif
