/**
 * Rodízio de atendentes — admin-rodizio.js
 * Adicionar, remover, reordenar (arrastar e soltar) e ativar/desativar
 * atendentes de cada funil — tudo via AJAX (/api/admin/rodizio/*), sem
 * navegar nem alterar a URL. Depois de qualquer ação, recarrega a lista
 * inteira (mesmo padrão de outras telas: mutation + reload).
 */
(function () {
    'use strict';

    const lista = document.getElementById('rodizio-lista');
    if (!lista) return;

    const csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

    function carregar() {
        fetch('/api/admin/rodizio/listar')
            .then((r) => r.json())
            .then((data) => {
                lista.innerHTML = data.html || '';
                // Os custom_select() de "adicionar atendente" são injetados via AJAX —
                // precisam ser (re)inicializados manualmente (ver custom-select.js).
                if (window.customSelectInit) window.customSelectInit(lista);
            })
            .catch(() => {
                lista.innerHTML = '<div class="panel"><div class="panel-body"><div class="empty-state"><p>Erro ao carregar os funis.</p></div></div></div>';
            });
    }

    async function postJson(url, body) {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrfToken,
            },
            body: JSON.stringify(body),
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok || data.success === false) {
            mpsAlert.error(data.message || 'Não foi possível concluir a ação.');
            return false;
        }

        return true;
    }

    // ── Botões: remover / adicionar ──────────────────────────────────────────
    lista.addEventListener('click', async (event) => {
        const btnRemover = event.target.closest('[data-action="rodizio-remover"]');
        if (btnRemover) {
            const confirmado = await mpsAlert.confirm({
                title: 'Remover atendente?',
                message: 'Ele deixa de receber cotações automaticamente por este funil.',
                confirmText: 'Remover',
                cancelText: 'Cancelar',
            });
            if (!confirmado) return;

            const ok = await postJson('/api/admin/rodizio/remover', {
                id: parseInt(btnRemover.dataset.id, 10),
            });
            if (ok) {
                mpsAlert.success('Atendente removido do rodízio.');
                carregar();
            }
            return;
        }

        const btnAdicionar = event.target.closest('[data-action="rodizio-adicionar"]');
        if (btnAdicionar) {
            const funilId = parseInt(btnAdicionar.dataset.funilId, 10);
            const addRow  = btnAdicionar.closest('.rodizio-add-row');
            const hidden  = addRow ? addRow.querySelector('[data-rodizio-add-select] input[type="hidden"]') : null;
            const userId  = hidden ? parseInt(hidden.value, 10) : 0;

            if (!userId) {
                mpsAlert.error('Selecione um atendente para adicionar.');
                return;
            }

            btnAdicionar.disabled = true;
            const ok = await postJson('/api/admin/rodizio/adicionar', { funil_id: funilId, user_id: userId });
            if (ok) {
                mpsAlert.success('Atendente adicionado ao rodízio.');
                carregar();
            } else {
                btnAdicionar.disabled = false;
            }
            return;
        }
    });

    // ── Switch de ativo/inativo por atendente ────────────────────────────────
    lista.addEventListener('change', async (event) => {
        const toggle = event.target.closest('.rodizio-status-toggle');
        if (!toggle) return;

        toggle.disabled = true;
        const ok = await postJson('/api/admin/rodizio/status', {
            id: parseInt(toggle.dataset.id, 10),
            ativo: toggle.checked,
        });
        if (ok) mpsAlert.success(toggle.checked ? 'Atendente ativado.' : 'Atendente desativado.');
        carregar(); // recarrega sempre — o badge "Próximo" muda conforme quem está ativo
        if (!ok) toggle.disabled = false;
    });

    // ── Arrastar e soltar pra reordenar dentro de um funil ───────────────────
    let ordemAntesDoArrasto = [];

    function linhaMaisProxima(container, y) {
        const linhas = [...container.querySelectorAll('.rodizio-row:not(.is-dragging)')];
        return linhas.reduce((maisProxima, linha) => {
            const box    = linha.getBoundingClientRect();
            const offset = y - box.top - box.height / 2;
            if (offset < 0 && offset > maisProxima.offset) {
                return { offset, elemento: linha };
            }
            return maisProxima;
        }, { offset: Number.NEGATIVE_INFINITY }).elemento;
    }

    lista.addEventListener('dragstart', (event) => {
        const row = event.target.closest('.rodizio-row');
        if (!row) return;
        row.classList.add('is-dragging');

        const container = row.closest('.rodizio-row-list');
        ordemAntesDoArrasto = container
            ? Array.from(container.querySelectorAll('.rodizio-row')).map((r) => r.dataset.id)
            : [];

        event.dataTransfer.effectAllowed = 'move';
    });

    lista.addEventListener('dragover', (event) => {
        const container = event.target.closest('.rodizio-row-list');
        const dragging  = lista.querySelector('.rodizio-row.is-dragging');
        if (!container || !dragging) return;
        event.preventDefault();

        const depoisDe = linhaMaisProxima(container, event.clientY);
        if (depoisDe == null) {
            container.appendChild(dragging);
        } else {
            container.insertBefore(dragging, depoisDe);
        }
    });

    lista.addEventListener('drop', (event) => event.preventDefault());

    lista.addEventListener('dragend', async (event) => {
        const row = event.target.closest('.rodizio-row');
        if (!row) return;
        row.classList.remove('is-dragging');

        const container = row.closest('.rodizio-row-list');
        if (!container) return;

        const ordemFinal = Array.from(container.querySelectorAll('.rodizio-row')).map((r) => r.dataset.id);
        if (ordemFinal.join(',') === ordemAntesDoArrasto.join(',')) return; // nada mudou de posição

        const funilId = parseInt(container.dataset.funilId, 10);
        const ok = await postJson('/api/admin/rodizio/reordenar', {
            funil_id: funilId,
            ordem: ordemFinal.map((id) => parseInt(id, 10)),
        });
        if (ok) carregar(); // recarrega pra atualizar numeração e badge "Próximo"
    });

    carregar();
})();
