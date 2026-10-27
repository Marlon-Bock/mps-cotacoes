/**
 * Dashboard — anima barras do gráfico mensal e segmentos do panorama de status.
 * Altura/largura vêm de data-height / data-width (PHP); aqui só convertemos
 * para custom properties CSS, sem nunca escrever style="" no HTML.
 */
document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.chart-bar[data-height]').forEach((bar) => {
        requestAnimationFrame(() => {
            bar.style.setProperty('--bar-height', `${bar.dataset.height}%`);
        });
    });

    document.querySelectorAll('.panorama-segment[data-width]').forEach((seg) => {
        requestAnimationFrame(() => {
            seg.style.setProperty('--seg-width', `${seg.dataset.width}%`);
        });
    });

    // ── Popup "cotações em análise sob minha responsabilidade" ────────────────
    const appContent = document.querySelector('.app-content[data-corretor-id]');
    if (!appContent || typeof mpsAlert === 'undefined') return;

    const meuId = appContent.dataset.corretorId || '';

    function escapeHtml(v) {
        const div = document.createElement('div');
        div.textContent = v;
        return div.innerHTML;
    }

    function iconeInfo() {
        return '<svg class="icon" viewBox="0 0 256 256" fill="currentColor" aria-hidden="true"><path d="M128,24A104,104,0,1,0,232,128,104.11,104.11,0,0,0,128,24Zm0,192a88,88,0,1,1,88-88A88.1,88.1,0,0,1,128,216Zm16-40a8,8,0,0,1-8,8,16,16,0,0,1-16-16V128a8,8,0,0,1,0-16,16,16,0,0,1,16,16v40A8,8,0,0,1,144,176ZM112,84a12,12,0,1,1,12,12A12,12,0,0,1,112,84Z"/></svg>';
    }

    function abrirMinhasCotacoes() {
        const base = typeof appUrl === 'function' ? appUrl('/admin/cotacoes') : '/admin/cotacoes';
        const qs = 'status=em_analise' + (meuId ? '&corretor_id=' + encodeURIComponent(meuId) : '');
        window.location.href = base + '?' + qs;
    }

    function mostrarPopup(titulo, total) {
        const mensagem = total > 0
            ? `Você tem <strong>${total}</strong> cotaç${total === 1 ? 'ão' : 'ões'} em análise aguardando sua resposta.`
            : 'Você não tem nenhuma cotação em análise aguardando sua resposta no momento.';

        mpsAlert.custom(`
            <div class="mps-alert-icon mps-alert-icon-info">${iconeInfo()}</div>
            <h3 class="mps-alert-title">${escapeHtml(titulo)}</h3>
            <p class="mps-alert-message">${mensagem}</p>
            <div class="mps-alert-actions">
                <button type="button" class="btn btn-secondary" data-mps-alert-close>Fechar</button>
                ${total > 0 ? '<button type="button" class="btn btn-primary" id="btn-ver-minhas-cotacoes">Ver cotações</button>' : ''}
            </div>
        `, {
            onMount: (card, close) => {
                card.querySelector('[data-mps-alert-close]').addEventListener('click', close);
                const btnVer = card.querySelector('#btn-ver-minhas-cotacoes');
                if (btnVer) btnVer.addEventListener('click', () => { close(); abrirMinhasCotacoes(); });
            },
        });
    }

    // Só no primeiro dashboard depois do login — com saudação pelo nome.
    if (appContent.dataset.popupEmAnalise !== undefined) {
        const total = parseInt(appContent.dataset.popupEmAnalise, 10) || 0;
        const primeiroNome = (appContent.dataset.popupNome || '').trim().split(/\s+/)[0] || '';
        mostrarPopup('Bem-vindo' + (primeiroNome ? `, ${primeiroNome}` : '') + '!', total);
    }

    // A cada 30 minutos, enquanto a aba ficar aberta: o backend decide se já
    // pode mostrar de novo (throttle por usuário em usuario.popup_cotacoes_visto_em).
    const INTERVALO_MS = 30 * 60 * 1000;
    setInterval(() => {
        const url = (typeof appUrl === 'function' ? appUrl('/api/admin/popup-cotacoes-em-analise') : '/api/admin/popup-cotacoes-em-analise');
        fetch(url)
            .then((r) => r.json())
            .then((data) => {
                if (data && data.show) {
                    mostrarPopup('Lembrete', data.total || 0);
                }
            })
            .catch(() => {});
    }, INTERVALO_MS);
});

// ── Popup "pendências da imobiliária" (biometria/proposta aguardando ação) ──
// Só no primeiro dashboard depois do login, uma vez — sem repetir na sessão.
document.addEventListener('DOMContentLoaded', () => {
    const appContent = document.querySelector('.app-content[data-popup-pendencias]');
    if (!appContent || typeof mpsAlert === 'undefined') return;

    let itens = [];
    try {
        itens = JSON.parse(appContent.dataset.popupPendencias || '[]');
    } catch {
        return;
    }
    if (!itens.length) return;

    const escapeHtml = (v) => {
        const div = document.createElement('div');
        div.textContent = v;
        return div.innerHTML;
    };

    const iconeRelogio = '<svg viewBox="0 0 256 256" fill="currentColor" aria-hidden="true"><path d="M128,24A104,104,0,1,0,232,128,104.11,104.11,0,0,0,128,24Zm0,192a88,88,0,1,1,88-88A88.1,88.1,0,0,1,128,216Zm64-88a8,8,0,0,1-8,8H128a8,8,0,0,1-8-8V72a8,8,0,0,1,16,0v48h48A8,8,0,0,1,192,128Z"/></svg>';
    const iconeCheck   = '<svg viewBox="0 0 256 256" fill="currentColor" aria-hidden="true"><path d="M173.66,98.34a8,8,0,0,1,0,11.32l-56,56a8,8,0,0,1-11.32,0l-24-24a8,8,0,0,1,11.32-11.32L112,148.69l50.34-50.35A8,8,0,0,1,173.66,98.34ZM232,128A104,104,0,1,1,128,24,104.11,104.11,0,0,1,232,128Zm-16,0a88,88,0,1,0-88,88A88.1,88.1,0,0,0,216,128Z"/></svg>';

    const linhaPorItem = (item) => {
        const biometria = item.tipo === 'biometria';
        const texto = biometria
            ? 'está aguardando você enviar a biometria.'
            : 'foi aprovada — você deve verificar a cotação.';
        return `
            <div class="mps-alert-pendencia-item">
                <span class="mps-alert-pendencia-icon ${biometria ? 'is-biometria' : 'is-proposta'}">${biometria ? iconeRelogio : iconeCheck}</span>
                <div class="mps-alert-pendencia-text">
                    <strong>${escapeHtml(item.protocolo)}</strong>
                    <span>${escapeHtml(texto)}</span>
                </div>
            </div>
        `;
    };

    const iconeAlerta = '<svg class="icon" viewBox="0 0 256 256" fill="currentColor" aria-hidden="true"><path d="M236.8,188.09,149.35,36.22h0a24.76,24.76,0,0,0-42.7,0L19.2,188.09a23.51,23.51,0,0,0,0,23.72A24.35,24.35,0,0,0,40.55,224h174.9a24.35,24.35,0,0,0,21.33-12.19A23.51,23.51,0,0,0,236.8,188.09ZM222.93,203.8a8.5,8.5,0,0,1-7.48,4.2H40.55a8.5,8.5,0,0,1-7.48-4.2,7.59,7.59,0,0,1,0-7.72L120.52,44.21a8.75,8.75,0,0,1,15,0l87.45,151.87A7.59,7.59,0,0,1,222.93,203.8ZM120,144V104a8,8,0,0,1,16,0v40a8,8,0,0,1-16,0Zm20,36a12,12,0,1,1-12-12A12,12,0,0,1,140,180Z"/></svg>';

    const totalTexto = itens.length === 1 ? '1 pendência' : `${itens.length} pendências`;
    const lista = itens.map(linhaPorItem).join('');

    mpsAlert.custom(`
        <div class="mps-alert-icon mps-alert-icon-warning">${iconeAlerta}</div>
        <h3 class="mps-alert-title">Você tem ${totalTexto}</h3>
        <p class="mps-alert-message">Confira o que precisa da sua ação:</p>
        <div class="mps-alert-pendencia-list">${lista}</div>
        <div class="mps-alert-actions">
            <button type="button" class="btn btn-secondary" data-mps-alert-close>Fechar</button>
            <button type="button" class="btn btn-primary" id="btn-ver-minhas-pendencias">Ver cotações</button>
        </div>
    `, {
        onMount: (card, close) => {
            card.querySelector('[data-mps-alert-close]').addEventListener('click', close);
            card.querySelector('#btn-ver-minhas-pendencias').addEventListener('click', () => {
                close();
                const protocolos = itens.map((item) => encodeURIComponent(item.protocolo)).join(',');
                const base = (typeof appUrl === 'function' ? appUrl('/cotacoes') : '/cotacoes');
                window.location.href = base + '?destacar=' + protocolos;
            });
        },
    });
});

// ── Popup "Novidade! Seguro Incêndio" ──────────────────────────────────────
// Mostra uma única vez por usuário (permanente, controlado no servidor por
// users.novidade_incendio_visto_em) — não repete nem em outra sessão/login.
document.addEventListener('DOMContentLoaded', () => {
    const appContent = document.querySelector('.app-content[data-popup-novidade-incendio]');
    if (!appContent || typeof mpsAlert === 'undefined') return;

    const temPermIncendio = appContent.dataset.popupNovidadeIncendioPerm === '1';
    const csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

    const marcarComoVisto = () => {
        const url = (typeof appUrl === 'function' ? appUrl('/api/novidade-incendio/visto') : '/api/novidade-incendio/visto');
        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify({}),
        }).catch(() => {});
    };

    const iconeFogo = '<svg class="icon" viewBox="0 0 256 256" fill="currentColor" aria-hidden="true"><path d="M183.89,153.34a57.6,57.6,0,0,1-46.56,46.55A8.75,8.75,0,0,1,136,200a8,8,0,0,1-1.32-15.89c16.57-2.79,30.63-16.85,33.44-33.45a8,8,0,0,1,15.78,2.68ZM216,144a88,88,0,0,1-176,0c0-27.92,11-56.47,32.66-84.85a8,8,0,0,1,11.93-.89l24.12,23.41,22-60.41a8,8,0,0,1,12.63-3.41C165.21,36,216,84.55,216,144Zm-16,0c0-46.09-35.79-85.92-58.21-106.33L119.52,98.74a8,8,0,0,1-13.09,3L80.06,76.16C64.09,99.21,56,122,56,144a72,72,0,0,0,144,0Z"/></svg>';

    const mensagem = temPermIncendio
        ? 'Agora você já pode cotar <strong>Seguro Incêndio</strong> direto pelo sistema, com suporte a vários imóveis numa mesma remessa, cada um com seu próprio andamento.'
        : 'Agora o sistema já cota <strong>Seguro Incêndio</strong>! Você ainda não tem acesso a esse produto, peça pra um administrador da sua imobiliária liberar em <em>Gerenciar Usuários</em>.';

    mpsAlert.custom(`
        <div class="mps-alert-icon mps-alert-icon-warning">${iconeFogo}</div>
        <h3 class="mps-alert-title">Novidade! 🎉</h3>
        <p class="mps-alert-message">${mensagem}</p>
        <div class="mps-alert-actions">
            ${temPermIncendio
                ? '<button type="button" class="btn btn-secondary" data-mps-alert-close>Depois</button><button type="button" class="btn btn-primary" id="btn-cotar-incendio">Cotar agora</button>'
                : '<button type="button" class="btn btn-primary" data-mps-alert-close>Entendi</button>'}
        </div>
    `, {
        onMount: (card, close) => {
            const fechar = () => { marcarComoVisto(); close(); };
            card.querySelector('[data-mps-alert-close]').addEventListener('click', fechar);
            const btnCotar = card.querySelector('#btn-cotar-incendio');
            if (btnCotar) {
                btnCotar.addEventListener('click', () => {
                    marcarComoVisto();
                    const base = (typeof appUrl === 'function' ? appUrl('/cotacao/incendio') : '/cotacao/incendio');
                    window.location.href = base;
                });
            }
        },
    });
});
