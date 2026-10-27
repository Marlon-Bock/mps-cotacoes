/**
 * Campanhas (imobiliária) — campanhas-listar.js
 * Filtros (busca, data, chips Ativas/Encerradas/Futuras/Todas) + paginação +
 * modal de detalhe — tudo via AJAX, sem navegar nem alterar a URL de /campanhas.
 */
(function () {
    'use strict';

    const cards        = document.getElementById('campanhas-cards');
    const rows          = document.getElementById('campanhas-rows');
    const viewGrid       = document.getElementById('campanhas-view-grid');
    const viewList        = document.getElementById('campanhas-view-list');
    const pagInfo       = document.getElementById('pag-info');
    const btnAnterior   = document.getElementById('pag-anterior');
    const btnProxima    = document.getElementById('pag-proxima');
    const buscaInput    = document.getElementById('f-busca');
    const btnViewGrid   = document.getElementById('btn-view-grid');
    const btnViewList   = document.getElementById('btn-view-list');

    const countEls = {
        todas:      document.getElementById('count-todas'),
        ativas:     document.getElementById('count-ativas'),
        encerradas: document.getElementById('count-encerradas'),
        futuras:    document.getElementById('count-futuras'),
    };

    let activeStatus  = 'ativas';
    let currentPage   = 1;
    let totalPages    = 1;
    let debounceTimer = null;
    let viewMode       = localStorage.getItem('campanhas-view') === 'list' ? 'list' : 'grid';

    function aplicarView() {
        const isList = viewMode === 'list';
        viewGrid.hidden = isList;
        viewList.hidden = !isList;
        btnViewGrid.classList.toggle('is-active', !isList);
        btnViewList.classList.toggle('is-active', isList);
    }

    function alvoAtual() {
        return viewMode === 'list' ? rows : cards;
    }

    function carregar() {
        const alvo = alvoAtual();
        const params = new URLSearchParams({
            status_rapido: activeStatus,
            busca: buscaInput.value.trim(),
            data_de: document.getElementById('f-data-de').value,
            data_ate: document.getElementById('f-data-ate').value,
            page: currentPage,
            view: viewMode,
        });

        alvo.innerHTML = '<div class="admin-cot-empty">Carregando...</div>';

        fetch('/api/campanhas/listar?' + params.toString())
            .then((r) => r.json())
            .then((data) => {
                alvo.innerHTML = data.html || '';

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
                countEls.todas.textContent      = c.todas      || 0;
                countEls.ativas.textContent     = c.ativas     || 0;
                countEls.encerradas.textContent = c.encerradas || 0;
                countEls.futuras.textContent    = c.futuras    || 0;
            })
            .catch(() => {
                alvo.innerHTML = '<div class="admin-cot-empty">Erro ao carregar campanhas.</div>';
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

    function trocarView(novoModo) {
        if (novoModo === viewMode) return;
        viewMode = novoModo;
        localStorage.setItem('campanhas-view', viewMode);
        aplicarView();
        irParaPagina(1);
    }

    btnViewGrid.addEventListener('click', () => trocarView('grid'));
    btnViewList.addEventListener('click', () => trocarView('list'));

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
    document.getElementById('f-data-de').addEventListener('change', () => irParaPagina(1));
    document.getElementById('f-data-ate').addEventListener('change', () => irParaPagina(1));
    btnAnterior.addEventListener('click', () => { if (currentPage > 1) irParaPagina(currentPage - 1); });
    btnProxima.addEventListener('click', () => { if (currentPage < totalPages) irParaPagina(currentPage + 1); });

    // Abertura do modal ao clicar em um card/linha é tratada globalmente em
    // app.js (delegado em [data-abrir-campanha]) — cobre grade e lista.

    aplicarView();
    carregar();
})();
