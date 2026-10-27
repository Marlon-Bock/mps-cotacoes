{{--
    Card do resultado de uma seguradora (tela da MPS): status, prêmio,
    pendências, erro, reanálise, emissão e PDFs; no "Fazer cotação", a chave
    "Enviar ao cliente" (aprovada ou biometria). Quem aceita/emite é o cliente.
    @include('cotacao._partials.seguradoras.card')  — só dentro do loop do seguradoras-conteudo.

    Espera (do seguradoras-conteudo): $c (linha de api_cotacoes), $slug,
    $ficha, $fiancaId, $is_admin, $fichaEncerrada, $escolhida, $correcoes,
    $podeCorrigir, $jaHouveAprovada, $tentativas, $podeCotar, $etapa e
    $naPropostaAtual (slugs na proposta aguardando).

    $jaHouveAprovada: no antigo o card fazia `$jaHouveAprovada = true` e isso
    voltava pro loop do pai (só o 1º card liberado ganha "Melhor oferta"). Com
    @include não volta — quem atualiza a flag agora é o loop do
    seguradoras-conteudo, logo depois de cada @include (mesma regra da linha abaixo).
--}}
@php
$seguradora  = api_seguradora($slug) ?? ['nome' => $slug, 'sigla' => '??', 'cor' => '#6b7280', 'logo' => null];
$status      = (string) $c['status'];
$recusada    = api_status_recusado($status);
$liberada    = api_status_liberado($status);
$pendencia   = api_pendencia_texto($status);

$emissao          = api_fila_ultimo($fiancaId, 'emissao', $slug);
$emitindo         = api_fila_em_andamento($emissao);
$reanalise        = api_fila_ultimo($fiancaId, 'reanalise', $slug);
$reanalisando     = api_fila_em_andamento($reanalise);
$temApolice       = api_tem_apolice($c);
// Proposta criada mas o aceite não concluiu: "Concluir emissão"
// retoma só o aceite (ver api_emitir_apolice).
$emissaoPendente  = !empty($c['proposta_id']) && !$temApolice;

// A lista vem ordenada por prêmio — a 1ª liberada é a melhor oferta.
$melhor = ($liberada || $status === 'proposta_escolhida') && !$jaHouveAprovada;
@endphp
<div class="seg-card {!! $melhor ? 'seg-card--melhor' : '' !!}">
    @if ($melhor)
    <div class="seg-card-fita">{!! icon('check-circle') !!} Melhor oferta</div>
    @endif

    <div class="seg-card-marca">
        @include('cotacao._partials.seguradoras.logo')
    </div>

    <div class="seg-card-titulo">
        <span class="seg-nome">{{ $seguradora['nome'] }}</span>
        @if ($c['numero_cotacao'])
        <span class="seg-subtitulo">Cotação nº {{ $c['numero_cotacao'] }}</span>
        @endif
        <span class="seg-subtitulo">Titular: {{ (string) $ficha['nome_completo'] }} ({{ api_formatar_documento((string) $ficha['cpf_cnpj']) }})</span>
        @if (!empty($c['solidarios_json']))
        <span class="seg-subtitulo">Solidário: {{ api_solidarios_texto($c['solidarios_json']) }}</span>
        @endif
        <span class="seg-status seg-status--{{ api_status_selo($status) }}">{{ api_status_label($status) }}</span>
    </div>

    @if ($pendencia)
    @include('cotacao._partials.seguradoras.aviso', ['aviso' => ['tipo' => 'alerta', 'texto' => $pendencia]])
    @endif

    @if (isset($correcoes[$slug]))
    @php
        $definicoes = api_campos_corrigiveis();
        // Mostra o dado que foi recusado (o 1º campo do grupo é o principal: CEP, nome...).
        $campoPrincipal = $correcoes[$slug]['campos'][0];
        $valorPrincipal = (string) ($ficha[$campoPrincipal] ?? '');
        if ($campoPrincipal === 'cep') $valorPrincipal = preg_replace('/(\d{5})(\d{3})/', '$1-$2', $valorPrincipal);
        if ($campoPrincipal === 'cpf_cnpj') $valorPrincipal = api_formatar_documento($valorPrincipal);
        if (str_starts_with($campoPrincipal, 'valor_')) $valorPrincipal = 'R$ ' . number_format((float) $valorPrincipal, 2, ',', '.');
        $aviso = [
            'tipo'   => 'erro',
            'titulo' => 'Dado recusado',
            'texto'  => $correcoes[$slug]['mensagem'],
            'extra'  => $valorPrincipal !== '' ? 'Informado: <strong>' . e($valorPrincipal) . '</strong>' : null,
            'acao'   => $podeCorrigir ? '<button type="button" class="btn btn-primary btn-sm btn-corrigir-dados">' . icon('note-pencil') . ' Corrigir dados</button>' : null,
        ];
    @endphp
    @include('cotacao._partials.seguradoras.aviso', ['aviso' => $aviso])
    @elseif ($c['erro'])
    @php
        $codigoErro = $c['erro_codigo'] ?? null;
        $tipoErro   = api_erro_tipo($codigoErro);
        $aviso = [
            'tipo'   => $tipoErro === 'temporario' ? 'alerta' : 'erro',
            'titulo' => match (true) {
                $codigoErro === 'imovel_com_apolice' => 'Imóvel já segurado',
                $codigoErro === 'fora_regras'        => 'Fora das regras da ' . $seguradora['nome'],
                $tipoErro === 'temporario'           => 'Não foi possível cotar agora',
                default                              => 'Dados recusados',
            },
            // fora_regras: a mensagem específica (em português) vem do adapter.
            'texto'  => $codigoErro === 'fora_regras' ? (string) $c['erro'] : api_erro_amigavel($codigoErro, $ficha),
            'dica'   => $is_admin ? (string) $c['erro'] : null,
        ];
        if ($codigoErro === 'imovel_com_apolice') {
            // Frase curta + os dados da apólice que ocupa o imóvel numa linha à parte.
            $aviso['texto'] = 'Esse imóvel já tem seguro fiança ativo na ' . $seguradora['nome'] . '. Não é possível contratar outro.';
            if (preg_match('/\(nº (\d+)(?:, cotação ([\w-]+))?\)/u', (string) $c['erro'], $m)) {
                $aviso['extra'] = 'Apólice <strong>nº ' . e($m[1]) . '</strong>' . (!empty($m[2]) ? ' · Cotação <strong>' . e($m[2]) . '</strong>' : '');
            }
        }
    @endphp
    @include('cotacao._partials.seguradoras.aviso', ['aviso' => $aviso])
    @endif

    @if ($c['premio_total'])
    <div class="seg-card-premio">
        <span class="seg-premio-label">Prêmio total</span>
        <span class="seg-premio-valor">R$ {!! number_format((float) $c['premio_total'], 2, ',', '.') !!}</span>
        @if ((int) $c['parcelas'] > 0)
        <span class="seg-premio-parcela">
            {!! (int) $c['parcelas'] === 1 ? 'À vista' : $c['parcelas'] . 'x de R$ ' . number_format((float) $c['premio_total'] / (int) $c['parcelas'], 2, ',', '.') !!}
        </span>
        @endif
    </div>
    @elseif ($recusada)
    @include('cotacao._partials.seguradoras.aviso', ['aviso' => ['tipo' => 'erro', 'titulo' => 'Cotação recusada', 'texto' => 'A seguradora não aprovou essa cotação.']])
    @endif

    @php
        // Reanálise com outro pretendente: só se a seguradora oferece pela API
        // e não é imóvel já segurado (reanálise não resolve).
        $podeReanalisar = $recusada && api_adapter($slug, 'reanalisar') !== null && ($c['erro_codigo'] ?? null) !== 'imovel_com_apolice';
    @endphp
    @if ($podeReanalisar)
        @if ($reanalisando)
        <div class="seg-processando">
            <span class="seg-processando-spinner" aria-hidden="true"></span>
            <div>
                <span class="seg-processando-titulo">Reanalisando na seguradora...</span>
                <span class="seg-processando-texto">Pode levar alguns minutos — a tela atualiza sozinha.</span>
            </div>
        </div>
        @elseif (($reanalise['status'] ?? '') === 'erro' && $is_admin)
        @include('cotacao._partials.seguradoras.aviso', ['aviso' => ['tipo' => 'erro', 'titulo' => 'A última reanálise falhou', 'texto' => (string) $reanalise['erro']]])
        @endif

        @if ($is_admin && !$fichaEncerrada && !$reanalisando)
        <div class="seg-reanalise">
            <label class="seg-reanalise-label" for="reanalise-nome-{{ $slug }}">Tentar com outro pretendente</label>
            <div class="seg-reanalise-campo seg-reanalise-campo--nome">
                <div class="field-input-wrap">
                    <span class="field-input-icon">{!! icon('user') !!}</span>
                    <input type="text" id="reanalise-nome-{{ $slug }}" class="field-input has-icon" maxlength="100" placeholder="Nome como no documento">
                </div>
            </div>
            <div class="seg-reanalise-campo">
                <div class="field-input-wrap">
                    <span class="field-input-icon">{!! icon('identification-card') !!}</span>
                    <input type="text" id="reanalise-cpf-{{ $slug }}" class="field-input has-icon" data-mask="cpfcnpj" inputmode="numeric" autocomplete="off" placeholder="000.000.000-00">
                </div>
                <button type="button" class="btn btn-primary btn-sm btn-reanalisar" data-seguradora="{{ $slug }}">
                    {!! icon('arrows-clockwise') !!} Reanalisar
                </button>
            </div>
        </div>
        @endif
    @endif

    @if ($c['premio_total'])
    <div class="seg-detalhes">
        @foreach ([
            'Prêmio líquido' => $c['premio_liquido'] ? 'R$ ' . number_format((float) $c['premio_liquido'], 2, ',', '.') : null,
            'IOF'            => $c['iof'] ? 'R$ ' . number_format((float) $c['iof'], 2, ',', '.') : null,
            'Cobertura'      => $c['cobertura'] ? 'R$ ' . number_format((float) $c['cobertura'], 2, ',', '.') : null,
            'Pagamento'      => $c['forma_pagamento'] ?: null,
            'Plano'          => $c['plano'] ?: null,
        ] as $rotulo => $valor)
        @if ($valor !== null)
        <div class="seg-detalhe-item">
            <span class="seg-detalhe-label">{{ $rotulo }}</span>
            <span class="seg-detalhe-valor">{{ $valor }}</span>
        </div>
        @endif
        @endforeach
        @if ($c['vigencia_inicio'] && $c['vigencia_fim'])
        <div class="seg-detalhe-item seg-detalhe-item--wide">
            <span class="seg-detalhe-label">Vigência</span>
            <span class="seg-detalhe-valor">{{ date('d/m/Y', strtotime($c['vigencia_inicio'])) }} a {{ date('d/m/Y', strtotime($c['vigencia_fim'])) }}</span>
        </div>
        @endif
    </div>
    @endif

    @php $historico = $tentativas[$slug] ?? []; @endphp
    @if (count($historico) > 1)
    <div class="seg-historico">
        <span class="seg-detalhe-label">Histórico de tentativas</span>
        @foreach ($historico as $t)
        <div class="seg-historico-linha">
            <span>
                {!! (int) $t['tentativa'] !!}ª tentativa
                <span class="seg-historico-desc">({{ (string) $t['pretendentes'] }})</span>
            </span>
            <span class="seg-historico-status">{{ api_status_label((string) $t['status']) }}</span>
        </div>
        @endforeach
    </div>
    @endif

    @if ($temApolice)
    @php
        // A emissão é assíncrona e a seguradora leva ~1 min pra gerar cada
        // PDF: o botão só aparece com o PDF já guardado aqui (abre na hora);
        // até lá, "Gerando o PDF...". O aviso sai quando ele chega.
        $apoliceEmitida = $c['apolice_status'] === 'emitida';
        $propostaPdf    = !empty($c['proposta_id']) && api_documento_em_cache($slug, 'proposta', (string) $c['proposta_id']);
        $apolicePdf     = $apoliceEmitida && !empty($c['apolice_id']) && api_documento_em_cache($slug, 'apolice', (string) $c['apolice_id']);
    @endphp
    <div class="seg-apolice {!! $apoliceEmitida ? '' : 'seg-apolice--pendente' !!}">
        {!! icon($apoliceEmitida ? 'check-circle' : 'clock') !!}
        <div>
            <span class="seg-apolice-label">{!! $propostaPdf ? 'Proposta pronta' : 'Gerando o PDF da proposta...' !!}</span>
            @if ($apoliceEmitida)
            <span class="seg-apolice-status">Apólice emitida</span>
            @if ($c['apolice_numero'])
            <span class="seg-apolice-aviso">Nº {{ $c['apolice_numero'] }}</span>
            @endif
            @if (!$apolicePdf)
            <span class="seg-apolice-aviso">Gerando o PDF da apólice — avisamos quando ficar pronto.</span>
            @endif
            @else
            <span class="seg-apolice-status">Apólice em emissão</span>
            <span class="seg-apolice-aviso">Avisamos quando ficar pronta.</span>
            @endif
        </div>
    </div>
    @if ($propostaPdf || $apolicePdf)
    <div class="seg-card-rodape seg-documentos">
        @if ($apolicePdf)
        <button type="button" class="btn btn-primary btn-sm btn-documento" data-seguradora="{{ $slug }}" data-tipo="apolice">
            {!! icon('file-pdf') !!} Apólice
        </button>
        @endif
        @if ($propostaPdf)
        <button type="button" class="btn {!! $apolicePdf ? 'btn-secondary' : 'btn-primary' !!} btn-sm btn-documento" data-seguradora="{{ $slug }}" data-tipo="proposta">
            {!! icon('file-pdf') !!} Proposta
        </button>
        @endif
    </div>
    @endif
    @elseif ($fichaEncerrada)
        {{-- Ficha encerrada: sem ações. --}}
    @elseif ($status === 'aguardando_biometria')
    <div class="seg-card-rodape">
        <button type="button" class="btn btn-primary btn-sm btn-link-biometria" data-seguradora="{{ $slug }}">
            {!! icon('fingerprint') !!} Link de biometria
        </button>
    </div>
    @elseif ($emitindo)
    <div class="seg-processando">
        <span class="seg-processando-spinner" aria-hidden="true"></span>
        <div>
            <span class="seg-processando-titulo">Gerando a proposta...</span>
            <span class="seg-processando-texto">Leva cerca de 1 minuto. Avisamos quando ficar pronta.</span>
        </div>
    </div>
    @elseif ($escolhida !== null && $escolhida !== $slug && ($emissaoPendente || $liberada))
    @include('cotacao._partials.seguradoras.aviso', ['aviso' => ['tipo' => 'info', 'texto' => 'Proposta da ' . api_seguradora_nome($escolhida) . ' escolhida para esta cotação.']])
    @elseif (api_emissao_presa($fiancaId, $c))
    @include('cotacao._partials.seguradoras.aviso', ['aviso' => ['tipo' => 'erro', 'titulo' => 'Emissão para finalizar com a seguradora', 'texto' => 'A ' . $seguradora['nome'] . ' registrou a proposta da cotação nº ' . ($c['numero_cotacao'] ?: $c['cotacao_externa_id']) . ', mas não devolveu o número dela (falha de comunicação). Finalize a emissão direto com a seguradora — o cliente vê que a MPS está finalizando.']])
    @elseif ($emissaoPendente || $liberada)
        @if (($emissao['status'] ?? '') === 'erro')
        @php
            // Erro já traduzido (ex: imóvel com apólice ativa) aparece pra
            // todos; o técnico, só pra MPS.
            $erroEmissao = (string) $emissao['erro'];
            $erroTecnico = str_contains($erroEmissao, 'HTTP') || str_contains($erroEmissao, 'Falha ao');
            $aviso = [
                'tipo'   => 'erro',
                'titulo' => 'Não foi possível emitir a apólice',
                'texto'  => !$erroTecnico ? $erroEmissao : ($is_admin ? $erroEmissao : 'Confira os dados do proprietário e tente de novo.'),
            ];
        @endphp
        @include('cotacao._partials.seguradoras.aviso', ['aviso' => $aviso])
        @endif
    @if ($emissaoPendente && api_documento_em_cache($slug, 'proposta', (string) $c['proposta_id']))
    <div class="seg-card-rodape seg-documentos">
        <button type="button" class="btn btn-secondary btn-sm btn-documento" data-seguradora="{{ $slug }}" data-tipo="proposta">
            {!! icon('file-pdf') !!} Proposta
        </button>
    </div>
    @endif
    @endif

    @if ($podeCotar && api_cotacao_ofertavel($c))
    @php $__marcada = $etapa === 'proposta' ? in_array($slug, $naPropostaAtual, true) : true; @endphp
    <label class="switch seg-card-incluir">
        <input type="checkbox" class="seg-incluir" value="{{ $slug }}" data-nome="{{ $seguradora['nome'] }}"{!! $__marcada ? ' checked' : '' !!}>
        <span class="switch-track"></span>
        <span class="switch-label">Enviar ao cliente</span>
    </label>
    @elseif (in_array($slug, $naPropostaAtual, true))
    <span class="seg-card-situacao is-pendente seg-card-na-proposta">{!! icon('paper-plane-tilt') !!} Na proposta enviada ao cliente</span>
    @endif

    @if ($c['atualizado_em'])
    <div class="seg-card-rodape-meta">
        {!! icon('clock') !!} Atualizado em {{ date('d/m/Y \à\s H:i', strtotime($c['atualizado_em'])) }}
    </div>
    @endif
</div>
