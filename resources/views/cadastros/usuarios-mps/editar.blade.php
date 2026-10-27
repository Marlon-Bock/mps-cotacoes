@extends('layouts.app')

@section('content')
            <form id="form-editar-usuario-mps" method="POST" action="{{ BASE_URI }}/admin/usuarios/{{ $slug_param }}/editar" novalidate class="cotacao-container">
                <input type="hidden" name="_csrf" value="{{ csrf_token() }}">

                <div class="form-layout-70-30">
                    <div class="form-layout-main">

                        <!-- Dados pessoais -->
                        <div class="panel">
                            <div class="panel-body">

                                <div class="form-section">
                                    <h3 class="form-section-title">{!! icon('identification-card') !!} Dados pessoais</h3>
                                    <div class="form-grid">

                                        <div class="field">
                                            <label class="field-label" for="username">
                                                Nome <span class="required">*</span>
                                                @if (!empty($erros['username']))
                                                    <span class="field-error">{{ $erros['username'] }}</span>
                                                @endif
                                            </label>
                                            <div class="field-input-wrap">
                                                <span class="field-input-icon">{!! icon('user') !!}</span>
                                                <input class="field-input has-icon{!! !empty($erros['username']) ? ' is-error' : '' !!}"
                                                       type="text" id="username" name="username"
                                                       value="{{ $valores['username'] }}" maxlength="100" required>
                                            </div>
                                        </div>

                                        <div class="field">
                                            <label class="field-label" for="email">
                                                E-mail <span class="required">*</span>
                                                @if (!empty($erros['email']))
                                                    <span class="field-error">{{ $erros['email'] }}</span>
                                                @endif
                                            </label>
                                            <div class="field-input-wrap">
                                                <span class="field-input-icon">{!! icon('envelope-simple') !!}</span>
                                                <input class="field-input has-icon{!! !empty($erros['email']) ? ' is-error' : '' !!}"
                                                       type="email" id="email" name="email"
                                                       value="{{ $valores['email'] }}" maxlength="255" required>
                                            </div>
                                        </div>

                                        <div class="field span-full">
                                            <label class="field-label" for="email_cotacao">
                                                E-mail para receber cotações (opcional)
                                                @if (!empty($erros['email_cotacao']))
                                                    <span class="field-error">{{ $erros['email_cotacao'] }}</span>
                                                @endif
                                            </label>
                                            <div class="field-input-wrap">
                                                <span class="field-input-icon">{!! icon('envelope-simple') !!}</span>
                                                <input class="field-input has-icon{!! !empty($erros['email_cotacao']) ? ' is-error' : '' !!}"
                                                       type="email" id="email_cotacao" name="email_cotacao"
                                                       value="{{ $valores['email_cotacao'] }}" maxlength="255"
                                                       placeholder="Se vazio, usaremos o e-mail de login">
                                            </div>
                                            <span class="field-hint">Se preenchido, este será usado para alertas de novas cotações.</span>
                                        </div>

                                        <div class="field span-full">
                                            <label class="field-label" for="telefone">Telefone</label>
                                            <div class="field-input-wrap">
                                                <span class="field-input-icon">{!! icon('phone') !!}</span>
                                                <input class="field-input has-icon" type="text" id="telefone" name="telefone"
                                                       data-mask="phone" placeholder="(00) 00000-0000"
                                                       value="{{ $valores['telefone'] }}" maxlength="16">
                                            </div>
                                        </div>

                                    </div>
                                </div>

                                <div class="form-section no-margin-bottom">
                                    <h3 class="form-section-title">{!! icon('lock') !!} Senha de acesso</h3>
                                    <div class="form-grid">

                                        <div class="field">
                                            <label class="field-label" for="senha">
                                                Nova senha
                                                @if (!empty($erros['senha']))
                                                    <span class="field-error">{{ $erros['senha'] }}</span>
                                                @endif
                                            </label>
                                            <div class="field-input-wrap">
                                                <span class="field-input-icon">{!! icon('lock') !!}</span>
                                                <input class="field-input has-icon has-icon-both{!! !empty($erros['senha']) ? ' is-error' : '' !!}"
                                                       type="password" id="senha" name="senha"
                                                       minlength="6" autocomplete="new-password" placeholder="Deixe em branco para manter">
                                            <button type="button" class="field-password-toggle" data-toggle="password" data-target="#senha" aria-label="Mostrar senha">
                                                {!! icon('eye', 'icon-eye') !!}
                                                {!! icon('eye-slash', 'icon-eye-slash') !!}
                                            </button>
                                            </div>
                                            <span class="field-hint">Deixe em branco para não alterar</span>
                                        </div>

                                        <div class="field">
                                            <label class="field-label" for="senha_confirmar">
                                                Confirmar nova senha
                                                @if (!empty($erros['senha_confirmar']))
                                                    <span class="field-error">{{ $erros['senha_confirmar'] }}</span>
                                                @endif
                                            </label>
                                            <div class="field-input-wrap">
                                                <span class="field-input-icon">{!! icon('lock') !!}</span>
                                                <input class="field-input has-icon has-icon-both{!! !empty($erros['senha_confirmar']) ? ' is-error' : '' !!}"
                                                       type="password" id="senha_confirmar" name="senha_confirmar"
                                                       autocomplete="new-password" placeholder="••••••••">
                                            <button type="button" class="field-password-toggle" data-toggle="password" data-target="#senha_confirmar" aria-label="Mostrar confirmação de senha">
                                                {!! icon('eye', 'icon-eye') !!}
                                                {!! icon('eye-slash', 'icon-eye-slash') !!}
                                            </button>
                                            </div>
                                        </div>

                                    </div>
                                </div>

                                <div class="stepper-nav">
                                    <div></div>
                                    <div class="stepper-nav-right">
                                        <a href="{{ url('/admin/usuarios') }}" class="btn btn-sm btn-secondary">Cancelar</a>
                                        <button type="submit" class="btn btn-sm btn-primary">
                                            {!! icon('check') !!} Salvar
                                        </button>
                                    </div>
                                </div>

                            </div>
                        </div>

                    </div>

                    <div class="form-layout-side">
                        @include('cadastros._partials.dois-fatores-conta', ['df_tabela' => 'usuario', 'df_usuario_id' => (int) $usuario['id'], 'df_proprio' => false])

                        <!-- Permissões -->
                        <div class="panel" id="painel-permissoes">
                            <div class="panel-header">
                                <h2>{!! icon('key') !!} Permissões</h2>
                            </div>
                            <div class="panel-body">
                                <label class="switch">
                                    <input type="checkbox" name="admin" value="S" id="perm-admin" {!! $valores['admin'] ? 'checked' : '' !!}>
                                    <span class="switch-track"></span>
                                    <span class="switch-label"><strong>Administrador</strong></span>
                                </label>
                                <label class="switch">
                                    <input type="checkbox" name="perm_fianca" value="S" class="perm-check" {!! $valores['perm_fianca'] ? 'checked' : '' !!}>
                                    <span class="switch-track"></span>
                                    <span class="switch-label">Fiança</span>
                                </label>
                                <label class="switch">
                                    <input type="checkbox" name="perm_auto" value="S" class="perm-check" {!! $valores['perm_auto'] ? 'checked' : '' !!}>
                                    <span class="switch-track"></span>
                                    <span class="switch-label">Seguro Auto</span>
                                </label>
                                <label class="switch">
                                    <input type="checkbox" name="perm_titulo" value="S" class="perm-check" {!! $valores['perm_titulo'] ? 'checked' : '' !!}>
                                    <span class="switch-track"></span>
                                    <span class="switch-label">Título de Capitalização</span>
                                </label>
                                <label class="switch">
                                    <input type="checkbox" name="perm_condominial" value="S" class="perm-check" {!! $valores['perm_condominial'] ? 'checked' : '' !!}>
                                    <span class="switch-track"></span>
                                    <span class="switch-label">Condominial</span>
                                </label>
                                <label class="switch">
                                    <input type="checkbox" name="perm_incendio" value="S" class="perm-check" {!! $valores['perm_incendio'] ? 'checked' : '' !!}>
                                    <span class="switch-track"></span>
                                    <span class="switch-label">Seguro Incêndio</span>
                                </label>
                                <label class="switch">
                                    <input type="checkbox" name="perm_seguro_residencial" value="S" class="perm-check" {!! $valores['perm_seguro_residencial'] ? 'checked' : '' !!}>
                                    <span class="switch-track"></span>
                                    <span class="switch-label">Seguro Residencial</span>
                                </label>
                                <label class="switch">
                                    <input type="checkbox" name="perm_suporte" value="S" class="perm-check" {!! $valores['perm_suporte'] ? 'checked' : '' !!}>
                                    <span class="switch-track"></span>
                                    <span class="switch-label">Suporte</span>
                                </label>
                                <label class="switch">
                                    <input type="checkbox" name="equipe_comercial" value="S" class="perm-check" {!! $valores['equipe_comercial'] ? 'checked' : '' !!}>
                                    <span class="switch-track"></span>
                                    <span class="switch-label">Equipe comercial</span>
                                </label>
                            </div>
                        </div>

                        <!-- Tarefas -->
                        <div class="panel" id="painel-tarefas">
                            <div class="panel-header">
                                <h2>{!! icon('list-checks') !!} Tarefas</h2>
                            </div>
                            <div class="panel-body">
                                <label class="switch">
                                    <input type="checkbox" name="tarefas_gestor" value="S" id="perm-tarefas-gestor" {!! $valores['tarefas_gestor'] ? 'checked' : '' !!}>
                                    <span class="switch-track"></span>
                                    <span class="switch-label"><strong>Gestor de Tarefas</strong> (vê tudo, setor = Administrador)</span>
                                </label>
                                <div id="setores-wrap"{!! $valores['tarefas_gestor'] ? ' class="is-hidden"' : '' !!}>
                                    <p class="field-hint">Setores do usuário</p>
                                    @if (empty($setores_disp))
                                        <p class="field-hint">Nenhum setor criado ainda. Crie setores em <a href="{{ url('/admin/tarefas') }}">Tarefas &rsaquo; Gerenciar</a>.</p>
                                    @else
                                    <div class="switch-group">
                                        @foreach ($setores_disp as $s)
                                        <label class="switch">
                                            <input type="checkbox" name="setores[]" value="{!! (int) $s['id'] !!}" {!! in_array((int) $s['id'], $valores['setores'], true) ? 'checked' : '' !!}>
                                            <span class="switch-track"></span>
                                            <span class="switch-label">{{ $s['nome'] }}</span>
                                        </label>
                                        @endforeach
                                    </div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <!-- Preferências de e-mail -->
                        <div class="panel" id="painel-emails">
                            <div class="panel-header">
                                <h2>{!! icon('envelope-simple') !!} Preferências de e-mail</h2>
                            </div>
                            <div class="panel-body">
                                <label class="switch">
                                    <input type="checkbox" name="recebe_email_fianca" value="S" {!! $valores['recebe_email_fianca'] ? 'checked' : '' !!}>
                                    <span class="switch-track"></span>
                                    <span class="switch-label">Fiança</span>
                                </label>
                                <label class="switch">
                                    <input type="checkbox" name="recebe_email_auto" value="S" {!! $valores['recebe_email_auto'] ? 'checked' : '' !!}>
                                    <span class="switch-track"></span>
                                    <span class="switch-label">Auto</span>
                                </label>
                                <label class="switch">
                                    <input type="checkbox" name="recebe_email_titulo" value="S" {!! $valores['recebe_email_titulo'] ? 'checked' : '' !!}>
                                    <span class="switch-track"></span>
                                    <span class="switch-label">Título de Capitalização</span>
                                </label>
                                <label class="switch">
                                    <input type="checkbox" name="recebe_email_condominial" value="S" {!! $valores['recebe_email_condominial'] ? 'checked' : '' !!}>
                                    <span class="switch-track"></span>
                                    <span class="switch-label">Condominial</span>
                                </label>
                                <label class="switch">
                                    <input type="checkbox" name="recebe_email_incendio" value="S" {!! $valores['recebe_email_incendio'] ? 'checked' : '' !!}>
                                    <span class="switch-track"></span>
                                    <span class="switch-label">Seguro Incêndio</span>
                                </label>
                                <label class="switch">
                                    <input type="checkbox" name="recebe_email_seguro_residencial" value="S" {!! $valores['recebe_email_seguro_residencial'] ? 'checked' : '' !!}>
                                    <span class="switch-track"></span>
                                    <span class="switch-label">Seguro Residencial</span>
                                </label>
                                <label class="switch">
                                    <input type="checkbox" name="recebe_email_suporte" value="S" {!! $valores['recebe_email_suporte'] ? 'checked' : '' !!}>
                                    <span class="switch-track"></span>
                                    <span class="switch-label">Chamados de suporte</span>
                                </label>
                            </div>
                        </div>

                    </div>
                </div>
            </form>

        
@endsection

@push('antes-scripts')
<script>
(function () {
    var form = document.getElementById('form-editar-usuario-mps');
    if (!form) return;

    var adminCb   = form.querySelector('#perm-admin');
    var outrosCbs = form.querySelectorAll('.perm-check');

    function sincronizarAdmin() {
        outrosCbs.forEach(function (cb) {
            if (adminCb.checked) cb.checked = true;
            cb.disabled = adminCb.checked;
        });
    }

    adminCb.addEventListener('change', sincronizarAdmin);
    sincronizarAdmin();

    var gestorCb    = form.querySelector('#perm-tarefas-gestor');
    var setoresWrap = form.querySelector('#setores-wrap');
    if (gestorCb && setoresWrap) {
        var sincronizarGestor = function () {
            setoresWrap.classList.toggle('is-hidden', gestorCb.checked);
        };
        gestorCb.addEventListener('change', sincronizarGestor);
        sincronizarGestor();
    }

}());
</script>
@endpush
