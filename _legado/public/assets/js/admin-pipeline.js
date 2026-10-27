/**
 * Pipeline (Kanban) — admin-pipeline.js
 * Filtros de data, imobiliária e atendente recarregam o Kanban via AJAX
 * (/api/admin/pipeline/listar) — a URL nunca muda, mesmo padrão de cotacoes.js.
 */
(function () {
    'use strict';

    const kanban    = document.getElementById('pipeline-kanban');
    const inputDe    = document.getElementById('pipeline-data-inicio');
    const inputAte   = document.getElementById('pipeline-data-fim');
    const btnLimpar  = document.getElementById('btn-limpar-pipeline');

    if (!kanban || !inputDe || !inputAte) return;

    const dataInicioPadrao = inputDe.value;
    const dataFimPadrao    = inputAte.value;

    const imobHidden  = document.querySelector('input[name="imobiliaria_id"]');
    const atendHidden = document.querySelector('input[name="atendente_id"]');

    function getFiltros() {
        return {
            data_inicio:     inputDe.value,
            data_fim:        inputAte.value,
            imobiliaria_id:  imobHidden  ? imobHidden.value  : '',
            atendente_id:    atendHidden ? atendHidden.value : '',
        };
    }

    function temFiltroAtivo() {
        const f = getFiltros();
        return f.imobiliaria_id || f.atendente_id
            || f.data_inicio !== dataInicioPadrao
            || f.data_fim    !== dataFimPadrao;
    }

    function carregar() {
        const params = new URLSearchParams(getFiltros());
        kanban.querySelectorAll('.pipeline-col-body').forEach((body) => {
            body.innerHTML = '<div class="empty-state pipeline-empty"><p>Carregando...</p></div>';
        });
        btnLimpar.hidden = !temFiltroAtivo();

        fetch('/api/admin/pipeline/listar?' + params.toString())
            .then((r) => r.json())
            .then((data) => {
                kanban.innerHTML = data.html || '';
            })
            .catch(() => {
                kanban.querySelectorAll('.pipeline-col-body').forEach((body) => {
                    body.innerHTML = '<div class="empty-state pipeline-empty"><p>Erro ao carregar dados.</p></div>';
                });
            });
    }

    function resetCustomSelect(hidden) {
        if (!hidden) return;
        const wrapper = hidden.closest('[data-custom-select]');
        if (!wrapper) return;
        const placeholder = wrapper.dataset.placeholder || 'Todos';
        const valueEl = wrapper.querySelector('.custom-select-value');
        valueEl.textContent = placeholder;
        valueEl.classList.add('is-placeholder');
        wrapper.querySelectorAll('.custom-select-option').forEach((opt) => {
            opt.classList.toggle('is-selected', opt.dataset.value === '');
        });
        hidden.value = '';
    }

    // Mesmo padrão do "Limpar filtros" de cotacoes.js: mexe direto no hidden +
    // no label visível do date-picker, sem depender do estado interno dele
    // (date-picker.js não expõe API pra isso).
    function resetDatePicker(hiddenInput, isoValor) {
        const wrapper = hiddenInput.closest('[data-date-picker]');
        const valueEl = wrapper ? wrapper.querySelector('.date-picker-value') : null;
        hiddenInput.value = isoValor;
        if (valueEl) {
            const [y, m, d] = isoValor.split('-');
            valueEl.textContent = `${d}/${m}/${y}`;
            valueEl.classList.remove('is-placeholder');
        }
    }

    // ── Filtros — recarregam ao mudar ────────────────────────────────────────
    inputDe.addEventListener('change', carregar);
    inputAte.addEventListener('change', carregar);
    if (imobHidden)  imobHidden.addEventListener('change', carregar);
    if (atendHidden) atendHidden.addEventListener('change', carregar);

    // ── Limpar filtros ───────────────────────────────────────────────────────
    btnLimpar.addEventListener('click', () => {
        resetDatePicker(inputDe, dataInicioPadrao);
        resetDatePicker(inputAte, dataFimPadrao);
        resetCustomSelect(imobHidden);
        resetCustomSelect(atendHidden);
        carregar();
    });

    // ── Navegação lateral da faixa — sem barra de rolagem visível ────────────
    // Arrastar com o mouse (segurar e puxar pro lado) e Shift + roda do mouse.
    // O arrasto só vira rolagem depois de alguns pixels, pra não engolir os
    // cliques em "Ver cotação".
    const LIMIAR_ARRASTO = 5;
    let arrastando = false;
    let moveu      = false;
    let xInicial   = 0;
    let scrollInicial = 0;

    kanban.addEventListener('mousedown', (e) => {
        if (e.button !== 0) return;
        arrastando    = true;
        moveu         = false;
        xInicial      = e.pageX;
        scrollInicial = kanban.scrollLeft;
    });

    document.addEventListener('mousemove', (e) => {
        if (!arrastando) return;
        const dx = e.pageX - xInicial;
        if (!moveu && Math.abs(dx) < LIMIAR_ARRASTO) return;
        moveu = true;
        kanban.classList.add('is-arrastando');
        kanban.scrollLeft = scrollInicial - dx;
        e.preventDefault();
    });

    document.addEventListener('mouseup', () => {
        if (!arrastando) return;
        arrastando = false;
        kanban.classList.remove('is-arrastando');
        // O clique dispara logo depois do mouseup; só então a flag é zerada,
        // pra não sobrar ligada e engolir um clique seguinte (teclado, por ex.).
        setTimeout(() => { moveu = false; }, 0);
    });

    // Depois de arrastar, cancela o clique que o mouseup dispararia no card.
    kanban.addEventListener('click', (e) => {
        if (!moveu) return;
        e.preventDefault();
        e.stopPropagation();
        moveu = false;
    }, true);

    kanban.addEventListener('wheel', (e) => {
        if (!e.shiftKey || !e.deltaY) return;
        kanban.scrollLeft += e.deltaY;
        e.preventDefault();
    }, { passive: false });

    // ── Carga inicial ─────────────────────────────────────────────────────────
    carregar();
})();
