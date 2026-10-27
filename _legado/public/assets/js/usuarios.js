(function () {
    'use strict';

    // ── Mensagem flash do redirect (ex.: usuário criado/atualizado com sucesso) ──
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

    const lista = document.getElementById('usuarios-lista');
    const fBusca = document.getElementById('f-busca');
    const csrfToken = lista ? lista.dataset.csrf : '';

    let debounceTimer = null;

    function carregar() {
        const params = new URLSearchParams({ busca: fBusca ? fBusca.value.trim() : '' });
        lista.innerHTML = '<div class="admin-cot-empty">Carregando...</div>';

        fetch('/api/usuarios/listar?' + params.toString())
            .then(function (r) { return r.json(); })
            .then(function (data) {
                lista.innerHTML = data.html || '';
            })
            .catch(function () {
                lista.innerHTML = '<div class="admin-cot-empty">Erro ao carregar dados.</div>';
            });
    }

    function carregarDebounce() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(carregar, 350);
    }

    if (fBusca) fBusca.addEventListener('input', carregarDebounce);

    if (lista) {
        lista.addEventListener('click', async function (e) {
            const btn = e.target.closest('[data-action="excluir-usuario"]');
            if (!btn) return;

            const ok = await mpsAlert.confirm({
                message: 'Excluir este usuário? Essa ação não pode ser desfeita.',
            });
            if (!ok) return;

            btn.disabled = true;
            const linha = btn.closest('.admin-cot-row');

            mpsAlert.loading('Excluindo usuário...');

            fetch('/api/usuarios/excluir', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({ id: btn.dataset.id }),
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.ok) {
                        if (linha) linha.remove();
                        mpsAlert.success('Usuário excluído com sucesso.');
                    } else {
                        mpsAlert.error(data.message || 'Erro ao excluir usuário.');
                        btn.disabled = false;
                    }
                })
                .catch(function () {
                    mpsAlert.error('Erro na requisição. Tente novamente.');
                    btn.disabled = false;
                });
        });
    }

    carregar();
})();
