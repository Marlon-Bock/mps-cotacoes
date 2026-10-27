@extends('layouts.app')

@section('content')
            <div class="panel">
                <div class="panel-body detalhe-panel-header">
                    <div class="dph-top">
                        <span class="dph-tipo">{!! icon('house') !!} Seguro Incêndio</span>
                        <span class="status-pill {{ status_pill_class($status) }}">{{ $status }}</span>
                    </div>
                    <span class="dph-proto">Cotação #{{ $protocolo }}</span>
                    <div class="dph-bar">
                        <span class="dph-bar-item">
                            {!! icon('house') !!}
                            <span class="dph-bar-label">Imóveis</span>
                            <span class="dph-bar-value">{!! count($imoveis) !!}</span>
                        </span>
                        <span class="dph-bar-sep">&middot;</span>
                        @if (!empty($cotacao['atendente_nome']))
                        <span class="dph-bar-item">
                            {!! icon('users') !!}
                            <span class="dph-bar-label">Atendente MPS</span>
                            <span class="dph-bar-value">{{ $cotacao['atendente_nome'] }}</span>
                        </span>
                        <span class="dph-bar-sep">&middot;</span>
                        @endif
                        <span class="dph-bar-item">
                            {!! icon('clock') !!}
                            <span class="dph-bar-value">{{ $data_criacao }}</span>
                        </span>
                        <span class="dph-bar-spacer"></span>
                        <button type="button" class="btn btn-sm btn-chat-cotacao {!! $chat_unread > 0 ? 'btn-danger-outline' : 'btn-secondary' !!}" data-action="abrir-chat-cotacao" data-protocolo="{{ $protocolo }}">
                            {!! icon('chat-circle') !!} Chat da cotação
                            @if ($chat_unread > 0)<span class="count-badge">{!! $chat_unread > 99 ? '99+' : e($chat_unread) !!}</span>@endif
                        </button>
                    </div>
                </div>
            </div>

            <div class="detalhe-layout cotacao-container">
                <div class="detalhe-left">

                @foreach ($imoveis as $i => $im)
                @php
                    $im_status         = $im['status'] ?? 'Aguardando análise';
                    $im_is_recusado    = cotacao_status_recusado($im_status);
                    $im_is_desistencia = cotacao_status_desistencia($im_status);
                    $anexos_do_imovel  = $anexos_por_imovel[$im['id']] ?? [];
                    $ultimo_anexo      = $anexos_do_imovel['proposta'] ?? null;
                    $anexo_cotacao     = $anexos_do_imovel['cotacao'] ?? null;
                    $anexo_apolice     = $anexos_do_imovel['apolice'] ?? null;
                    $eh_fluxo_novo     = ($im['fluxo'] ?? 'antigo') === 'novo';
                    $im_resumo = trim(implode(' — ', array_filter([
                        $im['locador'] ?? '',
                        implode('', array_filter([$im['endereco'] ?? '', ($im['numero'] ?? '') ? ', ' . $im['numero'] : ''])),
                    ])));
                @endphp
                <div class="panel" data-imovel-card data-imovel-id="{!! (int) $im['id'] !!}">
                    <div class="panel-header">
                        <h3 class="panel-title" style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">
                            {!! icon('house') !!} Imóvel {!! $i + 1 !!}{!! $im_resumo ? ' — ' . e($im_resumo) : '' !!}
                        </h3>
                        <span class="status-pill {{ status_pill_class($im_status) }}">{{ $im_status }}</span>
                    </div>
                    <div class="panel-body">
                        <div class="detalhe-fields">
                            <div class="detalhe-field">
                                <div class="detalhe-field-label">Proprietário</div>
                                @php dfv(cv($im['locador'])); @endphp
                            </div>
                            <div class="detalhe-field">
                                <div class="detalhe-field-label">CPF/CNPJ do Proprietário</div>
                                @php dfv(cv_cpf_cnpj($im['cpf_cnpj_locador'])); @endphp
                            </div>
                            <div class="detalhe-field">
                                <div class="detalhe-field-label">Inquilino</div>
                                @php dfv(cv($im['locatario'])); @endphp
                            </div>
                            <div class="detalhe-field">
                                <div class="detalhe-field-label">CPF/CNPJ do Inquilino</div>
                                @php dfv(cv_cpf_cnpj($im['cpf_cnpj_locatario'])); @endphp
                            </div>
                            <div class="detalhe-field">
                                <div class="detalhe-field-label">Tipo de imóvel</div>
                                @php dfv(cv($im['tipo_imovel'])); @endphp
                            </div>
                            <div class="detalhe-field">
                                <div class="detalhe-field-label">Atividade exercida</div>
                                @php dfv(cv($im['atividade_imovel'] ?? null)); @endphp
                            </div>
                            @php
                            $end = implode('', array_filter([
                                $im['endereco'] ?? '',
                                ($im['numero'] ?? '') ? ', ' . $im['numero'] : '',
                                ($im['complemento'] ?? '') ? ' - ' . $im['complemento'] : '',
                                ($im['bairro'] ?? '') ? ', ' . $im['bairro'] : '',
                                ($im['cidade'] ?? '') ? ' - ' . $im['cidade'] : '',
                                ($im['uf'] ?? '') ? '/' . $im['uf'] : '',
                            ]));
                            
@endphp
                            <div class="detalhe-field span-full">
                                <div class="detalhe-field-label">Endereço</div>
                                @php dfv(cv($end ?: null)); @endphp
                            </div>
                            <div class="detalhe-field">
                                <div class="detalhe-field-label">CEP</div>
                                @php dfv(cv_cep($im['cep'])); @endphp
                            </div>
                            <div class="detalhe-field">
                                <div class="detalhe-field-label">Metragem</div>
                                {{-- O antigo passava null pro dfv(string) e quebrava (TypeError) com metragem/parcelas vazias. --}}
                                @php dfv($im['metragem'] ? e($im['metragem']) . ' m²' : cv(null)); @endphp
                            </div>
                            <div class="detalhe-field">
                                <div class="detalhe-field-label">Valor do aluguel</div>
                                @php dfv(cv_money($im['valor_aluguel'])); @endphp
                            </div>
                            <div class="detalhe-field">
                                <div class="detalhe-field-label">Parcelas</div>
                                @php dfv($im['parcelas'] ? $im['parcelas'] . 'x' : cv(null)); @endphp
                            </div>
                            <div class="detalhe-field">
                                <div class="detalhe-field-label">Valor do Incêndio</div>
                                @php dfv(cv_money($im['valor_incendio'] ?? null)); @endphp
                            </div>
                        </div>

                        @php
                        $coberturas_incendio_detalhe = [
                            'valor_danos_eletricos'        => 'Danos Elétricos',
                            'valor_impacto_veiculos'       => 'Impacto de Veículos Terrestres e Aéreos',
                            'valor_vendaval'                => 'Vendaval',
                            'valor_perda_aluguel'           => 'Perda de Aluguel',
                            'valor_incendio_conteudo'       => 'Incêndio do conteúdo (inquilino)',
                            'valor_responsabilidade_civil'  => 'Responsabilidade Civil',
                        ];
                        $tem_cobertura_informada = false;
                        foreach ($coberturas_incendio_detalhe as $campo_cob => $rotulo_cob) {
                            if (!empty($im[$campo_cob])) { $tem_cobertura_informada = true; break; }
                        }
                        
@endphp
                        @if ($tem_cobertura_informada)
                        <div class="detalhe-fields" style="margin-top:var(--space-4)">
                            @foreach ($coberturas_incendio_detalhe as $campo_cob => $rotulo_cob)
                            <div class="detalhe-field">
                                <div class="detalhe-field-label">{{ $rotulo_cob }}</div>
                                @php dfv(cv_money($im[$campo_cob] ?? null)); @endphp
                            </div>
                            @endforeach
                        </div>
                        @else
                        <p class="field-hint">A imobiliária não informou os valores de cobertura — a MPS define na análise.</p>
                        @endif

                        @php
                        $im_eh_concluido = in_array($im_status, ['Concluída', 'Concluida', 'Concluído', 'Concluido'], true);
                        $pode_aceitar    = !empty($current_user->imob_admin) || ($current_user->perm_aceitar ?? 'N') === 'S';
                        
@endphp
                        <div class="imovel-acoes">
                            @if ($im_is_desistencia)
                            <div class="imovel-status-final is-desistencia">
                                <span class="imovel-status-final-icon">{!! icon('sign-out') !!}</span>
                                <span><strong>Você desistiu deste imóvel.</strong></span>
                            </div>
                            @elseif ($im_is_recusado)
                            <div class="imovel-status-final is-recusado">
                                <span class="imovel-status-final-icon">{!! icon('x') !!}</span>
                                <span><strong>{!! $im_status === STATUS_RECUSADO_CLIENTE ? 'Recusado pelo cliente.' : 'Recusado pela MPS.' !!}</strong></span>
                            </div>
                            @elseif ($im_eh_concluido)
                            <div class="imovel-status-final is-concluido">
                                <span class="imovel-status-final-icon">{!! icon('check-circle') !!}</span>
                                <span><strong>Proposta aceita! Imóvel concluído.</strong></span>
                            </div>
                            @endif

                            @if ($eh_fluxo_novo && $im_status === 'Aguardando aprovação')
                                @if ($anexo_cotacao && $anexo_cotacao['status_aprovacao'] === 'Recusada')
                                <div class="imovel-acoes-hint is-warning">{!! icon('warning') !!} Você recusou a cotação deste imóvel. Aguardando nova cotação da MPS.</div>
                                @endif
                                <div class="imovel-acoes-row">
                                    @if ($anexo_cotacao && $anexo_cotacao['status_aprovacao'] === 'Aguardando')
                                    <a href="{{ $anexo_cotacao['caminho_arquivo'] }}" target="_blank" class="btn btn-sm btn-secondary" data-acao="revelar-decisao-cotacao">
                                        {!! icon('arrow-right') !!} Ver cotação enviada
                                    </a>
                                    @if ($pode_aceitar)
                                    <button type="button" class="btn btn-sm btn-primary" data-area-decisao data-acao="aceitar-cotacao" hidden>{!! icon('check-circle') !!} Aprovar cotação</button>
                                    @endif
                                    <button type="button" class="btn btn-sm btn-secondary" data-area-decisao data-acao="solicitar-alteracao-cotacao" hidden>{!! icon('note-pencil') !!} Solicitar alteração</button>
                                    @if ($pode_aceitar)
                                    <button type="button" class="btn btn-sm btn-danger-outline" data-area-decisao data-acao="recusar-cotacao" hidden>{!! icon('x') !!} Recusar cotação</button>
                                    @endif
                                    @endif
                                    <button type="button" class="btn btn-sm btn-secondary imovel-acoes-secundaria" data-acao="desistir">
                                        {!! icon('sign-out') !!} Desistir deste imóvel
                                    </button>
                                </div>
                                @if ($anexo_cotacao && $anexo_cotacao['status_aprovacao'] === 'Aguardando')
                                <div class="imovel-acoes-hint">{!! icon('info') !!} Para solicitar alteração, aprovar ou recusar a cotação, clique no botão "Ver cotação enviada" acima para liberar as opções.</div>
                                @endif
                            @elseif ($eh_fluxo_novo && $im_status === 'Aguardando nova cotação')
                            <div class="imovel-acoes-hint">{!! icon('info') !!} Você solicitou alteração na cotação. Aguardando nova cotação da MPS.</div>
                            @elseif ($eh_fluxo_novo && $im_status === 'Cotação aprovada')
                            <div class="imovel-acoes-hint">{!! icon('check-circle') !!} Cotação aprovada. Aguardando a MPS enviar a proposta.</div>
                            @elseif ($eh_fluxo_novo && $im_status === 'Aguardando apólice')
                            <div class="imovel-acoes-hint">{!! icon('info') !!} Proposta enviada. Aguardando a emissão da apólice.</div>
                            @endif


                            @if ($im_status === 'Aprovado' && $ultimo_anexo && $ultimo_anexo['status_aprovacao'] === 'Recusada')
                            <div class="imovel-acoes-hint is-warning">{!! icon('warning') !!} Você recusou a proposta anterior. Aguardando nova proposta da MPS.</div>
                            @elseif ($im_status === 'Alteração solicitada')
                            <div class="imovel-acoes-hint">{!! icon('info') !!} Você solicitou alteração nesta proposta. Aguardando nova proposta da MPS.</div>
                            @endif

                            @if (!($eh_fluxo_novo && $im_status === 'Aguardando aprovação'))
                            <div class="imovel-acoes-row" style="margin-top:var(--space-3)">
                                @if ($im_status === 'Aprovado' && $ultimo_anexo && $ultimo_anexo['status_aprovacao'] === 'Aguardando')
                                <a href="{{ $ultimo_anexo['caminho_arquivo'] }}" target="_blank" class="btn btn-sm btn-secondary" data-acao="revelar-decisao">
                                    {!! icon('arrow-right') !!} Ver proposta enviada
                                </a>
                                @if ($pode_aceitar)
                                <button type="button" class="btn btn-sm btn-primary" data-area-decisao data-acao="aceitar-proposta" hidden>{!! icon('check-circle') !!} Aceitar proposta</button>
                                @endif
                                <button type="button" class="btn btn-sm btn-secondary" data-area-decisao data-acao="solicitar-alteracao-proposta" hidden>{!! icon('note-pencil') !!} Solicitar alteração</button>
                                @if ($pode_aceitar)
                                <button type="button" class="btn btn-sm btn-danger-outline" data-area-decisao data-acao="recusar-proposta" hidden>{!! icon('x') !!} Recusar proposta</button>
                                @endif
                                @endif

                                @php $__docs_imovel = array_filter(['Cotação enviada' => $anexo_cotacao, 'Proposta enviada' => $ultimo_anexo, 'Apólice emitida' => $anexo_apolice]); @endphp
                                @if ($__docs_imovel && ($im_eh_concluido || ($eh_fluxo_novo && in_array($im_status, ['Cotação aprovada', 'Aguardando apólice'], true))))
                                @foreach ($__docs_imovel as $__rotulo => $__doc)
                                <a href="{{ $__doc['caminho_arquivo'] }}" target="_blank" class="btn btn-sm btn-secondary">{!! icon('arrow-right') !!} {{ $__rotulo }}</a>
                                @endforeach
                                @endif

                                @if (!cotacao_status_encerrado($im_status))
                                <button type="button" class="btn btn-sm btn-secondary imovel-acoes-secundaria" data-acao="desistir">
                                    {!! icon('sign-out') !!} Desistir deste imóvel
                                </button>
                                @endif
                            </div>
                            @if ($im_status === 'Aprovado' && $ultimo_anexo && $ultimo_anexo['status_aprovacao'] === 'Aguardando')
                            <div class="imovel-acoes-hint">{!! icon('info') !!} Para solicitar alteração, aceitar ou recusar a proposta, clique no botão "Ver proposta enviada" acima para liberar as opções.</div>
                            @endif
                            @endif
                        </div>
                    </div><!-- /.panel-body -->
                </div><!-- /.panel -->
                @endforeach

                </div><!-- /.detalhe-left -->

                <div class="detalhe-right">
                    @include('cotacao._partials.documentos-incendio')
                    @if (!empty($historico))
                    <div class="panel">
                        <div class="panel-header"><h3 class="panel-title">{!! icon('clock') !!} Histórico</h3></div>
                        <div class="panel-body">
                            <div class="status-timeline" style="max-height:420px;overflow-y:auto;padding-right:var(--space-2)">
                                @foreach ($historico as $h)
                                <div class="timeline-item is-done">
                                    <div class="timeline-dot"></div>
                                    <div class="timeline-content">
                                        <span class="timeline-label">
                                            @if ($h['status_anterior'] && $h['status_novo'] && $h['status_anterior'] !== $h['status_novo'])
                                                <strong>{{ $h['status_anterior'] }}</strong> &rarr; <strong>{{ $h['status_novo'] }}</strong>
                                            @elseif ($h['status_novo'])
                                                <strong>{{ $h['status_novo'] }}</strong>
                                            @endif
                                        </span>
                                        <span class="timeline-meta">{{ $h['usuario_nome'] ?? 'MPS' }} &middot; {!! date('d/m/Y H:i', strtotime($h['data_evento'])) !!}</span>
                                        @if ($h['observacao'])
                                        <span class="timeline-obs">{{ $h['observacao'] }}</span>
                                        @endif
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                    @endif
                </div><!-- /.detalhe-right -->

            </div><!-- /.detalhe-layout -->

        
@endsection

@push('antes-scripts')
<script>
(function () {
    const proto = {!! json_encode($protocolo) !!};

    function handleResp(data, successMsg) {
        if (data.success) { mpsAlert.success(successMsg || data.message); setTimeout(() => location.reload(), 1200); }
        else { mpsAlert.error(data.message || 'Tente novamente.'); }
    }

    function apiPost(imovelId, endpoint, body) {
        return fetch(`/api/cotacao/incendio/${proto}/imovel/${imovelId}/${endpoint}`, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams(body),
        }).then(r => r.json());
    }

    document.querySelectorAll('[data-imovel-card]').forEach(card => {
        const imovelId = card.dataset.imovelId;

        card.querySelector('[data-acao="revelar-decisao"]')?.addEventListener('click', () => {
            card.querySelectorAll('[data-area-decisao]').forEach(el => { el.hidden = false; });
        });
        card.querySelector('[data-acao="revelar-decisao-cotacao"]')?.addEventListener('click', () => {
            card.querySelectorAll('[data-area-decisao]').forEach(el => { el.hidden = false; });
        });

        card.querySelector('[data-acao="aceitar-cotacao"]')?.addEventListener('click', async () => {
            const ok = await mpsAlert.confirm({ message: 'Confirmar aprovação da cotação deste imóvel?' });
            if (!ok) return;
            apiPost(imovelId, 'cotacao-aprovar', {}).then(d => handleResp(d, 'Cotação aprovada!'));
        });

        card.querySelector('[data-acao="recusar-cotacao"]')?.addEventListener('click', async () => {
            const ok = await mpsAlert.confirm({ message: 'Confirmar recusa da cotação deste imóvel?' });
            if (!ok) return;
            apiPost(imovelId, 'cotacao-recusar', {}).then(d => handleResp(d, 'Cotação recusada. A MPS será notificada.'));
        });

        card.querySelector('[data-acao="solicitar-alteracao-cotacao"]')?.addEventListener('click', async () => {
            const mensagem = await mpsAlert.prompt({
                title: 'Solicitar alteração',
                message: 'Descreva o que precisa ser alterado na cotação (valores, coberturas, etc.):',
                placeholder: 'Ex: o valor do incêndio ficou acima do esperado, poderiam revisar?',
                confirmText: 'Enviar pedido',
                icon: 'info',
                multiline: true,
                required: true,
            });
            if (!mensagem || !mensagem.trim()) return;
            apiPost(imovelId, 'alteracao-solicitar', { mensagem: mensagem.trim() })
                .then(d => handleResp(d, 'Pedido de alteração enviado. A MPS vai enviar uma nova cotação.'));
        });

        card.querySelector('[data-acao="aceitar-proposta"]')?.addEventListener('click', async () => {
            const ok = await mpsAlert.confirm({ message: 'Confirmar aceitação da proposta deste imóvel?' });
            if (!ok) return;
            apiPost(imovelId, 'aprovar', {}).then(d => handleResp(d, 'Proposta aceita! Imóvel concluído.'));
        });

        card.querySelector('[data-acao="recusar-proposta"]')?.addEventListener('click', async () => {
            const ok = await mpsAlert.confirm({ message: 'Confirmar recusa da proposta deste imóvel?' });
            if (!ok) return;
            apiPost(imovelId, 'recusar', {}).then(d => handleResp(d, 'Proposta recusada. A MPS será notificada.'));
        });

        card.querySelector('[data-acao="solicitar-alteracao-proposta"]')?.addEventListener('click', async () => {
            const mensagem = await mpsAlert.prompt({
                title: 'Solicitar alteração',
                message: 'Descreva o que precisa ser alterado na proposta (valores, coberturas, etc.):',
                placeholder: 'Ex: o valor ficou acima do combinado, poderiam revisar?',
                confirmText: 'Enviar pedido',
                icon: 'info',
                multiline: true,
                required: true,
            });
            if (!mensagem || !mensagem.trim()) return;
            apiPost(imovelId, 'proposta-alteracao-solicitar', { mensagem: mensagem.trim() })
                .then(d => handleResp(d, 'Pedido de alteração enviado. A MPS vai enviar uma nova proposta.'));
        });

        card.querySelector('[data-acao="desistir"]')?.addEventListener('click', async () => {
            const ok = await mpsAlert.confirm({ message: 'Desistir deste imóvel? Essa ação não pode ser desfeita.' });
            if (!ok) return;
            fetch(`/api/cotacao/incendio/${proto}/imovel/${imovelId}/desistir`, {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: new URLSearchParams({}),
            }).then(r => r.json()).then(d => handleResp(d, 'Imóvel encerrado por desistência.'));
        });
    });
})();
</script>
@endpush
