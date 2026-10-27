{{--
    Formulário de cotação Título de Capitalização — 3 steps (era pages/cotacao/titulo/_form.php).
    Usado por: produtos/titulo/index (TituloController@index) e pela página de embed (Título).
    Variáveis esperadas:
      $rascunho_protocolo (opcional, string) — se preenchido, vai como <input hidden name="rascunho">.
    Helpers: cpf_field_titulo()/cep_field_titulo() em app/Legado/modulos/produtos.php.
--}}
@php
$estados_civis = ['Solteiro', 'Casado', 'Amasiado', 'Desquitado', 'Divorciado', 'Separado', 'Viúvo'];

$prazos = [];
for ($m = 1; $m <= 60; $m++) {
    $prazos[(string)$m] = "{$m} " . ($m === 1 ? 'mês' : 'meses');
}
@endphp

<!-- Indicador de progresso -->
<div class="stepper-header">
    <div class="stepper-step is-active" data-step-indicator="1">
        <span class="stepper-num">1</span>
        <span class="stepper-step-label">Contrato e Imóvel</span>
    </div>
    <div class="stepper-connector"></div>
    <div class="stepper-step" data-step-indicator="2">
        <span class="stepper-num">2</span>
        <span class="stepper-step-label">Inquilino</span>
    </div>
    <div class="stepper-connector"></div>
    <div class="stepper-step" data-step-indicator="3">
        <span class="stepper-num">3</span>
        <span class="stepper-step-label">Proprietário</span>
    </div>
</div>

<form id="form-cotacao-titulo" novalidate>
    @if (!empty($rascunho_protocolo))
    <input type="hidden" name="rascunho" value="{{ $rascunho_protocolo }}">
    @endif

    <!-- ══════════════════════════════════════════════════════════════════
         STEP 1 — Contrato e Imóvel
    ══════════════════════════════════════════════════════════════════ -->
    <div class="stepper-panel is-active" data-step="1">

        <div class="form-section">
            <h3 class="form-section-title">{!! icon('file-text') !!} Dados do Contrato</h3>

            <!-- Finalidade -->
            <div class="field">
                <label class="field-label">
                    Finalidade <span class="required">*</span>
                    <span class="field-error" hidden></span>
                </label>
                <div class="switch-group">
                    <label class="switch">
                        <input type="radio" name="finalidade" value="Residencial" required>
                        <span class="switch-track"></span>
                        <span class="switch-label">Residencial</span>
                    </label>
                    <label class="switch">
                        <input type="radio" name="finalidade" value="Comercial">
                        <span class="switch-track"></span>
                        <span class="switch-label">Comercial</span>
                    </label>
                </div>
            </div>

            <div class="form-grid-4">

                <div class="field">
                    <label class="field-label">
                        Prazo do contrato de locação <span class="required">*</span>
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon">{!! icon('calendar-blank') !!}</span>
                        {!! custom_select('prazo_contrato', $prazos, 'Selecione...', null, 'has-icon', true) !!}
                    </div>
                </div>

                <div class="field">
                    <label class="field-label" for="valor_aluguel">
                        Valor do aluguel mensal <span class="required">*</span>
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon">{!! icon('currency-dollar') !!}</span>
                        <input class="field-input has-icon" type="text" id="valor_aluguel"
                               name="valor_aluguel" maxlength="30" data-mask="currency" placeholder="0,00" required>
                    </div>
                </div>

                <div class="field">
                    <label class="field-label" for="valor_titulo">
                        Valor do título <span class="required">*</span>
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon">{!! icon('currency-dollar') !!}</span>
                        <input class="field-input has-icon" type="text" id="valor_titulo"
                               name="valor_titulo" maxlength="30" data-mask="currency" placeholder="0,00" required>
                    </div>
                </div>

                <div class="field">
                    <label class="field-label">
                        Forma de pagamento <span class="required">*</span>
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="switch-group">
                        <label class="switch">
                            <input type="radio" name="forma_pagamento" value="Boleto" required>
                            <span class="switch-track"></span>
                            <span class="switch-label">Boleto</span>
                        </label>
                        <label class="switch">
                            <input type="radio" name="forma_pagamento" value="Cartão">
                            <span class="switch-track"></span>
                            <span class="switch-label">Cartão</span>
                        </label>
                    </div>
                </div>

            </div>
        </div>

        <div class="form-section">
            <h3 class="form-section-title">{!! icon('house') !!} Endereço do Imóvel a ser Alugado</h3>

            <div class="form-grid-3">

                <div class="field">
                    <label class="field-label" for="imovel_cep">
                        CEP <span class="required">*</span>
                        <span class="field-error" hidden></span>
                    </label>
                    {!! cep_field_titulo('imovel_cep', 'imovel_cep', true) !!}
                </div>

                <div class="field">
                    <label class="field-label" for="imovel_bairro">
                        Bairro <span class="required">*</span>
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon">{!! icon('buildings') !!}</span>
                        <input class="field-input has-icon" type="text" id="imovel_bairro"
                               name="imovel_bairro" maxlength="100" placeholder="Nome do bairro" required>
                    </div>
                </div>

                <div class="field">
                    <label class="field-label" for="imovel_uf">
                        UF <span class="required">*</span>
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon">{!! icon('buildings') !!}</span>
                        <input class="field-input has-icon" type="text" id="imovel_uf"
                               name="imovel_uf" placeholder="SP" maxlength="2" required>
                    </div>
                </div>

            </div>

            <div class="form-grid-4">

                <div class="field">
                    <label class="field-label" for="imovel_logradouro">
                        Logradouro <span class="required">*</span>
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon">{!! icon('house') !!}</span>
                        <input class="field-input has-icon" type="text" id="imovel_logradouro"
                               name="imovel_logradouro" maxlength="255" placeholder="Rua, Avenida..." required>
                    </div>
                </div>

                <div class="field">
                    <label class="field-label" for="imovel_numero">
                        Número <span class="required">*</span>
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon">{!! icon('house') !!}</span>
                        <input class="field-input has-icon" type="text" id="imovel_numero"
                               name="imovel_numero" maxlength="20" placeholder="123" required>
                    </div>
                </div>

                <div class="field">
                    <label class="field-label" for="imovel_complemento">
                        Complemento
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon">{!! icon('note-pencil') !!}</span>
                        <input class="field-input has-icon" type="text" id="imovel_complemento"
                               name="imovel_complemento" maxlength="100" placeholder="Apto, Sala...">
                    </div>
                </div>

                <div class="field">
                    <label class="field-label" for="imovel_cidade">
                        Cidade <span class="required">*</span>
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon">{!! icon('buildings') !!}</span>
                        <input class="field-input has-icon" type="text" id="imovel_cidade"
                               name="imovel_cidade" maxlength="100" placeholder="Cidade" required>
                    </div>
                </div>

            </div>
        </div>

        <div class="stepper-nav">
            <div class="stepper-nav-left"></div>
            <div class="stepper-nav-right">
                <button type="button" id="btn-salvar-rascunho" class="btn btn-sm btn-secondary" data-csrf="{{ csrf_token() }}">
                    {!! icon('note-pencil') !!} Salvar Rascunho
                </button>
                <button type="button" class="btn btn-sm btn-primary" data-next>
                    Próximo {!! icon('arrow-right') !!}
                </button>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════
         STEP 2 — Inquilino
    ══════════════════════════════════════════════════════════════════ -->
    <div class="stepper-panel" data-step="2">

        <div class="form-section">
            <h3 class="form-section-title">{!! icon('user') !!} Dados do Inquilino</h3>

            <!-- Toggle PF / PJ -->
            <div class="tab-toggle">
                <button type="button" class="tab-btn is-active" data-tab="pf">Pessoa Física</button>
                <button type="button" class="tab-btn" data-tab="pj">Pessoa Jurídica</button>
            </div>
            <input type="hidden" name="inquilino_tipo" id="inquilino_tipo" value="pf">

            <!-- ── Pessoa Física ── -->
            <div class="inq-pf-section">

                <div class="form-grid-3">

                    <div class="field">
                        <label class="field-label" for="inq_nome">
                            Nome <span class="required">*</span>
                            <span class="field-error" hidden></span>
                        </label>
                        <div class="field-input-wrap">
                            <span class="field-input-icon">{!! icon('user') !!}</span>
                            <input class="field-input has-icon" type="text" id="inq_nome"
                                   name="inq_nome" maxlength="255" placeholder="Nome completo" autocomplete="off">
                        </div>
                    </div>

                    <div class="field">
                        <label class="field-label" for="inq_cpf">
                            CPF <span class="required">*</span>
                            <span class="field-error" hidden></span>
                        </label>
                        {!! cpf_field_titulo('inq_cpf', 'inq_cpf', 'inq-cpf-status', true) !!}
                    </div>

                    <div class="field">
                        <label class="field-label" for="inq_data_nasc">
                            Data de Nascimento
                            <span class="field-error" hidden></span>
                        </label>
                        <div class="field-input-wrap">
                            <span class="field-input-icon">{!! icon('calendar-blank') !!}</span>
                            <input class="field-input has-icon" type="text" id="inq_data_nasc"
                                   name="inq_data_nasc" data-mask="date" placeholder="DD/MM/AAAA" maxlength="10">
                        </div>
                    </div>

                </div>

                <div class="form-grid-4">

                    <div class="field">
                        <label class="field-label">
                            Estado Civil
                            <span class="field-error" hidden></span>
                        </label>
                        <div class="field-input-wrap">
                            <span class="field-input-icon">{!! icon('user') !!}</span>
                            {!! custom_select('inq_estado_civil', $estados_civis, 'Selecione...', null, 'has-icon') !!}
                        </div>
                    </div>

                    <div class="field">
                        <label class="field-label" for="inq_rg">
                            RG
                            <span class="field-error" hidden></span>
                        </label>
                        <div class="field-input-wrap">
                            <span class="field-input-icon">{!! icon('identification-card') !!}</span>
                            <input class="field-input has-icon" type="text" id="inq_rg"
                                   name="inq_rg" maxlength="30" placeholder="RG" autocomplete="off">
                        </div>
                    </div>

                    <div class="field">
                        <label class="field-label" for="inq_rg_expedicao">
                            Data de Expedição
                            <span class="field-error" hidden></span>
                        </label>
                        <div class="field-input-wrap">
                            <span class="field-input-icon">{!! icon('calendar-blank') !!}</span>
                            <input class="field-input has-icon" type="text" id="inq_rg_expedicao"
                                   name="inq_rg_expedicao" data-mask="date" placeholder="DD/MM/AAAA" maxlength="10">
                        </div>
                    </div>

                    <div class="field">
                        <label class="field-label" for="inq_orgao_emissor">
                            Órgão Emissor
                            <span class="field-error" hidden></span>
                        </label>
                        <div class="field-input-wrap">
                            <span class="field-input-icon">{!! icon('identification-card') !!}</span>
                            <input class="field-input has-icon" type="text" id="inq_orgao_emissor"
                                   name="inq_orgao_emissor" placeholder="SSP" maxlength="10">
                        </div>
                    </div>

                </div>

                <div class="form-grid-4">

                    <div class="field">
                        <label class="field-label" for="inq_email">
                            E-mail
                            <span class="field-error" hidden></span>
                        </label>
                        <div class="field-input-wrap">
                            <span class="field-input-icon">{!! icon('envelope-simple') !!}</span>
                            <input class="field-input has-icon" type="email" id="inq_email"
                                   name="inq_email" maxlength="255" placeholder="email@exemplo.com">
                        </div>
                    </div>

                    <div class="field">
                        <label class="field-label" for="inq_profissao">
                            Profissão
                            <span class="field-error" hidden></span>
                        </label>
                        <div class="field-input-wrap">
                            <span class="field-input-icon">{!! icon('briefcase') !!}</span>
                            <input class="field-input has-icon" type="text" id="inq_profissao"
                                   name="inq_profissao" maxlength="100" placeholder="Profissão">
                        </div>
                    </div>

                    <div class="field">
                        <label class="field-label" for="inq_telefone1">
                            Telefone 1
                            <span class="field-error" hidden></span>
                        </label>
                        <div class="field-input-wrap">
                            <span class="field-input-icon">{!! icon('phone') !!}</span>
                            <input class="field-input has-icon" type="text" id="inq_telefone1"
                                   name="inq_telefone1" data-mask="phone" placeholder="(00) 00000-0000">
                        </div>
                    </div>

                    <div class="field">
                        <label class="field-label" for="inq_telefone2">
                            Telefone 2
                            <span class="field-error" hidden></span>
                        </label>
                        <div class="field-input-wrap">
                            <span class="field-input-icon">{!! icon('phone') !!}</span>
                            <input class="field-input has-icon" type="text" id="inq_telefone2"
                                   name="inq_telefone2" data-mask="phone" placeholder="(00) 00000-0000">
                        </div>
                    </div>

                </div>

                <h3 class="form-section-title">{!! icon('house') !!} Endereço Atual do Inquilino</h3>

                <div class="form-grid-3">

                    <div class="field">
                        <label class="field-label" for="inq_cep">
                            CEP
                            <span class="field-error" hidden></span>
                        </label>
                        {!! cep_field_titulo('inq_cep', 'inq_cep', true) !!}
                    </div>

                    <div class="field">
                        <label class="field-label" for="inq_bairro">
                            Bairro
                            <span class="field-error" hidden></span>
                        </label>
                        <div class="field-input-wrap">
                            <span class="field-input-icon">{!! icon('buildings') !!}</span>
                            <input class="field-input has-icon" type="text" id="inq_bairro"
                                   name="inq_bairro" maxlength="100" placeholder="Nome do bairro">
                        </div>
                    </div>

                    <div class="field">
                        <label class="field-label" for="inq_uf">
                            UF
                            <span class="field-error" hidden></span>
                        </label>
                        <div class="field-input-wrap">
                            <span class="field-input-icon">{!! icon('buildings') !!}</span>
                            <input class="field-input has-icon" type="text" id="inq_uf"
                                   name="inq_uf" placeholder="SP" maxlength="2">
                        </div>
                    </div>

                </div>

                <div class="form-grid-4">

                    <div class="field">
                        <label class="field-label" for="inq_logradouro">
                            Logradouro
                            <span class="field-error" hidden></span>
                        </label>
                        <div class="field-input-wrap">
                            <span class="field-input-icon">{!! icon('house') !!}</span>
                            <input class="field-input has-icon" type="text" id="inq_logradouro"
                                   name="inq_logradouro" maxlength="255" placeholder="Rua, Avenida...">
                        </div>
                    </div>

                    <div class="field">
                        <label class="field-label" for="inq_numero">
                            Número
                            <span class="field-error" hidden></span>
                        </label>
                        <div class="field-input-wrap">
                            <span class="field-input-icon">{!! icon('house') !!}</span>
                            <input class="field-input has-icon" type="text" id="inq_numero"
                                   name="inq_numero" maxlength="20" placeholder="123">
                        </div>
                    </div>

                    <div class="field">
                        <label class="field-label" for="inq_complemento">
                            Complemento
                            <span class="field-error" hidden></span>
                        </label>
                        <div class="field-input-wrap">
                            <span class="field-input-icon">{!! icon('note-pencil') !!}</span>
                            <input class="field-input has-icon" type="text" id="inq_complemento"
                                   name="inq_complemento" maxlength="100" placeholder="Apto, Sala...">
                        </div>
                    </div>

                    <div class="field">
                        <label class="field-label" for="inq_cidade">
                            Cidade
                            <span class="field-error" hidden></span>
                        </label>
                        <div class="field-input-wrap">
                            <span class="field-input-icon">{!! icon('buildings') !!}</span>
                            <input class="field-input has-icon" type="text" id="inq_cidade"
                                   name="inq_cidade" maxlength="100" placeholder="Cidade">
                        </div>
                    </div>

                </div>
            </div><!-- /.inq-pf-section -->

            <!-- ── Pessoa Jurídica ── -->
            <div class="inq-pj-section" hidden>

                <div class="form-grid-3">

                    <div class="field">
                        <label class="field-label" for="inq_razao_social">
                            Razão Social
                            <span class="field-error" hidden></span>
                        </label>
                        <div class="field-input-wrap">
                            <span class="field-input-icon">{!! icon('buildings') !!}</span>
                            <input class="field-input has-icon" type="text" id="inq_razao_social"
                                   name="inq_razao_social" maxlength="255" placeholder="Razão Social">
                        </div>
                    </div>

                    <div class="field">
                        <label class="field-label" for="inq_cnpj">
                            CNPJ
                            <span class="field-error" hidden></span>
                        </label>
                        {!! cpf_field_titulo('inq_cnpj', 'inq_cnpj', 'inq-cnpj-status') !!}
                    </div>

                    <div class="field">
                        <label class="field-label" for="inq_representante">
                            Representante Legal
                            <span class="field-error" hidden></span>
                        </label>
                        <div class="field-input-wrap">
                            <span class="field-input-icon">{!! icon('user') !!}</span>
                            <input class="field-input has-icon" type="text" id="inq_representante"
                                   name="inq_representante" maxlength="255" placeholder="Nome do representante">
                        </div>
                    </div>

                    <div class="field">
                        <label class="field-label" for="inq_cpf_representante">
                            CPF do Representante
                            <span class="field-error" hidden></span>
                        </label>
                        {!! cpf_field_titulo('inq_cpf_representante', 'inq_cpf_representante', 'inq-cpf-rep-status') !!}
                    </div>

                    <div class="field">
                        <label class="field-label" for="inq_pj_email">
                            E-mail
                            <span class="field-error" hidden></span>
                        </label>
                        <div class="field-input-wrap">
                            <span class="field-input-icon">{!! icon('envelope-simple') !!}</span>
                            <input class="field-input has-icon" type="email" id="inq_pj_email"
                                   name="inq_pj_email" maxlength="255" placeholder="email@empresa.com">
                        </div>
                    </div>

                    <div class="field">
                        <label class="field-label" for="inq_pj_telefone">
                            Telefone
                            <span class="field-error" hidden></span>
                        </label>
                        <div class="field-input-wrap">
                            <span class="field-input-icon">{!! icon('phone') !!}</span>
                            <input class="field-input has-icon" type="text" id="inq_pj_telefone"
                                   name="inq_pj_telefone" data-mask="phone" placeholder="(00) 00000-0000">
                        </div>
                    </div>

                </div>

                <!-- Pessoa politicamente exposta -->
                <div class="field">
                    <label class="field-label">
                        Pessoa politicamente exposta?
                        <span class="field-error" hidden></span>
                    </label>
                    <label class="switch">
                        <input type="hidden" name="pol_exposta" value="Nao">
                        <input type="checkbox" name="pol_exposta" value="Sim">
                        <span class="switch-track"></span>
                        <span class="switch-label">Sim</span>
                    </label>
                </div>

                <div class="field pol-exposta-funcao" hidden>
                    <label class="field-label" for="pol_exposta_funcao">
                        Função
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon">{!! icon('briefcase') !!}</span>
                        <input class="field-input has-icon" type="text" id="pol_exposta_funcao"
                               name="pol_exposta_funcao" maxlength="100" placeholder="Cargo / Função">
                    </div>
                </div>

            </div><!-- /.inq-pj-section -->

        </div>

        <div class="stepper-nav">
            <div class="stepper-nav-left">
                <button type="button" class="btn btn-sm btn-secondary" data-prev>
                    {!! icon('arrow-left') !!} Voltar
                </button>
            </div>
            <div class="stepper-nav-right">
                <button type="button" class="btn btn-sm btn-primary" data-next>
                    Próximo {!! icon('arrow-right') !!}
                </button>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════
         STEP 3 — Proprietário
    ══════════════════════════════════════════════════════════════════ -->
    <div class="stepper-panel" data-step="3">

        <div class="form-section">
            <h3 class="form-section-title">{!! icon('user') !!} Dados do Proprietário</h3>

            <div class="form-grid-4">

                <div class="field">
                    <label class="field-label" for="prop_nome">
                        Nome <span class="required">*</span>
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon">{!! icon('user') !!}</span>
                        <input class="field-input has-icon" type="text" id="prop_nome"
                               name="prop_nome" maxlength="255" placeholder="Nome completo" autocomplete="off" required>
                    </div>
                </div>

                <div class="field">
                    <label class="field-label" for="prop_cpf">
                        CPF <span class="required">*</span>
                        <span class="field-error" hidden></span>
                    </label>
                    {!! cpf_field_titulo('prop_cpf', 'prop_cpf', 'prop-cpf-status', true) !!}
                </div>

                <div class="field">
                    <label class="field-label" for="prop_data_nasc">
                        Data de Nascimento
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon">{!! icon('calendar-blank') !!}</span>
                        <input class="field-input has-icon" type="text" id="prop_data_nasc"
                               name="prop_data_nasc" data-mask="date" placeholder="DD/MM/AAAA" maxlength="10">
                    </div>
                </div>

                <div class="field">
                    <label class="field-label">
                        Estado Civil
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon">{!! icon('user') !!}</span>
                        {!! custom_select('prop_estado_civil', $estados_civis, 'Selecione...', null, 'has-icon') !!}
                    </div>
                </div>

            </div>

            <div class="form-grid-4">

                <div class="field">
                    <label class="field-label" for="prop_rg">
                        RG
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon">{!! icon('identification-card') !!}</span>
                        <input class="field-input has-icon" type="text" id="prop_rg"
                               name="prop_rg" maxlength="30" placeholder="RG" autocomplete="off">
                    </div>
                </div>

                <div class="field">
                    <label class="field-label" for="prop_rg_expedicao">
                        Data de Expedição
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon">{!! icon('calendar-blank') !!}</span>
                        <input class="field-input has-icon" type="text" id="prop_rg_expedicao"
                               name="prop_rg_expedicao" data-mask="date" placeholder="DD/MM/AAAA" maxlength="10">
                    </div>
                </div>

                <div class="field">
                    <label class="field-label" for="prop_orgao_emissor">
                        Órgão Emissor
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon">{!! icon('identification-card') !!}</span>
                        <input class="field-input has-icon" type="text" id="prop_orgao_emissor"
                               name="prop_orgao_emissor" placeholder="SSP" maxlength="10">
                    </div>
                </div>

                <div class="field">
                    <label class="field-label" for="prop_profissao">
                        Profissão
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon">{!! icon('briefcase') !!}</span>
                        <input class="field-input has-icon" type="text" id="prop_profissao"
                               name="prop_profissao" maxlength="100" placeholder="Profissão">
                    </div>
                </div>

            </div>

            <div class="form-grid-5">

                <div class="field">
                    <label class="field-label" for="prop_email">
                        E-mail
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon">{!! icon('envelope-simple') !!}</span>
                        <input class="field-input has-icon" type="email" id="prop_email"
                               name="prop_email" maxlength="255" placeholder="email@exemplo.com">
                    </div>
                </div>

                <div class="field">
                    <label class="field-label" for="prop_telefone1">
                        Telefone 1
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon">{!! icon('phone') !!}</span>
                        <input class="field-input has-icon" type="text" id="prop_telefone1"
                               name="prop_telefone1" data-mask="phone" placeholder="(00) 00000-0000">
                    </div>
                </div>

                <div class="field">
                    <label class="field-label" for="prop_telefone2">
                        Telefone 2
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon">{!! icon('phone') !!}</span>
                        <input class="field-input has-icon" type="text" id="prop_telefone2"
                               name="prop_telefone2" data-mask="phone" placeholder="(00) 00000-0000">
                    </div>
                </div>

                <div class="field">
                    <label class="field-label" for="prop_cnpj">
                        CNPJ (se PJ)
                        <span class="field-error" hidden></span>
                    </label>
                    {!! cpf_field_titulo('prop_cnpj', 'prop_cnpj', 'prop-cnpj-status') !!}
                </div>

                <div class="field">
                    <label class="field-label" for="prop_nome_socio">
                        Nome do Sócio
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon">{!! icon('users') !!}</span>
                        <input class="field-input has-icon" type="text" id="prop_nome_socio"
                               name="prop_nome_socio" maxlength="255" placeholder="Nome do sócio">
                    </div>
                </div>

            </div>
        </div>

        <div class="stepper-nav">
            <div class="stepper-nav-left">
                <button type="button" class="btn btn-sm btn-secondary" data-prev>
                    {!! icon('arrow-left') !!} Voltar
                </button>
            </div>
            <div class="stepper-nav-right">
                <button type="button" class="btn btn-sm btn-primary" id="btn-submit-titulo">
                    {!! icon('check') !!} Enviar cotação
                    <span class="spinner"></span>
                </button>
            </div>
        </div>
    </div>

</form>
