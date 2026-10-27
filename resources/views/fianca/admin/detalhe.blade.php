{{-- Detalhe da cotação de Fiança (MPS) — Fianca\DetalheController@admin. Era pages/admin/cotacao/fianca/detalhe.php. --}}
@extends('layouts.app')

@section('content')
            <!-- Header da cotação -->
            <div class="panel">
                <div class="panel-body detalhe-panel-header">
                    <div class="dph-top">
                        <span class="dph-tipo">{!! icon('house') !!} Fiança {!! $tipo_ficha_lower === 'comercial' ? 'Comercial' : 'Residencial' !!} &middot; Plano {{ $cotacao['tipo_plano'] ?? 'Básico' }}</span>
                        @php
  $__st = canal_status_exibicao((string) ($cotacao['origem'] ?? ''), (string) $status, (string) $protocolo); 
@endphp
                        <span class="status-pill {{ $__st['pill'] }}">{{ $__st['label'] }}</span>
                    </div>
                    <span class="dph-proto">Cotação #{{ $protocolo }}</span>
                    <div class="dph-bar">
                        <span class="dph-bar-item">
                            {!! icon('user') !!}
                            <span class="dph-bar-label">Cliente</span>
                            <span class="dph-bar-value">{{ $cotacao['nome_completo'] ?? '—' }}</span>
                        </span>
                        <span class="dph-bar-sep">&middot;</span>
                        <span class="dph-bar-item">
                            {!! icon('buildings') !!}
                            <span class="dph-bar-label">Imobiliária</span>
                            <span class="dph-bar-value">{{ $cotacao['imob_nome'] ?? '—' }}</span>
                        </span>
                        @if (!empty($cotacao['atendente_nome']))
                        <span class="dph-bar-sep">&middot;</span>
                        <span class="dph-bar-item">
                            {!! icon('users') !!}
                            <span class="dph-bar-label">Atendente MPS</span>
                            <span class="dph-bar-value">{{ $cotacao['atendente_nome'] }}</span>
                        </span>
                        @endif
                        <span class="dph-bar-sep">&middot;</span>
                        <span class="dph-bar-item">
                            {!! icon('clock') !!}
                            <span class="dph-bar-value">{{ $data_atualizacao }}</span>
                        </span>
                        <span class="dph-bar-spacer"></span>
                        <button type="button" class="btn btn-sm btn-chat-cotacao {!! $chat_unread > 0 ? ('btn-danger-outline') : ('btn-secondary') !!}" data-action="abrir-chat-cotacao" data-protocolo="{{ $protocolo }}">
                            {!! icon('chat-circle') !!} Chat da cotação
                            @if ($chat_unread > 0)<span class="count-badge">{!! $chat_unread > 99 ? '99+' : e($chat_unread) !!}</span>@endif
                        </button>
                        <a href="{{ BASE_URI }}/api/cotacao/{{ $protocolo }}/pdf" target="_blank" rel="noopener" class="btn btn-secondary btn-sm btn-pdf">
                            {!! icon('file-pdf') !!} Baixar PDF
                        </a>
                        @if ($__temSeguradoras)
                        <a href="{{ url('/admin/cotacao/fianca/' . $protocolo . '/seguradoras') }}" class="btn btn-secondary btn-sm">
                            {!! icon('buildings') !!} Seguradoras
                        </a>
                        @endif
                    </div>
                </div>
            </div>

            <div class="detalhe-layout cotacao-container">
                <div class="detalhe-left">

                    <div class="panel">
                        <div class="panel-body">

                            <!-- ── Dados do pretendente ──────────────────── -->
                            <div class="form-section">
                                <h3 class="form-section-title">{!! icon('user') !!} Dados do pretendente</h3>
                                <div class="detalhe-fields">
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Nome completo</div>
                                        @php
 dfv(cv($cotacao['nome_completo'])) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">CPF{!! $tipo_ficha_lower === 'comercial' ? '/CNPJ' : '' !!}</div>
                                        @php
 dfv(cv_cpf_cnpj($cotacao['cpf_cnpj'])) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Telefone</div>
                                        @php
 dfv(cv($cotacao['tel_celular'] ?? null)) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">E-mail</div>
                                        @php
 dfv(cv($cotacao['email'] ?? null)) 
@endphp
                                    </div>
                                </div>
                            </div>

                            <!-- ── Imóvel e contrato ─────────────────────── -->
                            <div class="form-section">
                                <h3 class="form-section-title">{!! icon('house') !!} Imóvel e contrato</h3>
                                @php
                                $endereco_full = implode('', array_filter([
                                    $cotacao['endereco'] ?? '',
                                    ($cotacao['numero'] ?? '')      ? ', ' . $cotacao['numero'] : '',
                                    ($cotacao['complemento'] ?? '') ? ' — ' . $cotacao['complemento'] : '',
                                    ($cotacao['bairro'] ?? '')      ? ', ' . $cotacao['bairro'] : '',
                                    ($cotacao['cidade'] ?? '')      ? ' — ' . $cotacao['cidade'] : '',
                                    ($cotacao['uf'] ?? '')          ? '/' . $cotacao['uf'] : '',
                                ]));
                                
@endphp
                                <div class="detalhe-fields">
                                    <div class="detalhe-field span-full">
                                        <div class="detalhe-field-label">Endereço do imóvel</div>
                                        @php
 dfv($endereco_full !== '' ? e($endereco_full) : 'Não preenchido') 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">CEP</div>
                                        @php
 dfv(cv_cep($cotacao['cep'])) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Vigência do contrato</div>
                                        @php
 dfv(($cotacao['vigencia_contrato'] ?? '') !== '' ? cv($cotacao['vigencia_contrato']) . ' meses' : 'Não preenchido') 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Índice de reajuste</div>
                                        @php
 dfv(cv($cotacao['indice_reajuste'])) 
@endphp
                                    </div>
                                </div>
                            </div>

                            <!-- ── Valores financeiros ───────────────────── -->
                            <div class="form-section">
                                <h3 class="form-section-title">{!! icon('currency-dollar') !!} Valores financeiros</h3>
                                <div class="detalhe-fields">
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Aluguel</div>
                                        @php
 dfv(cv_money($cotacao['valor_aluguel'] ?? null)) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Água</div>
                                        @php
 dfv(cv_money($cotacao['valor_agua'] ?? null)) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Condomínio</div>
                                        @php
 dfv(cv_money($cotacao['valor_condominio'] ?? null)) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Gás</div>
                                        @php
 dfv(cv_money($cotacao['valor_gas'] ?? null)) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">IPTU</div>
                                        @php
 dfv(cv_money($cotacao['valor_iptu'] ?? null)) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Energia</div>
                                        @php
 dfv(cv_money($cotacao['valor_energia'] ?? null)) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Total encargos</div>
                                        <div class="detalhe-field-value detalhe-field-value--strong">{!! cv_money($val_encargos) !!}</div>
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Total geral</div>
                                        <div class="detalhe-field-value detalhe-field-value--total">{!! cv_money($val_total) !!}</div>
                                    </div>
                                </div>
                            </div>

                            <!-- ── Coberturas e plano ────────────────────── -->
                            <div class="form-section">
                                <h3 class="form-section-title">{!! icon('check-circle') !!} Coberturas e plano</h3>
                                @php
                                $coberturas_map = [
                                    'danos_imovel'     => 'Danos ao imóvel',
                                    'pintura_interna'  => 'Pintura interna',
                                    'pintura_externa'  => 'Pintura externa',
                                    'multa_contratual' => 'Multa contratual',
                                    'cobertura_moveis' => 'Cobertura de móveis',
                                ];
                                
@endphp
                                <div class="coberturas-badges">
                                    @foreach ($coberturas_map as $key => $label)
                                    @php $ativo = !empty($cotacao[$key]) && (int)$cotacao[$key] === 1; @endphp
                                    <span class="cob-badge {!! $ativo ? 'is-active' : '' !!}">
                                        {!! icon($ativo ? 'check-circle' : 'x') !!} {{ $label }}
                                    </span>
                                    @endforeach
                                </div>
                            </div>

                            <!-- ── Pretendentes adicionais ──────────────── -->
                            @foreach ($pretendentes_adicionais as $pret)
                            <div class="form-section">
                                <h3 class="form-section-title">{!! icon('users') !!} Pretendente adicional {!! (int) $pret['numero_pretendente'] !!}</h3>
                                <div class="detalhe-fields">
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Nome completo</div>
                                        @php
 dfv(cv($pret['nome_completo'])) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">CPF/CNPJ</div>
                                        @php
 dfv(cv_cpf_cnpj($pret['cpf_cnpj'])) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">WhatsApp</div>
                                        @php
 dfv(cv($pret['tel_celular'] ?? null)) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">E-mail</div>
                                        @php
 dfv(cv($pret['email'] ?? null)) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Vínculo empregatício</div>
                                        @php
 dfv(cv($pret['vinculo_empregaticio'] ?? null)) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Salário bruto</div>
                                        @php
 dfv(cv_money($pret['salario_bruto'] ?? null)) 
@endphp
                                    </div>
                                </div>
                            </div>
                            @endforeach

                        </div>
                    </div>

                    @if (!empty($historico))
                    <div class="panel">
                        <div class="panel-header"><h3 class="panel-title">{!! icon('clock') !!} Histórico</h3></div>
                        <div class="panel-body">
                            <div class="status-timeline">
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

                </div><!-- /.detalhe-left -->

                <div class="detalhe-right">

                    @php
                    // API/Segimob: só a análise das seguradoras ('Cotação API'). O resultado
                    // pré-aprova ou recusa sozinho; depois segue o painel de documentos.
                    $__naApi     = ($cotacao['origem'] ?? '') === CANAL_API && !empty($cotacao['api_enviado_em']) && $status === 'Cotação API';
                    $__naSegimob = ($cotacao['origem'] ?? '') === SEGIMOB_ORIGEM && $status === 'Cotação API';

@endphp
                    @if ($__naApi)
                    @include('cotacao._partials.admin-api')

                    @elseif ($__naSegimob)
                    @include('fianca.admin._segimob')

                    @elseif ($preAnalisePendente)
                    <div class="panel" id="painel-pre-analise">
                        <div class="panel-header"><h3 class="panel-title">{!! icon('magnifying-glass') !!} Pré-análise</h3></div>
                        <div class="panel-body">
                            <div class="alert alert-info" style="margin-bottom:var(--space-3)">
                                {!! icon('info') !!} Formulário parcial recebido. Avalie e libere (ou não) a ficha completa.
                            </div>
                            <div style="display:flex;gap:var(--space-2)">
                                <button type="button" class="btn btn-primary" style="flex:1" id="btn-pre-aceitar">{!! icon('check-circle') !!} Aceitar</button>
                                <button type="button" class="btn btn-danger-outline" style="flex:1" id="btn-pre-recusar">{!! icon('x') !!} Recusar</button>
                            </div>
                        </div>
                    </div>

                    @elseif ($status === 'Aguardando preenchimento' && $formulario_completo === 0)
                    <div class="panel"><div class="panel-body">
                        <div class="alert alert-success" style="margin-bottom:0">
                            {!! icon('check-circle') !!} Pré-análise aceita. Aguardando a imobiliária completar o formulário.
                        </div>
                    </div></div>

                    @elseif (in_array($status, ['Pré-análise recusada', 'Pre-analise recusada'], true))
                    <div class="panel"><div class="panel-body">
                        <div class="alert alert-danger" style="margin-bottom:0">
                            {!! icon('x') !!} Pré-análise recusada.
                        </div>
                    </div></div>

                    @elseif ($fichaPronta)
                    <div class="panel">
                        <div class="panel-header"><h3 class="panel-title">{!! icon('clipboard-text') !!} Ficha completa recebida</h3></div>
                        <div class="panel-body">
                            <div class="alert alert-info" style="margin-bottom:var(--space-3)">{!! icon('info') !!} Pronta para iniciar a análise.</div>
                            <button type="button" class="btn btn-primary btn-block" id="btn-iniciar-analise">{!! icon('arrow-right') !!} Iniciar análise</button>
                        </div>
                    </div>

                    @elseif (in_array($status, ['Em Analise', 'Em Análise'], true))
                    <div class="panel">
                        <div class="panel-header"><h3 class="panel-title">{!! icon('clipboard-text') !!} Decisão da análise</h3></div>
                        <div class="panel-body">
                            <div class="alert alert-info" style="margin-bottom:var(--space-3)">
                                {!! icon('info') !!} Pré-aprove para liberar a biometria, os orçamentos, o contrato de locação e a apólice.
                            </div>
                            <button type="button" class="btn btn-primary btn-block" id="btn-pre-aprovar" style="margin-bottom:var(--space-2)">{!! icon('check-circle') !!} Pré-aprovar cotação</button>
                            <button type="button" class="btn btn-danger-outline btn-block" id="btn-recusar-mps">{!! icon('x') !!} Recusar cotação</button>
                        </div>
                    </div>

                    @elseif (fianca_docs_visiveis($status))
                    @include('fianca._partials.documentos')

                    @elseif (cotacao_status_desistencia($status))
                    <div class="panel"><div class="panel-body">
                        <div class="alert alert-desistencia">{!! icon('sign-out') !!} <strong>Cliente desistiu da cotação.</strong></div>
                    </div></div>

                    @elseif (cotacao_status_recusado($status))
                    <div class="panel"><div class="panel-body">
                        <div class="alert alert-danger" style="margin-bottom:0">{!! icon('x') !!} <strong>{!! $status === STATUS_RECUSADO_CLIENTE ? 'Cotação recusada pelo cliente.' : 'Cotação recusada.' !!}</strong></div>
                    </div></div>
                    @endif

                    @if (cotacao_status_encerrado($status) && !fianca_docs_visiveis($status))
                    @include('fianca._partials.documentos')
                    @endif

                    @if (!cotacao_status_encerrado($status))
                    <div class="panel panel-desistencia">
                        <div class="panel-body">
                            <p class="desistencia-texto">Cliente avisou que desistiu? Encerre a cotação para tirá-la da fila de análise.</p>
                            <button type="button" class="btn btn-secondary btn-block" id="btn-desistir">{!! icon('sign-out') !!} Cliente desistiu da cotação</button>
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

    function apiPost(endpoint, body) {
        return fetch(`/api/cotacao/${proto}/${endpoint}`, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: new URLSearchParams(body),
        }).then(r => r.json());
    }
    function handleResp(data, successMsg) {
        if (data.success) { mpsAlert.success(successMsg || data.message); setTimeout(() => location.reload(), 1200); }
        else { mpsAlert.error(data.message || 'Tente novamente.'); }
    }

    const btnPreAceitar = document.getElementById('btn-pre-aceitar');
    if (btnPreAceitar) btnPreAceitar.addEventListener('click', () => {
        apiPost('pre-analise', {resultado: 'aceita'}).then(d => handleResp(d, 'Pré-análise aceita!'));
    });
    const btnPreRecusar = document.getElementById('btn-pre-recusar');
    if (btnPreRecusar) btnPreRecusar.addEventListener('click', async () => {
        const ok = await mpsAlert.confirm({ message: 'Confirma a recusa da pré-análise?' });
        if (!ok) return;
        apiPost('pre-analise', {resultado: 'recusada'}).then(d => handleResp(d, 'Pré-análise recusada.'));
    });

    const btnIniciar = document.getElementById('btn-iniciar-analise');
    if (btnIniciar) btnIniciar.addEventListener('click', async () => {
        const ok = await mpsAlert.confirm({ message: 'Iniciar análise desta cotação?' });
        if (!ok) return;
        apiPost('iniciar-analise', {}).then(d => handleResp(d, 'Análise iniciada!'));
    });

    const btnPreAprovar = document.getElementById('btn-pre-aprovar');
    if (btnPreAprovar) btnPreAprovar.addEventListener('click', async () => {
        const ok = await mpsAlert.confirm({ message: 'Pré-aprovar esta cotação? A biometria e os orçamentos serão liberados.' });
        if (!ok) return;
        apiPost('pre-aprovar', {}).then(d => handleResp(d, 'Cotação pré-aprovada!'));
    });

    const btnRecusarMPS = document.getElementById('btn-recusar-mps');
    if (btnRecusarMPS) btnRecusarMPS.addEventListener('click', async () => {
        const ok = await mpsAlert.confirm({ message: 'Recusar esta cotação?' });
        if (!ok) return;
        apiPost('recusar-mps', {}).then(d => handleResp(d, 'Cotação recusada.'));
    });

    // Cliente recusou os orçamentos e não quer outros: encerra a cotação
    // com status "Recusado pelo cliente" (botão no painel de documentos).
    const btnRecusadoCliente = document.getElementById('btn-recusado-cliente');
    if (btnRecusadoCliente) btnRecusadoCliente.addEventListener('click', async () => {
        const ok = await mpsAlert.confirm({ message: 'Encerrar esta cotação como recusada pelo cliente?' });
        if (!ok) return;
        apiPost('recusar-cliente', {}).then(d => handleResp(d, 'Cotação recusada pelo cliente.'));
    });

    // Cliente desistiu (avisou a MPS direto): encerra a cotação em qualquer etapa.
    const btnDesistir = document.getElementById('btn-desistir');
    if (btnDesistir) btnDesistir.addEventListener('click', async () => {
        const ok = await mpsAlert.confirm({ message: 'Encerrar esta cotação por desistência do cliente? Essa ação não pode ser desfeita.' });
        if (!ok) return;
        apiPost('desistir', {}).then(d => handleResp(d, 'Cotação encerrada por desistência.'));
    });
})();
</script>
@endpush
