(function () {
    'use strict';

    const lista     = document.getElementById('chamados-lista');
    const btnLimpar = document.getElementById('btn-limpar-chamados');

    const countEls = {
        total:           document.getElementById('count-total'),
        aguardando_mps:  document.getElementById('count-aguardando-mps'),
        aguardando_imob: document.getElementById('count-aguardando-imob'),
        encerrado:       document.getElementById('count-encerrado'),
    };

    let activeStatus  = '';
    let debounceTimer = null;

    function getFiltros() {
        return {
            status:  activeStatus,
            assunto: document.getElementById('f-assunto').value.trim(),
        };
    }

    function temFiltroAtivo() {
        const f = getFiltros();
        return f.assunto !== '';
    }

    function carregar() {
        const params = new URLSearchParams(getFiltros());
        lista.innerHTML = '<div class="admin-cot-empty">Carregando...</div>';
        if (btnLimpar) btnLimpar.style.display = temFiltroAtivo() ? '' : 'none';

        fetch('/api/chamados/listar?' + params.toString())
            .then(function (r) { return r.json(); })
            .then(function (data) {
                lista.innerHTML = data.html || '';
                var c = data.contadores || {};
                if (countEls.total)           countEls.total.textContent           = c.total           || 0;
                if (countEls.aguardando_mps)  countEls.aguardando_mps.textContent  = c.aguardando_mps  || 0;
                if (countEls.aguardando_imob) countEls.aguardando_imob.textContent = c.aguardando_imob || 0;
                if (countEls.encerrado)       countEls.encerrado.textContent       = c.encerrado       || 0;
            })
            .catch(function () {
                lista.innerHTML = '<div class="admin-cot-empty">Erro ao carregar dados.</div>';
            });
    }

    function carregarDebounce() {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(carregar, 350);
    }

    // ── KPI cards de status ──────────────────────────────────────────────────
    document.querySelectorAll('.status-kpi-card').forEach(function (card) {
        card.addEventListener('click', function (e) {
            e.preventDefault();
            document.querySelectorAll('.status-kpi-card').forEach(function (c) {
                c.classList.remove('is-active');
            });
            card.classList.add('is-active');
            activeStatus = card.dataset.status || '';
            carregar();
        });
    });

    // ── Input de assunto com debounce ────────────────────────────────────────
    var fAssunto = document.getElementById('f-assunto');
    if (fAssunto) fAssunto.addEventListener('input', carregarDebounce);

    // ── Limpar filtros ───────────────────────────────────────────────────────
    if (btnLimpar) {
        btnLimpar.addEventListener('click', function () {
            if (fAssunto) fAssunto.value = '';
            carregar();
        });
    }

    // ── Carga inicial ────────────────────────────────────────────────────────
    carregar();
})();
