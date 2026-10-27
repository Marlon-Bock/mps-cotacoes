/**
 * Central de notificações — notificacoes.js
 * Filtro por tipo, paginação, "marcar como lida" e "marcar todas" — tudo via
 * AJAX (/api/notificacoes/listar e /api/notificacoes/lidas), sem navegar
 * nem alterar a URL. Mesmo padrão de cotacoes.js.
 */
(function () {
    'use strict';

    const lista       = document.getElementById('notif-lista');
    const totalBadge  = document.getElementById('notif-total-badge');
    const btnTodas    = document.getElementById('notif-marcar-todas');
    const pagWrap      = document.getElementById('notif-pagination');
    const pagInfo      = document.getElementById('notif-pag-info');
    const btnAnterior  = document.getElementById('notif-pag-anterior');
    const btnProxima   = document.getElementById('notif-pag-proxima');

    if (!lista) return;

    const csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

    const countEls = {
        todos:    document.getElementById('count-todos'),
        cotacao:  document.getElementById('count-cotacao'),
        chamado:  document.getElementById('count-chamado'),
        campanha: document.getElementById('count-campanha'),
    };

    let tipoAtivo   = '';
    let currentPage = 1;
    let totalPages  = 1;

    function carregar() {
        const params = new URLSearchParams({ tipo: tipoAtivo, page: currentPage });
        lista.innerHTML = '<div class="admin-cot-empty">Carregando...</div>';

        fetch('/api/notificacoes/listar?' + params.toString())
            .then((r) => r.json())
            .then((data) => {
                lista.innerHTML = data.html || '';

                const c = data.contadores || {};
                countEls.todos.textContent    = c.todos    || 0;
                countEls.cotacao.textContent  = c.cotacao  || 0;
                countEls.chamado.textContent  = c.chamado  || 0;
                countEls.campanha.textContent = c.campanha || 0;

                const total = data.total || 0;
                totalBadge.hidden = total === 0;
                totalBadge.textContent = total;
                btnTodas.hidden = total === 0 || !lista.querySelector('[data-action="marcar-lida"]');

                currentPage = data.page        || 1;
                totalPages  = data.total_pages || 1;
                const perPage = data.per_page  || 20;

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
                lista.innerHTML = '<div class="admin-cot-empty">Erro ao carregar notificações.</div>';
            });
    }

    function irParaPagina(p) {
        currentPage = p;
        carregar();
    }

    // ── Filtro por tipo (KPI cards) ──────────────────────────────────────────
    document.querySelectorAll('.status-kpi-card').forEach((card) => {
        card.addEventListener('click', (e) => {
            e.preventDefault();
            document.querySelectorAll('.status-kpi-card').forEach((c) => c.classList.remove('is-active'));
            card.classList.add('is-active');
            tipoAtivo = card.dataset.tipo || '';
            irParaPagina(1);
        });
    });

    // ── Paginação ─────────────────────────────────────────────────────────────
    btnAnterior.addEventListener('click', () => {
        if (currentPage > 1) irParaPagina(currentPage - 1);
    });
    btnProxima.addEventListener('click', () => {
        if (currentPage < totalPages) irParaPagina(currentPage + 1);
    });

    // ── Marcar como lida / marcar todas ──────────────────────────────────────
    async function postMarcarLidas(body) {
        const response = await fetch('/api/notificacoes/lidas', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken,
            },
            body: JSON.stringify(body),
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            mpsAlert.error(data.message || 'Não foi possível atualizar a notificação.');
            return false;
        }

        return true;
    }

    lista.addEventListener('click', async (event) => {
        const btn = event.target.closest('[data-action="marcar-lida"]');
        if (!btn) return;

        const id = parseInt(btn.dataset.id, 10);
        if (!id) return;

        btn.disabled = true;
        const ok = await postMarcarLidas({ id });

        if (ok) {
            carregar();
        } else {
            btn.disabled = false;
        }
    });

    btnTodas.addEventListener('click', async () => {
        btnTodas.disabled = true;

        const ok = await postMarcarLidas({ todas: true });

        if (ok) {
            mpsAlert.success('Todas as notificações foram marcadas como lidas.');
            carregar();
        }

        btnTodas.disabled = false;
    });

    // ── Carga inicial ─────────────────────────────────────────────────────────
    carregar();
})();
