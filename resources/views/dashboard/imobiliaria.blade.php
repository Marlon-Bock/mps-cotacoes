{{-- Dashboard da imobiliária — DashboardController@imobiliaria. $main_attrs carrega os popups (dashboard.js). --}}
@extends('layouts.app')

@section('content')

            <div class="kpi-grid kpi-grid-5">
                <div class="kpi-card">
                    <div class="kpi-card-info">
                        <span>Em análise</span>
                        <div class="kpi-card-value">{!! $kpis['em_analise'] !!}</div>
                    </div>
                    <div class="kpi-card-icon is-neutral">{!! icon('clock') !!}</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-card-info">
                        <span>Aguardando análise</span>
                        <div class="kpi-card-value">{!! $kpis['aguardando'] !!}</div>
                    </div>
                    <div class="kpi-card-icon is-neutral">{!! icon('clipboard-text') !!}</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-card-info">
                        <span>Aprovadas</span>
                        <div class="kpi-card-value">{!! $kpis['aprovadas'] !!}</div>
                    </div>
                    <div class="kpi-card-icon is-neutral">{!! icon('check') !!}</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-card-info">
                        <span>Concluídas</span>
                        <div class="kpi-card-value">{!! $kpis['concluidas'] !!}</div>
                    </div>
                    <div class="kpi-card-icon is-neutral">{!! icon('check') !!}</div>
                </div>
                <div class="kpi-card">
                    <div class="kpi-card-info">
                        <span>Recusadas</span>
                        <div class="kpi-card-value">{!! $kpis['recusadas'] !!}</div>
                    </div>
                    <div class="kpi-card-icon is-neutral">{!! icon('x') !!}</div>
                </div>
            </div>

            <div class="quick-actions">
                @if ($tem_cotar && $tem_perm('perm_fianca'))
                <a class="quick-action-card" href="{{ url('/cotacao/fianca/residencial') }}">
                    <div class="quick-action-icon">{!! icon('house') !!}</div>
                    <div>
                        <strong>Fiança Residencial</strong>
                        <span>Nova cotação</span>
                    </div>
                </a>
                <a class="quick-action-card" href="{{ url('/cotacao/fianca/comercial') }}">
                    <div class="quick-action-icon">{!! icon('buildings') !!}</div>
                    <div>
                        <strong>Fiança Comercial</strong>
                        <span>Nova cotação</span>
                    </div>
                </a>
                @endif
                @if ($tem_cotar && $tem_perm('perm_auto'))
                <a class="quick-action-card" href="{{ url('/cotacao/auto') }}">
                    <div class="quick-action-icon">{!! icon('car') !!}</div>
                    <div>
                        <strong>Seguro Auto</strong>
                        <span>Nova cotação</span>
                    </div>
                </a>
                @endif
                @if ($tem_cotar && $tem_perm('perm_titulo'))
                <a class="quick-action-card" href="{{ url('/cotacao/titulo') }}">
                    <div class="quick-action-icon">{!! icon('piggy-bank') !!}</div>
                    <div>
                        <strong>Título de Capitalização</strong>
                        <span>Nova cotação</span>
                    </div>
                </a>
                @endif
                @if ($tem_cotar && $tem_perm('perm_condo'))
                <a class="quick-action-card" href="{{ url('/cotacao/condominial') }}">
                    <div class="quick-action-icon">{!! icon('buildings') !!}</div>
                    <div>
                        <strong>Seguro Condomínio</strong>
                        <span>Nova cotação</span>
                    </div>
                </a>
                @endif
                @if ($tem_cotar && $tem_perm('perm_incendio'))
                <a class="quick-action-card" href="{{ url('/cotacao/incendio') }}">
                    <div class="quick-action-icon">{!! icon('fire') !!}</div>
                    <div>
                        <strong>Seguro Incêndio</strong>
                        <span>Nova cotação</span>
                    </div>
                </a>
                @endif
                @if ($tem_cotar && $tem_perm('perm_seguro_residencial'))
                <a class="quick-action-card" href="{{ url('/cotacao/residencial') }}">
                    <div class="quick-action-icon">{!! icon('house-line') !!}</div>
                    <div>
                        <strong>Seguro Residencial</strong>
                        <span>Nova cotação</span>
                    </div>
                </a>
                @endif
            </div>

            <div class="dashboard-grid">
                <section class="panel cot-panel">
                    <div class="panel-header">
                        <h2>Últimas cotações</h2>
                        <a class="btn-link" href="{{ url('/cotacoes') }}">Ver todas</a>
                    </div>
                    @if (!$ultimas_cotacoes)
                    <div class="panel-body">
                        <div class="empty-state">
                            <div class="empty-state-icon">{!! icon('clipboard-text') !!}</div>
                            <p>Você ainda não enviou nenhuma cotação.</p>
                        </div>
                    </div>
                    @else
                    @php
 foreach ($ultimas_cotacoes as $cot):
                        $tipo_key  = $cot['tipo'] ?? 'fianca';
                        $icon_name = match($tipo_key) {
                            'auto'        => 'car',
                            'condominial' => 'buildings',
                            'titulo'      => 'piggy-bank',
                            'incendio'    => 'fire',
                            'residencial' => 'house-line',
                            default       => 'house',
                        };
                        $tipo_ficha  = $cot['tipo_ficha'] ?? null;
                        $tipo_label  = match($tipo_key) {
                            'auto'        => 'Seguro Auto',
                            'condominial' => 'Seguro Condomínio',
                            'titulo'      => 'Título de Capitalização',
                            'incendio'    => 'Seguro Incêndio',
                            'residencial' => 'Seguro Residencial',
                            default       => $tipo_ficha === 'comercial' ? 'Fiança Comercial' : 'Fiança Residencial',
                        };
                        $data_fmt = !empty($cot['data_atualizacao']) ? date('d/m/Y', strtotime($cot['data_atualizacao'])) : '—';
                        $href     = '/cotacoes?protocolo=' . urlencode($cot['protocolo']);
                    
@endphp
                    <div class="cot-row">
                        <div class="cot-row-icon cot-row-icon--{{ $tipo_key }}">
                            {!! icon($icon_name) !!}
                        </div>
                        <div class="cot-row-info">
                            <span class="cot-row-nome">{{ $cot['pretendente'] ?? '—' }}</span>
                            <span class="cot-row-sub">{{ $tipo_label }} · {{ $cot['protocolo'] }}</span>
                        </div>
                        @php
  $__st = canal_status_exibicao((string) ($cot['origem'] ?? ''), (string) $cot['status'], (string) $cot['protocolo'], (string) $tipo_key, false); 
@endphp
                        <span class="status-pill {{ $__st['pill'] }}">{{ $__st['label'] }}</span>
                        <span class="cot-row-data">{{ $data_fmt }}</span>
                        <a href="{{ url($href) }}" class="btn btn-secondary btn-sm cot-row-btn">{!! icon('eye') !!} Ver</a>
                    </div>
                    @endforeach
                    @endif
                </section>

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
            </div>

            <div class="dashboard-grid">
                <section class="panel">
                    <div class="panel-header">
                        <h2>Campanhas ativas</h2>
                        <a class="btn-link" href="{{ url('/campanhas') }}">Ver todas</a>
                    </div>
                    <div class="panel-body">
                        @if (!$campanhas_ativas)
                        <div class="empty-state">
                            <div class="empty-state-icon">{!! icon('megaphone') !!}</div>
                            <p>Nenhuma campanha ativa no momento.</p>
                        </div>
                        @else
                        <div class="campaign-grid">
                            @foreach ($campanhas_ativas as $campanha)
                            @php
                                // media_upload() já retorna o path pronto pra uso direto (URL completa do
                                // media server em produção, ou "/uploads/..." local em dev) — nunca precisa
                                // passar por media_url().
                                $imagem_url = $campanha['imagem'] ?? '';
                                $dias_restantes = max(0, (int) ceil((strtotime((string) $campanha['data_fim']) - time()) / 86400));
                            
@endphp
                            <button type="button" class="campaign-card campaign-card-clickable" data-abrir-campanha data-imagem="{{ $imagem_url }}" data-titulo="{{ $campanha['titulo'] }}" data-observacoes="{{ $campanha['observacoes'] ?? '' }}">
                                @if ($imagem_url)
                                <img src="{{ $imagem_url }}" alt="" loading="lazy">
                                @endif
                                <div class="campaign-card-overlay">
                                    <strong>{{ $campanha['titulo'] }}</strong>
                                    <span class="campaign-card-badge">
                                        {!! $dias_restantes > 0 ? "Termina em {$dias_restantes} dia" . ($dias_restantes > 1 ? 's' : '') : 'Encerra hoje' !!}
                                    </span>
                                </div>
                            </button>
                            @endforeach
                        </div>
                        @endif
                    </div>
                </section>

                <section class="panel">
                    <div class="panel-header">
                        <h2>Notificações</h2>
                        <a class="btn-link" href="{{ url('/notificacoes') }}">Ver todas</a>
                    </div>
                    @if (!$notificacoes_recentes)
                    <div class="panel-body">
                        <div class="empty-state">
                            <div class="empty-state-icon">{!! icon('bell') !!}</div>
                            <p>Nenhuma notificação por aqui.</p>
                        </div>
                    </div>
                    @else
                    <ul class="notif-list">
                        @php
 foreach ($notificacoes_recentes as $notif):
                            $notif_tipo  = $notif['tipo'] ?? '';
                            $notif_icone = notif_tipo_icon($notif_tipo);
                            $notif_lida = (int) ($notif['lida'] ?? 0);
                        
@endphp
                        <li class="notif-item{!! $notif_lida ? ' is-read' : '' !!}">
                            <span class="notif-item-icon notif-item-icon--{{ $notif_tipo ?: 'geral' }}">
                                {!! icon($notif_icone) !!}
                            </span>
                            <div class="notif-item-body">
                                <div class="notif-item-header">
                                    <strong class="notif-item-titulo">{{ $notif['titulo'] ?? '' }}</strong>
                                    @if (!$notif_lida)
                                        <span class="badge badge-primary">Nova</span>
                                    @endif
                                </div>
                                <p class="notif-item-mensagem">{{ $notif['mensagem'] ?? '' }}</p>
                                <span class="notif-item-data">{{ tempo_decorrido($notif['criado_em'] ?? '') }}</span>
                            </div>
                        </li>
                        @endforeach
                    </ul>
                    @endif
                </section>
            </div>

        
@endsection

@push('antes-scripts')
<div class="modal modal-campanha-popup" id="modal-campanha-detalhe" hidden>
    <div class="modal-backdrop" data-close="modal-campanha-detalhe"></div>
    <div class="modal-dialog">
        <button type="button" class="modal-close" data-close="modal-campanha-detalhe" aria-label="Fechar">{!! icon('x') !!}</button>
        <button type="button" class="modal-campanha-popup-obs-btn" id="campanha-detalhe-obs-btn" data-open="modal-campanha-obs" hidden>{!! icon('file-text') !!} Observações</button>
        <img id="campanha-detalhe-img" src="" alt="">
    </div>
</div>

<div class="modal modal-campanha-obs" id="modal-campanha-obs" hidden>
    <div class="modal-backdrop" data-close="modal-campanha-obs"></div>
    <div class="modal-dialog">
        <div class="modal-header">
            <h3>{!! icon('file-text') !!} Observações</h3>
            <button type="button" class="modal-close" data-close="modal-campanha-obs" aria-label="Fechar">{!! icon('x') !!}</button>
        </div>
        <div class="modal-body">
            <p id="campanha-detalhe-obs-texto"></p>
        </div>
    </div>
</div>
@endpush
