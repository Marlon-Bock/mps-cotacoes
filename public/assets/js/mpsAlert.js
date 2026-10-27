/**
 * mpsAlert — modal de feedback próprio do sistema MPS.
 * Substitui qualquer uso de SweetAlert2 ou libs externas de alerta.
 *
 * Uso obrigatório em TODA ação de carregar/salvar/editar/excluir/logar/criar:
 *
 *   mpsAlert.loading('Validando...');
 *   mpsAlert.close();
 *   mpsAlert.success('Cotação enviada com sucesso!');
 *   mpsAlert.error('Não foi possível salvar. Tente novamente.');
 *   const ok = await mpsAlert.confirm({ message: 'Excluir este rascunho?' });
 *   mpsAlert.toast('success', 'Alterações salvas.');
 */

const mpsAlert = (() => {
    let overlay = null;
    let card = null;
    let autoCloseTimer = null;

    /**
     * Ícones Phosphor embutidos como SVG (mesmo conjunto de public/assets/icons/).
     * Embutidos aqui porque este módulo é JS puro, sem acesso ao helper PHP icon().
     */
    const ICONS = {
        check:   '<svg class="icon" viewBox="0 0 256 256" fill="currentColor" aria-hidden="true"><path d="M229.66,77.66l-128,128a8,8,0,0,1-11.32,0l-56-56a8,8,0,0,1,11.32-11.32L96,188.69,218.34,66.34a8,8,0,0,1,11.32,11.32Z"/></svg>',
        x:       '<svg class="icon" viewBox="0 0 256 256" fill="currentColor" aria-hidden="true"><path d="M205.66,194.34a8,8,0,0,1-11.32,11.32L128,139.31,61.66,205.66a8,8,0,0,1-11.32-11.32L116.69,128,50.34,61.66A8,8,0,0,1,61.66,50.34L128,116.69l66.34-66.35a8,8,0,0,1,11.32,11.32L139.31,128Z"/></svg>',
        warning: '<svg class="icon" viewBox="0 0 256 256" fill="currentColor" aria-hidden="true"><path d="M236.8,188.09,149.35,36.22h0a24.76,24.76,0,0,0-42.7,0L19.2,188.09a23.51,23.51,0,0,0,0,23.72A24.35,24.35,0,0,0,40.55,224h174.9a24.35,24.35,0,0,0,21.33-12.19A23.51,23.51,0,0,0,236.8,188.09ZM222.93,203.8a8.5,8.5,0,0,1-7.48,4.2H40.55a8.5,8.5,0,0,1-7.48-4.2,7.59,7.59,0,0,1,0-7.72L120.52,44.21a8.75,8.75,0,0,1,15,0l87.45,151.87A7.59,7.59,0,0,1,222.93,203.8ZM120,144V104a8,8,0,0,1,16,0v40a8,8,0,0,1-16,0Zm20,36a12,12,0,1,1-12-12A12,12,0,0,1,140,180Z"/></svg>',
        info:    '<svg class="icon" viewBox="0 0 256 256" fill="currentColor" aria-hidden="true"><path d="M128,24A104,104,0,1,0,232,128,104.11,104.11,0,0,0,128,24Zm0,192a88,88,0,1,1,88-88A88.1,88.1,0,0,1,128,216Zm16-40a8,8,0,0,1-8,8,16,16,0,0,1-16-16V128a8,8,0,0,1,0-16,16,16,0,0,1,16,16v40A8,8,0,0,1,144,176ZM112,84a12,12,0,1,1,12,12A12,12,0,0,1,112,84Z"/></svg>',
        question:'<svg class="icon" viewBox="0 0 256 256" fill="currentColor" aria-hidden="true"><path d="M140,180a12,12,0,1,1-12-12A12,12,0,0,1,140,180ZM128,72c-22.06,0-40,16.15-40,36v4a8,8,0,0,0,16,0v-4c0-11,10.77-20,24-20s24,9,24,20-10.77,20-24,20a8,8,0,0,0-8,8v8a8,8,0,0,0,16,0v-.72c18.24-3.35,32-17.9,32-35.28C168,88.15,150.06,72,128,72Zm104,56A104,104,0,1,1,128,24,104.11,104.11,0,0,1,232,128Zm-16,0a88,88,0,1,0-88,88A88.1,88.1,0,0,0,216,128Z"/></svg>',
    };

    function ensureOverlay() {
        if (overlay) return overlay;

        overlay = document.createElement('div');
        overlay.className = 'mps-alert-overlay';
        overlay.setAttribute('role', 'alertdialog');
        overlay.setAttribute('aria-modal', 'true');

        card = document.createElement('div');
        card.className = 'mps-alert-card';
        overlay.appendChild(card);

        document.body.appendChild(overlay);
        return overlay;
    }

    function render(html) {
        ensureOverlay();
        card.innerHTML = html;
        requestAnimationFrame(() => overlay.classList.add('is-open'));
    }

    function clearAutoClose() {
        if (autoCloseTimer) {
            clearTimeout(autoCloseTimer);
            autoCloseTimer = null;
        }
    }

    function close() {
        clearAutoClose();
        if (overlay) overlay.classList.remove('is-open');
    }

    /**
     * Renderiza conteúdo arbitrário no mesmo overlay/card — para fluxos
     * próprios (ex: seletor de arquivo com drag-and-drop) que depois
     * seguem para loading()/success()/error() no mesmo modal.
     * onMount(card, close) é chamado logo após renderizar, pra quem
     * chamou plugar seus próprios listeners.
     */
    function custom(html, { onMount } = {}) {
        clearAutoClose();
        render(html);
        if (typeof onMount === 'function') onMount(card, close);
    }

    /** Exibe estado de carregamento bloqueante. Não fecha automaticamente. */
    function loading(message = 'Carregando...') {
        clearAutoClose();
        render(`
            <div class="mps-alert-spinner" aria-hidden="true"></div>
            <p class="mps-alert-message">${escapeHtml(message)}</p>
        `);
    }

    /**
     * Exibe sucesso. Fecha automaticamente após autoCloseMs (padrão 2200ms).
     * Retorna uma Promise que só resolve quando o alerta fecha — quem chama
     * com `await` (ex: antes de redirecionar) espera o usuário ver a mensagem.
     */
    function success(message, { title = 'Sucesso', autoCloseMs = 2200, html = false } = {}) {
        clearAutoClose();
        render(`
            <div class="mps-alert-icon mps-alert-icon-success">${ICONS.check}</div>
            <h3 class="mps-alert-title">${escapeHtml(title)}</h3>
            <p class="mps-alert-message">${html ? message : escapeHtml(message)}</p>
        `);

        return new Promise((resolve) => {
            if (autoCloseMs > 0) {
                autoCloseTimer = setTimeout(() => { close(); resolve(); }, autoCloseMs);
            } else {
                resolve();
            }
        });
    }

    /** Exibe erro. Requer fechamento manual (botão "Entendi"). */
    function error(message, { title = 'Algo deu errado' } = {}) {
        clearAutoClose();
        render(`
            <div class="mps-alert-icon mps-alert-icon-error">${ICONS.x}</div>
            <h3 class="mps-alert-title">${escapeHtml(title)}</h3>
            <p class="mps-alert-message">${escapeHtml(message)}</p>
            <div class="mps-alert-actions">
                <button type="button" class="btn btn-primary" data-mps-alert-close>Entendi</button>
            </div>
        `);

        card.querySelector('[data-mps-alert-close]').addEventListener('click', close);
    }

    /**
     * Pede confirmação antes de uma ação destrutiva (excluir, recusar, etc.).
     * Retorna uma Promise<boolean>.
     */
    function confirm({
        title = 'Tem certeza?',
        message = 'Esta ação não poderá ser desfeita.',
        confirmText = 'Confirmar',
        cancelText = 'Cancelar',
        danger = true,
        icon = 'question',
        html = false,   // true só quando a mensagem já é HTML montado pelo próprio código
    } = {}) {
        clearAutoClose();

        const iconKey  = ICONS[icon] ? icon : 'question';
        const iconType = iconKey;

        return new Promise((resolve) => {
            render(`
                <div class="mps-alert-icon mps-alert-icon-${iconType}">${ICONS[iconKey]}</div>
                <h3 class="mps-alert-title">${escapeHtml(title)}</h3>
                <p class="mps-alert-message">${html ? message : escapeHtml(message)}</p>
                <div class="mps-alert-actions">
                    <button type="button" class="btn btn-secondary" data-mps-alert-cancel>${escapeHtml(cancelText)}</button>
                    <button type="button" class="btn ${danger ? 'btn-primary' : 'btn-primary'}" data-mps-alert-confirm>${escapeHtml(confirmText)}</button>
                </div>
            `);

            card.querySelector('[data-mps-alert-cancel]').addEventListener('click', () => {
                close();
                resolve(false);
            });

            card.querySelector('[data-mps-alert-confirm]').addEventListener('click', () => {
                close();
                resolve(true);
            });
        });
    }

    /**
     * Pede um texto ao usuário (substitui window.prompt).
     * Retorna uma Promise<string|null> — null quando cancelado.
     */
    function prompt({
        title = 'Informe um valor',
        message = '',
        placeholder = '',
        defaultValue = '',
        confirmText = 'OK',
        cancelText = 'Cancelar',
        icon = 'question',
        multiline = false,
        rows = 4,
        required = false,
    } = {}) {
        clearAutoClose();

        // Só success/error/warning/info têm classe de cor em CSS — "question"
        // (padrão histórico deste modal) sempre usou o círculo de "warning"
        // com o ícone de interrogação dentro.
        const coresComEstilo = ['success', 'error', 'warning', 'info'];
        const svgKey  = ICONS[icon] ? icon : 'question';
        const corKey  = coresComEstilo.includes(icon) ? icon : 'warning';
        const campoHtml = multiline
            ? `<textarea class="field-input mps-alert-input mps-alert-textarea" data-mps-alert-input
                         rows="${rows}" placeholder="${escapeHtml(placeholder)}">${escapeHtml(defaultValue)}</textarea>`
            : `<input type="text" class="field-input mps-alert-input" data-mps-alert-input
                      placeholder="${escapeHtml(placeholder)}" value="${escapeHtml(defaultValue)}">`;

        return new Promise((resolve) => {
            render(`
                <div class="mps-alert-icon mps-alert-icon-${corKey}">${ICONS[svgKey]}</div>
                <h3 class="mps-alert-title">${escapeHtml(title)}</h3>
                ${message ? `<p class="mps-alert-message">${escapeHtml(message)}</p>` : ''}
                ${campoHtml}
                <span class="mps-alert-field-error" data-mps-alert-error hidden>Preencha este campo pra continuar.</span>
                <div class="mps-alert-actions">
                    <button type="button" class="btn btn-secondary" data-mps-alert-cancel>${escapeHtml(cancelText)}</button>
                    <button type="button" class="btn btn-primary" data-mps-alert-confirm>${escapeHtml(confirmText)}</button>
                </div>
            `);

            const input = card.querySelector('[data-mps-alert-input]');
            const erro  = card.querySelector('[data-mps-alert-error]');
            input.focus();
            if (!multiline) input.select();

            const finish = (value) => { close(); resolve(value); };
            const tentarConfirmar = () => {
                if (required && !input.value.trim()) {
                    erro.hidden = false;
                    input.classList.add('has-error');
                    input.focus();
                    return;
                }
                finish(input.value);
            };

            card.querySelector('[data-mps-alert-cancel]').addEventListener('click', () => finish(null));
            card.querySelector('[data-mps-alert-confirm]').addEventListener('click', tentarConfirmar);
            input.addEventListener('input', () => { erro.hidden = true; input.classList.remove('has-error'); });
            input.addEventListener('keydown', (e) => {
                if (e.key === 'Enter' && !multiline) tentarConfirmar();
                if (e.key === 'Escape') finish(null);
            });
        });
    }

    /**
     * Toast curto no canto da tela — para confirmações que não bloqueiam o
     * fluxo. `onClick` opcional: toast inteiro vira clicável (menos o botão
     * de fechar), útil pra notificações que levam pra algum lugar.
     */
    function toast(type = 'info', message = '', { durationMs = 4000, onClick = null } = {}) {
        const stack = ensureToastStack();

        const toastIcons = { success: ICONS.check, error: ICONS.x, warning: ICONS.warning, info: ICONS.info };

        const el = document.createElement('div');
        el.className = 'mps-toast' + (onClick ? ' is-clickable' : '');
        el.innerHTML = `
            <span class="mps-toast-icon mps-toast-icon-${type}">${toastIcons[type] || toastIcons.info}</span>
            <span class="mps-toast-body">${escapeHtml(message)}</span>
            <button type="button" class="mps-toast-close" aria-label="Fechar">${ICONS.x.replace('class="icon"', 'class="icon icon-sm"')}</button>
        `;

        stack.appendChild(el);
        requestAnimationFrame(() => el.classList.add('is-open'));

        const remove = () => {
            el.classList.remove('is-open');
            setTimeout(() => el.remove(), 250);
        };

        el.querySelector('.mps-toast-close').addEventListener('click', (e) => {
            e.stopPropagation();
            remove();
        });
        if (onClick) {
            el.addEventListener('click', () => { onClick(); remove(); });
        }
        setTimeout(remove, durationMs);
    }

    function ensureToastStack() {
        let stack = document.querySelector('.mps-toast-stack');
        if (!stack) {
            stack = document.createElement('div');
            stack.className = 'mps-toast-stack';
            document.body.appendChild(stack);
        }
        return stack;
    }

    function escapeHtml(value) {
        const div = document.createElement('div');
        div.textContent = value;
        return div.innerHTML;
    }

    return { loading, success, error, confirm, prompt, toast, close, custom };
})();

// `const` no topo do script não vira propriedade de `window` (só `var` faz
// isso) — outros módulos que checam `window.mpsAlert` antes de usar (ex.:
// notify-sound.js) sempre viam `undefined` sem essa linha, mesmo com
// mpsAlert funcionando normal via referência direta ao identificador.
window.mpsAlert = mpsAlert;
