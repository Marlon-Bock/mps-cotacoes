<?php
/**
 * Formulário de cotação Seguro Fiança — 2 abas (Obrigatórios / Opcionais).
 * A ficha é cotada na API das seguradoras (ver src/helpers/api.php); por
 * isso pede só o que as seguradoras usam — e-mail é obrigatório.
 * Variável $tipo_ficha: 'residencial' | 'comercial'
 */

$indices = array_combine(
    ['IGP-M','IPG','IPC','IPCA','INPC','ICV','IGPDI'],
    ['IGP-M','IPG','IPC','IPCA','INPC','ICV','IGPDI']
);

// Too Seguros ligada e ficha residencial: ela exige nascimento, renda,
// vínculo e profissão do pretendente (e nascimento dos adicionais).
require_once BASE_PATH . '/src/helpers/api.php';
$campos_too = ($tipo_ficha ?? '') === 'residencial' && in_array('too', array_keys(api_seguradoras()), true);
$vinculos_too = array_combine(fianca_vinculos_empregaticios(), fianca_vinculos_empregaticios());

$vigencias = [];
for ($m = 1; $m <= 60; $m++) {
    $vigencias[$m] = "{$m} " . ($m === 1 ? 'mês' : 'meses');
}

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

/* Helper local: cep field com ícone + hint */
function cep_field(string $id, string $name, bool $autofill = false, bool $required = false): string {
    $af  = $autofill ? ' data-cep-autofill="1"' : '';
    $req = $required ? ' required' : '';
    return '<div class="field-input-wrap">'
        . '<span class="field-input-icon">' . icon('house') . '</span>'
        . '<input class="field-input has-icon" type="text" id="' . e($id) . '" name="' . e($name) . '"'
        . ' data-mask="cep" placeholder="00000-000"' . $af . $req . '>'
        . '</div>';
}
?>

<!-- Indicador de progresso -->
<div class="stepper-header">
    <div class="stepper-step is-active">
        <span class="stepper-num">1</span>
        <span class="stepper-step-label">Dados do Pretendente e Imóvel</span>
    </div>
    <div class="stepper-connector"></div>
    <div class="stepper-step">
        <span class="stepper-num">2</span>
        <span class="stepper-step-label">Informações Opcionais</span>
    </div>
</div>

<form id="form-fianca" novalidate>
    <input type="hidden" name="tipo_ficha" value="<?= e($tipo_ficha) ?>">
    <?php if (!empty($rascunho_protocolo)): ?>
    <input type="hidden" name="rascunho" value="<?= e($rascunho_protocolo) ?>">
    <?php endif; ?>

    <!-- ══════════════════════════════════════════════════════════════════
         STEP 1 — Campos Obrigatórios
    ══════════════════════════════════════════════════════════════════ -->
    <div class="stepper-panel is-active" data-step="1">

        <div class="form-section">
            <h3 class="form-section-title"><?= icon('user') ?> Dados do Pretendente</h3>
            <div class="form-grid">

                <div class="field">
                    <label class="field-label" for="nome_completo">
                        Nome ou razão social <span class="required">*</span>
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon"><?= icon('user') ?></span>
                        <input class="field-input has-icon" type="text" id="nome_completo" name="nome_completo" maxlength="100"
                               placeholder="Nome como no documento" autocomplete="name" required>
                    </div>
                </div>

                <div class="field">
                    <label class="field-label" for="cpf_cnpj">
                        CPF / CNPJ <span class="required">*</span>
                        <span class="field-error" hidden></span>
                    </label>
                    <?= cpf_field('cpf_cnpj', 'cpf_cnpj', 'cpf-status', true) ?>
                </div>

                <div class="field">
                    <label class="field-label" for="tel_celular">
                        Telefone
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon"><?= icon('phone') ?></span>
                        <input class="field-input has-icon" type="tel" id="tel_celular" name="tel_celular"
                               data-mask="phone" placeholder="(00) 00000-0000" autocomplete="tel">
                    </div>
                </div>

                <div class="field">
                    <label class="field-label" for="email">
                        E-mail <span class="required">*</span>
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon"><?= icon('envelope-simple') ?></span>
                        <input class="field-input has-icon" type="email" id="email" name="email" maxlength="100"
                               placeholder="pretendente@email.com" autocomplete="email" required>
                    </div>
                </div>

                <?php if ($campos_too): /* exigidos pela Too Seguros */ ?>
                <div class="field">
                    <label class="field-label" for="data_nascimento">
                        Data de nascimento <span class="required">*</span>
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon"><?= icon('calendar-blank') ?></span>
                        <input class="field-input has-icon" type="text" id="data_nascimento" name="data_nascimento"
                               data-mask="date" maxlength="10" placeholder="DD/MM/AAAA" inputmode="numeric" required>
                    </div>
                </div>

                <div class="field">
                    <label class="field-label" for="salario_bruto">
                        Renda mensal <span class="required">*</span>
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon"><?= icon('currency-dollar') ?></span>
                        <input class="field-input has-icon" type="text" id="salario_bruto" name="salario_bruto"
                               data-mask="currency" placeholder="R$ 0,00" required>
                    </div>
                </div>

                <div class="field">
                    <label class="field-label" for="vinculo_empregaticio">
                        Vínculo empregatício <span class="required">*</span>
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon"><?= icon('identification-card') ?></span>
                        <?= custom_select('vinculo_empregaticio', $vinculos_too, 'Selecione...', null, 'has-icon', true) ?>
                    </div>
                </div>

                <div class="field">
                    <label class="field-label" for="profissao">
                        Profissão <span class="required">*</span>
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon"><?= icon('briefcase') ?></span>
                        <input class="field-input has-icon" type="text" id="profissao" name="profissao"
                               maxlength="40" placeholder="Ex: Analista administrativo" required>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>

        <!-- Pretendentes adicionais — quantidade livre, adicionados via JS -->
        <div class="form-section">
            <h3 class="form-section-title"><?= icon('users') ?> Pretendentes Adicionais</h3>

            <div id="pretendentes-extras-list"></div>

            <button type="button" class="btn btn-sm btn-secondary" id="btn-add-pretendente">
                <?= icon('plus') ?> Adicionar pretendente
            </button>

            <template id="tpl-pretendente-extra">
                <div class="extra-pretendente is-open">
                    <div class="extra-pretendente-header">
                        <span class="extra-pretendente-title" data-pretendente-titulo></span>
                        <button type="button" class="extra-pretendente-remove" data-remove-pretendente>
                            <?= icon('trash') ?> Remover
                        </button>
                    </div>
                    <div class="extra-pretendente-body is-open">
                        <div class="extra-pretendente-body-inner">
                            <div class="form-grid-3">

                                <div class="field">
                                    <label class="field-label">
                                        Nome completo <span class="required">*</span>
                                        <span class="field-error" hidden></span>
                                    </label>
                                    <div class="field-input-wrap">
                                        <span class="field-input-icon"><?= icon('user') ?></span>
                                        <input class="field-input has-icon" type="text"
                                               data-field="nome_completo" maxlength="100" placeholder="Nome como no documento" required>
                                    </div>
                                </div>

                                <div class="field">
                                    <label class="field-label">
                                        CPF / CNPJ <span class="required">*</span>
                                        <span class="field-error" hidden></span>
                                    </label>
                                    <div class="field-input-wrap">
                                        <span class="field-input-icon"><?= icon('identification-card') ?></span>
                                        <input class="field-input has-icon has-icon-both" type="text"
                                               data-field="cpf_cnpj" data-mask="cpfcnpj"
                                               placeholder="000.000.000-00" autocomplete="off" required>
                                        <span class="field-input-status" data-cpf-status-el hidden></span>
                                    </div>
                                </div>

                                <div class="field">
                                    <label class="field-label">Telefone</label>
                                    <div class="field-input-wrap">
                                        <span class="field-input-icon"><?= icon('phone') ?></span>
                                        <input class="field-input has-icon" type="tel"
                                               data-field="tel_celular" data-mask="phone" placeholder="(00) 00000-0000">
                                    </div>
                                </div>

                                <div class="field">
                                    <label class="field-label">E-mail</label>
                                    <div class="field-input-wrap">
                                        <span class="field-input-icon"><?= icon('envelope-simple') ?></span>
                                        <input class="field-input has-icon" type="email"
                                               data-field="email" maxlength="100" placeholder="pretendente@email.com">
                                    </div>
                                </div>

                                <div class="field">
                                    <label class="field-label">Renda mensal</label>
                                    <div class="field-input-wrap">
                                        <span class="field-input-icon"><?= icon('currency-dollar') ?></span>
                                        <input class="field-input has-icon" type="text"
                                               data-field="salario_bruto" data-mask="currency" placeholder="R$ 0,00">
                                    </div>
                                </div>

                                <?php if ($campos_too): /* a Too exige o nascimento de todos */ ?>
                                <div class="field">
                                    <label class="field-label">
                                        Data de nascimento <span class="required">*</span>
                                        <span class="field-error" hidden></span>
                                    </label>
                                    <div class="field-input-wrap">
                                        <span class="field-input-icon"><?= icon('calendar-blank') ?></span>
                                        <input class="field-input has-icon" type="text"
                                               data-field="data_nascimento" data-mask="date" maxlength="10" placeholder="DD/MM/AAAA" inputmode="numeric" required>
                                    </div>
                                </div>
                                <?php endif; ?>

                            </div>
                        </div>
                    </div>
                </div>
            </template>
        </div>

        <div class="form-section">
            <h3 class="form-section-title"><?= icon('house') ?> Endereço do Imóvel</h3>

            <?php if (!empty($imob_cliente_sami)): ?>
            <div class="field imovel-lookup-compact" data-endpoint="/api/cotacao/imovel-buscar">
                <label class="field-label" for="codigo_imovel">Código do imóvel (Sami)</label>
                <div class="field-input-wrap">
                    <span class="field-input-icon"><?= icon('key') ?></span>
                    <input class="field-input has-icon has-action" type="text" id="codigo_imovel"
                           name="codigo_imovel" maxlength="50" placeholder="Ex: 123" autocomplete="off">
                    <button type="button" class="field-input-action" id="btn-buscar-imovel" title="Buscar">
                        <?= icon('magnifying-glass') ?>
                    </button>
                </div>
                <span class="field-hint" id="imovel-lookup-status" hidden></span>
            </div>
            <?php endif; ?>

            <div class="form-grid-3">

                <div class="field">
                    <label class="field-label">
                        Vigência do contrato <span class="required">*</span>
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon"><?= icon('calendar-blank') ?></span>
                        <?= custom_select('vigencia_contrato', $vigencias, 'Selecione...', null, 'has-icon', true) ?>
                    </div>
                </div>

                <div class="field">
                    <label class="field-label" for="cep">
                        CEP do imóvel <span class="required">*</span>
                        <span class="field-error" hidden></span>
                    </label>
                    <?= cep_field('cep', 'cep', true, true) ?>
                </div>

                <div class="field">
                    <label class="field-label" for="uf">
                        UF <span class="required">*</span>
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon"><?= icon('buildings') ?></span>
                        <input class="field-input has-icon" type="text" id="uf" name="uf" maxlength="2" placeholder="SP" required>
                    </div>
                </div>

            </div>

            <div class="field">
                <label class="field-label" for="endereco">
                    Logradouro <span class="required">*</span>
                    <span class="field-error" hidden></span>
                </label>
                <div class="field-input-wrap">
                    <span class="field-input-icon"><?= icon('house') ?></span>
                    <input class="field-input has-icon" type="text" id="endereco" name="endereco" maxlength="255" placeholder="Rua, Avenida, Travessa..." required>
                </div>
            </div>

            <div class="form-grid-4">

                <div class="field">
                    <label class="field-label" for="numero">
                        Número <span class="required">*</span>
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon"><?= icon('house') ?></span>
                        <input class="field-input has-icon" type="text" id="numero" name="numero" maxlength="20" placeholder="123" required>
                    </div>
                </div>

                <div class="field">
                    <label class="field-label" for="complemento">Complemento</label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon"><?= icon('note-pencil') ?></span>
                        <input class="field-input has-icon" type="text" id="complemento" name="complemento" maxlength="100" placeholder="Apto, Sala, Bloco...">
                    </div>
                </div>

                <div class="field">
                    <label class="field-label" for="bairro">
                        Bairro <span class="required">*</span>
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon"><?= icon('buildings') ?></span>
                        <input class="field-input has-icon" type="text" id="bairro" name="bairro" maxlength="100" placeholder="Nome do bairro" required>
                    </div>
                </div>

                <div class="field">
                    <label class="field-label" for="cidade">
                        Cidade <span class="required">*</span>
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon"><?= icon('buildings') ?></span>
                        <input class="field-input has-icon" type="text" id="cidade" name="cidade" maxlength="100" placeholder="Nome da cidade" required>
                    </div>
                </div>

            </div>
        </div>

        <div class="form-section">
            <h3 class="form-section-title"><?= icon('currency-dollar') ?> Valores da Locação</h3>
            <div class="values-grid">

                <div class="field">
                    <label class="field-label" for="valor_aluguel">
                        Aluguel <span class="required">*</span>
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon"><?= icon('currency-dollar') ?></span>
                        <input class="field-input has-icon" type="text" id="valor_aluguel" name="valor_aluguel"
                               data-mask="currency" placeholder="R$ 0,00" required>
                    </div>
                </div>

                <?php foreach ([
                    'valor_agua'       => 'Água',
                    'valor_condominio' => 'Condomínio',
                    'valor_gas'        => 'Gás',
                    'valor_iptu'       => 'IPTU (mensal)',
                    'valor_energia'    => 'Luz',
                ] as $fname => $flabel): ?>
                <div class="field">
                    <label class="field-label" for="<?= $fname ?>">
                        <?= e($flabel) ?> <span class="required">*</span>
                        <span class="field-error" hidden></span>
                    </label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon"><?= icon('currency-dollar') ?></span>
                        <input class="field-input has-icon has-action" type="text" id="<?= $fname ?>" name="<?= $fname ?>"
                               data-mask="currency" placeholder="R$ 0,00" data-valor-locacao>
                        <button type="button" class="field-input-action" data-skip-toggle="<?= $fname ?>"
                                title="Não informar" aria-pressed="false">Não informar</button>
                        <input type="hidden" name="<?= $fname ?>_skip" value="0">
                    </div>
                </div>
                <?php endforeach; ?>

            </div>
        </div>

        <div class="stepper-nav">
            <div class="stepper-nav-left"></div>
            <div class="stepper-nav-right">
                <?php if (empty($ocultar_rascunho)): ?>
                <button type="button" id="btn-salvar-rascunho" class="btn btn-sm btn-secondary" data-csrf="<?= e(csrf_token()) ?>">
                    <?= icon('note-pencil') ?> Salvar Rascunho
                </button>
                <?php endif; ?>
                <button type="button" class="btn btn-sm btn-primary" data-next>
                    Próximo <?= icon('arrow-right') ?>
                </button>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════════════
         STEP 2 — Opcionais
    ══════════════════════════════════════════════════════════════════ -->
    <div class="stepper-panel" data-step="2">

        <div class="form-section">
            <h3 class="form-section-title"><?= icon('house') ?> Outros Dados do Imóvel</h3>
            <div class="form-grid">

                <div class="field span-full">
                    <label class="field-label">Índice de reajuste</label>
                    <div class="switch-grid indice-reajuste-grid">
                        <?php foreach ($indices as $indice_codigo => $indice_label): ?>
                        <div class="switch-row">
                            <label class="switch">
                                <input type="radio" name="indice_reajuste" value="<?= e($indice_codigo) ?>">
                                <span class="switch-track"></span>
                                <span class="switch-label"><?= e($indice_label) ?></span>
                            </label>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

            </div>
        </div>

        <div class="form-section">
            <h3 class="form-section-title"><?= icon('list') ?> Coberturas Adicionais</h3>
            <div class="switch-grid">
                <?php
                $coberturas_extras = [
                    'danos_imovel'    => [
                        'label' => 'Danos ao imóvel',
                        'desc'  => 'Garante indenização por danos físicos causados ao imóvel pelo inquilino. Limite máximo de indenização: até 6x o valor do aluguel.',
                    ],
                    'pintura_externa' => [
                        'label' => 'Pintura externa',
                        'desc'  => 'Cobre os custos de pintura externa do imóvel ao final da locação. Limite máximo de indenização: até 3x o valor do aluguel.',
                    ],
                    'multa_contratual'=> [
                        'label' => 'Multa contratual',
                        'desc'  => 'Cobre a multa por rescisão antecipada do contrato de locação. Limite máximo de indenização: até 3x o valor do aluguel.',
                    ],
                    'pintura_interna' => [
                        'label' => 'Pintura interna',
                        'desc'  => 'Cobre os custos de pintura interna do imóvel ao final da locação. Limite máximo de indenização: até 3x o valor do aluguel.',
                    ],
                    'cobertura_moveis'=> [
                        'label' => 'Móveis',
                        'desc'  => 'Cobre danos ou perda de móveis e utensílios que guarnecem o imóvel.',
                    ],
                ];
                foreach ($coberturas_extras as $cname => $cdata): ?>
                <div class="switch-row">
                    <label class="switch">
                        <input type="checkbox" name="<?= e($cname) ?>" value="1">
                        <span class="switch-track"></span>
                        <span class="switch-label"><?= e($cdata['label']) ?></span>
                    </label>
                    <span class="tooltip-info" tabindex="0" data-tooltip="<?= e($cdata['desc']) ?>"><?= icon('info') ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <div class="stepper-nav">
            <div class="stepper-nav-left">
                <button type="button" class="btn btn-sm btn-secondary" data-prev>
                    <?= icon('arrow-left') ?> Voltar
                </button>
            </div>
            <div class="stepper-nav-right">
                <button type="button" class="btn btn-sm btn-primary" id="btn-submit">
                    <?= icon('check') ?>
                    <span data-label>Enviar cotação</span>
                    <span class="spinner"></span>
                </button>
            </div>
        </div>
    </div>

</form>
