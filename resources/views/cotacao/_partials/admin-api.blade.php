{{--
    Coluna direita do detalhe da Fiança na API das seguradoras — visão da MPS
    (era pages/admin/cotacao/fianca/_api.php; usado também pelo detalhe admin do
    Residencial): andamento da análise e, quando ela termina, o botão "Fazer
    cotação" (tela das seguradoras, onde as propostas são enviadas ao cliente).
    Depois: propostas com o cliente → o cliente aceita ('Aprovado') → a seguradora
    emite a apólice → "Enviar apólice" (conclui). Mesmo visual do dev-cotacoes-3.
    @include('cotacao._partials.admin-api')

    Espera: $cotacao (seguro_fianca / seguro_residencial), $protocolo.
    A ficha está em 'Cotação API' ou 'Aprovado'.
    Variáveis internas ($__lote, $__etapa, $__c...) não voltam pro pai — nenhuma
    página as usava depois do require.
--}}
@php
$__lote       = api_status_lote([(int) $cotacao['id']])[(int) $cotacao['id']];
$__etapa      = api_etapa($__lote['cotacoes'], $__lote['fila'], $__lote['propostas']);
$__urlSeg     = url('/admin/cotacao/' . api_ficha_produto((int) $cotacao['id']) . '/' . $protocolo . '/seguradoras');
$__pendentes  = array_values(array_filter($__lote['propostas'], fn($p) => $p['status'] === 'Aguardando'));
$__pills      = ['ok' => 'status-pill-aprovado', 'recusada' => 'status-pill-recusado', 'pendente' => 'status-pill-pendente'];
$__contratada = in_array($__etapa, ['contratada', 'emitida'], true);

// Um bloco por estado — mesmo formato da tela da imobiliária (ícone + título + texto).
$__estado = static function (string $tipo, string $icone, string $titulo, string $texto): void {
    echo '<div class="seg-processando seg-processando--solto seg-processando--' . e($tipo) . ' seg-mps-estado">'
        . ($icone === 'spinner' ? '<span class="seg-processando-spinner" aria-hidden="true"></span>' : '<span class="seg-processando-icone">' . icon($icone) . '</span>')
        . '<div><span class="seg-processando-titulo">' . e($titulo) . '</span>'
        . ($texto !== '' ? '<span class="seg-processando-texto">' . e($texto) . '</span>' : '')
        . '</div></div>';
};
@endphp
<div class="panel" id="painel-api">
    <div class="panel-header">
        <h3 class="panel-title">{!! icon($__contratada ? 'shield-check' : 'buildings') !!} {!! $__contratada ? 'Proposta aceita' : 'Análise nas seguradoras' !!}</h3>
    </div>
    <div class="panel-body">
        @if (in_array($__etapa, API_ETAPAS_COTAR, true))
        @php
            $__etapa === 'propostas_recusadas'
            ? $__estado('alerta', 'clock', 'Propostas recusadas', 'O cliente recusou as propostas. Envie outras seguradoras ou encerre a cotação.')
            : $__estado('ok', 'check-circle', 'Análise concluída', 'Escolha as seguradoras e envie as propostas ao cliente.'); 
        @endphp
        <a href="{{ $__urlSeg }}" class="btn btn-primary btn-block">{!! icon('paper-plane-tilt') !!} Fazer cotação</a>

        @elseif ($__etapa === 'biometria')
        @php
        $__biometrias    = api_biometrias($__lote['cotacoes']);
        $__faltam        = count(array_filter($__biometrias, 'api_cotacao_biometria_pendente'));
        $__telefone      = preg_replace('/\D/', '', (string) ($cotacao['tel_celular'] ?? ''));
        $__comSeguradora = true;
        $__estado('alerta', 'fingerprint', 'Aguardando biometria',
            ($__faltam > 1 ? $__faltam . ' biometrias pendentes' : 'Biometria pendente') . ' do pretendente. O "Fazer cotação" é liberado quando todas forem concluídas.');
        @endphp
        @include('cotacao._partials.seguradoras.biometria-lista')
        <a href="{{ $__urlSeg }}" class="btn btn-secondary btn-block">{!! icon('buildings') !!} Ver seguradoras</a>
        <script src="{{ asset_v('/assets/js/cotacao-fianca-seguradoras.js') }}"></script>
        <script>window.__SEGURADORAS_CONFIG = { protocolo: {!! json_encode($protocolo) !!}, csrf: {!! json_encode(csrf_token()) !!} };</script>

        @elseif ($__etapa === 'proposta')
        @php $__estado('analise', 'spinner', 'Com o cliente', 'Propostas enviadas em ' . date('d/m/Y \à\s H:i', strtotime((string) $__pendentes[0]['enviado_em'])) . '. Aguardando a escolha.'); @endphp
        <ul class="seg-mps-lista">
            @foreach ($__pendentes as $__p)
            <li>
                <span class="api-painel-cor" style="background:{{ (string) (api_seguradora((string) $__p['seguradora'])['cor'] ?? 'var(--color-gray-400)') }}"></span>
                {{ api_seguradora_nome((string) $__p['seguradora']) }}
                @if ($__p['premio_total'])<strong>{!! cv_money($__p['premio_total']) !!}</strong>@endif
                @if ($__p['biometria'])<span class="status-pill status-pill-pendente">Biometria</span>@endif
            </li>
            @endforeach
        </ul>
        <a href="{{ $__urlSeg }}" class="btn btn-secondary btn-block">{!! icon('arrows-clockwise') !!} Refazer cotação</a>

        @elseif ($__contratada)
        @php
        $__aceita  = api_proposta_aceita((int) $cotacao['id']);
        $__slug    = (string) ($__aceita['seguradora'] ?? api_seguradora_escolhida((int) $cotacao['id']) ?? '');
        $__c       = $__slug !== '' ? api_cotacao((int) $cotacao['id'], $__slug) : null;
        $__emissao = $__slug !== '' ? api_fila_ultimo((int) $cotacao['id'], 'emissao', $__slug) : null;
        @endphp
        <dl class="seg-mps-resumo">
            @foreach (array_filter([
                'Seguradora' => $__slug !== '' ? api_seguradora_nome($__slug) : null,
                'Prêmio'     => !empty($__c['premio_total']) ? cv_money($__c['premio_total']) : null,
                'Pagamento'  => $__c['forma_pagamento'] ?? null,
                'Aceita em'  => !empty($__aceita['decidido_em']) ? date('d/m/Y H:i', strtotime((string) $__aceita['decidido_em'])) : null,
            ]) as $__rotulo => $__valor)
            <div><dt>{{ $__rotulo }}</dt><dd>{{ (string) $__valor }}</dd></div>
            @endforeach
        </dl>

        @php
        $__urlDoc        = fn(string $tipo): string => url('/api/cotacao/fianca/' . $protocolo . '/seguradoras/' . $__slug . '/documento/' . $tipo);
        $__propostaPdf   = !empty($__c['proposta_id']) && api_documento_em_cache($__slug, 'proposta', (string) $__c['proposta_id']);
        $__propostaEnv   = api_proposta_enviada($cotacao);
        $__apoliceEmit   = ($__c['apolice_status'] ?? '') === 'emitida';
        $__apoliceEnv    = api_apolice_enviada($cotacao);
        // Linha de documento: situação + "Ver" (MPS) + "Enviar" (quando pronto e ainda não enviado).
        $__doc = static function (string $rotulo, string $situacao, string $pill, ?string $ver, ?string $enviar) {
            echo '<li class="seg-mps-doc">'
                . '<span class="seg-mps-doc-nome">' . icon('file-pdf') . e($rotulo) . '</span>'
                . '<span class="status-pill ' . e($pill) . '">' . e($situacao) . '</span>'
                . '<span class="seg-mps-doc-acoes">'
                . ($ver ? '<a href="' . e($ver) . '" target="_blank" rel="noopener" class="btn btn-secondary btn-sm">' . icon('eye') . ' Ver</a>' : '')
                . ($enviar ? '<button type="button" class="btn btn-primary btn-sm btn-enviar-documento" data-tipo="' . e($enviar) . '">' . icon('paper-plane-tilt') . ' Enviar</button>' : '')
                . '</span></li>';
        };
        @endphp
        @if (api_fila_em_andamento($__emissao))
        @php $__estado('analise', 'spinner', 'Emitindo na seguradora', 'A seguradora está gerando a proposta e a apólice — leva alguns minutos.'); @endphp
        @elseif (api_emissao_presa((int) $cotacao['id'], $__c))
        @php $__estado('erro', 'warning', 'Finalize com a seguradora', 'A ' . api_seguradora_nome((string) $__c['seguradora']) . ' registrou a proposta da cotação nº ' . ($__c['numero_cotacao'] ?: $__c['cotacao_externa_id']) . ', mas não devolveu o número dela (falha de comunicação). Finalize a emissão direto com a seguradora — o cliente vê que a MPS está finalizando.'); @endphp
        @elseif (($__emissao['status'] ?? '') === 'erro' && empty($__c['apolice_id']))
        @php $__estado('erro', 'warning', 'A emissão falhou', (api_ficha_produto((int) $cotacao['id']) === 'residencial' ? 'Aguardando o cliente tentar de novo. ' : 'Aguardando o cliente conferir os dados do proprietário e tentar de novo. ') . (string) $__emissao['erro']); @endphp
        @else
        <span class="seg-mps-docs-titulo">Documentos para o cliente</span>
        <ul class="seg-mps-docs">
            @php
            $__doc('Proposta',
                $__propostaEnv ? 'Enviada' : ($__propostaPdf ? 'Pronta' : 'Gerando'),
                $__propostaEnv ? 'status-pill-aprovado' : ($__propostaPdf ? 'status-pill-pendente' : 'status-pill-andamento'),
                $__propostaPdf ? $__urlDoc('proposta') : null,
                $__propostaPdf && !$__propostaEnv ? 'proposta' : null);
            $__doc('Apólice' . (!empty($__c['apolice_numero']) ? ' nº ' . $__c['apolice_numero'] : ''),
                $__apoliceEnv ? 'Enviada' : ($__apoliceEmit ? 'Emitida' : 'Em emissão'),
                $__apoliceEnv ? 'status-pill-aprovado' : ($__apoliceEmit ? 'status-pill-pendente' : 'status-pill-andamento'),
                $__apoliceEmit ? $__urlDoc('apolice') : null,
                $__apoliceEmit && !$__apoliceEnv ? 'apolice' : null);
            @endphp
        </ul>
        <p class="seg-mps-docs-nota">A proposta pode ir assim que a seguradora gerar; enviar a apólice conclui a cotação.</p>
        @endif
        <a href="{{ $__urlSeg }}" class="btn btn-secondary btn-block">{!! icon('shield-check') !!} Ver seguradoras</a>
        <script>
        document.querySelectorAll('.btn-enviar-documento').forEach(btn => btn.addEventListener('click', async () => {
            const apolice = btn.dataset.tipo === 'apolice';
            const ok = await mpsAlert.confirm({
                title: apolice ? 'Enviar a apólice?' : 'Enviar a proposta?',
                message: apolice
                    ? 'A imobiliária recebe a apólice (e a proposta, se ainda não recebeu) e a cotação fica como Concluída.'
                    : 'A imobiliária recebe a proposta da seguradora para download. A apólice vai depois, quando sair.',
                confirmText: 'Enviar',
                danger: false,
            });
            if (!ok) return;
            mpsAlert.loading(apolice ? 'Enviando a apólice...' : 'Enviando a proposta...');
            try {
                const resp = await fetch(`/api/cotacao/fianca/{{ rawurlencode($protocolo) }}/seguradoras/${btn.dataset.tipo}/enviar`, {
                    method: 'POST', headers: { 'Content-Type': 'application/json' }, body: '{}',
                });
                const d = await resp.json();
                if (!d.success) return mpsAlert.error(d.message || 'Erro ao enviar.');
                await mpsAlert.success(apolice ? 'Apólice enviada — cotação concluída!' : 'Proposta enviada à imobiliária!');
                window.location.reload();
            } catch (e) {
                mpsAlert.error('Erro na requisição.');
            }
        }));
        </script>

        @elseif ($__etapa === 'corrigir')
        @php $__estado('alerta', 'warning', 'Aguardando a imobiliária corrigir', implode(' ', array_unique(array_column(api_correcoes_pendentes($cotacao, $__lote['cotacoes']), 'mensagem')))); @endphp

        @elseif ($__etapa === 'erro')
        @php $__estado('erro', 'warning', 'Seguradora fora do ar', 'Não foi possível cotar agora. Use "Atualizar" na tela das seguradoras.'); @endphp
        <a href="{{ $__urlSeg }}" class="btn btn-secondary btn-block">{!! icon('arrows-clockwise') !!} Ver seguradoras</a>

        @else
        @php $__estado('analise', 'spinner', 'Em análise', 'O "Fazer cotação" é liberado quando todas as seguradoras responderem.'); @endphp
        <a href="{{ $__urlSeg }}" class="btn btn-secondary btn-block">{!! icon('buildings') !!} Ver seguradoras</a>
        @endif

        @if ($__lote['cotacoes'] && !$__contratada)
        <ul class="seg-mps-lista seg-mps-lista--status">
            @foreach ($__lote['cotacoes'] as $__c)
            @php $__st = (string) $__c['status']; @endphp
            <li>
                <span class="api-painel-cor" style="background:{{ (string) (api_seguradora((string) $__c['seguradora'])['cor'] ?? 'var(--color-gray-400)') }}"></span>
                {{ api_seguradora_nome((string) $__c['seguradora']) }}
                <span class="status-pill {{ $__pills[api_status_selo($__st)] }}">{{ api_status_label($__st) }}</span>
            </li>
            @endforeach
        </ul>
        @endif
    </div>
</div>
