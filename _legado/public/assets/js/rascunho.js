/**
 * Rascunho de cotação — salvar, carregar e preencher formulário.
 * Compartilhado pelos 4 produtos: fiança, auto, título, condominial.
 */
window.RascunhoCotacao = (function () {
    'use strict';

    function normalizar(str) {
        return String(str)
            .normalize('NFD').replace(/[̀-ͯ]/g, '')
            .trim().toLowerCase();
    }

    function setCustomSelect(form, name, value) {
        var hidden = form.querySelector('[data-custom-select] input[type="hidden"][name="' + name + '"]');
        if (!hidden) return false;
        var wrapper = hidden.closest('[data-custom-select]');
        if (!wrapper) return false;
        var options = Array.from(wrapper.querySelectorAll('.custom-select-option'));
        var opt = options.find(function (o) { return o.dataset.value === String(value); })
            || options.find(function (o) { return normalizar(o.dataset.value) === normalizar(value); });
        if (opt) { opt.click(); return true; }
        return false;
    }

    /** Preenche o formulário com os dados de um rascunho carregado. */
    function preencher(form, dados) {
        if (!dados) return;
        Object.keys(dados).forEach(function (key) {
            var val = dados[key];
            if (val === null || val === undefined || val === '') return;
            val = String(val);

            // Campo de seleção customizado (input hidden dentro de [data-custom-select])
            if (setCustomSelect(form, key, val)) return;

            var els = form.querySelectorAll('[name="' + key + '"]');
            if (!els.length) return;

            els.forEach(function (el) {
                if (el.type === 'checkbox' || el.type === 'radio') {
                    el.checked = (String(el.value) === val);
                } else {
                    el.value = val;
                }
            });
        });

        // Notifica listeners (máscaras, validações) de que os campos mudaram
        form.querySelectorAll('input, textarea').forEach(function (el) {
            el.dispatchEvent(new Event('input', { bubbles: true }));
        });
    }

    /** Liga o botão "Salvar Rascunho" — coleta o form inteiro e envia ao servidor. */
    function ligarBotaoSalvar(btn, form, tipo, csrfToken) {
        if (!btn || !form) return;

        btn.addEventListener('click', async function () {
            var hiddenId = form.querySelector('input[name="rascunho"]');
            var nomeAtual = hiddenId ? (form.dataset.rascunhoNome || '') : '';
            var nome = await mpsAlert.prompt({
                title: 'Salvar rascunho',
                message: 'Nome para identificar este rascunho:',
                placeholder: 'Ex: João da Silva - Residencial',
                defaultValue: nomeAtual,
                confirmText: 'Salvar',
            });
            if (nome === null) return; // cancelado

            var fd = new FormData(form);
            fd.set('tipo', tipo);
            fd.set('nome_rascunho', nome);
            if (hiddenId && hiddenId.value) fd.set('rascunho', hiddenId.value);

            // Checkboxes desmarcados não são enviados pelo browser — força "0"
            // para que uma atualização de rascunho limpe corretamente o campo.
            form.querySelectorAll('input[type="checkbox"]').forEach(function (cb) {
                if (!cb.checked && cb.name && !fd.has(cb.name)) {
                    fd.set(cb.name, '0');
                }
            });

            btn.disabled = true;
            var textoOriginal = btn.innerHTML;
            btn.textContent = 'Salvando...';

            fetch('/api/cotacao/rascunho/salvar', {
                method: 'POST',
                headers: { 'X-CSRF-Token': csrfToken },
                body: fd
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.ok) {
                    mpsAlert.success('Rascunho salvo com sucesso.', { autoCloseMs: 1200 });
                    setTimeout(function () { window.location.href = appUrl('/cotacao/rascunhos'); }, 900);
                } else {
                    btn.disabled = false;
                    btn.innerHTML = textoOriginal;
                    mpsAlert.error(data.message || 'Erro ao salvar rascunho.');
                }
            })
            .catch(function () {
                btn.disabled = false;
                btn.innerHTML = textoOriginal;
                mpsAlert.error('Erro na requisição. Tente novamente.');
            });
        });
    }

    /** Espera as opções de um custom-select FIPE serem carregadas via AJAX. */
    function aguardarOpcoes(form, name, timeoutMs) {
        return new Promise(function (resolve) {
            var tentativas = 0;
            var max = Math.ceil((timeoutMs || 4000) / 100);
            (function check() {
                var hidden = form.querySelector('[data-custom-select] input[type="hidden"][name="' + name + '"]');
                var wrapper = hidden ? hidden.closest('[data-custom-select]') : null;
                var opts = wrapper ? wrapper.querySelectorAll('.custom-select-option:not(.is-placeholder)') : [];
                if (opts.length > 0 || tentativas >= max) { resolve(opts.length > 0); return; }
                tentativas++;
                setTimeout(check, 100);
            })();
        });
    }

    /**
     * Restaura a cascata FIPE (tipo_veiculo → marca_id → modelo_id → ano_id) do Auto.
     * Cada seleção dispara um carregamento AJAX da próxima lista — por isso o
     * preenchimento precisa esperar as opções existirem antes de clicar na próxima.
     */
    async function preencherFipeCascata(form, dados) {
        if (!dados || !dados.tipo_veiculo) return;

        setCustomSelect(form, 'tipo_veiculo', dados.tipo_veiculo);
        if (!dados.marca_id) return;

        if (!(await aguardarOpcoes(form, 'marca_id'))) return;
        setCustomSelect(form, 'marca_id', dados.marca_id);
        if (!dados.modelo_id) return;

        if (!(await aguardarOpcoes(form, 'modelo_id'))) return;
        setCustomSelect(form, 'modelo_id', dados.modelo_id);
        if (!dados.ano_id) return;

        if (!(await aguardarOpcoes(form, 'ano_id'))) return;
        setCustomSelect(form, 'ano_id', dados.ano_id);
    }

    return {
        preencher: preencher,
        ligarBotaoSalvar: ligarBotaoSalvar,
        preencherFipeCascata: preencherFipeCascata,
    };
})();
