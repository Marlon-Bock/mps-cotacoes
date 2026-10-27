{{-- Detalhe da cotação (auto) — imobiliária — AutoController@detalhe. --}}
@extends('layouts.app')

@section('content')
            <!-- Header -->
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
                                        ($cotacao['segurado_complemento'] ?? '') ? ' - ' . $cotacao['segurado_complemento'] : '',
                                        ($cotacao['segurado_bairro'] ?? '') ? ', ' . $cotacao['segurado_bairro'] : '',
                                        ($cotacao['segurado_cidade'] ?? '') ? ' - ' . $cotacao['segurado_cidade'] : '',
                                        ($cotacao['segurado_uf'] ?? '') ? '/' . $cotacao['segurado_uf'] : '',
                                    ]));
                                    
@endphp
                                    <div class="detalhe-field span-full">
                                        <div class="detalhe-field-label">Endereço</div>
                                        @php
 dfv(cv($seg_end ?: null)) 
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

                @include('cotacao._partials.detalhe-acoes', ['usou_pre_analise' => false, 'tipo_key' => 'auto'])

            </div><!-- /.detalhe-layout -->
@endsection

@push('antes-scripts')
@if ($anexo && ($anexo['status_aprovacao'] ?? '') === 'Aguardando')
<script>window.cotacaoProtocolo = {!! json_encode($protocolo) !!};</script>
@endif
@endpush
