/**
 * Atendimentos do chat do FAQ (admin) — admin-faq-atendimentos-listar.js
 * Filtros (status, imobiliária, busca) + paginação — tudo via AJAX, sem
 * navegar nem alterar a URL de /admin/faq/atendimentos.
 */
(function () {
    'use strict';

    const linhas      = document.getElementById('atendimentos-rows');
    const pagInfo      = document.getElementById('pag-info');
    const btnAnterior  = document.getElementById('pag-anterior');
    const btnProxima   = document.getElementById('pag-proxima');
    const buscaInput   = document.getElementById('f-busca');
    const btnLimpar    = document.getElementById('btn-limpar');

    const countEls = {
        todos:          document.getElementById('count-total'),
        em_andamento:   document.getElementById('count-em-andamento'),
        concluido:      document.getElementById('count-concluido'),
        chamado_aberto: document.getElementById('count-chamado-aberto'),
    };

    const imobiliariaHidden = document.querySelector('#f-imobiliaria-wrap input[name="f_imobiliaria"]');

    let activeStatus  = '';
    let currentPage   = 1;
    let totalPages    = 1;
    let debounceTimer = null;

    function getFiltros() {
        return {
            status_rapido:  activeStatus,
            imobiliaria_id: imobiliariaHidden ? imobiliariaHidden.value : '',
            busca:          buscaInput.value.trim(),
        };
    }

    function temFiltroAtivo() {
        const f = getFiltros();
        return !!(f.status_rapido || f.imobiliaria_id || f.busca);
    }

    function carregar() {
        const params = new URLSearchParams(Object.assign({}, getFiltros(), { page: currentPage }));

        linhas.innerHTML = '<div class="admin-cot-empty">Carregando...</div>';
        btnLimpar.hidden = !temFiltroAtivo();

        fetch('/api/admin/faq-atendimentos/listar?' + params.toString())
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
                countEls.todos.textContent          = c.todos          || 0;
                countEls.em_andamento.textContent    = c.em_andamento   || 0;
                countEls.concluido.textContent       = c.concluido      || 0;
                countEls.chamado_aberto.textContent  = c.chamado_aberto || 0;
            })
            .catch(() => {
                linhas.innerHTML = '<div class="admin-cot-empty">Erro ao carregar atendimentos.</div>';
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
    btnAnterior.addEventListener('click', () => { if (currentPage > 1) irParaPagina(currentPage - 1); });
    btnProxima.addEventListener('click', () => { if (currentPage < totalPages) irParaPagina(currentPage + 1); });

    btnLimpar.addEventListener('click', () => {
        buscaInput.value = '';

        if (imobiliariaHidden) {
            const wrapper = imobiliariaHidden.closest('[data-custom-select]');
            if (wrapper) {
                const placeholder = wrapper.dataset.placeholder || 'Todas';
                const valueEl = wrapper.querySelector('.custom-select-value');
                valueEl.textContent = placeholder;
                valueEl.classList.add('is-placeholder');
                wrapper.querySelectorAll('.custom-select-option').forEach((opt) => {
                    opt.classList.toggle('is-selected', opt.dataset.value === '');
                });
            }
            imobiliariaHidden.value = '';
        }

        document.querySelectorAll('.status-kpi-card').forEach((c) => c.classList.remove('is-active'));
        document.querySelector('.status-kpi-card[data-status=""]').classList.add('is-active');
        activeStatus = '';

        irParaPagina(1);
    });

    carregar();
})();
