{{-- Dashboard da MPS — DashboardController@admin. $main_attrs: corretor e popup "em análise". --}}
@extends('layouts.app')

@section('content')

            <!-- KPIs — atendimento manual e API juntos: onde as cotações estão
                 hoje e o resultado do mês. Cada card abre a listagem filtrada. -->
            <div class="kpi-grid kpi-grid-5">
                <a href="{!! $link_cotacoes('acao_mps') !!}" class="kpi-card">
                    <div class="kpi-card-info">
                        <span>Aguardando a MPS</span>
                        <div class="kpi-card-value">{!! $kpis['acao_mps'] !!}</div>
                        <small class="kpi-card-detalhe">Análise, cotação ou envio de documentos</small>
                    </div>
                    <div class="kpi-card-icon is-neutral">{!! icon('clock') !!}</div>
                </a>
                <a href="{!! $link_cotacoes('andamento') !!}" class="kpi-card">
                    <div class="kpi-card-info">
                        <span>Em andamento</span>
                        <div class="kpi-card-value">{!! $kpis['andamento'] !!}</div>
                        <small class="kpi-card-detalhe">Em análise, nas seguradoras ou em emissão</small>
                    </div>
                    <div class="kpi-card-icon is-neutral">{!! icon('magnifying-glass') !!}</div>
                </a>
                <a href="{!! $link_cotacoes('com_cliente') !!}" class="kpi-card">
                    <div class="kpi-card-info">
                        <span>Com o cliente</span>
                        <div class="kpi-card-value">{!! $kpis['com_cliente'] !!}</div>
                        <small class="kpi-card-detalhe">Propostas ou fichas aguardando o cliente</small>
                    </div>
                    <div class="kpi-card-icon is-neutral">{!! icon('paper-plane-tilt') !!}</div>
                </a>
                <a href="{!! $link_cotacoes('concluidos', true) !!}" class="kpi-card">
                    <div class="kpi-card-info">
                        <span>Concluídas em {{ $mes_nome }}</span>
                        <div class="kpi-card-value">{!! $kpis['concluidas_mes'] !!}</div>
                        <small class="kpi-card-detalhe">Cotações fechadas no mês</small>
                    </div>
                    <div class="kpi-card-icon is-neutral">{!! icon('check-circle') !!}</div>
                </a>
                <a href="{!! $link_cotacoes('reprovados', true) !!}" class="kpi-card">
                    <div class="kpi-card-info">
                        <span>Recusadas em {{ $mes_nome }}</span>
                        <div class="kpi-card-value">{!! $kpis['recusadas_mes'] !!}</div>
                        <small class="kpi-card-detalhe">Pela MPS, seguradoras ou cliente</small>
                    </div>
                    <div class="kpi-card-icon is-neutral">{!! icon('x') !!}</div>
                </a>
            </div>

            <!-- Fila de pendentes (prioridade) + Panorama de status -->
            <div class="dashboard-grid">
                <section class="panel cot-panel">
                    <div class="panel-header">
                        <h2>Fila de atendimento</h2>
                        <a class="btn-link" href="{{ url('/admin/cotacoes') }}">Ver todas</a>
                    </div>
                    @if (!$fila_pendentes)
                    <div class="panel-body">
                        <div class="empty-state">
                            <div class="empty-state-icon">{!! icon('check-circle') !!}</div>
                            <p>Nenhuma cotação aguardando atendimento.</p>
                        </div>
                    </div>
                    @else
                    <div class="fila-scroll">
                        @php
 foreach ($fila_pendentes as $item):
                            $tipo_key   = $item['tipo'];
                            $icon_name  = match ($tipo_key) {
                                'auto'        => 'car',
                                'condominial' => 'buildings',
                                'titulo'      => 'piggy-bank',
                                'incendio'    => 'fire',
                                'residencial' => 'house-line',
                                default       => $item['tipo_ficha'] === 'comercial' ? 'buildings' : 'house',
                            };
                            $tipo_label = match ($tipo_key) {
                                'auto'        => 'Seguro Auto',
                                'condominial' => 'Seguro Condomínio',
                                'titulo'      => 'Título de Capitalização',
                                'incendio'    => 'Seguro Incêndio',
                                'residencial' => 'Seguro Residencial',
                                default       => $item['tipo_ficha'] === 'comercial' ? 'Fiança Comercial' : 'Fiança Residencial',
                            };
                        
@endphp
                        <div class="cot-row">
                            <div class="cot-row-icon cot-row-icon--{{ $tipo_key }}">
                                {!! icon($icon_name) !!}
                            </div>
                            <div class="cot-row-info">
                                <span class="cot-row-nome">{{ $item['pretendente'] ?? '—' }}</span>
                                <span class="cot-row-sub">{{ $tipo_label }} · {{ $item['protocolo'] }} · {{ $item['imobiliaria_nome'] ?? '—' }}</span>
                            </div>
                            <span class="cot-row-data">{{ date_to_br($item['data_cadastro']) }}</span>
                            <a href="{{ cotacao_admin_url($item['protocolo']) }}" class="btn btn-secondary btn-sm cot-row-btn">{!! icon('eye') !!} Ver</a>
                        </div>
                        @endforeach
                    </div>
                    @endif
                </section>

                <section class="panel">
                    <div class="panel-header">
                        <h2>Panorama — {!! $mes_nome !!}</h2>
                    </div>
                    <div class="panel-body">
                        @if ($panorama['total'] === 0)
                        <div class="empty-state">
                            <div class="empty-state-icon">{!! icon('chart-pie') !!}</div>
                            <p>Nenhuma cotação registrada este mês.</p>
                        </div>
                        @else
                        <div class="panorama-total">{!! $panorama['total'] !!} <span>cotações no mês</span></div>
                        <div class="panorama-bar">
                            @foreach ($panorama['statuses'] as $slot)
                            @if ($slot['pct'] > 0)
                            <div class="panorama-segment {!! $slot['key'] !!}" data-width="{!! $slot['pct'] !!}"></div>
                            @endif
                            @endforeach
                        </div>
                        <ul class="panorama-legend">
                            @foreach ($panorama['statuses'] as $slot)
                            <li class="panorama-legend-item">
                                <span class="panorama-legend-dot {!! $slot['key'] !!}"></span>
                                <span class="panorama-legend-label">{{ $slot['label'] }}</span>
                                <span class="panorama-legend-count">{!! $slot['total'] !!}</span>
                                <span class="panorama-legend-pct">{!! $slot['pct'] !!}%</span>
                            </li>
                            @endforeach
                        </ul>
                        @endif
                    </div>
                </section>
            </div>

            <!-- Gráfico mensal + Concluídos por tipo -->
            <div class="dashboard-grid">
                <section class="panel chart-panel">
                    <div class="panel-header">
                        <h2>Cotações por mês — {!! $ano_atual !!}</h2>
                    </div>
                    <div class="panel-body">
                        <div class="chart-bars">
                            @foreach ($meses_label as $i => $label)
                            @php
                                $valor = $grafico_mensal[$i + 1];
                                $altura_pct = max(4, (int) round($valor / $grafico_max * 100));
                            
@endphp
                            <div class="chart-bar-col">
                                <span class="chart-bar-value">{!! $valor !!}</span>
                                <div class="chart-bar-track">
                                    <div class="chart-bar" data-height="{!! $altura_pct !!}"></div>
                                </div>
                                <span class="chart-bar-label">{!! $label !!}</span>
                            </div>
                            @endforeach
                        </div>
                    </div>
                </section>

                <section class="panel">
                    <div class="panel-header">
                        <h2>Concluídas — {!! $mes_nome !!}</h2>
                    </div>
                    <div class="panel-body">
                        <ul class="concluidos-list">
                            @foreach ($concluidos as $tipo)
                            <li class="concluidos-item">
                                <div class="concluidos-item-icon">{!! icon($tipo['icone']) !!}</div>
                                <div class="concluidos-item-info">
                                    <strong>{{ $tipo['label'] }}</strong>
                                </div>
                                <div class="concluidos-item-right">
                                    <span class="concluidos-item-count">{!! $tipo['atual'] !!}</span>
                                    @php
                                        $d = $tipo['delta'];
                                        $badge_class = $d > 0 ? 'is-up' : ($d < 0 ? 'is-down' : 'is-neutral');
                                        $badge_text  = $d > 0 ? "+{$d}" : (string) $d;
                                    
@endphp
                                    <span class="delta-badge {!! $badge_class !!}">{!! $badge_text !!}</span>
                                </div>
                            </li>
                            @endforeach
                        </ul>
                    </div>
                </section>
            </div>

        
@endsection
