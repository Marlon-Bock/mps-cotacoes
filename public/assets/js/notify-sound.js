/**
 * Som + notificação do sistema, compartilhados entre sino da topbar e chat
 * da cotação.
 *
 * Som: sintetizado via WebAudio, sem depender de arquivo de áudio (mesma
 * técnica do painel de TV — ver painel.js). Navegadores só liberam áudio
 * depois de um gesto do usuário na página. Duas camadas:
 *   1) Qualquer clique/tecla/toque na página já destrava silenciosamente.
 *   2) Um modal obrigatório (mesmo componente do aviso de boas-vindas do
 *      dashboard), com botão "Ativar" — garante um gesto explícito, já toca
 *      um som de teste na hora (pra confirmar que funcionou) e pede
 *      permissão de notificação do SO. Fecha sozinho assim que o som é
 *      destravado por qualquer via (não só pelo próprio botão).
 *
 * Notificação do SO: única forma de avisar o usuário com a janela minimizada
 * ou outra janela em primeiro plano — um toast dentro da página não tem
 * como aparecer nesses casos. Exposta como `notificar(titulo, corpo, url)`;
 * quem chama decide quando usar (normalmente só se `document.hidden`).
 */
window.mpsNotifySound = (function () {
    'use strict';

    var audioCtx    = null;
    var somLiberado  = false;

    /**
     * Retorna uma Promise que só resolve quando o áudio está de fato
     * liberado — só marca somLiberado depois que o navegador CONFIRMA que o
     * contexto está rodando de verdade. Um gesto "fraco" (ex.: F12 pra abrir
     * o DevTools, atalhos de teclado em geral) não conta pra política de
     * autoplay, e o resume() falha silenciosamente (context continua
     * "suspended"). Sem essa checagem, o código achava que já tinha liberado
     * e escondia o aviso pra sempre, mesmo com o som nunca tendo funcionado.
     */
    function liberar() {
        if (somLiberado) return Promise.resolve();
        try {
            var Ctx = window.AudioContext || window.webkitAudioContext;
            if (!Ctx) return Promise.resolve();
            if (!audioCtx) audioCtx = new Ctx();

            var marcarLiberado = function () {
                if (audioCtx.state === 'running') {
                    somLiberado = true;
                    try { localStorage.setItem('mps_som_ativado_antes', '1'); } catch (e) {}
                    esconderAviso();
                }
            };

            if (audioCtx.state === 'running') {
                marcarLiberado();
                return Promise.resolve();
            }
            return audioCtx.resume().then(marcarLiberado).catch(function () {});
        } catch (e) {
            return Promise.resolve();
        }
    }

    function jaAtivouAntes() {
        try {
            return localStorage.getItem('mps_som_ativado_antes') === '1';
        } catch (e) {
            return false;
        }
    }

    // Só clique/toque contam como gesto real pra política de autoplay —
    // atalhos de teclado (F12, Ctrl+algo etc.) não são um "gesto do usuário"
    // válido e fariam o resume() falhar silenciosamente. Sem `once`, porque
    // liberar() só se considera bem-sucedido quando o contexto confirma que
    // está rodando — se o primeiro clique não bastar, o próximo tenta de novo.
    // `capture: true` — escuta ANTES de qualquer outro handler da página, então
    // um clique num botão que chama stopPropagation() não escapa do destrave.
    ['click', 'touchstart'].forEach(function (evt) {
        document.addEventListener(evt, liberar, { passive: true, capture: true });
    });

    /**
     * Arpejo curto de 3 notas (acorde maior, tipo notificação de app de
     * mensagem), onda "triangle" — mais presente/mais alta ao ouvido que uma
     * senoidal pura — cada nota com uma oitava sobreposta bem baixa pra dar
     * corpo.
     */
    function tocar() {
        if (!somLiberado) return;

        // Contexto novo a cada som, em vez de reaproveitar o mesmo pra
        // sempre: um contexto "antigo" pode continuar preso ao dispositivo
        // de saída de áudio que era o padrão do sistema no momento em que
        // foi criado (ex.: fone de ouvido que já foi desconectado), mesmo
        // reportando state "running" — sem erro nenhum, só sem som de
        // verdade. Um contexto criado na hora sempre pega o dispositivo
        // atual. Fecha sozinho um segundo depois de tocar.
        try {
            var Ctx = window.AudioContext || window.webkitAudioContext;
            if (!Ctx) return;
            var ctx = new Ctx();
            tocarAgora(ctx);
            setTimeout(function () {
                ctx.close().catch(function () {});
            }, 1200);
        } catch (e) {
            console.warn('[notify-sound] falha ao tocar:', e);
        }
    }

    function tocarAgora(ctx) {
        try {
            var t0    = ctx.currentTime;
            var notas = [659.25, 830.61, 987.77]; // E5 → G#5 → B5 (acorde maior)

            notas.forEach(function (freq, i) {
                var start = t0 + i * 0.11;
                var dur   = 0.5;

                var gain = ctx.createGain();
                gain.gain.setValueAtTime(0.0001, start);
                gain.gain.exponentialRampToValueAtTime(0.65, start + 0.01);
                gain.gain.exponentialRampToValueAtTime(0.0001, start + dur);
                gain.connect(ctx.destination);

                var fundamental = ctx.createOscillator();
                fundamental.type = 'triangle';
                fundamental.frequency.setValueAtTime(freq, start);
                fundamental.connect(gain);
                fundamental.start(start);
                fundamental.stop(start + dur + 0.05);

                var oitava = ctx.createOscillator();
                var gainOitava = ctx.createGain();
                gainOitava.gain.value = 0.15;
                oitava.type = 'triangle';
                oitava.frequency.setValueAtTime(freq * 2, start);
                oitava.connect(gainOitava);
                gainOitava.connect(gain);
                oitava.start(start);
                oitava.stop(start + dur + 0.05);
            });
        } catch (e) {
            console.warn('[notify-sound] falha ao tocar:', e);
        }
    }

    /**
     * Notificação do sistema operacional (fora da página) — a única coisa
     * que consegue aparecer com o navegador minimizado ou em outra janela,
     * já que um toast dentro da página fica invisível nesses casos. Exige
     * permissão explícita (pedida junto com "Ativar som"); sem permissão,
     * simplesmente não faz nada — quem chamou continua funcionando normal.
     */
    function pedirPermissaoNotificacao() {
        if (!('Notification' in window)) return;
        if (Notification.permission === 'default') {
            try { Notification.requestPermission(); } catch (e) {}
        }
    }

    function notificar(titulo, corpo, url) {
        if (!('Notification' in window) || Notification.permission !== 'granted') return;

        // Preferência: pedir pro Service Worker mostrar (self.registration.
        // showNotification, já usado pelo Web Push em sw.js) — é o caminho
        // mais confiável para aba/janela em segundo plano; new Notification()
        // direto da página pode ser silenciosamente descartado pelo navegador
        // nesses casos. Sem SW ativo, cai pro caminho direto como reserva.
        // Em ambos os casos, um clique leva pra `url` (a cotação/chat da
        // notificação) — sw.js já sabe tratar isso no notificationclick.
        if (navigator.serviceWorker && navigator.serviceWorker.controller) {
            try {
                // Resolve pro path completo (com o prefixo /mps, se houver)
                // ANTES de mandar pro service worker — o SW não tem acesso à
                // <meta name="base-path"> da página, só ao próprio scope.
                var destUrl = url ? (window.appUrl ? window.appUrl(url) : url) : (window.appUrl ? window.appUrl('/') : '/');
                navigator.serviceWorker.controller.postMessage({
                    type: 'SHOW_NOTIF',
                    payload: { titulo: titulo, mensagem: corpo || '', destUrl: destUrl },
                });
                return;
            } catch (e) {
                console.warn('[notify-sound] falha ao pedir notificação ao service worker:', e);
            }
        }

        try {
            var iconUrl = window.appUrl ? window.appUrl('/assets/img/logo-mark.png') : '/assets/img/logo-mark.png';
            // Tag única por notificação — com a mesma tag toda vez, o
            // navegador troca a notificação anterior "por baixo dos panos"
            // em vez de estourar uma nova na tela, então a 2ª em diante
            // parava de aparecer visualmente (só a 1ª "vencia" a troca).
            var n = new Notification(titulo, {
                body: corpo || '',
                tag: 'mps-notificacao-' + Date.now(),
                icon: iconUrl,
            });
            n.onclick = function () {
                window.focus();
                if (url) window.location.href = (window.appUrl ? window.appUrl(url) : url);
                n.close();
            };
        } catch (e) {
            console.warn('[notify-sound] falha ao exibir notificação do SO:', e);
        }
    }

    var fecharAvisoModal = null; // guarda o close() do mpsAlert enquanto o aviso está aberto

    function esconderAviso() {
        if (!fecharAvisoModal) return;
        fecharAvisoModal();
        fecharAvisoModal = null;
    }

    function permissaoNotificacaoPendente() {
        return ('Notification' in window) && Notification.permission === 'default';
    }

    /**
     * Mesmo modal usado no aviso de boas-vindas do dashboard ("Você tem N
     * cotações em análise") — reaproveita o componente mpsAlert em vez de um
     * balão flutuante próprio, pra ficar consistente com o resto do sistema.
     * Obrigatório: só tem o botão "Ativar", sem opção de pular — o modal só
     * fecha (sozinho) quando o som é realmente confirmado como liberado.
     */
    function mostrarAviso() {
        // Já ativou o som E já respondeu (concedeu ou negou) a permissão de
        // notificação — nada pendente, não precisa reavisar. Se só o som foi
        // liberado antes (ex.: ativou numa versão anterior desta página, sem
        // o pedido de notificação), o aviso volta a aparecer pra fechar essa
        // pendência — sem isso, quem já tinha ativado o som nunca mais via
        // o pedido de permissão de notificação.
        if (jaAtivouAntes() && !permissaoNotificacaoPendente()) return;
        if (fecharAvisoModal || !window.mpsAlert) return;

        mpsAlert.custom(
            '<div class="mps-alert-icon mps-alert-icon-info">'
                + '<svg class="icon" viewBox="0 0 256 256" fill="currentColor" aria-hidden="true"><path d="M221.8,175.94C216.25,166.38,208,139.33,208,104a80,80,0,1,0-160,0c0,35.34-8.26,62.38-13.81,71.94A16,16,0,0,0,48,200H88.81a40,40,0,0,0,78.38,0H208a16,16,0,0,0,13.8-24.06ZM128,216a24,24,0,0,1-22.62-16h45.24A24,24,0,0,1,128,216ZM48,184c7.7-13.24,16-43.92,16-80a64,64,0,1,1,128,0c0,36.05,8.28,66.73,16,80Z"/></svg>'
                + '</div>'
            + '<h3 class="mps-alert-title">Ative as notificações</h3>'
            + '<p class="mps-alert-message">É preciso ativar pra ouvir e ver quando chegar uma cotação nova ou mensagem no chat — inclusive com o navegador minimizado.</p>'
            + '<div class="mps-alert-actions">'
                + '<button type="button" class="btn btn-primary" data-mps-ativar-som>Ativar</button>'
                + '</div>',
            {
                onMount: function (card, close) {
                    fecharAvisoModal = close;
                    card.querySelector('[data-mps-ativar-som]').addEventListener('click', function () {
                        liberar().then(tocar);
                        pedirPermissaoNotificacao();
                    });
                },
            }
        );
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', mostrarAviso);
    } else {
        mostrarAviso();
    }

    /** Diagnóstico manual via console: window.mpsNotifySound.debug() */
    function debug() {
        return {
            somLiberado: somLiberado,
            audioCtxExiste: !!audioCtx,
            audioCtxState: audioCtx ? audioCtx.state : null,
            notificationPermission: ('Notification' in window) ? Notification.permission : 'indisponível',
            swController: !!(navigator.serviceWorker && navigator.serviceWorker.controller),
        };
    }

    return { tocar: tocar, notificar: notificar, debug: debug };
}());
