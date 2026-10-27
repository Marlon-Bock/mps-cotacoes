/**
 * Painel de exibição (/painel) — TV da empresa.
 *
 * Busca /api/painel/dados a cada 15s, atualiza os números e, quando aparece
 * uma cotação que ainda não estava na tela, toca o sino, fala o aviso em voz
 * alta e mostra o mpsAlert no centro.
 *
 * Sino: sintetizado no próprio navegador (WebAudio). Voz: SpeechSynthesis, em
 * pt-BR. Nenhum dos dois precisa de arquivo de áudio. Navegadores só liberam
 * som depois de um clique — daí a sobreposição "Iniciar exibição" na abertura.
 */
(function () {
    'use strict';

    var INTERVALO_MS   = 15000;   // frequência de atualização
    var ALERTA_MS      = 5000;    // tempo mínimo que o alerta fica na tela
    var ALERTA_MAX_MS  = 14000;   // teto, caso a voz trave no meio da frase
    var MAX_ALERTAS    = 3;       // por rodada, pra não empilhar som se chegarem várias
    var VOLUME_SINO    = 0.9;     // pico do sino (0 a 1) — TV fica longe das mesas

    // Frase completa do produto: título de capitalização não é seguro, então a
    // palavra "seguro" faz parte do rótulo em vez de ser fixa no aviso.
    var PRODUTO_LABEL = {
        fianca: 'seguro fiança',
        auto: 'seguro auto',
        titulo: 'título de capitalização',
        condominial: 'seguro condominial',
    };

    var conhecidas   = null;      // Set de protocolos já vistos (null = primeira carga)
    var audioCtx     = null;
    var somLiberado  = false;
    var filaAlertas  = [];
    var exibindo     = false;

    var el = {
        hoje:       document.querySelector('[data-painel="hoje"]'),
        relogio:    document.querySelector('[data-painel="relogio"]'),
        data:       document.querySelector('[data-painel="data"]'),
        produtos:   document.querySelector('[data-painel="produtos"]'),
        recentes:   document.querySelector('[data-painel="recentes"]'),
        atualizado: document.querySelector('[data-painel="atualizado"]'),
        erro:       document.querySelector('[data-painel="erro"]'),
        inicio:     document.querySelector('[data-painel="inicio"]'),
        iniciar:    document.querySelector('[data-painel="iniciar"]'),
        testar:     document.querySelector('[data-painel="testar"]'),
    };

    // ── Relógio ──────────────────────────────────────────────────────────────
    var DIAS  = ['domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado'];
    var MESES = ['janeiro', 'fevereiro', 'março', 'abril', 'maio', 'junho', 'julho', 'agosto', 'setembro', 'outubro', 'novembro', 'dezembro'];

    function doisDigitos(n) { return n < 10 ? '0' + n : String(n); }

    function atualizarRelogio() {
        var agora = new Date();
        if (el.relogio) el.relogio.textContent = doisDigitos(agora.getHours()) + ':' + doisDigitos(agora.getMinutes());
        if (el.data) {
            el.data.textContent = DIAS[agora.getDay()] + ', ' + agora.getDate() + ' de ' + MESES[agora.getMonth()];
        }
    }

    // ── Som ──────────────────────────────────────────────────────────────────
    function liberarSom() {
        try {
            var Ctx = window.AudioContext || window.webkitAudioContext;
            if (Ctx) {
                audioCtx = new Ctx();
                if (audioCtx.state === 'suspended') audioCtx.resume();
                somLiberado = true;
            }
        } catch (e) {
            somLiberado = false;
        }

        // A fala também precisa do gesto do usuário: uma frase muda destrava.
        if (window.speechSynthesis) {
            try {
                window.speechSynthesis.resume();
                var mudo = new SpeechSynthesisUtterance(' ');
                mudo.volume = 0;
                window.speechSynthesis.speak(mudo);
                escolherVoz();
                window.speechSynthesis.onvoiceschanged = escolherVoz;
            } catch (e) {}
        }
    }

    /** Sino curto de duas notas, no volume máximo — a TV fica longe das mesas. */
    function tocarSino() {
        if (!somLiberado || !audioCtx) return;
        if (audioCtx.state === 'suspended') audioCtx.resume();

        [[880, 0], [1174.7, 0.18]].forEach(function (nota) {
            var osc  = audioCtx.createOscillator();
            var gain = audioCtx.createGain();
            var t0   = audioCtx.currentTime + nota[1];

            osc.type = 'triangle';   // mais presente que a senoidal no alto-falante da TV
            osc.frequency.setValueAtTime(nota[0], t0);

            gain.gain.setValueAtTime(0.0001, t0);
            gain.gain.exponentialRampToValueAtTime(VOLUME_SINO, t0 + 0.02);
            gain.gain.exponentialRampToValueAtTime(0.0001, t0 + 0.9);

            osc.connect(gain);
            gain.connect(audioCtx.destination);
            osc.start(t0);
            osc.stop(t0 + 0.95);
        });
    }

    // ── Voz ──────────────────────────────────────────────────────────────────
    var vozPtBr = null;

    function escolherVoz() {
        if (!window.speechSynthesis) return;
        var vozes = window.speechSynthesis.getVoices() || [];

        vozPtBr = vozes.filter(function (v) { return /^pt[-_]BR/i.test(v.lang); })[0]
               || vozes.filter(function (v) { return /^pt/i.test(v.lang); })[0]
               || null;
    }

    /** Fala o aviso. Chama pronto() quando termina (ou na hora, se não houver voz). */
    function falarAviso(texto, pronto) {
        if (!window.speechSynthesis) { pronto(); return; }

        try {
            var fala = new SpeechSynthesisUtterance(texto);
            fala.lang   = vozPtBr ? vozPtBr.lang : 'pt-BR';
            fala.volume = 1;
            fala.rate   = 0.95;
            fala.pitch  = 1;
            if (vozPtBr) fala.voice = vozPtBr;

            fala.onend   = pronto;
            fala.onerror = pronto;

            window.speechSynthesis.cancel();   // descarta fala anterior travada
            window.speechSynthesis.speak(fala);
        } catch (e) {
            pronto();
        }
    }

    // ── Alerta central ───────────────────────────────────────────────────────
    function escapar(valor) {
        var div = document.createElement('div');
        div.textContent = valor == null ? '' : String(valor);
        return div.innerHTML;
    }

    function enfileirarAlerta(cotacao) {
        filaAlertas.push(cotacao);
        if (!exibindo) proximoAlerta();
    }

    function proximoAlerta() {
        var cotacao = filaAlertas.shift();
        if (!cotacao) { exibindo = false; return; }

        exibindo = true;

        var produto = PRODUTO_LABEL[cotacao.produto] || cotacao.produto_label || '';

        mpsAlert.custom(
            '<div class="painel-alerta">' +
                '<span class="painel-alerta-tag">Nova cotação</span>' +
                '<h3 class="painel-alerta-titulo">Nova cotação de ' + escapar(produto) + ' chegou</h3>' +
                '<p class="painel-alerta-proto">' + escapar(cotacao.protocolo) + '</p>' +
                '<p class="painel-alerta-imob">' + escapar(cotacao.imobiliaria) + '</p>' +
            '</div>'
        );

        tocarSino();

        // Sino primeiro, voz logo depois — falar por cima do sino embola tudo.
        var fechado = false;
        var minimo  = false;
        var falou   = false;

        function fechar() {
            if (fechado || !minimo || !falou) return;
            fechado = true;
            mpsAlert.close();
            setTimeout(proximoAlerta, 400);
        }

        setTimeout(function () { minimo = true; fechar(); }, ALERTA_MS);
        setTimeout(function () { minimo = true; falou = true; fechar(); }, ALERTA_MAX_MS);

        // O protocolo fica só na tela — falado, vira uma sopa de letras e números
        // que ninguém entende de longe.
        setTimeout(function () {
            falarAviso(
                'Nova cotação de ' + produto + ' chegou!',
                function () { falou = true; fechar(); }
            );
        }, 1000);
    }

    // ── Render ───────────────────────────────────────────────────────────────
    function preencherTotais(totais) {
        Object.keys(totais).forEach(function (chave) {
            var alvo = document.querySelector('[data-painel-total="' + chave + '"]');
            if (alvo) alvo.textContent = totais[chave];
        });
    }

    function preencherProdutos(linhas) {
        if (!el.produtos) return;

        if (!linhas.length) {
            el.produtos.innerHTML = '<tr><td colspan="6" class="painel-vazio">Sem cotações.</td></tr>';
            return;
        }

        el.produtos.innerHTML = linhas.map(function (l) {
            return '<tr>' +
                '<td class="painel-tabela-produto">' + escapar(l.label) + '</td>' +
                '<td>' + l.pre_analise + '</td>' +
                '<td>' + l.aguardando + '</td>' +
                '<td>' + l.em_analise + '</td>' +
                '<td>' + l.aprovados + '</td>' +
                '<td class="painel-tabela-hoje">' + l.hoje + '</td>' +
            '</tr>';
        }).join('');
    }

    function preencherRecentes(lista) {
        if (!el.recentes) return;

        if (!lista.length) {
            el.recentes.innerHTML = '<li class="painel-vazio">Nenhuma cotação ainda.</li>';
            return;
        }

        el.recentes.innerHTML = lista.slice(0, 6).map(function (c) {
            return '<li class="painel-recente">' +
                '<span class="painel-recente-produto">' + escapar(c.produto_label) + '</span>' +
                '<span class="painel-recente-proto">' + escapar(c.protocolo) + '</span>' +
                '<span class="painel-recente-imob">' + escapar(c.imobiliaria) + '</span>' +
                '<span class="painel-recente-hora">' + escapar(c.hora) + '</span>' +
            '</li>';
        }).join('');
    }

    function detectarNovas(lista) {
        // Primeira carga: só memoriza o que já existe, sem alertar.
        if (conhecidas === null) {
            conhecidas = new Set(lista.map(function (c) { return c.protocolo; }));
            return;
        }

        var novas = lista.filter(function (c) { return !conhecidas.has(c.protocolo); });
        novas.forEach(function (c) { conhecidas.add(c.protocolo); });

        // Mais antiga primeiro, pra fila de alertas seguir a ordem de chegada.
        novas.slice(0, MAX_ALERTAS).reverse().forEach(enfileirarAlerta);
    }

    // ── Ciclo de atualização ─────────────────────────────────────────────────
    function marcarErro(temErro) {
        if (el.erro) el.erro.hidden = !temErro;
    }

    function carregar() {
        fetch('/api/painel/dados', { headers: { 'Accept': 'application/json' } })
            .then(function (r) {
                if (r.status === 401) { window.location.href = appUrl('/painel/login'); return null; }
                return r.json();
            })
            .then(function (dados) {
                if (!dados || !dados.success) { marcarErro(true); return; }

                marcarErro(false);
                if (el.hoje) el.hoje.textContent = dados.hoje;
                if (el.atualizado) el.atualizado.textContent = dados.atualizado;

                preencherTotais(dados.totais || {});
                preencherProdutos(dados.por_produto || []);
                preencherRecentes(dados.recentes || []);
                detectarNovas(dados.recentes || []);
            })
            .catch(function () { marcarErro(true); });
    }

    // ── Início ───────────────────────────────────────────────────────────────
    if (el.iniciar) {
        el.iniciar.addEventListener('click', function () {
            liberarSom();
            if (el.inicio) el.inicio.hidden = true;

            var raiz = document.documentElement;
            if (raiz.requestFullscreen) raiz.requestFullscreen().catch(function () {});
        });
    }

    // Botão do rodapé: dispara um aviso de mentira só pra conferir o volume.
    if (el.testar) {
        el.testar.addEventListener('click', function () {
            if (!somLiberado) liberarSom();
            enfileirarAlerta({
                produto: 'fianca',
                produto_label: 'Fiança',
                protocolo: 'MPS2026-00000',
                imobiliaria: 'Teste de som do painel',
            });
        });
    }

    atualizarRelogio();
    setInterval(atualizarRelogio, 20000);

    carregar();
    setInterval(carregar, INTERVALO_MS);
}());
