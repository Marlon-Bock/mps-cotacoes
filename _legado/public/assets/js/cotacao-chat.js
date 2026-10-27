/**
 * Chat/histórico da cotação — painel off-canvas único, aberto a partir de
 * qualquer botão [data-action="abrir-chat-cotacao"][data-protocolo="..."]
 * (grids de cotações e telas de detalhe). Reaproveita o padrão visual e de
 * fetch de pages/chamado.php, adaptado para múltiplos pontos de abertura,
 * feed misto (eventos de status + mensagens) e polling contínuo.
 */
(function () {
    var backdrop      = document.getElementById('cotacao-chat-backdrop');
    var offcanvas     = document.getElementById('cotacao-chat-offcanvas');
    var btnClose      = document.getElementById('cotacao-chat-close');
    var messagesEl    = document.getElementById('cotacao-chat-messages');
    var inputBar      = document.getElementById('cotacao-chat-input-bar');
    var closedNotice  = document.getElementById('cotacao-chat-closed-notice');
    var textarea      = document.getElementById('cotacao-chat-textarea');
    var btnSend       = document.getElementById('cotacao-chat-send');
    var btnAttach     = document.getElementById('cotacao-chat-attach');
    var titleEl       = document.getElementById('cotacao-chat-protocolo');
    var statusEl      = document.getElementById('cotacao-chat-status');
    if (!offcanvas || !messagesEl) return;

    var csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
    var myRole    = document.body.dataset.role === 'admin' ? 'admin' : 'imobiliaria';

    var ANEXO_ICON_SVG = '<svg class="icon" viewBox="0 0 448 512" fill="currentColor" aria-hidden="true">' +
        '<path d="M43.246 466.142c-58.43-60.289-57.341-157.511 1.386-217.581L254.392 34c44.316-45.332 116.351-45.336 160.671 0 43.89 44.894 43.943 117.329 0 162.276L232.214 383.128c-29.855 30.537-78.633 30.111-107.982-.998-28.275-29.97-27.368-77.473 1.452-106.953l143.743-146.835c6.182-6.314 16.312-6.422 22.626-.241l22.861 22.379c6.315 6.182 6.422 16.312.241 22.626L171.427 319.927c-4.932 5.045-5.236 13.428-.648 18.292 4.372 4.634 11.245 4.711 15.688.165l182.849-186.851c19.613-20.062 19.613-52.725-.011-72.798-19.189-19.627-49.957-19.637-69.154 0L90.39 293.295c-34.763 35.56-35.299 93.12-1.191 128.313 34.01 35.093 88.985 35.137 123.058.286l172.06-175.999c6.177-6.319 16.307-6.433 22.626-.256l22.877 22.364c6.319 6.177 6.434 16.307.256 22.626l-172.06 175.998c-59.576 60.938-155.943 60.216-214.77-.485z"/></svg>';

    var ANEXO_MAX_BYTES = 2 * 1024 * 1024;
    var ANEXO_EXT_PERMITIDAS = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'doc', 'docx'];

    var protocoloAtual = null;
    var lastId          = 0;
    var pollTimer       = null;
    var isOpen           = false;
    var lastDateKey      = null;

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
    }

    function rotuloData(d) {
        var hoje  = new Date();
        var ontem = new Date();
        ontem.setDate(hoje.getDate() - 1);
        var mesmoDia = function (a, b) {
            return a.getFullYear() === b.getFullYear() && a.getMonth() === b.getMonth() && a.getDate() === b.getDate();
        };
        if (mesmoDia(d, hoje))  return 'Hoje';
        if (mesmoDia(d, ontem)) return 'Ontem';
        return d.toLocaleDateString('pt-BR');
    }

    // Insere um separador de data (estilo "Hoje"/"Ontem"/dd/mm/aaaa) sempre
    // que o dia do item mudar em relação ao último renderizado — cobre tanto
    // a carga inicial do feed (a primeira data é a da criação da cotação)
    // quanto mensagens que chegam depois, via polling ou envio.
    function checarDivisorData(tsSegundos) {
        var d   = new Date(tsSegundos * 1000);
        var key = d.getFullYear() + '-' + d.getMonth() + '-' + d.getDate();
        if (key === lastDateKey) return;
        lastDateKey = key;

        var div = document.createElement('div');
        div.className = 'chamado-thread-date';
        div.dataset.date = key;
        div.innerHTML = '<span>' + escapeHtml(rotuloData(d)) + '</span>';
        messagesEl.appendChild(div);
    }

    function scrollToBottom() {
        messagesEl.scrollTop = messagesEl.scrollHeight;
    }

    function getThread() {
        var thread = messagesEl.querySelector('.chamado-thread');
        if (!thread) {
            messagesEl.innerHTML = '';
            thread = document.createElement('div');
            thread.className = 'chamado-thread';
            messagesEl.appendChild(thread);
        }
        return thread;
    }

    function getLastMsgState() {
        var thread = messagesEl.querySelector('.chamado-thread');
        if (!thread) return { autorKey: null, ts: 0 };
        var msgs    = thread.querySelectorAll('.chamado-msg');
        var lastMsg = msgs.length ? msgs[msgs.length - 1] : null;
        return {
            autorKey: lastMsg ? lastMsg.dataset.autorKey : null,
            ts:       lastMsg ? parseInt(lastMsg.dataset.ts || '0', 10) : 0
        };
    }

    function observacaoValida(obs) {
        // Dados antigos às vezes salvaram "0" literal como observação
        // (não é uma observação de verdade) — trata como "sem observação".
        var texto = (obs == null ? '' : String(obs)).trim();
        return texto !== '' && texto !== '0' ? texto : null;
    }

    var RE_LINK_BIOMETRIA = /^Link de biometria enviado: (\S+)$/;

    // Observação em texto solto que embute uma URL crua ("Link de biometria
    // enviado: https://...") — escapa o texto normalmente, mas troca a URL
    // por um link clicável + botão de copiar, em vez de deixar a string
    // inteira sem quebra (estourava a largura do card).
    function obsHtml(obs) {
        if (obs == null) return '';
        var m = RE_LINK_BIOMETRIA.exec(obs);
        if (!m) return escapeHtml(obs);
        var link = m[1];
        return 'Link de biometria enviado: <a href="' + escapeHtml(link) + '" target="_blank" rel="noopener" class="cotacao-feed-link">' + escapeHtml(link) + '</a>' +
            ' <button type="button" class="cotacao-feed-copiar" data-link="' + escapeHtml(link) + '" title="Copiar link">' + escapeHtml('Copiar') + '</button>';
    }

    function eventoTexto(ev) {
        var quem = escapeHtml(ev.usuario_nome || 'MPS');
        var obs  = observacaoValida(ev.observacao);
        if (ev.tipo_evento === 'CRIACAO') {
            return '<strong>Cotação criada</strong>' + (obs ? ' — ' + obsHtml(obs) : '');
        }
        var mudou = ev.status_anterior && ev.status_novo && ev.status_anterior !== ev.status_novo;
        var linha = mudou
            ? quem + ' alterou o status: <strong>' + escapeHtml(ev.status_anterior) + '</strong> &rarr; <strong>' + escapeHtml(ev.status_novo) + '</strong>'
            : quem + (ev.status_novo ? ': <strong>' + escapeHtml(ev.status_novo) + '</strong>' : ' atualizou a cotação');
        return linha + (obs ? ' — ' + obsHtml(obs) : '');
    }

    function appendEvento(ev) {
        var div = document.createElement('div');
        div.className = 'cotacao-feed-evento';
        div.innerHTML = '<span>' + eventoTexto(ev) + '</span>';
        messagesEl.appendChild(div);
    }

    // Clique no botão "Copiar" de um link de biometria dentro do chat: copia
    // pro clipboard e manda uma mensagem normal de chat avisando ("Link da
    // biometria copiado!") — aparece como uma mensagem comum, não como um
    // evento de sistema, pra ficar visível igual o resto da conversa.
    messagesEl.addEventListener('click', function (e) {
        var btn = e.target.closest('.cotacao-feed-copiar');
        if (!btn) return;
        var link = btn.dataset.link;
        if (!link) return;

        function avisarCopia() {
            var textoOriginal = btn.textContent;
            btn.disabled = true;
            btn.textContent = 'Copiado!';
            setTimeout(function () { btn.disabled = false; btn.textContent = textoOriginal; }, 2000);

            if (!protocoloAtual) return;
            fetch('/api/cotacao/' + protocoloAtual + '/mensagem', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({ mensagem: 'Link da biometria copiado!' }),
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data && data.success && data.mensagem) {
                        var ts = Math.floor(new Date(data.mensagem.criado_em.replace(' ', 'T')).getTime() / 1000);
                        checarDivisorData(ts);
                        appendMensagem({
                            id: data.mensagem.id, autor_tipo: data.mensagem.autor_tipo, autor_nome: data.mensagem.autor_nome,
                            mensagem: data.mensagem.mensagem, ts: ts, hora: data.mensagem.hora
                        });
                        lastId = Math.max(lastId, data.mensagem.id);
                        scrollToBottom();
                    }
                })
                .catch(function () {});
        }

        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(link).then(avisarCopia).catch(avisarCopia);
        } else {
            avisarCopia();
        }
    });

    function appendMensagem(m) {
        getThread();
        var state    = getLastMsgState();
        var autorKey = m.autor_tipo + '_' + (m.autor_id || m.id);
        var ehMinha  = m.autor_tipo === myRole;
        var ts       = m.ts || Math.floor(new Date((m.criado_em || '').replace(' ', 'T')).getTime() / 1000);
        var isFirst  = (autorKey !== state.autorKey) || Math.abs(ts - state.ts) > 300;

        var msgDiv = document.createElement('div');
        msgDiv.className = 'chamado-msg ' + (ehMinha ? 'chamado-msg--direita' : 'chamado-msg--esquerda') + (isFirst ? '' : ' is-continuation');
        msgDiv.dataset.msgId    = m.id;
        msgDiv.dataset.autorKey = autorKey;
        msgDiv.dataset.ts       = ts;

        var bubbleClass = 'chamado-msg-bubble ' + (ehMinha ? 'chamado-msg-bubble--direita' : 'chamado-msg-bubble--esquerda') + ' ' + (isFirst ? 'is-first' : 'is-continuation');
        var autorHtml = (!ehMinha && isFirst) ? '<span class="chamado-msg-autor">' + escapeHtml(m.autor_nome || 'MPS') + '</span>' : '';

        var texto = (m.mensagem || '').trim();
        var textoHtml = texto ? '<div class="chamado-msg-texto">' + escapeHtml(texto).replace(/\n/g, '<br>') + '</div>' : '';

        var anexoHtml = '';
        if (m.anexo_url) {
            anexoHtml = '<a class="chamado-msg-anexo" href="' + escapeHtml(m.anexo_url) + '" target="_blank" rel="noopener">' +
                ANEXO_ICON_SVG +
                '<span>' + escapeHtml(m.anexo_nome || 'Documento') + '</span>' +
            '</a>';
        }

        msgDiv.innerHTML =
            '<div class="' + bubbleClass + '">' +
                autorHtml +
                textoHtml +
                anexoHtml +
                '<div class="chamado-msg-meta"><span class="chamado-msg-data">' + escapeHtml(m.hora || '') + '</span></div>' +
            '</div>';

        messagesEl.appendChild(msgDiv);
        lastId = Math.max(lastId, parseInt(m.id, 10) || 0);
    }

    function setEncerrado(encerrado) {
        if (!inputBar || !closedNotice) return;
        inputBar.hidden     = !!encerrado;
        closedNotice.hidden = !encerrado;
        if (btnAttach) btnAttach.disabled = !!encerrado;
    }

    function limparBadge(protocolo) {
        document.querySelectorAll('[data-action="abrir-chat-cotacao"][data-protocolo="' + protocolo + '"]')
            .forEach(function (btn) {
                btn.querySelectorAll('.count-badge').forEach(function (el) { el.remove(); });
                btn.classList.remove('btn-danger-outline');
                btn.classList.add('btn-secondary');
            });
    }

    // Revalida os badges de todo botão de chat já renderizado na página
    // contra o servidor — é o que faz ler o chat em um lugar (grid, outra
    // aba, tela de detalhe) refletir em qualquer outro lugar que mostre a
    // mesma cotação, sem precisar dar reload manual.
    function sincronizarBadges() {
        var botoes = document.querySelectorAll('[data-action="abrir-chat-cotacao"][data-protocolo]');
        if (!botoes.length) return;

        var protocolos = [];
        botoes.forEach(function (btn) {
            if (protocolos.indexOf(btn.dataset.protocolo) === -1) protocolos.push(btn.dataset.protocolo);
        });

        fetch('/api/cotacao/unread-lote?protocolos=' + encodeURIComponent(protocolos.join(',')))
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success) return;
                var unread = data.unread || {};

                // Som/toast/notificação do SO ficam centralizados só no poller
                // do sino (notificacoes-bell.js) — a mesma mensagem de chat já
                // vira uma notificação em notificacoes_mps, então tocar aqui
                // também duplicava o aviso (e em horários diferentes, já que
                // são dois pollers independentes). Este fetch cuida só dos
                // badges visuais por cotação.

                botoes.forEach(function (btn) {
                    var count = unread[btn.dataset.protocolo] || 0;
                    var badge = btn.querySelector('.count-badge');
                    if (count > 0) {
                        if (!badge) {
                            badge = document.createElement('span');
                            badge.className = 'count-badge';
                            btn.appendChild(badge);
                        }
                        badge.textContent = count > 99 ? '99+' : String(count);
                        btn.classList.remove('btn-secondary');
                        btn.classList.add('btn-danger-outline');
                    } else if (badge) {
                        badge.remove();
                        btn.classList.remove('btn-danger-outline');
                        btn.classList.add('btn-secondary');
                    }
                });
            })
            .catch(function () {});
    }

    function appendDivisorNaoLidas(count) {
        var div = document.createElement('div');
        div.className = 'chamado-thread-date cotacao-nao-lidas-divisor';
        div.innerHTML = '<span>' + count + (count === 1 ? ' não lida' : ' não lidas') + '</span>';
        messagesEl.appendChild(div);
    }

    function renderFeed(feed, naoLidas) {
        messagesEl.innerHTML = '';
        lastDateKey = null;
        if (!feed.length) {
            messagesEl.innerHTML = '<div class="chamado-thread-empty"><p>Nenhum evento ainda.</p></div>';
            return;
        }
        var thread = document.createElement('div');
        thread.className = 'chamado-thread';
        messagesEl.appendChild(thread);

        var divisorIndex = naoLidas > 0 ? Math.max(0, feed.length - naoLidas) : -1;

        feed.forEach(function (item, idx) {
            var ts = Math.floor(new Date(item.ts.replace(' ', 'T')).getTime() / 1000);
            checarDivisorData(ts);
            if (idx === divisorIndex) appendDivisorNaoLidas(naoLidas);

            if (item.tipo === 'evento') {
                appendEvento(item);
            } else {
                appendMensagem({
                    id: item.id, autor_tipo: item.autor_tipo, autor_nome: item.autor_nome,
                    mensagem: item.mensagem, ts: ts,
                    anexo_url: item.anexo_url, anexo_nome: item.anexo_nome,
                    hora: new Date(ts * 1000).toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
                });
            }
        });
    }

    function stopPolling() {
        if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
    }

    function poll() {
        if (!isOpen || !protocoloAtual) return;
        fetch('/api/cotacao/' + protocoloAtual + '/mensagens?depois_de=' + lastId)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok) return;
                if (data.mensagens && data.mensagens.length) {
                    // O servidor só retorna mensagens depois de "lastId", que já é
                    // atualizado ao enviar por esta aba (appendMensagem) — então
                    // tudo que chega aqui é novo para esta aba, mesmo vindo de
                    // outra aba/sessão da mesma conta.
                    data.mensagens.forEach(function (m) {
                        var ts = Math.floor(new Date((m.criado_em || '').replace(' ', 'T')).getTime() / 1000);
                        checarDivisorData(ts);
                        appendMensagem(m);
                    });
                    scrollToBottom();
                    // Som/toast/notificação do SO ficam só no poller do sino
                    // (notificacoes-bell.js) — ver comentário em sincronizarBadges().
                }
                if (data.encerrado) setEncerrado(true);
            })
            .catch(function () {});
    }

    function startPolling() {
        stopPolling();
        pollTimer = setInterval(poll, 60000);
    }

    function openChat(protocolo) {
        protocoloAtual = protocolo;
        lastId  = 0;
        isOpen  = true;

        titleEl.textContent  = protocolo;
        statusEl.textContent = 'Carregando...';
        messagesEl.innerHTML = '<div class="chamado-thread-empty"><p>Carregando...</p></div>';
        if (textarea) textarea.value = '';
        setEncerrado(false);

        backdrop.classList.add('is-open');
        offcanvas.classList.add('is-open');
        offcanvas.setAttribute('aria-hidden', 'false');

        fetch('/api/cotacao/' + protocolo + '/historico')
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.success) {
                    messagesEl.innerHTML = '<div class="chamado-thread-empty"><p>' + escapeHtml(data.message || 'Erro ao carregar.') + '</p></div>';
                    return;
                }
                statusEl.textContent = data.status || '';
                renderFeed(data.feed || [], data.nao_lidas || 0);
                scrollToBottom();
                limparBadge(protocolo);
                setEncerrado(!!data.encerrado);
                startPolling();
            })
            .catch(function () {
                messagesEl.innerHTML = '<div class="chamado-thread-empty"><p>Erro na requisição.</p></div>';
            });
    }

    function closeChat() {
        isOpen = false;
        stopPolling();
        backdrop.classList.remove('is-open');
        offcanvas.classList.remove('is-open');
        offcanvas.setAttribute('aria-hidden', 'true');
        protocoloAtual = null;
    }

    // ── Envio ───────────────────────────────────────────────────────────────
    if (textarea) {
        textarea.addEventListener('input', function () {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 140) + 'px';
        });
        textarea.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                if (btnSend && !btnSend.disabled) btnSend.click();
            }
        });
    }

    if (btnSend) {
        btnSend.addEventListener('click', function () {
            var msg = textarea ? textarea.value.trim() : '';
            if (!msg || !protocoloAtual) return;
            btnSend.disabled = true;

            fetch('/api/cotacao/' + protocoloAtual + '/mensagem', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({ mensagem: msg })
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    btnSend.disabled = false;
                    if (data.success) {
                        textarea.value = '';
                        textarea.style.height = 'auto';
                        var ts = Math.floor(new Date((data.mensagem.criado_em || '').replace(' ', 'T')).getTime() / 1000);
                        checarDivisorData(ts);
                        appendMensagem(data.mensagem);
                        scrollToBottom();
                    } else {
                        mpsAlert.error(data.message || 'Erro ao enviar mensagem.');
                    }
                })
                .catch(function () {
                    btnSend.disabled = false;
                    mpsAlert.error('Erro na requisição. Tente novamente.');
                });
        });
    }

    // ── Envio de anexo — modal (mpsAlert) com dropzone + preview + envio ─────
    function formatBytes(n) {
        if (n < 1024) return n + ' B';
        if (n < 1024 * 1024) return Math.round(n / 1024) + ' KB';
        return (n / (1024 * 1024)).toFixed(1) + ' MB';
    }

    function validarArquivoAnexo(file) {
        var ext = (file.name.split('.').pop() || '').toLowerCase();
        if (ANEXO_EXT_PERMITIDAS.indexOf(ext) === -1) {
            return 'Tipo de arquivo não permitido. Envie PDF, Word, JPG, PNG ou WEBP.';
        }
        if (file.size > ANEXO_MAX_BYTES) {
            return 'Arquivo excede o tamanho máximo de 2 MB.';
        }
        return null;
    }

    function renderPickerAnexo() {
        var html =
            '<h3 class="mps-alert-title">Enviar documento</h3>' +
            '<div class="mps-dropzone" id="cotacao-anexo-dropzone" tabindex="0">' +
                ANEXO_ICON_SVG.replace('class="icon"', 'class="icon mps-dropzone-icon"') +
                '<p class="mps-dropzone-text">Arraste um arquivo aqui ou <strong>clique para selecionar</strong></p>' +
                '<span class="mps-dropzone-hint">PDF, Word, JPG, PNG ou WEBP — até 2 MB</span>' +
            '</div>' +
            '<input type="file" id="cotacao-anexo-input" accept=".pdf,.jpg,.jpeg,.png,.webp,.doc,.docx" hidden>' +
            '<div class="mps-alert-actions">' +
                '<button type="button" class="btn btn-secondary" data-anexo-cancelar>Cancelar</button>' +
            '</div>';

        mpsAlert.custom(html, {
            onMount: function (card, close) {
                var dropzone  = card.querySelector('#cotacao-anexo-dropzone');
                var fileInput = card.querySelector('#cotacao-anexo-input');

                card.querySelector('[data-anexo-cancelar]').addEventListener('click', close);
                dropzone.addEventListener('click', function () { fileInput.click(); });
                dropzone.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); fileInput.click(); }
                });

                ['dragenter', 'dragover'].forEach(function (evt) {
                    dropzone.addEventListener(evt, function (e) {
                        e.preventDefault();
                        dropzone.classList.add('is-dragover');
                    });
                });
                ['dragleave', 'drop'].forEach(function (evt) {
                    dropzone.addEventListener(evt, function (e) {
                        e.preventDefault();
                        dropzone.classList.remove('is-dragover');
                    });
                });
                dropzone.addEventListener('drop', function (e) {
                    var file = e.dataTransfer.files && e.dataTransfer.files[0];
                    if (file) selecionarArquivoAnexo(file);
                });
                fileInput.addEventListener('change', function () {
                    var file = fileInput.files && fileInput.files[0];
                    if (file) selecionarArquivoAnexo(file);
                });
            }
        });
    }

    function selecionarArquivoAnexo(file) {
        var erro = validarArquivoAnexo(file);
        if (erro) { mpsAlert.error(erro); return; }
        renderPreviewAnexo(file);
    }

    function renderPreviewAnexo(file) {
        var html =
            '<h3 class="mps-alert-title">Enviar documento</h3>' +
            '<div class="mps-file-preview">' +
                ANEXO_ICON_SVG.replace('class="icon"', 'class="icon mps-file-preview-icon"') +
                '<div class="mps-file-preview-info">' +
                    '<span class="mps-file-preview-nome">' + escapeHtml(file.name) + '</span>' +
                    '<span class="mps-file-preview-tamanho">' + escapeHtml(formatBytes(file.size)) + '</span>' +
                '</div>' +
            '</div>' +
            '<div class="mps-alert-actions">' +
                '<button type="button" class="btn btn-secondary" data-anexo-trocar>Trocar arquivo</button>' +
                '<button type="button" class="btn btn-primary" data-anexo-enviar>Enviar</button>' +
            '</div>';

        mpsAlert.custom(html, {
            onMount: function (card) {
                card.querySelector('[data-anexo-trocar]').addEventListener('click', renderPickerAnexo);
                card.querySelector('[data-anexo-enviar]').addEventListener('click', function () {
                    enviarAnexo(file);
                });
            }
        });
    }

    function enviarAnexo(file) {
        mpsAlert.loading('Enviando documento...');

        var fd = new FormData();
        fd.append('arquivo', file);

        fetch('/api/cotacao/' + protocoloAtual + '/mensagem-anexo', {
            method: 'POST',
            headers: { 'X-CSRF-Token': csrfToken },
            body: fd
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.success) {
                    mpsAlert.close();
                    mpsAlert.toast('success', 'Documento enviado.');
                    var ts = Math.floor(new Date((data.mensagem.criado_em || '').replace(' ', 'T')).getTime() / 1000);
                    checarDivisorData(ts);
                    appendMensagem(data.mensagem);
                    scrollToBottom();
                } else {
                    mpsAlert.error(data.message || 'Erro ao enviar arquivo.');
                }
            })
            .catch(function () {
                mpsAlert.error('Erro na requisição. Tente novamente.');
            });
    }

    if (btnAttach) {
        btnAttach.addEventListener('click', function () {
            if (!protocoloAtual || btnAttach.disabled) return;
            renderPickerAnexo();
        });
    }

    // ── Abertura via delegação de evento (funciona em botões criados via AJAX) ──
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-action="abrir-chat-cotacao"]');
        if (btn) {
            e.preventDefault();
            openChat(btn.dataset.protocolo);
        }
    });

    if (btnClose) btnClose.addEventListener('click', closeChat);
    if (backdrop) backdrop.addEventListener('click', closeChat);
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape' && isOpen) closeChat();
    });

    // ── Revalida os badges ao voltar de outra aba/janela (cobre o caso de
    // duas abas/telas abertas na mesma cotação). O polling em si não pausa
    // mais em segundo plano — só desacelera (ver throttle dentro de poll())
    // — pra permitir o som de notificação mesmo com a aba fora de foco. ──────
    document.addEventListener('visibilitychange', function () {
        if (document.hidden) return;
        sincronizarBadges();
        if (isOpen) poll();
    });

    // Mantém os badges de chat não lido atualizados mesmo parado numa lista
    // de cotações, sem precisar trocar de aba. Só visual — som/toast/SO
    // ficam no poller do sino (30s, mesmo ritmo, ver notificacoes-bell.js).
    setInterval(sincronizarBadges, 30000);

    // ── bfcache: evita pollers duplicados e revalida os badges ao voltar
    // com o botão do navegador (a página pode ter sido restaurada do cache
    // sem passar pelo servidor de novo). ───────────────────────────────────
    window.addEventListener('pageshow', function (e) {
        sincronizarBadges();
        if (e.persisted && isOpen) {
            stopPolling();
            startPolling();
        }
    });

    // Sincroniza uma vez já na carga inicial também.
    sincronizarBadges();
}());
