@extends('layouts.app')

@section('content')
            <form id="form-perfil" class="cotacao-container" data-csrf="{{ csrf_token() }}" novalidate>

                <div class="form-layout-70-30">
                    <div class="form-layout-main">

                        <div class="panel">
                            <div class="panel-body">

                                <div class="alert alert-danger" data-form-alert hidden></div>

                                <div class="form-section">
                                    <h3 class="form-section-title">{!! icon('identification-card') !!} Dados pessoais</h3>
                                    <div class="form-grid">

                                        <div class="field">
                                            <label class="field-label" for="username">
                                                Nome <span class="required">*</span>
                                                <span class="field-error" data-error-for="username"></span>
                                            </label>
                                            <div class="field-input-wrap">
                                                <span class="field-input-icon">{!! icon('user') !!}</span>
                                                <input class="field-input has-icon" type="text" id="username" name="username"
                                                       value="{{ $usuario['username'] }}" required>
                                            </div>
                                        </div>

                                        <div class="field">
                                            <label class="field-label" for="email">
                                                E-mail <span class="required">*</span>
                                                <span class="field-error" data-error-for="email"></span>
                                            </label>
                                            <div class="field-input-wrap">
                                                <span class="field-input-icon">{!! icon('envelope-simple') !!}</span>
                                                <input class="field-input has-icon" type="email" id="email" name="email"
                                                       value="{{ $usuario['email'] }}" required>
                                            </div>
                                        </div>

                                        <div class="field span-full">
                                            <label class="field-label" for="telefone">
                                                Telefone
                                                <span class="field-error" data-error-for="telefone"></span>
                                            </label>
                                            <div class="field-input-wrap">
                                                <span class="field-input-icon">{!! icon('phone') !!}</span>
                                                <input class="field-input has-icon" type="text" id="telefone" name="telefone"
                                                       data-mask="phone" value="{{ $usuario['telefone'] ?? '' }}" placeholder="(00) 00000-0000">
                                            </div>
                                        </div>

                                        @if ($tabela === 'usuario')
                                        <div class="field span-full">
                                            <label class="field-label" for="email_cotacao">
                                                E-mail para receber cotações (opcional)
                                                <span class="field-error" data-error-for="email_cotacao"></span>
                                            </label>
                                            <div class="field-input-wrap">
                                                <span class="field-input-icon">{!! icon('envelope-simple') !!}</span>
                                                <input class="field-input has-icon" type="email" id="email_cotacao" name="email_cotacao"
                                                       value="{{ $usuario['email_cotacao'] ?? '' }}" placeholder="Se vazio, usaremos o e-mail de login">
                                            </div>
                                            <span class="field-hint">Se preenchido, este será usado para alertas de novas cotações.</span>
                                        </div>
                                        @endif

                                    </div>
                                </div>

                                <div class="form-section no-margin-bottom">
                                    <h3 class="form-section-title">{!! icon('lock') !!} Alterar senha</h3>
                                    <p class="form-section-hint">Deixe em branco para manter a senha atual.</p>
                                    <div class="form-grid">

                                        <div class="field">
                                            <label class="field-label" for="senha_atual">
                                                Senha atual
                                                <span class="field-error" data-error-for="senha_atual"></span>
                                            </label>
                                            <div class="field-input-wrap">
                                                <span class="field-input-icon">{!! icon('lock') !!}</span>
                                                <input class="field-input has-icon has-icon-both" type="password" id="senha_atual" name="senha_atual"
                                                       placeholder="••••••••" autocomplete="current-password">
                                                <button type="button" class="field-password-toggle" data-toggle="password" data-target="#senha_atual"
                                                        aria-label="Mostrar senha">
                                                    {!! icon('eye', 'icon-eye') !!}
                                                    {!! icon('eye-slash', 'icon-eye-slash') !!}
                                                </button>
                                            </div>
                                        </div>

                                        <div class="field">
                                            <label class="field-label" for="nova_senha">
                                                Nova senha
                                                <span class="field-error" data-error-for="nova_senha"></span>
                                            </label>
                                            <div class="field-input-wrap">
                                                <span class="field-input-icon">{!! icon('key') !!}</span>
                                                <input class="field-input has-icon has-icon-both" type="password" id="nova_senha" name="nova_senha"
                                                       placeholder="••••••••" minlength="6" autocomplete="new-password">
                                                <button type="button" class="field-password-toggle" data-toggle="password" data-target="#nova_senha"
                                                        aria-label="Mostrar senha">
                                                    {!! icon('eye', 'icon-eye') !!}
                                                    {!! icon('eye-slash', 'icon-eye-slash') !!}
                                                </button>
                                            </div>
                                            <span class="field-hint">Mínimo 6 caracteres</span>
                                        </div>

                                    </div>
                                </div>

                                <div class="stepper-nav">
                                    <div></div>
                                    <div class="stepper-nav-right">
                                        <button type="submit" class="btn btn-sm btn-primary">
                                            <span data-label>{!! icon('check') !!} Salvar alterações</span>
                                            <span class="spinner"></span>
                                        </button>
                                    </div>
                                </div>

                            </div>
                        </div>

                    </div>

                    <div class="form-layout-main">

                        <!-- Avatar -->
                        <div class="panel">
                            <div class="panel-header">
                                <h2>{!! icon('user') !!} Foto de perfil</h2>
                            </div>
                            <div class="panel-body perfil-avatar-panel">
                                <div class="perfil-avatar-wrap">
                                    @if (!empty($usuario['avatar']))
                                        <img src="{{ $usuario['avatar'] }}" alt="Avatar" class="perfil-avatar-img" id="perfil-avatar-img">
                                    @else
                                        <div class="perfil-avatar-placeholder" id="perfil-avatar-placeholder">
                                            {{ mb_strtoupper(mb_substr($usuario['username'], 0, 1)) }}
                                        </div>
                                    @endif
                                </div>
                                <input type="file" id="perfil-avatar-input" accept="image/png,image/jpeg,image/webp" hidden>
                                <button type="button" class="btn btn-secondary btn-sm" id="btn-trocar-avatar">
                                    {!! icon('user') !!} Trocar foto
                                </button>
                                <p class="perfil-avatar-hint">PNG, JPEG ou WEBP — até 2MB.</p>
                            </div>
                        </div>

                        @if (in_array($current_user->role ?? '', ['admin', 'imobiliaria'], true))
                        @include('cadastros._partials.dois-fatores-conta', ['df_tabela' => $tabela, 'df_usuario_id' => (int) $current_user->uid, 'df_proprio' => true])
                        @endif

                        <!-- Sessões ativas -->
                        <div class="panel">
                            <div class="panel-header">
                                <h2>{!! icon($sessao_atual['dispositivo'] === 'Celular' ? 'device-mobile' : 'monitor') !!} Sessões ativas</h2>
                            </div>
                            <div class="panel-body p-0">
                                <div class="acesso-item acesso-item-atual">
                                    <span class="acesso-icon">{!! icon($sessao_atual['dispositivo'] === 'Celular' ? 'device-mobile' : 'monitor') !!}</span>
                                    <div class="acesso-info">
                                        <span class="acesso-tempo">Esta sessão</span>
                                        <span class="acesso-meta">{{ $sessao_atual['navegador'] }} · Agora mesmo · {{ $ip_atual }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Últimos acessos -->
                        <div class="panel">
                            <div class="panel-header">
                                <h2>{!! icon('arrows-clockwise') !!} Últimos acessos</h2>
                            </div>
                            <div class="panel-body p-0 acessos-list">
                                @if (!$historico_acessos)
                                    <p class="acessos-empty">Nenhum acesso registrado ainda.</p>
                                @else
                                @foreach ($historico_acessos as $acesso)
                                    <div class="acesso-item">
                                        <span class="acesso-icon">{!! icon($acesso['dispositivo'] === 'Celular' ? 'device-mobile' : 'monitor') !!}</span>
                                        <div class="acesso-info">
                                            <span class="acesso-tempo">{{ ucfirst($acesso['tempo']) }}</span>
                                            <span class="acesso-meta">{{ $acesso['navegador'] }} · {{ $acesso['dispositivo'] }} · {{ $acesso['ip'] }}</span>
                                        </div>
                                        @if ($acesso['e_atual'])
                                            <span class="badge-atual">Atual</span>
                                        @endif
                                    </div>
                                @endforeach
                                @endif
                            </div>
                        </div>

                    </div>

                </div>
            </form>

        
@endsection
