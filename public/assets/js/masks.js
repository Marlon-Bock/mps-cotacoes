/**
 * Máscaras de input genéricas — aplica automaticamente em qualquer campo
 * com data-mask="phone|cep|cpfcnpj" presente na página, sem precisar
 * reescrever a mesma lógica em cada form.
 */
(function () {
    'use strict';

    function applyMask(input, fn) {
        input.addEventListener('input', function () {
            const start  = this.selectionStart;
            const before = this.value.length;
            this.value   = fn(this.value);
            const diff   = this.value.length - before;
            this.setSelectionRange(start + diff, start + diff);
        });
    }

    // Telefone: 10 dígitos (fixo, "(00) 0000-0000") ou 11 dígitos
    // (celular, "(00) 00000-0000") — a posição do hífen muda conforme o
    // tamanho do número já digitado, não é sempre a mesma.
    function maskPhone(v) {
        const n = v.replace(/\D/g, '').slice(0, 11);
        if (n.length <= 10) {
            return n.replace(/(\d{2})(\d)/, '($1) $2')
                    .replace(/(\d{4})(\d{1,4})$/, '$1-$2');
        }
        return n.replace(/(\d{2})(\d)/, '($1) $2')
                .replace(/(\d{5})(\d{1,4})$/, '$1-$2');
    }

    function maskCep(v) {
        const n = v.replace(/\D/g, '').slice(0, 8);
        return n.replace(/(\d{5})(\d{1,3})$/, '$1-$2');
    }

    function maskCpfCnpj(v) {
        const n = v.replace(/\D/g, '').slice(0, 14);
        if (n.length <= 11) {
            return n.replace(/(\d{3})(\d)/, '$1.$2')
                    .replace(/(\d{3})(\d)/, '$1.$2')
                    .replace(/(\d{3})(\d{1,2})$/, '$1-$2');
        }
        return n.replace(/(\d{2})(\d)/, '$1.$2')
                .replace(/(\d{3})(\d)/, '$1.$2')
                .replace(/(\d{3})(\d{1,4})/, '$1/$2')
                .replace(/(\d{4})(\d{1,2})$/, '$1-$2');
    }

    document.querySelectorAll('[data-mask="phone"]').forEach((el) => applyMask(el, maskPhone));
    document.querySelectorAll('[data-mask="cep"]').forEach((el) => applyMask(el, maskCep));
    document.querySelectorAll('[data-mask="cpfcnpj"]').forEach((el) => applyMask(el, maskCpfCnpj));
})();
