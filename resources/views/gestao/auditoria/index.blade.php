{{-- Auditoria — /admin/auditoria (Gestao\AuditoriaController@index). Só o suporte da Sami. Linhas via /api/admin/auditoria/listar. --}}
@extends('layouts.app')

@section('content')
            <!-- Filtros — aplicam sozinhos (AJAX) -->
            <div class="panel cot-panel aud-filtros-painel">
                <div class="cotacoes-filtros admin-filtros aud-filtros">
                    <div class="field">
                        <label class="field-label">Quem</label>
                        <div class="field-input-wrap">
                            <span class="field-input-icon">{!! icon('user') !!}</span>
                            <input type="text" id="f-quem" class="field-input has-icon" placeholder="Nome ou e-mail">
                        </div>
                    </div>
                    <div class="field">
                        <label class="field-label">Tipo</label>
                        <div class="field-input-wrap" id="f-tipo-wrap">
                            <span class="field-input-icon">{!! icon('users') !!}</span>
                            {!! custom_select('f_tipo', $opcoes_tipo, 'Todos', null, 'has-icon') !!}
                        </div>
                    </div>
                    <div class="field">
                        <label class="field-label">Imobiliária</label>
                        <div class="field-input-wrap" id="f-imobiliaria-wrap">
                            <span class="field-input-icon">{!! icon('buildings') !!}</span>
                            {!! custom_select('f_imobiliaria', $opcoes_imobiliaria, 'Todas', null, 'has-icon', false, ['data-busca' => '']) !!}
                        </div>
                    </div>
                    <div class="field">
                        <label class="field-label">Período (de / até)</label>
                        <div class="field-date-range">
                            {!! date_picker('f-de') !!}
                            {!! date_picker('f-ate') !!}
                        </div>
                    </div>
                    <div class="field">
                        <label class="field-label">Ação, URL ou dado enviado</label>
                        <div class="field-input-wrap">
                            <span class="field-input-icon">{!! icon('magnifying-glass') !!}</span>
                            <input type="text" id="f-busca" class="field-input has-icon" placeholder="Ex: MPS2026-77199, perm_cotar">
                        </div>
                    </div>
                    <div class="field">
                        <label class="field-label">Registro alterado</label>
                        <div class="aud-registro">
                            <div class="field-input-wrap">
                                <span class="field-input-icon">{!! icon('table') !!}</span>
                                <input type="text" id="f-tabela" class="field-input has-icon" placeholder="Tabela">
                            </div>
                            <div class="field-input-wrap">
                                <span class="field-input-icon">{!! icon('key') !!}</span>
                                <input type="text" id="f-registro" class="field-input has-icon" placeholder="ID">
                            </div>
                        </div>
                    </div>
                    <div class="field">
                        <label class="field-label">IP</label>
                        <div class="field-input-wrap">
                            <span class="field-input-icon">{!! icon('map-pin') !!}</span>
                            <input type="text" id="f-ip" class="field-input has-icon" placeholder="Começo do IP">
                        </div>
                    </div>
                    <div class="field aud-chaves">
                        <label class="switch">
                            <input type="checkbox" id="f-so-alteracoes">
                            <span class="switch-track"></span>
                            <span class="switch-label">Só o que alterou dados</span>
                        </label>
                        <label class="switch">
                            <input type="checkbox" id="f-senha-mestra">
                            <span class="switch-track"></span>
                            <span class="switch-label">Só com a senha do dia</span>
                        </label>
                    </div>
                </div>
                <div class="aud-filtros-acoes">
                    <button type="button" id="btn-limpar" class="btn btn-secondary btn-sm" hidden>{!! icon('x') !!} Limpar filtros</button>
                </div>
            </div>

            <!-- Registros (grid de divs — sem table); clique abre os detalhes -->
            <div class="panel cot-panel">
                <div class="admin-cot-grid-wrap">
                    <div class="admin-cot-row admin-cot-head aud-row">
                        <div class="admin-cot-cell">Quando</div>
                        <div class="admin-cot-cell">Quem</div>
                        <div class="admin-cot-cell">O que</div>
                        <div class="admin-cot-cell">Imobiliária</div>
                        <div class="admin-cot-cell">IP</div>
                        <div class="admin-cot-cell">Alterações</div>
                    </div>
                    <div id="aud-rows">
                        <div class="admin-cot-empty">Carregando...</div>
                    </div>
                </div>
                <div class="admin-cot-pagination">
                    <span id="pag-info" class="admin-cot-pagination-info"></span>
                    <div class="admin-cot-pagination-btns">
                        <button type="button" id="pag-anterior" class="btn btn-secondary btn-sm">{!! icon('arrow-left') !!} Anterior</button>
                        <button type="button" id="pag-proxima" class="btn btn-secondary btn-sm">Próxima {!! icon('arrow-right') !!}</button>
                    </div>
                </div>
            </div>
@endsection
