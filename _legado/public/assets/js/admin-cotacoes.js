(function () {
    'use strict';

    const linhas     = document.getElementById('cotacoes-rows');
    const btnLimpar  = document.getElementById('btn-limpar');
    const pagInfo    = document.getElementById('pag-info');
    const btnAnterior = document.getElementById('pag-anterior');
    const btnProxima  = document.getElementById('pag-proxima');

    // Contadores dos chips: <span data-count="chave"> ← contadores[chave] da listagem.
    const countEls = document.querySelectorAll('[data-count]');

    const produtoHidden  = document.querySelector('#f-produto-wrap input[name="f_produto"]');
    const corretorHidden = document.querySelector('#f-corretor-wrap input[name="f_corretor"]');

    // Filtro do chip ativo — ou o que veio no link (?status=) sem chip próprio
    // (ex: links antigos com os filtros da API — api_cotar, api_aceitas...).
    let activeStatus  = (document.querySelector('.status-kpi-card.is-active') || {}).dataset?.status
        || new URLSearchParams(window.location.search).get('status') || '';
    let currentPage   = 1;
    let totalPages    = 1;
    let sortBy        = 'atualizacao';
    let sortDir       = 'desc';
    let debounceTimer = null;

    function getFiltros() {
        return {
            status_rapido: activeStatus,
            protocolo:     document.getElementById('f-protocolo').value.trim(),
            busca:         document.getElementById('f-busca').value.trim(),
            produto:       produtoHidden  ? produtoHidden.value  : '',
            corretor_id:   corretorHidden ? corretorHidden.value : '',
            data_de:       document.getElementById('f-data-de').value,
            data_ate:      document.getElementById('f-data-ate').value,
        };
    }

    function temFiltroAtivo() {
        const f = getFiltros();
        return f.protocolo || f.busca || f.produto || f.corretor_id || f.data_de || f.data_ate;
    }

    // ── Carregar via AJAX ────────────────────────────────────────────────────
    function carregar() {
        const params = new URLSearchParams(Object.assign({}, getFiltros(), {
            page: currentPage, sort_by: sortBy, sort_dir: sortDir,
        }));
        linhas.innerHTML = '<div class="admin-cot-empty">Carregando...</div>';
        btnLimpar.style.display = temFiltroAtivo() ? '' : 'none';

        fetch('/api/admin/cotacoes/listar?' + params.toString())
            .then(function (r) { return r.json(); })
            .then(function (data) {
                linhas.innerHTML = data.html || '';

                totalPages = data.total_pages || 1;
                currentPage = data.page || 1;
                const inicio = data.total > 0 ? ((currentPage - 1) * data.per_page) + 1 : 0;
                const fim    = Math.min(currentPage * data.per_page, data.total || 0);
                pagInfo.textContent = data.total > 0
                    ? `Mostrando ${inicio}–${fim} de ${data.total}`
                    : 'Nenhum resultado';
                btnAnterior.disabled = currentPage <= 1;
                btnProxima.disabled  = currentPage >= totalPages;

                const c = data.contadores || {};
                countEls.forEach(el => { el.textContent = c[el.dataset.count] || 0; });
            })
            .catch(function () {
                linhas.innerHTML = '<div class="admin-cot-empty">Erro ao carregar dados.</div>';
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
            document.querySelectorAll('.status-kpi-card').forEach(function (c) { c.classList.remove('is-active'); });
            card.classList.add('is-active');
            activeStatus = card.dataset.status || '';
            irParaPagina(1);
        });
    });

    // ── Inputs com debounce ──────────────────────────────────────────────────
    document.getElementById('f-protocolo').addEventListener('input', carregarDebounce);
    document.getElementById('f-busca').addEventListener('input', carregarDebounce);

    if (produtoHidden)  produtoHidden.addEventListener('change', function () { irParaPagina(1); });
    if (corretorHidden) corretorHidden.addEventListener('change', function () { irParaPagina(1); });

    // ── Date pickers — escuta change nos hidden inputs ───────────────────────
    document.getElementById('f-data-de').addEventListener('change', function () { irParaPagina(1); });
    document.getElementById('f-data-ate').addEventListener('change', function () { irParaPagina(1); });

    // ── Ordenação por coluna ─────────────────────────────────────────────────
    document.querySelectorAll('.th-sortable').forEach(function (th) {
        th.addEventListener('click', function () {
            const campo = th.dataset.sort;
            if (sortBy === campo) {
                sortDir = sortDir === 'asc' ? 'desc' : 'asc';
            } else {
                sortBy = campo;
                sortDir = 'desc';
            }
            document.querySelectorAll('.th-sortable .sort-arrow').forEach(function (a) { a.remove(); });
            const arrow = document.createElement('span');
            arrow.className = 'sort-arrow';
            arrow.textContent = sortDir === 'asc' ? '▲' : '▼';
            th.appendChild(arrow);
            irParaPagina(1);
        });
    });

    // ── Paginação ────────────────────────────────────────────────────────────
    btnAnterior.addEventListener('click', function () { if (currentPage > 1) irParaPagina(currentPage - 1); });
    btnProxima.addEventListener('click', function () { if (currentPage < totalPages) irParaPagina(currentPage + 1); });

    // ── Limpar filtros ───────────────────────────────────────────────────────
    btnLimpar.addEventListener('click', function () {
        document.getElementById('f-protocolo').value = '';
        document.getElementById('f-busca').value     = '';

        // Reset dos date pickers (mesmo padrão do custom select abaixo)
        document.querySelectorAll('[data-date-picker]').forEach(function (dp) {
            var hiddenDate = dp.querySelector('input[type="hidden"]');
            var valueEl    = dp.querySelector('.date-picker-value');
            hiddenDate.value = '';
            valueEl.textContent = dp.dataset.placeholder || 'DD/MM/AAAA';
            valueEl.classList.add('is-placeholder');
        });

        [produtoHidden, corretorHidden].forEach(function (hidden) {
            if (!hidden) return;
            var wrapper = hidden.closest('[data-custom-select]');
            if (wrapper) {
                var placeholder = wrapper.dataset.placeholder || 'Todos';
                wrapper.querySelector('.custom-select-value').textContent = placeholder;
                wrapper.querySelector('.custom-select-value').classList.add('is-placeholder');
                wrapper.querySelectorAll('.custom-select-option').forEach(function (opt) {
                    opt.classList.toggle('is-selected', opt.dataset.value === '');
                });
                hidden.value = '';
            }
        });

        document.querySelectorAll('.status-kpi-card').forEach(function (c) { c.classList.remove('is-active'); });
        document.querySelector('.status-kpi-card[data-status=""]').classList.add('is-active');
        activeStatus = '';

        irParaPagina(1);
    });

    // ── Carga inicial ────────────────────────────────────────────────────────
    carregar();

    // ── Hover do (i) ao lado do Status (Fiança API encaminhada pra MPS) ─────
    // Mesma técnica da listagem da imobiliária (cotacoes.js): a tabela tem
    // overflow-x:auto e a célula overflow:hidden, que cortam o balão com
    // position:absolute — no hover/foco vira position:fixed na posição real
    // da tela. Delegado no container (linhas recriadas a cada carregar()).
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

    ['mouseover', 'focusin'].forEach(evento => {
        linhas?.addEventListener(evento, (e) => {
            const trigger = e.target.closest('.api-status-hover');
            if (trigger) posicionarPainelApi(trigger);
        });
    });
})();
