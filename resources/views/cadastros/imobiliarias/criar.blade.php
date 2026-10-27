@extends('layouts.app')

@section('content')
            <form
                method="POST"
                action="{{ url('/admin/imobiliarias/criar') }}"
                novalidate
                id="form-criar-imobiliaria"
                class="cotacao-container form-layout-70-30"
            >
                <input type="hidden" name="_csrf" value="{{ csrf_token() }}">

                <div class="form-layout-main">

                    <!-- Seção: Dados da imobiliária -->
                    <div class="panel">
                        <div class="panel-header">
                            <h2>{!! icon('buildings') !!} Dados da imobiliária</h2>
                        </div>
                        <div class="panel-body">
                            <div class="form-section">

                                <div class="field">
                                    <label class="switch">
                                        <input type="checkbox" id="eh-cliente-sami" name="eh_cliente_sami" value="1" {!! $valores['eh_cliente_sami'] ? 'checked' : '' !!}>
                                        <span class="switch-track"></span>
                                        <span class="switch-label">É cliente Sami?</span>
                                    </label>
                                </div>

                                <div class="field visita-imob-field" id="bloco-busca-institucional" hidden>
                                    <label class="field-label">Buscar no sistema institucional</label>
                                    <div class="field-input-wrap">
                                        <span class="field-input-icon">{!! icon('magnifying-glass') !!}</span>
                                        <input type="text" id="institucional-busca" class="field-input has-icon" placeholder="Digite o nome da imobiliária..." autocomplete="off">
                                    </div>
                                    <input type="hidden" name="institucional_sigla" id="institucional-sigla" value="{{ $valores['institucional_sigla'] }}">
                                    <ul class="visita-imob-sugestoes" id="institucional-sugestoes" hidden></ul>
                                    <div class="imob-institucional-chip" id="institucional-chip" hidden>
                                        <span class="status-pill status-pill-pendente" id="institucional-chip-sistema"></span>
                                        <span id="institucional-chip-texto"></span>
                                        <button type="button" class="imob-institucional-chip-limpar" id="institucional-chip-limpar" title="Limpar seleção">{!! icon('x') !!}</button>
                                    </div>
                                </div>

                                @php
                                /* Sistema: só existem esses 2 na prática pra integração de imóvel por
                                   código (ERP direto ou Samiloca). Vem sozinho da busca institucional,
                                   mas o admin pode trocar manualmente aqui — caso do cliente Sami cujo
                                   cadastro institucional está como ERP++ mas que na prática usa o site
                                   Samiloca (ou vice-versa). */
                                $sistema_eh_samiloca = strtoupper((string) $valores['institucional_sistema']) === 'SAMILOCA';
                                
@endphp
                                <div class="field" id="bloco-institucional-sistema" hidden>
                                    <label class="field-label">Sistema</label>
                                    <div class="imob-sistema-toggle">
                                        <span class="imob-sistema-toggle-label{!! $sistema_eh_samiloca ? '' : ' is-active' !!}" data-sistema-label="erp">SAMI ERP</span>
                                        <label class="switch">
                                            <input type="checkbox" id="institucional-sistema-toggle" {!! $sistema_eh_samiloca ? 'checked' : '' !!}>
                                            <span class="switch-track"></span>
                                        </label>
                                        <span class="imob-sistema-toggle-label{!! $sistema_eh_samiloca ? ' is-active' : '' !!}" data-sistema-label="samiloca">SAMILOCA</span>
                                    </div>
                                    <input type="hidden" name="institucional_sistema" id="institucional-sistema" value="{{ $valores['institucional_sistema'] ?: 'ERP++' }}">
                                    <span class="field-hint">Preenchido automaticamente pela busca acima — troque aqui se o cliente usa outro sistema na prática.</span>
                                </div>

                                @php
                                /* URL da API SAMILOCA: só aparece para clientes desse sistema. Muda
                                   conforme quem fez o site (domínio próprio ou subdomínio da Sami) e
                                   não existe no cadastro institucional, por isso é informada aqui. */
                                
@endphp
                                <div class="field" id="bloco-samiloca-url" hidden>
                                    <label class="field-label" for="institucional-site">URL do site (API SAMILOCA)</label>
                                    <div class="imob-samiloca-linha">
                                        <div class="field-input-wrap imob-samiloca-input">
                                            <span class="field-input-icon">{!! icon('monitor') !!}</span>
                                            <input type="text" class="field-input has-icon" id="institucional-site" name="institucional_site"
                                                   value="{{ $valores['institucional_site'] }}"
                                                   placeholder="https://imobiliaria.samiloca.com.br" autocomplete="off">
                                        </div>
                                        <button type="button" class="btn btn-sm btn-secondary" id="samiloca-detectar">{!! icon('magnifying-glass') !!} Detectar</button>
                                        <button type="button" class="btn btn-sm btn-secondary" id="samiloca-testar">{!! icon('check') !!} Testar</button>
                                    </div>
                                    <span class="field-hint imob-samiloca-status" id="samiloca-status">Usada na busca de imóvel por código na cotação de fiança.</span>

                                    <div class="form-grid" style="margin-top:var(--space-3)">
                                        <div class="field">
                                            <label class="field-label" for="samiloca-api-user">Usuário da API</label>
                                            <div class="field-input-wrap">
                                                <span class="field-input-icon">{!! icon('user') !!}</span>
                                                <input type="text" class="field-input has-icon" id="samiloca-api-user" name="samiloca_api_user"
                                                       value="{{ $valores['samiloca_api_user'] }}"
                                                       placeholder="api@imobiliaria.com.br" autocomplete="off">
                                            </div>
                                        </div>
                                        <div class="field">
                                            <label class="field-label" for="samiloca-api-pass">Senha da API</label>
                                            <div class="field-input-wrap">
                                                <span class="field-input-icon">{!! icon('lock') !!}</span>
                                                <input type="text" class="field-input has-icon" id="samiloca-api-pass" name="samiloca_api_pass"
                                                       value="{{ $valores['samiloca_api_pass'] }}"
                                                       placeholder="senha da API" autocomplete="off">
                                            </div>
                                        </div>
                                    </div>
                                    <span class="field-hint">Cada cliente Samiloca tem usuário/senha próprios de API — se não preencher, o sistema usa o par global do .env (nem sempre funciona pra esse cliente).</span>
                                </div>

                                <div class="field">
                                    <label class="field-label required" for="nome">
                                        Nome <span class="required">*</span>
                                        @if (!empty($erros['nome']))
                                            <span class="field-error">{{ $erros['nome'] }}</span>
                                        @endif
                                    </label>
                                    <div class="field-input-wrap">
                                        <span class="field-input-icon">{!! icon('buildings') !!}</span>
                                        <input
                                            class="field-input has-icon{!! !empty($erros['nome']) ? ' is-error' : '' !!}"
                                            type="text"
                                            id="nome"
                                            name="nome"
                                            value="{{ $valores['nome'] }}"
                                            placeholder="Ex.: Imobiliária Central"
                                            maxlength="200"
                                            required
                                            autocomplete="organization"
                                        >
                                    </div>
                                </div>

                                <div class="form-grid">
                                    <div class="field">
                                        <label class="field-label" for="cnpj">
                                            CNPJ <span class="required">*</span>
                                            @if (!empty($erros['cnpj']))
                                                <span class="field-error">{{ $erros['cnpj'] }}</span>
                                            @endif
                                        </label>
                                        <div class="field-input-wrap">
                                            <span class="field-input-icon">{!! icon('identification-card') !!}</span>
                                            <input class="field-input has-icon{!! !empty($erros['cnpj']) ? ' is-error' : '' !!}" type="text" id="cnpj" name="cnpj" data-mask="cpfcnpj" value="{{ $valores['cnpj'] }}" placeholder="00.000.000/0000-00" maxlength="18" required autocomplete="off">
                                        </div>
                                    </div>
                                    <div class="field">
                                        <label class="field-label" for="telefone">
                                            Telefone <span class="required">*</span>
                                            @if (!empty($erros['telefone']))
                                                <span class="field-error">{{ $erros['telefone'] }}</span>
                                            @endif
                                        </label>
                                        <div class="field-input-wrap">
                                            <span class="field-input-icon">{!! icon('phone') !!}</span>
                                            <input class="field-input has-icon{!! !empty($erros['telefone']) ? ' is-error' : '' !!}" type="text" id="telefone" name="telefone" data-mask="phone" value="{{ $valores['telefone'] }}" placeholder="(00) 00000-0000" maxlength="20" required autocomplete="tel">
                                        </div>
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
                                        <input class="field-input has-icon{!! !empty($erros['email']) ? ' is-error' : '' !!}" type="email" id="email" name="email" value="{{ $valores['email'] }}" placeholder="contato@imobiliaria.com.br" required autocomplete="email">
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>

                    <!-- Seção: Endereço -->
                    <div class="panel">
                        <div class="panel-header">
                            <h2>{!! icon('map-pin') !!} Endereço</h2>
                        </div>
                        <div class="panel-body">
                            <div class="form-section">
                                <div class="form-grid">
                                    <div class="field">
                                        <label class="field-label" for="cep">
                                            CEP <span class="required">*</span>
                                            @if (!empty($erros['cep']))
                                                <span class="field-error">{{ $erros['cep'] }}</span>
                                            @endif
                                        </label>
                                        <div class="field-input-wrap">
                                            <span class="field-input-icon">{!! icon('map-pin') !!}</span>
                                            <input class="field-input has-icon{!! !empty($erros['cep']) ? ' is-error' : '' !!}" type="text" id="cep" name="cep" data-mask="cep" value="{{ $valores['cep'] }}" maxlength="9" placeholder="00000-000" required autocomplete="off">
                                        </div>
                                    </div>
                                    <div class="field">
                                        <label class="field-label" for="endereco">
                                            Rua/Avenida <span class="required">*</span>
                                            @if (!empty($erros['endereco']))
                                                <span class="field-error">{{ $erros['endereco'] }}</span>
                                            @endif
                                        </label>
                                        <div class="field-input-wrap">
                                            <span class="field-input-icon">{!! icon('house') !!}</span>
                                            <input class="field-input has-icon{!! !empty($erros['endereco']) ? ' is-error' : '' !!}" type="text" id="endereco" name="endereco" value="{{ $valores['endereco'] }}" placeholder="Nome da rua/avenida" maxlength="200" required autocomplete="off">
                                        </div>
                                    </div>
                                </div>
                                <div class="form-grid-3">
                                    <div class="field">
                                        <label class="field-label" for="numero">
                                            Número <span class="required">*</span>
                                            @if (!empty($erros['numero']))
                                                <span class="field-error">{{ $erros['numero'] }}</span>
                                            @endif
                                        </label>
                                        <div class="field-input-wrap">
                                            <span class="field-input-icon">{!! icon('key') !!}</span>
                                            <input class="field-input has-icon{!! !empty($erros['numero']) ? ' is-error' : '' !!}" type="text" id="numero" name="numero" value="{{ $valores['numero'] }}" placeholder="Nº" maxlength="20" required autocomplete="off">
                                        </div>
                                    </div>
                                    <div class="field">
                                        <label class="field-label" for="complemento">Complemento</label>
                                        <div class="field-input-wrap">
                                            <span class="field-input-icon">{!! icon('note-pencil') !!}</span>
                                            <input class="field-input has-icon" type="text" id="complemento" name="complemento" value="{{ $valores['complemento'] }}" placeholder="Apto, bloco, etc." maxlength="60" autocomplete="off">
                                        </div>
                                    </div>
                                    <div class="field">
                                        <label class="field-label" for="bairro">
                                            Bairro <span class="required">*</span>
                                            @if (!empty($erros['bairro']))
                                                <span class="field-error">{{ $erros['bairro'] }}</span>
                                            @endif
                                        </label>
                                        <div class="field-input-wrap">
                                            <span class="field-input-icon">{!! icon('buildings') !!}</span>
                                            <input class="field-input has-icon{!! !empty($erros['bairro']) ? ' is-error' : '' !!}" type="text" id="bairro" name="bairro" value="{{ $valores['bairro'] }}" placeholder="Nome do bairro" maxlength="100" required autocomplete="off">
                                        </div>
                                    </div>
                                </div>
                                <div class="form-grid">
                                    <div class="field">
                                        <label class="field-label" for="cidade">
                                            Cidade <span class="required">*</span>
                                            @if (!empty($erros['cidade']))
                                                <span class="field-error">{{ $erros['cidade'] }}</span>
                                            @endif
                                        </label>
                                        <div class="field-input-wrap">
                                            <span class="field-input-icon">{!! icon('buildings') !!}</span>
                                            <input class="field-input has-icon{!! !empty($erros['cidade']) ? ' is-error' : '' !!}" type="text" id="cidade" name="cidade" value="{{ $valores['cidade'] }}" placeholder="Nome da cidade" maxlength="100" required autocomplete="off">
                                        </div>
                                    </div>
                                    <div class="field">
                                        <label class="field-label" for="uf">
                                            UF <span class="required">*</span>
                                            @if (!empty($erros['uf']))
                                                <span class="field-error">{{ $erros['uf'] }}</span>
                                            @endif
                                        </label>
                                        <div class="field-input-wrap">
                                            <span class="field-input-icon">{!! icon('buildings') !!}</span>
                                            <input class="field-input has-icon{!! !empty($erros['uf']) ? ' is-error' : '' !!}" type="text" id="uf" name="uf" value="{{ $valores['uf'] }}" maxlength="2" placeholder="SP" required autocomplete="off">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Seção: Usuário administrador -->
                    <div class="panel">
                        <div class="panel-header">
                            <h2>{!! icon('user') !!} Usuário administrador</h2>
                        </div>
                        <div class="panel-body">
                            <div class="form-section no-margin-bottom" id="bloco-admin-usuario">
                                <div class="form-grid">
                                    <div class="field">
                                        <label class="field-label" for="admin_username">
                                            Nome <span class="required">*</span>
                                            @if (!empty($erros['admin_username']))
                                                <span class="field-error">{{ $erros['admin_username'] }}</span>
                                            @endif
                                        </label>
                                        <div class="field-input-wrap">
                                            <span class="field-input-icon">{!! icon('user') !!}</span>
                                            <input class="field-input has-icon{!! !empty($erros['admin_username']) ? ' is-error' : '' !!}"
                                                   type="text" id="admin_username" name="admin_username"
                                                   placeholder="Nome completo" value="{{ $valores['admin_username'] }}" maxlength="100">
                                        </div>
                                    </div>
                                    <div class="field">
                                        <label class="field-label" for="admin_email">
                                            E-mail <span class="required">*</span>
                                            @if (!empty($erros['admin_email']))
                                                <span class="field-error">{{ $erros['admin_email'] }}</span>
                                            @endif
                                        </label>
                                        <div class="field-input-wrap">
                                            <span class="field-input-icon">{!! icon('envelope-simple') !!}</span>
                                            <input class="field-input has-icon{!! !empty($erros['admin_email']) ? ' is-error' : '' !!}"
                                                   type="email" id="admin_email" name="admin_email"
                                                   placeholder="email@exemplo.com" value="{{ $valores['admin_email'] }}" maxlength="255">
                                        </div>
                                    </div>
                                </div>
                                <div class="field span-full">
                                    <label class="field-label" for="admin_telefone">
                                        Telefone <span class="required">*</span>
                                        @if (!empty($erros['admin_telefone']))
                                            <span class="field-error">{{ $erros['admin_telefone'] }}</span>
                                        @endif
                                    </label>
                                    <div class="field-input-wrap">
                                        <span class="field-input-icon">{!! icon('phone') !!}</span>
                                        <input class="field-input has-icon{!! !empty($erros['admin_telefone']) ? ' is-error' : '' !!}" type="text" id="admin_telefone" name="admin_telefone"
                                               data-mask="phone" placeholder="(00) 00000-0000" value="{{ $valores['admin_telefone'] }}" maxlength="16" required>
                                    </div>
                                </div>
                                <div class="form-grid">
                                    <div class="field">
                                        <label class="field-label" for="admin_senha">
                                            Senha <span class="required">*</span>
                                            @if (!empty($erros['admin_senha']))
                                                <span class="field-error">{{ $erros['admin_senha'] }}</span>
                                            @endif
                                        </label>
                                        <div class="field-input-wrap">
                                            <span class="field-input-icon">{!! icon('lock') !!}</span>
                                            <input class="field-input has-icon has-icon-both{!! !empty($erros['admin_senha']) ? ' is-error' : '' !!}"
                                                   type="password" id="admin_senha" name="admin_senha"
                                                   minlength="6" autocomplete="new-password" placeholder="••••••••">
                                        <button type="button" class="field-password-toggle" data-toggle="password" data-target="#admin_senha" aria-label="Mostrar senha">
                                            {!! icon('eye', 'icon-eye') !!}
                                            {!! icon('eye-slash', 'icon-eye-slash') !!}
                                        </button>
                                        </div>
                                    </div>
                                    <div class="field">
                                        <label class="field-label" for="admin_senha_confirmar">
                                            Confirmar senha <span class="required">*</span>
                                            @if (!empty($erros['admin_senha_confirmar']))
                                                <span class="field-error">{{ $erros['admin_senha_confirmar'] }}</span>
                                            @endif
                                        </label>
                                        <div class="field-input-wrap">
                                            <span class="field-input-icon">{!! icon('lock') !!}</span>
                                            <input class="field-input has-icon has-icon-both{!! !empty($erros['admin_senha_confirmar']) ? ' is-error' : '' !!}"
                                                   type="password" id="admin_senha_confirmar" name="admin_senha_confirmar"
                                                   autocomplete="new-password" placeholder="••••••••">
                                        <button type="button" class="field-password-toggle" data-toggle="password" data-target="#admin_senha_confirmar" aria-label="Mostrar confirmação de senha">
                                            {!! icon('eye', 'icon-eye') !!}
                                            {!! icon('eye-slash', 'icon-eye-slash') !!}
                                        </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="form-layout-side">

                    <!-- Seção: Comissões -->
                    <div class="panel">
                        <div class="panel-header">
                            <h2>{!! icon('currency-dollar') !!} Comissões</h2>
                        </div>
                        <div class="panel-body">
                            <p class="field-hint">Habilite os seguros que essa imobiliária trabalha e defina a comissão de cada um.</p>
                            <div class="imob-comissao-lista">
                                @foreach (IMOB_TIPOS_SEGURO as $tipo => $label)
                                <div class="imob-comissao-row" data-tipo="{{ $tipo }}">
                                    <label class="switch">
                                        <input type="checkbox" class="imob-seguro-check" name="seguro_{{ $tipo }}" value="1" data-tipo="{{ $tipo }}">
                                        <span class="switch-track"></span>
                                        <span class="switch-label">{{ $label }}</span>
                                    </label>
                                    <div class="imob-comissao-controles" data-tipo="{{ $tipo }}" hidden>
                                        <div class="visita-comissao-modo">
                                            <label class="field-radio"><input type="radio" name="com_modo_{{ $tipo }}" value="percentual" checked> %</label>
                                            <label class="field-radio"><input type="radio" name="com_modo_{{ $tipo }}" value="valor"> R$</label>
                                        </div>
                                        <div class="field-input-wrap imob-comissao-valor-wrap">
                                            <span class="field-input-prefix" hidden>R$</span>
                                            <input type="text" inputmode="decimal" class="field-input has-suffix imob-comissao-valor" name="com_valor_{{ $tipo }}" data-mask="percentual" placeholder="0,00">
                                            <span class="field-input-suffix">%</span>
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <!-- Seção: Região -->
                    <div class="panel">
                        <div class="panel-header">
                            <h2>{!! icon('map-pin') !!} Região</h2>
                        </div>
                        <div class="panel-body">
                            <div class="form-section">
                                <div class="field">
                                    <label class="field-label">
                                        Região de atendimento
                                        <button type="button" class="visita-regiao-cadastrar-btn" id="btn-regiao-cadastrar" title="Gerenciar tipos de região">
                                            {!! icon('plus') !!}
                                        </button>
                                    </label>
                                    {!! custom_select('regiao_id', $opcoes_regiao, 'Selecione...', $valores['regiao_id'] ?: null) !!}
                                </div>
                            </div>
                        </div>
                    </div>

                    @if (FEATURE_EMBED_UI)
                    <!-- Canais de cotação (Manual / API / Segimob por produto) -->
                    @include('cadastros.imobiliarias._canais', ['imobiliaria_id_atual' => 0])

                    <!-- Configurações -->
                    <div class="panel">
                        <div class="panel-header">
                            <h2>{!! icon('gear') !!} Configurações</h2>
                        </div>
                        <div class="panel-body">
                          <div class="form-section">

                            <div class="field">
                                <label class="switch">
                                    <input type="checkbox" id="usa-embed" name="usa_embed" value="1" {!! $valores['usa_embed'] ? 'checked' : '' !!}>
                                    <span class="switch-track"></span>
                                    <span class="switch-label">Usar embed (cotação sem login)?</span>
                                </label>
                                <span class="field-hint">Libera para a imobiliária um link de <code>&lt;iframe&gt;</code> por tipo de cotação, para embutir no sistema do cliente sem precisar de login.</span>
                            </div>

                            <div class="field" id="bloco-embed-dominio" hidden>
                                <label class="field-label" for="embed_dominio">Domínio autorizado a embutir</label>
                                <div class="field-input-wrap">
                                    <span class="field-input-icon">{!! icon('monitor') !!}</span>
                                    <input class="field-input has-icon" type="text" id="embed_dominio" name="embed_dominio" value="{{ $valores['embed_dominio'] }}" placeholder="crm.imobiliaria.com.br" maxlength="255">
                                </div>
                                <span class="field-hint">Domínio do site/sistema do cliente onde o iframe vai rodar (sem "https://").</span>
                            </div>

                          </div>
                        </div>
                    </div>
                    @endif

                    <!-- Botões de ação -->
                    <div class="form-actions form-actions-side">
                        <button type="submit" class="btn btn-primary">
                            {!! icon('check') !!}
                            Salvar
                        </button>
                        <a href="{{ url('/admin/imobiliarias') }}" class="btn btn-secondary">
                            Cancelar
                        </a>
                    </div>

                </div>

            </form>

        
@endsection
