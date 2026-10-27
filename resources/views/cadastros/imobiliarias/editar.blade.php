@extends('layouts.app')

@section('content')
            <!-- KPIs -->
            <div class="kpi-grid kpi-grid-4">
                <div class="kpi-card">
                    <div class="kpi-card-info">
                        <span>Total de cotações</span>
                        <div class="kpi-card-value">{!! $total_cotacoes !!}</div>
                    </div>
                    <div class="kpi-card-icon is-neutral">{!! icon('clipboard-text') !!}</div>
                </div>

                <div class="kpi-card">
                    <div class="kpi-card-info">
                        <span>Usuários cadastrados</span>
                        <div class="kpi-card-value">{!! $total_usuarios !!}</div>
                    </div>
                    <div class="kpi-card-icon is-neutral">{!! icon('users') !!}</div>
                </div>

                <div class="kpi-card">
                    <div class="kpi-card-info">
                        <span>Status</span>
                        <div class="kpi-card-value">
                            <span class="status-pill {!! $valores['ativo'] ? 'status-pill-aprovado' : 'status-pill-recusado' !!}">
                                {!! $valores['ativo'] ? 'Ativa' : 'Inativa' !!}
                            </span>
                        </div>
                    </div>
                    <div class="kpi-card-icon is-neutral">{!! icon('buildings') !!}</div>
                </div>

                <div class="kpi-card">
                    <div class="kpi-card-info">
                        <span>Cadastrada em</span>
                        <div class="kpi-card-value">{{ $criado_em }}</div>
                    </div>
                    <div class="kpi-card-icon is-neutral">{!! icon('calendar-blank') !!}</div>
                </div>
            </div>

            <!-- Formulário de edição -->
            <form method="POST" action="{{ BASE_URI }}/admin/imobiliarias/{{ $slug_param }}/editar" novalidate class="cotacao-container form-layout-70-30">
                <input type="hidden" name="_csrf" value="{{ csrf_token() }}">

                <div class="form-layout-main">

                    <div class="panel">
                        <div class="panel-header">
                            <h2>{!! icon('identification-card') !!} Dados da Imobiliária</h2>
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

                                <div class="field visita-imob-field" id="bloco-busca-institucional" {!! $valores['eh_cliente_sami'] ? '' : 'hidden' !!}>
                                    <label class="field-label">Buscar no sistema institucional</label>
                                    <div class="field-input-wrap">
                                        <span class="field-input-icon">{!! icon('magnifying-glass') !!}</span>
                                        <input type="text" id="institucional-busca" class="field-input has-icon" placeholder="Digite o nome da imobiliária..." autocomplete="off">
                                    </div>
                                    <input type="hidden" name="institucional_sigla" id="institucional-sigla" value="{{ $valores['institucional_sigla'] }}">
                                    <ul class="visita-imob-sugestoes" id="institucional-sugestoes" hidden></ul>
                                    <div class="imob-institucional-chip" id="institucional-chip" {!! $valores['institucional_sigla'] ? '' : 'hidden' !!}>
                                        <span class="status-pill status-pill-pendente" id="institucional-chip-sistema">{{ $valores['institucional_sistema'] }}</span>
                                        <span id="institucional-chip-texto">{{ $valores['nome'] }} ({{ $valores['institucional_sigla'] }})</span>
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
                                <div class="field" id="bloco-institucional-sistema" {!! $valores['eh_cliente_sami'] ? '' : 'hidden' !!}>
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
                                $mostra_samiloca = $valores['eh_cliente_sami']
                                    && strtoupper((string) $valores['institucional_sistema']) === 'SAMILOCA';
                                
@endphp
                                <div class="field" id="bloco-samiloca-url" {!! $mostra_samiloca ? '' : 'hidden' !!}>
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
                                    <label class="field-label required" for="nome">Nome da imobiliária</label>
                                    <div class="field-input-wrap">
                                        <span class="field-input-icon">{!! icon('buildings') !!}</span>
                                        <input
                                            class="field-input has-icon{!! !empty($erros['nome']) ? ' has-error' : '' !!}"
                                            type="text"
                                            id="nome"
                                            name="nome"
                                            value="{{ $valores['nome'] }}"
                                            placeholder="Ex.: Imobiliária Central"
                                            required
                                            maxlength="120"
                                        >
                                    </div>
                                    <span class="field-error">{{ $erros['nome'] ?? '' }}</span>
                                </div>

                                <div class="form-grid">
                                    <div class="field">
                                        <label class="field-label" for="cnpj">CNPJ</label>
                                        <div class="field-input-wrap">
                                            <span class="field-input-icon">{!! icon('identification-card') !!}</span>
                                            <input class="field-input has-icon" type="text" id="cnpj" name="cnpj" data-mask="cpfcnpj" value="{{ $valores['cnpj'] }}" placeholder="00.000.000/0000-00" maxlength="18">
                                        </div>
                                    </div>
                                    <div class="field">
                                        <label class="field-label" for="telefone">Telefone</label>
                                        <div class="field-input-wrap">
                                            <span class="field-input-icon">{!! icon('phone') !!}</span>
                                            <input class="field-input has-icon" type="text" id="telefone" name="telefone" data-mask="phone" value="{{ $valores['telefone'] }}" placeholder="(00) 00000-0000" maxlength="20">
                                        </div>
                                    </div>
                                </div>

                                <div class="field">
                                    <label class="field-label" for="email">E-mail</label>
                                    <div class="field-input-wrap">
                                        <span class="field-input-icon">{!! icon('envelope-simple') !!}</span>
                                        <input class="field-input has-icon{!! !empty($erros['email']) ? ' has-error' : '' !!}" type="email" id="email" name="email" value="{{ $valores['email'] }}" placeholder="contato@imobiliaria.com.br" maxlength="120">
                                    </div>
                                    <span class="field-error">{{ $erros['email'] ?? '' }}</span>
                                </div>

                            </div>
                        </div>
                    </div>

                    <!-- Endereço -->
                    <div class="panel">
                        <div class="panel-header">
                            <h2>{!! icon('map-pin') !!} Endereço</h2>
                        </div>
                        <div class="panel-body">
                            <div class="form-section">
                                <div class="form-grid">
                                    <div class="field">
                                        <label class="field-label" for="cep">CEP</label>
                                        <div class="field-input-wrap">
                                            <span class="field-input-icon">{!! icon('map-pin') !!}</span>
                                            <input class="field-input has-icon" type="text" id="cep" name="cep" data-mask="cep" value="{{ $valores['cep'] }}" maxlength="9" placeholder="00000-000">
                                        </div>
                                    </div>
                                    <div class="field">
                                        <label class="field-label" for="endereco">Rua/Avenida</label>
                                        <div class="field-input-wrap">
                                            <span class="field-input-icon">{!! icon('house') !!}</span>
                                            <input class="field-input has-icon" type="text" id="endereco" name="endereco" value="{{ $valores['endereco'] }}" placeholder="Nome da rua/avenida" maxlength="200">
                                        </div>
                                    </div>
                                </div>
                                <div class="form-grid-3">
                                    <div class="field">
                                        <label class="field-label" for="numero">Número</label>
                                        <div class="field-input-wrap">
                                            <span class="field-input-icon">{!! icon('key') !!}</span>
                                            <input class="field-input has-icon" type="text" id="numero" name="numero" value="{{ $valores['numero'] }}" placeholder="Nº" maxlength="20">
                                        </div>
                                    </div>
                                    <div class="field">
                                        <label class="field-label" for="complemento">Complemento</label>
                                        <div class="field-input-wrap">
                                            <span class="field-input-icon">{!! icon('note-pencil') !!}</span>
                                            <input class="field-input has-icon" type="text" id="complemento" name="complemento" value="{{ $valores['complemento'] }}" placeholder="Apto, bloco, etc." maxlength="60">
                                        </div>
                                    </div>
                                    <div class="field">
                                        <label class="field-label" for="bairro">Bairro</label>
                                        <div class="field-input-wrap">
                                            <span class="field-input-icon">{!! icon('buildings') !!}</span>
                                            <input class="field-input has-icon" type="text" id="bairro" name="bairro" value="{{ $valores['bairro'] }}" placeholder="Nome do bairro" maxlength="100">
                                        </div>
                                    </div>
                                </div>
                                <div class="form-grid">
                                    <div class="field">
                                        <label class="field-label" for="cidade">Cidade</label>
                                        <div class="field-input-wrap">
                                            <span class="field-input-icon">{!! icon('buildings') !!}</span>
                                            <input class="field-input has-icon" type="text" id="cidade" name="cidade" value="{{ $valores['cidade'] }}" placeholder="Nome da cidade" maxlength="100">
                                        </div>
                                    </div>
                                    <div class="field">
                                        <label class="field-label" for="uf">UF</label>
                                        <div class="field-input-wrap">
                                            <span class="field-input-icon">{!! icon('buildings') !!}</span>
                                            <input class="field-input has-icon" type="text" id="uf" name="uf" value="{{ $valores['uf'] }}" maxlength="2" placeholder="SP">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="form-layout-side">

                    <!-- Comissões -->
                    <div class="panel">
                        <div class="panel-header">
                            <h2>{!! icon('currency-dollar') !!} Comissões</h2>
                        </div>
                        <div class="panel-body">
                            <div class="imob-comissao-lista">
                                @foreach (IMOB_TIPOS_SEGURO as $tipo => $label)
                                @php
                                    $c = $comissoes_atuais[$tipo];
                                    $isValor = $c['modo'] === 'valor';
                                @endphp
                                <div class="imob-comissao-row" data-tipo="{{ $tipo }}">
                                    <label class="switch">
                                        <input type="checkbox" class="imob-seguro-check" name="seguro_{{ $tipo }}" value="1" data-tipo="{{ $tipo }}" {!! $c['ativo'] ? 'checked' : '' !!}>
                                        <span class="switch-track"></span>
                                        <span class="switch-label">{{ $label }}</span>
                                    </label>
                                    <div class="imob-comissao-controles" data-tipo="{{ $tipo }}" {!! $c['ativo'] ? '' : 'hidden' !!}>
                                        <div class="visita-comissao-modo">
                                            <label class="field-radio"><input type="radio" name="com_modo_{{ $tipo }}" value="percentual" {!! !$isValor ? 'checked' : '' !!}> %</label>
                                            <label class="field-radio"><input type="radio" name="com_modo_{{ $tipo }}" value="valor" {!! $isValor ? 'checked' : '' !!}> R$</label>
                                        </div>
                                        <div class="field-input-wrap imob-comissao-valor-wrap">
                                            <span class="field-input-prefix" {!! $isValor ? '' : 'hidden' !!}>R$</span>
                                            <input type="text" inputmode="decimal" class="field-input {!! $isValor ? 'has-prefix' : 'has-suffix' !!} imob-comissao-valor" name="com_valor_{{ $tipo }}" data-mask="percentual" placeholder="0,00" value="{!! $c['valor'] ? e(number_format((float) $c['valor'], 2, ',', '')) : '' !!}">
                                            <span class="field-input-suffix" {!! $isValor ? 'hidden' : '' !!}>%</span>
                                        </div>
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>
                    </div>

                    <!-- Região -->
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
                                    {!! custom_select('regiao_id', $opcoes_regiao, 'Selecione...', $valores['regiao_id'] ? (string) $valores['regiao_id'] : null) !!}
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Canais de cotação (Manual / API / Segimob por produto) -->
                    @include('cadastros.imobiliarias._canais', ['imobiliaria_id_atual' => $id])

                    <!-- Configurações -->
                    <div class="panel">
                        <div class="panel-header">
                            <h2>{!! icon('gear') !!} Configurações</h2>
                        </div>
                        <div class="panel-body">
                          <div class="form-section">

                            <div class="field">
                                <label class="switch">
                                    <input type="checkbox" name="ativo" value="1" {!! $valores['ativo'] ? 'checked' : '' !!}>
                                    <span class="switch-track"></span>
                                    <span class="switch-label">Imobiliária ativa</span>
                                </label>
                                <span class="field-hint">Imobiliárias inativas não conseguem acessar o sistema nem abrir novas cotações.</span>
                            </div>

                            <div class="field">
                                <a href="{{ url('/admin/imobiliarias/' . $slug_param . '/usuarios') }}" class="btn btn-secondary btn-sm">
                                    {!! icon('users') !!} Usuários da imobiliária
                                </a>
                            </div>

                            @if (FEATURE_EMBED_UI)
                            <div class="field">
                                <label class="switch">
                                    <input type="checkbox" id="usa-embed" name="usa_embed" value="1" {!! $valores['usa_embed'] ? 'checked' : '' !!}>
                                    <span class="switch-track"></span>
                                    <span class="switch-label">Usar embed (cotação sem login)?</span>
                                </label>
                                <span class="field-hint">Libera para a imobiliária um link de <code>&lt;iframe&gt;</code> por tipo de cotação, para embutir no sistema do cliente sem precisar de login.</span>
                            </div>

                            <div class="field" id="bloco-embed-dominio" {!! $valores['usa_embed'] ? '' : 'hidden' !!}>
                                <label class="field-label" for="embed_dominio">Domínio autorizado a embutir</label>
                                <div class="field-input-wrap">
                                    <span class="field-input-icon">{!! icon('monitor') !!}</span>
                                    <input class="field-input has-icon" type="text" id="embed_dominio" name="embed_dominio" value="{{ $valores['embed_dominio'] }}" placeholder="crm.imobiliaria.com.br" maxlength="255">
                                </div>
                                <span class="field-hint">Domínio do site/sistema do cliente onde o iframe vai rodar (sem "https://").</span>
                            </div>
                            @endif

                          </div>
                        </div>
                    </div>

                    <!-- Ações do formulário -->
                    <div class="form-actions form-actions-side">
                        <button type="submit" class="btn btn-primary">
                            {!! icon('check') !!} Salvar alterações
                        </button>
                        <a href="{{ url('/admin/imobiliarias') }}" class="btn btn-secondary">
                            {!! icon('x') !!} Cancelar
                        </a>
                    </div>

                </div>

            </form>

        
@endsection
