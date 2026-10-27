/**
 * Usuários de uma imobiliária (admin) — admin-imobiliarias-usuarios.js
 * Ativar/inativar usuário via AJAX, sem recarregar a URL.
 */
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

    const csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

    document.addEventListener('click', async function (e) {
        const btn = e.target.closest('.js-toggle-ativo');
        if (!btn) return;

        const ativo = btn.dataset.ativo === '1';

        const ok = await mpsAlert.confirm({
            message: ativo ? 'Inativar este usuário?' : 'Ativar este usuário?',
        });
        if (!ok) return;

        btn.disabled = true;

        try {
            const r = await fetch('/api/admin/imobiliarias/usuario-toggle-ativo', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({
                    usuario_id: btn.dataset.usuarioId,
                    imobiliaria_id: btn.dataset.imobiliariaId,
                }),
            });
            const data = await r.json();

            if (!data.ok) {
                mpsAlert.error(data.message || 'Erro ao atualizar o usuário.');
                btn.disabled = false;
                return;
            }

            mpsAlert.success(ativo ? 'Usuário inativado.' : 'Usuário ativado.');
            window.location.reload();
        } catch (err) {
            mpsAlert.error('Erro na requisição. Tente novamente.');
            btn.disabled = false;
        }
    });
})();
