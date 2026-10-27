/**
 * Gerenciador (Central de Gestão) — admin-gerenciador.js
 * Filtros (período, atendente, imobiliária) + status-kpi-grid — tudo via
 * AJAX, sem navegar nem alterar a URL de /admin/gerenciador.
 */
(function () {
    'use strict';

    const rankingImob  = document.getElementById('ranking-imobiliarias');
    const rankingAtend = document.getElementById('ranking-atendentes');
    const imobiliariaInput = document.getElementById('f-imobiliaria');
    const btnLimpar    = document.getElementById('btn-limpar');

    // Mesmo padrão -30 dias/hoje usado no PHP pra preencher os date-pickers
    // no carregamento inicial da página — usado aqui só pra "Limpar filtros"
    // voltar ao período padrão em vez de deixar as datas em branco.
    function pad2(n) { return n < 10 ? '0' + n : '' + n; }
    function toISO(d) { return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate()); }
    function toBR(iso) { const p = iso.split('-'); return p[2] + '/' + p[1] + '/' + p[0]; }

    const hoje = new Date();
    const trintaDiasAtras = new Date();
    trintaDiasAtras.setDate(hoje.getDate() - 30);
    const dataDePadrao  = toISO(trintaDiasAtras);
    const dataAtePadrao = toISO(hoje);

    const totalEls = {
        fianca:      document.getElementById('total-fianca'),
        auto:        document.getElementById('total-auto'),
        titulo:      document.getElementById('total-titulo'),
        condominial: document.getElementById('total-condominial'),
        incendio:    document.getElementById('total-incendio'),
        geral:       document.getElementById('total-geral'),
    };

    const countEls = {
        todos:       document.getElementById('count-todos'),
        pre_analise: document.getElementById('count-pre-analise'),
        em_analise:  document.getElementById('count-em-analise'),
        aguardando:  document.getElementById('count-aguardando'),
        aprovados:   document.getElementById('count-aprovados'),
        reprovados:  document.getElementById('count-recusados'),
        concluidos:  document.getElementById('count-concluidos'),
    };

    const atendenteHidden = document.querySelector('#f-atendente-wrap input[name="f_atendente"]');

    const temposRows      = document.getElementById('tempos-rows');
    const temposPagInfo    = document.getElementById('tempos-pag-info');
    const temposPagAnterior = document.getElementById('tempos-pag-anterior');
    const temposPagProxima  = document.getElementById('tempos-pag-proxima');

    let activeStatus   = '';
    let temposPage      = 1;
    let temposTotalPages = 1;
    let debounceTimer   = null;

    function getFiltros() {
        return {
            status_rapido: activeStatus,
            atendente_id:  atendenteHidden ? atendenteHidden.value : '',
            imobiliaria:   imobiliariaInput.value.trim(),
            data_de:       document.getElementById('f-data-de').value,
            data_ate:      document.getElementById('f-data-ate').value,
        };
    }

    function temFiltroAtivo() {
        const f = getFiltros();
        return !!(f.status_rapido || f.atendente_id || f.imobiliaria || f.data_de !== dataDePadrao || f.data_ate !== dataAtePadrao);
    }

    function carregar() {
        const params = new URLSearchParams(getFiltros());

        btnLimpar.style.display = temFiltroAtivo() ? '' : 'none';

        rankingImob.innerHTML  = '<div class="admin-cot-empty">Carregando...</div>';
        rankingAtend.innerHTML = '<div class="admin-cot-empty">Carregando...</div>';

        fetch('/api/admin/gerenciador/stats?' + params.toString())
            .then(function (r) { return r.json(); })
            .then(function (data) {
                const t = data.produto_totais || {};
                totalEls.fianca.textContent      = t.fianca      || 0;
                totalEls.auto.textContent        = t.auto        || 0;
                totalEls.titulo.textContent      = t.titulo      || 0;
                totalEls.condominial.textContent = t.condominial || 0;
                totalEls.incendio.textContent    = t.incendio    || 0;
                totalEls.geral.textContent       = t.geral       || 0;

                const c = data.contadores || {};
                countEls.todos.textContent       = c.todos       || 0;
                countEls.pre_analise.textContent = c.pre_analise || 0;
                countEls.em_analise.textContent  = c.em_analise  || 0;
                countEls.aguardando.textContent  = c.aguardando  || 0;
                countEls.aprovados.textContent   = c.aprovados   || 0;
                countEls.reprovados.textContent  = c.reprovados  || 0;
                countEls.concluidos.textContent  = c.concluidos  || 0;

                rankingImob.innerHTML  = data.ranking_imobiliarias_html || '';
                rankingAtend.innerHTML = data.ranking_atendentes_html   || '';
                aplicarLarguraBarras(rankingImob);
                aplicarLarguraBarras(rankingAtend);
            })
            .catch(function () {
                rankingImob.innerHTML  = '<li class="tk-empty">Erro ao carregar dados.</li>';
                rankingAtend.innerHTML = '<li class="tk-empty">Erro ao carregar dados.</li>';
            });
    }

    // Aplica a largura das barras de proporção (via data-w) após injetar o HTML.
    function aplicarLarguraBarras(container) {
        if (!container) return;
        container.querySelectorAll('.tk-dash-fill[data-w]').forEach(function (f) {
            f.style.width = (f.dataset.w || 0) + '%';
        });
    }

    // ── Lista "Tempo por cotação" ────────────────────────────────────────────
    function carregarTempos() {
        const params = new URLSearchParams(Object.assign({}, getFiltros(), { page: temposPage }));

        temposRows.innerHTML = '<div class="admin-cot-empty">Carregando...</div>';

        fetch('/api/admin/gerenciador/tempos?' + params.toString())
            .then(function (r) { return r.json(); })
            .then(function (data) {
                temposRows.innerHTML = data.html || '';

                temposTotalPages = data.total_pages || 1;
                temposPage       = data.page || 1;
                const inicio = data.total > 0 ? ((temposPage - 1) * data.per_page) + 1 : 0;
                const fim    = Math.min(temposPage * data.per_page, data.total || 0);
                temposPagInfo.textContent = data.total > 0
                    ? `Mostrando ${inicio}–${fim} de ${data.total}`
                    : 'Nenhum resultado';
                temposPagAnterior.disabled = temposPage <= 1;
                temposPagProxima.disabled  = temposPage >= temposTotalPages;
            })
            .catch(function () {
                temposRows.innerHTML = '<div class="admin-cot-empty">Erro ao carregar dados.</div>';
            });
    }

    function irParaPaginaTempos(p) {
        temposPage = p;
        carregarTempos();
    }

    function carregarTudo() {
        carregar();
        irParaPaginaTempos(1);
    }

    function carregarTudoDebounce() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(carregarTudo, 350);
    }

    // ── KPI cards de status ──────────────────────────────────────────────────
    document.querySelectorAll('.status-kpi-card').forEach(function (card) {
        card.addEventListener('click', function (e) {
            e.preventDefault();
            document.querySelectorAll('.status-kpi-card').forEach(function (c) { c.classList.remove('is-active'); });
            card.classList.add('is-active');
            activeStatus = card.dataset.status || '';
            carregarTudo();
        });
    });

    // ── Filtros ──────────────────────────────────────────────────────────────
    imobiliariaInput.addEventListener('input', carregarTudoDebounce);
    if (atendenteHidden) atendenteHidden.addEventListener('change', carregarTudo);
    document.getElementById('f-data-de').addEventListener('change', carregarTudo);
    document.getElementById('f-data-ate').addEventListener('change', carregarTudo);

    // ── Paginação da lista de tempos ──────────────────────────────────────────
    temposPagAnterior.addEventListener('click', function () { if (temposPage > 1) irParaPaginaTempos(temposPage - 1); });
    temposPagProxima.addEventListener('click', function () { if (temposPage < temposTotalPages) irParaPaginaTempos(temposPage + 1); });

    // ── Limpar filtros (volta ao período padrão de -30 dias, sem atendente/
    //    imobiliária/status filtrados) ──────────────────────────────────────
    btnLimpar.addEventListener('click', function () {
        imobiliariaInput.value = '';

        [
            { id: 'f-data-de', iso: dataDePadrao },
            { id: 'f-data-ate', iso: dataAtePadrao },
        ].forEach(function (item) {
            const hiddenDate = document.getElementById(item.id);
            const wrapper = hiddenDate.closest('[data-date-picker]');
            const valueEl = wrapper.querySelector('.date-picker-value');
            hiddenDate.value = item.iso;
            valueEl.textContent = toBR(item.iso);
            valueEl.classList.remove('is-placeholder');
        });

        if (atendenteHidden) {
            const wrapper = atendenteHidden.closest('[data-custom-select]');
            if (wrapper) {
                const placeholder = wrapper.dataset.placeholder || 'Todos';
                const valueEl = wrapper.querySelector('.custom-select-value');
                valueEl.textContent = placeholder;
                valueEl.classList.add('is-placeholder');
                wrapper.querySelectorAll('.custom-select-option').forEach(function (opt) {
                    opt.classList.toggle('is-selected', opt.dataset.value === '');
                });
            }
            atendenteHidden.value = '';
        }

        document.querySelectorAll('.status-kpi-card').forEach(function (c) { c.classList.remove('is-active'); });
        document.querySelector('.status-kpi-card[data-status=""]').classList.add('is-active');
        activeStatus = '';

        carregarTudo();
    });

    // ── Linha do tempo (modal) ────────────────────────────────────────────────
    temposRows.addEventListener('click', function (e) {
        const btn = e.target.closest('[data-action="ver-timeline"]');
        if (!btn) return;

        const protocolo = btn.dataset.protocolo || '';

        mpsAlert.loading('Carregando linha do tempo...');

        fetch('/api/admin/gerenciador/timeline?protocolo=' + encodeURIComponent(protocolo))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                mpsAlert.custom(`
                    <h3 class="mps-alert-title">Linha do tempo — ${protocolo}</h3>
                    <div class="mps-alert-body-left">${data.html || ''}</div>
                    <div class="mps-alert-actions">
                        <button type="button" class="btn btn-primary" data-mps-alert-close>Fechar</button>
                    </div>
                `, {
                    onMount: function (card, close) {
                        card.querySelector('[data-mps-alert-close]').addEventListener('click', close);
                    },
                });
            })
            .catch(function () {
                mpsAlert.error('Erro ao carregar a linha do tempo.');
            });
    });

    // ── Carga inicial ────────────────────────────────────────────────────────
    carregarTudo();
})();
