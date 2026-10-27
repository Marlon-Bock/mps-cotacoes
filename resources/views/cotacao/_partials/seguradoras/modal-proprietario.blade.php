{{--
    Modal "Escolher proposta" da Fiança: dados do proprietário (locador) → emissão.
    @include('cotacao._partials.seguradoras.modal-proprietario')

    Espera: $ficha (seguro_fianca — usa proprietario_imovel, cpf_cnpj_proprietario
    e o endereço do imóvel).
--}}
@php
    // "Escolher proposta" — a seguradora exige o proprietário (locador) como
    // segurado/beneficiário pra emitir. Nome/CPF vêm pré-preenchidos se a
    // ficha já tiver; o endereço do imóvel fica a um clique.
    $imovel = array_map('strval', array_intersect_key(
        $ficha,
        array_flip(['cep', 'endereco', 'numero', 'complemento', 'bairro', 'cidade', 'uf'])
    ));
@endphp
<div class="modal" id="modal-escolher-proposta" hidden>
    <div class="modal-backdrop" data-close="modal-escolher-proposta"></div>
    <div class="modal-dialog seg-modal-proprietario">
        <div class="modal-header">
            <h3>Aceitar proposta</h3>
            <button type="button" class="modal-close" data-close="modal-escolher-proposta">{!! icon('x') !!}</button>
        </div>
        <form id="form-escolher-proposta" class="seg-modal-form" novalidate>
          <div class="modal-body">
            <input type="hidden" name="seguradora" value="">
            <p class="seg-modal-intro">Informe os dados do proprietário do imóvel. Ao confirmar, a proposta é aceita e a apólice emitida na seguradora — a MPS envia a apólice quando sair.</p>

            <div class="field">
                <label class="field-label" for="prop-nome">Nome ou razão social <span class="required">*</span></label>
                <div class="field-input-wrap">
                    <span class="field-input-icon">{!! icon('user') !!}</span>
                    <input class="field-input has-icon" type="text" id="prop-nome" name="nome_completo" maxlength="100" placeholder="Nome como no documento" value="{{ $ficha['proprietario_imovel'] ?? '' }}" required>
                </div>
            </div>
            <div class="field-row">
                <div class="field">
                    <label class="field-label" for="prop-doc">CPF / CNPJ <span class="required">*</span></label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon">{!! icon('identification-card') !!}</span>
                        <input class="field-input has-icon" type="text" id="prop-doc" name="cpf_cnpj" data-mask="cpfcnpj" inputmode="numeric" placeholder="000.000.000-00" autocomplete="off" value="{{ !empty($ficha['cpf_cnpj_proprietario']) ? api_formatar_documento($ficha['cpf_cnpj_proprietario']) : '' }}" required>
                    </div>
                </div>
                <div class="field">
                    <label class="field-label" for="prop-tel">Celular</label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon">{!! icon('phone') !!}</span>
                        <input class="field-input has-icon" type="tel" id="prop-tel" name="tel_celular" data-mask="phone" placeholder="(00) 00000-0000">
                    </div>
                </div>
            </div>
            <div class="field">
                <label class="field-label" for="prop-email">E-mail</label>
                <div class="field-input-wrap">
                    <span class="field-input-icon">{!! icon('envelope-simple') !!}</span>
                    <input class="field-input has-icon" type="email" id="prop-email" name="email" maxlength="150" placeholder="proprietario@email.com">
                </div>
            </div>

            @if (in_array('too', array_keys(api_seguradoras()), true))
            {{-- Too Seguros exige mais dados do proprietário — a seção só aparece
                 quando a proposta escolhida é da Too (cotacao-fianca-seguradoras.js). --}}
            <div class="seg-modal-too" data-somente-seguradora="too" hidden>
                <div class="seg-modal-secao">
                    <span class="field-label">Dados exigidos pela Too Seguros</span>
                </div>
                <div class="field-row" data-somente-pf>
                    <div class="field">
                        <label class="field-label" for="prop-nascimento">Data de nascimento <span class="required">*</span></label>
                        <div class="field-input-wrap">
                            <span class="field-input-icon">{!! icon('calendar-blank') !!}</span>
                            <input class="field-input has-icon" type="text" id="prop-nascimento" name="data_nascimento" data-mascara="data" maxlength="10" inputmode="numeric" placeholder="DD/MM/AAAA">
                        </div>
                    </div>
                    <div class="field">
                        <label class="field-label" for="prop-estado-civil">Estado civil <span class="required">*</span></label>
                        <div class="field-input-wrap">
                            <span class="field-input-icon">{!! icon('users') !!}</span>
                            {!! custom_select('estado_civil', ['Solteiro' => 'Solteiro(a)', 'Casado' => 'Casado(a)', 'UniaoEstavel' => 'União estável', 'Divorciado' => 'Divorciado(a)', 'SeparadoJudicialmente' => 'Separado(a) judicialmente', 'Viuvo' => 'Viúvo(a)'], 'Selecione...', null, 'has-icon') !!}
                        </div>
                    </div>
                </div>
                <div class="field-row" data-somente-pf>
                    <div class="field">
                        <label class="field-label" for="prop-nacionalidade">Nacionalidade <span class="required">*</span></label>
                        <div class="field-input-wrap">
                            <span class="field-input-icon">{!! icon('identification-card') !!}</span>
                            <input class="field-input has-icon" type="text" id="prop-nacionalidade" name="nacionalidade" maxlength="40" placeholder="Ex: Brasil" value="Brasil">
                        </div>
                    </div>
                    <div class="field">
                        <label class="field-label" for="prop-renda">Renda mensal <span class="required">*</span></label>
                        <div class="field-input-wrap">
                            <span class="field-input-icon">{!! icon('currency-dollar') !!}</span>
                            <input class="field-input has-icon" type="text" id="prop-renda" name="renda" data-mascara="moeda" inputmode="numeric" placeholder="R$ 0,00">
                        </div>
                    </div>
                </div>
                <div class="field" data-somente-pf>
                    <label class="field-label" for="prop-pep">Pessoa politicamente exposta? <span class="required">*</span></label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon">{!! icon('info') !!}</span>
                        {!! custom_select('pep', ['Nao' => 'Não', 'Sim' => 'Sim'], 'Selecione...', 'Nao', 'has-icon') !!}
                    </div>
                </div>
                <div class="field" data-somente-pj hidden>
                    <label class="field-label" for="prop-faturamento">Faturamento mensal da empresa <span class="required">*</span></label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon">{!! icon('currency-dollar') !!}</span>
                        <input class="field-input has-icon" type="text" id="prop-faturamento" name="faturamento" data-mascara="moeda" inputmode="numeric" placeholder="R$ 0,00">
                    </div>
                </div>
            </div>
            @endif

            <div class="seg-modal-secao">
                <span class="field-label">Endereço do proprietário</span>
                <button type="button" class="btn btn-link" id="btn-prop-endereco-imovel" data-imovel="{{ json_encode($imovel) }}">
                    {!! icon('house') !!} Usar endereço do imóvel
                </button>
            </div>
            <div class="field-row">
                <div class="field">
                    <label class="field-label" for="prop-cep">CEP <span class="required">*</span></label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon">{!! icon('house') !!}</span>
                        <input class="field-input has-icon" type="text" id="prop-cep" name="cep" data-mask="cep" inputmode="numeric" placeholder="00000-000" required>
                    </div>
                </div>
                <div class="field">
                    <label class="field-label" for="prop-numero">Número <span class="required">*</span></label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon">{!! icon('house') !!}</span>
                        <input class="field-input has-icon" type="text" id="prop-numero" name="numero" maxlength="20" placeholder="123" required>
                    </div>
                </div>
            </div>
            <div class="field">
                <label class="field-label" for="prop-endereco">Endereço <span class="required">*</span></label>
                <div class="field-input-wrap">
                    <span class="field-input-icon">{!! icon('house') !!}</span>
                    <input class="field-input has-icon" type="text" id="prop-endereco" name="endereco" maxlength="150" placeholder="Rua, Avenida, Travessa..." required>
                </div>
            </div>
            <div class="field-row">
                <div class="field">
                    <label class="field-label" for="prop-complemento">Complemento</label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon">{!! icon('note-pencil') !!}</span>
                        <input class="field-input has-icon" type="text" id="prop-complemento" name="complemento" maxlength="100" placeholder="Apto, Sala, Bloco...">
                    </div>
                </div>
                <div class="field">
                    <label class="field-label" for="prop-bairro">Bairro <span class="required">*</span></label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon">{!! icon('buildings') !!}</span>
                        <input class="field-input has-icon" type="text" id="prop-bairro" name="bairro" maxlength="100" placeholder="Nome do bairro" required>
                    </div>
                </div>
            </div>
            <div class="field-row">
                <div class="field">
                    <label class="field-label" for="prop-cidade">Cidade <span class="required">*</span></label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon">{!! icon('buildings') !!}</span>
                        <input class="field-input has-icon" type="text" id="prop-cidade" name="cidade" maxlength="100" placeholder="Nome da cidade" required>
                    </div>
                </div>
                <div class="field">
                    <label class="field-label" for="prop-uf">UF <span class="required">*</span></label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon">{!! icon('buildings') !!}</span>
                        <input class="field-input has-icon" type="text" id="prop-uf" name="uf" maxlength="2" placeholder="SP" required>
                    </div>
                </div>
            </div>
          </div>

          <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-close="modal-escolher-proposta">{!! icon('x') !!} Cancelar</button>
              <button type="submit" class="btn btn-primary" id="btn-confirmar-proposta">
                  {!! icon('check') !!} Aceitar proposta
              </button>
          </div>
        </form>
    </div>
</div>
