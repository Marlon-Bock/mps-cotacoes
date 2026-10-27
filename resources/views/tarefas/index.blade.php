{{-- Gerenciador de Tarefas da MPS — TarefasController@index. $main_attrs: data-csrf e data-gestor. --}}
@extends('layouts.app')

@section('content')
            <!-- Abas principais (só gestor: usuário comum vê direto a lista).
                 Tarefas (= todas) + uma aba por setor criado, e depois Dashboard/Gerenciar. -->
            @if ($gestor)
            <div class="tarefas-tabs">
                <button type="button" class="tarefas-tab is-active" data-tab="tarefas" data-setor="">{!! icon('list-checks') !!} Tarefas</button>
                <button type="button" class="tarefas-add-setor" id="btn-novo-setor" title="Novo setor" aria-label="Novo setor">{!! icon('plus') !!} <span class="tarefas-add-setor-label">Novo setor</span></button>
                @foreach ($setores as $s)
                <span class="tarefas-tab-setor-wrap">
                    <button type="button" class="tarefas-tab tarefas-tab-setor" data-tab="tarefas" data-setor="{!! (int) $s['id'] !!}"><span class="tarefas-tab-dot" data-cor="{{ $s['cor'] }}"></span>{{ $s['nome'] }}</button>
                    <button type="button" class="tarefas-tab-edit" data-edit-setor="{!! (int) $s['id'] !!}" title="Editar setor" aria-label="Editar setor">{!! icon('note-pencil') !!}</button>
                </span>
                @endforeach
                <span class="tarefas-tabs-sep"></span>
                <button type="button" class="tarefas-tab" data-tab="dashboard">{!! icon('chart-bar') !!} Dashboard</button>
            </div>
            @endif

            <!-- ══════════════ ABA: TAREFAS ══════════════ -->
            <section class="tarefas-panel is-active" data-panel="tarefas">

                <div class="admin-cot-filterbar">
                    <div class="admin-cot-toolbar">
                        <div class="status-kpi-grid">
                            <a href="#" class="status-kpi-card is-active" data-status="">
                                <span class="status-kpi-icon">{!! icon('list') !!}</span>
                                <span class="status-kpi-value" id="count-todos">0</span>
                                <span class="status-kpi-label">Todas</span>
                            </a>
                            @foreach ($status_list as $st)
                            <a href="#" class="status-kpi-card" data-status="{!! (int) $st['id'] !!}">
                                <span class="status-kpi-icon">{!! icon('check-circle') !!}</span>
                                <span class="status-kpi-value" id="count-status-{!! (int) $st['id'] !!}">0</span>
                                <span class="status-kpi-label">{{ $st['nome'] }}</span>
                            </a>
                            @endforeach
                            <a href="#" class="status-kpi-card" data-status="atrasadas">
                                <span class="status-kpi-icon">{!! icon('warning') !!}</span>
                                <span class="status-kpi-value" id="count-atrasadas">0</span>
                                <span class="status-kpi-label">Atrasadas</span>
                            </a>
                            <a href="#" class="status-kpi-card" data-status="arquivadas">
                                <span class="status-kpi-icon">{!! icon('eye-slash') !!}</span>
                                <span class="status-kpi-value" id="count-arquivadas">0</span>
                                <span class="status-kpi-label">Arquivadas</span>
                            </a>
                        </div>

                        <div class="admin-cot-toolbar-actions">
                            <button type="button" id="btn-limpar" class="btn btn-secondary btn-sm is-hidden">Limpar filtros</button>
                            <button type="button" id="btn-nova-tarefa" class="btn btn-primary btn-sm">{!! icon('plus') !!} Nova tarefa</button>
                        </div>
                    </div>

                    <div class="cotacoes-filtros admin-filtros cotacao-container">
                        <div class="field field-search-wide">
                            <label class="field-label">Buscar</label>
                            <div class="field-input-wrap">
                                <span class="field-input-icon">{!! icon('magnifying-glass') !!}</span>
                                <input type="text" id="f-busca" class="field-input has-icon" placeholder="Título ou descrição">
                            </div>
                        </div>
                        @if ($gestor)
                        <div class="field">
                            <label class="field-label">Responsável</label>
                            <div class="field-input-wrap">
                                <span class="field-input-icon">{!! icon('user') !!}</span>
                                {!! custom_select('f_responsavel', $opc_resp, 'Todos', null, 'has-icon', false, ['id' => 'f-responsavel-wrap']) !!}
                            </div>
                        </div>
                        @endif
                        <div class="field">
                            <label class="field-label">Prazo (período)</label>
                            <div class="field-date-range">
                                {!! date_picker('f-data-de', 'DD/MM/AAAA') !!}
                                {!! date_picker('f-data-ate', 'DD/MM/AAAA') !!}
                            </div>
                        </div>
                    </div>
                </div>

                <div id="tarefas-rows" class="mnd-lista">
                    <div class="admin-cot-empty">Carregando...</div>
                </div>
                <div class="mnd-rodape">
                    <span id="pag-info" class="admin-cot-pagination-info"></span>
                    <div class="admin-cot-pagination-btns is-hidden">
                        <button type="button" id="pag-anterior" class="btn btn-secondary btn-sm">{!! icon('arrow-left') !!} Anterior</button>
                        <button type="button" id="pag-proxima" class="btn btn-secondary btn-sm">Próxima {!! icon('arrow-right') !!}</button>
                    </div>
                </div>
            </section>

            @if ($gestor)
            <!-- ══════════════ ABA: DASHBOARD ══════════════ -->
            <section class="tarefas-panel" data-panel="dashboard">
                <!-- Cartões-resumo -->
                <div class="tk-kpis">
                    <div class="tk-kpi">
                        <span class="tk-kpi-ic tk-kpi-ic-total">{!! icon('list-checks') !!}</span>
                        <div><div class="tk-kpi-num" id="dash-total">0</div><span class="tk-kpi-lbl">Total de tarefas</span></div>
                    </div>
                    <div class="tk-kpi">
                        <span class="tk-kpi-ic tk-kpi-ic-pend">{!! icon('clock') !!}</span>
                        <div><div class="tk-kpi-num" id="dash-pendentes">0</div><span class="tk-kpi-lbl">Pendentes</span></div>
                    </div>
                    <div class="tk-kpi">
                        <span class="tk-kpi-ic tk-kpi-ic-ok">{!! icon('check-circle') !!}</span>
                        <div><div class="tk-kpi-num" id="dash-concluidas">0</div><span class="tk-kpi-lbl">Concluídas</span></div>
                    </div>
                    <div class="tk-kpi">
                        <span class="tk-kpi-ic tk-kpi-ic-atr">{!! icon('warning') !!}</span>
                        <div><div class="tk-kpi-num" id="dash-atrasadas">0</div><span class="tk-kpi-lbl">Atrasadas</span></div>
                    </div>
                </div>

                <div class="tk-dash-grid">
                    <!-- Donut: distribuição por status -->
                    <section class="panel">
                        <div class="panel-header"><h2>{!! icon('chart-pie') !!} Distribuição por status</h2></div>
                        <div class="panel-body">
                            <div class="tk-donut-wrap">
                                <div class="tk-donut" id="dash-donut"></div>
                                <ul class="tk-legend" id="dash-donut-leg"></ul>
                            </div>
                        </div>
                    </section>

                    <!-- Barras empilhadas: situação por setor -->
                    <section class="panel">
                        <div class="panel-header"><h2>{!! icon('buildings') !!} Situação por setor</h2></div>
                        <div class="panel-body">
                            <div class="tk-legend tk-legend-inline" id="dash-setor-leg"></div>
                            <ul class="tk-stack-list" id="dash-por-setor"></ul>
                        </div>
                    </section>

                    <!-- Prazos próximos -->
                    <section class="panel">
                        <div class="panel-header"><h2>{!! icon('calendar-blank') !!} Prazos próximos</h2></div>
                        <div class="panel-body">
                            <div class="tk-mini-tiles" id="dash-prazo-tiles"></div>
                            <ul class="tk-task-list" id="dash-prazo-lista"></ul>
                        </div>
                    </section>

                    <!-- Tarefas paradas (sem interação) -->
                    <section class="panel">
                        <div class="panel-header"><h2>{!! icon('clock') !!} Paradas (sem interação)</h2></div>
                        <div class="panel-body">
                            <div class="tk-mini-tiles" id="dash-parada-tiles"></div>
                            <ul class="tk-task-list" id="dash-parada-lista"></ul>
                        </div>
                    </section>

                    <!-- Carga por responsável -->
                    <section class="panel tk-span-2">
                        <div class="panel-header"><h2>{!! icon('user') !!} Carga por responsável</h2></div>
                        <div class="panel-body"><ul class="concluidos-list" id="dash-por-resp"></ul></div>
                    </section>
                </div>
            </section>
            @endif

        
@endsection

@push('antes-scripts')
<!-- Dados dos usuários MPS (com setores) — autocomplete de setor e filtro de responsáveis -->
<script type="application/json" id="tarefas-usuarios-json">{!! json_encode(
    array_map(static fn($u) => [
        'id'      => (int) $u['id'],
        'nome'    => $u['username'],
        'avatar'  => $u['avatar'] ?: null,
        'gestor'  => ($u['tarefas_gestor'] ?? 'N') === 'S',
        'setores' => $usuarios_setores[(int) $u['id']] ?? [],
    ], $usuarios),
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
) !!}</script>

<!-- Status disponíveis (menu de troca rápida + editor) -->
<script type="application/json" id="tarefas-status-json">{!! json_encode(
    array_map(static fn($s) => [
        'id'       => (int) $s['id'],
        'nome'     => $s['nome'],
        'cor'      => $s['cor'],
        'is_final' => (int) $s['is_final'],
        'ativo'    => (int) $s['ativo'],
    ], $status_all),
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
) !!}</script>

<!-- Setores (para editar: nome + cor) -->
<script type="application/json" id="tarefas-setores-json">{!! json_encode(
    array_map(static fn($s) => ['id' => (int) $s['id'], 'nome' => $s['nome'], 'cor' => $s['cor']], $setores),
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
) !!}</script>

@if ($gestor)
<!-- Modal "Novo setor / Editar setor" (componente .modal padrão) -->
<div class="modal" id="modal-setor" hidden>
    <div class="modal-backdrop" data-close="modal-setor"></div>
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 id="modal-setor-titulo">Novo setor</h3>
            <button type="button" class="modal-close" data-close="modal-setor">{!! icon('x') !!}</button>
        </div>
        <form id="form-setor" class="modal-body">
            <input type="hidden" id="ns-id" value="">
            <div class="field">
                <label class="field-label" for="ns-nome">Nome do setor</label>
                <div class="field-input-wrap">
                    <span class="field-input-icon">{!! icon('buildings') !!}</span>
                    <input class="field-input has-icon" type="text" id="ns-nome" placeholder="Ex: Financeiro" maxlength="120" autocomplete="off">
                </div>
            </div>

            <div class="field">
                <label class="field-label">Cor do setor</label>
                <div class="colorpicker" id="ns-colorpicker">
                    <div class="colorpicker-presets">
                        @foreach ($preset_cores as $pc)
                        <button type="button" class="colorpicker-preset" data-cor="{{ $pc }}" aria-label="{{ $pc }}"></button>
                        @endforeach
                        <button type="button" class="colorpicker-custom" id="ns-cp-trigger" title="Personalizar cor" aria-label="Personalizar cor">{!! icon('plus') !!}</button>
                    </div>
                    <div class="colorpicker-pop is-hidden" id="ns-cp-pop">
                        <div class="colorpicker-sv" id="ns-cp-sv"><span class="colorpicker-sv-handle" id="ns-cp-sv-handle"></span></div>
                        <div class="colorpicker-hue" id="ns-cp-hue"><span class="colorpicker-hue-handle" id="ns-cp-hue-handle"></span></div>
                        <input type="text" class="field-input colorpicker-hexinput" id="ns-cp-hexinput" maxlength="7" value="#2563EB" spellcheck="false">
                    </div>
                    <input type="hidden" id="ns-cor" value="#2563eb">
                </div>
            </div>

            <div class="field">
                <label class="field-label" for="ns-user-input">Usuários do setor</label>
                <div class="tarefa-userpicker">
                    <div class="field-input-wrap">
                        <span class="field-input-icon">{!! icon('user') !!}</span>
                        <input class="field-input has-icon" type="text" id="ns-user-input" placeholder="Digite para buscar..." autocomplete="off">
                    </div>
                    <div class="tarefa-userpicker-drop is-hidden" id="ns-user-drop"></div>
                </div>
                <div class="tarefa-userpicker-chips" id="ns-user-chips"></div>
            </div>

            <p class="field-error is-hidden" id="ns-erro"></p>

            <div class="modal-footer">
                <button type="button" class="btn btn-danger-outline is-hidden" id="ns-excluir">{!! icon('trash') !!} Excluir</button>
                <button type="button" class="btn btn-secondary" data-close="modal-setor">Cancelar</button>
                <button type="submit" class="btn btn-primary">{!! icon('check') !!} Salvar</button>
            </div>
        </form>
    </div>
</div>
@endif

<!-- Offcanvas de detalhes da tarefa: resumo, comentários (com menções) e histórico -->
<div class="tk-oc-overlay is-hidden" id="tk-oc-overlay"></div>
<aside class="tk-oc" id="tk-oc" aria-hidden="true">
    <header class="tk-oc-head">
        <div class="tk-oc-head-info">
            <span class="tk-oc-setor" id="tk-oc-setor"></span>
            <input type="text" class="tk-oc-titulo" id="tk-oc-titulo" placeholder="Escreva o título da tarefa" autocomplete="off" maxlength="255">
        </div>
        <button type="button" class="tk-oc-close" id="tk-oc-close" aria-label="Fechar">{!! icon('x') !!}</button>
    </header>

    <div class="tk-oc-resumo" id="tk-oc-resumo"></div>

    <nav class="tk-oc-tabs">
        <button type="button" class="tk-oc-tab is-active" data-oc-tab="comentarios">{!! icon('chat-circle') !!} Comentários</button>
        <button type="button" class="tk-oc-tab" data-oc-tab="historico">{!! icon('clock') !!} Histórico</button>
    </nav>

    <div class="tk-oc-body">
        <div class="tk-oc-pane" data-oc-pane="comentarios">
            <div class="tk-cmt-lista" id="tk-cmt-lista"></div>
        </div>
        <div class="tk-oc-pane is-hidden" data-oc-pane="historico">
            <ol class="tk-hist" id="tk-hist"></ol>
        </div>
    </div>

    <footer class="tk-oc-composer" id="tk-oc-composer">
        <div class="tk-mencao-drop is-hidden" id="tk-mencao-drop"></div>
        <textarea class="tk-cmt-input" id="tk-cmt-input" rows="2" placeholder="Escreva um comentário. Use @ para mencionar alguém."></textarea>
        <div class="tk-cmt-acoes">
            <span class="tk-cmt-dica">{!! icon('at') !!} menciona alguém do setor</span>
            <button type="button" class="btn btn-primary" id="tk-cmt-enviar">{!! icon('paper-plane-tilt') !!} Comentar</button>
        </div>
    </footer>
</aside>
@endpush
