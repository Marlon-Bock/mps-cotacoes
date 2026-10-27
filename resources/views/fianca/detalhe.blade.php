{{-- Detalhe da cotação de Fiança (imobiliária) — Fianca\DetalheController@imobiliaria. Era pages/cotacao/fianca/detalhe.php. --}}
@extends('layouts.app')

@section('content')
            <!-- Header da cotação -->
                        <div class="panel">
                <div class="panel-body detalhe-panel-header">
                    <div class="dph-top">
                        <span class="dph-tipo">{!! icon('house') !!} Fiança {!! ucfirst($tipo_ficha_lower) !!} &middot; Plano {{ $cotacao['tipo_plano'] ?? 'Básico' }}</span>
                        @php
  $__st = canal_status_exibicao($canal_ficha, (string) $status, (string) $protocolo, 'fianca', false); 
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
 dfv(cv_telefone($cotacao['tel_celular'])) 
@endphp
                                    </div>
                                    <div class="detalhe-field">
                                        <div class="detalhe-field-label">E-mail</div>
                                        @php
 dfv(cv($cotacao['email'])) 
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
                                <h3 class="form-section-title">{!! icon('users') !!} Pretendente adicional {!! (int)$pret['numero_pretendente'] !!}</h3>
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

                @include('cotacao._partials.detalhe-acoes')

            </div><!-- /.detalhe-layout -->

        
@endsection
