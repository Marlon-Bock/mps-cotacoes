{{--
    Formulário de cotação Seguro Incêndio — 1 protocolo pode ter N imóveis.
    A tela começa com 1 bloco de imóvel (renderizado pelo JS a partir do
    <template>, ver cotacao-incendio.js) + botão "Adicionar outro imóvel".
    Nenhum imóvel é "principal" — todos usam o mesmo template.
    @include('incendio._partials.form') — espera $imob_cliente_sami (bool); opcionais
    $rascunho_protocolo (reabrindo um rascunho) e $ocultar_rascunho (embed).
--}}
@php
$vigencias = [];
for ($m = 1; $m <= 12; $m++) {
    $vigencias[$m] = $m . ($m === 1 ? 'x' : 'x');
}
@endphp

<form id="form-incendio" novalidate>
    @if (!empty($rascunho_protocolo))
    <input type="hidden" name="rascunho" value="{{ $rascunho_protocolo }}">
    @endif

    <div id="imoveis-list"></div>

    <template id="tpl-imovel">
            <div class="panel" data-imovel-bloco>
                <div class="panel-header">
                    <h3 class="panel-title" data-imovel-titulo></h3>
                    <button type="button" class="extra-pretendente-remove" data-remove-imovel hidden>
                        {!! icon('trash') !!} Remover
                    </button>
                </div>
                <div class="panel-body">

                        <div class="form-section">
                            <h3 class="form-section-title">{!! icon('user') !!} Proprietário e Inquilino</h3>

                            @if (!empty($imob_cliente_sami))
                            <div class="field imovel-lookup-compact">
                                <label class="field-label">Código do imóvel (Sami)</label>
                                <div class="field-input-wrap">
                                    <span class="field-input-icon">{!! icon('key') !!}</span>
                                    <input class="field-input has-icon has-action" type="text" data-field="codigo_imovel_sami" maxlength="30" placeholder="Ex: 123" autocomplete="off">
                                    <button type="button" class="field-input-action" data-buscar-imovel title="Buscar">
                                        {!! icon('magnifying-glass') !!}
                                    </button>
                                </div>
                                <span class="field-hint" data-imovel-lookup-status hidden></span>
                            </div>
                            @endif

                            <div class="form-grid-4">
                                <div class="field">
                                    <label class="field-label">Proprietário <span class="required">*</span></label>
                                    <div class="field-input-wrap">
                                        <span class="field-input-icon">{!! icon('user') !!}</span>
                                        <input class="field-input has-icon" type="text" data-field="locador" maxlength="150" placeholder="Nome ou razão social" required>
                                    </div>
                                </div>
                                <div class="field">
                                    <label class="field-label">CPF/CNPJ do Proprietário <span class="required">*</span></label>
                                    <div class="field-input-wrap">
                                        <span class="field-input-icon">{!! icon('identification-card') !!}</span>
                                        <input class="field-input has-icon has-icon-both" type="text" data-field="cpf_cnpj_locador" data-mask="cpfcnpj" placeholder="000.000.000-00" autocomplete="off" required>
                                        <span class="field-input-status" data-cpf-status-el hidden></span>
                                    </div>
                                </div>
                                <div class="field">
                                    <label class="field-label">Tipo de imóvel <span class="required">*</span></label>
                                    <div class="field-input-wrap">
                                        <span class="field-input-icon">{!! icon('house') !!}</span>
                                        <input class="field-input has-icon" type="text" data-field="tipo_imovel" maxlength="60" placeholder="Ex: Apartamento, Casa, Sala Comercial..." required>
                                    </div>
                                </div>
                                <div class="field">
                                    <label class="field-label">Atividade exercida (se comercial)</label>
                                    <div class="field-input-wrap">
                                        <span class="field-input-icon">{!! icon('briefcase') !!}</span>
                                        <input class="field-input has-icon" type="text" data-field="atividade_imovel" maxlength="150" placeholder="Ex: Livraria, consultório médico...">
                                    </div>
                                </div>
                            </div>

                            <div class="form-grid-3">
                                <div class="field">
                                    <label class="field-label">Inquilino <span class="required">*</span></label>
                                    <div class="field-input-wrap">
                                        <span class="field-input-icon">{!! icon('user') !!}</span>
                                        <input class="field-input has-icon" type="text" data-field="locatario" maxlength="150" placeholder="Nome do inquilino" required>
                                    </div>
                                </div>
                                <div class="field">
                                    <label class="field-label">CPF/CNPJ do Inquilino <span class="required">*</span></label>
                                    <div class="field-input-wrap">
                                        <span class="field-input-icon">{!! icon('identification-card') !!}</span>
                                        <input class="field-input has-icon has-icon-both" type="text" data-field="cpf_cnpj_locatario" data-mask="cpfcnpj" placeholder="000.000.000-00" autocomplete="off" required>
                                        <span class="field-input-status" data-cpf-status-el hidden></span>
                                    </div>
                                </div>
                                <div class="field">
                                    <label class="field-label">Parcelas <span class="required">*</span></label>
                                    {!! custom_select('__parcelas_placeholder__', $vigencias, 'Selecione...', null, '', true, ['data-field' => 'parcelas']) !!}
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h3 class="form-section-title">{!! icon('currency-dollar') !!} Valores</h3>

                            <div class="form-grid-3">
                                <div class="field">
                                    <label class="field-label">Valor do aluguel <span class="required">*</span></label>
                                    <div class="field-input-wrap">
                                        <span class="field-input-icon">{!! icon('currency-dollar') !!}</span>
                                        <input class="field-input has-icon has-action" type="text" data-field="valor_aluguel" data-mask="currency" placeholder="R$ 0,00" data-valor-imovel>
                                        <button type="button" class="field-input-action" data-skip-toggle="valor_aluguel" title="Não informar" aria-pressed="false">Não informar</button>
                                        <input type="hidden" data-field="valor_aluguel_skip" value="0">
                                    </div>
                                </div>
                                <div class="field">
                                    <label class="field-label">Metragem (m²) <span class="required">*</span></label>
                                    <div class="field-input-wrap">
                                        <span class="field-input-icon">{!! icon('house') !!}</span>
                                        <input class="field-input has-icon has-action" type="text" data-field="metragem" data-mask="decimal" placeholder="0,00" data-metragem-imovel>
                                        <button type="button" class="field-input-action" data-skip-toggle="metragem" title="Não informar" aria-pressed="false">Não informar</button>
                                        <input type="hidden" data-field="metragem_skip" value="0">
                                    </div>
                                </div>
                                <div class="field">
                                    <label class="field-label">Valor do Incêndio <span class="required">*</span></label>
                                    <div class="field-input-wrap">
                                        <span class="field-input-icon">{!! icon('currency-dollar') !!}</span>
                                        <input class="field-input has-icon has-action" type="text" data-field="valor_incendio" data-mask="currency" placeholder="R$ 0,00" data-valor-imovel>
                                        <button type="button" class="field-input-action" data-skip-toggle="valor_incendio" title="Não informar" aria-pressed="false">Não informar</button>
                                        <input type="hidden" data-field="valor_incendio_skip" value="0">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-section">
                            <h3 class="form-section-title">{!! icon('house') !!} Endereço do Imóvel</h3>

                            <div class="form-grid-4">
                                <div class="field">
                                    <label class="field-label">CEP <span class="required">*</span></label>
                                    <div class="field-input-wrap">
                                        <span class="field-input-icon">{!! icon('house') !!}</span>
                                        <input class="field-input has-icon" type="text" data-field="cep" data-mask="cep" data-cep-autofill="1" placeholder="00000-000" required>
                                    </div>
                                </div>
                                <div class="field">
                                    <label class="field-label">Logradouro <span class="required">*</span></label>
                                    <div class="field-input-wrap">
                                        <span class="field-input-icon">{!! icon('house') !!}</span>
                                        <input class="field-input has-icon" type="text" data-field="endereco" maxlength="200" placeholder="Rua, Avenida, Travessa..." required>
                                    </div>
                                </div>
                                <div class="field">
                                    <label class="field-label">Número <span class="required">*</span></label>
                                    <div class="field-input-wrap">
                                        <span class="field-input-icon">{!! icon('house') !!}</span>
                                        <input class="field-input has-icon" type="text" data-field="numero" maxlength="20" placeholder="123" required>
                                    </div>
                                </div>
                                <div class="field">
                                    <label class="field-label">Complemento</label>
                                    <div class="field-input-wrap">
                                        <span class="field-input-icon">{!! icon('note-pencil') !!}</span>
                                        <input class="field-input has-icon" type="text" data-field="complemento" maxlength="100" placeholder="Apto, Sala, Bloco...">
                                    </div>
                                </div>
                            </div>

                            <div class="form-grid-3">
                                <div class="field">
                                    <label class="field-label">Bairro <span class="required">*</span></label>
                                    <div class="field-input-wrap">
                                        <span class="field-input-icon">{!! icon('buildings') !!}</span>
                                        <input class="field-input has-icon" type="text" data-field="bairro" maxlength="100" placeholder="Nome do bairro" required>
                                    </div>
                                </div>
                                <div class="field">
                                    <label class="field-label">Cidade <span class="required">*</span></label>
                                    <div class="field-input-wrap">
                                        <span class="field-input-icon">{!! icon('buildings') !!}</span>
                                        <input class="field-input has-icon" type="text" data-field="cidade" maxlength="100" placeholder="Nome da cidade" required>
                                    </div>
                                </div>
                                <div class="field">
                                    <label class="field-label">UF <span class="required">*</span></label>
                                    <div class="field-input-wrap">
                                        <span class="field-input-icon">{!! icon('buildings') !!}</span>
                                        <input class="field-input has-icon" type="text" data-field="uf" maxlength="2" placeholder="RS" required>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="form-section no-margin-bottom">
                            <h3 class="form-section-title">{!! icon('fire') !!} Coberturas</h3>

                            <div class="switch-row">
                                <label class="switch">
                                    <input type="checkbox" data-informar-coberturas-toggle>
                                    <span class="switch-track"></span>
                                    <span class="switch-label">Já sei os valores de cada cobertura</span>
                                </label>
                                <input type="hidden" data-field="informar_coberturas" value="0">
                            </div>
                            <p class="field-hint" data-coberturas-hint>Deixando desmarcado, a MPS define os valores de cobertura na análise.</p>

                            <div class="form-grid-3" data-coberturas-campos hidden>
                                @php
                                $coberturas_incendio = [
                                    'valor_danos_eletricos'        => 'Danos Elétricos',
                                    'valor_impacto_veiculos'       => 'Impacto de Veículos Terrestres e Aéreos',
                                    'valor_vendaval'               => 'Vendaval',
                                    'valor_perda_aluguel'          => 'Perda de Aluguel',
                                    'valor_incendio_conteudo'      => 'Incêndio do conteúdo (inquilino)',
                                    'valor_responsabilidade_civil' => 'Responsabilidade Civil',
                                ];
                                @endphp
                                @foreach ($coberturas_incendio as $campo => $rotulo)
                                <div class="field">
                                    <label class="field-label">{{ $rotulo }}</label>
                                    <div class="field-input-wrap">
                                        <span class="field-input-icon">{!! icon('currency-dollar') !!}</span>
                                        <input class="field-input has-icon has-action" type="text" data-field="{{ $campo }}" data-mask="currency" placeholder="R$ 0,00" data-cobertura-imovel>
                                        <button type="button" class="field-input-action" data-skip-toggle="{{ $campo }}" title="Não informar" aria-pressed="false">Não informar</button>
                                        <input type="hidden" data-field="{{ $campo }}_skip" value="0">
                                    </div>
                                </div>
                                @endforeach
                            </div>
                        </div>

                    </div>
            </div>
        </template>

    <div class="incendio-form-nav">
        <button type="button" class="btn btn-sm btn-secondary btn-add-imovel" id="btn-add-imovel">
            {!! icon('plus') !!} Adicionar outro imóvel
        </button>
        <div class="incendio-form-nav-right">
            @if (empty($ocultar_rascunho))
            <button type="button" id="btn-salvar-rascunho" class="btn btn-sm btn-secondary" data-csrf="{{ csrf_token() }}">
                {!! icon('note-pencil') !!} Salvar Rascunho
            </button>
            @endif
            <button type="button" class="btn btn-sm btn-primary" id="btn-submit-incendio">
                {!! icon('check') !!}
                <span data-label>Enviar cotação</span>
                <span class="spinner"></span>
            </button>
        </div>
    </div>

</form>
