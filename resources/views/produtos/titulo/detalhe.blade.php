{{-- Detalhe da cotação (titulo) — imobiliária — TituloController@detalhe. --}}
@extends('layouts.app')

@section('content')
            <!-- Header -->
                                    <div class="panel">
                <div class="panel-body detalhe-panel-header">
                    <div class="dph-top">
                        <span class="dph-tipo">{!! icon('ticket') !!} Título de Capitalização</span>
                        <span class="status-pill {{ status_pill_class($status) }}">{{ $status_label_imob }}</span>
                    </div>
                    <span class="dph-proto">Cotação #{{ $protocolo }}</span>
                    <div class="dph-bar">
                        @php
 $_inq = $cotacao['inq_nome'] ?? $cotacao['inq_razao_social'] ?? ''; 
@endphp
                        @if ($_inq)
                        <span class="dph-bar-item">
                            {!! icon('user') !!}
                            <span class="dph-bar-label">Inquilino</span>
                            <span class="dph-bar-value">{{ $_inq }}</span>
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
                        <a href="{{ url('/api/cotacao/' . $protocolo . '/titulo-pdf') }}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm btn-pdf">
                            {!! icon('file-pdf') !!} Baixar PDF
                        </a>
                    </div>
                </div>
            </div>

            <div class="detalhe-layout cotacao-container">
                <div class="detalhe-left">

                    <div class="panel">
                        <div class="panel-body">

                            <!-- Contrato e imóvel -->
                            <div class="form-section">
                                <h3 class="form-section-title">{!! icon('house') !!} Contrato e imóvel</h3>
                                <div class="detalhe-fields">
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Finalidade</div>
                                        @php
 dfv(cv($cotacao['finalidade'])) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Prazo do contrato</div>
                                        @php
 dfv(cv($cotacao['prazo_contrato'])) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Valor do aluguel</div>
                                        @php
 dfv(cv_money($cotacao['valor_aluguel'] ?? null)) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Valor do título</div>
                                        @php
 dfv(cv_money($cotacao['valor_titulo'] ?? null)) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Forma de pagamento</div>
                                        @php
 dfv(cv($cotacao['forma_pagamento'] ?? null)) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">CEP do imóvel</div>
                                        @php
 dfv(cv($cotacao['imovel_cep'])) 
@endphp
                                    </div>
                                    @php
                                    $imovel_end = implode('', array_filter([
                                        $cotacao['imovel_logradouro'] ?? '',
                                        ($cotacao['imovel_numero'] ?? '') ? ', ' . $cotacao['imovel_numero'] : '',
                                        ($cotacao['imovel_complemento'] ?? '') ? ' - ' . $cotacao['imovel_complemento'] : '',
                                        ($cotacao['imovel_bairro'] ?? '') ? ', ' . $cotacao['imovel_bairro'] : '',
                                        ($cotacao['imovel_cidade'] ?? '') ? ' - ' . $cotacao['imovel_cidade'] : '',
                                        ($cotacao['imovel_uf'] ?? '') ? '/' . $cotacao['imovel_uf'] : '',
                                    ]));
                                    
@endphp
                                    <div class="detalhe-field span-full">
                                        <div class="detalhe-field-label">Endereço do imóvel</div>
                                        @php
 dfv(cv($imovel_end ?: null)) 
@endphp
                                    </div>
                                </div>
                            </div>

                            <!-- Inquilino -->
                            <div class="form-section">
                                <h3 class="form-section-title">{!! icon('user') !!} Dados do inquilino</h3>
                                <div class="detalhe-fields">
                                @if ($inquilino_tipo === 'pj')
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Razão social</div>
                                        @php
 dfv(cv($cotacao['inq_razao_social'])) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">CNPJ</div>
                                        @php
 dfv(cv($cotacao['inq_cnpj'])) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Representante</div>
                                        @php
 dfv(cv($cotacao['inq_representante'])) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">CPF do representante</div>
                                        @php
 dfv(cv($cotacao['inq_cpf_representante'])) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">E-mail</div>
                                        @php
 dfv(cv($cotacao['inq_pj_email'])) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Telefone</div>
                                        @php
 dfv(cv($cotacao['inq_pj_telefone'])) 
@endphp
                                    </div>
                                    @if (!empty($cotacao['pol_exposta']))
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Pessoa politicamente exposta</div>
                                        @php
 dfv(cv_sim_nao($cotacao['pol_exposta'])) 
@endphp
                                    </div>
                                    @if (!empty($cotacao['pol_exposta_funcao']))
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Função (PEP)</div>
                                        @php
 dfv(cv($cotacao['pol_exposta_funcao'])) 
@endphp
                                    </div>
                                    @endif
                                    @endif
                                @else
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Nome</div>
                                        @php
 dfv(cv($cotacao['inq_nome'])) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">CPF</div>
                                        @php
 dfv(cv($cotacao['inq_cpf'])) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Data de nascimento</div>
                                        @php
 dfv(cv_data($cotacao['inq_data_nasc'] ?? null)) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Estado civil</div>
                                        @php
 dfv(cv($cotacao['inq_estado_civil'])) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">RG</div>
                                        @php
 dfv(cv($cotacao['inq_rg'])) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Órgão emissor</div>
                                        @php
 dfv(cv($cotacao['inq_orgao_emissor'])) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Profissão</div>
                                        @php
 dfv(cv($cotacao['inq_profissao'])) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">E-mail</div>
                                        @php
 dfv(cv($cotacao['inq_email'])) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Telefone 1</div>
                                        @php
 dfv(cv($cotacao['inq_telefone1'])) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Telefone 2</div>
                                        @php
 dfv(cv($cotacao['inq_telefone2'])) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">CEP</div>
                                        @php
 dfv(cv($cotacao['inq_cep'])) 
@endphp
                                    </div>
                                    @php
                                    $inq_end = implode('', array_filter([
                                        $cotacao['inq_logradouro'] ?? '',
                                        ($cotacao['inq_numero'] ?? '') ? ', ' . $cotacao['inq_numero'] : '',
                                        ($cotacao['inq_complemento'] ?? '') ? ' - ' . $cotacao['inq_complemento'] : '',
                                        ($cotacao['inq_bairro'] ?? '') ? ', ' . $cotacao['inq_bairro'] : '',
                                        ($cotacao['inq_cidade'] ?? '') ? ' - ' . $cotacao['inq_cidade'] : '',
                                        ($cotacao['inq_uf'] ?? '') ? '/' . $cotacao['inq_uf'] : '',
                                    ]));
                                    
@endphp
                                    <div class="detalhe-field span-full">
                                        <div class="detalhe-field-label">Endereço do inquilino</div>
                                        @php
 dfv(cv($inq_end ?: null)) 
@endphp
                                    </div>
                                @endif
                                </div>
                            </div>

                            <!-- Proprietário -->
                            <div class="form-section">
                                <h3 class="form-section-title">{!! icon('briefcase') !!} Dados do proprietário</h3>
                                <div class="detalhe-fields">
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Nome</div>
                                        @php
 dfv(cv($cotacao['prop_nome'])) 
@endphp
                                    </div>
                                    @if (!empty($cotacao['prop_cnpj']))
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">CNPJ</div>
                                        @php
 dfv(cv($cotacao['prop_cnpj'])) 
@endphp
                                    </div>
                                    @if (!empty($cotacao['prop_nome_socio']))
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Nome do sócio</div>
                                        @php
 dfv(cv($cotacao['prop_nome_socio'])) 
@endphp
                                    </div>
                                    @endif
                                    @else
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">CPF</div>
                                        @php
 dfv(cv($cotacao['prop_cpf'])) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Data de nascimento</div>
                                        @php
 dfv(cv_data($cotacao['prop_data_nasc'] ?? null)) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Estado civil</div>
                                        @php
 dfv(cv($cotacao['prop_estado_civil'])) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">RG</div>
                                        @php
 dfv(cv($cotacao['prop_rg'])) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Órgão emissor</div>
                                        @php
 dfv(cv($cotacao['prop_orgao_emissor'])) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Profissão</div>
                                        @php
 dfv(cv($cotacao['prop_profissao'])) 
@endphp
                                    </div>
                                    @endif
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">E-mail</div>
                                        @php
 dfv(cv($cotacao['prop_email'])) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Telefone 1</div>
                                        @php
 dfv(cv($cotacao['prop_telefone1'])) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">Telefone 2</div>
                                        @php
 dfv(cv($cotacao['prop_telefone2'])) 
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

                @include('cotacao._partials.detalhe-acoes', ['usou_pre_analise' => false, 'tipo_key' => 'titulo'])

            </div><!-- /.detalhe-layout -->
@endsection

@push('antes-scripts')
@if ($anexo && ($anexo['status_aprovacao'] ?? '') === 'Aguardando')
<script>window.cotacaoProtocolo = {!! json_encode($protocolo) !!};</script>
@endif
@endpush
