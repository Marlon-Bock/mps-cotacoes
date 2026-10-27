document.addEventListener('DOMContentLoaded', () => {
    initPerfilForm();
    initPerfilAvatar();
    initPasswordToggle();
});

/** Alterna visibilidade dos campos de senha (ícone olho) — mesmo padrão do login. */
function initPasswordToggle() {
    document.querySelectorAll('[data-toggle="password"]').forEach((toggle) => {
        toggle.addEventListener('click', () => {
            const input = document.querySelector(toggle.dataset.target);
            if (!input) return;

            const isHidden = input.type === 'password';
            input.type = isHidden ? 'text' : 'password';
            toggle.setAttribute('aria-label', isHidden ? 'Ocultar senha' : 'Mostrar senha');
            toggle.classList.toggle('is-visible', isHidden);
        });
    });
}

function clearPerfilErrors(form) {
    form.querySelectorAll('[data-error-for]').forEach((el) => (el.textContent = ''));
    const alertEl = form.querySelector('[data-form-alert]');
    if (alertEl) alertEl.hidden = true;
}

function showPerfilErrors(form, data) {
    if (data.errors) {
        Object.entries(data.errors).forEach(([field, message]) => {
            const el = form.querySelector(`[data-error-for="${field}"]`);
            if (el) el.textContent = message;
        });
    }
    const alertEl = form.querySelector('[data-form-alert]');
    if (alertEl && data.message) {
        alertEl.textContent = data.message;
        alertEl.hidden = false;
    }
}

function initPerfilForm() {
    const form = document.getElementById('form-perfil');
    if (!form) return;

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        clearPerfilErrors(form);

        const submitBtn = form.querySelector('[type="submit"]');
        submitBtn.disabled = true;
        mpsAlert.loading('Salvando...');

        try {
            const response = await fetch('/api/perfil', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': form.dataset.csrf || '',
                },
                body: JSON.stringify(Object.fromEntries(new FormData(form))),
            });
            const data = await response.json();
            mpsAlert.close();

            if (!response.ok) {
                showPerfilErrors(form, data);
                return;
            }

            form.querySelector('[name="senha_atual"]').value = '';
            form.querySelector('[name="nova_senha"]').value = '';
            mpsAlert.success(data.message || 'Perfil atualizado com sucesso.');
        } catch (e) {
            mpsAlert.close();
            mpsAlert.error('Falha na comunicação com o servidor.');
        } finally {
            submitBtn.disabled = false;
        }
    });
}

function initPerfilAvatar() {
    const btn = document.getElementById('btn-trocar-avatar');
    const input = document.getElementById('perfil-avatar-input');
    const form = document.getElementById('form-perfil');
    if (!btn || !input || !form) return;

    btn.addEventListener('click', () => input.click());

    input.addEventListener('change', async () => {
        const file = input.files[0];
        if (!file) return;

        const confirmed = await mpsAlert.confirm({ message: 'Usar esta imagem como novo avatar?' });
        if (!confirmed) {
            input.value = '';
            return;
        }

        const fd = new FormData();
        fd.append('avatar', file);

        mpsAlert.loading('Enviando avatar...');
        try {
            const response = await fetch('/api/perfil/avatar', {
                method: 'POST',
                headers: { 'X-CSRF-Token': form.dataset.csrf || '' },
                body: fd,
            });
            const data = await response.json();
            mpsAlert.close();

            if (!response.ok) {
                mpsAlert.error(data.message || 'Falha ao atualizar avatar.');
                return;
            }

            const img = document.getElementById('perfil-avatar-img');
            const placeholder = document.getElementById('perfil-avatar-placeholder');
            if (img) {
                img.src = data.avatar + '?v=' + Date.now();
            } else if (placeholder) {
                const newImg = document.createElement('img');
                newImg.id = 'perfil-avatar-img';
                newImg.className = 'perfil-avatar-img';
                newImg.alt = 'Avatar';
                newImg.src = data.avatar + '?v=' + Date.now();
                placeholder.replaceWith(newImg);
            }
            mpsAlert.success(data.message || 'Avatar atualizado com sucesso.');
        } catch (e) {
            mpsAlert.close();
            mpsAlert.error('Falha na comunicação com o servidor.');
        } finally {
            input.value = '';
        }
    });
}
