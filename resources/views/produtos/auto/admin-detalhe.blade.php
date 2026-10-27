{{-- Detalhe da cotação (auto) — MPS — AutoController@adminDetalhe. --}}
@extends('layouts.app')

@section('content')
            <!-- Header da cotação -->
            <div class="panel">
                <div class="panel-body detalhe-panel-header">
                    <div class="dph-top">
                        <span class="dph-tipo">{!! icon('car') !!} Seguro Auto</span>
                        <span class="status-pill {{ status_pill_class($status) }}">{{ $status }}</span>
                    </div>
                    <span class="dph-proto">Cotação #{{ $protocolo }}</span>
                    <div class="dph-bar">
                        @if (!empty($cotacao['segurado_nome']))
                        <span class="dph-bar-item">
                            {!! icon('user') !!}
                            <span class="dph-bar-label">Segurado</span>
                            <span class="dph-bar-value">{{ $cotacao['segurado_nome'] }}</span>
                        </span>
                        <span class="dph-bar-sep">&middot;</span>
                        @endif
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
                            <span class="dph-bar-value">{{ $data_criacao }}</span>
                        </span>
                        <span class="dph-bar-spacer"></span>
                        <button type="button" class="btn btn-sm btn-chat-cotacao {!! $chat_unread > 0 ? ('btn-danger-outline') : ('btn-secondary') !!}" data-action="abrir-chat-cotacao" data-protocolo="{{ $protocolo }}">
                            {!! icon('chat-circle') !!} Chat da cotação
                            @if ($chat_unread > 0)<span class="count-badge">{!! $chat_unread > 99 ? '99+' : e($chat_unread) !!}</span>@endif
                        </button>
                        <button onclick="window.print()" class="btn btn-secondary btn-sm btn-pdf">
                            {!! icon('file-pdf') !!} Baixar PDF
                        </button>
                    </div>
                </div>
            </div>

            <div class="detalhe-layout cotacao-container">
                <div class="detalhe-left">

                    <div class="panel">
                        <div class="panel-body">

                            <!-- Dados do veículo -->
                            <div class="form-section">
                                <h3 class="form-section-title">{!! icon('car') !!} Dados do veículo</h3>
                                <div class="detalhe-fields">
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Tipo de veículo</div>
                                        @php
 dfv(cv($cotacao['tipo_veiculo'])) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Marca</div>
                                        @php
 dfv(cv($cotacao['marca_nome'])) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Modelo</div>
                                        @php
 dfv(cv($cotacao['modelo_nome'])) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Ano fabricação</div>
                                        @php
 dfv(cv($cotacao['ano_fabricacao'] ?? $cotacao['ano_nome'] ?? null)) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Ano modelo</div>
                                        @php
 dfv(cv($cotacao['ano_modelo'] ?? null)) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Combustível</div>
                                        @php
 dfv(cv($cotacao['combustivel'])) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Placa</div>
                                        @php
 dfv(cv($cotacao['placa'])) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Chassi</div>
                                        @php
 dfv(cv($cotacao['chassi'])) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Kit gás</div>
                                        @php
 dfv(cv_sim_nao($cotacao['kit_gas'] ?? null)) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Tipo de seguro</div>
                                        @php
                                        $tipo_seguro_label = (int)($cotacao['renovacao_cia'] ?? 0) === 1
                                            ? 'Renovação'
                                            : ((int)($cotacao['seguro_novo'] ?? 0) === 1 ? 'Seguro novo' : null);
                                        dfv(cv($tipo_seguro_label));
                                        
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Utilizado a trabalho</div>
                                        @php
 dfv(cv_sim_nao($cotacao['utilizado_trabalho'] ?? null)) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Utilizado para ir ao trabalho</div>
                                        @php
 dfv(cv_sim_nao($cotacao['utilizado_ir_trabalho'] ?? null)) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">KM distância trabalho</div>
                                        @php
 dfv(cv($cotacao['km_distancia_trabalho'])) 
@endphp
                                    </div>
                                </div>
                            </div>

                            <!-- Dados do segurado -->
                            <div class="form-section">
                                <h3 class="form-section-title">{!! icon('user') !!} Dados do segurado</h3>
                                <div class="detalhe-fields">
                                    <div class="detalhe-field span-full">
                                        <div class="detalhe-field-label">Nome</div>
                                        @php
 dfv(cv($cotacao['segurado_nome'])) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">CPF</div>
                                        @php
 dfv(cv_cpf_cnpj($cotacao['segurado_cpf'])) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Data de nascimento</div>
                                        @php
 dfv(cv_data($cotacao['segurado_data_nasc'] ?? null)) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Estado civil</div>
                                        @php
 dfv(cv($cotacao['segurado_estado_civil'])) 
@endphp
                                    </div>
                                    @php
                                    $seg_end = implode('', array_filter([
                                        $cotacao['segurado_endereco'] ?? '',
                                        ($cotacao['segurado_numero'] ?? '') ? ', ' . $cotacao['segurado_numero'] : '',
                                        ($cotacao['segurado_complemento'] ?? '') ? ' — ' . $cotacao['segurado_complemento'] : '',
                                        ($cotacao['segurado_bairro'] ?? '') ? ', ' . $cotacao['segurado_bairro'] : '',
                                        ($cotacao['segurado_cidade'] ?? '') ? ' — ' . $cotacao['segurado_cidade'] : '',
                                        ($cotacao['segurado_uf'] ?? '') ? '/' . $cotacao['segurado_uf'] : '',
                                    ]));
                                    
@endphp
                                    <div class="detalhe-field span-full">
                                        <div class="detalhe-field-label">Endereço</div>
                                        @php
 dfv($seg_end !== '' ? e($seg_end) : 'Não preenchido') 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">CEP</div>
                                        @php
 dfv(cv_cep($cotacao['segurado_cep'])) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Garagem residência</div>
                                        @php
 dfv(cv_sim_nao($cotacao['garagem_residencia'] ?? null)) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Portão residência</div>
                                        @php
 dfv(cv_sim_nao($cotacao['portao_residencia'] ?? null)) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Garagem trabalho</div>
                                        @php
 dfv(cv_sim_nao($cotacao['garagem_trabalho'] ?? null)) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Portão trabalho</div>
                                        @php
 dfv(cv_sim_nao($cotacao['portao_trabalho'] ?? null)) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Garagem faculdade</div>
                                        @php
 dfv(cv_sim_nao($cotacao['garagem_faculdade'] ?? null)) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Portão faculdade</div>
                                        @php
 dfv(cv_sim_nao($cotacao['portao_faculdade'] ?? null)) 
@endphp
                                    </div>
                                </div>
                            </div>

                            <!-- Dados do condutor -->
                            <div class="form-section">
                                <h3 class="form-section-title">{!! icon('identification-card') !!} Dados do condutor</h3>
                                <div class="detalhe-fields">
                                    <div class="detalhe-field span-full">
                                        <div class="detalhe-field-label">Nome</div>
                                        @php
 dfv(cv($cotacao['condutor_nome'])) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">CPF</div>
                                        @php
 dfv(cv_cpf_cnpj($cotacao['condutor_cpf'])) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Data de nascimento</div>
                                        @php
 dfv(cv_data($cotacao['condutor_data_nasc'] ?? null)) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Estado civil</div>
                                        @php
 dfv(cv($cotacao['condutor_estado_civil'])) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Relação com segurado</div>
                                        @php
 dfv(cv($cotacao['condutor_relacao_segurado'])) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Condutor menor de 25</div>
                                        @php
 dfv(cv($cotacao['condutor_menor_25'])) 
@endphp
                                    </div>
                                    @if (!empty($cotacao['condutor_menor_25_sexo']))
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Sexo (menor de 25)</div>
                                        @php
 dfv(cv($cotacao['condutor_menor_25_sexo'])) 
@endphp
                                    </div>
                                    @endif
                                </div>
                            </div>

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
                                        <span class="timeline-meta">{{ $h['usuario_nome'] ?? '—' }} · {!! date('d/m/Y H:i', strtotime($h['data_evento'])) !!}</span>
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

                    @if ($fichaPronta)
                    <div class="panel">
                        <div class="panel-header"><h3 class="panel-title">{!! icon('clipboard-text') !!} Ficha recebida</h3></div>
                        <div class="panel-body">
                            <div class="alert alert-info" style="margin-bottom:var(--space-3)">{!! icon('info') !!} Pronta para iniciar a análise.</div>
                            <button type="button" class="btn btn-primary btn-block" id="btn-iniciar-analise">{!! icon('arrow-right') !!} Iniciar análise</button>
                        </div>
                    </div>

                    @elseif (str_contains($status_lower, 'em an'))
                    <div class="panel" id="painel-proposta">
                        <div class="panel-header"><h3 class="panel-title">{!! icon('clipboard-text') !!} Decisão da cotação</h3></div>
                        <div class="panel-body">
                            <div class="alert alert-info" style="margin-bottom:var(--space-3)">
                                {!! icon('info') !!} Aprove para enviar a proposta ou recuse a cotação.
                            </div>

                            <div id="decisao-escolha">
                                <button type="button" class="btn btn-primary btn-block" id="btn-ir-aprovar" style="margin-bottom:var(--space-2)">{!! icon('check-circle') !!} Aprovar cotação</button>
                                <button type="button" class="btn btn-danger-outline btn-block" id="btn-recusar-mps">{!! icon('x') !!} Recusar cotação</button>
                            </div>

                            <div id="decisao-proposta" hidden>
                                <div class="field" style="margin-bottom:var(--space-3)">
                                    <label class="field-label">Arquivo PDF da proposta</label>
                                    <div class="field-file-picker" data-file-picker>
                                        <input type="file" id="proposta-arquivo" accept=".pdf" hidden>
                                        <button type="button" class="btn btn-secondary btn-sm" data-file-picker-trigger>{!! icon('clipboard-text') !!} Escolher arquivo PDF</button>
                                        <span class="field-file-picker-name" data-file-picker-name>Nenhum arquivo selecionado</span>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-primary btn-block" id="btn-enviar-proposta" disabled>{!! icon('paper-plane-tilt') !!} Enviar proposta</button>
                            </div>
                        </div>
                    </div>

                    @elseif (str_contains($status_lower, 'aprovad'))
                        @if ($ultimo_anexo && $ultimo_anexo['status_aprovacao'] === 'Aguardando')
                        <div class="panel">
                            <div class="panel-header"><h3 class="panel-title">{!! icon('clock') !!} Aguardando decisão</h3></div>
                            <div class="panel-body">
                                <div class="alert alert-info" style="margin-bottom:var(--space-3)">{!! icon('info') !!} Proposta enviada. Aguardando decisão da imobiliária/cliente.</div>
                                <a href="{{ $ultimo_anexo['caminho_arquivo'] }}" target="_blank" class="btn btn-secondary btn-block">{!! icon('arrow-right') !!} Ver proposta enviada</a>
                            </div>
                        </div>
                        @elseif ($ultimo_anexo && $ultimo_anexo['status_aprovacao'] === 'Recusada')
                        <div class="panel" id="painel-proposta">
                            <div class="panel-header"><h3 class="panel-title">{!! icon('clipboard-text') !!} Enviar nova proposta</h3></div>
                            <div class="panel-body">
                                <div class="alert alert-warning" style="margin-bottom:var(--space-3)">{!! icon('warning') !!} O cliente recusou a proposta anterior.</div>
                                <div class="field" style="margin-bottom:var(--space-2)">
                                    <label class="field-label">Novo arquivo PDF</label>
                                    <div class="field-file-picker" data-file-picker>
                                    <input type="file" id="proposta-arquivo" accept=".pdf" hidden>
                                    <button type="button" class="btn btn-secondary btn-sm" data-file-picker-trigger>{!! icon('clipboard-text') !!} Escolher arquivo PDF</button>
                                    <span class="field-file-picker-name" data-file-picker-name>Nenhum arquivo selecionado</span>
                                </div>
                                </div>
                                <button type="button" class="btn btn-primary btn-block" id="btn-enviar-proposta">{!! icon('paper-plane-tilt') !!} Enviar nova proposta</button>
                                <button type="button" class="btn btn-danger-outline btn-block proposta-acao-secundaria" id="btn-recusado-cliente">{!! icon('x') !!} Recusado pelo cliente</button>
                            </div>
                        </div>
                        @else
                        <div class="panel" id="painel-proposta">
                            <div class="panel-header"><h3 class="panel-title">{!! icon('clipboard-text') !!} Enviar proposta</h3></div>
                            <div class="panel-body">
                                <div class="alert alert-info" style="margin-bottom:var(--space-3)">{!! icon('info') !!} Cotação aprovada. Envie o arquivo da proposta.</div>
                                <div class="field" style="margin-bottom:var(--space-3)">
                                    <label class="field-label">Arquivo PDF da proposta</label>
                                    <div class="field-file-picker" data-file-picker>
                                        <input type="file" id="proposta-arquivo" accept=".pdf" hidden>
                                        <button type="button" class="btn btn-secondary btn-sm" data-file-picker-trigger>{!! icon('clipboard-text') !!} Escolher arquivo PDF</button>
                                        <span class="field-file-picker-name" data-file-picker-name>Nenhum arquivo selecionado</span>
                                    </div>
                                </div>
                                <button type="button" class="btn btn-primary btn-block" id="btn-enviar-proposta">{!! icon('paper-plane-tilt') !!} Enviar proposta</button>
                            </div>
                        </div>
                        @endif

                    @elseif (str_contains($status_lower, 'concluí') || str_contains($status_lower, 'concluido'))
                    <div class="panel"><div class="panel-body">
                        <div class="alert alert-success" style="margin-bottom:0">{!! icon('check-circle') !!} <strong>Cotação concluída!</strong></div>
                    </div></div>

                    @elseif ($is_desistencia)
                    <div class="panel"><div class="panel-body">
                        <div class="alert alert-desistencia">{!! icon('sign-out') !!} <strong>Cliente desistiu da cotação.</strong></div>
                    </div></div>

                    @elseif ($is_recusado)
                    <div class="panel"><div class="panel-body">
                        <div class="alert alert-danger" style="margin-bottom:0">{!! icon('x') !!} <strong>{!! $status === STATUS_RECUSADO_CLIENTE ? 'Cotação recusada pelo cliente.' : 'Cotação recusada.' !!}</strong></div>
                    </div></div>
                    @endif

                    @if (!cotacao_status_encerrado($status))
                    <div class="panel panel-desistencia">
                        <div class="panel-body">
                            <p class="desistencia-texto">Cliente avisou que desistiu? Encerre a cotação para tirá-la da fila de análise.</p>
                            <button type="button" class="btn btn-secondary btn-block" id="btn-desistir">{!! icon('sign-out') !!} Cliente desistiu da cotação</button>
                        </div>
                    </div>
                    @endif

                    @if (!empty($anexos))
                    <div class="panel" style="margin-top:var(--space-3)">
                        <div class="panel-header"><h3 class="panel-title">{!! icon('list') !!} Propostas enviadas</h3></div>
                        <div class="panel-body">
                            @foreach ($anexos as $a)
                            <div style="display:flex;align-items:center;justify-content:space-between;padding:var(--space-2) 0;border-bottom:1px solid var(--color-border)">
                                <div>
                                    <small>{!! date('d/m/Y H:i', strtotime($a['data_envio'])) !!}</small>
                                    <br><span class="status-pill {{ status_pill_class($a['status_aprovacao']) }}" style="font-size:.7rem">{{ $a['status_aprovacao'] }}</span>
                                </div>
                                <a href="{{ $a['caminho_arquivo'] }}" target="_blank" class="btn btn-secondary" style="padding:var(--space-1) var(--space-2)">{!! icon('arrow-right') !!}</a>
                            </div>
                            @endforeach
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

    const btnIniciar = document.getElementById('btn-iniciar-analise');
    if (btnIniciar) btnIniciar.addEventListener('click', async () => {
        const ok = await mpsAlert.confirm({ message: 'Iniciar análise desta cotação?' });
        if (!ok) return;
        apiPost('iniciar-analise', {}).then(d => handleResp(d, 'Análise iniciada!'));
    });

    const btnRecusarMPS = document.getElementById('btn-recusar-mps');
    if (btnRecusarMPS) btnRecusarMPS.addEventListener('click', async () => {
        const ok = await mpsAlert.confirm({ message: 'Recusar esta cotação?' });
        if (!ok) return;
        apiPost('recusar-mps', {}).then(d => handleResp(d, 'Cotação recusada.'));
    });

    // "Aprovar cotação" só troca a etapa visível — a aprovação de fato só
    // acontece quando a proposta é enviada (POST /proposta muda o status).
    const btnIrAprovar     = document.getElementById('btn-ir-aprovar');
    const decisaoEscolha   = document.getElementById('decisao-escolha');
    const decisaoProposta  = document.getElementById('decisao-proposta');
    if (btnIrAprovar && decisaoEscolha && decisaoProposta) {
        btnIrAprovar.addEventListener('click', () => {
            decisaoEscolha.hidden  = true;
            decisaoProposta.hidden = false;
        });
    }

    const inputPropostaArquivo = document.getElementById('proposta-arquivo');
    const btnEnviarProposta    = document.getElementById('btn-enviar-proposta');
    if (inputPropostaArquivo && btnEnviarProposta) {
        inputPropostaArquivo.addEventListener('change', () => {
            btnEnviarProposta.disabled = inputPropostaArquivo.files.length === 0;
        });
    }
    if (btnEnviarProposta) btnEnviarProposta.addEventListener('click', () => {
        const arquivo = document.getElementById('proposta-arquivo').files[0];
        if (!arquivo) { mpsAlert.error('Selecione o arquivo PDF.'); return; }
        const fd = new FormData();
        fd.append('arquivo', arquivo);
        btnEnviarProposta.disabled = true;
        btnEnviarProposta.textContent = 'Enviando...';
        fetch(`/api/cotacao/${proto}/proposta`, {method: 'POST', body: fd})
            .then(r => r.json())
            .then(d => handleResp(d, 'Proposta enviada!'))
            .catch(() => mpsAlert.error('Erro ao enviar proposta.'))
            .finally(() => { btnEnviarProposta.disabled = false; btnEnviarProposta.textContent = 'Enviar proposta'; });
    });

    // Cliente recusou a proposta e a imobiliária não quer outra: encerra a
    // cotação com status "Recusado pelo cliente".
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
