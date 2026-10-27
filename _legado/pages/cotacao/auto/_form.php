<?php
/**
 * Formulário de cotação Seguro Auto — 3 steps.
 */

$combustiveis  = ['Flex', 'Gasolina', 'Etanol', 'Diesel', 'GNV', 'Elétrico', 'Híbrido'];
$estados_civis = ['Solteiro(a)', 'Casado(a)', 'Divorciado(a)', 'Viúvo(a)', 'União Estável', 'Separado(a)'];
$tipos_veiculo = ['carros' => 'Automóvel', 'motos' => 'Motocicleta', 'caminhoes' => 'Caminhão'];

/* Helper local: cpf/cnpj field com ícone + status */
function cpf_field(string $id, string $name, string $statusId = '', bool $required = false): string {
    $sid = $statusId ?: "{$id}-status";
    $req = $required ? ' required' : '';
    return '<div class="field-input-wrap">'
        . '<span class="field-input-icon">' . icon('identification-card') . '</span>'
        . '<input class="field-input has-icon has-icon-both" type="text" id="' . e($id) . '" name="' . e($name) . '"'
        . ' data-mask="cpfcnpj" data-cpf-status="' . e($sid) . '" placeholder="000.000.000-00" autocomplete="off"' . $req . '>'
        . '<span class="field-input-status" id="' . e($sid) . '" hidden></span>'
        . '</div>';
}

/* Helper local: cep field com ícone */
function cep_field(string $id, string $name, bool $autofill = false): string {
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
        <span class="stepper-step-label">Veículo</span>
    </div>
    <div class="stepper-connector"></div>
    <div class="stepper-step" data-step-indicator="2">
        <span class="stepper-num">2</span>
        <span class="stepper-step-label">Segurado</span>
    </div>
    <div class="stepper-connector"></div>
    <div class="stepper-step" data-step-indicator="3">
        <span class="stepper-num">3</span>
        <span class="stepper-step-label">Condutor</span>
    </div>
</div>

<form id="form-cotacao-auto" novalidate>
    <?php if (!empty($rascunho_protocolo)): ?>
    <input type="hidden" name="rascunho" value="<?= e($rascunho_protocolo) ?>">
    <?php endif; ?>

    <!-- ══════════════════════════════════════════════════════════════════
         STEP 1 — Dados do Veículo
    ══════════════════════════════════════════════════════════════════ -->
    <div class="stepper-panel is-active" data-step="1">

        <div class="form-section">
            <h3 class="form-section-title"><?= icon('car') ?> Informações do Veículo</h3>

            <!-- Tipo do seguro — seleção única -->
            <div class="field">
                <label class="field-label">
                    Tipo de seguro <span class="required">*</span>
                    <span class="field-error" hidden></span>
                </label>
                <div class="switch-group">
                    <label class="switch">
                        <input type="radio" name="tipo_seguro" value="seguro_novo" required>
                        <span class="switch-track"></span>
                        <span class="switch-label">Seguro novo</span>
                    </label>
                    <label class="switch">
                        <input type="radio" name="tipo_seguro" value="renovacao_cia">
                        <span class="switch-track"></span>
                        <span class="switch-label">Renovação</span>
                    </label>
                </div>
            </div>

            <!-- FIPE: 4 colunas -->
            <div class="form-grid-4">

                <div class="field">
                    <label class="field-label">
                        Tipo de veículo <span class="required">*</span>
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon"><?= icon('car') ?></span>
                        <?= custom_select('tipo_veiculo', $tipos_veiculo, 'Selecione...', null, 'has-icon', true, ['id' => 'fipe-tipo']) ?>
                    </div>
                </div>

                <div class="field">
                    <label class="field-label">
                        Marca <span class="required">*</span>
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon"><?= icon('gear') ?></span>
                        <?= custom_select('marca_id', [], 'Selecione o tipo primeiro', null, 'has-icon', false, ['id' => 'fipe-marca', 'data-fipe' => 'marca']) ?>
                        <input type="hidden" name="marca_nome" id="marca_nome">
                    </div>
                </div>

                <div class="field">
                    <label class="field-label">
                        Modelo <span class="required">*</span>
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon"><?= icon('clipboard-text') ?></span>
                        <?= custom_select('modelo_id', [], 'Selecione a marca primeiro', null, 'has-icon', false, ['id' => 'fipe-modelo', 'data-fipe' => 'modelo']) ?>
                        <input type="hidden" name="modelo_nome" id="modelo_nome">
                    </div>
                </div>

                <div class="field">
                    <label class="field-label">
                        Ano <span class="required">*</span>
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon"><?= icon('calendar-blank') ?></span>
                        <?= custom_select('ano_id', [], 'Selecione o modelo primeiro', null, 'has-icon', false, ['id' => 'fipe-ano', 'data-fipe' => 'ano']) ?>
                        <input type="hidden" name="ano_nome" id="ano_nome">
                    </div>
                </div>

            </div>

            <!-- Placa, Chassi, Combustível: 3 colunas -->
            <div class="form-grid-3">

                <div class="field">
                    <label class="field-label" for="placa">
                        Placa <span class="required">*</span>
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon"><?= icon('identification-card') ?></span>
                        <input class="field-input has-icon" type="text" id="placa" name="placa"
                               data-mask="placa" placeholder="ABC-1234" maxlength="8" autocomplete="off" required>
                    </div>
                </div>

                <div class="field">
                    <label class="field-label" for="chassi">
                        Chassi
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon"><?= icon('key') ?></span>
                        <input class="field-input has-icon" type="text" id="chassi" name="chassi"
                               placeholder="Opcional" maxlength="17" autocomplete="off">
                    </div>
                </div>

                <div class="field">
                    <label class="field-label">
                        Combustível <span class="required">*</span>
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon"><?= icon('fire') ?></span>
                        <?= custom_select('combustivel', $combustiveis, 'Selecione...', null, 'has-icon', true) ?>
                    </div>
                </div>

            </div>

            <!-- Kit gás, Utilizado trabalho, Ir ao trabalho: 3 colunas -->
            <div class="radio-row-3">

                <div class="field">
                    <label class="field-label">
                        Kit gás?
                        <span class="field-error" hidden></span>
                    </label>
                    <label class="switch">
                        <input type="hidden" name="kit_gas" value="Nao">
                        <input type="checkbox" name="kit_gas" value="Sim">
                        <span class="switch-track"></span>
                        <span class="switch-label">Sim</span>
                    </label>
                </div>

                <div class="field">
                    <label class="field-label">
                        Utilizado para trabalho?
                        <span class="field-error" hidden></span>
                    </label>
                    <label class="switch">
                        <input type="hidden" name="utilizado_trabalho" value="Nao">
                        <input type="checkbox" name="utilizado_trabalho" value="Sim">
                        <span class="switch-track"></span>
                        <span class="switch-label">Sim</span>
                    </label>
                </div>

                <div class="field">
                    <label class="field-label">
                        Ir ao trabalho?
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="ir-trabalho-row">
                        <label class="switch">
                            <input type="hidden" name="utilizado_ir_trabalho" value="Nao">
                            <input type="checkbox" name="utilizado_ir_trabalho" value="Sim">
                            <span class="switch-track"></span>
                            <span class="switch-label">Sim</span>
                        </label>
                        <div class="field-input-wrap ir-trabalho-km">
                            <span class="field-input-icon"><?= icon('gauge') ?></span>
                            <input class="field-input has-icon" type="text" id="km_distancia_trabalho"
                                   name="km_distancia_trabalho" data-mask="numeric"
                                   placeholder="Distância em km" disabled>
                        </div>
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
         STEP 2 — Dados do Segurado
    ══════════════════════════════════════════════════════════════════ -->
    <div class="stepper-panel" data-step="2">

        <div class="form-section">
            <h3 class="form-section-title"><?= icon('user') ?> Informações do Segurado</h3>
            <div class="form-grid-3">

                <div class="field">
                    <label class="field-label" for="segurado_nome">
                        Nome completo <span class="required">*</span>
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon"><?= icon('user') ?></span>
                        <input class="field-input has-icon" type="text" id="segurado_nome" name="segurado_nome" maxlength="140"
                               placeholder="Nome completo" autocomplete="name" required>
                    </div>
                </div>

                <div class="field">
                    <label class="field-label" for="segurado_cpf">
                        CPF <span class="required">*</span>
                        <span class="field-error" hidden></span>
                    </label>
                    <?= cpf_field('segurado_cpf', 'segurado_cpf', 'segurado-cpf-status', true) ?>
                </div>

                <div class="field">
                    <label class="field-label" for="segurado_data_nasc">
                        Data de nascimento
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon"><?= icon('calendar-blank') ?></span>
                        <input class="field-input has-icon" type="text" id="segurado_data_nasc"
                               name="segurado_data_nasc" data-mask="date"
                               placeholder="DD/MM/AAAA" maxlength="10">
                    </div>
                </div>

            </div>

            <div class="form-grid-4">

                <div class="field">
                    <label class="field-label">
                        Estado civil
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon"><?= icon('user') ?></span>
                        <?= custom_select('segurado_estado_civil', $estados_civis, 'Selecione...', null, 'has-icon') ?>
                    </div>
                </div>

                <div class="field">
                    <label class="field-label" for="segurado_cep">
                        CEP
                        <span class="field-error" hidden></span>
                    </label>
                    <?= cep_field('segurado_cep', 'segurado_cep', true) ?>
                </div>

                <div class="field">
                    <label class="field-label" for="segurado_bairro">
                        Bairro
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon"><?= icon('buildings') ?></span>
                        <input class="field-input has-icon" type="text" id="segurado_bairro"
                               name="segurado_bairro" maxlength="100" placeholder="Nome do bairro">
                    </div>
                </div>

                <div class="field">
                    <label class="field-label" for="segurado_uf">
                        UF
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon"><?= icon('buildings') ?></span>
                        <input class="field-input has-icon" type="text" id="segurado_uf"
                               name="segurado_uf" placeholder="SP" maxlength="2">
                    </div>
                </div>

            </div>

            <div class="form-grid-4">

                <div class="field">
                    <label class="field-label" for="segurado_endereco">
                        Endereço
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon"><?= icon('house') ?></span>
                        <input class="field-input has-icon" type="text" id="segurado_endereco"
                               name="segurado_endereco" maxlength="160" placeholder="Rua, Avenida...">
                    </div>
                </div>

                <div class="field">
                    <label class="field-label" for="segurado_numero">
                        Número
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon"><?= icon('house') ?></span>
                        <input class="field-input has-icon" type="text" id="segurado_numero"
                               name="segurado_numero" maxlength="20" placeholder="123">
                    </div>
                </div>

                <div class="field">
                    <label class="field-label" for="segurado_complemento">
                        Complemento
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon"><?= icon('note-pencil') ?></span>
                        <input class="field-input has-icon" type="text" id="segurado_complemento"
                               name="segurado_complemento" maxlength="80" placeholder="Apto, Sala...">
                    </div>
                </div>

                <div class="field">
                    <label class="field-label" for="segurado_cidade">
                        Cidade
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon"><?= icon('buildings') ?></span>
                        <input class="field-input has-icon" type="text" id="segurado_cidade"
                               name="segurado_cidade" maxlength="100" placeholder="Cidade">
                    </div>
                </div>

            </div>
        </div>

        <div class="form-section">
            <h3 class="form-section-title"><?= icon('house') ?> Garagem</h3>
            <p class="form-section-hint">Informe onde o veículo fica guardado normalmente.</p>

            <div class="garagem-grid">

                <div class="garagem-item">
                    <label class="switch">
                        <input type="checkbox" name="garagem_residencia" value="1" id="garagem_residencia">
                        <span class="switch-track"></span>
                        <span class="switch-label">Residência</span>
                    </label>
                    <label class="switch">
                        <input type="checkbox" name="portao_residencia" value="1" id="portao_residencia" disabled>
                        <span class="switch-track"></span>
                        <span class="switch-label">Portão eletrônico</span>
                    </label>
                </div>

                <div class="garagem-item">
                    <label class="switch">
                        <input type="checkbox" name="garagem_trabalho" value="1" id="garagem_trabalho">
                        <span class="switch-track"></span>
                        <span class="switch-label">Trabalho</span>
                    </label>
                    <label class="switch">
                        <input type="checkbox" name="portao_trabalho" value="1" id="portao_trabalho" disabled>
                        <span class="switch-track"></span>
                        <span class="switch-label">Portão eletrônico</span>
                    </label>
                </div>

                <div class="garagem-item">
                    <label class="switch">
                        <input type="checkbox" name="garagem_faculdade" value="1" id="garagem_faculdade">
                        <span class="switch-track"></span>
                        <span class="switch-label">Faculdade</span>
                    </label>
                    <label class="switch">
                        <input type="checkbox" name="portao_faculdade" value="1" id="portao_faculdade" disabled>
                        <span class="switch-track"></span>
                        <span class="switch-label">Portão eletrônico</span>
                    </label>
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
         STEP 3 — Principal Condutor
    ══════════════════════════════════════════════════════════════════ -->
    <div class="stepper-panel" data-step="3">

        <div class="form-section">
            <h3 class="form-section-title"><?= icon('user') ?> Principal Condutor</h3>

            <div class="form-section-hint">
                <button type="button" class="btn btn-secondary btn-sm" id="btn-copiar-segurado">
                    <?= icon('check-circle') ?> Mesmo que o segurado
                </button>
            </div>

            <div class="form-grid-3">

                <div class="field">
                    <label class="field-label" for="condutor_nome">
                        Nome completo
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon"><?= icon('user') ?></span>
                        <input class="field-input has-icon" type="text" id="condutor_nome" name="condutor_nome" maxlength="140"
                               placeholder="Nome completo" autocomplete="off">
                    </div>
                </div>

                <div class="field">
                    <label class="field-label" for="condutor_cpf">
                        CPF
                        <span class="field-error" hidden></span>
                    </label>
                    <?= cpf_field('condutor_cpf', 'condutor_cpf', 'condutor-cpf-status') ?>
                </div>

                <div class="field">
                    <label class="field-label" for="condutor_data_nasc">
                        Data de nascimento
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon"><?= icon('calendar-blank') ?></span>
                        <input class="field-input has-icon" type="text" id="condutor_data_nasc"
                               name="condutor_data_nasc" data-mask="date"
                               placeholder="DD/MM/AAAA" maxlength="10">
                    </div>
                </div>

            </div>

            <div class="form-grid-4">

                <div class="field">
                    <label class="field-label">
                        Estado civil
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon"><?= icon('user') ?></span>
                        <?= custom_select('condutor_estado_civil', $estados_civis, 'Selecione...', null, 'has-icon') ?>
                    </div>
                </div>

                <div class="field">
                    <label class="field-label" for="condutor_relacao_segurado">
                        Relação com o segurado
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon"><?= icon('user') ?></span>
                        <input class="field-input has-icon" type="text" id="condutor_relacao_segurado"
                               name="condutor_relacao_segurado" maxlength="80"
                               placeholder="Ex: Cônjuge, Filho(a), Próprio">
                    </div>
                </div>

                <div class="field">
                    <label class="field-label">
                        Condutor menor de 25 anos?
                        <span class="field-error" hidden></span>
                    </label>
                    <label class="switch">
                        <input type="hidden" name="condutor_menor_25" value="Nao">
                        <input type="checkbox" name="condutor_menor_25" value="Sim">
                        <span class="switch-track"></span>
                        <span class="switch-label">Sim</span>
                    </label>
                </div>

                <div class="field" id="field-condutor-sexo">
                    <label class="field-label">
                        Sexo do condutor menor
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="switch-group">
                        <label class="switch">
                            <input type="radio" name="condutor_menor_25_sexo" value="Feminino" disabled>
                            <span class="switch-track"></span>
                            <span class="switch-label">Feminino</span>
                        </label>
                        <label class="switch">
                            <input type="radio" name="condutor_menor_25_sexo" value="Masculino" disabled>
                            <span class="switch-track"></span>
                            <span class="switch-label">Masculino</span>
                        </label>
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
                <button type="button" class="btn btn-sm btn-primary" id="btn-submit-auto">
                    <?= icon('check') ?> Enviar cotação
                    <span class="spinner"></span>
                </button>
            </div>
        </div>
    </div>

</form>
