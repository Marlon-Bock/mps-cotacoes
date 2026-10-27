/**
 * Auditoria (/admin/auditoria) — mesmo esquema do Financeiro: filtros aplicam
 * sozinhos (AJAX), busca com debounce, "Limpar filtros" e paginação. Clicar na
 * linha abre os detalhes (dados enviados e o antes/depois de cada alteração).
 */
(function () {
    'use strict';

    const linhas      = document.getElementById('aud-rows');
    const btnLimpar   = document.getElementById('btn-limpar');
    const pagInfo     = document.getElementById('pag-info');
    const btnAnterior = document.getElementById('pag-anterior');
    const btnProxima  = document.getElementById('pag-proxima');
    const textos      = ['f-quem', 'f-busca', 'f-tabela', 'f-registro', 'f-ip'].map(id => document.getElementById(id));
    const datas       = ['f-de', 'f-ate'].map(id => document.getElementById(id));
    const chaves      = { so_alteracoes: document.getElementById('f-so-alteracoes'), senha_mestra: document.getElementById('f-senha-mestra') };
    const selects     = {
        tipo:        document.querySelector('#f-tipo-wrap input[type="hidden"]'),
        imobiliaria: document.querySelector('#f-imobiliaria-wrap input[type="hidden"]'),
    };
    let pagina   = 1;
    let paginas  = 1;
    let debounce = null;
    let pedido   = 0;

    function filtros() {
        return {
            quem:          textos[0].value.trim(),
            busca:         textos[1].value.trim(),
            tabela:        textos[2].value.trim(),
            registro:      textos[3].value.trim(),
            ip:            textos[4].value.trim(),
            de:            datas[0].value,
            ate:           datas[1].value,
            tipo:          selects.tipo.value,
            imobiliaria:   selects.imobiliaria.value,
            so_alteracoes: chaves.so_alteracoes.checked ? '1' : '',
            senha_mestra:  chaves.senha_mestra.checked ? '1' : '',
        };
    }

    function carregar() {
        const f = filtros();
        const temFiltro = Object.values(f).some(v => v !== '');
        const q = new URLSearchParams(Object.fromEntries(Object.entries(f).filter(([, v]) => v !== '')));
        q.set('page', pagina);
        const meu = ++pedido;
        btnLimpar.hidden = !temFiltro;
        linhas.innerHTML = '<div class="admin-cot-empty">Carregando...</div>';

        fetch('/api/admin/auditoria/listar?' + q.toString())
            .then(r => r.json())
            .then(d => {
                if (meu !== pedido) return; // chegou uma resposta mais nova
                linhas.innerHTML = d.html || '';
                pagina  = d.pagina || 1;
                paginas = d.paginas || 1;
                pagInfo.textContent = d.total
                    ? `${d.total.toLocaleString('pt-BR')} registro${d.total === 1 ? '' : 's'} · página ${pagina} de ${paginas}`
                    : '';
                btnAnterior.disabled = pagina <= 1;
                btnProxima.disabled  = pagina >= paginas;
            })
            .catch(() => {
                if (meu === pedido) linhas.innerHTML = '<div class="admin-cot-empty">Erro ao carregar os registros.</div>';
            });
    }

    const recarregar = () => { pagina = 1; carregar(); };

    textos.forEach(el => el.addEventListener('input', () => {
        clearTimeout(debounce);
        debounce = setTimeout(recarregar, 400);
    }));
    [...datas, ...Object.values(selects), ...Object.values(chaves)].forEach(el => el.addEventListener('change', recarregar));
    btnAnterior.addEventListener('click', () => { if (pagina > 1) { pagina--; carregar(); } });
    btnProxima.addEventListener('click', () => { if (pagina < paginas) { pagina++; carregar(); } });

    // Detalhes: clique na linha abre/fecha.
    linhas.addEventListener('click', (e) => {
        const linha = e.target.closest('.aud-linha');
        if (!linha) return;
        const detalhe = linha.nextElementSibling;
        const aberto = !detalhe.hidden;
        detalhe.hidden = aberto;
        linha.setAttribute('aria-expanded', aberto ? 'false' : 'true');
        linha.closest('.aud-item').classList.toggle('is-aberto', !aberto);
    });

    // ── Limpar filtros (mesmo reset do Financeiro) ──
    btnLimpar.addEventListener('click', () => {
        textos.forEach(el => { el.value = ''; });
        Object.values(chaves).forEach(el => { el.checked = false; });
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
        recarregar();
    });

    carregar();
})();
