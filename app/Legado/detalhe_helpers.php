<?php
/**
 * Helpers de exibição compartilhados entre as páginas de detalhe de cotação.
 * Incluir antes de qualquer saída HTML.
 */

if (!function_exists('cv')) {
    function cv(mixed $v, string $fallback = 'Não preenchido'): string {
        $s = trim((string)($v ?? ''));
        return $s !== '' ? htmlspecialchars($s, ENT_QUOTES, 'UTF-8') : $fallback;
    }
}

if (!function_exists('cv_data')) {
    function cv_data(mixed $v): string {
        $s = trim((string)($v ?? ''));
        if ($s === '') return 'Não preenchido';
        $ts = strtotime($s);
        return $ts ? date('d/m/Y', $ts) : htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('cv_money')) {
    function cv_money(mixed $v): string {
        if ($v === null || $v === '') return 'Não preenchido';
        // Aceita tanto valor decimal puro quanto texto mascarado ("R$ 7.000,00")
        // gravado por formulários que ainda não normalizam o valor antes de salvar.
        $s = trim((string)$v);
        if (!is_numeric($s)) {
            $s = preg_replace('/[^\d,.-]/', '', $s);
            if (str_contains($s, ',')) {
                $s = str_replace('.', '', $s);
                $s = str_replace(',', '.', $s);
            }
        }
        if ($s === '' || !is_numeric($s)) return 'Não preenchido';
        return 'R$ ' . number_format((float)$s, 2, ',', '.');
    }
}

if (!function_exists('dfv')) {
    /** Emite <div class="detalhe-field-value"> com classe is-empty quando não preenchido. */
    function dfv(?string $value): void {
        if ($value === null || $value === '') $value = 'Não preenchido';
        $empty = ($value === 'Não preenchido');
        echo '<div class="detalhe-field-value' . ($empty ? ' is-empty' : '') . '">' . $value . '</div>';
    }
}

if (!function_exists('cv_cpf_cnpj')) {
    /** Exibe CPF/CNPJ com máscara (11 dígitos → CPF, 14 → CNPJ). */
    function cv_cpf_cnpj(mixed $v): string {
        $digitos = preg_replace('/\D/', '', (string)($v ?? ''));
        if ($digitos === '') return 'Não preenchido';
        if (strlen($digitos) === 11) {
            return preg_replace('/(\d{3})(\d{3})(\d{3})(\d{2})/', '$1.$2.$3-$4', $digitos);
        }
        if (strlen($digitos) === 14) {
            return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $digitos);
        }
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('cv_cep')) {
    /** Exibe CEP com máscara 00000-000. */
    function cv_cep(mixed $v): string {
        $digitos = preg_replace('/\D/', '', (string)($v ?? ''));
        if ($digitos === '') return 'Não preenchido';
        if (strlen($digitos) === 8) {
            return preg_replace('/(\d{5})(\d{3})/', '$1-$2', $digitos);
        }
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('cv_telefone')) {
    /** Exibe telefone com máscara (99) 99999-9999 ou (99) 9999-9999. */
    function cv_telefone(mixed $v): string {
        $digitos = preg_replace('/\D/', '', (string)($v ?? ''));
        if ($digitos === '') return 'Não preenchido';
        if (strlen($digitos) === 11) {
            return preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $digitos);
        }
        if (strlen($digitos) === 10) {
            return preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $digitos);
        }
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('cv_sim_nao')) {
    function cv_sim_nao(mixed $v): string {
        if ($v === null || $v === '') return 'Não preenchido';
        if (is_numeric($v)) return ((int)$v === 1) ? 'Sim' : 'Não';
        $s = strtoupper(trim((string)$v));
        if (in_array($s, ['SIM', 'S', 'YES', '1'], true)) return 'Sim';
        if (in_array($s, ['NAO', 'NÃO', 'N', 'NO', '0'], true)) return 'Não';
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}
