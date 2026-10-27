/**
 * Módulo — Formulário de cotação Título de Capitalização.
 * Controla: stepper, máscaras, busca de CEP, validação CPF/CNPJ,
 * toggle PF/PJ, pessoa politicamente exposta e envio via fetch.
 */
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('form-cotacao-titulo');
    if (!form) return;

    // ─── Estado ──────────────────────────────────────────────────────────
    let currentStep = 1;
    const TOTAL = 3;

    const stepEls    = document.querySelectorAll('[data-step-indicator]');
    const connectors = document.querySelectorAll('.stepper-connector');
    const panels     = form.querySelectorAll('.stepper-panel');

    // ─── Navegação do stepper ─────────────────────────────────────────────
    const btnSalvarRascunho = document.getElementById('btn-salvar-rascunho');

    function goToStep(n) {
        stepEls.forEach((s, i) => {
            s.classList.toggle('is-active', i + 1 === n);
            s.classList.toggle('is-done',   i + 1 < n);
        });
        connectors.forEach((c, i) => {
            c.classList.toggle('is-done', i + 1 < n);
        });
        panels.forEach((p, i) => {
            p.classList.toggle('is-active', i + 1 === n);
        });
        currentStep = n;

        if (btnSalvarRascunho) {
            const navRight = form.querySelector(`.stepper-panel[data-step="${n}"] .stepper-nav-right`);
            if (navRight) navRight.insertBefore(btnSalvarRascunho, navRight.firstChild);
        }

        document.querySelector('.panel')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    form.querySelectorAll('[data-next]').forEach(btn => {
        btn.addEventListener('click', () => {
            if (currentStep === 1 && !validateStep1()) return;
            if (currentStep < TOTAL) goToStep(currentStep + 1);
        });
    });

    form.querySelectorAll('[data-prev]').forEach(btn => {
        btn.addEventListener('click', () => {
            if (currentStep > 1) goToStep(currentStep - 1);
        });
    });

    // ─── Helpers de erro de campo ─────────────────────────────────────────

    function clearFieldError(field) {
        const err = field?.querySelector('.field-error');
        if (err) { err.textContent = ''; err.hidden = true; }
    }

    function setFieldError(field, msg) {
        const err = field?.querySelector('.field-error');
        if (err) { err.textContent = msg; err.hidden = false; }
    }

    form.querySelectorAll('.field-input').forEach(input => {
        input.addEventListener('input', () => {
            input.classList.remove('has-error');
            clearFieldError(input.closest('.field'));
        });
    });

    // ─── Validação do Step 1 ─────────────────────────────────────────────

    function validateStep1() {
        let valid = true;

        // custom_select: prazo_contrato
        const prazoHidden  = form.querySelector('[name="prazo_contrato"]');
        const prazoCs      = prazoHidden?.closest('[data-custom-select]');
        const prazoTrigger = prazoCs?.querySelector('.custom-select-trigger');
        const prazoField   = prazoHidden?.closest('.field');
        const prazoEmpty   = !prazoHidden?.value?.trim();
        if (prazoTrigger) prazoTrigger.classList.toggle('has-error', prazoEmpty);
        if (prazoEmpty) { setFieldError(prazoField, 'Campo obrigatório.'); valid = false; }
        else             { clearFieldError(prazoField); }

        // Inputs de texto obrigatórios
        const required = [
            { name: 'valor_aluguel',    label: 'Valor do aluguel' },
            { name: 'valor_titulo',     label: 'Valor do título' },
            { name: 'imovel_logradouro',label: 'Logradouro' },
            { name: 'imovel_numero',    label: 'Número' },
            { name: 'imovel_bairro',    label: 'Bairro' },
            { name: 'imovel_cidade',    label: 'Cidade' },
            { name: 'imovel_uf',        label: 'UF' },
            { name: 'imovel_cep',       label: 'CEP' },
        ];

        required.forEach(({ name }) => {
            const input = form.querySelector(`[name="${name}"]`);
            const field = input?.closest('.field');
            const empty = !input?.value?.trim();
            if (input) input.classList.toggle('has-error', empty);
            if (empty) { setFieldError(field, 'Campo obrigatório.'); valid = false; }
            else        { clearFieldError(field); }
        });

        // Radio finalidade
        const finalidade = form.querySelector('[name="finalidade"]:checked');
        const radios     = form.querySelectorAll('[name="finalidade"]');
        const fField     = radios[0]?.closest('.field');
        if (!finalidade) {
            setFieldError(fField, 'Selecione uma opção.');
            valid = false;
        } else {
            clearFieldError(fField);
        }

        // Radio forma de pagamento
        const formaPagamento  = form.querySelector('[name="forma_pagamento"]:checked');
        const radiosPagamento = form.querySelectorAll('[name="forma_pagamento"]');
        const fpField         = radiosPagamento[0]?.closest('.field');
        if (!formaPagamento) {
            setFieldError(fpField, 'Selecione uma opção.');
            valid = false;
        } else {
            clearFieldError(fpField);
        }

        if (!valid) {
            const panel = form.querySelector('.stepper-panel[data-step="1"]');
            panel?.querySelector('.has-error, .field-error:not([hidden])')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        return valid;
    }

    // ─── Máscaras de input ────────────────────────────────────────────────

    function applyMask(input, fn) {
        input.addEventListener('input', function () {
            const start  = this.selectionStart;
            const before = this.value.length;
            this.value   = fn(this.value);
            const diff   = this.value.length - before;
            this.setSelectionRange(start + diff, start + diff);
        });
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

    function maskDate(v) {
        const n = v.replace(/\D/g, '').slice(0, 8);
        return n.replace(/(\d{2})(\d)/, '$1/$2')
                .replace(/(\d{2})(\d)/, '$1/$2');
    }

    function maskCep(v) {
        const n = v.replace(/\D/g, '').slice(0, 8);
        return n.replace(/(\d{5})(\d{1,3})$/, '$1-$2');
    }

    function maskPhone(v) {
        const n = v.replace(/\D/g, '').slice(0, 11);
        if (n.length <= 10) {
            return n.replace(/(\d{2})(\d)/, '($1) $2')
                    .replace(/(\d{4})(\d{1,4})$/, '$1-$2');
        }
        return n.replace(/(\d{2})(\d)/, '($1) $2')
                .replace(/(\d{5})(\d{1,4})$/, '$1-$2');
    }

    function maskCurrency(v) {
        const n = v.replace(/\D/g, '');
        if (!n) return '';
        const num = (parseInt(n, 10) / 100).toFixed(2);
        return 'R$ ' + num.replace('.', ',').replace(/(\d)(?=(\d{3})+(?!\d))/g, '$1.');
    }

    form.querySelectorAll('[data-mask="cpfcnpj"]').forEach(el  => applyMask(el, maskCpfCnpj));
    form.querySelectorAll('[data-mask="date"]').forEach(el     => applyMask(el, maskDate));
    form.querySelectorAll('[data-mask="cep"]').forEach(el      => applyMask(el, maskCep));
    form.querySelectorAll('[data-mask="phone"]').forEach(el    => applyMask(el, maskPhone));
    form.querySelectorAll('[data-mask="currency"]').forEach(el => applyMask(el, maskCurrency));

    // ─── Validação CPF / CNPJ ─────────────────────────────────────────────

    function validarCpf(cpf) {
        const n = cpf.replace(/\D/g, '');
        if (n.length !== 11 || /^(\d)\1{10}$/.test(n)) return false;
        let s = 0;
        for (let i = 0; i < 9; i++) s += parseInt(n[i]) * (10 - i);
        let r = (s * 10) % 11; if (r === 10 || r === 11) r = 0;
        if (r !== parseInt(n[9])) return false;
        s = 0;
        for (let i = 0; i < 10; i++) s += parseInt(n[i]) * (11 - i);
        r = (s * 10) % 11; if (r === 10 || r === 11) r = 0;
        return r === parseInt(n[10]);
    }

    function validarCnpj(cnpj) {
        const n = cnpj.replace(/\D/g, '');
        if (n.length !== 14 || /^(\d)\1{13}$/.test(n)) return false;
        const calc = (str, weights) => {
            const sum = str.split('').reduce((acc, d, i) => acc + parseInt(d) * weights[i], 0);
            const rem = sum % 11;
            return rem < 2 ? 0 : 11 - rem;
        };
        const w1 = [5,4,3,2,9,8,7,6,5,4,3,2];
        const w2 = [6,5,4,3,2,9,8,7,6,5,4,3,2];
        return calc(n.slice(0,12), w1) === parseInt(n[12]) && calc(n.slice(0,13), w2) === parseInt(n[13]);
    }

    const SVG_OK  = '<svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256"><path d="M173.66,98.34a8,8,0,0,1,0,11.32l-56,56a8,8,0,0,1-11.32,0l-24-24a8,8,0,0,1,11.32-11.32L112,148.69l50.34-50.35A8,8,0,0,1,173.66,98.34ZM232,128A104,104,0,1,1,128,24,104.11,104.11,0,0,1,232,128Zm-16,0a88,88,0,1,0-88,88A88.1,88.1,0,0,0,216,128Z"/></svg>';
    const SVG_ERR = '<svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256"><path d="M165.66,101.66,139.31,128l26.35,26.34a8,8,0,0,1-11.32,11.32L128,139.31l-26.34,26.35a8,8,0,0,1-11.32-11.32L116.69,128,90.34,101.66a8,8,0,0,1,11.32-11.32L128,116.69l26.34-26.35a8,8,0,0,1,11.32,11.32ZM232,128A104,104,0,1,1,128,24,104.11,104.11,0,0,1,232,128Zm-16,0a88,88,0,1,0-88,88A88.1,88.1,0,0,0,216,128Z"/></svg>';

    function updateCpfStatus(input) {
        const statusId = input.dataset.cpfStatus;
        if (!statusId) return;
        const status = document.getElementById(statusId);
        if (!status) return;

        const digits = input.value.replace(/\D/g, '');

        if (digits.length === 0) {
            status.hidden = true;
            status.className = 'field-input-status';
            return;
        }

        if (digits.length === 11) {
            const ok = validarCpf(digits);
            status.hidden = false;
            status.className = `field-input-status ${ok ? 'is-valid' : 'is-error'}`;
            status.innerHTML = ok ? SVG_OK : SVG_ERR;
        } else if (digits.length === 14) {
            const ok = validarCnpj(digits);
            status.hidden = false;
            status.className = `field-input-status ${ok ? 'is-valid' : 'is-error'}`;
            status.innerHTML = ok ? SVG_OK : SVG_ERR;
        } else {
            status.hidden = true;
            status.className = 'field-input-status';
        }
    }

    form.querySelectorAll('[data-mask="cpfcnpj"]').forEach(input => {
        input.addEventListener('input', () => updateCpfStatus(input));
        input.addEventListener('blur',  () => updateCpfStatus(input));
    });

    // ─── Busca de CEP (ViaCEP) ────────────────────────────────────────────

    async function lookupCep(cepInput, fieldMap) {
        const cep = cepInput.value.replace(/\D/g, '');
        if (cep.length !== 8) return;
        try {
            const r    = await fetch(`https://viacep.com.br/ws/${cep}/json/`);
            const data = await r.json();
            if (data.erro) return;
            Object.entries(fieldMap).forEach(([name, viaCepKey]) => {
                const el = form.querySelector(`[name="${name}"]`);
                if (el && data[viaCepKey]) el.value = data[viaCepKey];
            });
        } catch (_) {}
    }

    // CEP do imóvel
    const cepImovel = form.querySelector('[name="imovel_cep"]');
    cepImovel?.addEventListener('blur', () => lookupCep(cepImovel, {
        imovel_logradouro: 'logradouro',
        imovel_bairro:     'bairro',
        imovel_cidade:     'localidade',
        imovel_uf:         'uf',
    }));

    // CEP do inquilino
    const cepInquilino = form.querySelector('[name="inq_cep"]');
    cepInquilino?.addEventListener('blur', () => lookupCep(cepInquilino, {
        inq_logradouro: 'logradouro',
        inq_bairro:     'bairro',
        inq_cidade:     'localidade',
        inq_uf:         'uf',
    }));

    // ─── Toggle PF / PJ ───────────────────────────────────────────────────

    const tabBtns     = form.querySelectorAll('.tab-btn');
    const pfSection   = form.querySelector('.inq-pf-section');
    const pjSection   = form.querySelector('.inq-pj-section');
    const tipoHidden  = form.querySelector('#inquilino_tipo');

    tabBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            const tab = btn.dataset.tab;
            tabBtns.forEach(b => b.classList.toggle('is-active', b.dataset.tab === tab));
            if (pfSection) pfSection.hidden = tab !== 'pf';
            if (pjSection) pjSection.hidden = tab !== 'pj';
            if (tipoHidden) tipoHidden.value = tab;
        });
    });

    // ─── Pessoa politicamente exposta ─────────────────────────────────────

    const polExpostaCb = form.querySelector('[name="pol_exposta"][type="checkbox"]');
    polExpostaCb?.addEventListener('change', () => {
        const funcaoField = form.querySelector('.pol-exposta-funcao');
        if (funcaoField) funcaoField.hidden = !polExpostaCb.checked;
    });

    // ─── Envio do formulário ──────────────────────────────────────────────

    document.getElementById('btn-submit-titulo')?.addEventListener('click', async () => {
        if (!validateStep1()) {
            goToStep(1);
            return;
        }

        const btn = document.getElementById('btn-submit-titulo');
        btn.classList.add('is-loading');
        btn.disabled = true;

        try {
            const fd = new FormData(form);
            if (!form.dataset.submitToken) form.dataset.submitToken = (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : (Date.now() + '-' + Math.random().toString(36).slice(2));
            fd.set('submit_token', form.dataset.submitToken);
            const embedToken = document.body.dataset.embedToken;
            const urlSubmit  = embedToken ? `/api/embed/${embedToken}/cotacao/titulo` : '/api/cotacao/titulo';
            const resp = await fetch(urlSubmit, { method: 'POST', body: fd });
            const data = await resp.json();

            if (!resp.ok || !data.success) {
                mpsAlert.error(data.message || 'Erro ao enviar cotação. Tente novamente.');
                return;
            }

            await mpsAlert.success(
                `Pré-análise enviada! Protocolo: <strong>${data.protocolo}</strong>` +
                    (data.aviso_horario ? `<br><br>${data.aviso_horario}` : ''),
                { title: 'Enviado!', autoCloseMs: data.aviso_horario ? 8000 : 4000, html: true }
            );

            if (embedToken) {
                window.location.reload();
            } else {
                window.location.href = appUrl('/cotacoes');
            }
        } catch (_) {
            mpsAlert.error('Erro de conexão. Verifique sua internet e tente novamente.');
        } finally {
            btn.classList.remove('is-loading');
            btn.disabled = false;
        }
    });
});
