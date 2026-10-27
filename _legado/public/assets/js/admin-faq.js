/**
 * Gerenciar FAQ — admin-faq.js
 * CRUD de blocos e perguntas, tudo via AJAX (modais + fetch), sem navegar.
 */
(function () {
    'use strict';

    const lista       = document.getElementById('faq-lista');
    const csrfToken    = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

    const modalBloco   = document.getElementById('modal-bloco');
    const formBloco    = document.getElementById('form-bloco');
    const modalItem    = document.getElementById('modal-item');
    const formItem     = document.getElementById('form-item');

    function carregar() {
        lista.innerHTML = '<div class="admin-cot-empty">Carregando...</div>';

        fetch('/api/admin/faq/listar')
            .then((r) => r.json())
            .then((data) => {
                lista.innerHTML = data.html || '';
            })
            .catch(() => {
                lista.innerHTML = '<div class="admin-cot-empty">Erro ao carregar o FAQ.</div>';
            });
    }

    function abrir(id) {
        var modal = document.getElementById(id);
        if (modal) modal.hidden = false;
    }
    function fechar(id) {
        var modal = document.getElementById(id);
        if (modal) modal.hidden = true;
    }

    // ── Modal Bloco ───────────────────────────────────────────────────────────
    document.getElementById('btn-novo-bloco').addEventListener('click', () => {
        formBloco.reset();
        formBloco.querySelector('[name="id"]').value = '';
        formBloco.querySelector('#bloco-ativo').checked = true;
        document.getElementById('modal-bloco-titulo').textContent = 'Novo bloco';
        abrir('modal-bloco');
    });

    formBloco.addEventListener('submit', (e) => {
        e.preventDefault();
        const payload = {
            id: formBloco.querySelector('[name="id"]').value,
            titulo: formBloco.querySelector('#bloco-titulo').value.trim(),
            ordem: formBloco.querySelector('#bloco-ordem').value,
            ativo: formBloco.querySelector('#bloco-ativo').checked ? 1 : 0,
        };

        fetch('/api/admin/faq/bloco-salvar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify(payload),
        })
            .then((r) => r.json())
            .then((data) => {
                if (data.ok) {
                    mpsAlert.success('Bloco salvo com sucesso.');
                    fechar('modal-bloco');
                    carregar();
                } else {
                    mpsAlert.error(data.message || 'Erro ao salvar o bloco.');
                }
            })
            .catch(() => mpsAlert.error('Erro na requisição. Tente novamente.'));
    });

    // ── Modal Item ────────────────────────────────────────────────────────────
    function abrirModalItemNovo(blocoId) {
        formItem.reset();
        formItem.querySelector('[name="id"]').value = '';
        formItem.querySelector('[name="bloco_id"]').value = blocoId;
        document.getElementById('modal-item-titulo').textContent = 'Nova pergunta';
        abrir('modal-item');
    }

    function abrirModalItemEditar(btn) {
        formItem.querySelector('[name="id"]').value = btn.dataset.itemId;
        formItem.querySelector('[name="bloco_id"]').value = btn.dataset.blocoId;
        formItem.querySelector('#item-pergunta').value = btn.dataset.pergunta || '';
        formItem.querySelector('#item-resposta').value = btn.dataset.resposta || '';
        formItem.querySelector('#item-ordem').value = btn.dataset.ordem || 0;
        document.getElementById('modal-item-titulo').textContent = 'Editar pergunta';
        abrir('modal-item');
    }

    formItem.addEventListener('submit', (e) => {
        e.preventDefault();
        const payload = {
            id: formItem.querySelector('[name="id"]').value,
            bloco_id: formItem.querySelector('[name="bloco_id"]').value,
            pergunta: formItem.querySelector('#item-pergunta').value.trim(),
            resposta: formItem.querySelector('#item-resposta').value.trim(),
            ordem: formItem.querySelector('#item-ordem').value,
        };

        fetch('/api/admin/faq/item-salvar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify(payload),
        })
            .then((r) => r.json())
            .then((data) => {
                if (data.ok) {
                    mpsAlert.success('Pergunta salva com sucesso.');
                    fechar('modal-item');
                    carregar();
                } else {
                    mpsAlert.error(data.message || 'Erro ao salvar a pergunta.');
                }
            })
            .catch(() => mpsAlert.error('Erro na requisição. Tente novamente.'));
    });

    // ── Ações na lista (delegadas, já que os blocos são recarregados via AJAX) ─
    lista.addEventListener('click', async (e) => {
        const btnNovoItem = e.target.closest('[data-action="novo-item"]');
        if (btnNovoItem) {
            abrirModalItemNovo(btnNovoItem.dataset.blocoId);
            return;
        }

        const btnEditarBloco = e.target.closest('[data-action="editar-bloco"]');
        if (btnEditarBloco) {
            formBloco.querySelector('[name="id"]').value = btnEditarBloco.dataset.blocoId;
            formBloco.querySelector('#bloco-titulo').value = btnEditarBloco.dataset.titulo || '';
            formBloco.querySelector('#bloco-ordem').value = btnEditarBloco.dataset.ordem || 0;
            formBloco.querySelector('#bloco-ativo').checked = btnEditarBloco.dataset.ativo === '1';
            document.getElementById('modal-bloco-titulo').textContent = 'Editar bloco';
            abrir('modal-bloco');
            return;
        }

        const btnExcluirBloco = e.target.closest('[data-action="excluir-bloco"]');
        if (btnExcluirBloco) {
            const ok = await mpsAlert.confirm({
                message: 'Excluir este bloco? Todas as perguntas dentro dele também serão excluídas.',
            });
            if (!ok) return;

            fetch('/api/admin/faq/bloco-excluir', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({ id: btnExcluirBloco.dataset.blocoId }),
            })
                .then((r) => r.json())
                .then((data) => {
                    if (data.ok) { mpsAlert.success('Bloco excluído.'); carregar(); }
                    else mpsAlert.error(data.message || 'Erro ao excluir o bloco.');
                })
                .catch(() => mpsAlert.error('Erro na requisição. Tente novamente.'));
            return;
        }

        const btnEditarItem = e.target.closest('[data-action="editar-item"]');
        if (btnEditarItem) {
            abrirModalItemEditar(btnEditarItem);
            return;
        }

        const btnExcluirItem = e.target.closest('[data-action="excluir-item"]');
        if (btnExcluirItem) {
            const ok = await mpsAlert.confirm({ message: 'Excluir esta pergunta?' });
            if (!ok) return;

            fetch('/api/admin/faq/item-excluir', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({ id: btnExcluirItem.dataset.itemId }),
            })
                .then((r) => r.json())
                .then((data) => {
                    if (data.ok) { mpsAlert.success('Pergunta excluída.'); carregar(); }
                    else mpsAlert.error(data.message || 'Erro ao excluir a pergunta.');
                })
                .catch(() => mpsAlert.error('Erro na requisição. Tente novamente.'));
            return;
        }

        // Accordion toggle (mesma lógica de faq.js)
        const toggleBtn = e.target.closest('[data-toggle="faq-item"]');
        if (toggleBtn) {
            const item = toggleBtn.closest('.faq-item');
            if (item) item.classList.toggle('is-open');
        }
    });

    carregar();
})();
