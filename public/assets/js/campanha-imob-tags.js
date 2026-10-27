/**
 * Segmentação de campanha (criar/editar) — campo de busca + chips no lugar
 * de uma lista enorme de imobiliárias sempre visível. Digita, escolhe da
 * sugestão, vira uma chip removível; o valor vai pro form como
 * imobiliarias[] via um <input type="hidden"> criado por chip.
 */
(function () {
    'use strict';

    function escapeHtml(str) {
        const div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function initImobTags(container) {
        const dadosScript = document.getElementById('campanha-imob-dados');
        const chipsEl      = container.querySelector('.imob-tags-chips');
        const inputEl      = container.querySelector('.imob-tags-search input');
        const sugestoesEl  = container.querySelector('.imob-tags-suggestions');
        if (!dadosScript || !chipsEl || !inputEl || !sugestoesEl) return;

        let todas = [];
        try { todas = JSON.parse(dadosScript.textContent || '[]'); } catch (_) { todas = []; }

        const iniciais = (container.dataset.selecionadas || '')
            .split(',')
            .map((v) => parseInt(v, 10))
            .filter((v) => !isNaN(v));

        const selecionadas = new Map();
        todas.forEach((imob) => {
            if (iniciais.includes(imob.id)) selecionadas.set(imob.id, imob.nome);
        });

        function renderChips() {
            chipsEl.innerHTML = '';
            selecionadas.forEach((nome, id) => {
                const chip = document.createElement('span');
                chip.className = 'imob-tag';
                chip.innerHTML =
                    `<input type="hidden" name="imobiliarias[]" value="${id}">` +
                    `<span class="imob-tag-nome">${escapeHtml(nome)}</span>` +
                    `<button type="button" class="imob-tag-remove" data-id="${id}" aria-label="Remover ${escapeHtml(nome)}">&times;</button>`;
                chipsEl.appendChild(chip);
            });
        }

        function fecharSugestoes() {
            sugestoesEl.hidden = true;
            sugestoesEl.innerHTML = '';
        }

        function renderSugestoes(termo) {
            const termoLower = termo.trim().toLowerCase();
            if (!termoLower) { fecharSugestoes(); return; }

            const resultados = todas
                .filter((imob) => !selecionadas.has(imob.id) && imob.nome.toLowerCase().includes(termoLower))
                .slice(0, 8);

            if (!resultados.length) {
                sugestoesEl.innerHTML = '<li class="imob-tags-suggestion-empty">Nenhuma imobiliária encontrada.</li>';
                sugestoesEl.hidden = false;
                return;
            }

            sugestoesEl.innerHTML = resultados
                .map((imob) => `<li class="imob-tags-suggestion-item" data-id="${imob.id}">${escapeHtml(imob.nome)}</li>`)
                .join('');
            sugestoesEl.hidden = false;
        }

        inputEl.addEventListener('input', () => renderSugestoes(inputEl.value));
        inputEl.addEventListener('focus', () => {
            if (inputEl.value.trim()) renderSugestoes(inputEl.value);
        });

        sugestoesEl.addEventListener('click', (e) => {
            const item = e.target.closest('.imob-tags-suggestion-item');
            if (!item) return;
            const id   = parseInt(item.dataset.id, 10);
            const imob = todas.find((i) => i.id === id);
            if (!imob) return;

            selecionadas.set(id, imob.nome);
            renderChips();
            inputEl.value = '';
            fecharSugestoes();
            inputEl.focus();
        });

        chipsEl.addEventListener('click', (e) => {
            const btn = e.target.closest('.imob-tag-remove');
            if (!btn) return;
            selecionadas.delete(parseInt(btn.dataset.id, 10));
            renderChips();
        });

        document.addEventListener('click', (e) => {
            if (!container.contains(e.target)) fecharSugestoes();
        });

        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') fecharSugestoes();
        });

        renderChips();
    }

    document.querySelectorAll('.imob-tags').forEach(initImobTags);
})();
