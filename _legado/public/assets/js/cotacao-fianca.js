/**
 * Módulo 3 — Formulário de cotação Seguro Fiança.
 * Controla: stepper, máscaras, busca de CEP, validação CPF/CNPJ,
 * pretendentes adicionais, checkboxes "não informar" e envio via fetch.
 */
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('form-fianca');
    if (!form) return;

    // ─── Estado ──────────────────────────────────────────────────────────
    let currentStep = 1;
    const TOTAL = 2;

    const stepEls    = document.querySelectorAll('.stepper-step');
    const connectors = document.querySelectorAll('.stepper-connector');
    const panels     = form.querySelectorAll('.stepper-panel');

    // ─── Verifica valores "não informar" no Step 1 ───────────────────────

    const VALUE_FIELDS = {
        valor_agua:       'Água',
        valor_condominio: 'Condomínio',
        valor_gas:        'Gás',
        valor_iptu:       'IPTU (mensal)',
        valor_energia:    'Luz',
    };

    function checkStep1Skipped() {
        const skipped = [];
        for (const [name, label] of Object.entries(VALUE_FIELDS)) {
            const hiddenSkip = form.querySelector(`[name="${name}_skip"]`);
            const input      = form.querySelector(`[name="${name}"]`);
            const isSkipped  = hiddenSkip && hiddenSkip.value === '1';
            const isEmpty    = input && input.value.replace(/\D/g, '') === '';
            if (isSkipped || isEmpty) skipped.push(label);
        }
        return skipped;
    }

    // ─── Navegação do stepper ─────────────────────────────────────────────
    const btnSalvarRascunho = document.getElementById('btn-salvar-rascunho');

    function goToStep(n) {
        stepEls.forEach((s, i) => {
            s.classList.toggle('is-active', i + 1 === n);
            s.classList.toggle('is-done', i + 1 < n);
        });
        connectors.forEach((c, i) => {
            c.classList.toggle('is-done', i + 1 < n);
        });
        panels.forEach((p, i) => {
            p.classList.toggle('is-active', i + 1 === n);
        });
        currentStep = n;

        // Mantém o botão "Salvar Rascunho" ao lado do botão principal do step atual
        if (btnSalvarRascunho) {
            const navRight = form.querySelector(`.stepper-panel[data-step="${n}"] .stepper-nav-right`);
            if (navRight) navRight.insertBefore(btnSalvarRascunho, navRight.firstChild);
        }

        document.querySelector('.panel')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    form.querySelectorAll('[data-next]').forEach(btn => {
        btn.addEventListener('click', async () => {
            if (currentStep === 1 && !validateStep1()) return;

            // Aviso de valores não informados ao sair do Step 1
            if (currentStep === 1) {
                const skippedLabels = checkStep1Skipped();
                if (skippedLabels.length > 0) {
                    const chipsHtml = skippedLabels.map(l => `<span class="mps-skip-chip">${l}</span>`).join('');
                    const ok = await mpsAlert.confirm({
                        title: 'Valores não informados',
                        message: `<span class="mps-skip-subtitle">Os campos abaixo não serão incluídos no cálculo da cotação.</span><div class="mps-skip-chips">${chipsHtml}</div>`,
                        confirmText: 'Continuar assim',
                        cancelText: 'Revisar',
                        danger: false,
                        icon: 'warning',
                    });
                    if (!ok) return;
                }
            }

            if (currentStep < TOTAL) goToStep(currentStep + 1);
        });
    });

    form.querySelectorAll('[data-prev]').forEach(btn => {
        btn.addEventListener('click', () => {
            if (currentStep > 1) goToStep(currentStep - 1);
        });
    });

    // Pré-análise já enviada e liberada: pula direto para o Step 2
    if (window.__PRE_ANALISE_DADOS) goToStep(2);

    // ─── Helpers de erro de campo ─────────────────────────────────────────

    function clearFieldError(field) {
        const err = field?.querySelector('.field-error');
        if (err) { err.textContent = ''; err.hidden = true; }
    }

    function setFieldError(field, msg) {
        const err = field?.querySelector('.field-error');
        if (err) { err.textContent = msg; err.hidden = false; }
    }

    function visualInput(input) {
        const cs = input.closest('[data-custom-select]');
        return cs ? cs.querySelector('.custom-select-trigger') : input;
    }

    // ─── Validação do Step 1 ─────────────────────────────────────────────

    function validateStep1() {
        let valid = true;
        const panel = form.querySelector('.stepper-panel[data-step="1"]');
        panel?.querySelectorAll('[required]').forEach(input => {
            const field = input.closest('.field');
            const empty = !input.value.trim();
            visualInput(input).classList.toggle('has-error', empty);
            if (empty) { setFieldError(field, 'Campo obrigatório.'); valid = false; }
            else        { clearFieldError(field); }
        });

        // Valores de locação: obrigatório preencher OU marcar "Não informar".
        panel?.querySelectorAll('[data-valor-locacao]').forEach(input => {
            const field  = input.closest('.field');
            const hidden = form.querySelector(`[name="${input.name}_skip"]`);
            const skipped = hidden && hidden.value === '1';
            const empty   = !skipped && input.value.replace(/\D/g, '') === '';
            input.classList.toggle('has-error', empty);
            if (empty) { setFieldError(field, 'Preencha ou marque "Não informar".'); valid = false; }
            else       { clearFieldError(field); }
        });

        if (!valid) {
            panel?.querySelector('.has-error')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        return valid;
    }

    form.querySelectorAll('.field-input').forEach(input => {
        input.addEventListener('input', () => {
            input.classList.remove('has-error');
            clearFieldError(input.closest('.field'));
        });
    });

    form.querySelectorAll('[data-custom-select] input[type="hidden"]').forEach(hidden => {
        hidden.addEventListener('change', () => {
            const cs = hidden.closest('[data-custom-select]');
            cs?.querySelector('.custom-select-trigger')?.classList.remove('has-error');
            clearFieldError(hidden.closest('.field'));
        });
    });

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

    function maskPhone(v) {
        const n = v.replace(/\D/g, '').slice(0, 11);
        if (n.length <= 10) {
            return n.replace(/(\d{2})(\d)/, '($1) $2')
                    .replace(/(\d{4})(\d{1,4})$/, '$1-$2');
        }
        return n.replace(/(\d{2})(\d)/, '($1) $2')
                .replace(/(\d{5})(\d{1,4})$/, '$1-$2');
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

    function maskCurrency(v) {
        let n = v.replace(/\D/g, '');
        if (!n) return '';
        n = (n.replace(/^0+/, '') || '0');
        while (n.length < 3) n = '0' + n;
        const cents = n.slice(-2);
        const reais = n.slice(0, -2).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        return `R$ ${reais},${cents}`;
    }

    form.querySelectorAll('[data-mask="cpfcnpj"]').forEach(el  => applyMask(el, maskCpfCnpj));
    form.querySelectorAll('[data-mask="phone"]').forEach(el    => applyMask(el, maskPhone));
    form.querySelectorAll('[data-mask="date"]').forEach(el     => applyMask(el, maskDate));
    form.querySelectorAll('[data-mask="cep"]').forEach(el      => applyMask(el, maskCep));
    form.querySelectorAll('[data-mask="currency"]').forEach(el => applyMask(el, maskCurrency));

    // ─── Validação CPF / CNPJ — genérica para todos os campos ─────────────

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

    const SVG_OK = '<svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256"><path d="M173.66,98.34a8,8,0,0,1,0,11.32l-56,56a8,8,0,0,1-11.32,0l-24-24a8,8,0,0,1,11.32-11.32L112,148.69l50.34-50.35A8,8,0,0,1,173.66,98.34ZM232,128A104,104,0,1,1,128,24,104.11,104.11,0,0,1,232,128Zm-16,0a88,88,0,1,0-88,88A88.1,88.1,0,0,0,216,128Z"/></svg>';
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

    // ─── Busca de CEP (ViaCEP) — genérica ────────────────────────────────

    async function lookupCep(cepInput, fields) {
        const cep = cepInput.value.replace(/\D/g, '');
        if (cep.length !== 8) return;
        try {
            const r    = await fetch(`https://viacep.com.br/ws/${cep}/json/`);
            const data = await r.json();
            if (data.erro) return;
            Object.entries(fields).forEach(([name, viaCepKey]) => {
                const el = form.querySelector(`[name="${name}"]`);
                if (el && data[viaCepKey]) el.value = data[viaCepKey];
            });
        } catch (_) {}
    }

    // CEP do imóvel (Step 1) — auto-preenche endereço
    const cepImovel = form.querySelector('[name="cep"]');
    cepImovel?.addEventListener('blur', () => lookupCep(cepImovel, {
        endereco: 'logradouro',
        bairro:   'bairro',
        cidade:   'localidade',
        uf:       'uf',
    }));

    // ─── Botões "Não informar" inline ────────────────────────────────────

    form.querySelectorAll('[data-skip-toggle]').forEach(btn => {
        const fieldName        = btn.dataset.skipToggle;
        const input            = form.querySelector(`[name="${fieldName}"]`);
        const hidden           = form.querySelector(`[name="${fieldName}_skip"]`);
        if (!input) return;
        const originalPlaceholder = input.placeholder;

        btn.addEventListener('click', () => {
            const active = btn.getAttribute('aria-pressed') === 'true';
            const next   = !active;

            btn.setAttribute('aria-pressed', String(next));
            btn.classList.toggle('is-active', next);
            input.classList.toggle('is-skipped', next);
            input.disabled = next;
            if (hidden) hidden.value = next ? '1' : '0';
            input.value       = '';
            input.placeholder = next ? 'Não informado' : originalPlaceholder;

            if (next) {
                input.classList.remove('has-error');
                clearFieldError(input.closest('.field'));
            }
        });
    });

    // ─── Pretendentes adicionais (quantidade livre) ───────────────────────

    const pretendentesList = document.getElementById('pretendentes-extras-list');
    const tplPretendente    = document.getElementById('tpl-pretendente-extra');
    const btnAddPretendente = document.getElementById('btn-add-pretendente');
    let pretendenteSeq = 0;
    const MAX_PRETENDENTES_EXTRA = 7;

    function renumerarPretendentes() {
        pretendentesList?.querySelectorAll('[data-pretendente-titulo]').forEach((el, i) => {
            el.textContent = `${i + 1}º pretendente adicional`;
        });
        atualizarLimitePretendentes();
    }

    function atualizarLimitePretendentes() {
        if (!btnAddPretendente || !pretendentesList) return;
        const atingiuLimite = pretendentesList.children.length >= MAX_PRETENDENTES_EXTRA;
        btnAddPretendente.disabled = atingiuLimite;
        btnAddPretendente.title = atingiuLimite
            ? `Limite de ${MAX_PRETENDENTES_EXTRA} pretendentes adicionais atingido.`
            : '';
    }

    function adicionarPretendente() {
        if (!tplPretendente || !pretendentesList) return;
        if (pretendentesList.children.length >= MAX_PRETENDENTES_EXTRA) return;
        const idx  = pretendenteSeq++;
        const card = tplPretendente.content.firstElementChild.cloneNode(true);

        card.querySelectorAll('[data-field]').forEach(input => {
            input.name = `pretendentes_extra[${idx}][${input.dataset.field}]`;
        });

        const cpfInput = card.querySelector('[data-field="cpf_cnpj"]');
        const cpfStatus = card.querySelector('[data-cpf-status-el]');
        if (cpfInput && cpfStatus) {
            const statusId = `pretendente-extra-${idx}-cpf-status`;
            cpfStatus.id = statusId;
            cpfInput.dataset.cpfStatus = statusId;
        }

        const hiddenSelect = card.querySelector('[data-custom-select] input[type="hidden"]');
        if (hiddenSelect) hiddenSelect.name = `pretendentes_extra[${idx}][vinculo_empregaticio]`;

        card.querySelectorAll('[data-mask="cpfcnpj"]').forEach(el => applyMask(el, maskCpfCnpj));
        card.querySelectorAll('[data-mask="phone"]').forEach(el => applyMask(el, maskPhone));
        card.querySelectorAll('[data-mask="date"]').forEach(el => applyMask(el, maskDate));
        card.querySelectorAll('[data-mask="currency"]').forEach(el => applyMask(el, maskCurrency));
        if (cpfInput) {
            cpfInput.addEventListener('input', () => updateCpfStatus(cpfInput));
            cpfInput.addEventListener('blur',  () => updateCpfStatus(cpfInput));
        }

        card.querySelector('[data-remove-pretendente]')?.addEventListener('click', () => {
            card.remove();
            renumerarPretendentes();
        });

        pretendentesList.appendChild(card);
        if (window.customSelectInit) window.customSelectInit(card);
        renumerarPretendentes();
    }

    btnAddPretendente?.addEventListener('click', adicionarPretendente);

    // ─── Busca imóvel por código (clientes Sami — integração ERP) ────────────

    const lookupBox       = form.querySelector('.imovel-lookup-compact');
    const btnBuscarImovel = form.querySelector('#btn-buscar-imovel');
    const inputCodigo     = form.querySelector('#codigo_imovel');
    const lookupStatusEl  = form.querySelector('#imovel-lookup-status');

    function setLookupStatus(msg, type) {
        if (!lookupStatusEl) return;
        lookupStatusEl.textContent = msg || '';
        lookupStatusEl.hidden = !msg;
        lookupStatusEl.classList.remove('is-error', 'is-success', 'is-loading');
        if (type) lookupStatusEl.classList.add('is-' + type);
    }

    function preencherImovel(imovel) {
        const setVal = (name, val) => {
            const el = form.querySelector(`[name="${name}"]`);
            if (el && val != null && val !== '') el.value = val;
        };
        // Valores vêm em Reais (float) — converte para centavos e aplica a máscara.
        const setMoeda = (name, valorReais) => {
            const num = parseFloat(valorReais);
            if (!num || isNaN(num)) return;
            setVal(name, maskCurrency(String(Math.round(num * 100))));
        };

        setVal('endereco',    imovel.logradouro);
        setVal('numero',      imovel.numero);
        setVal('complemento', imovel.complemento);
        setVal('bairro',      imovel.bairro);
        setVal('cidade',      imovel.cidade);
        setVal('uf',          (imovel.uf || '').toUpperCase());
        if (imovel.cep) setVal('cep', maskCep(imovel.cep));

        setMoeda('valor_aluguel',    imovel.valor_aluguel);
        setMoeda('valor_condominio', imovel.valor_condominio);
        setMoeda('valor_iptu',       imovel.valor_iptu);

        // Proprietário
        setVal('proprietario_imovel', imovel.proprietario);
        if (imovel.cpf_proprietario) {
            setVal('cpf_cnpj_proprietario', maskCpfCnpj(imovel.cpf_proprietario));
        }
    }

    if (btnBuscarImovel && lookupBox) {
        const endpoint = lookupBox.dataset.endpoint || '/api/cotacao/imovel-buscar';

        async function buscarImovel() {
            const codigo = (inputCodigo?.value || '').trim();
            if (!codigo) {
                setLookupStatus('Informe o código do imóvel.', 'error');
                return;
            }

            btnBuscarImovel.disabled = true;
            setLookupStatus('Buscando imóvel...', 'loading');

            try {
                const r = await fetch(`${endpoint}?codigo=${encodeURIComponent(codigo)}`, {
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                });
                const data = await r.json();

                if (!data || !data.ok) {
                    setLookupStatus((data && data.erro) || 'Imóvel não encontrado.', 'error');
                    return;
                }

                preencherImovel(data.imovel);
                setLookupStatus(`Imóvel ${data.imovel.codigo} carregado. Confira os dados abaixo.`, 'success');
            } catch (_) {
                setLookupStatus('Erro ao buscar o imóvel. Tente novamente.', 'error');
            } finally {
                btnBuscarImovel.disabled = false;
            }
        }

        btnBuscarImovel.addEventListener('click', buscarImovel);
        inputCodigo?.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                buscarImovel();
            }
        });
    }

    // ─── Envio do formulário ──────────────────────────────────────────────

    function cleanCurrency(v) {
        const n = v.replace(/\D/g, '');
        return n ? (parseInt(n, 10) / 100).toFixed(2) : '';
    }


    async function submitForm(partial, btnId) {
        if (!validateStep1()) {
            goToStep(1);
            return;
        }

        const btn = document.getElementById(btnId || (partial ? 'btn-partial' : 'btn-submit'));
        btn.classList.add('is-loading');
        btn.disabled = true;

        try {
            const fd = new FormData(form);
            if (!form.dataset.submitToken) form.dataset.submitToken = (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : (Date.now() + '-' + Math.random().toString(36).slice(2));
            fd.set('submit_token', form.dataset.submitToken);
            fd.set('envio_parcial', partial ? '1' : '0');

            form.querySelectorAll('[data-mask="currency"]').forEach(el => {
                if (!el.name || el.disabled) return;
                fd.set(el.name, cleanCurrency(el.value));
            });

            const embedToken = document.body.dataset.embedToken;
            const urlSubmit  = embedToken ? `/api/embed/${embedToken}/cotacao/fianca` : '/api/cotacao/fianca';
            const resp = await fetch(urlSubmit, { method: 'POST', body: fd });
            const data = await resp.json();

            if (!resp.ok || !data.success) {
                mpsAlert.error(data.message || 'Erro ao enviar cotação. Tente novamente.');
                return;
            }

            // A análise acontece na API das seguradoras, em segundo plano —
            // o detalhe da cotação acompanha a pré-análise.
            if (!partial && !embedToken) {
                mpsAlert.success('Cotação enviada para análise!', { autoCloseMs: 2500 });
                window.location.href = appUrl(`/cotacao/fianca/${data.protocolo}`);
                return;
            }

            await mpsAlert.success(
                (partial
                    ? `Pré-análise enviada! Protocolo: <strong>${data.protocolo}</strong>`
                    : `Cotação enviada! Protocolo: <strong>${data.protocolo}</strong>`) +
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
    }

    document.getElementById('btn-partial')?.addEventListener('click', () => {
        mpsAlert.confirm({
            title: 'Enviar para pré-análise?',
            message: 'Somente os dados do pretendente serão enviados agora. A MPS irá analisar e, se aprovado, você completa o formulário.',
            confirmText: 'Enviar',
            cancelText: 'Cancelar',
        }).then(ok => { if (ok) submitForm(true); });
    });

    document.getElementById('btn-submit')?.addEventListener('click', () => submitForm(false, 'btn-submit'));
});
