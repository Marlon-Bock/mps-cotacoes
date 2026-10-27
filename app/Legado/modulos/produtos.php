<?php
/**
 * Helpers do módulo de produtos (Seguro Auto, Título de Capitalização,
 * Seguro Condominial e Rascunhos).
 *
 * Os campos com ícone eram funções declaradas dentro dos _form.php antigos
 * (cpf_field/cep_field no auto, *_titulo no título, *_cond no condominial).
 * Viraram helpers aqui porque Blade não pode declarar função (o partial é
 * incluído também pelas páginas de embed). Os do auto ganharam o prefixo
 * "auto_" porque cpf_field/cep_field também existem no formulário de fiança.
 */

if (!function_exists('auto_cpf_field')) {
    /** Auto: campo CPF/CNPJ com ícone + status (era cpf_field() do auto/_form.php). */
    function auto_cpf_field(string $id, string $name, string $statusId = '', bool $required = false): string
    {
        $sid = $statusId ?: "{$id}-status";
        $req = $required ? ' required' : '';
        return '<div class="field-input-wrap">'
            . '<span class="field-input-icon">' . icon('identification-card') . '</span>'
            . '<input class="field-input has-icon has-icon-both" type="text" id="' . e($id) . '" name="' . e($name) . '"'
            . ' data-mask="cpfcnpj" data-cpf-status="' . e($sid) . '" placeholder="000.000.000-00" autocomplete="off"' . $req . '>'
            . '<span class="field-input-status" id="' . e($sid) . '" hidden></span>'
            . '</div>';
    }
}

if (!function_exists('auto_cep_field')) {
    /** Auto: campo CEP com ícone (era cep_field() do auto/_form.php). */
    function auto_cep_field(string $id, string $name, bool $autofill = false): string
    {
        $af = $autofill ? ' data-cep-autofill="1"' : '';
        return '<div class="field-input-wrap">'
            . '<span class="field-input-icon">' . icon('house') . '</span>'
            . '<input class="field-input has-icon" type="text" id="' . e($id) . '" name="' . e($name) . '"'
            . ' data-mask="cep" placeholder="00000-000"' . $af . '>'
            . '</div>';
    }
}

if (!function_exists('cpf_field_titulo')) {
    /** Título: campo CPF/CNPJ com ícone + status. */
    function cpf_field_titulo(string $id, string $name, string $statusId = '', bool $required = false): string
    {
        $sid = $statusId ?: "{$id}-status";
        $req = $required ? ' required' : '';
        return '<div class="field-input-wrap">'
            . '<span class="field-input-icon">' . icon('identification-card') . '</span>'
            . '<input class="field-input has-icon has-icon-both" type="text" id="' . e($id) . '" name="' . e($name) . '"'
            . ' data-mask="cpfcnpj" data-cpf-status="' . e($sid) . '" placeholder="000.000.000-00" autocomplete="off"' . $req . '>'
            . '<span class="field-input-status" id="' . e($sid) . '" hidden></span>'
            . '</div>';
    }
}

if (!function_exists('cep_field_titulo')) {
    /** Título: campo CEP com ícone. */
    function cep_field_titulo(string $id, string $name, bool $autofill = false): string
    {
        $af = $autofill ? ' data-cep-autofill="1"' : '';
        return '<div class="field-input-wrap">'
            . '<span class="field-input-icon">' . icon('house') . '</span>'
            . '<input class="field-input has-icon" type="text" id="' . e($id) . '" name="' . e($name) . '"'
            . ' data-mask="cep" placeholder="00000-000"' . $af . '>'
            . '</div>';
    }
}

if (!function_exists('cnpj_field_cond')) {
    /** Condominial: campo CNPJ com ícone + status. */
    function cnpj_field_cond(string $id, string $name, string $statusId = '', bool $required = false): string
    {
        $sid = $statusId ?: "{$id}-status";
        $req = $required ? ' required' : '';
        return '<div class="field-input-wrap">'
            . '<span class="field-input-icon">' . icon('identification-card') . '</span>'
            . '<input class="field-input has-icon has-icon-both" type="text" id="' . e($id) . '" name="' . e($name) . '"'
            . ' data-mask="cpfcnpj" data-cpf-status="' . e($sid) . '" placeholder="00.000.000/0000-00" autocomplete="off"' . $req . '>'
            . '<span class="field-input-status" id="' . e($sid) . '" hidden></span>'
            . '</div>';
    }
}

if (!function_exists('cep_field_cond')) {
    /** Condominial: campo CEP com ícone. */
    function cep_field_cond(string $id, string $name, bool $autofill = false): string
    {
        $af = $autofill ? ' data-cep-autofill="1"' : '';
        return '<div class="field-input-wrap">'
            . '<span class="field-input-icon">' . icon('house') . '</span>'
            . '<input class="field-input has-icon" type="text" id="' . e($id) . '" name="' . e($name) . '"'
            . ' data-mask="cep" placeholder="00000-000"' . $af . '>'
            . '</div>';
    }
}
