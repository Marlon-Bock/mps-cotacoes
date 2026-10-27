/**
 * Embed de Cotações (imobiliária) — embed-config.js
 * Switch de ativação + editar/excluir domínio (feedback via mpsAlert) e
 * cópia dos snippets de <iframe> para a área de transferência.
 */
(function () {
    'use strict';

    document.querySelectorAll('[data-copiar]').forEach((botao) => {
        botao.addEventListener('click', async () => {
            const textarea = botao.closest('.embed-card')?.querySelector('.embed-snippet');
            if (!textarea) return;

            try {
                await navigator.clipboard.writeText(textarea.value);
            } catch {
                textarea.select();
                document.execCommand('copy');
            }

            const textoOriginal = botao.innerHTML;
            botao.textContent = 'Copiado!';
            setTimeout(() => { botao.innerHTML = textoOriginal; }, 1500);
        });
    });

    const switchInput  = document.getElementById('embed-switch');
    const urlBlock      = document.getElementById('embed-url-block');
    const domainView    = document.getElementById('embed-domain-view');
    const form           = document.getElementById('embed-ativar-form');
    if (!switchInput || !urlBlock || !form) return;

    const csrfToken    = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    const campoUrl     = document.getElementById('embed-url');
    const botaoAtivar  = document.getElementById('embed-ativar-btn');
    const botaoEditar  = document.getElementById('embed-editar-btn');
    const botaoExcluir = document.getElementById('embed-excluir-btn');
    const botaoCancelar = document.getElementById('embed-cancelar-btn');
    const badge         = document.getElementById('embed-status-badge');
    const domainText     = document.getElementById('embed-domain-text');

    let jaConfigurado = switchInput.checked;

    const abrirEdicao = () => {
        domainView?.classList.add('is-hidden');
        urlBlock.classList.add('is-open');
        campoUrl.focus();
    };

    const fecharEdicao = () => {
        urlBlock.classList.remove('is-open');
        if (jaConfigurado) domainView?.classList.remove('is-hidden');
    };

    const chamar = (payload) => fetch('/api/embed/ativar', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
        body: JSON.stringify(payload),
    }).then((r) => r.json());

    const desativar = async (mensagemLoading, mensagemSucesso) => {
        mpsAlert.loading(mensagemLoading);
        try {
            const data = await chamar({ ativar: false });
            if (!data.success) {
                mpsAlert.error(data.message || 'Não foi possível concluir. Tente novamente.');
                return false;
            }
            await mpsAlert.success(mensagemSucesso);
            window.location.reload();
            return true;
        } catch {
            mpsAlert.error('Erro na requisição. Tente novamente.');
            return false;
        }
    };

    switchInput.addEventListener('change', async () => {
        if (switchInput.checked) {
            abrirEdicao();
            return;
        }

        fecharEdicao();
        domainView?.classList.add('is-hidden');

        if (!jaConfigurado) return; // ainda não tinha sido ativado, nada a desfazer

        const ok = await mpsAlert.confirm({
            title: 'Desativar o embed?',
            message: 'Os códigos deixarão de funcionar no seu site.',
            confirmText: 'Desativar',
        });
        if (!ok) {
            switchInput.checked = true;
            domainView?.classList.remove('is-hidden');
            return;
        }

        const sucesso = await desativar('Desativando embed…', 'Embed desativado.');
        if (!sucesso) {
            switchInput.checked = true;
            domainView?.classList.remove('is-hidden');
        }
    });

    botaoEditar?.addEventListener('click', abrirEdicao);

    botaoCancelar?.addEventListener('click', () => {
        campoUrl.value = domainText?.textContent.trim() || '';
        fecharEdicao();
    });

    botaoExcluir?.addEventListener('click', async () => {
        const ok = await mpsAlert.confirm({
            title: 'Excluir o domínio autorizado?',
            message: 'O embed é desativado e os códigos deixam de funcionar no seu site até você ativar de novo com uma nova URL.',
            confirmText: 'Excluir',
        });
        if (!ok) return;

        const sucesso = await desativar('Excluindo domínio…', 'Domínio excluído.');
        if (sucesso) {
            switchInput.checked = false;
            jaConfigurado = false;
        }
    });

    form.addEventListener('submit', async (ev) => {
        ev.preventDefault();

        const url = campoUrl.value.trim();
        if (!url) {
            mpsAlert.error('Informe a URL do seu site.');
            return;
        }

        botaoAtivar.classList.add('is-loading');
        botaoAtivar.disabled = true;
        mpsAlert.loading('Verificando se o site está no ar…');

        try {
            const data = await chamar({ ativar: true, url });
            if (!data.success) {
                mpsAlert.error(data.message || 'Não foi possível validar essa URL. Tente novamente.');
                return;
            }
            badge.textContent = 'Ativo';
            badge.classList.add('badge-primary');
            await mpsAlert.success('Domínio validado! Preparando os códigos de embed…');
            window.location.reload();
        } catch {
            mpsAlert.error('Erro na requisição. Tente novamente.');
        } finally {
            botaoAtivar.classList.remove('is-loading');
            botaoAtivar.disabled = false;
        }
    });
})();
