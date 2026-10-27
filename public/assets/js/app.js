/**
 * Comportamento do shell autenticado (sidebar + topbar).
 * Usado por todas as telas internas. Sem inline handlers.
 */

document.addEventListener('DOMContentLoaded', () => {
    initSidebarToggle();
    initSidebarSubmenus();
    initDropdowns();
    initLogout();
    initModals();
    initFilePickers();
});

/**
 * Seletor de arquivo estilizado — substitui o <input type="file"> nativo
 * (que não tem como estilizar de forma consistente entre navegadores).
 * Marcação:
 *   <div class="field-file-picker" data-file-picker>
 *     <input type="file" hidden ...>
 *     <button type="button" class="btn btn-secondary btn-sm" data-file-picker-trigger>Escolher arquivo</button>
 *     <span class="field-file-picker-name" data-file-picker-name>Nenhum arquivo selecionado</span>
 *   </div>
 */
function initFilePickers() {
    document.querySelectorAll('[data-file-picker]').forEach((wrap) => {
        const input   = wrap.querySelector('input[type="file"]');
        const trigger = wrap.querySelector('[data-file-picker-trigger]');
        const nameEl  = wrap.querySelector('[data-file-picker-name]');
        if (!input || !trigger || !nameEl) return;

        const placeholder = nameEl.textContent;
        trigger.addEventListener('click', () => input.click());
        input.addEventListener('change', () => {
            nameEl.textContent = input.files[0] ? input.files[0].name : placeholder;
        });
    });
}

/**
 * Modais genéricos (FAQ, Imobiliárias, Usuários, Campanhas...).
 * Abrir:  <button data-open="modal-id">
 * Fechar: <button data-close="modal-id"> ou clique no backdrop (mesmo atributo).
 */
function initModals() {
    document.querySelectorAll('[data-open]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const modal = document.getElementById(btn.dataset.open);
            if (modal) modal.hidden = false;
        });
    });

    document.querySelectorAll('[data-close]').forEach((el) => {
        el.addEventListener('click', () => {
            const modal = document.getElementById(el.dataset.close);
            if (modal) modal.hidden = true;
        });
    });

    document.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape') return;
        document.querySelectorAll('.modal:not([hidden])').forEach((modal) => (modal.hidden = true));
    });

    // Cards/linhas de campanha (dashboard, /campanhas): clique abre o popup
    // #modal-campanha-detalhe com a imagem em tamanho grande.
    document.addEventListener('click', (event) => {
        const item = event.target.closest('[data-abrir-campanha]');
        if (!item || !item.dataset.imagem) return;
        // Editar/Excluir (admin) ficam dentro do próprio card/linha clicável —
        // clicar neles não deve também abrir o popup da imagem.
        if (event.target.closest('.campaign-card-actions, .table-actions')) return;

        const modal   = document.getElementById('modal-campanha-detalhe');
        const img     = document.getElementById('campanha-detalhe-img');
        const obsBtn  = document.getElementById('campanha-detalhe-obs-btn');
        const obsTexto = document.getElementById('campanha-detalhe-obs-texto');
        if (!modal || !img) return;

        img.src = item.dataset.imagem;
        img.alt = item.dataset.titulo || '';
        if (obsBtn && obsTexto) {
            const texto = item.dataset.observacoes || '';
            obsTexto.textContent = texto;
            obsBtn.hidden = texto.trim() === '';
        }
        openModal('modal-campanha-detalhe');
    });
}

/** Abre um modal já existente no DOM pelo id (uso em JS de página). */
function openModal(id) {
    const modal = document.getElementById(id);
    if (modal) modal.hidden = false;
}

/** Fecha um modal pelo id. */
function closeModal(id) {
    const modal = document.getElementById(id);
    if (modal) modal.hidden = true;
}

/** Submenus colapsáveis da sidebar (ex: "Seguro Fiança" agrupando produtos). */
function initSidebarSubmenus() {
    document.querySelectorAll('[data-toggle="submenu"]').forEach((toggle) => {
        toggle.addEventListener('click', () => {
            const panel = document.getElementById(toggle.getAttribute('aria-controls'));
            const willOpen = !panel.classList.contains('is-open');

            panel.classList.toggle('is-open', willOpen);
            toggle.setAttribute('aria-expanded', String(willOpen));
        });
    });
}

/**
 * Liga qualquer `.app-dropdown` (menu do usuário, notificações, etc.) ao seu
 * gatilho `[data-toggle]`. Abrir um fecha os demais; fecha ao clicar fora ou Esc.
 */
function initDropdowns() {
    const dropdowns = document.querySelectorAll('.app-dropdown');
    if (!dropdowns.length) return;

    const closeAll = () => {
        dropdowns.forEach((dropdown) => {
            dropdown.classList.remove('is-open');
            dropdown.querySelector('[data-toggle]')?.setAttribute('aria-expanded', 'false');
        });
    };

    dropdowns.forEach((dropdown) => {
        const trigger = dropdown.querySelector('[data-toggle]');
        if (!trigger) return;

        trigger.addEventListener('click', (event) => {
            event.stopPropagation();
            const willOpen = !dropdown.classList.contains('is-open');
            closeAll();
            if (willOpen) {
                dropdown.classList.add('is-open');
                trigger.setAttribute('aria-expanded', 'true');
            }
        });
    });

    document.addEventListener('click', (event) => {
        const isInsideAny = [...dropdowns].some((d) => d.contains(event.target));
        if (!isInsideAny) closeAll();
    });

    document.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') closeAll();
    });
}

function initSidebarToggle() {
    const sidebar = document.querySelector('.app-sidebar');
    const overlay = document.querySelector('.app-sidebar-overlay');
    // O overlay também tem data-toggle="sidebar" e vem antes do botão no DOM
    // (sidebar.php é incluído antes da topbar.php), então um querySelector por
    // [data-toggle="sidebar"] pegaria o overlay e o botão ficaria sem listener.
    const toggleBtn = document.querySelector('.app-sidebar-toggle');

    if (!sidebar || !overlay || !toggleBtn) return;

    const open = () => {
        sidebar.classList.add('is-open');
        overlay.classList.add('is-open');
    };

    const close = () => {
        sidebar.classList.remove('is-open');
        overlay.classList.remove('is-open');
    };

    toggleBtn.addEventListener('click', open);
    overlay.addEventListener('click', close);
}

function initLogout() {
    const logoutBtn = document.querySelector('[data-action="logout"]');
    if (!logoutBtn) return;

    logoutBtn.addEventListener('click', async () => {
        const ok = await mpsAlert.confirm({
            title: 'Encerrar sessão?',
            message: 'Você precisará fazer login novamente para acessar o sistema.',
            confirmText: 'Sair',
            cancelText: 'Cancelar',
        });

        if (!ok) return;

        mpsAlert.loading('Encerrando sessão...');

        try {
            await fetch('/logout', { method: 'POST' });
            window.location.href = appUrl('/login');
        } catch (err) {
            mpsAlert.error('Não foi possível encerrar a sessão. Tente novamente.');
        }
    });
}
