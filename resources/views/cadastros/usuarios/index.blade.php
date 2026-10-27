@extends('layouts.app')

@section('content')
            <div class="admin-cot-filterbar">
                <div class="admin-cot-toolbar">
                    <div class="cotacoes-filtros admin-filtros cotacao-container">
                        <div class="field field-search-wide">
                            <label class="field-label">Nome / E-mail</label>
                            <div class="field-input-wrap">
                                <span class="field-input-icon">{!! icon('magnifying-glass') !!}</span>
                                <input type="text" id="f-busca" class="field-input has-icon" placeholder="Buscar por nome ou e-mail">
                            </div>
                        </div>
                    </div>
                    <div class="admin-cot-toolbar-actions">
                        <a href="{{ url('/usuarios/criar') }}" class="btn btn-primary btn-sm">
                            {!! icon('user') !!} Criar usuário
                        </a>
                    </div>
                </div>
            </div>

            <div class="panel cot-panel">
                <div class="admin-cot-grid-wrap">
                    <div class="admin-cot-row admin-cot-row-usuarios admin-cot-head">
                        <div class="admin-cot-cell admin-cot-cell-nome">Nome</div>
                        <div class="admin-cot-cell">E-mail</div>
                        <div class="admin-cot-cell">Telefone</div>
                        <div class="admin-cot-cell">Perfil</div>
                        <div class="admin-cot-cell">Último login</div>
                        <div class="admin-cot-cell">Ações</div>
                    </div>
                    <div id="usuarios-lista" data-csrf="{{ csrf_token() }}">
                        <div class="admin-cot-empty">Carregando...</div>
                    </div>
                </div>
            </div>

        
@endsection
