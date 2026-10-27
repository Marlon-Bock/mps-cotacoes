/**
 * Custom Select — inicializa todos os [data-custom-select] na página.
 * Suporta: teclado (↑↓ Enter Esc), fechar ao clicar fora, ícone à esquerda.
 * Não depende de nenhuma lib externa.
 */
(function () {
    'use strict';

    function initSelect(wrapper) {
        if (wrapper.dataset.customSelectReady) return;
        wrapper.dataset.customSelectReady = '1';

        const trigger   = wrapper.querySelector('.custom-select-trigger');
        const dropdown  = wrapper.querySelector('.custom-select-dropdown');
        const valueEl   = wrapper.querySelector('.custom-select-value');
        const hidden    = wrapper.querySelector('input[type="hidden"]');
        const options   = Array.from(wrapper.querySelectorAll('.custom-select-option'));
        const placeholder = wrapper.dataset.placeholder || 'Selecione...';

        if (!trigger || !dropdown || !valueEl || !hidden) return;

        let focusedIdx = -1;

        function open() {
            const live        = getLiveOptions();
            const triggerRect = trigger.getBoundingClientRect();
            const dropdownH   = Math.min(232, live.length * 36 + 8);
            const spaceBelow  = window.innerHeight - triggerRect.bottom;
            wrapper.classList.toggle('is-flipped', spaceBelow < dropdownH && triggerRect.top > dropdownH);

            wrapper.classList.add('is-open');
            trigger.setAttribute('aria-expanded', 'true');
            const selIdx = live.findIndex(o => o.classList.contains('is-selected'));
            focusedIdx = selIdx >= 0 ? selIdx : 0;
            highlightOption(focusedIdx);
            dropdown.scrollTop = 0;
        }

        function close() {
            wrapper.classList.remove('is-open', 'is-flipped');
            trigger.setAttribute('aria-expanded', 'false');
            focusedIdx = -1;
            getLiveOptions().forEach(o => o.classList.remove('is-focused'));
        }

        function select(option) {
            const val   = option.dataset.value;
            const label = option.textContent.trim();
            const isPlaceholder = val === '' || option.classList.contains('is-placeholder');

            getLiveOptions().forEach(o => o.classList.remove('is-selected'));
            option.classList.add('is-selected');

            hidden.value = val;
            hidden.dispatchEvent(new Event('change', { bubbles: true }));

            valueEl.textContent = label;
            valueEl.classList.toggle('is-placeholder', isPlaceholder);

            // Limpa estado de erro se houver
            trigger.classList.remove('has-error');
            wrapper.closest('.field')?.querySelector('.field-error')?.setAttribute('hidden', '');

            close();
            trigger.focus();
        }

        function getLiveOptions() {
            return Array.from(wrapper.querySelectorAll('.custom-select-option'));
        }

        function highlightOption(idx) {
            const live = getLiveOptions();
            live.forEach(o => o.classList.remove('is-focused'));
            if (live[idx]) {
                live[idx].classList.add('is-focused');
                live[idx].scrollIntoView({ block: 'nearest' });
            }
        }

        // Toggle ao clicar no trigger
        trigger.addEventListener('click', (e) => {
            e.stopPropagation();
            wrapper.classList.contains('is-open') ? close() : open();
        });

        // Selecionar opção ao clicar — event delegation para suportar opções dinâmicas (FIPE)
        dropdown.addEventListener('click', (e) => {
            e.stopPropagation();
            const opt = e.target.closest('.custom-select-option');
            if (opt) select(opt);
        });

        dropdown.addEventListener('mouseover', (e) => {
            const opt = e.target.closest('.custom-select-option');
            if (!opt) return;
            const live = getLiveOptions();
            focusedIdx = live.indexOf(opt);
            live.forEach(o => o.classList.remove('is-focused'));
            opt.classList.add('is-focused');
        });

        // Navegação por teclado
        trigger.addEventListener('keydown', (e) => {
            const isOpen = wrapper.classList.contains('is-open');

            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                if (!isOpen) { open(); return; }
                const live = getLiveOptions();
                focusedIdx = e.key === 'ArrowDown'
                    ? Math.min(focusedIdx + 1, live.length - 1)
                    : Math.max(focusedIdx - 1, 0);
                highlightOption(focusedIdx);
            } else if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                if (!isOpen) { open(); return; }
                const live = getLiveOptions();
                if (focusedIdx >= 0) select(live[focusedIdx]);
            } else if (e.key === 'Escape') {
                close();
            } else if (e.key === 'Tab') {
                if (isOpen) close();
            }
        });

        // Fechar ao clicar fora
        document.addEventListener('click', (e) => {
            if (!wrapper.contains(e.target)) close();
        });

        // Impede que o trigger perca o foco ao clicar dentro do dropdown.
        // Sem isso: mousedown na opção → blur no trigger → focusout → close()
        // antes do click na opção registrar.
        dropdown.addEventListener('mousedown', (e) => {
            e.preventDefault();
        });

        // Fechar ao perder o foco para fora do componente
        wrapper.addEventListener('focusout', (e) => {
            if (!wrapper.contains(e.relatedTarget)) close();
        });
    }

    function initAll(root) {
        root.querySelectorAll('[data-custom-select]').forEach(initSelect);
    }

    // Init ao carregar a página
    document.addEventListener('DOMContentLoaded', () => initAll(document));

    // Expõe para re-init dinâmico (ex: conteúdo carregado via JS)
    window.customSelectInit = initAll;
})();
