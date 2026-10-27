/**
 * Formulário de Seguro Incêndio — 1 protocolo pode ter N imóveis. Cada
 * bloco de imóvel vem do mesmo <template> (inclusive o primeiro, criado
 * automaticamente ao carregar a página) — não tem "imóvel principal".
 */
document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('form-incendio');
    if (!form) return;

    // ─── Máscaras (reaplicadas em cada bloco clonado — masks.js só cobre
    // o que já existe na página no carregamento, não elementos futuros) ───
    function applyMask(input, fn) {
        input.addEventListener('input', function () {
            const start  = this.selectionStart;
            const before = this.value.length;
            this.value   = fn(this.value);
            const diff   = this.value.length - before;
            this.setSelectionRange(start + diff, start + diff);
        });
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
    function maskCurrency(v) {
        let n = v.replace(/\D/g, '');
        if (!n) return '';
        n = (n.replace(/^0+/, '') || '0');
        while (n.length < 3) n = '0' + n;
        const cents = n.slice(-2);
        const reais = n.slice(0, -2).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        return `R$ ${reais},${cents}`;
    }
    function maskDecimal(v) {
        let n = v.replace(/\D/g, '');
        if (!n) return '';
        n = (n.replace(/^0+/, '') || '0');
        while (n.length < 3) n = '0' + n;
        const dec = n.slice(-2);
        const int = n.slice(0, -2).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        return `${int},${dec}`;
    }
    // ─── Validação CPF / CNPJ (mesmo algoritmo do Fiança) ───────────────────
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
        const status = input.closest('.field-input-wrap')?.querySelector('[data-cpf-status-el]');
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

    // ─── Bloco de imóvel (template) ────────────────────────────────────────

    const imoveisList = document.getElementById('imoveis-list');
    const tplImovel    = document.getElementById('tpl-imovel');
    const btnAddImovel = document.getElementById('btn-add-imovel');
    const MAX_IMOVEIS  = 20;
    let imovelSeq = 0;

    function renumerarImoveis() {
        imoveisList?.querySelectorAll('[data-imovel-titulo]').forEach((el, i) => {
            el.textContent = `Imóvel ${i + 1}`;
        });
        imoveisList?.querySelectorAll('[data-remove-imovel]').forEach(btn => {
            btn.hidden = imoveisList.children.length <= 1;
        });
        if (btnAddImovel) {
            const atingiuLimite = (imoveisList?.children.length || 0) >= MAX_IMOVEIS;
            btnAddImovel.disabled = atingiuLimite;
            btnAddImovel.title = atingiuLimite ? `Máximo de ${MAX_IMOVEIS} imóveis por remessa.` : '';
        }
    }

    async function buscarImovelPorCodigo(card, codigo) {
        const status = card.querySelector('[data-imovel-lookup-status]');
        if (!codigo) {
            if (status) { status.hidden = false; status.textContent = 'Informe o código do imóvel.'; status.className = 'field-hint field-hint-erro'; }
            return;
        }

        // Não permite buscar um código já usado em outro imóvel desta remessa.
        const codigoJaUsado = Array.from(imoveisList.querySelectorAll('[data-field="codigo_imovel_sami"]')).some(el => {
            return el !== card.querySelector('[data-field="codigo_imovel_sami"]') && el.value.trim() === codigo;
        });
        if (codigoJaUsado) {
            if (status) { status.hidden = false; status.textContent = 'Este código já foi usado em outro imóvel desta remessa.'; status.className = 'field-hint field-hint-erro'; }
            return;
        }

        if (status) { status.hidden = false; status.textContent = 'Buscando imóvel...'; status.className = 'field-hint'; }

        try {
            const resp = await fetch(`/api/cotacao/incendio/imovel-buscar?codigo=${encodeURIComponent(codigo)}`);
            const d = await resp.json();
            if (!d.ok) {
                if (status) { status.textContent = d.erro || 'Imóvel não encontrado.'; status.className = 'field-hint field-hint-erro'; }
                return;
            }
            const im = d.imovel || {};
            const set = (field, value) => {
                const el = card.querySelector(`[data-field="${field}"]`);
                if (el && value !== undefined && value !== null && value !== '') el.value = value;
            };
            set('locador', im.proprietario);
            set('cpf_cnpj_locador', im.cpf_proprietario ? maskCpfCnpj(im.cpf_proprietario) : '');
            set('locatario', im.locatario);
            set('cpf_cnpj_locatario', im.cpf_locatario ? maskCpfCnpj(im.cpf_locatario) : '');
            set('endereco', im.logradouro);
            set('numero', im.numero);
            set('complemento', im.complemento);
            set('bairro', im.bairro);
            set('cidade', im.cidade);
            set('uf', im.uf);
            set('cep', im.cep);
            set('tipo_imovel', im.tipo_imovel);
            set('metragem', im.metragem ? maskDecimal(String(Math.round(Number(im.metragem) * 100))) : '');
            set('valor_aluguel', im.valor_aluguel ? maskCurrency(String(Math.round(Number(im.valor_aluguel) * 100))) : '');

            card.querySelectorAll('[data-mask="cpfcnpj"]').forEach(el => updateCpfStatus(el));

            if (status) { status.textContent = `Imóvel ${im.codigo || codigo} carregado. Confira os dados abaixo.`; status.className = 'field-hint field-hint-ok'; }
        } catch (e) {
            if (status) { status.textContent = 'Erro na busca. Tente novamente.'; status.className = 'field-hint field-hint-erro'; }
        }
    }

    async function lookupCep(card) {
        const cepInput = card.querySelector('[data-field="cep"]');
        if (!cepInput) return;
        const cep = cepInput.value.replace(/\D/g, '');
        if (cep.length !== 8) return;
        try {
            const r = await fetch(`https://viacep.com.br/ws/${cep}/json/`);
            const data = await r.json();
            if (data.erro) return;
            const set = (field, value) => {
                const el = card.querySelector(`[data-field="${field}"]`);
                if (el && value) el.value = value;
            };
            set('endereco', data.logradouro);
            set('bairro', data.bairro);
            set('cidade', data.localidade);
            set('uf', data.uf);
        } catch (_) {}
    }

    function adicionarImovel() {
        if (!tplImovel || !imoveisList) return;
        if (imoveisList.children.length >= MAX_IMOVEIS) return;
        const idx  = imovelSeq++;
        const card = tplImovel.content.firstElementChild.cloneNode(true);

        card.querySelectorAll('[data-field]').forEach(input => {
            input.name = `imoveis[${idx}][${input.dataset.field}]`;
        });

        card.querySelectorAll('[data-custom-select]').forEach(wrap => {
            const field  = wrap.dataset.field;
            const hidden = wrap.querySelector('input[type="hidden"]');
            if (field && hidden) hidden.name = `imoveis[${idx}][${field}]`;
        });

        card.querySelectorAll('[data-mask="cpfcnpj"]').forEach(el => {
            applyMask(el, maskCpfCnpj);
            el.addEventListener('input', () => updateCpfStatus(el));
            el.addEventListener('blur', () => updateCpfStatus(el));
        });
        card.querySelectorAll('[data-mask="cep"]').forEach(el => applyMask(el, maskCep));
        card.querySelectorAll('[data-mask="currency"]').forEach(el => applyMask(el, maskCurrency));
        card.querySelectorAll('[data-mask="decimal"]').forEach(el => applyMask(el, maskDecimal));

        // Cada bloco de imóvel é clonado do <template> (inclusive o primeiro) —
        // custom-select.js só inicializa o que já existe na página no carregamento,
        // então sem isso o dropdown de Parcelas nunca abre nos clones.
        imoveisList.appendChild(card);
        window.customSelectInit?.(card);

        card.querySelector('[data-field="cep"]')?.addEventListener('blur', () => lookupCep(card));
        const inputCodigoSami = card.querySelector('[data-field="codigo_imovel_sami"]');
        inputCodigoSami?.addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                buscarImovelPorCodigo(card, inputCodigoSami.value.trim());
            }
        });
        card.querySelector('[data-buscar-imovel]')?.addEventListener('click', () => {
            buscarImovelPorCodigo(card, inputCodigoSami?.value.trim());
        });

        card.querySelector('[data-remove-imovel]')?.addEventListener('click', () => {
            card.remove();
            renumerarImoveis();
        });

        // ─── Toggle "Já sei os valores de cada cobertura" ───────────────────
        // Desligado (padrão): a MPS define os valores na análise — esconde os
        // campos e nem exige preenchimento. Ligado: mostra os campos, cada um
        // ainda com seu próprio "Não informar" (a imobiliária pode saber só
        // parte dos valores).
        const toggleCoberturas  = card.querySelector('[data-informar-coberturas-toggle]');
        const hiddenCoberturas  = card.querySelector('[data-field="informar_coberturas"]');
        const camposCoberturas  = card.querySelector('[data-coberturas-campos]');
        toggleCoberturas?.addEventListener('change', () => {
            const ligado = toggleCoberturas.checked;
            if (hiddenCoberturas) hiddenCoberturas.value = ligado ? '1' : '0';
            if (camposCoberturas) camposCoberturas.hidden = !ligado;
        });

        // ─── Botões "Não informar" (Valor do aluguel / Vendaval) ───────────
        card.querySelectorAll('[data-skip-toggle]').forEach(btn => {
            const fieldName = btn.dataset.skipToggle;
            const input     = card.querySelector(`[data-field="${fieldName}"]`);
            const hidden    = card.querySelector(`[data-field="${fieldName}_skip"]`);
            if (!input) return;
            const originalPlaceholder = input.placeholder;

            btn.addEventListener('click', () => {
                const next = btn.getAttribute('aria-pressed') !== 'true';
                btn.setAttribute('aria-pressed', String(next));
                btn.classList.toggle('is-active', next);
                input.classList.toggle('is-skipped', next);
                input.disabled = next;
                if (hidden) hidden.value = next ? '1' : '0';
                input.value = '';
                input.placeholder = next ? 'Não informado' : originalPlaceholder;
                input.classList.remove('has-error');
            });
        });

        renumerarImoveis();
    }

    btnAddImovel?.addEventListener('click', adicionarImovel);
    adicionarImovel(); // sempre começa com 1 bloco visível

    // ─── Envio do formulário ────────────────────────────────────────────

    const btnSubmit = document.getElementById('btn-submit-incendio');
    btnSubmit?.addEventListener('click', async () => {
        const camposObrigatorios = imoveisList.querySelectorAll('[required]');
        let valido = true;
        camposObrigatorios.forEach(el => {
            if (!el.disabled && !el.value.trim()) {
                valido = false;
                el.classList.add('has-error');
            } else {
                el.classList.remove('has-error');
            }
        });

        // Valor do aluguel / Metragem: obrigatório preencher OU marcar "Não informar".
        imoveisList.querySelectorAll('[data-valor-imovel], [data-metragem-imovel]').forEach(el => {
            const card    = el.closest('[data-imovel-bloco]');
            const field   = el.dataset.field;
            const hidden  = card?.querySelector(`[data-field="${field}_skip"]`);
            const skipped = hidden && hidden.value === '1';
            const empty   = !skipped && el.value.replace(/\D/g, '') === '';
            el.classList.toggle('has-error', empty);
            if (empty) valido = false;
        });

        // Coberturas (Incêndio, Vendaval, etc.): só são obrigatórias quando a
        // imobiliária ligou "Já sei os valores de cada cobertura" — e mesmo
        // assim, cada uma pode ser marcada individualmente como "Não informar".
        imoveisList.querySelectorAll('[data-cobertura-imovel]').forEach(el => {
            const card      = el.closest('[data-imovel-bloco]');
            const informa   = card?.querySelector('[data-field="informar_coberturas"]')?.value === '1';
            if (!informa) { el.classList.remove('has-error'); return; }
            const field     = el.dataset.field;
            const hidden    = card?.querySelector(`[data-field="${field}_skip"]`);
            const skipped   = hidden && hidden.value === '1';
            const empty     = !skipped && el.value.replace(/\D/g, '') === '';
            el.classList.toggle('has-error', empty);
            if (empty) valido = false;
        });

        if (!valido) {
            mpsAlert.error('Preencha os campos obrigatórios de todos os imóveis (ou marque "Não informar" nos valores).');
            return;
        }

        // ─── Bloqueia imóveis duplicados na mesma remessa ───────────────────
        const cards = Array.from(imoveisList.querySelectorAll('[data-imovel-bloco]'));

        // Mesmo código Sami usado em mais de um imóvel.
        const codigosVistos = new Map();
        for (let i = 0; i < cards.length; i++) {
            const codigo = cards[i].querySelector('[data-field="codigo_imovel_sami"]')?.value.trim();
            if (!codigo) continue;
            if (codigosVistos.has(codigo)) {
                mpsAlert.error(`Os imóveis ${codigosVistos.get(codigo) + 1} e ${i + 1} usam o mesmo código (${codigo}). Remova a duplicata.`);
                return;
            }
            codigosVistos.set(codigo, i);
        }

        // Imóveis idênticos digitados manualmente (mesmo endereço e mesmos dados).
        const CAMPOS_ASSINATURA = [
            'locador', 'cpf_cnpj_locador', 'locatario', 'cpf_cnpj_locatario',
            'endereco', 'numero', 'complemento', 'bairro', 'cidade', 'uf', 'cep',
        ];
        const assinaturaDe = (card) => CAMPOS_ASSINATURA
            .map(f => (card.querySelector(`[data-field="${f}"]`)?.value || '').trim().toLowerCase())
            .join('|');
        const assinaturasVistas = new Map();
        for (let i = 0; i < cards.length; i++) {
            const assinatura = assinaturaDe(cards[i]);
            if (assinaturasVistas.has(assinatura)) {
                mpsAlert.error(`Os imóveis ${assinaturasVistas.get(assinatura) + 1} e ${i + 1} estão com os mesmos dados (endereço, locador e locatário iguais). Remova a duplicata.`);
                return;
            }
            assinaturasVistas.set(assinatura, i);
        }

        btnSubmit.classList.add('is-loading');
        btnSubmit.disabled = true;

        try {
            const fd = new FormData(form);
            if (!form.dataset.submitToken) {
                form.dataset.submitToken = (window.crypto && crypto.randomUUID) ? crypto.randomUUID() : (Date.now() + '-' + Math.random().toString(36).slice(2));
            }
            fd.set('submit_token', form.dataset.submitToken);

            const resp = await fetch('/api/cotacao/incendio', { method: 'POST', body: fd });
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
            window.location.href = (typeof appUrl === 'function' ? appUrl('/cotacoes') : '/cotacoes');
        } catch (e) {
            mpsAlert.error('Erro de conexão. Verifique sua internet e tente novamente.');
        } finally {
            btnSubmit.classList.remove('is-loading');
            btnSubmit.disabled = false;
        }
    });
});
