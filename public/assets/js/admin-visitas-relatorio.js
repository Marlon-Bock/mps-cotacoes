/**
 * Relatório de Visitas — admin-visitas-relatorio.js
 * Autocomplete de imobiliária, comissão por seguro (%/R$), salvar/editar/
 * excluir visita do dia — tudo via AJAX (/api/admin/visitas-relatorio/* e
 * /api/admin/visitas/*), sem navegar nem alterar a URL.
 */
(function () {
    'use strict';

    const form        = document.getElementById('form-visita');
    const lista       = document.getElementById('visitas-hoje-lista');
    if (!form || !lista) return;

    const csrfToken = form.dataset.csrf || '';

    const elImobNome = document.getElementById('f-imobiliaria-nome');
    const elImobId   = document.getElementById('f-imobiliaria-id');
    const elSugestoes = document.getElementById('visita-imob-sugestoes');
    const elFaltaMaterial = document.getElementById('f-falta-material');
    const elMateriaisWrap = document.getElementById('f-materiais-wrap');
    const badge = document.getElementById('visitas-hoje-badge');
    const btnSalvarLabel = document.getElementById('btn-salvar-visita-label');
    const formTitulo = document.getElementById('visita-form-titulo');

    // ── "Corretores que foram junto": dropdown único vira multi-seleção ───────
    // O corretor principal é sempre o usuário logado (implícito, não faz
    // parte desta lista) — aqui é só participantes adicionais, sem conceito
    // de "principal" (diferente de #para-usuario-select em
    // admin-visitas-agendar.js). Não mexe em custom-select.js (genérico,
    // single-select) — intercepta o clique nas opções só deste dropdown
    // específico, em fase de captura, antes do handler genérico rodar.
    let USUARIOS_RELATORIO = [];
    try {
        const _uj = document.getElementById('relatorio-usuarios-json');
        if (_uj) USUARIOS_RELATORIO = JSON.parse(_uj.textContent || '[]');
    } catch (_) { USUARIOS_RELATORIO = []; }
    const nomeCorretorExtra = (id) => {
        const u = USUARIOS_RELATORIO.filter((x) => x.id === id)[0];
        return u ? u.username : '';
    };

    const corretoresExtraWrap = document.getElementById('corretores-extra-select');
    const corretoresExtraHidden = corretoresExtraWrap ? corretoresExtraWrap.querySelector('input[type="hidden"]') : null;
    const corretoresExtraValueEl = corretoresExtraWrap ? corretoresExtraWrap.querySelector('.custom-select-value') : null;
    const corretoresExtraDefault = corretoresExtraWrap && corretoresExtraWrap.dataset.selecionados
        ? corretoresExtraWrap.dataset.selecionados.split(',').map((v) => parseInt(v, 10)).filter((v) => v > 0)
        : [];

    let corretoresExtraSelecionados = corretoresExtraDefault.slice();

    function escHtml(s) {
        const d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML.replace(/"/g, '&quot;');
    }

    function renderCorretoresExtraSelecao() {
        if (!corretoresExtraWrap) return;
        corretoresExtraWrap.querySelectorAll('.custom-select-option').forEach((opt) => {
            const id = parseInt(opt.dataset.value || '0', 10);
            opt.classList.toggle('is-selected', corretoresExtraSelecionados.indexOf(id) !== -1);
        });

        if (corretoresExtraHidden) corretoresExtraHidden.value = corretoresExtraSelecionados[0] || '';
        if (corretoresExtraValueEl) {
            if (!corretoresExtraSelecionados.length) {
                corretoresExtraValueEl.textContent = corretoresExtraWrap.dataset.placeholder || 'Selecione...';
                corretoresExtraValueEl.classList.add('is-placeholder');
            } else {
                const nomes = corretoresExtraSelecionados.map(nomeCorretorExtra).filter(Boolean);
                corretoresExtraValueEl.textContent = nomes.length > 1
                    ? `${nomes[0]} +${nomes.length - 1}`
                    : (nomes[0] || '');
                corretoresExtraValueEl.classList.remove('is-placeholder');
            }
        }
    }

    if (corretoresExtraWrap) {
        // Fase de captura: intercepta ANTES do listener genérico de
        // custom-select.js (que fecharia o dropdown e trocaria o valor como
        // single-select).
        corretoresExtraWrap.addEventListener('click', (e) => {
            const opt = e.target.closest('.custom-select-option');
            if (!opt || opt.classList.contains('is-placeholder')) return;
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();

            const id = parseInt(opt.dataset.value || '0', 10);
            if (!id) return;

            const idx = corretoresExtraSelecionados.indexOf(id);
            if (idx !== -1) {
                corretoresExtraSelecionados.splice(idx, 1);
            } else {
                corretoresExtraSelecionados.push(id);
            }
            renderCorretoresExtraSelecao();
        }, true);

        renderCorretoresExtraSelecao();
    }

    function setCorretoresExtraSelecao(ids) {
        corretoresExtraSelecionados = Array.isArray(ids) ? ids.map((v) => parseInt(v, 10)).filter((v) => v > 0) : [];
        renderCorretoresExtraSelecao();
    }

    // ── Máscara do valor de comissão (% ou R$, conforme o modo escolhido) ─────
    function maskPercentual(v) {
        let n = v.replace(/\D/g, '');
        if (!n) return '';
        n = (n.replace(/^0+/, '') || '0');
        while (n.length < 3) n = '0' + n;
        const dec = n.slice(-2);
        let inteiro = n.slice(0, -2).replace(/^0+(?=\d)/, '');
        if (parseInt(inteiro || '0', 10) > 100) inteiro = '100';
        return `${inteiro},${dec}`;
    }

    function maskMoeda(v) {
        let n = v.replace(/\D/g, '');
        if (!n) return '';
        n = (n.replace(/^0+/, '') || '0');
        while (n.length < 3) n = '0' + n;
        const cents = n.slice(-2);
        const reais = n.slice(0, -2).replace(/\B(?=(\d{3})+(?!\d))/g, '.');
        return `${reais},${cents}`;
    }

    function limparNumero(v) {
        const n = (v || '').replace(/\D/g, '');
        return n ? (parseInt(n, 10) / 100).toFixed(2) : '';
    }

    function comissaoModoAtual(wrap) {
        const radio = wrap.querySelector('input[type="radio"]:checked');
        return radio ? radio.value : 'percentual';
    }

    function setComissaoValor(wrap, modo, valorNumerico) {
        const prefixo = wrap.querySelector('.field-input-prefix');
        const sufixo  = wrap.querySelector('.field-input-suffix');
        const input   = wrap.querySelector('input[data-mask="percentual"]');
        if (!input) return;
        const isValor = modo === 'valor';
        if (prefixo) prefixo.hidden = !isValor;
        if (sufixo)  sufixo.hidden  = isValor;
        input.classList.toggle('has-prefix', isValor);
        input.classList.toggle('has-suffix', !isValor);
        const n = parseFloat(valorNumerico) || 0;
        input.value = n ? n.toFixed(2).replace('.', ',') : '';
    }

    function configurarComissaoMascara(wrap) {
        const input = wrap.querySelector('input[data-mask="percentual"]');
        if (!input) return;

        wrap.querySelectorAll('input[type="radio"]').forEach((r) => {
            r.addEventListener('change', () => setComissaoValor(wrap, r.value, 0));
        });

        input.addEventListener('input', function () {
            const isValor = comissaoModoAtual(wrap) === 'valor';
            const start  = this.selectionStart;
            const before = this.value.length;
            this.value   = isValor ? maskMoeda(this.value) : maskPercentual(this.value);
            const diff   = this.value.length - before;
            this.setSelectionRange(start + diff, start + diff);
        });

        setComissaoValor(wrap, comissaoModoAtual(wrap), '0');
    }

    form.querySelectorAll('.visita-comissao-wrap').forEach(configurarComissaoMascara);

    function setTimePickerValue(elementId, hhmm) {
        const hidden = document.getElementById(elementId);
        if (!hidden) return;
        const wrapper = hidden.closest('[data-time-picker]');
        hidden.value = hhmm || '';
        if (!wrapper) return;
        const valueEl = wrapper.querySelector('.time-picker-value');
        if (hhmm) {
            valueEl.textContent = hhmm;
            valueEl.classList.remove('is-placeholder');
        } else {
            valueEl.textContent = wrapper.dataset.placeholder || '--:--';
            valueEl.classList.add('is-placeholder');
        }
    }

    async function postJson(url, body) {
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify(body),
        });
        const data = await response.json().catch(() => ({}));
        if (!response.ok || data.success === false) {
            mpsAlert.error(data.message || 'Não foi possível concluir a ação.');
            return null;
        }
        return data;
    }

    // ── Lista de visitas de hoje ──────────────────────────────────────────────
    function carregarLista() {
        fetch('/api/admin/visitas-relatorio/listar')
            .then((r) => r.json())
            .then((data) => {
                lista.innerHTML = data.html || '';
                badge.hidden = !data.total;
                badge.textContent = data.total || 0;
            })
            .catch(() => {
                lista.innerHTML = '<div class="panel-body"><div class="empty-state"><p>Erro ao carregar visitas.</p></div></div>';
            });
    }

    // ── Autocomplete de imobiliária ───────────────────────────────────────────
    let buscaTimer = null;

    function fecharSugestoes() {
        elSugestoes.innerHTML = '';
        elSugestoes.hidden = true;
    }

    function selecionarImobiliaria(id, nome) {
        elImobId.value = id;
        elImobNome.value = nome;
        fecharSugestoes();
        if (id) carregarComissoesDaImobiliaria(parseInt(id, 10));
    }

    elImobNome.addEventListener('input', () => {
        elImobId.value = ''; // digitou de novo — só volta a ter id se clicar numa sugestão
        clearTimeout(buscaTimer);
        const termo = elImobNome.value.trim();
        if (termo.length < 2) { fecharSugestoes(); return; }

        buscaTimer = setTimeout(() => {
            fetch('/api/admin/visitas/imobiliaria-buscar?q=' + encodeURIComponent(termo))
                .then((r) => r.json())
                .then((data) => {
                    const itens = data.itens || [];
                    if (!itens.length) { fecharSugestoes(); return; }
                    elSugestoes.innerHTML = itens.map((i) =>
                        `<li data-id="${i.id}" data-nome="${escHtml(i.nome)}">${escHtml(i.nome)}</li>`
                    ).join('');
                    elSugestoes.hidden = false;
                })
                .catch(() => fecharSugestoes());
        }, 300);
    });

    elSugestoes.addEventListener('click', (e) => {
        const li = e.target.closest('li');
        if (!li) return;
        selecionarImobiliaria(li.dataset.id, li.dataset.nome);
    });

    document.addEventListener('click', (e) => {
        if (!e.target.closest('.visita-imob-field')) fecharSugestoes();
    });

    // ── Comissão por tipo de seguro ────────────────────────────────────────────
    function carregarComissoesDaImobiliaria(imobiliariaId) {
        fetch('/api/admin/visitas/comissao?action=get&imobiliaria_id=' + imobiliariaId)
            .then((r) => r.json())
            .then((data) => {
                if (!data.success) return;
                Object.entries(data.comissoes).forEach(([tipo, c]) => {
                    const wrap = form.querySelector(`.visita-comissao-wrap[data-tipo="${tipo}"]`);
                    if (!wrap) return;
                    const radio = wrap.querySelector(`input[type="radio"][value="${c.modo}"]`);
                    if (radio) radio.checked = true;
                    setComissaoValor(wrap, c.modo, c.modo === 'valor' ? c.valor : c.percentual);
                });
            })
            .catch(() => {});
    }

    form.querySelectorAll('.visita-seguro-check').forEach((chk) => {
        chk.addEventListener('change', () => {
            const wrap = form.querySelector(`.visita-comissao-wrap[data-tipo="${chk.dataset.tipo}"]`);
            if (wrap) wrap.hidden = !chk.checked;
        });
    });

    // ── Falta material ─────────────────────────────────────────────────────────
    elFaltaMaterial.addEventListener('change', () => {
        elMateriaisWrap.hidden = !elFaltaMaterial.checked;
    });

    // ── Limpar / resetar formulário ────────────────────────────────────────────
    function limparFormulario() {
        form.reset();
        document.getElementById('f-item-id').value = '';
        document.getElementById('f-ag-id').value = '';
        elImobId.value = '';
        setTimePickerValue('f-entrada', '');
        setTimePickerValue('f-saida', '');
        fecharSugestoes();
        elMateriaisWrap.hidden = true;
        setCorretoresExtraSelecao([]);
        form.querySelectorAll('.visita-comissao-wrap').forEach((w) => {
            w.hidden = true;
            setComissaoValor(w, 'percentual', 0);
        });
        formTitulo.textContent = 'Nova visita';
        btnSalvarLabel.textContent = 'Salvar visita';

        // custom_select de motivo_visita não é limpo pelo form.reset() nativo
        const motivoHidden = form.querySelector('input[name="motivo_visita"]');
        if (motivoHidden) {
            const wrapper = motivoHidden.closest('[data-custom-select]');
            if (wrapper) {
                const placeholder = wrapper.dataset.placeholder || 'Selecione...';
                const valueEl = wrapper.querySelector('.custom-select-value');
                valueEl.textContent = placeholder;
                valueEl.classList.add('is-placeholder');
                wrapper.querySelectorAll('.custom-select-option').forEach((opt) => {
                    opt.classList.toggle('is-selected', opt.dataset.value === '');
                });
                motivoHidden.value = '';
            }
        }
    }

    document.getElementById('btn-limpar-visita').addEventListener('click', limparFormulario);

    // ── Editar / Excluir (delegação no container da lista) ────────────────────
    lista.addEventListener('click', async (e) => {
        const btnEditar = e.target.closest('[data-action="visita-editar"]');
        if (btnEditar) {
            const card = btnEditar.closest('.visita-hoje-card');
            const item = JSON.parse(card.dataset.item);

            document.getElementById('f-item-id').value = item.id;
            selecionarImobiliaria(item.imobiliaria_id || '', item.imobiliaria_nome || '');
            setCorretoresExtraSelecao(item.corretores_extra || []);
            document.getElementById('f-gerente').value = item.gerente || '';
            document.getElementById('f-atendente').value = item.atendente || '';
            setTimePickerValue('f-entrada', (item.horario_entrada || '').substring(0, 5));
            setTimePickerValue('f-saida', (item.horario_saida || '').substring(0, 5));
            document.getElementById('f-motivo-descricao').value = item.motivo_descricao || '';
            document.getElementById('f-outros-seguros').value = item.outros_seguros || '';
            document.getElementById('f-observacoes').value = item.observacoes || '';

            elFaltaMaterial.checked = !!parseInt(item.falta_material, 10);
            elMateriaisWrap.hidden = !elFaltaMaterial.checked;
            document.getElementById('f-materiais-faltantes').value = item.materiais_faltantes || '';

            const mapaSeguro = { fianca: 'seguro_fianca', cap: 'seguro_cap', incendio: 'seguro_incendio', vida: 'seguro_vida', auto: 'seguro_auto' };
            Object.entries(mapaSeguro).forEach(([tipo, coluna]) => {
                const chk = form.querySelector(`.visita-seguro-check[data-tipo="${tipo}"]`);
                const marcado = !!parseInt(item[coluna], 10);
                chk.checked = marcado;
                const wrap = form.querySelector(`.visita-comissao-wrap[data-tipo="${tipo}"]`);
                if (wrap) wrap.hidden = !marcado;
            });

            // motivo_visita (custom_select)
            const motivoHidden = form.querySelector('input[name="motivo_visita"]');
            if (motivoHidden && item.motivo_visita) {
                const wrapper = motivoHidden.closest('[data-custom-select]');
                const opt = wrapper.querySelector(`.custom-select-option[data-value="${item.motivo_visita}"]`);
                if (opt) opt.click();
            }

            formTitulo.textContent = 'Editar visita';
            btnSalvarLabel.textContent = 'Atualizar visita';
            window.scrollTo({ top: 0, behavior: 'smooth' });
            return;
        }

        const btnExcluir = e.target.closest('[data-action="visita-excluir"]');
        if (btnExcluir) {
            const confirmado = await mpsAlert.confirm({
                title: 'Remover visita?',
                message: 'Essa ação não pode ser desfeita.',
                confirmText: 'Remover',
                cancelText: 'Cancelar',
            });
            if (!confirmado) return;

            const ok = await postJson('/api/admin/visitas-relatorio/excluir', { item_id: parseInt(btnExcluir.dataset.id, 10) });
            if (ok) carregarLista();
        }
    });

    // ── Salvar ────────────────────────────────────────────────────────────────
    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const motivoHidden = form.querySelector('input[name="motivo_visita"]');
        const seguros = {};
        form.querySelectorAll('.visita-seguro-check').forEach((chk) => {
            const tipo = chk.dataset.tipo;
            const wrap = form.querySelector(`.visita-comissao-wrap[data-tipo="${tipo}"]`);
            const modoRadio = wrap ? wrap.querySelector('input[type="radio"]:checked') : null;
            const valorInput = wrap ? wrap.querySelector('.visita-comissao-valor') : null;
            seguros[tipo] = {
                checked: chk.checked,
                modo: modoRadio ? modoRadio.value : 'percentual',
                numero: valorInput ? parseFloat(limparNumero(valorInput.value) || '0') : 0,
            };
        });

        const payload = {
            item_id: parseInt(document.getElementById('f-item-id').value || '0', 10),
            ag_id: parseInt(document.getElementById('f-ag-id').value || '0', 10),
            imobiliaria_id: parseInt(elImobId.value || '0', 10),
            imobiliaria_nome: elImobNome.value.trim(),
            gerente: document.getElementById('f-gerente').value.trim(),
            atendente: document.getElementById('f-atendente').value.trim(),
            horario_entrada: document.getElementById('f-entrada').value,
            horario_saida: document.getElementById('f-saida').value,
            motivo_visita: motivoHidden ? motivoHidden.value : '',
            motivo_descricao: document.getElementById('f-motivo-descricao').value.trim(),
            outros_seguros: document.getElementById('f-outros-seguros').value.trim(),
            falta_material: elFaltaMaterial.checked,
            materiais_faltantes: document.getElementById('f-materiais-faltantes').value.trim(),
            observacoes: document.getElementById('f-observacoes').value.trim(),
            seguros,
            corretores_extra: corretoresExtraSelecionados.slice(),
        };

        if (!payload.imobiliaria_nome || !payload.gerente || !payload.motivo_visita) {
            mpsAlert.error('Preencha imobiliária, gerente e motivo da visita.');
            return;
        }
        if (!payload.horario_entrada || !payload.horario_saida) {
            mpsAlert.error('Informe os horários de entrada e saída.');
            return;
        }

        const btn = document.getElementById('btn-salvar-visita');
        btn.disabled = true;

        const ok = await postJson('/api/admin/visitas-relatorio/salvar', payload);

        if (ok) {
            mpsAlert.success('Visita salva com sucesso.');
            limparFormulario();
            carregarLista();
        }
        btn.disabled = false;
    });

    // Se a página já veio com uma imobiliária pré-preenchida (via ?ag_id=N),
    // busca as comissões dela também.
    if (elImobId.value) {
        carregarComissoesDaImobiliaria(parseInt(elImobId.value, 10));
    }

    carregarLista();
})();
