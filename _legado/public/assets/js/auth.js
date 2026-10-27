/**
 * Módulo 1 — Acesso
 * Comportamento de login, esqueceu-senha e recuperar-senha.
 * Não usa inline handlers — tudo via addEventListener + data-attributes.
 */

document.addEventListener('DOMContentLoaded', () => {
    initPasswordToggle();
    initFormAjax('[data-form="login"]', '/api/auth/login', onLoginSuccess);
    initFormAjax('[data-form="esqueceu-senha"]', '/api/auth/esqueceu-senha', onEsqueceuSenhaSuccess);
    initFormAjax('[data-form="recuperar-senha"]', '/api/auth/recuperar-senha', onRecuperarSenhaSuccess);
});

/** Alterna visibilidade do campo de senha (ícone olho). */
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

/**
 * Liga um formulário a um endpoint via fetch, exibindo estado de
 * carregamento no botão e erros nos campos / alerta geral.
 */
function initFormAjax(selector, endpoint, onSuccess) {
    const form = document.querySelector(selector);
    if (!form) return;

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        clearFormErrors(form);

        const submitBtn = form.querySelector('[type="submit"]');
        const loadingLabel = form.dataset.loadingLabel || 'Validando...';
        setButtonLoading(submitBtn, true);
        mpsAlert.loading(loadingLabel);

        try {
            const response = await fetch(endpoint, {
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
                handleFormError(form, data);
                return;
            }

            onSuccess?.(form, data);
        } catch (err) {
            mpsAlert.error('Não foi possível conectar ao servidor. Tente novamente.');
        } finally {
            setButtonLoading(submitBtn, false);
        }
    });
}

function setButtonLoading(button, isLoading) {
    if (!button) return;

    button.disabled = isLoading;
    button.classList.toggle('is-loading', isLoading);
}

function handleFormError(form, data) {
    if (data.errors) {
        Object.entries(data.errors).forEach(([field, message]) => {
            const input = form.querySelector(`[name="${field}"]`);
            const errorEl = form.querySelector(`[data-error-for="${field}"]`);

            input?.classList.add('has-error');
            if (errorEl) errorEl.textContent = message;
        });
    }

    showFormAlert(form, data.message || 'Verifique os dados informados.');

    // Token CSRF ficou desatualizado (aba aberta há muito tempo, sessão expirou
    // no servidor) — recarrega a página pra pegar um token novo automaticamente,
    // em vez de deixar a pessoa tentar de novo com o mesmo formulário quebrado.
    if (data.session_expirada) {
        setTimeout(() => window.location.reload(), 2000);
    }
}

function clearFormErrors(form) {
    form.querySelectorAll('.has-error').forEach((el) => el.classList.remove('has-error'));
    form.querySelectorAll('[data-error-for]').forEach((el) => (el.textContent = ''));
    hideFormAlert(form);
}

function showFormAlert(form, message) {
    const alertEl = form.querySelector('[data-form-alert]');
    if (!alertEl) return;

    alertEl.textContent = message;
    alertEl.hidden = false;
}

function hideFormAlert(form) {
    const alertEl = form.querySelector('[data-form-alert]');
    if (alertEl) alertEl.hidden = true;
}

function onLoginSuccess(form, data) {
    if (data.choice_required) {
        handleLoginChoice(form);
        return;
    }
    mpsAlert.success('Login realizado com sucesso!', { autoCloseMs: 0 });
    setTimeout(() => {
        window.location.href = data.redirect || appUrl('/');
    }, 700);
}

/** Conta existe nos dois perfis (admin e imobiliária) — pede ao usuário para escolher. */
async function handleLoginChoice(form) {
    const irParaAdmin = await mpsAlert.confirm({
        title: 'Duas contas encontradas',
        message: 'Esta conta tem acesso à área administrativa e à imobiliária. Para onde deseja entrar?',
        confirmText: 'Área Admin',
        cancelText: 'Área Imobiliária',
        icon: 'question',
        danger: false,
    });

    const submitBtn = form.querySelector('[type="submit"]');
    setButtonLoading(submitBtn, true);
    mpsAlert.loading('Validando...');

    try {
        const payload = Object.fromEntries(new FormData(form));
        payload.escolha = irParaAdmin ? 'admin' : 'imobiliaria';

        const response = await fetch('/api/auth/login', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': form.dataset.csrf || '',
            },
            body: JSON.stringify(payload),
        });

        const data = await response.json();
        mpsAlert.close();

        if (!response.ok) {
            handleFormError(form, data);
            return;
        }

        mpsAlert.success('Login realizado com sucesso!', { autoCloseMs: 0 });
        setTimeout(() => {
            window.location.href = data.redirect || appUrl('/');
        }, 700);
    } catch (err) {
        mpsAlert.error('Não foi possível conectar ao servidor. Tente novamente.');
    } finally {
        setButtonLoading(submitBtn, false);
    }
}

function onEsqueceuSenhaSuccess(form, data) {
    // Mesmo e-mail com mais de uma conta: mostra as contas pra pessoa escolher a
    // dela e reenviar (o usuario_id escolhido vai junto no próximo submit).
    if (data.escolher_usuario) {
        mostrarEscolhaUsuario(form, data.usuarios || [], data.message);
        return;
    }

    const box = document.querySelector('[data-state="form"]');
    const success = document.querySelector('[data-state="success"]');
    if (box) box.hidden = true;
    if (success) success.hidden = false;
    mpsAlert.toast('success', data.message || 'Link de recuperação enviado.');
}

function mostrarEscolhaUsuario(form, usuarios, mensagem) {
    const bloco = form.querySelector('[data-escolha-usuario]');
    const lista = form.querySelector('[data-escolha-lista]');
    if (!bloco || !lista) return;

    lista.innerHTML = '';
    usuarios.forEach((u, i) => {
        const label = document.createElement('label');
        label.className = 'auth-conta-opcao';

        const radio = document.createElement('input');
        radio.type = 'radio';
        radio.name = 'usuario_id';
        radio.value = String(u.id);
        radio.required = true;
        if (i === 0) radio.checked = true;

        const nome = document.createElement('span');
        nome.textContent = u.nome;   // textContent: nome vem do banco, nunca vira HTML

        label.append(radio, nome);
        lista.append(label);
    });

    bloco.hidden = false;
    const label = form.querySelector('[type="submit"] [data-label]');
    if (label) label.textContent = 'Enviar link para esta conta';
    if (mensagem) mpsAlert.toast('info', mensagem);

    // Trocou o e-mail: a lista era do e-mail anterior — esconde e volta ao normal.
    const email = form.querySelector('[name="email"]');
    if (email && !email.dataset.escolhaWatch) {
        email.dataset.escolhaWatch = '1';
        email.addEventListener('input', () => {
            bloco.hidden = true;
            lista.innerHTML = '';
            if (label) label.textContent = 'Enviar link de recuperação';
        });
    }
}

function onRecuperarSenhaSuccess(form, data) {
    mpsAlert.success('Senha redefinida com sucesso!', { autoCloseMs: 0 });
    setTimeout(() => {
        window.location.href = appUrl('/login?redefinida=1');
    }, 700);
}
