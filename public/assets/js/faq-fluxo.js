/**
 * Chat guiado do FAQ (fluxo estilo chatbot) — faq-fluxo.js
 * Busca a árvore de blocos uma vez e conduz o usuário bloco a bloco, tudo
 * client-side (sem round-trip a cada clique/resposta). Reaproveita o visual
 * do chat de chamados/cotação (.chamado-msg/.chamado-msg-bubble).
 */
(function () {
    'use strict';

    const thread   = document.getElementById('faq-fluxo-thread');
    const inputBar = document.getElementById('faq-fluxo-input-bar');
    const inputEl  = document.getElementById('faq-fluxo-input');
    const sendBtn  = document.getElementById('faq-fluxo-send');
    const launcher = document.getElementById('faq-fluxo-launcher');
    const widget   = document.getElementById('faq-fluxo-widget');
    const widgetClose = document.getElementById('faq-fluxo-widget-close');
    if (!thread) return;

    const ARROW_ICON = '<svg class="icon" aria-hidden="true" viewBox="0 0 256 256" fill="currentColor"><path d="M221.66,133.66l-72,72a8,8,0,0,1-11.32-11.32L196.69,136H40a8,8,0,0,1,0-16H196.69L138.34,61.66a8,8,0,0,1,11.32-11.32l72,72A8,8,0,0,1,221.66,133.66Z"/></svg>';

    const csrfToken   = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    const SESSAO_TOKEN = (window.crypto && crypto.randomUUID)
        ? crypto.randomUUID()
        : `${Date.now()}-${Math.random().toString(16).slice(2)}`;

    let flowRaiz = [];
    let aguardandoTexto = null; // resolve() da Promise de resposta_texto pendente
    let tipoCapturaAtual = 'texto'; // tipo esperado do bloco resposta_texto em andamento

    // Salva/atualiza a sessão do atendimento (fire-and-forget — nunca trava o chat).
    function salvarSessao(patch) {
        fetch('/api/faq/fluxo/sessao', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify(Object.assign({ sessao_token: SESSAO_TOKEN }, patch)),
        }).catch(() => {});
    }

    // Máscara de telefone brasileiro: (99) 99999-9999 (celular) ou (99) 9999-9999 (fixo).
    function mascararTelefone(valor) {
        const d = valor.replace(/\D/g, '').slice(0, 11);
        if (d.length <= 2) return d.replace(/^(\d*)/, '($1');
        if (d.length <= 6) return d.replace(/^(\d{2})(\d*)/, '($1) $2');
        if (d.length <= 10) return d.replace(/^(\d{2})(\d{4})(\d*)/, '($1) $2-$3');
        return d.replace(/^(\d{2})(\d{5})(\d*)/, '($1) $2-$3');
    }

    function escapeHtml(texto) {
        const div = document.createElement('div');
        div.textContent = texto;
        return div.innerHTML;
    }

    function scrollParaFim() {
        const box = thread.closest('.chamado-chat-messages');
        if (box) box.scrollTop = box.scrollHeight;
    }

    function criarBolha(texto, lado) {
        const linha = document.createElement('div');
        linha.className = `chamado-msg chamado-msg--${lado} is-first`;

        const bolha = document.createElement('div');
        bolha.className = `chamado-msg-bubble chamado-msg-bubble--${lado} is-first`;

        const p = document.createElement('p');
        p.className = 'chamado-msg-texto';
        p.innerHTML = escapeHtml(texto).replace(/\n/g, '<br>');

        bolha.appendChild(p);
        linha.appendChild(bolha);
        return linha;
    }

    // Bolha do bot com efeito de "digitando..." antes de mostrar o texto.
    function bolhaBotComDelay(texto) {
        return new Promise((resolve) => {
            const linha = document.createElement('div');
            linha.className = 'chamado-msg chamado-msg--esquerda is-first';
            const bolha = document.createElement('div');
            bolha.className = 'chamado-msg-bubble chamado-msg-bubble--esquerda is-first chamado-msg-typing';
            bolha.innerHTML = '<span></span><span></span><span></span>';
            linha.appendChild(bolha);
            thread.appendChild(linha);
            scrollParaFim();

            const delay = Math.min(Math.max((texto || '').length * 18, 400), 1600);
            setTimeout(() => {
                bolha.classList.remove('chamado-msg-typing');
                bolha.innerHTML = '';
                const p = document.createElement('p');
                p.className = 'chamado-msg-texto';
                p.innerHTML = escapeHtml(texto || '').replace(/\n/g, '<br>');
                bolha.appendChild(p);
                scrollParaFim();
                resolve();
            }, delay);
        });
    }

    // Mostra as opções dentro de uma bolha, igual às mensagens de texto do
    // bot — não como uma lista solta flutuando no thread.
    function mostrarOpcoes(opcoes, aoEscolher) {
        const linha = document.createElement('div');
        linha.className = 'chamado-msg chamado-msg--esquerda is-first';

        const bolha = document.createElement('div');
        bolha.className = 'chamado-msg-bubble chamado-msg-bubble--esquerda is-first';

        const wrap = document.createElement('div');
        wrap.className = 'faq-fluxo-opcoes';

        opcoes.forEach((op) => {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'faq-fluxo-opcao-btn';
            btn.innerHTML = `<span>${escapeHtml(op.label)}</span>${ARROW_ICON}`;
            btn.addEventListener('click', () => {
                linha.remove();
                thread.appendChild(criarBolha(op.label, 'direita'));
                scrollParaFim();
                aoEscolher(op);
            });
            wrap.appendChild(btn);
        });

        bolha.appendChild(wrap);
        linha.appendChild(bolha);
        thread.appendChild(linha);
        scrollParaFim();
    }

    function mostrarInputTexto(tipoCaptura) {
        tipoCapturaAtual = tipoCaptura || 'texto';

        return new Promise((resolve) => {
            aguardandoTexto = resolve;
            inputBar.hidden = false;
            inputEl.value = '';
            inputEl.type = tipoCapturaAtual === 'telefone' ? 'tel' : 'text';
            inputEl.oninput = tipoCapturaAtual === 'telefone'
                ? () => { inputEl.value = mascararTelefone(inputEl.value); }
                : null;
            inputEl.focus();
        });
    }

    function enviarTexto() {
        const valor = inputEl.value.trim();
        if (!valor || !aguardandoTexto) return;

        inputBar.hidden = true;
        thread.appendChild(criarBolha(valor, 'direita'));
        scrollParaFim();

        if (tipoCapturaAtual === 'nome')        salvarSessao({ nome: valor });
        else if (tipoCapturaAtual === 'telefone')    salvarSessao({ telefone: valor });
        else if (tipoCapturaAtual === 'imobiliaria') salvarSessao({ imobiliaria: valor });

        const resolve = aguardandoTexto;
        aguardandoTexto = null;
        inputEl.value = '';
        resolve(valor);
    }

    sendBtn.addEventListener('click', enviarTexto);
    inputEl.addEventListener('keydown', (e) => { if (e.key === 'Enter') enviarTexto(); });

    // ── Execução do fluxo ────────────────────────────────────────────────────

    function runFlow(blocos) {
        return blocos.reduce(
            (promise, bloco) => promise.then(() => processBlock(bloco)),
            Promise.resolve()
        );
    }

    function processBlock(bloco) {
        switch (bloco.type) {
            case 'mensagem':
                return bolhaBotComDelay(bloco.mensagem || '');

            case 'mensagem_botao': {
                const antesBotao = bloco.mensagem ? bolhaBotComDelay(bloco.mensagem) : Promise.resolve();
                return antesBotao.then(() => new Promise((resolve) => {
                    mostrarOpcoes([{ label: bloco.texto_botao || 'Continuar', valor: null }], () => resolve());
                }));
            }

            case 'resposta_texto':
                return bolhaBotComDelay(bloco.pergunta || '').then(() => mostrarInputTexto(bloco.tipo_captura));

            case 'opcoes': {
                const opcoes = (bloco.opcoes || []).map((op) => ({ label: op.nome, minifluxo: op.minifluxo || [] }));
                const mostrar = () => new Promise((resolve) => {
                    if (!opcoes.length) { resolve([]); return; }
                    mostrarOpcoes(opcoes, (escolhida) => {
                        salvarSessao({ caminho_add: escolhida.label });
                        resolve(escolhida.minifluxo);
                    });
                });
                const p = bloco.mensagem ? bolhaBotComDelay(bloco.mensagem) : Promise.resolve();
                return p.then(mostrar).then((minifluxo) => runFlow(minifluxo));
            }

            case 'chamado':
                return bolhaBotComDelay('Vou te ajudar a abrir um chamado com nossa equipe.').then(() => {
                    salvarSessao({ chamado_aberto: 1 });
                    const acoes = document.createElement('div');
                    acoes.className = 'faq-fluxo-final-acoes';
                    const btnChamado = document.createElement('a');
                    btnChamado.href = appUrl('/chamados/novo');
                    btnChamado.className = 'btn btn-primary btn-sm';
                    btnChamado.textContent = 'Abrir chamado';
                    acoes.appendChild(btnChamado);
                    thread.appendChild(acoes);
                    scrollParaFim();
                });

            default:
                return Promise.resolve();
        }
    }

    function mostrarEncerramento() {
        salvarSessao({ concluido: 1 });

        const acoes = document.createElement('div');
        acoes.className = 'faq-fluxo-final-acoes';
        const btnRecomecar = document.createElement('button');
        btnRecomecar.type = 'button';
        btnRecomecar.className = 'btn btn-secondary btn-sm';
        btnRecomecar.textContent = 'Recomeçar';
        btnRecomecar.addEventListener('click', recomecar);
        acoes.appendChild(btnRecomecar);
        thread.appendChild(acoes);
        scrollParaFim();
    }

    function recomecar() {
        thread.innerHTML = '';
        inputBar.hidden = true;
        aguardandoTexto = null;
        runFlow(flowRaiz).then(mostrarEncerramento);
    }

    function iniciarChat() {
        fetch('/api/faq/fluxo')
            .then((r) => r.json())
            .then((data) => {
                flowRaiz = Array.isArray(data.flow) ? data.flow : [];
                thread.innerHTML = '';

                if (!flowRaiz.length) {
                    thread.innerHTML = '<div class="chamado-thread-empty"><p>O chat guiado ainda não foi configurado.</p></div>';
                    return;
                }

                runFlow(flowRaiz).then(mostrarEncerramento);
            })
            .catch(() => {
                thread.innerHTML = '<div class="chamado-thread-empty"><p>Erro ao carregar o chat. Tente novamente mais tarde.</p></div>';
            });
    }

    // ── Abrir/fechar o widget flutuante ─────────────────────────────────────
    // O fluxo só é carregado/iniciado na primeira vez que o widget abre —
    // sem sentido rodar o chat "escondido" atrás do botão.
    if (launcher && widget) {
        let jaIniciou = false;

        launcher.addEventListener('click', () => {
            widget.hidden = false;
            launcher.hidden = true;
            if (!jaIniciou) {
                jaIniciou = true;
                iniciarChat();
            }
        });

        widgetClose?.addEventListener('click', () => {
            widget.hidden = true;
            launcher.hidden = false;
        });
    } else {
        iniciarChat();
    }
})();
