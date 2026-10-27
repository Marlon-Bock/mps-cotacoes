/**
 * session-watchdog.js
 * Monitora a expiração do JWT e:
 *  - Tenta renovar automaticamente enquanto o usuário está ativo (última
 *    atividade < 15 min atrás).
 *  - Exibe aviso 5 minutos antes de expirar se o usuário estiver inativo.
 *  - Redireciona para /sessao-expirada quando o token expira.
 */
(function () {
    'use strict';

    const WARN_BEFORE_MS   = 5 * 60 * 1000;   // avisa 5 min antes
    const RENEW_BEFORE_MS  = 10 * 60 * 1000;  // renova se ativo e falta < 10 min
    const IDLE_THRESHOLD   = 15 * 60 * 1000;  // considera inativo após 15 min sem evento
    const CHECK_INTERVAL   = 30 * 1000;        // verifica a cada 30s

    const metaEl = document.querySelector('meta[name="session-exp"]');
    if (!metaEl) return;

    let expTs     = parseInt(metaEl.content, 10) * 1000; // converte para ms
    let lastActive = Date.now();
    let warned     = false;
    let warnModal  = null;

    if (!expTs || expTs <= Date.now()) return;

    // Rastreia atividade do usuário
    ['mousemove', 'keydown', 'click', 'scroll', 'touchstart'].forEach(evt => {
        document.addEventListener(evt, () => { lastActive = Date.now(); }, { passive: true });
    });

    function isIdle() {
        return (Date.now() - lastActive) > IDLE_THRESHOLD;
    }

    function remaining() {
        return expTs - Date.now();
    }

    async function renewSession() {
        try {
            const r    = await fetch('/api/renovar-sessao', { method: 'POST' });
            const data = await r.json();
            if (data.success && data.exp) {
                expTs  = data.exp * 1000;
                warned = false;
                if (warnModal) {
                    warnModal.close?.();
                    warnModal = null;
                }
            }
        } catch (_) {}
    }

    function goExpired() {
        window.location.href = appUrl('/sessao-expirada');
    }

    function showWarning() {
        if (warned || typeof mpsAlert === 'undefined') return;
        warned = true;

        const mins = Math.ceil(remaining() / 60000);

        // Usa o mpsAlert existente no sistema
        mpsAlert.confirm({
            title: 'Sua sessão está prestes a expirar',
            message: `Você ficará desconectado em aproximadamente ${mins} minuto${mins !== 1 ? 's' : ''}. Deseja continuar conectado?`,
            confirmText: 'Continuar conectado',
            cancelText: 'Sair agora',
            icon: 'warning',
        }).then(ok => {
            warnModal = null;
            if (ok) {
                renewSession();
            } else {
                fetch('/logout', { method: 'POST' }).finally(() => {
                    window.location.href = appUrl('/login');
                });
            }
        });
    }

    function tick() {
        const rem = remaining();

        if (rem <= 0) {
            goExpired();
            return;
        }

        // Usuário ativo e faltam menos de 10 min → renova silenciosamente
        if (!isIdle() && rem < RENEW_BEFORE_MS) {
            renewSession();
            return;
        }

        // Usuário inativo e faltam menos de 5 min → avisa
        if (isIdle() && rem < WARN_BEFORE_MS && !warned) {
            showWarning();
        }
    }

    setInterval(tick, CHECK_INTERVAL);

})();
