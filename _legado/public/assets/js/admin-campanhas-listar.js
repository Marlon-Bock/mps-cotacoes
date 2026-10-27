/**
 * Gerenciar Campanhas — admin-campanhas-listar.js
 * Filtros (busca, status) + paginação + excluir + alternância lista/cards —
 * tudo via AJAX, sem navegar nem alterar a URL de /admin/campanhas.
 */
(function () {
    'use strict';

    // ── Mensagem flash do redirect (ex.: campanha criada/atualizada com sucesso) ──
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

    const linhas       = document.getElementById('campanhas-rows');
    const viewList      = document.getElementById('campanhas-view-list');
    const viewGrid       = document.getElementById('campanhas-view-grid');
    const pagInfo      = document.getElementById('pag-info');
    const btnAnterior  = document.getElementById('pag-anterior');
    const btnProxima   = document.getElementById('pag-proxima');
    const buscaInput   = document.getElementById('f-busca');
    const btnViewList  = document.getElementById('btn-view-list');
    const btnViewGrid  = document.getElementById('btn-view-grid');
    const csrfToken    = linhas ? linhas.dataset.csrf : '';

    const countEls = {
        todas:    document.getElementById('count-todas'),
        ativas:   document.getElementById('count-ativas'),
        inativas: document.getElementById('count-inativas'),
    };

    let activeStatus  = '';
    let currentPage   = 1;
    let totalPages    = 1;
    let debounceTimer = null;
    let viewMode       = localStorage.getItem('admin-campanhas-view') === 'grid' ? 'grid' : 'list';

    function aplicarView() {
        const isGrid = viewMode === 'grid';
        viewList.hidden = isGrid;
        viewGrid.hidden = !isGrid;
        btnViewList.classList.toggle('is-active', !isGrid);
        btnViewGrid.classList.toggle('is-active', isGrid);
    }

    function alvoAtual() {
        return viewMode === 'grid' ? viewGrid : linhas;
    }

    function carregar() {
        const alvo = alvoAtual();
        const params = new URLSearchParams({
            status: activeStatus,
            busca: buscaInput.value.trim(),
            data_de: document.getElementById('f-data-de').value,
            data_ate: document.getElementById('f-data-ate').value,
            page: currentPage,
            view: viewMode,
        });

        alvo.innerHTML = '<div class="admin-cot-empty">Carregando...</div>';

        fetch('/api/admin/campanhas/listar?' + params.toString())
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
                countEls.todas.textContent    = c.todas    || 0;
                countEls.ativas.textContent   = c.ativas   || 0;
                countEls.inativas.textContent = c.inativas || 0;
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
        localStorage.setItem('admin-campanhas-view', viewMode);
        aplicarView();
        irParaPagina(1);
    }

    btnViewList.addEventListener('click', () => trocarView('list'));
    btnViewGrid.addEventListener('click', () => trocarView('grid'));

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

    // Delegado no documento — o botão "Excluir" existe tanto na lista quanto
    // nos cards, e apenas um dos dois contêineres está visível por vez.
    document.addEventListener('click', async function (e) {
        const btn = e.target.closest('[data-action="excluir-campanha"]');
        if (!btn) return;

        const ok = await mpsAlert.confirm({
            message: 'Excluir esta campanha? Essa ação não pode ser desfeita.',
        });
        if (!ok) return;

        btn.disabled = true;

        fetch('/api/admin/campanhas/excluir', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify({ id: btn.dataset.id }),
        })
            .then((r) => r.json())
            .then((data) => {
                if (data.ok) {
                    mpsAlert.success('Campanha excluída.');
                    carregar();
                } else {
                    mpsAlert.error(data.message || 'Erro ao excluir a campanha.');
                    btn.disabled = false;
                }
            })
            .catch(() => {
                mpsAlert.error('Erro na requisição. Tente novamente.');
                btn.disabled = false;
            });
    });

    aplicarView();
    carregar();
})();
