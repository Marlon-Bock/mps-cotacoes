/**
 * Módulo — Formulário de cotação Seguro Auto.
 * Controla: stepper, máscaras, FIPE cascade, busca de CEP,
 * validação CPF, garagem/portão, condutor e envio via fetch.
 */
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('form-cotacao-auto');
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
            if (currentStep === 1 && !validateStep1Auto()) return;
            if (currentStep === 2 && !validateStep2Auto()) return;
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

    function visualInput(input) {
        const cs = input.closest('[data-custom-select]');
        return cs ? cs.querySelector('.custom-select-trigger') : input;
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

    // ─── Validação do Step 1 ─────────────────────────────────────────────

    function validateStep1Auto() {
        let valid = true;
        const panel = form.querySelector('.stepper-panel[data-step="1"]');

        // Custom selects obrigatórios
        const requiredSelects = [
            { name: 'tipo_veiculo', label: 'Tipo de veículo' },
            { name: 'marca_id',     label: 'Marca' },
            { name: 'modelo_id',    label: 'Modelo' },
            { name: 'ano_id',       label: 'Ano' },
            { name: 'combustivel',  label: 'Combustível' },
        ];

        requiredSelects.forEach(({ name, label }) => {
            const hidden = form.querySelector(`[name="${name}"]`);
            const field  = hidden?.closest('.field');
            const cs     = hidden?.closest('[data-custom-select]');
            const trigger = cs?.querySelector('.custom-select-trigger');
            const empty  = !hidden?.value?.trim();
            if (trigger) trigger.classList.toggle('has-error', empty);
            if (empty) { setFieldError(field, 'Campo obrigatório.'); valid = false; }
            else        { clearFieldError(field); }
        });

        // Radios obrigatórios (categóricos — sem valor padrão)
        const requiredRadios = [
            { name: 'tipo_seguro', label: 'Tipo de seguro' },
        ];

        requiredRadios.forEach(({ name, label }) => {
            const selected = form.querySelector(`[name="${name}"]:checked`);
            const radios   = form.querySelectorAll(`[name="${name}"]`);
            const field    = radios[0]?.closest('.field');
            if (!selected) {
                setFieldError(field, 'Selecione uma opção.');
                valid = false;
            } else {
                clearFieldError(field);
            }
        });

        // Inputs de texto obrigatórios
        const requiredInputs = [
            { name: 'placa', label: 'Placa' },
        ];

        requiredInputs.forEach(({ name, label }) => {
            const input = form.querySelector(`[name="${name}"]`);
            const field = input?.closest('.field');
            const empty = !input?.value?.trim();
            input?.classList.toggle('has-error', empty);
            if (empty) { setFieldError(field, 'Campo obrigatório.'); valid = false; }
            else        { clearFieldError(field); }
        });

        // km se utilizado_ir_trabalho = Sim
        const irTrabalho = form.querySelector('[name="utilizado_ir_trabalho"][type="checkbox"]');
        if (irTrabalho?.checked) {
            const kmInput = form.querySelector('[name="km_distancia_trabalho"]');
            const field   = kmInput?.closest('.field');
            if (!kmInput?.value?.trim()) {
                kmInput?.classList.add('has-error');
                setFieldError(field, 'Informe a distância.');
                valid = false;
            }
        }

        if (!valid) {
            panel?.querySelector('.has-error')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
        return valid;
    }

    // ─── Validação do Step 2 ─────────────────────────────────────────────

    function validateStep2Auto() {
        let valid = true;
        const panel = form.querySelector('.stepper-panel[data-step="2"]');

        const requiredInputs = [
            { name: 'segurado_nome', label: 'Nome completo' },
            { name: 'segurado_cpf',  label: 'CPF' },
        ];

        requiredInputs.forEach(({ name, label }) => {
            const input = form.querySelector(`[name="${name}"]`);
            const field = input?.closest('.field');
            const empty = !input?.value?.trim();
            input?.classList.toggle('has-error', empty);
            if (empty) { setFieldError(field, 'Campo obrigatório.'); valid = false; }
            else        { clearFieldError(field); }
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

    function maskDate(v) {
        const n = v.replace(/\D/g, '').slice(0, 8);
        return n.replace(/(\d{2})(\d)/, '$1/$2')
                .replace(/(\d{2})(\d)/, '$1/$2');
    }

    function maskCep(v) {
        const n = v.replace(/\D/g, '').slice(0, 8);
        return n.replace(/(\d{5})(\d{1,3})$/, '$1-$2');
    }

    function maskNumeric(v) {
        return v.replace(/\D/g, '');
    }

    function maskPlaca(v) {
        // Remove não-alfanuméricos e limita a 7 chars base (sem traço)
        v = v.replace(/[^a-zA-Z0-9]/g, '').toUpperCase().slice(0, 7);
        if (v.length > 3) {
            const suffix = v.slice(3); // até 4 chars
            v = v.slice(0, 3) + '-' + suffix;
        }
        return v;
    }

    // Atualiza maxlength dinamicamente: padrão ABC-1234 = 8, Mercosul ABC-9A99 = 8 (mesmo tamanho)
    form.querySelectorAll('[data-mask="placa"]').forEach(el => {
        el.setAttribute('maxlength', '8');
    });

    form.querySelectorAll('[data-mask="cpfcnpj"]').forEach(el  => applyMask(el, maskCpfCnpj));
    form.querySelectorAll('[data-mask="date"]').forEach(el     => applyMask(el, maskDate));
    form.querySelectorAll('[data-mask="cep"]').forEach(el      => applyMask(el, maskCep));
    form.querySelectorAll('[data-mask="numeric"]').forEach(el  => applyMask(el, maskNumeric));
    form.querySelectorAll('[data-mask="placa"]').forEach(el    => applyMask(el, maskPlaca));

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

    const cepSegurado = form.querySelector('[name="segurado_cep"]');
    cepSegurado?.addEventListener('blur', () => lookupCep(cepSegurado, {
        segurado_endereco: 'logradouro',
        segurado_bairro:   'bairro',
        segurado_cidade:   'localidade',
        segurado_uf:       'uf',
    }));

    // ─── FIPE cascade ────────────────────────────────────────────────────
    // Trabalha com a estrutura real do custom_select() PHP:
    //   [data-custom-select] > .custom-select-trigger > .custom-select-value
    //   [data-custom-select] > .custom-select-dropdown > .custom-select-option[data-value]
    //   [data-custom-select] > input[type=hidden]

    function fipeWrapper(name) {
        return form.querySelector(`[data-custom-select] input[name="${name}"]`)?.closest('[data-custom-select]') ?? null;
    }

    function fipeSetLoading(name, msg) {
        const w = fipeWrapper(name);
        if (!w) return;
        const dropdown = w.querySelector('.custom-select-dropdown');
        const valueEl  = w.querySelector('.custom-select-value');
        const hidden   = w.querySelector('input[type="hidden"]');
        const trigger  = w.querySelector('.custom-select-trigger');
        if (dropdown) dropdown.innerHTML = `<li class="custom-select-option is-placeholder">${msg}</li>`;
        if (valueEl)  { valueEl.textContent = msg; valueEl.classList.add('is-placeholder'); }
        if (hidden)   hidden.value = '';
        if (trigger)  trigger.disabled = true;
    }

    function fipeSetOptions(name, items, placeholder) {
        const w = fipeWrapper(name);
        if (!w) return;
        const dropdown = w.querySelector('.custom-select-dropdown');
        const valueEl  = w.querySelector('.custom-select-value');
        const hidden   = w.querySelector('input[type="hidden"]');
        const trigger  = w.querySelector('.custom-select-trigger');
        if (!dropdown) return;

        // Reconstruir opções — o event delegation em custom-select.js cuida dos clicks
        dropdown.innerHTML = `<li class="custom-select-option is-placeholder is-selected" data-value="">${placeholder}</li>`;
        items.forEach(item => {
            const li = document.createElement('li');
            li.className = 'custom-select-option';
            li.dataset.value = String(item.id);
            li.textContent = item.nome;
            dropdown.appendChild(li);
        });

        if (valueEl)  { valueEl.textContent = placeholder; valueEl.classList.add('is-placeholder'); }
        if (hidden)   hidden.value = '';
        if (trigger)  trigger.disabled = items.length === 0;
    }

    function fipeReset(name, placeholder) {
        const w = fipeWrapper(name);
        if (!w) return;
        const dropdown = w.querySelector('.custom-select-dropdown');
        const valueEl  = w.querySelector('.custom-select-value');
        const hidden   = w.querySelector('input[type="hidden"]');
        const trigger  = w.querySelector('.custom-select-trigger');
        if (dropdown) dropdown.innerHTML = `<li class="custom-select-option is-placeholder is-selected" data-value="">${placeholder}</li>`;
        if (valueEl)  { valueEl.textContent = placeholder; valueEl.classList.add('is-placeholder'); }
        if (hidden)   hidden.value = '';
        if (trigger)  trigger.disabled = true;
    }

    function fipeGetValue(name) {
        return fipeWrapper(name)?.querySelector('input[type="hidden"]')?.value ?? '';
    }

    function fipeGetLabel(name) {
        return fipeWrapper(name)?.querySelector('.custom-select-value')?.textContent?.trim() ?? '';
    }

    const marcaNome  = document.getElementById('marca_nome');
    const modeloNome = document.getElementById('modelo_nome');
    const anoNome    = document.getElementById('ano_nome');

    // Inicialmente desabilita marca, modelo, ano
    fipeReset('marca_id',  'Selecione o tipo primeiro');
    fipeReset('modelo_id', 'Selecione a marca primeiro');
    fipeReset('ano_id',    'Selecione o modelo primeiro');

    async function loadMarcas(tipo) {
        fipeSetLoading('marca_id', 'Carregando marcas...');
        fipeReset('modelo_id', 'Selecione a marca primeiro');
        fipeReset('ano_id',    'Selecione o modelo primeiro');
        if (marcaNome)  marcaNome.value  = '';
        if (modeloNome) modeloNome.value = '';
        if (anoNome)    anoNome.value    = '';
        try {
            const r = await fetch(`/api/fipe?action=marcas&tipo=${encodeURIComponent(tipo)}`);
            const d = await r.json();
            fipeSetOptions('marca_id', d.ok ? d.items : [], 'Selecione a marca');
        } catch (_) {
            fipeSetOptions('marca_id', [], 'Erro ao carregar');
        }
    }

    async function loadModelos(tipo, marcaId) {
        fipeSetLoading('modelo_id', 'Carregando modelos...');
        fipeReset('ano_id', 'Selecione o modelo primeiro');
        if (modeloNome) modeloNome.value = '';
        if (anoNome)    anoNome.value    = '';
        try {
            const r = await fetch(`/api/fipe?action=modelos&tipo=${encodeURIComponent(tipo)}&marca_id=${encodeURIComponent(marcaId)}`);
            const d = await r.json();
            fipeSetOptions('modelo_id', d.ok ? d.items : [], 'Selecione o modelo');
        } catch (_) {
            fipeSetOptions('modelo_id', [], 'Erro ao carregar');
        }
    }

    async function loadAnos(tipo, modeloId) {
        fipeSetLoading('ano_id', 'Carregando anos...');
        if (anoNome) anoNome.value = '';
        try {
            const r = await fetch(`/api/fipe?action=anos&tipo=${encodeURIComponent(tipo)}&modelo_id=${encodeURIComponent(modeloId)}`);
            const d = await r.json();
            fipeSetOptions('ano_id', d.ok ? d.items : [], 'Selecione o ano');
        } catch (_) {
            fipeSetOptions('ano_id', [], 'Erro ao carregar');
        }
    }

    // Listeners nos hidden inputs (disparados pelo custom-select.js após seleção)
    fipeWrapper('tipo_veiculo')?.querySelector('input[type="hidden"]')?.addEventListener('change', () => {
        const tipo = fipeGetValue('tipo_veiculo');
        if (tipo) loadMarcas(tipo);
    });

    fipeWrapper('marca_id')?.querySelector('input[type="hidden"]')?.addEventListener('change', () => {
        const marcaId = fipeGetValue('marca_id');
        if (!marcaId) return;
        if (marcaNome) marcaNome.value = fipeGetLabel('marca_id');
        loadModelos(fipeGetValue('tipo_veiculo'), marcaId);
    });

    fipeWrapper('modelo_id')?.querySelector('input[type="hidden"]')?.addEventListener('change', () => {
        const modeloId = fipeGetValue('modelo_id');
        if (!modeloId) return;
        if (modeloNome) modeloNome.value = fipeGetLabel('modelo_id');
        loadAnos(fipeGetValue('tipo_veiculo'), modeloId);
    });

    fipeWrapper('ano_id')?.querySelector('input[type="hidden"]')?.addEventListener('change', () => {
        if (anoNome) anoNome.value = fipeGetLabel('ano_id');
    });

    // ─── utilizado_ir_trabalho → km field ────────────────────────────────

    const kmInput = form.querySelector('[name="km_distancia_trabalho"]');
    const irTrabalhoCb = form.querySelector('[name="utilizado_ir_trabalho"][type="checkbox"]');

    irTrabalhoCb?.addEventListener('change', () => {
        const sim = irTrabalhoCb.checked;
        if (kmInput) {
            kmInput.disabled = !sim;
            if (!sim) kmInput.value = '';
        }
    });

    // ─── Garagem → portão eletrônico ─────────────────────────────────────

    [
        ['garagem_residencia', 'portao_residencia'],
        ['garagem_trabalho',   'portao_trabalho'],
        ['garagem_faculdade',  'portao_faculdade'],
    ].forEach(([garagemId, portaoId]) => {
        const garagem = document.getElementById(garagemId);
        const portao  = document.getElementById(portaoId);
        if (!garagem || !portao) return;
        garagem.addEventListener('change', () => {
            portao.disabled = !garagem.checked;
            if (!garagem.checked) portao.checked = false;
        });
    });

    // ─── "Mesmo que o segurado" ───────────────────────────────────────────

    document.getElementById('btn-copiar-segurado')?.addEventListener('click', () => {
        // Nome
        const nomeEl = form.querySelector('[name="condutor_nome"]');
        if (nomeEl) nomeEl.value = form.querySelector('[name="segurado_nome"]')?.value || '';

        // CPF — copia valor e dispara máscara + status
        const cpfSrc = form.querySelector('[name="segurado_cpf"]');
        const cpfDst = form.querySelector('[name="condutor_cpf"]');
        if (cpfSrc && cpfDst) {
            cpfDst.value = cpfSrc.value;
            updateCpfStatus(cpfDst);
        }

        // Data de nascimento
        const nascEl = form.querySelector('[name="condutor_data_nasc"]');
        if (nascEl) nascEl.value = form.querySelector('[name="segurado_data_nasc"]')?.value || '';

        // Estado civil — custom_select: copiar valor hidden e label do trigger
        const ecSrcHidden  = getCustomSelectHidden('segurado_estado_civil');
        const ecDstHidden  = getCustomSelectHidden('condutor_estado_civil');
        const ecSrcTrigger = getCustomSelectTrigger('segurado_estado_civil');
        const ecDstTrigger = getCustomSelectTrigger('condutor_estado_civil');

        if (ecSrcHidden && ecDstHidden) {
            ecDstHidden.value = ecSrcHidden.value;
        }
        if (ecSrcTrigger && ecDstTrigger) {
            ecDstTrigger.textContent = ecSrcTrigger.textContent;
        }
    });

    // ─── condutor_menor_25 → sexo do condutor ────────────────────────────

    const menorCb = form.querySelector('[name="condutor_menor_25"][type="checkbox"]');
    menorCb?.addEventListener('change', () => {
        const sim = menorCb.checked;
        form.querySelectorAll('[name="condutor_menor_25_sexo"]').forEach(r => {
            r.disabled = !sim;
            if (!sim) r.checked = false;
        });
        const sexoField = document.getElementById('field-condutor-sexo');
        if (sexoField) sexoField.classList.toggle('is-faded', !sim);
    });

    // ─── Envio do formulário ──────────────────────────────────────────────

    document.getElementById('btn-submit-auto')?.addEventListener('click', async () => {
        // Re-validar steps 1 e 2 caso o usuário tenha navegado para trás
        if (!validateStep1Auto()) {
            goToStep(1);
            return;
        }
        if (!validateStep2Auto()) {
            goToStep(2);
            return;
        }

        const btn = document.getElementById('btn-submit-auto');
        btn.classList.add('is-loading');
        btn.disabled = true;

        try {
            const fd = new FormData(form);
            if (!form.dataset.submitToken) form.dataset.submitToken = (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : (Date.now() + '-' + Math.random().toString(36).slice(2));
            fd.set('submit_token', form.dataset.submitToken);
            const embedToken = document.body.dataset.embedToken;
            const urlSubmit  = embedToken ? `/api/embed/${embedToken}/cotacao/auto` : '/api/cotacao/auto';
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
