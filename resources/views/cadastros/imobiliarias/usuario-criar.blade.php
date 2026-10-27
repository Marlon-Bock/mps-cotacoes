@extends('layouts.app')

@section('content')
            <form id="form-criar-usuario" method="POST" action="{{ BASE_URI }}/admin/imobiliarias/{{ $slug_param }}/usuarios/criar" novalidate class="cotacao-container">
                <input type="hidden" name="_csrf" value="{{ csrf_token() }}">

                <div class="form-layout-70-30">
                    <div class="form-layout-main">

                        <!-- Dados do usuário -->
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
                                                       placeholder="Nome completo"
                                                       value="{{ $valores['username'] }}"
                                                       maxlength="100" required>
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
                                                       placeholder="email@exemplo.com"
                                                       value="{{ $valores['email'] }}"
                                                       maxlength="255" required>
                                            </div>
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
                                                Senha <span class="required">*</span>
                                                @if (!empty($erros['senha']))
                                                    <span class="field-error">{{ $erros['senha'] }}</span>
                                                @endif
                                            </label>
                                            <div class="field-input-wrap">
                                                <span class="field-input-icon">{!! icon('lock') !!}</span>
                                                <input class="field-input has-icon has-icon-both{!! !empty($erros['senha']) ? ' is-error' : '' !!}"
                                                       type="password" id="senha" name="senha"
                                                       minlength="6" autocomplete="new-password" placeholder="••••••••" required>
                                            <button type="button" class="field-password-toggle" data-toggle="password" data-target="#senha" aria-label="Mostrar senha">
                                                {!! icon('eye', 'icon-eye') !!}
                                                {!! icon('eye-slash', 'icon-eye-slash') !!}
                                            </button>
                                            </div>
                                        </div>

                                        <div class="field">
                                            <label class="field-label" for="senha_confirmar">
                                                Confirmar senha <span class="required">*</span>
                                                @if (!empty($erros['senha_confirmar']))
                                                    <span class="field-error">{{ $erros['senha_confirmar'] }}</span>
                                                @endif
                                            </label>
                                            <div class="field-input-wrap">
                                                <span class="field-input-icon">{!! icon('lock') !!}</span>
                                                <input class="field-input has-icon has-icon-both{!! !empty($erros['senha_confirmar']) ? ' is-error' : '' !!}"
                                                       type="password" id="senha_confirmar" name="senha_confirmar"
                                                       autocomplete="new-password" placeholder="••••••••" required>
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
                                        <a href="{{ BASE_URI }}/admin/imobiliarias/{{ $slug_param }}/usuarios" class="btn btn-sm btn-secondary">Cancelar</a>
                                        <button type="submit" class="btn btn-sm btn-primary">
                                            {!! icon('check') !!} Salvar
                                        </button>
                                    </div>
                                </div>

                            </div>
                        </div>

                    </div>

                    <!-- Permissões -->
                    <div class="panel">
                        <div class="panel-header">
                            <h2>{!! icon('key') !!} Permissões</h2>
                        </div>
                        <div class="panel-body">
                            <label class="switch">
                                <input type="checkbox" name="admin" value="S" id="perm-admin" {!! $valores['admin'] ? 'checked' : '' !!}>
                                <span class="switch-track"></span>
                                <span class="switch-label"><strong>Administrador da imobiliária</strong></span>
                            </label>
                            <label class="switch">
                                <input type="checkbox" name="perm_criar_usuarios" value="S" class="perm-check" {!! $valores['perm_criar_usuarios'] ? 'checked' : '' !!}>
                                <span class="switch-track"></span>
                                <span class="switch-label">Pode criar outros usuários</span>
                            </label>
                            <label class="switch">
                                <input type="checkbox" name="perm_cotar" value="S" class="perm-check" id="perm-cotar" {!! $valores['perm_cotar'] ? 'checked' : '' !!}>
                                <span class="switch-track"></span>
                                <span class="switch-label">Pode realizar cotações</span>
                            </label>
                            <div class="perm-produtos-wrap" id="perm-produtos-wrap" {!! $valores['perm_cotar'] ? '' : 'hidden' !!}>
                                <label class="switch">
                                    <input type="checkbox" name="perm_fianca" value="S" class="perm-check perm-produto" {!! $valores['perm_fianca'] ? 'checked' : '' !!}>
                                    <span class="switch-track"></span>
                                    <span class="switch-label">Acesso a Seguro Fiança</span>
                                </label>
                                <label class="switch">
                                    <input type="checkbox" name="perm_auto" value="S" class="perm-check perm-produto" {!! $valores['perm_auto'] ? 'checked' : '' !!}>
                                    <span class="switch-track"></span>
                                    <span class="switch-label">Acesso a Seguro Auto</span>
                                </label>
                                <label class="switch">
                                    <input type="checkbox" name="perm_condo" value="S" class="perm-check perm-produto" {!! $valores['perm_condo'] ? 'checked' : '' !!}>
                                    <span class="switch-track"></span>
                                    <span class="switch-label">Acesso a Seguro Condominial</span>
                                </label>
                                <label class="switch">
                                    <input type="checkbox" name="perm_titulo" value="S" class="perm-check perm-produto" {!! $valores['perm_titulo'] ? 'checked' : '' !!}>
                                    <span class="switch-track"></span>
                                    <span class="switch-label">Acesso a Título de Capitalização</span>
                                </label>
                                <label class="switch">
                                    <input type="checkbox" name="perm_incendio" value="S" class="perm-check perm-produto" {!! $valores['perm_incendio'] ? 'checked' : '' !!}>
                                    <span class="switch-track"></span>
                                    <span class="switch-label">Acesso a Seguro Incêndio</span>
                                </label>
                                <label class="switch">
                                    <input type="checkbox" name="perm_seguro_residencial" value="S" class="perm-check perm-produto" {!! $valores['perm_seguro_residencial'] ? 'checked' : '' !!}>
                                    <span class="switch-track"></span>
                                    <span class="switch-label">Acesso a Seguro Residencial</span>
                                </label>
                            </div>
                            <label class="switch">
                                <input type="checkbox" name="perm_suporte" value="S" class="perm-check" {!! $valores['perm_suporte'] ? 'checked' : '' !!}>
                                <span class="switch-track"></span>
                                <span class="switch-label">Pode abrir chamados de suporte</span>
                            </label>
                            <label class="switch">
                                <input type="checkbox" name="perm_aceitar" value="S" class="perm-check" {!! $valores['perm_aceitar'] ? 'checked' : '' !!}>
                                <span class="switch-track"></span>
                                <span class="switch-label">Pode aceitar propostas</span>
                            </label>
                        </div>
                    </div>

                </div>
            </form>

        
@endsection

@push('antes-scripts')
<script>
(function () {
    var form = document.getElementById('form-criar-usuario');
    if (!form) return;

    var adminCb    = form.querySelector('#perm-admin');
    var cotarCb    = form.querySelector('#perm-cotar');
    var outrosCbs  = form.querySelectorAll('.perm-check');
    var produtoCbs = form.querySelectorAll('.perm-produto');
    var produtosWrap = form.querySelector('#perm-produtos-wrap');

    function sincronizarProdutos() {
        produtosWrap.hidden = !cotarCb.checked;
        if (!cotarCb.checked) {
            produtoCbs.forEach(function (cb) { cb.checked = false; });
        }
    }

    function sincronizarAdmin() {
        outrosCbs.forEach(function (cb) {
            if (adminCb.checked) cb.checked = true;
            cb.disabled = adminCb.checked;
        });
        produtoCbs.forEach(function (cb) { cb.disabled = adminCb.checked; });
        if (adminCb.checked) {
            produtosWrap.hidden = false;
        } else {
            sincronizarProdutos();
        }
    }

    adminCb.addEventListener('change', sincronizarAdmin);
    cotarCb.addEventListener('change', sincronizarProdutos);
    sincronizarAdmin();
    sincronizarProdutos();
}());
</script>
@endpush
