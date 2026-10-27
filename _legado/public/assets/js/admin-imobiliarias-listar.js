/**
 * Gerenciar Imobiliárias — admin-imobiliarias-listar.js
 * Filtros (busca, status) + paginação — tudo via AJAX, sem navegar nem
 * alterar a URL de /admin/imobiliarias.
 */
(function () {
    'use strict';

    // ── Mensagem flash do redirect (ex.: imobiliária criada com sucesso) ──────
    const appContent = document.querySelector('.app-content[data-flash]');
    if (appContent) {
        let flashMessages = [];
        try { flashMessages = JSON.parse(appContent.dataset.flash || '[]'); } catch (_) { flashMessages = []; }

        flashMessages.forEach((msg) => {
            if (msg.type === 'success') {
                mpsAlert.success(msg.message);
            } else if (msg.type === 'error') {
                mpsAlert.error(msg.message);
            } else {
                mpsAlert.toast(msg.type, msg.message);
            }
        });
    }

    const linhas      = document.getElementById('imobiliarias-rows');
    const pagInfo      = document.getElementById('pag-info');
    const btnAnterior  = document.getElementById('pag-anterior');
    const btnProxima   = document.getElementById('pag-proxima');
    const buscaInput   = document.getElementById('f-busca');

    const countEls = {
        todas:    document.getElementById('count-todas'),
        ativas:   document.getElementById('count-ativas'),
        inativas: document.getElementById('count-inativas'),
    };

    let activeStatus  = '';
    let currentPage   = 1;
    let totalPages    = 1;
    let sortBy        = 'nome';
    let sortDir       = 'asc';
    let debounceTimer = null;

    function carregar() {
        const params = new URLSearchParams({
            status: activeStatus,
            busca: buscaInput.value.trim(),
            page: currentPage,
            sort_by: sortBy,
            sort_dir: sortDir,
        });

        linhas.innerHTML = '<div class="admin-cot-empty">Carregando...</div>';

        fetch('/api/admin/imobiliarias/listar?' + params.toString())
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
                countEls.todas.textContent    = c.todas    || 0;
                countEls.ativas.textContent   = c.ativas   || 0;
                countEls.inativas.textContent = c.inativas || 0;
            })
            .catch(() => {
                linhas.innerHTML = '<div class="admin-cot-empty">Erro ao carregar imobiliárias.</div>';
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
    btnAnterior.addEventListener('click', () => { if (currentPage > 1) irParaPagina(currentPage - 1); });
    btnProxima.addEventListener('click', () => { if (currentPage < totalPages) irParaPagina(currentPage + 1); });

    // ── Ordenação por coluna ─────────────────────────────────────────────────
    document.querySelectorAll('.th-sortable').forEach((th) => {
        th.addEventListener('click', () => {
            const campo = th.dataset.sort;
            if (sortBy === campo) {
                sortDir = sortDir === 'asc' ? 'desc' : 'asc';
            } else {
                sortBy = campo;
                sortDir = 'asc';
            }
            document.querySelectorAll('.th-sortable .sort-arrow').forEach((a) => a.remove());
            const arrow = document.createElement('span');
            arrow.className = 'sort-arrow';
            arrow.textContent = sortDir === 'asc' ? '▲' : '▼';
            th.appendChild(arrow);
            irParaPagina(1);
        });
    });

    carregar();
})();
