<?php
/**
 * Formulário de cotação Seguro Condomínio — 3 steps.
 */

/* Helper local: cnpj field com ícone + status */
function cnpj_field_cond(string $id, string $name, string $statusId = '', bool $required = false): string {
    $sid = $statusId ?: "{$id}-status";
    $req = $required ? ' required' : '';
    return '<div class="field-input-wrap">'
        . '<span class="field-input-icon">' . icon('identification-card') . '</span>'
        . '<input class="field-input has-icon has-icon-both" type="text" id="' . e($id) . '" name="' . e($name) . '"'
        . ' data-mask="cpfcnpj" data-cpf-status="' . e($sid) . '" placeholder="00.000.000/0000-00" autocomplete="off"' . $req . '>'
        . '<span class="field-input-status" id="' . e($sid) . '" hidden></span>'
        . '</div>';
}

/* Helper local: cep field com ícone */
function cep_field_cond(string $id, string $name, bool $autofill = false): string {
    $af = $autofill ? ' data-cep-autofill="1"' : '';
    return '<div class="field-input-wrap">'
        . '<span class="field-input-icon">' . icon('house') . '</span>'
        . '<input class="field-input has-icon" type="text" id="' . e($id) . '" name="' . e($name) . '"'
        . ' data-mask="cep" placeholder="00000-000"' . $af . '>'
        . '</div>';
}
?>

<!-- Indicador de progresso -->
<div class="stepper-header">
    <div class="stepper-step is-active" data-step-indicator="1">
        <span class="stepper-num">1</span>
        <span class="stepper-step-label">Condomínio</span>
    </div>
    <div class="stepper-connector"></div>
    <div class="stepper-step" data-step-indicator="2">
        <span class="stepper-num">2</span>
        <span class="stepper-step-label">Características</span>
    </div>
    <div class="stepper-connector"></div>
    <div class="stepper-step" data-step-indicator="3">
        <span class="stepper-num">3</span>
        <span class="stepper-step-label">Coberturas e Síndico</span>
    </div>
</div>

<form id="form-cotacao-condominial" novalidate>
    <?php if (!empty($rascunho_protocolo)): ?>
    <input type="hidden" name="rascunho" value="<?= e($rascunho_protocolo) ?>">
    <?php endif; ?>

    <!-- ══════════════════════════════════════════════════════════════════
         STEP 1 — Dados do Condomínio
    ══════════════════════════════════════════════════════════════════ -->
    <div class="stepper-panel is-active" data-step="1">

        <div class="form-section">
            <h3 class="form-section-title"><?= icon('buildings') ?> Tipo de Condomínio</h3>

            <div class="field">
                <label class="field-label">
                    Finalidade <span class="required">*</span>
                </label>
                <div class="switch-group">
                    <label class="switch">
                        <input type="radio" name="tipo_condominio" value="Residencial">
                        <span class="switch-track"></span>
                        <span class="switch-label">Residencial</span>
                    </label>
                    <label class="switch">
                        <input type="radio" name="tipo_condominio" value="Comercial">
                        <span class="switch-track"></span>
                        <span class="switch-label">Comercial</span>
                    </label>
                    <label class="switch">
                        <input type="radio" name="tipo_condominio" value="Escritorio_Consultorio">
                        <span class="switch-track"></span>
                        <span class="switch-label">Escritório/Consultório</span>
                    </label>
                    <label class="switch">
                        <input type="radio" name="tipo_condominio" value="Com_Comercio_Terreo">
                        <span class="switch-track"></span>
                        <span class="switch-label">Com Comércio no Térreo</span>
                    </label>
                </div>
                <span class="field-error" id="tipo-condominio-error" hidden></span>
            </div>
        </div>

        <div class="form-section">
            <h3 class="form-section-title"><?= icon('buildings') ?> Dados do Condomínio</h3>

            <div class="form-grid-4">

                <div class="field">
                    <label class="field-label" for="imovel_nome">
                        Nome do Condomínio <span class="required">*</span>
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon"><?= icon('buildings') ?></span>
                        <input class="field-input has-icon" type="text" id="imovel_nome" name="imovel_nome" maxlength="255"
                               placeholder="Nome do condomínio" required autocomplete="organization">
                    </div>
                </div>

                <div class="field">
                    <label class="field-label" for="imovel_cnpj">
                        CNPJ <span class="required">*</span>
                        <span class="field-error" hidden></span>
                    </label>
                    <?= cnpj_field_cond('imovel_cnpj', 'imovel_cnpj', 'imovel-cnpj-status', true) ?>
                </div>

                <div class="field">
                    <label class="field-label" for="imovel_cep">
                        CEP <span class="required">*</span>
                        <span class="field-error" hidden></span>
                    </label>
                    <?= cep_field_cond('imovel_cep', 'imovel_cep', true) ?>
                </div>

                <div class="field">
                    <label class="field-label" for="imovel_uf">
                        UF <span class="required">*</span>
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon"><?= icon('buildings') ?></span>
                        <input class="field-input has-icon" type="text" id="imovel_uf" name="imovel_uf"
                               placeholder="SP" maxlength="2" required>
                    </div>
                </div>

            </div>

            <div class="field">
                <label class="field-label" for="imovel_endereco">
                    Endereço/Logradouro <span class="required">*</span>
                    <span class="field-error" hidden></span>
                </label>
                <div class="field-input-wrap">
                    <span class="field-input-icon"><?= icon('house') ?></span>
                    <input class="field-input has-icon" type="text" id="imovel_endereco" name="imovel_endereco" maxlength="255"
                           placeholder="Rua, Avenida..." required>
                </div>
            </div>

            <div class="form-grid-4">

                <div class="field">
                    <label class="field-label" for="imovel_numero">
                        Número <span class="required">*</span>
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon"><?= icon('key') ?></span>
                        <input class="field-input has-icon" type="text" id="imovel_numero" name="imovel_numero" maxlength="20"
                               placeholder="123" required>
                    </div>
                </div>

                <div class="field">
                    <label class="field-label" for="imovel_complemento">
                        Complemento
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon"><?= icon('note-pencil') ?></span>
                        <input class="field-input has-icon" type="text" id="imovel_complemento" name="imovel_complemento" maxlength="100"
                               placeholder="Bloco, Torre...">
                    </div>
                </div>

                <div class="field">
                    <label class="field-label" for="imovel_bairro">
                        Bairro <span class="required">*</span>
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon"><?= icon('buildings') ?></span>
                        <input class="field-input has-icon" type="text" id="imovel_bairro" name="imovel_bairro" maxlength="100"
                               placeholder="Nome do bairro" required>
                    </div>
                </div>

                <div class="field">
                    <label class="field-label" for="imovel_cidade">
                        Cidade <span class="required">*</span>
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon"><?= icon('buildings') ?></span>
                        <input class="field-input has-icon" type="text" id="imovel_cidade" name="imovel_cidade" maxlength="100"
                               placeholder="Cidade" required>
                    </div>
                </div>

            </div>

            <div class="form-grid-5">

                <div class="field">
                    <label class="field-label" for="imovel_ano_construcao">
                        Ano de Construção
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon"><?= icon('calendar-blank') ?></span>
                        <input class="field-input has-icon" type="number" id="imovel_ano_construcao"
                               name="imovel_ano_construcao" placeholder="2000" min="1900" max="2099" maxlength="4">
                    </div>
                </div>

                <div class="field">
                    <label class="field-label" for="imovel_blocos">
                        Blocos
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon"><?= icon('buildings') ?></span>
                        <input class="field-input has-icon" type="number" id="imovel_blocos" name="imovel_blocos"
                               placeholder="1" min="0">
                    </div>
                </div>

                <div class="field">
                    <label class="field-label" for="imovel_andares">
                        Andares
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon"><?= icon('buildings') ?></span>
                        <input class="field-input has-icon" type="number" id="imovel_andares" name="imovel_andares"
                               placeholder="10" min="0">
                    </div>
                </div>

                <div class="field">
                    <label class="field-label" for="imovel_elevadores">
                        Elevadores
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon"><?= icon('buildings') ?></span>
                        <input class="field-input has-icon" type="number" id="imovel_elevadores" name="imovel_elevadores"
                               placeholder="2" min="0">
                    </div>
                </div>

                <div class="field">
                    <label class="field-label" for="imovel_area_m2">
                        Área m²
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon"><?= icon('note-pencil') ?></span>
                        <input class="field-input has-icon" type="number" id="imovel_area_m2" name="imovel_area_m2"
                               placeholder="5000" min="0">
                    </div>
                </div>

            </div>
        </div>

        <div class="stepper-nav">
            <div class="stepper-nav-left"></div>
            <div class="stepper-nav-right">
                <button type="button" id="btn-salvar-rascunho" class="btn btn-sm btn-secondary" data-csrf="<?= e(csrf_token()) ?>">
                    <?= icon('note-pencil') ?> Salvar Rascunho
                </button>
                <button type="button" class="btn btn-sm btn-primary" data-next>
                    Próximo <?= icon('arrow-right') ?>
                </button>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════
         STEP 2 — Características
    ══════════════════════════════════════════════════════════════════ -->
    <div class="stepper-panel" data-step="2">

        <div class="form-section">
            <h3 class="form-section-title"><?= icon('check-circle') ?> Características do Condomínio</h3>

            <div class="form-grid-5">

                <div class="field">
                    <label class="field-label">
                        Alarme
                        <span class="field-error" hidden></span>
                    </label>
                    <label class="switch">
                        <input type="hidden" name="tem_alarme" value="Nao">
                        <input type="checkbox" name="tem_alarme" value="Sim">
                        <span class="switch-track"></span>
                        <span class="switch-label">Sim</span>
                    </label>
                </div>

                <div class="field">
                    <label class="field-label">
                        Câmeras de Segurança
                        <span class="field-error" hidden></span>
                    </label>
                    <label class="switch">
                        <input type="hidden" name="tem_cameras" value="Nao">
                        <input type="checkbox" name="tem_cameras" value="Sim">
                        <span class="switch-track"></span>
                        <span class="switch-label">Sim</span>
                    </label>
                </div>

                <div class="field">
                    <label class="field-label">
                        Central Telefônica
                        <span class="field-error" hidden></span>
                    </label>
                    <label class="switch">
                        <input type="hidden" name="tem_central_telefonica" value="Nao">
                        <input type="checkbox" name="tem_central_telefonica" value="Sim">
                        <span class="switch-track"></span>
                        <span class="switch-label">Sim</span>
                    </label>
                </div>

                <div class="field">
                    <label class="field-label">
                        Funcionários Registrados
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="ir-trabalho-row">
                        <label class="switch">
                            <input type="hidden" name="tem_funcionarios" value="Nao">
                            <input type="checkbox" name="tem_funcionarios" value="Sim">
                            <span class="switch-track"></span>
                            <span class="switch-label">Sim</span>
                        </label>
                        <div class="field-input-wrap ir-trabalho-km" id="wrap-qtd-funcionarios">
                            <span class="field-input-icon"><?= icon('users') ?></span>
                            <input class="field-input has-icon" type="number" id="qtd_funcionarios"
                                   name="qtd_funcionarios" placeholder="Quantas vidas?" min="1" disabled>
                        </div>
                    </div>
                </div>

                <div class="field">
                    <label class="field-label">
                        Seguro Atual
                        <span class="field-error" hidden></span>
                    </label>
                    <label class="switch">
                        <input type="hidden" name="tem_seguro_atual" value="Nao">
                        <input type="checkbox" name="tem_seguro_atual" value="Sim">
                        <span class="switch-track"></span>
                        <span class="switch-label">Sim</span>
                    </label>
                    <div class="seguro-atual-details" id="seguro-atual-details">
                        <div class="field">
                            <label class="field-label" for="seguro_atual_seguradora">
                                Seguradora
                                <span class="field-error" hidden></span>
                            </label>
                            <div class="field-input-wrap">
                                <span class="field-input-icon"><?= icon('check-circle') ?></span>
                                <input class="field-input has-icon" type="text" id="seguro_atual_seguradora"
                                       name="seguro_atual_seguradora" maxlength="100" placeholder="Nome da seguradora">
                            </div>
                        </div>
                        <div class="field">
                            <label class="field-label" for="seguro_atual_classe_bonus">
                                Classe Bônus
                                <span class="field-error" hidden></span>
                            </label>
                            <div class="field-input-wrap">
                                <span class="field-input-icon"><?= icon('check') ?></span>
                                <input class="field-input has-icon" type="text" id="seguro_atual_classe_bonus"
                                       name="seguro_atual_classe_bonus" maxlength="30" placeholder="Ex: Classe 1">
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <div class="stepper-nav">
            <div class="stepper-nav-left">
                <button type="button" class="btn btn-sm btn-secondary" data-prev>
                    <?= icon('arrow-left') ?> Anterior
                </button>
            </div>
            <div class="stepper-nav-right">
                <button type="button" class="btn btn-sm btn-primary" data-next>
                    Próximo <?= icon('arrow-right') ?>
                </button>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════
         STEP 3 — Coberturas e Síndico
    ══════════════════════════════════════════════════════════════════ -->
    <div class="stepper-panel" data-step="3">

        <div class="form-section">
            <h3 class="form-section-title"><?= icon('check-circle') ?> Coberturas</h3>
            <p class="form-section-hint">Informe os valores desejados para cada cobertura (deixe em branco se não quiser).</p>

            <div class="coberturas-grid">

                <!-- Coluna esquerda -->
                <div class="cobertura-row">
                    <label class="cobertura-row-label">Incêndio/Raio/Explosão/Fumaça</label>
                    <div class="field-input-wrap">
                        <span class="field-input-prefix">R$</span>
                        <input class="field-input has-prefix" type="text" name="cob_incendio" data-mask="currency" placeholder="0,00">
                    </div>
                </div>

                <div class="cobertura-row">
                    <label class="cobertura-row-label">Danos Elétricos</label>
                    <div class="field-input-wrap">
                        <span class="field-input-prefix">R$</span>
                        <input class="field-input has-prefix" type="text" name="cob_danos_eletricos" data-mask="currency" placeholder="0,00">
                    </div>
                </div>

                <div class="cobertura-row">
                    <label class="cobertura-row-label">Vazamento Tub. Água e Esgoto</label>
                    <div class="field-input-wrap">
                        <span class="field-input-prefix">R$</span>
                        <input class="field-input has-prefix" type="text" name="cob_vazamento" data-mask="currency" placeholder="0,00">
                    </div>
                </div>

                <div class="cobertura-row">
                    <label class="cobertura-row-label">RC Síndico</label>
                    <div class="field-input-wrap">
                        <span class="field-input-prefix">R$</span>
                        <input class="field-input has-prefix" type="text" name="cob_rc_sindico" data-mask="currency" placeholder="0,00">
                    </div>
                </div>

                <div class="cobertura-row">
                    <label class="cobertura-row-label">Roubo bens Condomínio</label>
                    <div class="field-input-wrap">
                        <span class="field-input-prefix">R$</span>
                        <input class="field-input has-prefix" type="text" name="cob_roubo_cond" data-mask="currency" placeholder="0,00">
                    </div>
                </div>

                <div class="cobertura-row">
                    <label class="cobertura-row-label">RC Condomínio</label>
                    <div class="field-input-wrap">
                        <span class="field-input-prefix">R$</span>
                        <input class="field-input has-prefix" type="text" name="cob_rc_condominio" data-mask="currency" placeholder="0,00">
                    </div>
                </div>

                <div class="cobertura-row">
                    <label class="cobertura-row-label">Perda/Pagamento de Aluguel</label>
                    <div class="field-input-wrap">
                        <span class="field-input-prefix">R$</span>
                        <input class="field-input has-prefix" type="text" name="cob_perda_aluguel" data-mask="currency" placeholder="0,00">
                    </div>
                </div>

                <div class="cobertura-row">
                    <label class="cobertura-row-label">RC Danos Morais</label>
                    <div class="field-input-wrap">
                        <span class="field-input-prefix">R$</span>
                        <input class="field-input has-prefix" type="text" name="cob_rc_danos_morais" data-mask="currency" placeholder="0,00">
                    </div>
                </div>

                <div class="cobertura-row">
                    <label class="cobertura-row-label">Roubo bens dos Condôminos</label>
                    <div class="field-input-wrap">
                        <span class="field-input-prefix">R$</span>
                        <input class="field-input has-prefix" type="text" name="cob_roubo_condominios" data-mask="currency" placeholder="0,00">
                    </div>
                </div>

                <div class="cobertura-row">
                    <label class="cobertura-row-label">RC Empregador</label>
                    <div class="field-input-wrap">
                        <span class="field-input-prefix">R$</span>
                        <input class="field-input has-prefix" type="text" name="cob_rc_empregador" data-mask="currency" placeholder="0,00">
                    </div>
                </div>

                <div class="cobertura-row">
                    <label class="cobertura-row-label">Incêndio bens dos Condôminos</label>
                    <div class="field-input-wrap">
                        <span class="field-input-prefix">R$</span>
                        <input class="field-input has-prefix" type="text" name="cob_incendio_condominios" data-mask="currency" placeholder="0,00">
                    </div>
                </div>

                <div class="cobertura-row">
                    <label class="cobertura-row-label">RC Portões</label>
                    <div class="field-input-wrap">
                        <span class="field-input-prefix">R$</span>
                        <input class="field-input has-prefix" type="text" name="cob_rc_portoes" data-mask="currency" placeholder="0,00">
                    </div>
                </div>

                <div class="cobertura-row">
                    <label class="cobertura-row-label">Equipamentos Eletrônicos</label>
                    <div class="field-input-wrap">
                        <span class="field-input-prefix">R$</span>
                        <input class="field-input has-prefix" type="text" name="cob_equipamentos_eletronicos" data-mask="currency" placeholder="0,00">
                    </div>
                </div>

                <div class="cobertura-row">
                    <label class="cobertura-row-label">Vendaval</label>
                    <div class="field-input-wrap">
                        <span class="field-input-prefix">R$</span>
                        <input class="field-input has-prefix" type="text" name="cob_vendaval" data-mask="currency" placeholder="0,00">
                    </div>
                </div>

                <div class="cobertura-row">
                    <label class="cobertura-row-label">Impacto de Veículos</label>
                    <div class="field-input-wrap">
                        <span class="field-input-prefix">R$</span>
                        <input class="field-input has-prefix" type="text" name="cob_impacto_veiculos" data-mask="currency" placeholder="0,00">
                    </div>
                </div>

                <div class="cobertura-row">
                    <label class="cobertura-row-label">Tumulto e Greve</label>
                    <div class="field-input-wrap">
                        <span class="field-input-prefix">R$</span>
                        <input class="field-input has-prefix" type="text" name="cob_tumulto_greve" data-mask="currency" placeholder="0,00">
                    </div>
                </div>

                <div class="cobertura-row">
                    <label class="cobertura-row-label">Quebra de Vidros</label>
                    <div class="field-input-wrap">
                        <span class="field-input-prefix">R$</span>
                        <input class="field-input has-prefix" type="text" name="cob_quebra_vidros" data-mask="currency" placeholder="0,00">
                    </div>
                </div>

                <div class="cobertura-row">
                    <label class="cobertura-row-label">Despesas Fixas</label>
                    <div class="field-input-wrap">
                        <span class="field-input-prefix">R$</span>
                        <input class="field-input has-prefix" type="text" name="cob_despesas_fixas" data-mask="currency" placeholder="0,00">
                    </div>
                </div>

                <div class="cobertura-row">
                    <label class="cobertura-row-label">RC Garagista Compreensiva</label>
                    <div class="field-input-wrap">
                        <span class="field-input-prefix">R$</span>
                        <input class="field-input has-prefix" type="text" name="cob_rc_garagista_compreensiva" data-mask="currency" placeholder="0,00">
                    </div>
                </div>

                <div class="cobertura-row">
                    <label class="cobertura-row-label">Lucros Cessantes</label>
                    <div class="field-input-wrap">
                        <span class="field-input-prefix">R$</span>
                        <input class="field-input has-prefix" type="text" name="cob_lucros_cessantes" data-mask="currency" placeholder="0,00">
                    </div>
                </div>

                <div class="cobertura-row">
                    <label class="cobertura-row-label">RC Garagista Exclusiva</label>
                    <div class="field-input-wrap">
                        <span class="field-input-prefix">R$</span>
                        <input class="field-input has-prefix" type="text" name="cob_rc_garagista_exclusiva" data-mask="currency" placeholder="0,00">
                    </div>
                </div>

                <div class="cobertura-row">
                    <label class="cobertura-row-label">Desmoronamento</label>
                    <div class="field-input-wrap">
                        <span class="field-input-prefix">R$</span>
                        <input class="field-input has-prefix" type="text" name="cob_desmoronamento" data-mask="currency" placeholder="0,00">
                    </div>
                </div>

                <div class="cobertura-row">
                    <label class="cobertura-row-label">Vida em Grupo Funcionários</label>
                    <div class="field-input-wrap">
                        <span class="field-input-prefix">R$</span>
                        <input class="field-input has-prefix" type="text" name="cob_vida_grupo" data-mask="currency" placeholder="0,00">
                    </div>
                </div>

                <div class="cobertura-row">
                    <label class="cobertura-row-label">Alagamento</label>
                    <div class="field-input-wrap">
                        <span class="field-input-prefix">R$</span>
                        <input class="field-input has-prefix" type="text" name="cob_alagamento" data-mask="currency" placeholder="0,00">
                    </div>
                </div>

                <div class="cobertura-row">
                    <label class="cobertura-row-label">Chuveiros/Sprinkler</label>
                    <div class="field-input-wrap">
                        <span class="field-input-prefix">R$</span>
                        <input class="field-input has-prefix" type="text" name="cob_chuveiros" data-mask="currency" placeholder="0,00">
                    </div>
                </div>

                <div class="cobertura-row">
                    <label class="cobertura-row-label">Placas Solares</label>
                    <div class="field-input-wrap">
                        <span class="field-input-prefix">R$</span>
                        <input class="field-input has-prefix" type="text" name="cob_placas_solares" data-mask="currency" placeholder="0,00">
                    </div>
                </div>

            </div>
        </div>

        <div class="form-section">
            <h3 class="form-section-title"><?= icon('user') ?> Dados do Síndico</h3>

            <div class="form-grid-3">

                <div class="field">
                    <label class="field-label" for="sindico_nome">
                        Nome do Síndico <span class="required">*</span>
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon"><?= icon('user') ?></span>
                        <input class="field-input has-icon" type="text" id="sindico_nome" name="sindico_nome" maxlength="255"
                               placeholder="Nome completo" autocomplete="off" required>
                    </div>
                </div>

                <div class="field">
                    <label class="field-label" for="sindico_telefone">
                        Telefone <span class="required">*</span>
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon"><?= icon('phone') ?></span>
                        <input class="field-input has-icon" type="text" id="sindico_telefone" name="sindico_telefone"
                               data-mask="phone" placeholder="(99) 99999-9999" required>
                    </div>
                </div>

                <div class="field">
                    <label class="field-label" for="sindico_email">
                        E-mail <span class="required">*</span>
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon"><?= icon('envelope-simple') ?></span>
                        <input class="field-input has-icon" type="email" id="sindico_email" name="sindico_email" maxlength="255"
                               placeholder="sindico@condominio.com.br" required>
                    </div>
                </div>

            </div>
        </div>

        <div class="stepper-nav">
            <div class="stepper-nav-left">
                <button type="button" class="btn btn-sm btn-secondary" data-prev>
                    <?= icon('arrow-left') ?> Anterior
                </button>
            </div>
            <div class="stepper-nav-right">
                <button type="button" class="btn btn-sm btn-primary" id="btn-submit-condominial">
                    <?= icon('check') ?> Enviar cotação
                    <span class="spinner"></span>
                </button>
            </div>
        </div>
    </div>

</form>
