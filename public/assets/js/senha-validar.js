/**
 * Validação client-side dos campos de senha nova (todo formulário que cria
 * ou altera senha) — como os forms usam `novalidate`, o navegador não avisa
 * nada sozinho; sem isso o usuário só descobre o erro depois de recarregar
 * a página e reparar no texto pequeno ao lado do campo.
 */
(function () {
    'use strict';

    function rotuloDoCampo(input) {
        const label = input.closest('.field')?.querySelector('.field-label');
        if (!label) return 'Senha';
        return label.childNodes[0]?.textContent?.trim() || 'Senha';
    }

    // O guard de duplo-clique (em admin-imobiliarias-form.js e afins) desabilita
    // o botão assim que o submit dispara — se a gente bloquear o envio aqui por
    // senha inválida, precisa desfazer isso ou o botão fica travado pra sempre.
    function liberarSubmit(form) {
        form.dataset.enviando = '';
        form.querySelectorAll('button[type="submit"]').forEach((btn) => { btn.disabled = false; });
    }

    document.querySelectorAll('form').forEach((form) => {
        const camposSenha = form.querySelectorAll('input[type="password"][minlength]');
        if (!camposSenha.length) return;

        form.addEventListener('submit', (e) => {
            for (const input of camposSenha) {
                const minimo = parseInt(input.getAttribute('minlength'), 10) || 0;
                const valor  = input.value;

                if (input.hasAttribute('required') && valor === '') {
                    e.preventDefault();
                    liberarSubmit(form);
                    input.classList.add('is-error');
                    input.focus();
                    if (typeof mpsAlert !== 'undefined') mpsAlert.error(`Informe a ${rotuloDoCampo(input).toLowerCase()}.`);
                    return;
                }

                if (valor !== '' && valor.length < minimo) {
                    e.preventDefault();
                    liberarSubmit(form);
                    input.classList.add('is-error');
                    input.focus();
                    if (typeof mpsAlert !== 'undefined') mpsAlert.error(`A senha deve ter no mínimo ${minimo} caracteres.`);
                    return;
                }

                if (valor !== '' && (!/[A-Z]/.test(valor) || !/[0-9]/.test(valor))) {
                    e.preventDefault();
                    liberarSubmit(form);
                    input.classList.add('is-error');
                    input.focus();
                    if (typeof mpsAlert !== 'undefined') mpsAlert.error('A senha deve ter pelo menos uma letra maiúscula e um número.');
                    return;
                }

                const confirmar = form.querySelector('#' + input.id + '_confirmar');
                if (confirmar && valor !== '' && valor !== confirmar.value) {
                    e.preventDefault();
                    liberarSubmit(form);
                    confirmar.classList.add('is-error');
                    confirmar.focus();
                    if (typeof mpsAlert !== 'undefined') mpsAlert.error('As senhas não coincidem.');
                    return;
                }
            }
        });

        camposSenha.forEach((input) => {
            const campo   = input.closest('.field');
            const minimo  = parseInt(input.getAttribute('minlength'), 10) || 6;
            const confirmar = form.querySelector('#' + input.id + '_confirmar');

            let itemTamanho    = null;
            let itemMaiuscula  = null;
            let itemNumero     = null;
            let itemIgual      = null;

            if (campo && !campo.querySelector('.senha-requisitos')) {
                const requisitos = document.createElement('ul');
                requisitos.className = 'senha-requisitos';
                requisitos.innerHTML = `
                    <li class="senha-req-item" data-req="tamanho">Ter pelo menos ${minimo} caracteres</li>
                    <li class="senha-req-item" data-req="maiuscula">Incluir 1 letra maiúscula</li>
                    <li class="senha-req-item" data-req="numero">Incluir 1 número</li>
                    ${confirmar ? '<li class="senha-req-item" data-req="igual">Ser a mesma em ambos os campos</li>' : ''}
                `;
                const hint = campo.querySelector('.field-hint');
                (hint || campo.querySelector('.field-input-wrap')).after(requisitos);
            }
            if (campo) {
                itemTamanho   = campo.querySelector('[data-req="tamanho"]');
                itemMaiuscula = campo.querySelector('[data-req="maiuscula"]');
                itemNumero    = campo.querySelector('[data-req="numero"]');
                itemIgual     = campo.querySelector('[data-req="igual"]');
            }

            function atualizarRequisitos() {
                const valor = input.value;
                if (itemTamanho)   itemTamanho.classList.toggle('is-valid', valor.length >= minimo);
                if (itemMaiuscula) itemMaiuscula.classList.toggle('is-valid', /[A-Z]/.test(valor));
                if (itemNumero)    itemNumero.classList.toggle('is-valid', /[0-9]/.test(valor));
            }

            function atualizarConfirmacao() {
                if (!confirmar) return;
                const combina = input.value !== '' && confirmar.value !== '' && input.value === confirmar.value;
                input.classList.toggle('is-match', combina);
                confirmar.classList.toggle('is-match', combina);
                if (itemIgual) itemIgual.classList.toggle('is-valid', combina);
            }

            input.addEventListener('input', () => {
                input.classList.remove('is-error');
                atualizarRequisitos();
                atualizarConfirmacao();
            });
            if (confirmar) {
                confirmar.addEventListener('input', () => {
                    confirmar.classList.remove('is-error');
                    atualizarConfirmacao();
                });
            }

            atualizarRequisitos();
            atualizarConfirmacao();
        });
    });
})();
