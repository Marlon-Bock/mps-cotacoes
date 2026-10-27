(function () {
    'use strict';

    const linhas      = document.getElementById('leads-rows');
    const btnLimpar   = document.getElementById('btn-limpar');
    const pagInfo     = document.getElementById('pag-info');
    const btnAnterior = document.getElementById('pag-anterior');
    const btnProxima  = document.getElementById('pag-proxima');

    const countTodos  = document.getElementById('count-todos');

    const seguroHidden = document.querySelector('#f-seguro-wrap input[name="f_seguro"]');

    let currentPage   = 1;
    let totalPages    = 1;
    let sortDir       = 'desc';
    let debounceTimer = null;

    function getFiltros() {
        return {
            seguro:        seguroHidden ? seguroHidden.value : '',
            protocolo:     document.getElementById('f-protocolo').value.trim(),
            busca:         document.getElementById('f-busca').value.trim(),
            data_de:       document.getElementById('f-data-de').value,
            data_ate:      document.getElementById('f-data-ate').value,
        };
    }

    function temFiltroAtivo() {
        const f = getFiltros();
        return f.seguro || f.protocolo || f.busca || f.data_de || f.data_ate;
    }

    // ── Carregar via AJAX ────────────────────────────────────────────────────
    function carregar() {
        const params = new URLSearchParams(Object.assign({}, getFiltros(), {
            page: currentPage, sort_dir: sortDir,
        }));
        linhas.innerHTML = '<div class="admin-cot-empty">Carregando...</div>';
        btnLimpar.style.display = temFiltroAtivo() ? '' : 'none';

        fetch('/api/admin/leads/listar?' + params.toString())
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

                countTodos.textContent = data.total || 0;
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

    // ── Inputs com debounce ──────────────────────────────────────────────────
    document.getElementById('f-protocolo').addEventListener('input', carregarDebounce);
    document.getElementById('f-busca').addEventListener('input', carregarDebounce);
    if (seguroHidden) seguroHidden.addEventListener('change', function () { irParaPagina(1); });
    document.getElementById('f-data-de').addEventListener('change', function () { irParaPagina(1); });
    document.getElementById('f-data-ate').addEventListener('change', function () { irParaPagina(1); });

    // ── Ordenação por data ───────────────────────────────────────────────────
    document.querySelectorAll('.th-sortable').forEach(function (th) {
        th.addEventListener('click', function () {
            sortDir = sortDir === 'asc' ? 'desc' : 'asc';
            th.querySelector('.sort-arrow').textContent = sortDir === 'asc' ? '▲' : '▼';
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

        document.querySelectorAll('[data-date-picker]').forEach(function (dp) {
            var hiddenDate = dp.querySelector('input[type="hidden"]');
            var valueEl    = dp.querySelector('.date-picker-value');
            hiddenDate.value = '';
            valueEl.textContent = dp.dataset.placeholder || 'DD/MM/AAAA';
            valueEl.classList.add('is-placeholder');
        });

        if (seguroHidden) {
            var wrapper = seguroHidden.closest('[data-custom-select]');
            if (wrapper) {
                wrapper.querySelector('.custom-select-value').textContent = wrapper.dataset.placeholder || 'Todos';
                wrapper.querySelector('.custom-select-value').classList.add('is-placeholder');
                wrapper.querySelectorAll('.custom-select-option').forEach(function (opt) {
                    opt.classList.toggle('is-selected', opt.dataset.value === '');
                });
                seguroHidden.value = '';
            }
        }

        irParaPagina(1);
    });

    // ── Carga inicial ────────────────────────────────────────────────────────
    carregar();
})();
