/**
 * Chamados (admin) — admin-chamados-listar.js
 * Filtros (status, imobiliária, atendente, busca) + paginação — tudo via
 * AJAX, sem navegar nem alterar a URL de /admin/chamados.
 */
(function () {
    'use strict';

    const linhas     = document.getElementById('chamados-rows');
    const pagInfo     = document.getElementById('pag-info');
    const btnAnterior = document.getElementById('pag-anterior');
    const btnProxima  = document.getElementById('pag-proxima');
    const buscaInput  = document.getElementById('f-assunto');
    const btnLimpar   = document.getElementById('btn-limpar');

    const countEls = {
        todos:           document.getElementById('count-total'),
        aguardando_mps:  document.getElementById('count-aguardando-mps'),
        aguardando_imob: document.getElementById('count-aguardando-imob'),
        encerrado:       document.getElementById('count-encerrado'),
    };

    const imobiliariaHidden = document.querySelector('#f-imobiliaria-wrap input[name="f_imobiliaria"]');
    const atendenteHidden   = document.querySelector('#f-atendente-wrap input[name="f_atendente"]');

    let activeStatus  = '';
    let currentPage   = 1;
    let totalPages    = 1;
    let debounceTimer = null;

    function getFiltros() {
        return {
            status_rapido:  activeStatus,
            imobiliaria_id: imobiliariaHidden ? imobiliariaHidden.value : '',
            atendente_id:   atendenteHidden ? atendenteHidden.value : '',
            busca:          buscaInput.value.trim(),
        };
    }

    function temFiltroAtivo() {
        const f = getFiltros();
        return !!(f.status_rapido || f.imobiliaria_id || f.atendente_id || f.busca);
    }

    function carregar() {
        const params = new URLSearchParams(Object.assign({}, getFiltros(), { page: currentPage }));

        linhas.innerHTML = '<div class="admin-cot-empty">Carregando...</div>';
        btnLimpar.style.display = temFiltroAtivo() ? '' : 'none';

        fetch('/api/admin/chamados/listar?' + params.toString())
            .then((r) => r.json())
            .then((data) => {
                linhas.innerHTML = data.html || '';

                totalPages  = data.total_pages || 1;
                currentPage = data.page || 1;
                const inicio = data.total > 0 ? ((currentPage - 1) * data.per_page) + 1 : 0;
                const fim    = Math.min(currentPage * data.per_page, data.total || 0);
                pagInfo.textContent = data.total > 0
                    ? `Mostrando ${inicio}–${fim} de ${data.total}`
                    : 'Nenhum resultado';
                btnAnterior.disabled = currentPage <= 1;
                btnProxima.disabled  = currentPage >= totalPages;

                const c = data.contadores || {};
                countEls.todos.textContent           = c.todos           || 0;
                countEls.aguardando_mps.textContent  = c.aguardando_mps  || 0;
                countEls.aguardando_imob.textContent = c.aguardando_imob || 0;
                countEls.encerrado.textContent        = c.encerrado       || 0;
            })
            .catch(() => {
                linhas.innerHTML = '<div class="admin-cot-empty">Erro ao carregar chamados.</div>';
            });
    }

    function irParaPagina(p) {
        currentPage = p;
        carregar();
    }

    function carregarDebounce() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(() => irParaPagina(1), 350);
    }

    document.querySelectorAll('.status-kpi-card').forEach((card) => {
        card.addEventListener('click', (e) => {
            e.preventDefault();
            document.querySelectorAll('.status-kpi-card').forEach((c) => c.classList.remove('is-active'));
            card.classList.add('is-active');
            activeStatus = card.dataset.status || '';
            irParaPagina(1);
        });
    });

    buscaInput.addEventListener('input', carregarDebounce);
    if (imobiliariaHidden) imobiliariaHidden.addEventListener('change', () => irParaPagina(1));
    if (atendenteHidden)   atendenteHidden.addEventListener('change', () => irParaPagina(1));
    btnAnterior.addEventListener('click', () => { if (currentPage > 1) irParaPagina(currentPage - 1); });
    btnProxima.addEventListener('click', () => { if (currentPage < totalPages) irParaPagina(currentPage + 1); });

    btnLimpar.addEventListener('click', () => {
        buscaInput.value = '';

        [imobiliariaHidden, atendenteHidden].forEach((hidden) => {
            if (!hidden) return;
            const wrapper = hidden.closest('[data-custom-select]');
            if (wrapper) {
                const placeholder = wrapper.dataset.placeholder || 'Todos';
                const valueEl = wrapper.querySelector('.custom-select-value');
                valueEl.textContent = placeholder;
                valueEl.classList.add('is-placeholder');
                wrapper.querySelectorAll('.custom-select-option').forEach((opt) => {
                    opt.classList.toggle('is-selected', opt.dataset.value === '');
                });
            }
            hidden.value = '';
        });

        document.querySelectorAll('.status-kpi-card').forEach((c) => c.classList.remove('is-active'));
        document.querySelector('.status-kpi-card[data-status=""]').classList.add('is-active');
        activeStatus = '';

        irParaPagina(1);
    });

    carregar();
})();
