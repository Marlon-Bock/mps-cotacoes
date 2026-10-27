/**
 * Módulo — Formulário de cotação Seguro Condomínio.
 * Controla: stepper, máscaras, busca de CEP,
 * validação CNPJ, funcionários, seguro atual e envio via fetch.
 */
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('form-cotacao-condominial');
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

        // Tipo do condomínio (radio flags)
        const tipoChecked = form.querySelector('[name="tipo_condominio"]:checked');
        const tipoError   = document.getElementById('tipo-condominio-error');
        if (!tipoChecked) {
            if (tipoError) { tipoError.textContent = 'Selecione o tipo de condomínio.'; tipoError.hidden = false; }
            valid = false;
        } else {
            if (tipoError) { tipoError.textContent = ''; tipoError.hidden = true; }
        }

        // Nome do condomínio
        const nomeInput = form.querySelector('[name="imovel_nome"]');
        const nomeField = nomeInput?.closest('.field');
        if (!nomeInput?.value?.trim()) {
            nomeInput?.classList.add('has-error');
            setFieldError(nomeField, 'Campo obrigatório.');
            valid = false;
        } else {
            nomeInput?.classList.remove('has-error');
            clearFieldError(nomeField);
        }

        // CEP
        const cepInput = form.querySelector('[name="imovel_cep"]');
        const cepField = cepInput?.closest('.field');
        if (!cepInput?.value?.trim()) {
            cepInput?.classList.add('has-error');
            setFieldError(cepField, 'Campo obrigatório.');
            valid = false;
        } else {
            cepInput?.classList.remove('has-error');
            clearFieldError(cepField);
        }

        // Endereço/Logradouro
        const enderecoInput = form.querySelector('[name="imovel_endereco"]');
        const enderecoField = enderecoInput?.closest('.field');
        if (!enderecoInput?.value?.trim()) {
            enderecoInput?.classList.add('has-error');
            setFieldError(enderecoField, 'Campo obrigatório.');
            valid = false;
        } else {
            enderecoInput?.classList.remove('has-error');
            clearFieldError(enderecoField);
        }

        // Demais campos obrigatórios do endereço
        const requiredInputs = ['imovel_cnpj', 'imovel_uf', 'imovel_numero', 'imovel_bairro', 'imovel_cidade'];
        requiredInputs.forEach(name => {
            const input = form.querySelector(`[name="${name}"]`);
            const field = input?.closest('.field');
            if (!input?.value?.trim()) {
                input?.classList.add('has-error');
                setFieldError(field, 'Campo obrigatório.');
                valid = false;
            } else {
                input?.classList.remove('has-error');
                clearFieldError(field);
            }
        });

        if (!valid) {
            form.querySelector('.has-error, #tipo-condominio-error:not([hidden])')
                ?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        return valid;
    }

    // ─── Validação do Step 3 ───────────────────────────────────────────────

    function validateStep3() {
        let valid = true;
        const panel = form.querySelector('.stepper-panel[data-step="3"]');

        const requiredInputs = ['sindico_nome', 'sindico_telefone', 'sindico_email'];
        requiredInputs.forEach(name => {
            const input = form.querySelector(`[name="${name}"]`);
            const field = input?.closest('.field');
            if (!input?.value?.trim()) {
                input?.classList.add('has-error');
                setFieldError(field, 'Campo obrigatório.');
                valid = false;
            } else {
                input?.classList.remove('has-error');
                clearFieldError(field);
            }
        });

        if (!valid) {
            panel?.querySelector('.has-error')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
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
        // Remove tudo que não é dígito
        const digits = v.replace(/\D/g, '');
        if (!digits) return '';
        // Trata como centavos
        const cents = parseInt(digits, 10);
        const reais = cents / 100;
        return reais.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    form.querySelectorAll('[data-mask="cpfcnpj"]').forEach(el  => applyMask(el, maskCpfCnpj));
    form.querySelectorAll('[data-mask="cep"]').forEach(el      => applyMask(el, maskCep));
    form.querySelectorAll('[data-mask="phone"]').forEach(el    => applyMask(el, maskPhone));
    form.querySelectorAll('[data-mask="currency"]').forEach(el => applyMask(el, maskCurrency));

    // ─── Validação CNPJ ───────────────────────────────────────────────────

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

    function updateCnpjStatus(input) {
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

        if (digits.length === 14) {
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
        input.addEventListener('input', () => updateCnpjStatus(input));
        input.addEventListener('blur',  () => updateCnpjStatus(input));
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

    const cepImovel = form.querySelector('[name="imovel_cep"]');
    cepImovel?.addEventListener('blur', () => lookupCep(cepImovel, {
        imovel_endereco: 'logradouro',
        imovel_bairro:   'bairro',
        imovel_cidade:   'localidade',
        imovel_uf:       'uf',
    }));

    // ─── Funcionários Registrados → input de vidas ────────────────────────

    const qtdFuncionariosInput = form.querySelector('[name="qtd_funcionarios"]');
    const temFuncionariosCb = form.querySelector('[name="tem_funcionarios"][type="checkbox"]');

    temFuncionariosCb?.addEventListener('change', () => {
        const sim = temFuncionariosCb.checked;
        if (qtdFuncionariosInput) {
            qtdFuncionariosInput.disabled = !sim;
            if (!sim) qtdFuncionariosInput.value = '';
        }
    });

    // ─── Seguro Atual → revelar seguradora + classe bônus ─────────────────

    const seguroAtualDetails = document.getElementById('seguro-atual-details');
    const temSeguroAtualCb = form.querySelector('[name="tem_seguro_atual"][type="checkbox"]');

    temSeguroAtualCb?.addEventListener('change', () => {
        const sim = temSeguroAtualCb.checked;
        if (seguroAtualDetails) {
            seguroAtualDetails.classList.toggle('is-visible', sim);
        }
    });

    // ─── Envio do formulário ──────────────────────────────────────────────

    document.getElementById('btn-submit-condominial')?.addEventListener('click', async () => {
        if (!validateStep1()) {
            goToStep(1);
            return;
        }
        if (!validateStep3()) {
            return;
        }

        const btn = document.getElementById('btn-submit-condominial');
        btn.classList.add('is-loading');
        btn.disabled = true;

        try {
            const fd = new FormData(form);
            if (!form.dataset.submitToken) form.dataset.submitToken = (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : (Date.now() + '-' + Math.random().toString(36).slice(2));
            fd.set('submit_token', form.dataset.submitToken);
            const embedToken = document.body.dataset.embedToken;
            const urlSubmit  = embedToken ? `/api/embed/${embedToken}/cotacao/condominial` : '/api/cotacao/condominial';
            const resp = await fetch(urlSubmit, { method: 'POST', body: fd });
            const data = await resp.json();

            if (!resp.ok || !data.success) {
                mpsAlert.error(data.message || 'Erro ao enviar cotação. Tente novamente.');
                return;
            }

            await mpsAlert.success(
                `Cotação enviada! Protocolo: <strong>${data.protocolo}</strong>` +
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
