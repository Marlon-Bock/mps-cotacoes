/**
 * Financeiro (/admin/financeiro) — mesmo esquema da "Todas as Cotações":
 * filtros aplicam sozinhos (AJAX), busca com debounce, "Limpar filtros"
 * quando há filtro e a planilha (.xlsx) com os filtros atuais.
 */
(function () {
    'use strict';

    const linhas      = document.getElementById('fin-rows');
    const resumo      = document.getElementById('fin-resumo');
    const seguradoras = document.getElementById('fin-seguradoras');
    const painelSeg   = document.getElementById('fin-seguradoras-painel');
    const contagem    = document.getElementById('fin-contagem');
    const btnLimpar   = document.getElementById('btn-limpar');
    const btnExportar = document.getElementById('btn-exportar');
    const busca       = document.getElementById('f-busca');
    const dataDe      = document.getElementById('f-data-de');
    const dataAte     = document.getElementById('f-data-ate');
    const selects     = {
        seguradora:  document.querySelector('#f-seguradora-wrap input[type="hidden"]'),
        imobiliaria: document.querySelector('#f-imobiliaria-wrap input[type="hidden"]'),
        situacao:    document.querySelector('#f-situacao-wrap input[type="hidden"]'),
    };
    const urlExportar = btnExportar.getAttribute('href');
    const btnPdf      = document.getElementById('btn-pdf');
    const urlPdf      = btnPdf.getAttribute('href');
    let debounce = null;
    let pedido   = 0;

    function filtros() {
        return {
            busca:       busca.value.trim(),
            de:          dataDe.value,
            ate:         dataAte.value,
            seguradora:  selects.seguradora.value,
            imobiliaria: selects.imobiliaria.value,
            situacao:    selects.situacao.value,
        };
    }

    function query() {
        const f = filtros();
        return new URLSearchParams(Object.fromEntries(Object.entries(f).filter(([, v]) => v !== ''))).toString();
    }

    function carregar() {
        const q = query();
        const meu = ++pedido;
        btnLimpar.style.display = q ? '' : 'none';
        btnExportar.setAttribute('href', urlExportar + (q ? '?' + q : ''));
        btnPdf.setAttribute('href', urlPdf + (q ? '?' + q : ''));
        linhas.innerHTML = '<div class="admin-cot-empty">Carregando...</div>';

        fetch('/api/admin/financeiro/listar' + (q ? '?' + q : ''))
            .then(r => r.json())
            .then(d => {
                if (meu !== pedido) return; // chegou uma resposta mais nova
                resumo.innerHTML      = d.resumo || '';
                seguradoras.innerHTML = d.seguradoras || '';
                painelSeg.hidden      = !(d.seguradoras || '').trim();
                contagem.textContent  = d.total ? `${d.total} ${d.total === 1 ? 'contratação' : 'contratações'}` : '';
                linhas.innerHTML      = d.html || '';
            })
            .catch(() => {
                if (meu === pedido) linhas.innerHTML = '<div class="admin-cot-empty">Erro ao carregar dados.</div>';
            });
    }

    busca.addEventListener('input', () => {
        clearTimeout(debounce);
        debounce = setTimeout(carregar, 350);
    });
    [dataDe, dataAte, ...Object.values(selects)].forEach(el => el.addEventListener('change', carregar));

    // ── Limpar filtros (mesmo reset da "Todas as Cotações") ──
    btnLimpar.addEventListener('click', () => {
        busca.value = '';
        document.querySelectorAll('[data-date-picker]').forEach(dp => {
            const valor = dp.querySelector('.date-picker-value');
            dp.querySelector('input[type="hidden"]').value = '';
            valor.textContent = dp.dataset.placeholder || 'DD/MM/AAAA';
            valor.classList.add('is-placeholder');
        });
        Object.values(selects).forEach(hidden => {
            const wrapper = hidden.closest('[data-custom-select]');
            const valor   = wrapper.querySelector('.custom-select-value');
            valor.textContent = wrapper.dataset.placeholder || 'Todos';
            valor.classList.add('is-placeholder');
            wrapper.querySelectorAll('.custom-select-option').forEach(o => o.classList.toggle('is-selected', o.dataset.value === ''));
            hidden.value = '';
        });
        carregar();
    });

    carregar();
})();
