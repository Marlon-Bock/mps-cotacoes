/**
 * Sininho de notificações da topbar — populado via /api/notificacoes/resumo,
 * carregado globalmente (toda página autenticada tem o dropdown na topbar).
 */
(function () {
    const btnBell   = document.querySelector('[data-toggle="notif-menu"]');
    const countEl   = document.getElementById('app-notif-count');
    const bodyEl    = document.getElementById('app-notif-dropdown-body');
    const btnTodas  = document.getElementById('app-notif-marcar-todas');
    if (!btnBell || !countEl || !bodyEl) return;

    const csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

    const ICONS = {
        nova_cotacao: '<svg viewBox="0 0 256 256" fill="currentColor"><path d="M213.66,82.34l-56-56A8,8,0,0,0,152,24H56A16,16,0,0,0,40,40V216a16,16,0,0,0,16,16H200a16,16,0,0,0,16-16V88A8,8,0,0,0,213.66,82.34ZM160,51.31,188.69,80H160ZM200,216H56V40h88V88a8,8,0,0,0,8,8h48V216Z"/></svg>',
        chat_mensagem: '<svg viewBox="0 0 256 256" fill="currentColor"><path d="M128,24A104,104,0,0,0,36.19,178.61L24.83,210.11a16,16,0,0,0,20.68,20.32l32.71-11.9A104,104,0,1,0,128,24Zm0,192a87.6,87.6,0,0,1-44.24-12,8,8,0,0,0-6.16-.86L48,214.85l11.6-30a8,8,0,0,0-.75-7.31A88,88,0,1,1,128,216Z"/></svg>',
        default: '<svg viewBox="0 0 256 256" fill="currentColor"><path d="M221.8,175.94C216.25,166.38,208,139.33,208,104a80,80,0,1,0-160,0c0,35.34-8.26,62.38-13.81,71.94A16,16,0,0,0,48,200H88.81a40,40,0,0,0,78.38,0H208a16,16,0,0,0,13.8-24.06ZM128,216a24,24,0,0,1-22.62-16h45.24A24,24,0,0,1,128,216ZM48,184c7.7-13.24,16-43.92,16-80a64,64,0,1,1,128,0c0,36.05,8.28,66.73,16,80Z"/></svg>',
    };

    // Fiança (API das seguradoras): cotação/proposta/apólice.
    ['proposta_pronta', 'apolice_emitida', 'apolice_erro', 'cotacao_aprovada', 'cotacao_recusada', 'cotacao_biometria']
        .forEach(tipo => { ICONS[tipo] = ICONS.nova_cotacao; });

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    let ultimaContagem = null; // null = ainda não sincronizou (evita som na carga inicial)

    function render(data) {
        const nao_lidas = data.nao_lidas || 0;

        if (ultimaContagem !== null && nao_lidas > ultimaContagem) {
            // Cada efeito isolado no seu try/catch — uma falha no som (ex.:
            // navegador bloqueando áudio) não pode impedir o toast de aparecer,
            // e vice-versa.
            try {
                if (window.mpsNotifySound) window.mpsNotifySound.tocar();
            } catch (e) {
                console.warn('[notificacoes-bell] falha ao tocar som:', e);
            }
            try {
                const maisRecente = (data.itens || [])[0];
                if (maisRecente) {
                    // Dispara os dois sempre, em vez de escolher um só com base
                    // em document.hidden — o SO às vezes suprime a notificação
                    // nativa quando o navegador (mesmo que outra aba) ainda é o
                    // app em foco, o que fazia sumir o único aviso visual que
                    // tentávamos mostrar. Com os dois, sempre sobra pelo menos um.
                    // Um clique em qualquer um dos dois leva pra cotação/chat.
                    if (window.mpsAlert) {
                        mpsAlert.toast('info', `${maisRecente.titulo}: ${maisRecente.mensagem}`, {
                            onClick: maisRecente.url ? () => { window.location.href = appUrl(maisRecente.url); } : null,
                        });
                    }
                    if (window.mpsNotifySound) {
                        window.mpsNotifySound.notificar(maisRecente.titulo, maisRecente.mensagem, maisRecente.url);
                    }
                } else {
                    console.warn('[notificacoes-bell] contagem subiu mas nenhum item veio em data.itens');
                }
            } catch (e) {
                console.warn('[notificacoes-bell] falha ao exibir notificação:', e);
            }
        }
        ultimaContagem = nao_lidas;

        if (nao_lidas > 0) {
            countEl.textContent = nao_lidas > 99 ? '99+' : String(nao_lidas);
            countEl.hidden = false;
        } else {
            countEl.hidden = true;
        }

        if (btnTodas) btnTodas.hidden = nao_lidas === 0;

        const itens = data.itens || [];
        if (!itens.length) {
            bodyEl.innerHTML = `
                <div class="empty-state">
                    <div class="empty-state-icon">${ICONS.default}</div>
                    <p>Nenhuma notificação por aqui.</p>
                </div>`;
            return;
        }

        bodyEl.innerHTML = itens.map((n) => `
            <button type="button" class="app-notif-item${n.lida ? '' : ' is-unread'}" data-id="${n.id}" data-url="${n.url ? escapeHtml(n.url) : ''}">
                <span class="app-notif-item-icon">${ICONS[n.tipo] || ICONS.default}</span>
                <span class="app-notif-item-body">
                    <span class="app-notif-item-titulo">${escapeHtml(n.titulo)}</span>
                    <p class="app-notif-item-mensagem">${escapeHtml(n.mensagem)}</p>
                    <span class="app-notif-item-tempo">${escapeHtml(n.tempo)}</span>
                </span>
            </button>
        `).join('');
    }

    function carregar() {
        fetch('/api/notificacoes/resumo')
            .then((r) => r.json())
            .then((data) => { if (data.success) render(data); })
            .catch(() => {});
    }

    const marcandoIds = new Set();

    function marcarLida(id) {
        if (marcandoIds.has(id)) return;
        marcandoIds.add(id);

        fetch('/api/notificacoes/lidas', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify({ id }),
        }).then(carregar).catch(() => {}).finally(() => marcandoIds.delete(id));
    }

    function marcarAoClicar(e) {
        const item = e.target.closest('.app-notif-item');
        if (!item) return;
        if (item.classList.contains('is-unread')) marcarLida(parseInt(item.dataset.id, 10));
        if (item.dataset.url) window.location.href = appUrl(item.dataset.url);
    }

    bodyEl.addEventListener('click', marcarAoClicar);

    if (btnTodas) {
        btnTodas.addEventListener('click', () => {
            fetch('/api/notificacoes/lidas', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({ todas: true }),
            }).then(carregar).catch(() => {});
        });
    }

    btnBell.addEventListener('click', carregar);

    // Checa a cada 30s, em foco ou em segundo plano (outra aba/janela/
    // navegador) — é o único poller que dispara som/toast/notificação do SO
    // no sistema inteiro (ver cotacao-chat.js), então o intervalo aqui é o
    // "tempo de notificação" do sistema como um todo.
    carregar();
    setInterval(carregar, 30000);
}());
