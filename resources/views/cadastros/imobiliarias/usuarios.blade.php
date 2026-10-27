@extends('layouts.app')

@section('content')
            <div class="panel cot-panel">
                <div class="panel-header">
                    <h2>Usuários de {{ $imob['nome'] }}</h2>
                    <a href="{{ BASE_URI }}/admin/imobiliarias/{{ $slug_param }}/usuarios/criar" class="btn btn-primary btn-sm">
                        {!! icon('plus') !!} Criar Usuário
                    </a>
                </div>
                @if (empty($usuarios))
                    <div class="panel-body">
                        <div class="empty-state">
                            <div class="empty-state-icon">
                                {!! icon('users') !!}
                            </div>
                            <p>Nenhum usuário vinculado a esta imobiliária ainda.</p>
                            <a href="{{ BASE_URI }}/admin/imobiliarias/{{ $slug_param }}/usuarios/criar" class="btn btn-primary btn-sm">
                                Criar primeiro usuário
                            </a>
                        </div>
                    </div>
                @else
                    <div class="admin-cot-grid-wrap">
                        <div class="admin-cot-row admin-cot-row-imob-usuarios admin-cot-head">
                            <div class="admin-cot-cell admin-cot-cell-nome">Nome</div>
                            <div class="admin-cot-cell">E-mail</div>
                            <div class="admin-cot-cell">Telefone</div>
                            <div class="admin-cot-cell">Perfil</div>
                            <div class="admin-cot-cell">Último login</div>
                            <div class="admin-cot-cell">Status</div>
                            <div class="admin-cot-cell">Ações</div>
                        </div>
                        @foreach ($usuarios as $u)
                        <div class="admin-cot-row admin-cot-row-imob-usuarios">
                            <div class="admin-cot-cell admin-cot-cell-nome">{{ $u['username'] }}</div>
                            <div class="admin-cot-cell">{{ $u['email'] }}</div>
                            <div class="admin-cot-cell">{!! $u['telefone'] ? e($u['telefone']) : '<span class="admin-cot-cell-vazio">—</span>' !!}</div>
                            <div class="admin-cot-cell">
                                @if ($u['admin'] === 'S')
                                    <span class="badge badge-primary">Admin</span>
                                @else
                                    <span class="badge">Operador</span>
                                @endif
                            </div>
                            <div class="admin-cot-cell">
                                @if ($u['last_login_at'])
                                    {{ date('d/m/Y H:i', strtotime($u['last_login_at'])) }}
                                @else
                                    <span class="admin-cot-cell-vazio">Nunca</span>
                                @endif
                            </div>
                            <div class="admin-cot-cell">
                                @if ($u['ativo'])
                                    <span class="status-pill status-pill-aprovado">Ativo</span>
                                @else
                                    <span class="status-pill status-pill-recusado">Inativo</span>
                                @endif
                            </div>
                            <div class="admin-cot-cell admin-cot-cell-acoes">
                                <div class="table-actions">
                                    <a href="{{ BASE_URI }}/admin/imobiliarias/{{ $slug_param }}/usuarios/{{ $u['slug'] }}/editar" class="btn btn-secondary btn-sm" title="Editar">{!! icon('note-pencil') !!}</a>
                                    <button
                                        type="button"
                                        class="btn {!! $u['ativo'] ? 'btn-danger-outline' : 'btn-secondary' !!} btn-sm js-toggle-ativo"
                                        data-action="toggle-ativo"
                                        data-usuario-id="{!! (int) $u['id'] !!}"
                                        data-ativo="{!! (int) $u['ativo'] !!}"
                                        data-imobiliaria-id="{!! (int) $imob['id'] !!}"
                                        title="{!! $u['ativo'] ? 'Inativar' : 'Ativar' !!}"
                                    >
                                        {!! $u['ativo'] ? icon('x') : icon('check') !!}
                                    </button>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                @endif
            </div>

        
@endsection
