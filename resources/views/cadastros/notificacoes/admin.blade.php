@extends('layouts.app')

@section('content')
            <div class="admin-cot-filterbar">
                <div class="admin-cot-toolbar">
                    <div class="status-kpi-grid">
                        <a href="#" class="status-kpi-card is-active" data-tipo="">
                            <span class="status-kpi-icon">{!! icon('list') !!}</span>
                            <span class="status-kpi-value" id="count-todos">0</span>
                            <span class="status-kpi-label">Todos</span>
                        </a>
                        <a href="#" class="status-kpi-card" data-tipo="nova_cotacao">
                            <span class="status-kpi-icon">{!! icon('clipboard-text') !!}</span>
                            <span class="status-kpi-value" id="count-nova_cotacao">0</span>
                            <span class="status-kpi-label">Nova cotação</span>
                        </a>
                        <a href="#" class="status-kpi-card" data-tipo="chat_mensagem">
                            <span class="status-kpi-icon">{!! icon('chat-circle') !!}</span>
                            <span class="status-kpi-value" id="count-chat_mensagem">0</span>
                            <span class="status-kpi-label">Mensagem</span>
                        </a>
                        <a href="#" class="status-kpi-card" data-tipo="cotacao_finalizada">
                            <span class="status-kpi-icon">{!! icon('check-circle') !!}</span>
                            <span class="status-kpi-value" id="count-cotacao_finalizada">0</span>
                            <span class="status-kpi-label">Concluídas</span>
                        </a>
                        <a href="#" class="status-kpi-card" data-tipo="acao_imob">
                            <span class="status-kpi-icon">{!! icon('arrows-clockwise') !!}</span>
                            <span class="status-kpi-value" id="count-acao_imob">0</span>
                            <span class="status-kpi-label">Ação da imobiliária</span>
                        </a>
                    </div>
                </div>
            </div>

            <div class="panel">
                <div class="panel-header">
                    <h2>
                        Notificações
                        <span class="badge badge-primary" id="notif-total-badge" hidden>0</span>
                    </h2>
                    <button type="button" class="btn btn-secondary btn-sm" id="notif-marcar-todas" data-action="marcar-todas-lidas" hidden>
                        {!! icon('check') !!} Marcar todas como lidas
                    </button>
                </div>

                <div id="notif-lista">
                    <div class="admin-cot-empty">Carregando...</div>
                </div>

                <div class="admin-cot-pagination" id="notif-pagination" hidden>
                    <span id="notif-pag-info" class="admin-cot-pagination-info"></span>
                    <div class="admin-cot-pagination-btns">
                        <button type="button" id="notif-pag-anterior" class="btn btn-secondary btn-sm">{!! icon('arrow-left') !!} Anterior</button>
                        <button type="button" id="notif-pag-proxima" class="btn btn-secondary btn-sm">Próxima {!! icon('arrow-right') !!}</button>
                    </div>
                </div>
            </div>

        
@endsection
