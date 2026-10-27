/**
 * Agendar Visita — admin-visitas-agendar.js
 * Filtros + lista de agendamentos, modal de criar/editar, exclusão,
 * desconectar Google — tudo via AJAX (/api/admin/visitas-agendar/* e
 * /api/admin/visitas/*), sem navegar nem alterar a URL. Inclui o autocomplete
 * de "Corretores adicionais" (participantes extras, sem permissão de editar).
 */
(function () {
    'use strict';

    const lista = document.getElementById('agendar-lista');
    if (!lista) return;

    const csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    const appContent = document.querySelector('.app-content[data-mps-admin]');
    const uidAtual    = appContent ? parseInt(appContent.dataset.uid || '0', 10) : 0;

    const countEls = {
        todos:     document.getElementById('count-todos'),
        pendente:  document.getElementById('count-pendente'),
        concluida: document.getElementById('count-concluida'),
    };

    const dataFiltro    = document.getElementById('f-ag-data');
    const corretorFiltro = document.querySelector('input[name="ag_corretor_id"]');

    let statusAtivo = '';

    function carregar() {
        const params = new URLSearchParams({
            status: statusAtivo,
            data: dataFiltro.value,
            corretor_id: corretorFiltro ? corretorFiltro.value : '',
        });

        lista.innerHTML = '<div class="admin-cot-empty">Carregando...</div>';

        fetch('/api/admin/visitas-agendar/listar?' + params.toString())
            .then((r) => r.json())
            .then((data) => {
                lista.innerHTML = data.html || '';
                const c = data.contadores || {};
                countEls.todos.textContent     = c.todos     || 0;
                countEls.pendente.textContent   = c.pendente  || 0;
                countEls.concluida.textContent = c.concluida || 0;
            })
            .catch(() => {
                lista.innerHTML = '<div class="admin-cot-empty">Erro ao carregar agendamentos.</div>';
            });
    }

    document.querySelectorAll('.status-kpi-card').forEach((card) => {
        card.addEventListener('click', (e) => {
            e.preventDefault();
            document.querySelectorAll('.status-kpi-card').forEach((c) => c.classList.remove('is-active'));
            card.classList.add('is-active');
            statusAtivo = card.dataset.status || '';
            carregar();
        });
    });

    dataFiltro.addEventListener('change', carregar);
    if (corretorFiltro) corretorFiltro.addEventListener('change', carregar);

    // ── Modal: criar/editar ──────────────────────────────────────────────────
    const form       = document.getElementById('form-agendamento');
    const modalTitulo = document.getElementById('modal-agendamento-titulo');
    const btnNovo     = document.getElementById('btn-novo-agendamento');

    // ── "Agendar para": dropdown único vira multi-seleção ─────────────────────
    // O 1º id marcado (ordem de clique) é o corretor principal (para_usuario_id
    // — dono do agendamento, permissão/e-mail/relatório continuam baseados
    // nele); os demais viram corretores_extra (participantes, sem permissão de
    // editar/excluir). Não mexe em custom-select.js (genérico, usado em vários
    // lugares como single-select) — intercepta o clique nas opções só deste
    // dropdown específico, em fase de captura, antes do handler genérico rodar.
    let USUARIOS_VISITA = [];
    try {
        const _uj = document.getElementById('visita-usuarios-json');
        if (_uj) USUARIOS_VISITA = JSON.parse(_uj.textContent || '[]');
    } catch (_) { USUARIOS_VISITA = []; }
    const nomeCorretor = (id) => {
        const u = USUARIOS_VISITA.filter((x) => x.id === id)[0];
        return u ? u.username : '';
    };

    const paraUsuarioWrap  = document.getElementById('para-usuario-select');
    const paraUsuarioHidden = paraUsuarioWrap ? paraUsuarioWrap.querySelector('input[type="hidden"]') : null;
    const paraUsuarioValueEl = paraUsuarioWrap ? paraUsuarioWrap.querySelector('.custom-select-value') : null;
    const principalFixo = paraUsuarioWrap && paraUsuarioWrap.dataset.principalFixo
        ? parseInt(paraUsuarioWrap.dataset.principalFixo, 10) : null;

    // selecionadosPrincipalExtra[0] = principal; o resto = extras.
    let selecionadosPrincipalExtra = principalFixo
        ? [principalFixo]
        : (paraUsuarioHidden && paraUsuarioHidden.value ? [parseInt(paraUsuarioHidden.value, 10)] : []);

    function renderParaUsuarioSelecao() {
        if (!paraUsuarioWrap) return;
        paraUsuarioWrap.querySelectorAll('.custom-select-option').forEach((opt) => {
            const id = parseInt(opt.dataset.value || '0', 10);
            const selecionado = selecionadosPrincipalExtra.indexOf(id) !== -1;
            opt.classList.toggle('is-selected', selecionado);
            let tag = opt.querySelector('.visita-corretor-tag-principal');
            if (id && selecionadosPrincipalExtra[0] === id) {
                if (!tag) {
                    tag = document.createElement('span');
                    tag.className = 'visita-corretor-tag-principal';
                    tag.textContent = 'Principal';
                    opt.appendChild(tag);
                }
            } else if (tag) {
                tag.remove();
            }
        });

        if (paraUsuarioHidden) paraUsuarioHidden.value = selecionadosPrincipalExtra[0] || '';
        if (paraUsuarioValueEl) {
            if (!selecionadosPrincipalExtra.length) {
                paraUsuarioValueEl.textContent = paraUsuarioWrap.dataset.placeholder || 'Selecione...';
                paraUsuarioValueEl.classList.add('is-placeholder');
            } else {
                const nomes = selecionadosPrincipalExtra.map(nomeCorretor).filter(Boolean);
                paraUsuarioValueEl.textContent = nomes.length > 1
                    ? `${nomes[0]} +${nomes.length - 1}`
                    : (nomes[0] || '');
                paraUsuarioValueEl.classList.remove('is-placeholder');
            }
        }
    }

    if (paraUsuarioWrap) {
        // Fase de captura: intercepta ANTES do listener genérico de custom-select.js
        // (que fecharia o dropdown e trocaria o valor como single-select).
        paraUsuarioWrap.addEventListener('click', (e) => {
            const opt = e.target.closest('.custom-select-option');
            if (!opt || opt.classList.contains('is-placeholder')) return;
            e.preventDefault();
            e.stopPropagation();
            e.stopImmediatePropagation();

            const id = parseInt(opt.dataset.value || '0', 10);
            if (!id) return;

            const idx = selecionadosPrincipalExtra.indexOf(id);
            if (idx !== -1) {
                if (id === principalFixo) return; // principal fixo (não-admin) não pode ser removido
                selecionadosPrincipalExtra.splice(idx, 1);
            } else {
                selecionadosPrincipalExtra.push(id);
            }
            renderParaUsuarioSelecao();
        }, true);

        renderParaUsuarioSelecao();
    }

    function resetParaUsuarioSelecao() {
        selecionadosPrincipalExtra = principalFixo ? [principalFixo] : [uidAtual];
        renderParaUsuarioSelecao();
    }

    function setCustomSelectValue(name, value) {
        const hidden = form.querySelector(`input[name="${name}"]`);
        if (!hidden) return;
        const wrapper = hidden.closest('[data-custom-select]');
        if (!wrapper) return;
        const opt = wrapper.querySelector(`.custom-select-option[data-value="${value}"]`);
        if (opt) opt.click();
    }

    function resetCustomSelect(name, valorPadrao) {
        const hidden = form.querySelector(`input[name="${name}"]`);
        if (!hidden) return;
        const wrapper = hidden.closest('[data-custom-select]');
        if (!wrapper) return;
        if (valorPadrao) {
            setCustomSelectValue(name, valorPadrao);
            return;
        }
        const placeholder = wrapper.dataset.placeholder || 'Selecione...';
        const valueEl = wrapper.querySelector('.custom-select-value');
        valueEl.textContent = placeholder;
        valueEl.classList.add('is-placeholder');
        wrapper.querySelectorAll('.custom-select-option').forEach((opt) => opt.classList.toggle('is-selected', opt.dataset.value === ''));
        hidden.value = '';
    }

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

    function setDatePickerValue(elementId, isoValor) {
        const hidden = document.getElementById(elementId);
        if (!hidden) return;
        const wrapper = hidden.closest('[data-date-picker]');
        hidden.value = isoValor || '';
        if (!wrapper) return;
        const valueEl = wrapper.querySelector('.date-picker-value');
        if (isoValor) {
            const [y, m, d] = isoValor.split('-');
            valueEl.textContent = `${d}/${m}/${y}`;
            valueEl.classList.remove('is-placeholder');
        } else {
            valueEl.textContent = wrapper.dataset.placeholder || 'DD/MM/AAAA';
            valueEl.classList.add('is-placeholder');
        }
    }

    function limparModal() {
        form.reset();
        document.getElementById('ag-id').value = '';
        resetParaUsuarioSelecao();
        resetCustomSelect('regiao_id');
        document.getElementById('ag-imobiliaria-id').value = '';
        document.getElementById('ag-link-reuniao').value = '';
        document.getElementById('ag-link-reuniao-wrap').hidden = true;
        setDatePickerValue('ag-data', '');
        setTimePickerValue('ag-hora-inicio', '09:00');
        setTimePickerValue('ag-hora-fim', '10:00');
        modalTitulo.textContent = 'Novo agendamento';
    }

    btnNovo.addEventListener('click', limparModal);

    // ── Região: mostra o campo de link quando a região exige (ex: Online) ─────
    function atualizarLinkReuniaoVisibilidade(regiaoId) {
        const regiao = (window.AGENDAR_REGIOES || []).find((r) => r.id === regiaoId);
        document.getElementById('ag-link-reuniao-wrap').hidden = !regiao || !regiao.requer_link;
    }

    const regiaoHiddenInput = form.querySelector('input[name="regiao_id"]');
    if (regiaoHiddenInput) {
        regiaoHiddenInput.addEventListener('change', () => {
            atualizarLinkReuniaoVisibilidade(regiaoHiddenInput.value);
        });
    }

    // ── Região: modal de cadastro de novos tipos ───────────────────────────────
    function escapeHtml(v) {
        const div = document.createElement('div');
        div.textContent = v;
        return div.innerHTML;
    }

    function iconeX() {
        return '<svg class="icon" viewBox="0 0 256 256" fill="currentColor" aria-hidden="true"><path d="M205.66,194.34a8,8,0,0,1-11.32,11.32L128,139.31,61.66,205.66a8,8,0,0,1-11.32-11.32L116.69,128,50.34,61.66A8,8,0,0,1,61.66,50.34L128,116.69l66.34-66.35a8,8,0,0,1,11.32,11.32L139.31,128Z"/></svg>';
    }

    function abrirModalRegiao() {
        const regioes = window.AGENDAR_REGIOES || [];
        const listaHtml = regioes.length
            ? regioes.map((r) => `
                <li class="visita-regiao-item" data-id="${r.id}">
                    <span class="visita-regiao-item-nome">${escapeHtml(r.nome)}</span>
                    ${r.requer_link ? '<span class="visita-regiao-item-badge">precisa de link</span>' : ''}
                    <button type="button" class="visita-regiao-item-excluir" data-id="${r.id}" data-nome="${escapeHtml(r.nome)}" title="Excluir região">${iconeX()}</button>
                </li>`).join('')
            : '<li class="visita-regiao-item">Nenhuma região cadastrada ainda.</li>';

        mpsAlert.custom(`
            <h3 class="mps-alert-title">Tipos de região</h3>
            <ul class="visita-regiao-lista">${listaHtml}</ul>
            <div class="visita-regiao-form">
                <div class="field">
                    <label class="field-label">Nova região</label>
                    <div class="field-input-wrap">
                        <input type="text" class="field-input" id="nova-regiao-nome" placeholder="Ex: Zona Sul" maxlength="60">
                    </div>
                </div>
                <label class="switch">
                    <input type="checkbox" id="nova-regiao-link">
                    <span class="switch-track"></span>
                    <span class="switch-label">Precisa de link de reunião (ex: Online)</span>
                </label>
            </div>
            <div class="mps-alert-actions">
                <button type="button" class="btn btn-secondary" data-mps-alert-close>Fechar</button>
                <button type="button" class="btn btn-primary" id="btn-regiao-salvar">Adicionar</button>
            </div>
        `, {
            onMount: (card, close) => {
                card.querySelector('[data-mps-alert-close]').addEventListener('click', close);

                card.querySelector('#btn-regiao-salvar').addEventListener('click', async () => {
                    const nomeInput = card.querySelector('#nova-regiao-nome');
                    const linkInput = card.querySelector('#nova-regiao-link');
                    const nome = nomeInput.value.trim();
                    if (!nome) {
                        nomeInput.focus();
                        return;
                    }

                    const response = await fetch('/api/admin/visitas/regiao-criar', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                        body: JSON.stringify({ nome, requer_link: linkInput.checked }),
                    });
                    const data = await response.json().catch(() => ({}));

                    if (response.ok && data.success) {
                        close();
                        await mpsAlert.success('Região cadastrada com sucesso.');
                        window.location.reload();
                    } else {
                        mpsAlert.error(data.message || 'Não foi possível cadastrar a região.');
                    }
                });

                card.querySelectorAll('.visita-regiao-item-excluir').forEach((btn) => {
                    btn.addEventListener('click', async () => {
                        const id   = btn.dataset.id;
                        const nome = btn.dataset.nome;

                        const confirmado = await mpsAlert.confirm({
                            title: 'Excluir região?',
                            message: `Remover "${nome}" da lista de regiões?`,
                            confirmText: 'Excluir',
                            cancelText: 'Cancelar',
                        });
                        if (!confirmado) { abrirModalRegiao(); return; }

                        const response = await fetch('/api/admin/visitas/regiao-excluir', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                            body: JSON.stringify({ id: parseInt(id, 10) }),
                        });
                        const data = await response.json().catch(() => ({}));

                        if (response.ok && data.success) {
                            await mpsAlert.success('Região excluída.');
                            window.location.reload();
                        } else {
                            mpsAlert.error(data.message || 'Não foi possível excluir a região.');
                        }
                    });
                });
            },
        });
    }

    const btnRegiaoCadastrar = document.getElementById('btn-regiao-cadastrar');
    if (btnRegiaoCadastrar) {
        btnRegiaoCadastrar.addEventListener('click', abrirModalRegiao);
    }

    // ── Imobiliária: busca por digitação, com autofill de endereço ────────────
    const imobNomeInput = document.getElementById('ag-imobiliaria-nome');
    const imobIdInput   = document.getElementById('ag-imobiliaria-id');
    const imobSugestoes = document.getElementById('ag-imobiliaria-sugestoes');
    let imobBuscaTimer = null;

    function fecharSugestoesImob() {
        imobSugestoes.innerHTML = '';
        imobSugestoes.hidden = true;
    }

    function preencherEnderecoDaImobiliaria(item) {
        const mapa = {
            ag_cep: item.cep, ag_endereco: item.endereco, ag_numero: item.numero,
            ag_complemento: item.complemento, ag_bairro: item.bairro, ag_cidade: item.cidade, ag_uf: item.uf,
        };
        Object.entries(mapa).forEach(([name, valor]) => {
            const el = form.querySelector(`[name="${name}"]`);
            if (el && valor) el.value = valor;
        });
    }

    if (imobNomeInput) {
        imobNomeInput.addEventListener('input', () => {
            imobIdInput.value = ''; // digitou de novo — só volta a ter id se clicar numa sugestão
            clearTimeout(imobBuscaTimer);
            const termo = imobNomeInput.value.trim();
            if (termo.length < 2) { fecharSugestoesImob(); return; }

            imobBuscaTimer = setTimeout(() => {
                fetch('/api/admin/visitas/imobiliaria-buscar?q=' + encodeURIComponent(termo))
                    .then((r) => r.json())
                    .then((data) => {
                        const itens = data.itens || [];
                        if (!itens.length) { fecharSugestoesImob(); return; }
                        imobSugestoes.innerHTML = itens.map((i, idx) =>
                            `<li data-idx="${idx}">${escapeHtml(i.nome)}</li>`
                        ).join('');
                        imobSugestoes.dataset.itens = JSON.stringify(itens);
                        imobSugestoes.hidden = false;
                    })
                    .catch(() => fecharSugestoesImob());
            }, 300);
        });

        imobSugestoes.addEventListener('click', (e) => {
            const li = e.target.closest('li[data-idx]');
            if (!li) return;
            const itens = JSON.parse(imobSugestoes.dataset.itens || '[]');
            const item = itens[parseInt(li.dataset.idx, 10)];
            if (!item) return;

            imobIdInput.value = item.id;
            imobNomeInput.value = item.nome;
            fecharSugestoesImob();
            preencherEnderecoDaImobiliaria(item);
        });

        document.addEventListener('click', (e) => {
            if (!e.target.closest('#modal-agendamento .visita-imob-field')) fecharSugestoesImob();
        });
    }

    lista.addEventListener('click', async (e) => {
        const btnEditar = e.target.closest('[data-action="agendamento-editar"]');
        if (btnEditar) {
            const card = btnEditar.closest('.admin-cot-row');
            const item = JSON.parse(card.dataset.item);

            limparModal();
            document.getElementById('ag-id').value = item.id;
            selecionadosPrincipalExtra = [item.para_usuario_id, ...((item.corretores_extra || []).map((u) => u.id))]
                .filter((id, idx, arr) => id && arr.indexOf(id) === idx);
            if (principalFixo && selecionadosPrincipalExtra.indexOf(principalFixo) === -1) {
                selecionadosPrincipalExtra.unshift(principalFixo);
            }
            renderParaUsuarioSelecao();
            document.getElementById('ag-titulo').value = item.titulo || '';
            if (item.regiao_id) {
                setCustomSelectValue('regiao_id', String(item.regiao_id));
                atualizarLinkReuniaoVisibilidade(String(item.regiao_id));
            }
            document.getElementById('ag-link-reuniao').value = item.link_reuniao || '';
            document.getElementById('ag-imobiliaria-nome').value = item.imobiliaria_nome || '';
            document.getElementById('ag-imobiliaria-id').value = item.imobiliaria_id || '';
            setDatePickerValue('ag-data', item.data_visita);
            setTimePickerValue('ag-hora-inicio', (item.hora_inicio || '').substring(0, 5));
            setTimePickerValue('ag-hora-fim', (item.hora_fim || '').substring(0, 5));
            document.getElementById('ag-descricao').value = item.descricao || '';
            form.querySelector('[name="ag_endereco"]').value = item.local_visita || '';

            modalTitulo.textContent = 'Editar agendamento';
            document.getElementById('modal-agendamento').hidden = false;
            return;
        }

        const btnDescricao = e.target.closest('[data-action="agendamento-descricao"]');
        if (btnDescricao) {
            const card = btnDescricao.closest('.admin-cot-row');
            const item = JSON.parse(card.dataset.item);
            const descricao = (item.descricao || '').trim();

            mpsAlert.custom(`
                <h3 class="mps-alert-title">Descrição da visita</h3>
                <p class="mps-alert-message mps-alert-message-desc mps-alert-body-left"></p>
                <div class="mps-alert-actions">
                    <button type="button" class="btn btn-primary" data-desc-fechar>Fechar</button>
                </div>
            `, {
                onMount: (cardEl, close) => {
                    // textContent (em vez de interpolar no HTML) evita XSS e
                    // preserva as quebras de linha via CSS white-space: pre-wrap.
                    cardEl.querySelector('.mps-alert-message-desc').textContent =
                        descricao || 'Sem descrição informada.';
                    cardEl.querySelector('[data-desc-fechar]').addEventListener('click', close);
                },
            });
            return;
        }

        const btnExcluir = e.target.closest('[data-action="agendamento-excluir"]');
        if (btnExcluir) {
            const confirmado = await mpsAlert.confirm({
                title: 'Excluir agendamento?',
                message: 'O evento também será removido do Google Agenda, se estiver sincronizado.',
                confirmText: 'Excluir',
                cancelText: 'Cancelar',
            });
            if (!confirmado) return;

            const response = await fetch('/api/admin/visitas-agendar/excluir', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({ id: parseInt(btnExcluir.dataset.id, 10) }),
            });
            const data = await response.json().catch(() => ({}));
            if (response.ok && data.success) {
                mpsAlert.success('Agendamento excluído.');
                carregar();
            } else {
                mpsAlert.error(data.message || 'Não foi possível excluir.');
            }
        }
    });

    // ── CEP (ViaCEP) ──────────────────────────────────────────────────────────
    const cepInput = form.querySelector('[name="ag_cep"]');
    cepInput.addEventListener('blur', async () => {
        const cep = cepInput.value.replace(/\D/g, '');
        if (cep.length !== 8) return;
        try {
            const r = await fetch(`https://viacep.com.br/ws/${cep}/json/`);
            const data = await r.json();
            if (data.erro) return;
            const mapa = { ag_endereco: 'logradouro', ag_bairro: 'bairro', ag_cidade: 'localidade', ag_uf: 'uf' };
            Object.entries(mapa).forEach(([name, viaCepKey]) => {
                const el = form.querySelector(`[name="${name}"]`);
                if (el && data[viaCepKey]) el.value = data[viaCepKey];
            });
        } catch (_) { /* CEP inválido ou serviço fora do ar — segue sem preencher */ }
    });

    // ── Salvar ────────────────────────────────────────────────────────────────
    form.addEventListener('submit', async (e) => {
        e.preventDefault();

        const payload = {
            id: parseInt(document.getElementById('ag-id').value || '0', 10),
            para_usuario_id: selecionadosPrincipalExtra[0] || 0,
            titulo: document.getElementById('ag-titulo').value.trim(),
            regiao_id: parseInt(regiaoHiddenInput ? regiaoHiddenInput.value : '' || '0', 10) || null,
            link_reuniao: document.getElementById('ag-link-reuniao').value.trim(),
            imobiliaria_id: parseInt(imobIdInput.value || '0', 10) || null,
            imobiliaria_nome: imobNomeInput.value.trim(),
            data_visita: document.getElementById('ag-data').value,
            hora_inicio: document.getElementById('ag-hora-inicio').value,
            hora_fim: document.getElementById('ag-hora-fim').value,
            descricao: document.getElementById('ag-descricao').value.trim(),
            cep: form.querySelector('[name="ag_cep"]').value.trim(),
            endereco: form.querySelector('[name="ag_endereco"]').value.trim(),
            numero: form.querySelector('[name="ag_numero"]').value.trim(),
            complemento: form.querySelector('[name="ag_complemento"]').value.trim(),
            bairro: form.querySelector('[name="ag_bairro"]').value.trim(),
            cidade: form.querySelector('[name="ag_cidade"]').value.trim(),
            uf: form.querySelector('[name="ag_uf"]').value.trim(),
            corretores_extra: selecionadosPrincipalExtra.slice(1),
        };

        if (!payload.titulo || !payload.para_usuario_id) {
            mpsAlert.error('Preencha o corretor e o título.');
            return;
        }

        const regiaoSelecionada = (window.AGENDAR_REGIOES || []).find((r) => r.id === String(payload.regiao_id));
        if (regiaoSelecionada && regiaoSelecionada.requer_link && !payload.link_reuniao) {
            mpsAlert.error('Informe o link da reunião para uma região online.');
            return;
        }
        if (!payload.data_visita || !payload.hora_inicio || !payload.hora_fim) {
            mpsAlert.error('Informe data e horários.');
            return;
        }

        const btn = document.getElementById('btn-salvar-agendamento');
        btn.disabled = true;
        mpsAlert.loading('Salvando agendamento...');

        try {
            const response = await fetch('/api/admin/visitas-agendar/salvar', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify(payload),
            });
            const data = await response.json().catch(() => ({}));

            if (response.ok && data.success) {
                await mpsAlert.success('Agendamento salvo com sucesso.');
                document.getElementById('modal-agendamento').hidden = true;
                carregar();
            } else {
                mpsAlert.error(data.message || 'Não foi possível salvar o agendamento.');
            }
        } catch (_) {
            mpsAlert.error('Erro de conexão. Tente novamente.');
        }

        btn.disabled = false;
    });

    // ── Desconectar Google ────────────────────────────────────────────────────
    const btnDesconectar = document.getElementById('btn-google-desconectar');
    if (btnDesconectar) {
        btnDesconectar.addEventListener('click', async () => {
            const confirmado = await mpsAlert.confirm({
                title: 'Desconectar Google Agenda?',
                message: 'Seus próximos agendamentos deixarão de sincronizar automaticamente.',
                confirmText: 'Desconectar',
                cancelText: 'Cancelar',
            });
            if (!confirmado) return;

            const response = await fetch('/api/admin/visitas/google-desconectar', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            });
            const data = await response.json().catch(() => ({}));
            if (response.ok && data.success) {
                mpsAlert.success('Conta Google desconectada.');
                window.location.reload();
            } else {
                mpsAlert.error(data.message || 'Não foi possível desconectar.');
            }
        });
    }

    carregar();
})();
