/**
 * Histórico de Visitas — admin-visitas-historico.js
 * Filtros (corretor, imobiliária, período) + paginação da listagem —
 * tudo via AJAX, sem navegar nem alterar a URL de /admin/visitas.
 */
(function () {
    'use strict';

    const lista = document.getElementById('historico-lista');
    if (!lista) return;

    const badge      = document.getElementById('historico-total-badge');
    const pagWrap     = document.getElementById('historico-pagination');
    const pagInfo     = document.getElementById('historico-pag-info');
    const btnAnterior = document.getElementById('historico-pag-anterior');
    const btnProxima  = document.getElementById('historico-pag-proxima');

    const corretorHidden = document.querySelector('input[name="corretor_id"]');
    const elImobiliaria  = document.getElementById('f-imobiliaria');
    const dataDe  = document.getElementById('f-data-de');
    const dataAte = document.getElementById('f-data-ate');

    let currentPage = 1;
    let totalPages  = 1;
    let buscaTimer  = null;

    function carregar() {
        const params = new URLSearchParams({
            corretor_id: corretorHidden ? corretorHidden.value : '',
            imobiliaria: elImobiliaria.value.trim(),
            data_de: dataDe.value,
            data_ate: dataAte.value,
            page: currentPage,
        });

        lista.innerHTML = '<div class="admin-cot-empty">Carregando...</div>';

        fetch('/api/admin/visitas-historico/listar?' + params.toString())
            .then((r) => r.json())
            .then((data) => {
                lista.innerHTML = data.html || '';

                const total = data.total || 0;
                badge.hidden = !total;
                badge.textContent = total;

                currentPage = data.page || 1;
                totalPages  = data.total_pages || 1;
                const perPage = data.per_page || 20;

                if (totalPages > 1) {
                    pagWrap.hidden = false;
                    const ini = (currentPage - 1) * perPage + 1;
                    const fim = Math.min(total, currentPage * perPage);
                    pagInfo.textContent = 'Mostrando ' + ini + '–' + fim + ' de ' + total;
                    btnAnterior.disabled = currentPage <= 1;
                    btnProxima.disabled  = currentPage >= totalPages;
                } else {
                    pagWrap.hidden = true;
                }
            })
            .catch(() => {
                lista.innerHTML = '<div class="admin-cot-empty">Erro ao carregar visitas.</div>';
            });
    }

    function irParaPagina(p) {
        currentPage = p;
        carregar();
    }

    if (corretorHidden) corretorHidden.addEventListener('change', () => irParaPagina(1));
    dataDe.addEventListener('change', () => irParaPagina(1));
    dataAte.addEventListener('change', () => irParaPagina(1));
    elImobiliaria.addEventListener('input', () => {
        clearTimeout(buscaTimer);
        buscaTimer = setTimeout(() => irParaPagina(1), 350);
    });

    btnAnterior.addEventListener('click', () => { if (currentPage > 1) irParaPagina(currentPage - 1); });
    btnProxima.addEventListener('click', () => { if (currentPage < totalPages) irParaPagina(currentPage + 1); });

    // ── Ver observação completa (célula truncada) ─────────────────────────────
    function escapeHtml(v) {
        const div = document.createElement('div');
        div.textContent = v;
        return div.innerHTML;
    }

    lista.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-action="ver-observacao"]');
        if (!btn) return;

        mpsAlert.custom(`
            <div class="mps-alert-icon mps-alert-icon-info"><svg class="icon" viewBox="0 0 256 256" fill="currentColor" aria-hidden="true"><path d="M128,24A104,104,0,1,0,232,128,104.11,104.11,0,0,0,128,24Zm0,192a88,88,0,1,1,88-88A88.1,88.1,0,0,1,128,216Zm16-40a8,8,0,0,1-8,8,16,16,0,0,1-16-16V128a8,8,0,0,1,0-16,16,16,0,0,1,16,16v40A8,8,0,0,1,144,176ZM112,84a12,12,0,1,1,12,12A12,12,0,0,1,112,84Z"/></svg></div>
            <h3 class="mps-alert-title">Observações</h3>
            <p class="mps-alert-message" style="white-space:pre-wrap;text-align:left;">${escapeHtml(btn.dataset.obs || '')}</p>
            <div class="mps-alert-actions">
                <button type="button" class="btn btn-primary" data-mps-alert-close>Fechar</button>
            </div>
        `, {
            onMount: (card, close) => {
                card.querySelector('[data-mps-alert-close]').addEventListener('click', close);
            },
        });
    });

    carregar();
})();
