/**
 * Módulo — Formulário de cotação Seguro Residencial.
 * Controla: stepper (3 etapas), máscaras, validação de CPF, busca de CEP,
 * endereço do segurado e envio via fetch (includes/acao/cotacao-residencial.php).
 */
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('form-cotacao-residencial');
    if (!form) return;

    // ─── Stepper ─────────────────────────────────────────────────────────
    let currentStep = 1;
    const TOTAL = 3;
    const stepEls    = document.querySelectorAll('[data-step-indicator]');
    const connectors = document.querySelectorAll('.stepper-connector');
    const panels     = form.querySelectorAll('.stepper-panel');

    function goToStep(n) {
        stepEls.forEach((s, i) => {
            s.classList.toggle('is-active', i + 1 === n);
            s.classList.toggle('is-done',   i + 1 < n);
        });
        connectors.forEach((c, i) => c.classList.toggle('is-done', i + 1 < n));
        panels.forEach((p, i) => p.classList.toggle('is-active', i + 1 === n));
        currentStep = n;
        document.querySelector('.panel')?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    const validadores = { 1: validarStep1, 2: validarStep2, 3: validarStep3 };

    form.querySelectorAll('[data-next]').forEach(btn => {
        btn.addEventListener('click', () => {
            if (!validadores[currentStep]()) return;
            if (currentStep < TOTAL) goToStep(currentStep + 1);
        });
    });
    form.querySelectorAll('[data-prev]').forEach(btn => {
        btn.addEventListener('click', () => { if (currentStep > 1) goToStep(currentStep - 1); });
    });

    // ─── Erros de campo ──────────────────────────────────────────────────
    function setErro(input, msg) {
        input?.classList.add('has-error');
        const err = input?.closest('.field')?.querySelector('.field-error');
        if (err) { err.textContent = msg; err.hidden = false; }
    }
    function limparErro(input) {
        input?.classList.remove('has-error');
        const err = input?.closest('.field')?.querySelector('.field-error');
        if (err) { err.textContent = ''; err.hidden = true; }
    }
    function erroGrupo(id, msg) {
        const el = document.getElementById(id);
        if (!el) return;
        el.textContent = msg || '';
        el.hidden = !msg;
    }
    form.querySelectorAll('.field-input').forEach(input => {
        input.addEventListener('input', () => limparErro(input));
    });

    function obrigatorios(nomes) {
        let ok = true;
        nomes.forEach(nome => {
            const input = form.elements[nome];
            if (!input) return;
            if (!String(input.value || '').trim()) {
                setErro(input, 'Campo obrigatório.');
                ok = false;
            } else {
                limparErro(input);
            }
        });
        return ok;
    }

    function rolarParaErro(painel) {
        painel?.querySelector('.has-error, .field-error:not([hidden])')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    // ─── Validações por etapa ────────────────────────────────────────────
    function validarStep1() {
        let ok = obrigatorios(['nome_completo', 'cpf_cnpj', 'tel_celular', 'email']);
        const nome = form.elements.nome_completo;
        if (nome.value.trim() && !nome.value.trim().includes(' ')) {
            setErro(nome, 'Informe nome e sobrenome.');
            ok = false;
        }
        const cpf = form.elements.cpf_cnpj;
        if (cpf.value.trim() && !validarCpf(cpf.value)) {
            setErro(cpf, 'CPF inválido.');
            ok = false;
        }
        const email = form.elements.email;
        if (email.value.trim() && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email.value.trim())) {
            setErro(email, 'E-mail inválido.');
            ok = false;
        }
        const cel = form.elements.tel_celular;
        if (cel.value.trim() && cel.value.replace(/\D/g, '').length !== 11) {
            setErro(cel, 'Informe o celular com DDD.');
            ok = false;
        }
        const nascimento = form.elements.data_nascimento;
        if (!dataValida(nascimento.value)) {
            setErro(nascimento, nascimento.value.trim() ? 'Data inválida.' : 'Campo obrigatório.');
            ok = false;
        } else {
            limparErro(nascimento);
        }
        if (!form.querySelector('[name="sexo"]:checked')) {
            erroGrupo('sexo-error', 'Selecione o sexo.');
            ok = false;
        } else {
            erroGrupo('sexo-error', '');
        }
        if (!ok) rolarParaErro(form.querySelector('[data-step="1"]'));
        return ok;
    }

    function validarStep2() {
        let ok = obrigatorios(['cep', 'endereco', 'numero', 'bairro', 'cidade', 'uf']);
        if (!form.querySelector('[name="tipo_imovel"]:checked')) {
            erroGrupo('tipo-imovel-error', 'Selecione o tipo do imóvel.');
            ok = false;
        } else {
            erroGrupo('tipo-imovel-error', '');
        }
        if (!form.elements.endereco_segurado_igual.checked) {
            ok = obrigatorios(['seg_cep', 'seg_endereco', 'seg_numero', 'seg_bairro', 'seg_cidade', 'seg_uf']) && ok;
        }
        if (!ok) rolarParaErro(form.querySelector('[data-step="2"]'));
        return ok;
    }

    function validarStep3() {
        const incendio = form.elements.cob_incendio;
        if (numero(incendio.value) <= 0) {
            setErro(incendio, 'Informe o valor da cobertura de incêndio.');
            rolarParaErro(form.querySelector('[data-step="3"]'));
            return false;
        }
        limparErro(incendio);
        return true;
    }

    // ─── Máscaras ────────────────────────────────────────────────────────
    function aplicarMascara(input, fn) {
        input.addEventListener('input', function () {
            const inicio = this.selectionStart;
            const antes  = this.value.length;
            this.value   = fn(this.value);
            const dif    = this.value.length - antes;
            this.setSelectionRange(inicio + dif, inicio + dif);
        });
    }
    // DD/MM/AAAA real e no passado (nascimento).
    function dataValida(v) {
        const m = String(v || '').match(/^(\d{2})\/(\d{2})\/(\d{4})$/);
        if (!m) return false;
        const d = new Date(+m[3], +m[2] - 1, +m[1]);
        return d.getDate() === +m[1] && d.getMonth() === +m[2] - 1 && d < new Date() && +m[3] > 1900;
    }

    const mascaras = {
        date: v => v.replace(/\D/g, '').slice(0, 8).replace(/^(\d{2})(\d)/, '$1/$2').replace(/^(\d{2})\/(\d{2})(\d)/, '$1/$2/$3'),
        cpf: v => v.replace(/\D/g, '').slice(0, 11)
            .replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d)/, '$1.$2').replace(/(\d{3})(\d{1,2})$/, '$1-$2'),
        cep: v => v.replace(/\D/g, '').slice(0, 8).replace(/(\d{5})(\d{1,3})$/, '$1-$2'),
        phone: v => {
            const n = v.replace(/\D/g, '').slice(0, 11);
            return n.length <= 10
                ? n.replace(/(\d{2})(\d)/, '($1) $2').replace(/(\d{4})(\d{1,4})$/, '$1-$2')
                : n.replace(/(\d{2})(\d)/, '($1) $2').replace(/(\d{5})(\d{1,4})$/, '$1-$2');
        },
        currency: v => {
            const d = v.replace(/\D/g, '');
            return d ? (parseInt(d, 10) / 100).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '';
        },
    };
    Object.entries(mascaras).forEach(([tipo, fn]) => {
        form.querySelectorAll(`[data-mask="${tipo}"]`).forEach(el => aplicarMascara(el, fn));
    });
    form.elements.uf?.addEventListener('input', e => { e.target.value = e.target.value.toUpperCase().replace(/[^A-Z]/g, ''); });
    form.elements.seg_uf?.addEventListener('input', e => { e.target.value = e.target.value.toUpperCase().replace(/[^A-Z]/g, ''); });

    function numero(v) {
        return parseFloat(String(v || '').replace(/\./g, '').replace(',', '.')) || 0;
    }

    // ─── CPF ─────────────────────────────────────────────────────────────
    function validarCpf(valor) {
        const n = valor.replace(/\D/g, '');
        if (n.length !== 11 || /^(\d)\1{10}$/.test(n)) return false;
        const dv = (base) => {
            let soma = 0;
            for (let i = 0; i < base; i++) soma += parseInt(n[i], 10) * (base + 1 - i);
            const r = (soma * 10) % 11;
            return r === 10 ? 0 : r;
        };
        return dv(9) === parseInt(n[9], 10) && dv(10) === parseInt(n[10], 10);
    }
    const SVG_OK  = '<svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256"><path d="M173.66,98.34a8,8,0,0,1,0,11.32l-56,56a8,8,0,0,1-11.32,0l-24-24a8,8,0,0,1,11.32-11.32L112,148.69l50.34-50.35A8,8,0,0,1,173.66,98.34ZM232,128A104,104,0,1,1,128,24,104.11,104.11,0,0,1,232,128Zm-16,0a88,88,0,1,0-88,88A88.1,88.1,0,0,0,216,128Z"/></svg>';
    const SVG_ERR = '<svg class="icon" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 256 256"><path d="M165.66,101.66,139.31,128l26.35,26.34a8,8,0,0,1-11.32,11.32L128,139.31l-26.34,26.35a8,8,0,0,1-11.32-11.32L116.69,128,90.34,101.66a8,8,0,0,1,11.32-11.32L128,116.69l26.34-26.35a8,8,0,0,1,11.32,11.32ZM232,128A104,104,0,1,1,128,24,104.11,104.11,0,0,1,232,128Zm-16,0a88,88,0,1,0-88,88A88.1,88.1,0,0,0,216,128Z"/></svg>';
    const cpfInput  = form.elements.cpf_cnpj;
    const cpfStatus = document.getElementById('cpf-status');
    function atualizarStatusCpf() {
        const n = cpfInput.value.replace(/\D/g, '');
        if (n.length !== 11) { cpfStatus.hidden = true; return; }
        const ok = validarCpf(n);
        cpfStatus.hidden = false;
        cpfStatus.className = `field-input-status ${ok ? 'is-valid' : 'is-error'}`;
        cpfStatus.innerHTML = ok ? SVG_OK : SVG_ERR;
    }
    cpfInput?.addEventListener('input', atualizarStatusCpf);
    cpfInput?.addEventListener('blur', atualizarStatusCpf);

    // ─── CEP (ViaCEP) — imóvel e segurado ────────────────────────────────
    form.querySelectorAll('[data-cep-prefixo]').forEach(input => {
        input.addEventListener('blur', async () => {
            const p   = input.dataset.cepPrefixo || '';
            const cep = input.value.replace(/\D/g, '');
            if (cep.length !== 8) return;
            try {
                const r = await fetch(`https://viacep.com.br/ws/${cep}/json/`);
                const d = await r.json();
                if (d.erro) return;
                const mapa = { endereco: d.logradouro, bairro: d.bairro, cidade: d.localidade, uf: d.uf };
                Object.entries(mapa).forEach(([campo, valor]) => {
                    const el = form.elements[p + campo];
                    if (el && valor) { el.value = valor; limparErro(el); }
                });
                form.elements[p + 'numero']?.focus();
            } catch (_) {}
        });
    });

    // ─── Endereço do segurado ────────────────────────────────────────────
    const igual = form.elements.endereco_segurado_igual;
    const blocoSegurado = document.getElementById('endereco-segurado');
    igual?.addEventListener('change', () => { blocoSegurado.hidden = igual.checked; });

    // ─── Envio ───────────────────────────────────────────────────────────
    const btnEnviar = document.getElementById('btn-submit-residencial');
    btnEnviar?.addEventListener('click', async () => {
        for (let s = 1; s <= TOTAL; s++) {
            if (!validadores[s]()) { goToStep(s); return; }
        }

        btnEnviar.classList.add('is-loading');
        btnEnviar.disabled = true;
        try {
            const fd = new FormData(form);
            if (!form.dataset.submitToken) {
                form.dataset.submitToken = (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : (Date.now() + '-' + Math.random().toString(36).slice(2));
            }
            fd.set('submit_token', form.dataset.submitToken);
            const embedToken = document.body.dataset.embedToken;
            const urlSubmit  = embedToken ? `/api/embed/${embedToken}/cotacao/residencial` : '/api/cotacao/residencial';
            const resp = await fetch(urlSubmit, { method: 'POST', body: fd });
            const data = await resp.json();
            if (!resp.ok || !data.success) {
                mpsAlert.error(data.message || 'Erro ao enviar a cotação. Tente novamente.');
                return;
            }
            await mpsAlert.success(
                `Cotação enviada! Protocolo: <strong>${data.protocolo}</strong>` + (data.aviso_horario ? `<br><br>${data.aviso_horario}` : ''),
                { title: 'Enviado!', autoCloseMs: data.aviso_horario ? 8000 : 4000, html: true }
            );
            if (embedToken) {
                window.location.reload();
            } else {
                window.location.href = appUrl(data.url || '/cotacoes');
            }
        } catch (_) {
            mpsAlert.error('Erro de conexão. Verifique sua internet e tente novamente.');
        } finally {
            btnEnviar.classList.remove('is-loading');
            btnEnviar.disabled = false;
        }
    });
});
