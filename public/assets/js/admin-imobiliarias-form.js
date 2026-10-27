/**
 * Criar/Editar Imobiliária — autofill de endereço via ViaCEP, toggle
 * "cliente Sami" com busca no cadastro institucional (autofill de
 * nome/sistema/endereço), e máscara das comissões por tipo de seguro
 * (mesmo padrão do Relatório/Histórico de Visitas).
 */
(function () {
    'use strict';

    const csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

    // ── Erro geral de validação/salvamento vindo do POST anterior ─────────────
    const erroGeral = (document.querySelector('.app-content[data-erro-geral]') || {}).dataset || {};
    if (erroGeral.erroGeral) {
        mpsAlert.error(erroGeral.erroGeral);
    }

    // ── Evita duplo envio (clique duplo/repetido no Salvar) ────────────────────
    document.querySelectorAll('form').forEach((form) => {
        form.addEventListener('submit', () => {
            if (form.dataset.enviando === '1') return;
            form.dataset.enviando = '1';
            form.querySelectorAll('button[type="submit"]').forEach((btn) => { btn.disabled = true; });
        });
    });

    // ── CEP (ViaCEP) — busca assim que os 8 dígitos são digitados, sem
    // precisar sair do campo; o blur cobre o caso de colar o CEP inteiro. ──
    const cepInput = document.getElementById('cep');
    if (cepInput) {
        let ultimoCepBuscado = '';

        async function buscarEnderecoPorCep() {
            const cep = cepInput.value.replace(/\D/g, '');
            if (cep.length !== 8 || cep === ultimoCepBuscado) return;
            ultimoCepBuscado = cep;

            try {
                const r = await fetch(`https://viacep.com.br/ws/${cep}/json/`);
                const data = await r.json();
                if (data.erro) return;

                const mapa = { endereco: 'logradouro', bairro: 'bairro', cidade: 'localidade', uf: 'uf' };
                Object.entries(mapa).forEach(([id, viaCepKey]) => {
                    const el = document.getElementById(id);
                    if (el && data[viaCepKey]) el.value = data[viaCepKey];
                });
            } catch (_) { /* CEP inválido ou serviço fora do ar — segue sem preencher */ }
        }

        cepInput.addEventListener('input', buscarEnderecoPorCep);
        cepInput.addEventListener('blur', buscarEnderecoPorCep);
    }

    function escapeHtml(v) {
        const div = document.createElement('div');
        div.textContent = v;
        return div.innerHTML;
    }

    // ── Máscaras de input (mesmo padrão das telas de Cotação) ─────────────────
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

    function maskCep(v) {
        const n = v.replace(/\D/g, '').slice(0, 8);
        return n.replace(/(\d{5})(\d{1,3})$/, '$1-$2');
    }

    document.querySelectorAll('[data-mask="cpfcnpj"]').forEach((el) => applyMask(el, maskCpfCnpj));
    document.querySelectorAll('[data-mask="phone"]').forEach((el) => applyMask(el, maskPhone));
    document.querySelectorAll('[data-mask="cep"]').forEach((el) => applyMask(el, maskCep));

    // ── Embed: toggle mostra/esconde o domínio autorizado ─────────────────────
    const toggleEmbed = document.getElementById('usa-embed');
    const blocoEmbed  = document.getElementById('bloco-embed-dominio');

    if (toggleEmbed && blocoEmbed) {
        toggleEmbed.addEventListener('change', () => {
            blocoEmbed.hidden = !toggleEmbed.checked;
        });
    }

    // ── Cliente Sami: toggle + busca no cadastro institucional ────────────────
    const toggleSami = document.getElementById('eh-cliente-sami');
    const blocoBusca = document.getElementById('bloco-busca-institucional');

    if (toggleSami && blocoBusca) {
        const blocoSistema = document.getElementById('bloco-institucional-sistema');

        toggleSami.addEventListener('change', () => {
            blocoBusca.hidden = !toggleSami.checked;
            if (blocoSistema) blocoSistema.hidden = !toggleSami.checked;
            const blocoUrl = document.getElementById('bloco-samiloca-url');
            if (toggleSami.checked) {
                atualizarBlocoSami();
            } else if (blocoUrl) {
                blocoUrl.hidden = true;
            }
        });

        const buscaInput   = document.getElementById('institucional-busca');
        const sugestoes    = document.getElementById('institucional-sugestoes');
        const siglaHidden  = document.getElementById('institucional-sigla');
        const sistemaHidden = document.getElementById('institucional-sistema');
        const sistemaToggle = document.getElementById('institucional-sistema-toggle');
        const labelErp      = document.querySelector('[data-sistema-label="erp"]');
        const labelSamiloca = document.querySelector('[data-sistema-label="samiloca"]');
        const siteInput   = document.getElementById('institucional-site');

        const chip         = document.getElementById('institucional-chip');
        const chipSistema  = document.getElementById('institucional-chip-sistema');
        const chipTexto    = document.getElementById('institucional-chip-texto');
        const chipLimpar   = document.getElementById('institucional-chip-limpar');
        const nomeInput    = document.getElementById('nome');

        // Só existem 2 sistemas na prática — ERP direto ou Samiloca. Aceita
        // qualquer valor que a busca institucional devolva (ERP, ERP++,
        // IMOBIFREE etc.) e reduz pra um dos dois lados do toggle.
        function setSistema(valor) {
            const isSamiloca = (valor || '').trim().toUpperCase() === 'SAMILOCA';
            if (sistemaHidden) sistemaHidden.value = isSamiloca ? 'SAMILOCA' : 'ERP++';
            if (sistemaToggle) sistemaToggle.checked = isSamiloca;
            labelErp?.classList.toggle('is-active', !isSamiloca);
            labelSamiloca?.classList.toggle('is-active', isSamiloca);
            if (chipSistema) chipSistema.textContent = sistemaHidden ? sistemaHidden.value : '';
            atualizarBlocoSami();
        }

        // Admin pode trocar o sistema manualmente a qualquer momento (inclusive
        // depois da busca já ter preenchido algo).
        sistemaToggle?.addEventListener('change', () => {
            setSistema(sistemaToggle.checked ? 'SAMILOCA' : 'ERP++');
        });

        // Bloco da URL da API SAMILOCA (só existe/aparece para esse sistema).
        const blocoSami    = document.getElementById('bloco-samiloca-url');
        const btnDetectar  = document.getElementById('samiloca-detectar');
        const btnTestar    = document.getElementById('samiloca-testar');
        const statusSami   = document.getElementById('samiloca-status');
        const apiUserInput = document.getElementById('samiloca-api-user');
        const apiPassInput = document.getElementById('samiloca-api-pass');

        const AJUDA_SAMI = 'Usada na busca de imóvel por código na cotação de fiança.';

        let buscaTimer = null;

        function ehSamiloca() {
            return (sistemaHidden.value || '').trim().toUpperCase() === 'SAMILOCA';
        }

        function atualizarBlocoSami() {
            if (!blocoSami) return;
            blocoSami.hidden = !ehSamiloca();
            if (blocoSami.hidden) return;

            if (!siteInput.value.trim()) {
                marcarStatus('aviso', 'Sem a URL a busca de imóvel por código não funciona. Use Detectar ou informe manualmente.');
            } else {
                marcarStatus('', AJUDA_SAMI);
            }
        }

        function marcarStatus(tipo, texto) {
            if (!statusSami) return;
            statusSami.textContent = texto;
            statusSami.classList.remove('is-ok', 'is-erro', 'is-aviso');
            if (tipo) statusSami.classList.add('is-' + tipo);
        }

        function pedirSamiloca(payload, textoOcupado) {
            if (!statusSami) return;
            [btnDetectar, btnTestar].forEach((b) => { if (b) b.disabled = true; });
            marcarStatus('', textoOcupado);

            fetch('/api/admin/imobiliarias/testar-samiloca', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(payload),
            })
                .then((r) => r.json())
                .then((d) => {
                    if (d.ok) {
                        siteInput.value = d.url;
                        marcarStatus('ok', 'API respondeu em ' + d.url);
                    } else {
                        marcarStatus('erro', d.erro || 'Não foi possível validar a URL.');
                    }
                })
                .catch(() => marcarStatus('erro', 'Erro na requisição.'))
                .finally(() => { [btnDetectar, btnTestar].forEach((b) => { if (b) b.disabled = false; }); });
        }

        if (btnDetectar) {
            btnDetectar.addEventListener('click', () => {
                pedirSamiloca(
                    {
                        nome: (nomeInput && nomeInput.value) || '', site: siteInput.value,
                        api_user: (apiUserInput && apiUserInput.value) || '', api_pass: (apiPassInput && apiPassInput.value) || '',
                    },
                    'Procurando a URL da API nos padrões conhecidos...'
                );
            });
        }

        if (btnTestar) {
            btnTestar.addEventListener('click', () => {
                const url = siteInput.value.trim();
                if (!url) { marcarStatus('erro', 'Informe a URL antes de testar.'); return; }
                pedirSamiloca({
                    url: url,
                    api_user: (apiUserInput && apiUserInput.value) || '', api_pass: (apiPassInput && apiPassInput.value) || '',
                }, 'Testando ' + url + '...');
            });
        }

        if (blocoSami) {
            siteInput.addEventListener('input', () => marcarStatus('', AJUDA_SAMI));
            atualizarBlocoSami();
        }

        function fecharSugestoes() {
            sugestoes.innerHTML = '';
            sugestoes.hidden = true;
        }

        function mostrarChip(sigla, nome, sistema) {
            chipSistema.textContent = sistema;
            chipTexto.textContent = `${nome} (${sigla})`;
            chip.hidden = false;
        }

        buscaInput.addEventListener('input', () => {
            clearTimeout(buscaTimer);
            const termo = buscaInput.value.trim();
            if (termo.length < 2) { fecharSugestoes(); return; }

            buscaTimer = setTimeout(() => {
                fetch('/api/admin/imobiliarias/buscar-institucional?q=' + encodeURIComponent(termo))
                    .then((r) => r.json())
                    .then((data) => {
                        const itens = data.itens || [];
                        if (!itens.length) { fecharSugestoes(); return; }
                        sugestoes.innerHTML = itens.map((i, idx) => `
                            <li data-idx="${idx}">
                                ${escapeHtml(i.nome)} <span class="admin-cot-cell-vazio">${escapeHtml(i.sigla)} · ${escapeHtml(i.sistema)}</span>
                                ${i.endereco ? `<span class="imob-institucional-sugestao-endereco">${escapeHtml(i.endereco)}${i.cidade ? ', ' + escapeHtml(i.cidade) + '/' + escapeHtml(i.uf) : ''}</span>` : ''}
                            </li>
                        `).join('');
                        sugestoes.dataset.itens = JSON.stringify(itens);
                        sugestoes.hidden = false;
                    })
                    .catch(() => fecharSugestoes());
            }, 300);
        });

        sugestoes.addEventListener('click', (e) => {
            const li = e.target.closest('li[data-idx]');
            if (!li) return;
            const itens = JSON.parse(sugestoes.dataset.itens || '[]');
            const item = itens[parseInt(li.dataset.idx, 10)];
            if (!item) return;

            siglaHidden.value = item.sigla;
            setSistema(item.sistema);
            siteInput.value = item.site || '';
            buscaInput.value = '';
            fecharSugestoes();
            mostrarChip(item.sigla, item.nome, item.sistema);

            if (nomeInput && !nomeInput.value.trim()) nomeInput.value = item.nome;

            const mapaAutofill = {
                cep: item.cep, endereco: item.endereco, bairro: item.bairro, cidade: item.cidade, uf: item.uf,
                cnpj: item.cnpj, telefone: item.fone, email: item.email,
            };
            Object.entries(mapaAutofill).forEach(([id, valor]) => {
                const el = document.getElementById(id);
                if (!el || !valor) return;
                el.value = id === 'cnpj' ? maskCpfCnpj(valor) : (id === 'telefone' ? maskPhone(valor) : valor);
            });

            const adminEmailInput = document.getElementById('admin_email');
            if (adminEmailInput && item.email) adminEmailInput.value = item.email;

            atualizarBlocoSami();
        });

        document.addEventListener('click', (e) => {
            if (!e.target.closest('#bloco-busca-institucional')) fecharSugestoes();
        });

        chipLimpar.addEventListener('click', () => {
            siglaHidden.value = '';
            siteInput.value = '';
            chip.hidden = true;
            // O toggle Sistema é independente da busca — limpar a busca não
            // mexe na escolha de SAMI ERP / SAMILOCA já feita.
        });
    }

    // ── Comissões por tipo de seguro: toggle habilita/esconde o input ─────────
    document.querySelectorAll('.imob-seguro-check').forEach((chk) => {
        chk.addEventListener('change', () => {
            const controles = document.querySelector(`.imob-comissao-controles[data-tipo="${chk.dataset.tipo}"]`);
            if (controles) controles.hidden = !chk.checked;
        });
    });

    // ── Canais de cotação: canal Segimob na Fiança mostra o estipulante ──────
    // (código da imobiliária na Segimob — ver _canais.blade.php). Os outros
    // canais não usam o campo: esconde.
    const canalFianca = document.querySelector('[data-canal-produto="fianca"] input[type="hidden"]');
    const blocoEstip  = document.getElementById('bloco-estipulante');
    if (canalFianca && blocoEstip) {
        const sincronizarCanal = () => { blocoEstip.hidden = canalFianca.value !== 'segimob'; };
        canalFianca.addEventListener('change', sincronizarCanal);
        sincronizarCanal();
    }

    // ── Segimob: imobiliárias com fiança → código (estipulante) ──────────────
    // Ao clicar no campo, carrega (uma vez) a lista das imobiliárias com
    // fiança habilitada — sem as que já estão em outro cadastro — e digitar
    // filtra (sem acento/maiúscula). Escolher grava o código no hidden.
    const estBusca = document.getElementById('estipulante-busca');
    if (estBusca) {
        const bloco      = document.getElementById('bloco-estipulante');
        const estHidden  = document.getElementById('segimob_estipulante');
        const estLista   = document.getElementById('estipulante-sugestoes');
        const estChip    = document.getElementById('estipulante-chip');
        const estTexto   = document.getElementById('estipulante-chip-texto');
        const estStatus  = document.getElementById('estipulante-status');
        const estLimpar  = document.getElementById('estipulante-chip-limpar');
        const textoPadrao = estStatus.textContent;
        const esc  = (t) => String(t).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
        const norm = (t) => String(t).normalize('NFD').replace(/[\u0300-\u036f]/g, '').toLowerCase();
        let todas = null;   // lista da API (carregada no 1º clique)
        let carregando = null;

        const fechar = () => { estLista.innerHTML = ''; estLista.hidden = true; };

        function carregar() {
            if (todas) return Promise.resolve(todas);
            if (carregando) return carregando;
            estStatus.textContent = 'Carregando imobiliárias...';
            carregando = fetch('/api/admin/imobiliarias/estipulante?imobiliaria_id=' + (bloco.dataset.imobiliariaId || 0))
                .then((r) => r.json())
                .then((d) => {
                    if (!d.success) throw new Error(d.message || 'Erro');
                    todas = d.itens || [];
                    estStatus.textContent = textoPadrao;
                    return todas;
                })
                .catch((e) => {
                    estStatus.textContent = 'Não foi possível carregar a lista agora. Tente de novo em instantes.';
                    carregando = null;
                    throw e;
                });
            return carregando;
        }

        function mostrar() {
            carregar().then((lista) => {
                const termo = norm(estBusca.value.trim());
                const itens = termo ? lista.filter((i) => norm(i.nome).includes(termo) || String(i.codigo).includes(termo)) : lista;
                estLista.innerHTML = itens.length
                    ? itens.map((i) => `<li data-codigo="${i.codigo}" data-nome="${esc(i.nome)}"${String(i.codigo) === estHidden.value ? ' class="is-atual"' : ''}>${esc(i.nome)} <span class="admin-cot-cell-vazio">código ${i.codigo}</span></li>`).join('')
                    : '<li class="is-vazio">Nenhuma imobiliária encontrada.</li>';
                estLista.hidden = false;
            }).catch(() => fechar());
        }

        estBusca.addEventListener('focus', mostrar);
        estBusca.addEventListener('click', mostrar);
        estBusca.addEventListener('input', mostrar);

        estLista.addEventListener('mousedown', (e) => e.preventDefault()); // não perde o foco antes do clique
        estLista.addEventListener('click', (e) => {
            const li = e.target.closest('li[data-codigo]');
            if (!li) return;
            estHidden.value = li.dataset.codigo;
            estTexto.textContent = `${li.dataset.nome} · código ${li.dataset.codigo}`;
            estChip.hidden = false;
            estBusca.value = '';
            estBusca.blur();
            fechar();
        });

        estLimpar.addEventListener('click', () => {
            estHidden.value = '';
            estChip.hidden = true;
        });

        document.addEventListener('click', (e) => {
            if (!e.target.closest('#bloco-estipulante')) fechar();
        });
    }

    // ── Comissões por tipo de seguro (% ou R$) ─────────────────────────────────
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

    function comissaoModoAtual(row) {
        const radio = row.querySelector('input[type="radio"]:checked');
        return radio ? radio.value : 'percentual';
    }

    function aplicarModoVisual(row, modo) {
        const prefixo = row.querySelector('.field-input-prefix');
        const sufixo  = row.querySelector('.field-input-suffix');
        const input   = row.querySelector('.imob-comissao-valor');
        const isValor = modo === 'valor';
        if (prefixo) prefixo.hidden = !isValor;
        if (sufixo)  sufixo.hidden  = isValor;
        input.classList.toggle('has-prefix', isValor);
        input.classList.toggle('has-suffix', !isValor);
    }

    document.querySelectorAll('.imob-comissao-row').forEach((row) => {
        const input = row.querySelector('.imob-comissao-valor');
        if (!input) return;

        row.querySelectorAll('input[type="radio"]').forEach((r) => {
            r.addEventListener('change', () => {
                aplicarModoVisual(row, r.value);
                input.value = '';
            });
        });

        input.addEventListener('input', function () {
            const isValor = comissaoModoAtual(row) === 'valor';
            const start  = this.selectionStart;
            const before = this.value.length;
            this.value   = isValor ? maskMoeda(this.value) : maskPercentual(this.value);
            const diff   = this.value.length - before;
            this.setSelectionRange(start + diff, start + diff);
        });
    });

    // ── Região: modal de cadastro de novos tipos (mesmo padrão do Agendar Visita) ──
    function iconeX() {
        return '<svg class="icon" viewBox="0 0 256 256" fill="currentColor" aria-hidden="true"><path d="M205.66,194.34a8,8,0,0,1-11.32,11.32L128,139.31,61.66,205.66a8,8,0,0,1-11.32-11.32L116.69,128,50.34,61.66A8,8,0,0,1,61.66,50.34L128,116.69l66.34-66.35a8,8,0,0,1,11.32,11.32L139.31,128Z"/></svg>';
    }

    const btnRegiaoCadastrar = document.getElementById('btn-regiao-cadastrar');
    if (btnRegiaoCadastrar) {
        function abrirModalRegiao() {
            fetch('/api/admin/visitas/regiao-listar')
                .then((r) => r.json())
                .then((data) => renderModalRegiao(data.itens || []))
                .catch(() => renderModalRegiao([]));
        }

        function renderModalRegiao(regioes) {
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

        btnRegiaoCadastrar.addEventListener('click', abrirModalRegiao);
    }
})();
