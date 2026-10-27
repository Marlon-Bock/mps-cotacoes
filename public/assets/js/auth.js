/**
 * Módulo 1 — Acesso
 * Comportamento de login, esqueceu-senha e recuperar-senha.
 * Não usa inline handlers — tudo via addEventListener + data-attributes.
 */

document.addEventListener('DOMContentLoaded', () => {
    initPasswordToggle();
    initDoisFatores();
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

        const dados = Object.fromEntries(new FormData(form));
        if (form.dataset.turnstile) {
            const token = await verificarTurnstile(form.dataset.turnstile);
            if (!token) {
                setButtonLoading(submitBtn, false);
                return;
            }
            dados['cf-turnstile-response'] = token;
        }
        mpsAlert.loading(loadingLabel);

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-Token': form.dataset.csrf || '',
                },
                body: JSON.stringify(dados),
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

/**
 * Verificação do Turnstile (Cloudflare) ao clicar em "Entrar", dentro do
 * mpsAlert: roda sozinha com o "Verificando acesso..." e só mostra o desafio
 * se a Cloudflare pedir interação. Resolve com o token (vale uma vez — cada
 * clique gera outro) ou null se falhar (o erro já foi exibido).
 */
async function verificarTurnstile(siteKey) {
    mpsAlert.loading('Verificando acesso...');

    // O script da Cloudflare carrega em paralelo (async): espera até 8 s.
    for (let i = 0; i < 80 && !window.turnstile; i++) {
        await new Promise((r) => setTimeout(r, 100));
    }
    if (!window.turnstile) {
        mpsAlert.error('Não foi possível carregar a verificação de segurança. Recarregue a página e tente de novo.');
        return null;
    }

    return new Promise((resolve) => {
        mpsAlert.custom(`
            <div class="mps-alert-spinner" aria-hidden="true" data-ts-spinner></div>
            <p class="mps-alert-message" data-ts-mensagem>Verificando acesso...</p>
            <div class="auth-turnstile" data-ts-widget></div>
        `, {
            onMount(card) {
                let widgetId = null;
                const fim = (token, erro) => {
                    setTimeout(() => { try { window.turnstile.remove(widgetId); } catch (e) { /* já saiu */ } });
                    if (erro) mpsAlert.error(erro);
                    resolve(token);
                };
                widgetId = window.turnstile.render(card.querySelector('[data-ts-widget]'), {
                    sitekey: siteKey,
                    language: 'pt-br',
                    theme: 'light',
                    appearance: 'interaction-only',
                    callback: (token) => fim(token),
                    'before-interactive-callback': () => {
                        card.querySelector('[data-ts-spinner]').hidden = true;
                        card.querySelector('[data-ts-mensagem]').textContent = 'Confirme que você não é um robô para continuar.';
                        card.querySelector('[data-ts-widget]').classList.add('is-visivel');
                    },
                    'error-callback': () => {
                        fim(null, 'Não foi possível verificar seu acesso. Tente de novo.');
                        return true;
                    },
                    'expired-callback': () => fim(null, 'A verificação expirou. Clique em Entrar de novo.'),
                    'timeout-callback': () => fim(null, 'A verificação expirou. Clique em Entrar de novo.'),
                });
            },
        });
    });
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
    if (data.dois_fatores) {
        mostrarDoisFatores(data.dois_fatores);
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

        onLoginSuccess(form, data);
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

/* ─── Dois fatores (segunda etapa do login) ─────────────────────────────────
   Depois da senha: configurar (primeiro acesso — e-mail dos códigos ou, na
   MPS, o Google Authenticator) e/ou digitar o código. Auth\DoisFatoresController. */

const DF = {};

function initDoisFatores() {
    const caixa = document.querySelector('[data-dois-fatores]');
    if (!caixa) return;
    Object.assign(DF, {
        caixa,
        login:       document.querySelector('[data-etapa-login]'),
        titulo:      caixa.querySelector('[data-2fa-titulo]'),
        texto:       caixa.querySelector('[data-2fa-texto]'),
        alerta:      caixa.querySelector('[data-2fa-alerta]'),
        metodos:     caixa.querySelector('[data-2fa-metodos]'),
        formEmail:   caixa.querySelector('[data-2fa-form-email]'),
        formCodigo:  caixa.querySelector('[data-2fa-form-codigo]'),
        qr:          caixa.querySelector('[data-2fa-qr]'),
        reenviar:    caixa.querySelector('[data-2fa-reenviar]'),
    });

    caixa.querySelectorAll('[data-2fa-escolher]').forEach((btn) => btn.addEventListener('click', () => {
        if (btn.dataset['2faEscolher'] === 'email') {
            dfMostrarConfigurarEmail(true);
        } else {
            dfPost('/api/auth/2fa/totp', {}, null).then((d) => d && mostrarDoisFatores(d.dois_fatores));
        }
    }));

    DF.formEmail.addEventListener('submit', async (e) => {
        e.preventDefault();
        const d = await dfPost('/api/auth/2fa/email', { email: DF.formEmail.elements.email.value.trim() }, DF.formEmail);
        if (d) mostrarDoisFatores(d.dois_fatores);
    });

    DF.formCodigo.addEventListener('submit', async (e) => {
        e.preventDefault();
        const d = await dfPost('/api/auth/2fa/verificar', { codigo: DF.formCodigo.elements.codigo.value }, DF.formCodigo);
        if (!d) return;
        mpsAlert.success('Login realizado com sucesso!', { autoCloseMs: 0 });
        setTimeout(() => { window.location.href = d.redirect || appUrl('/'); }, 700);
    });

    // Só números no código; com 6 dígitos, confirma sozinho.
    DF.formCodigo.elements.codigo.addEventListener('input', (e) => {
        e.target.value = e.target.value.replace(/\D/g, '').slice(0, 6);
        if (e.target.value.length === 6) DF.formCodigo.requestSubmit();
    });

    DF.reenviar.addEventListener('click', async () => {
        const d = await dfPost('/api/auth/2fa/reenviar', {}, null);
        if (!d) return;
        mpsAlert.success(`Enviamos um novo código para ${d.destino}.`);
        dfContagemReenvio();
    });

    caixa.querySelector('[data-2fa-voltar]').addEventListener('click', async () => {
        await dfPost('/api/auth/2fa/cancelar', {}, null, true);
        window.location.reload();
    });
}

/** Mostra a etapa que o servidor pediu: 'configurar' ou 'codigo'. */
function mostrarDoisFatores(df) {
    DF.login.hidden = true;
    DF.caixa.hidden = false;
    dfAlerta(df.aviso || '');
    [DF.metodos, DF.formEmail, DF.formCodigo, DF.qr].forEach((el) => { el.hidden = true; });
    DF.reenviar.hidden = true;

    if (df.etapa === 'configurar') {
        if (df.metodos.length > 1) {
            DF.titulo.textContent = 'Ative a verificação em duas etapas';
            DF.texto.textContent = 'Para sua segurança, todo acesso pede um código além da senha. Escolha como quer receber:';
            DF.metodos.hidden = false;
        } else if (df.metodos[0] === 'totp') {
            // Suporte da Sami: só o Authenticator — já vai pro QR code.
            dfPost('/api/auth/2fa/totp', {}, null).then((d) => d && mostrarDoisFatores(d.dois_fatores));
        } else {
            dfMostrarConfigurarEmail(false);
        }
        return;
    }

    DF.formCodigo.hidden = false;
    DF.formCodigo.elements.codigo.value = '';
    if (df.metodo === 'email') {
        DF.titulo.textContent = 'Digite o código';
        DF.texto.textContent = `Enviamos um código de 6 dígitos para ${df.destino}. Ele vale por 10 minutos.`;
        DF.reenviar.hidden = false;
        dfContagemReenvio();
    } else if (df.qr) {
        DF.titulo.textContent = 'Configure o Google Authenticator';
        DF.texto.textContent = 'Abra o Google Authenticator no celular, toque em "+" e leia o QR code. Depois digite o código que aparecer.';
        DF.qr.hidden = false;
        DF.qr.querySelector('[data-2fa-qr-img]').src = df.qr;
        DF.qr.querySelector('[data-2fa-segredo]').textContent = df.segredo;
    } else if (df.suporte) {
        DF.titulo.textContent = 'Código do suporte';
        DF.texto.textContent = 'Acesso com a senha do dia: digite o código do Google Authenticator do suporte da Sami.';
    } else {
        DF.titulo.textContent = 'Digite o código do aplicativo';
        DF.texto.textContent = 'Abra o Google Authenticator no celular e digite o código de 6 dígitos desta conta.';
    }
    DF.formCodigo.elements.codigo.focus();
}

/** Primeiro acesso por e-mail: pede o e-mail que vai receber os códigos. */
function dfMostrarConfigurarEmail(escolheuNaMps) {
    DF.metodos.hidden = true;
    DF.formEmail.hidden = false;
    DF.titulo.textContent = escolheuNaMps ? 'Código por e-mail' : 'Confirme seu e-mail de acesso';
    DF.texto.textContent = 'Para sua segurança, todo acesso pede um código enviado por e-mail. Informe o e-mail que vai receber os códigos — mesmo que já esteja no seu cadastro.';
    DF.formEmail.elements.email.focus();
}

/** "Reenviar código" liberado depois de 60 s. */
function dfContagemReenvio() {
    clearInterval(DF.timer);
    let falta = 60;
    const rotulo = () => { DF.reenviar.innerHTML = falta > 0 ? `Reenviar código em ${falta}s` : 'Reenviar código'; };
    DF.reenviar.disabled = true;
    rotulo();
    DF.timer = setInterval(() => {
        falta--;
        rotulo();
        if (falta <= 0) { clearInterval(DF.timer); DF.reenviar.disabled = false; }
    }, 1000);
}

function dfAlerta(mensagem) {
    DF.alerta.textContent = mensagem;
    DF.alerta.hidden = !mensagem;
}

/** POST da segunda etapa: erros no campo/alerta; tempo esgotado volta pro login. */
async function dfPost(url, corpo, form, silencioso = false) {
    const botao = form?.querySelector('[type="submit"]');
    if (form) {
        form.querySelectorAll('[data-2fa-erro]').forEach((el) => { el.textContent = ''; });
        setButtonLoading(botao, true);
    }
    if (!silencioso) mpsAlert.loading('Validando...');
    try {
        const resp = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': DF.caixa.dataset.csrf || '' },
            body: JSON.stringify(corpo),
        });
        const d = await resp.json();
        mpsAlert.close();
        if (resp.ok) { dfAlerta(''); return d; }
        if (silencioso) return null;
        if (d.reiniciar) {
            await mpsAlert.error(d.message);
            window.location.reload();
            return null;
        }
        Object.entries(d.errors || {}).forEach(([campo, msg]) => {
            const el = DF.caixa.querySelector(`[data-2fa-erro="${campo}"]`);
            if (el) el.textContent = msg;
        });
        dfAlerta(d.message || 'Não foi possível continuar.');
        return null;
    } catch (e) {
        mpsAlert.error('Não foi possível conectar ao servidor. Tente novamente.');
        return null;
    } finally {
        if (form) setButtonLoading(botao, false);
    }
}
