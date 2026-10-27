(function () {
    'use strict';

    const lista     = document.getElementById('cotacoes-lista');
    const btnLimpar = document.getElementById('btn-limpar');
    const pagInfo     = document.getElementById('pag-info');
    const btnAnterior = document.getElementById('pag-anterior');
    const btnProxima  = document.getElementById('pag-proxima');

    const countEls = {
        todos:       document.getElementById('count-todos'),
        pre_analise: document.getElementById('count-pre-analise'),
        em_analise:  document.getElementById('count-em-analise'),
        aguardando:  document.getElementById('count-aguardando'),
        aprovados:   document.getElementById('count-aprovados'),
        recusados:   document.getElementById('count-recusados'),
        desistencias: document.getElementById('count-desistencias'),
        concluidos:  document.getElementById('count-concluidos'),
    };

    // Hidden input do custom_select de tipo
    const tipoHidden = document.querySelector('[data-custom-select] input[name="f_tipo"]');

    let activeStatus  = '';
    let currentPage   = 1;
    let totalPages    = 1;
    let debounceTimer = null;

    // ── Filtros actuais ──────────────────────────────────────────────────────
    function getFiltros() {
        return {
            status:      activeStatus,
            pretendente: document.getElementById('f-pretendente').value.trim(),
            protocolo:   document.getElementById('f-protocolo').value.trim(),
            tipo:        tipoHidden ? tipoHidden.value : '',
            data_de:     document.getElementById('f-data-de').value,
            data_ate:    document.getElementById('f-data-ate').value,
            page:        currentPage,
        };
    }

    function temFiltroAtivo() {
        const f = getFiltros();
        return f.pretendente || f.protocolo || f.tipo || f.data_de || f.data_ate;
    }

    // ── Carregar via AJAX ────────────────────────────────────────────────────
    function carregar() {
        const params = new URLSearchParams(getFiltros());
        lista.innerHTML = '<div class="admin-cot-empty">Carregando...</div>';
        btnLimpar.style.display = temFiltroAtivo() ? '' : 'none';

        fetch('/api/cotacoes/listar?' + params.toString())
            .then(function (r) { return r.json(); })
            .then(function (data) {
                lista.innerHTML = data.html || '';
                aplicarDestaque();
                var c = data.contadores || {};
                countEls.todos.textContent       = c.todos       || 0;
                countEls.pre_analise.textContent = c.pre_analise || 0;
                countEls.em_analise.textContent  = c.em_analise  || 0;
                countEls.aguardando.textContent  = c.aguardando  || 0;
                countEls.aprovados.textContent   = c.aprovados   || 0;
                countEls.recusados.textContent   = c.recusados   || 0;
                countEls.desistencias.textContent = c.desistencias || 0;
                countEls.concluidos.textContent  = c.concluidos  || 0;

                currentPage = data.page       || 1;
                totalPages  = data.total_pages || 1;
                var total   = data.total || 0;
                var perPage = data.per_page || 15;

                if (total > 0) {
                    var ini = (currentPage - 1) * perPage + 1;
                    var fim = Math.min(total, currentPage * perPage);
                    pagInfo.textContent = 'Mostrando ' + ini + '–' + fim + ' de ' + total;
                } else {
                    pagInfo.textContent = 'Nenhum resultado';
                }
                btnAnterior.disabled = currentPage <= 1;
                btnProxima.disabled  = currentPage >= totalPages;
            })
            .catch(function () {
                lista.innerHTML = '<div class="admin-cot-empty">Erro ao carregar dados.</div>';
            });
    }

    function irParaPagina(p) {
        currentPage = p;
        carregar();
    }

    function carregarDebounce() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function () { irParaPagina(1); }, 350);
    }

    // ── KPI cards de status ──────────────────────────────────────────────────
    document.querySelectorAll('.status-kpi-card').forEach(function (card) {
        card.addEventListener('click', function (e) {
            e.preventDefault();
            document.querySelectorAll('.status-kpi-card').forEach(function (c) {
                c.classList.remove('is-active');
            });
            card.classList.add('is-active');
            activeStatus = card.dataset.status || '';
            irParaPagina(1);
        });
    });

    // ── Inputs com debounce ──────────────────────────────────────────────────
    document.getElementById('f-pretendente').addEventListener('input', carregarDebounce);
    document.getElementById('f-protocolo').addEventListener('input', carregarDebounce);

    // ── Custom select de tipo — escuta change no hidden input ────────────────
    if (tipoHidden) {
        tipoHidden.addEventListener('change', function () { irParaPagina(1); });
    }

    // ── Date pickers — escuta change nos hidden inputs ───────────────────────
    document.getElementById('f-data-de').addEventListener('change', function () { irParaPagina(1); });
    document.getElementById('f-data-ate').addEventListener('change', function () { irParaPagina(1); });

    // ── Paginação — botões anterior / próxima ────────────────────────────────
    btnAnterior.addEventListener('click', function () {
        if (currentPage > 1) irParaPagina(currentPage - 1);
    });
    btnProxima.addEventListener('click', function () {
        if (currentPage < totalPages) irParaPagina(currentPage + 1);
    });

    // ── Limpar filtros ───────────────────────────────────────────────────────
    btnLimpar.addEventListener('click', function () {
        document.getElementById('f-pretendente').value = '';
        document.getElementById('f-protocolo').value   = '';

        // Reset dos date pickers (mesmo padrão do custom select abaixo)
        document.querySelectorAll('[data-date-picker]').forEach(function (dp) {
            var hiddenDate = dp.querySelector('input[type="hidden"]');
            var valueEl    = dp.querySelector('.date-picker-value');
            hiddenDate.value = '';
            valueEl.textContent = dp.dataset.placeholder || 'DD/MM/AAAA';
            valueEl.classList.add('is-placeholder');
        });

        // Reset custom select
        if (tipoHidden) {
            var wrapper = tipoHidden.closest('[data-custom-select]');
            if (wrapper) {
                var placeholder = wrapper.dataset.placeholder || 'Todos';
                wrapper.querySelector('.custom-select-value').textContent = placeholder;
                wrapper.querySelector('.custom-select-value').classList.add('is-placeholder');
                wrapper.querySelectorAll('.custom-select-option').forEach(function (opt) {
                    opt.classList.toggle('is-selected', opt.dataset.value === '');
                });
                tipoHidden.value = '';
            }
        }
        irParaPagina(1);
    });

    // ── Copiar link (biometria do Fiança, link de pagamento do Título) ───────
    //    (linhas são recriadas a cada carregar(), então o listener fica no
    //    container estável, delegando o clique)
    lista.addEventListener('click', function (e) {
        var btnCopiar = e.target.closest('[data-action="copiar-link"]');
        if (!btnCopiar || !btnCopiar.dataset.link) return;
        navigator.clipboard.writeText(btnCopiar.dataset.link)
            .then(function () { mpsAlert.success(btnCopiar.dataset.mensagem || 'Link copiado!'); })
            .catch(function () { mpsAlert.error('Não foi possível copiar o link.'); });
    });

    // ── Filtro vindo por URL (ex: "Ver" de uma cotação no dashboard aponta
    //    pra /cotacoes?protocolo=...) — pré-preenche o campo antes da carga
    //    inicial, pra já abrir a listagem filtrada nessa cotação.
    (function aplicarFiltroDaUrl() {
        var protocoloUrl = new URLSearchParams(window.location.search).get('protocolo');
        if (protocoloUrl) {
            document.getElementById('f-protocolo').value = protocoloUrl;
        }
    })();

    // ── Destaque vindo por URL (ex: popup de pendências do dashboard aponta
    //    pra /cotacoes?destacar=PROTO1,PROTO2) — não filtra a lista (os itens
    //    podem ter status diferentes), só realça as linhas correspondentes.
    var destacarSet = (function () {
        var raw = new URLSearchParams(window.location.search).get('destacar');
        return raw ? raw.split(',').map(decodeURIComponent).filter(Boolean) : null;
    })();

    function aplicarDestaque() {
        if (!destacarSet || !destacarSet.length) return;
        var primeiro = null;
        lista.querySelectorAll('.proto-code').forEach(function (el) {
            if (destacarSet.indexOf(el.textContent.trim()) !== -1) {
                var row = el.closest('.admin-cot-row');
                if (row) {
                    row.classList.add('is-destacado');
                    if (!primeiro) primeiro = row;
                }
            }
        });
        if (primeiro) primeiro.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }

    // ── Carga inicial ────────────────────────────────────────────────────────
    carregar();

    // ── Hover de "status por seguradora" (canal API) ────────────────────────
    // A tabela tem overflow-x:auto pra rolar horizontalmente, o que corta
    // qualquer popup posicionado com position:absolute que ultrapasse a
    // linha. Aqui a gente muda pra position:fixed no hover/foco, calculando
    // a posição real na tela — assim o balão escapa do corte. Delegado no
    // container (as linhas são recriadas via innerHTML a cada carregar()).
    lista.addEventListener('mouseover', function (e) {
        const trigger = e.target.closest('.api-status-hover');
        if (trigger) posicionarPainelApi(trigger);
    });
    lista.addEventListener('focusin', function (e) {
        const trigger = e.target.closest('.api-status-hover');
        if (trigger) posicionarPainelApi(trigger);
    });

    function posicionarPainelApi(trigger) {
        const painel = trigger.querySelector('.api-status-panel');
        if (!painel) return;
        // Acima do (i); sem espaço no topo da tela, abre embaixo. Na
        // horizontal, não deixa sair pela borda da janela.
        const rect    = trigger.getBoundingClientRect();
        const margem  = 8;
        const altura = painel.offsetHeight;
        const largura = painel.offsetWidth;
        const cabeAcima = rect.top - altura - 10 >= margem;
        painel.style.position = 'fixed';
        painel.style.bottom = 'auto';
        painel.style.top = (cabeAcima ? rect.top - altura - 10 : rect.bottom + 10) + 'px';
        painel.style.left = Math.max(margem, Math.min(rect.left - 12, window.innerWidth - largura - margem)) + 'px';
    }
})();
