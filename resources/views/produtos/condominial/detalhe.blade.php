{{-- Detalhe da cotação (condominial) — imobiliária — CondominialController@detalhe. --}}
@extends('layouts.app')

@section('content')
            <!-- Header -->
                                    <div class="panel">
                <div class="panel-body detalhe-panel-header">
                    <div class="dph-top">
                        <span class="dph-tipo">{!! icon('buildings') !!} Seguro Condominial</span>
                        <span class="status-pill {{ status_pill_class($status) }}">{{ $status }}</span>
                    </div>
                    <span class="dph-proto">Cotação #{{ $protocolo }}</span>
                    <div class="dph-bar">
                        @php
 $_cond_nome = $cotacao['imovel_nome'] ?? $cotacao['sindico_nome'] ?? ''; 
@endphp
                        @if ($_cond_nome)
                        <span class="dph-bar-item">
                            {!! icon('buildings') !!}
                            <span class="dph-bar-label">Condomínio</span>
                            <span class="dph-bar-value">{{ $_cond_nome }}</span>
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
                        <a href="{{ url('/api/cotacao/' . $protocolo . '/condominial-pdf') }}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm btn-pdf">
                            {!! icon('file-pdf') !!} Baixar PDF
                        </a>
                    </div>
                </div>
            </div>

            <div class="detalhe-layout cotacao-container">
                <div class="detalhe-left">

                    <div class="panel">
                        <div class="panel-body">

                            <!-- Dados do condomínio -->
                            <div class="form-section">
                                <h3 class="form-section-title">{!! icon('buildings') !!} Dados do condomínio</h3>
                                <div class="detalhe-fields">
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Tipo de condomínio</div>
                                        @php
 dfv(cv($cotacao['tipo_condominio'])) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Nome</div>
                                        @php
 dfv(cv($cotacao['imovel_nome'])) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">CNPJ</div>
                                        @php
 dfv(cv_cpf_cnpj($cotacao['imovel_cnpj'])) 
@endphp
                                    </div>
                                    @php
                                    $cond_end = implode('', array_filter([
                                        $cotacao['imovel_endereco'] ?? '',
                                        ($cotacao['imovel_numero'] ?? '') ? ', ' . $cotacao['imovel_numero'] : '',
                                        ($cotacao['imovel_complemento'] ?? '') ? ' - ' . $cotacao['imovel_complemento'] : '',
                                        ($cotacao['imovel_bairro'] ?? '') ? ', ' . $cotacao['imovel_bairro'] : '',
                                        ($cotacao['imovel_cidade'] ?? '') ? ' - ' . $cotacao['imovel_cidade'] : '',
                                        ($cotacao['imovel_uf'] ?? '') ? '/' . $cotacao['imovel_uf'] : '',
                                    ]));
                                    
@endphp
                                    <div class="detalhe-field span-full">
                                        <div class="detalhe-field-label">Endereço</div>
                                        @php
 dfv(cv($cond_end ?: null)) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">CEP</div>
                                        @php
 dfv(cv_cep($cotacao['imovel_cep'])) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Ano de construção</div>
                                        @php
 dfv(cv($cotacao['imovel_ano_construcao'])) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Nº de blocos</div>
                                        @php
 dfv(cv($cotacao['imovel_blocos'])) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Nº de andares</div>
                                        @php
 dfv(cv($cotacao['imovel_andares'])) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Elevadores</div>
                                        @php
 dfv(cv($cotacao['imovel_elevadores'])) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Área (m²)</div>
                                        @php
 dfv(cv($cotacao['imovel_area_m2'])) 
@endphp
                                    </div>
                                </div>
                            </div>

                            <!-- Características -->
                            <div class="form-section">
                                <h3 class="form-section-title">{!! icon('gear') !!} Características</h3>
                                <div class="detalhe-fields">
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Alarme</div>
                                        @php
 dfv(cv_sim_nao($cotacao['tem_alarme'] ?? null)) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Câmeras</div>
                                        @php
 dfv(cv_sim_nao($cotacao['tem_cameras'] ?? null)) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Central telefônica</div>
                                        @php
 dfv(cv_sim_nao($cotacao['tem_central_telefonica'] ?? null)) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Funcionários</div>
                                        @php
 dfv(cv_sim_nao($cotacao['tem_funcionarios'] ?? null)) 
@endphp
                                    </div>
                                    @if (!empty($cotacao['qtd_funcionarios']))
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Qtd. funcionários</div>
                                        @php
 dfv(cv($cotacao['qtd_funcionarios'])) 
@endphp
                                    </div>
                                    @endif
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Seguro atual</div>
                                        @php
 dfv(cv_sim_nao($cotacao['tem_seguro_atual'] ?? null)) 
@endphp
                                    </div>
                                    @if (!empty($cotacao['seguro_atual_seguradora']))
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Seguradora atual</div>
                                        @php
 dfv(cv($cotacao['seguro_atual_seguradora'])) 
@endphp
                                    </div>
                                    @endif
                                    @if (!empty($cotacao['seguro_atual_classe_bonus']))
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Classe bônus</div>
                                        @php
 dfv(cv($cotacao['seguro_atual_classe_bonus'])) 
@endphp
                                    </div>
                                    @endif
                                </div>
                            </div>

                            <!-- Coberturas -->
                            <div class="form-section">
                                <h3 class="form-section-title">{!! icon('check-circle') !!} Coberturas solicitadas</h3>
                                <div class="coberturas-badges">
                                    @foreach ($coberturas_estado as $label => $c)
                                    <span class="cob-badge {!! $c['ativo'] ? 'is-active' : '' !!}">
                                        {!! icon($c['ativo'] ? 'check-circle' : 'x') !!} {{ $label }}{!! $c['ativo'] ? ' — ' . cv_money($c['valor']) : '' !!}
                                    </span>
                                    @endforeach
                                </div>
                            </div>

                            <!-- Síndico -->
                            <div class="form-section">
                                <h3 class="form-section-title">{!! icon('user') !!} Dados do síndico</h3>
                                <div class="detalhe-fields">
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Nome</div>
                                        @php
 dfv(cv($cotacao['sindico_nome'])) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Telefone</div>
                                        @php
 dfv(cv_telefone($cotacao['sindico_telefone'])) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">E-mail</div>
                                        @php
 dfv(cv($cotacao['sindico_email'])) 
@endphp
                                    </div>
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

                @include('cotacao._partials.detalhe-acoes', ['usou_pre_analise' => false, 'tipo_key' => 'condominial'])

            </div><!-- /.detalhe-layout -->
@endsection

@push('antes-scripts')
@if ($anexo && ($anexo['status_aprovacao'] ?? '') === 'Aguardando')
<script>window.cotacaoProtocolo = {!! json_encode($protocolo) !!};</script>
@endif
@endpush
