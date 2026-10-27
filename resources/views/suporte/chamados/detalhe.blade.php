@extends('layouts.app')

@section('content')
            <!-- ── Header do chamado ─────────────────────────────────────── -->
            <div class="panel">
                <div class="panel-body detalhe-panel-header">
                    <div class="dph-top">
                        <span class="dph-tipo">{!! icon('headset') !!} Chamado de Suporte &middot; {{ $categoria_label }}</span>
                        <span class="status-pill {!! $status_pill !!}">{!! $status_label !!}</span>
                    </div>
                    <span class="dph-proto">{!! $protocolo !!}</span>
                    <div class="dph-bar">
                        <span class="dph-bar-item">
                            {!! icon('user') !!}
                            <span class="dph-bar-label">Aberto por</span>
                            <span class="dph-bar-value">{{ $chamado['autor_nome_user'] ?? $current_user->nome }}</span>
                        </span>
                        @if (!empty($chamado['atendente_nome']))
                        <span class="dph-bar-sep">&middot;</span>
                        <span class="dph-bar-item">
                            {!! icon('users') !!}
                            <span class="dph-bar-label">Atendente MPS</span>
                            <span class="dph-bar-value">{{ $chamado['atendente_nome'] }}</span>
                        </span>
                        @endif
                        <span class="dph-bar-sep">&middot;</span>
                        <span class="dph-bar-item">
                            {!! icon('clock') !!}
                            <span class="dph-bar-label">Aberto em</span>
                            <span class="dph-bar-value">{{ date('d/m/Y \à\s H:i', strtotime($chamado['criado_em'])) }}</span>
                        </span>
                        <span class="dph-bar-spacer"></span>
                        @if (!$encerrado)
                            <button class="btn btn-secondary btn-sm" id="btn-encerrar"
                                    data-protocolo="{{ $protocolo }}">
                                {!! icon('x') !!} Encerrar chamado
                            </button>
                        @endif
                    </div>
                </div>
            </div>

            <!-- ── Layout principal ──────────────────────────────────────── -->
            <div class="detalhe-layout cotacao-container">

                <!-- ── Coluna esquerda: chat ────────────────────────────── -->
                <div class="detalhe-left">
                    <div class="chamado-chat-box">

                        <!-- Mensagens -->
                        <div class="chamado-chat-messages" id="chamado-messages"
                             data-protocolo="{{ $protocolo }}"
                             data-last-id="{!! $last_msg_id !!}"
                             data-status="{{ $chamado['status'] }}">
                        @if (empty($respostas))
                            <div class="chamado-thread-empty">
                                {!! icon('headset') !!}
                                <p>Nenhuma mensagem ainda.<br>Envie uma resposta abaixo para iniciar o atendimento.</p>
                            </div>
                        @else
                            <div class="chamado-thread">
                                @php
                                $last_date  = null;
                                $last_autor = null;
                                $last_ts    = null;
                                @endphp
                                @foreach ($respostas as $idx => $msg)
                                @php
                                    $eh_imob   = $msg['autor_tipo'] === 'imobiliaria';
                                    $autor_key = $msg['autor_tipo'] . '_' . ($msg['autor_id'] ?? '');
                                    $nome_mps  = e($msg['autor_nome'] ?: 'Equipe MPS');
                                    $msg_date  = date('Y-m-d', strtotime($msg['criado_em']));
                                    $hora      = date('H:i', strtotime($msg['criado_em']));

                                    // Agrupa se mesmo autor E dentro de 5 minutos
                                    $ts_atual  = strtotime($msg['criado_em']);
                                    $is_first  = ($autor_key !== $last_autor)
                                              || (abs($ts_atual - ($last_ts ?? 0)) > 300);

                                    $last_autor = $autor_key;
                                    $last_ts    = $ts_atual;
                                @endphp
                                @if ($msg_date !== $last_date)
                                    @php $last_date = $msg_date; @endphp
                                    <div class="chamado-thread-date" data-date="{{ $msg_date }}">
                                        <span>{!! date('d/m/Y', strtotime($msg['criado_em'])) !!}</span>
                                    </div>
                                @endif

                                @php
                                    $texto_html = trim((string) $msg['mensagem']) !== ''
                                        ? '<div class="chamado-msg-texto">' . nl2br(e($msg['mensagem'])) . '</div>'
                                        : '';
                                    $anexo_html = !empty($msg['anexo_url'])
                                        ? '<a class="chamado-msg-anexo" href="' . e($msg['anexo_url']) . '" target="_blank" rel="noopener">'
                                            . icon('paperclip') . '<span>' . e($msg['anexo_nome'] ?: 'Documento') . '</span></a>'
                                        : '';
@endphp
                                @if ($eh_imob) {{-- ── Direita (usuário) ── --}}
                                    <div class="chamado-msg chamado-msg--direita {!! $is_first ? '' : 'is-continuation' !!}"
                                         data-msg-id="{!! (int) $msg['id'] !!}" data-autor-key="{{ $autor_key }}" data-ts="{!! $ts_atual !!}">
                                        <div class="chamado-msg-bubble chamado-msg-bubble--direita {!! $is_first ? 'is-first' : 'is-continuation' !!}">
                                            {!! $texto_html !!}
                                            {!! $anexo_html !!}
                                            <div class="chamado-msg-meta">
                                                <span class="chamado-msg-data">{!! $hora !!}</span>
                                            </div>
                                        </div>
                                    </div>
                                @else {{-- ── Esquerda (MPS) ── --}}
                                    <div class="chamado-msg chamado-msg--esquerda {!! $is_first ? '' : 'is-continuation' !!}"
                                         data-msg-id="{!! (int) $msg['id'] !!}" data-autor-key="{{ $autor_key }}" data-ts="{!! $ts_atual !!}">
                                        <div class="chamado-msg-bubble chamado-msg-bubble--esquerda {!! $is_first ? 'is-first' : 'is-continuation' !!}">
                                            @if ($is_first)
                                                <span class="chamado-msg-autor">{!! $nome_mps !!}</span>
                                            @endif
                                            {!! $texto_html !!}
                                            {!! $anexo_html !!}
                                            <div class="chamado-msg-meta">
                                                <span class="chamado-msg-data">{!! $hora !!}</span>
                                            </div>
                                        </div>
                                    </div>
                                @endif

                                @endforeach
                            </div>
                        @endif
                        </div>

                        <!-- Barra de input (estilo WhatsApp) -->
                        @if ($encerrado)
                            <div class="chamado-chat-closed">
                                {!! icon('info') !!}
                                <span>Este chamado está encerrado e não aceita novas mensagens.</span>
                            </div>
                        @else
                            <div class="chamado-chat-input-bar">
                                <button type="button" class="chamado-attach-btn" id="chamado-chat-attach" title="Anexar documento">
                                    {!! icon('plus') !!}
                                </button>
                                <textarea id="mensagem" name="mensagem"
                                          class="chamado-input-field"
                                          rows="1"
                                          placeholder="Digite uma mensagem"></textarea>
                                <button class="chamado-send-btn" id="btn-responder" data-protocolo="{{ $protocolo }}">
                                    {!! icon('paper-plane-tilt') !!}
                                </button>
                            </div>
                        @endif

                    </div>
                </div>

                <!-- ── Coluna direita: informações do chamado ────────────── -->
                <div class="detalhe-right">
                    <div class="panel">
                        <div class="panel-body">
                            <div class="form-section">
                                <h3 class="form-section-title">{!! icon('clipboard-text') !!} Detalhes</h3>
                                <div class="detalhe-fields">

                                    <div class="detalhe-field span-full">
                                        <div class="detalhe-field-label">Título</div>
                                        <div class="detalhe-field-value">{{ $chamado['titulo'] ?? '—' }}</div>
                                    </div>

                                    <div class="detalhe-field span-full">
                                        <div class="detalhe-field-label">Assunto</div>
                                        <div class="detalhe-field-value">{{ $chamado['assunto'] ?? '—' }}</div>
                                    </div>

                                    <div class="detalhe-field span-full">
                                        <div class="detalhe-field-label">Categoria</div>
                                        <div class="detalhe-field-value">{{ $categoria_label }}</div>
                                    </div>

                                    <div class="detalhe-field span-full">
                                        <div class="detalhe-field-label">Status</div>
                                        <div class="detalhe-field-value">
                                            <span class="status-pill {!! $status_pill !!}">{!! $status_label !!}</span>
                                        </div>
                                    </div>

                                    @if (!empty($chamado['telefone']))
                                    <div class="detalhe-field span-full">
                                        <div class="detalhe-field-label">Telefone</div>
                                        <div class="detalhe-field-value">{{ $chamado['telefone'] }}</div>
                                    </div>
                                    @endif

                                    @if (!empty($chamado['email']))
                                    <div class="detalhe-field span-full">
                                        <div class="detalhe-field-label">E-mail</div>
                                        <div class="detalhe-field-value">{{ $chamado['email'] }}</div>
                                    </div>
                                    @endif

                                    <div class="detalhe-field span-full">
                                        <div class="detalhe-field-label">Aberto em</div>
                                        <div class="detalhe-field-value" style="color:var(--color-text-muted)">
                                            {{ date('d/m/Y \à\s H:i', strtotime($chamado['criado_em'])) }}
                                        </div>
                                    </div>

                                    <div class="detalhe-field span-full">
                                        <div class="detalhe-field-label">Última atualização</div>
                                        <div class="detalhe-field-value" style="color:var(--color-text-muted)">
                                            {{ date('d/m/Y \à\s H:i', strtotime($chamado['atualizado_em'])) }}
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>
                </div>

            </div>

        
@endsection

@push('antes-scripts')
<script>
(function () {
    var textarea   = document.getElementById('mensagem');
    var btnResp    = document.getElementById('btn-responder');
    var btnAttach  = document.getElementById('chamado-chat-attach');
    var messagesEl = document.getElementById('chamado-messages');
    if (!messagesEl) return;

    var protocolo = messagesEl.dataset.protocolo;
    var lastId    = parseInt(messagesEl.dataset.lastId || '0', 10);

    var ANEXO_ICON_SVG        = {!! json_encode(icon('paperclip')) !!};
    var ANEXO_MAX_BYTES       = 2 * 1024 * 1024;
    var ANEXO_EXT_PERMITIDAS  = ['pdf', 'jpg', 'jpeg', 'png', 'webp'];

    function escapeHtml(str) {
        return String(str)
            .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;').replace(/'/g, '&#039;');
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

    // Estado de agrupamento — lido a partir da última mensagem já renderizada
    function getLastState() {
        var thread = messagesEl.querySelector('.chamado-thread');
        if (!thread) return { autorKey: null, ts: 0, dateLabel: null };
        var msgs     = thread.querySelectorAll('.chamado-msg');
        var dates    = thread.querySelectorAll('.chamado-thread-date');
        var lastMsg  = msgs.length ? msgs[msgs.length - 1] : null;
        var lastDate = dates.length ? dates[dates.length - 1] : null;
        return {
            autorKey:  lastMsg ? lastMsg.dataset.autorKey : null,
            ts:        lastMsg ? parseInt(lastMsg.dataset.ts || '0', 10) : 0,
            dateLabel: lastDate ? lastDate.dataset.date : null
        };
    }

    function appendMessage(m) {
        var thread     = getThread();
        var state      = getLastState();
        var autorKey   = m.autor_tipo + '_' + (m.autor_id || '');
        var ehImob     = m.autor_tipo === 'imobiliaria';
        var isFirst    = (autorKey !== state.autorKey) || Math.abs(m.ts - state.ts) > 300;

        // Separador de data
        if (m.data !== state.dateLabel) {
            var dateDiv = document.createElement('div');
            dateDiv.className = 'chamado-thread-date';
            dateDiv.dataset.date = m.data;
            dateDiv.innerHTML = '<span>' + escapeHtml(m.data_label) + '</span>';
            thread.appendChild(dateDiv);
        }

        var msgDiv = document.createElement('div');
        msgDiv.className = 'chamado-msg ' + (ehImob ? 'chamado-msg--direita' : 'chamado-msg--esquerda') + (isFirst ? '' : ' is-continuation');
        msgDiv.dataset.msgId    = m.id;
        msgDiv.dataset.autorKey = autorKey;
        msgDiv.dataset.ts       = m.ts;

        var textoStr = (m.mensagem || '').trim();
        var textoHtml = textoStr ? '<div class="chamado-msg-texto">' + escapeHtml(textoStr).replace(/\n/g, '<br>') + '</div>' : '';
        var bubbleClass = 'chamado-msg-bubble ' + (ehImob ? 'chamado-msg-bubble--direita' : 'chamado-msg-bubble--esquerda') + ' ' + (isFirst ? 'is-first' : 'is-continuation');
        var autorHtml = (!ehImob && isFirst) ? '<span class="chamado-msg-autor">' + escapeHtml(m.autor_nome || 'Equipe MPS') + '</span>' : '';

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
                '<div class="chamado-msg-meta"><span class="chamado-msg-data">' + escapeHtml(m.hora) + '</span></div>' +
            '</div>';

        thread.appendChild(msgDiv);
        lastId = m.id;
    }

    function setEncerrado() {
        var bar = messagesEl.parentElement.querySelector('.chamado-chat-input-bar');
        if (bar) {
            bar.outerHTML = '<div class="chamado-chat-closed">' +
                {!! json_encode(icon('info')) !!} +
                '<span>Este chamado está encerrado e não aceita novas mensagens.</span></div>';
        }
        var btnEnc = document.getElementById('btn-encerrar');
        if (btnEnc) btnEnc.remove();
    }

    function poll() {
        fetch('/api/chamado/' + protocolo + '/mensagens?depois_de=' + lastId)
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok) return;
                if (data.mensagens && data.mensagens.length) {
                    data.mensagens.forEach(function (m) {
                        m.ts = Math.floor(new Date(m.criado_em.replace(' ', 'T')).getTime() / 1000);
                        appendMessage(m);
                    });
                    scrollToBottom();
                }
                if (data.status === 'encerrado' && messagesEl.dataset.status !== 'encerrado') {
                    messagesEl.dataset.status = 'encerrado';
                    setEncerrado();
                }
            })
            .catch(function () {});
    }

    // Scroll para o fim ao carregar
    scrollToBottom();

    // Auto-resize do textarea
    if (textarea) {
        textarea.addEventListener('input', function () {
            this.style.height = 'auto';
            this.style.height = Math.min(this.scrollHeight, 140) + 'px';
        });

        textarea.addEventListener('keydown', function (e) {
            if (e.key === 'Enter' && !e.shiftKey) {
                e.preventDefault();
                if (btnResp && !btnResp.disabled) btnResp.click();
            }
        });
    }

    // ── Enviar resposta (sem reload) ─────────────────────────────────────────
    if (btnResp) {
        btnResp.addEventListener('click', function () {
            var msg = textarea ? textarea.value.trim() : '';
            if (!msg) return;

            btnResp.disabled = true;

            fetch('/api/chamado/' + btnResp.dataset.protocolo + '/responder', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': '{{ csrf_token() }}' },
                body: JSON.stringify({ mensagem: msg })
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                btnResp.disabled = false;
                if (data.ok) {
                    textarea.value = '';
                    textarea.style.height = 'auto';
                    mpsAlert.success('Mensagem enviada.');
                    poll(); // busca a mensagem recém-criada do servidor (fonte única)
                } else {
                    mpsAlert.error(data.message || 'Erro ao enviar resposta.');
                }
            })
            .catch(function () {
                mpsAlert.error('Erro na requisição. Tente novamente.');
                btnResp.disabled = false;
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
            return 'Tipo de arquivo não permitido. Envie PDF, JPG, PNG ou WEBP.';
        }
        if (file.size > ANEXO_MAX_BYTES) {
            return 'Arquivo excede o tamanho máximo de 2 MB.';
        }
        return null;
    }

    function renderPickerAnexo() {
        var html =
            '<h3 class="mps-alert-title">Enviar documento</h3>' +
            '<div class="mps-dropzone" id="chamado-anexo-dropzone" tabindex="0">' +
                ANEXO_ICON_SVG.replace('class="icon"', 'class="icon mps-dropzone-icon"') +
                '<p class="mps-dropzone-text">Arraste um arquivo aqui ou <strong>clique para selecionar</strong></p>' +
                '<span class="mps-dropzone-hint">PDF, JPG, PNG ou WEBP — até 2 MB</span>' +
            '</div>' +
            '<input type="file" id="chamado-anexo-input" accept=".pdf,.jpg,.jpeg,.png,.webp" hidden>' +
            '<div class="mps-alert-actions">' +
                '<button type="button" class="btn btn-secondary" data-anexo-cancelar>Cancelar</button>' +
            '</div>';

        mpsAlert.custom(html, {
            onMount: function (card, close) {
                var dropzone  = card.querySelector('#chamado-anexo-dropzone');
                var fileInput = card.querySelector('#chamado-anexo-input');

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

        fetch('/api/chamado/' + protocolo + '/mensagem-anexo', {
            method: 'POST',
            headers: { 'X-CSRF-Token': '{{ csrf_token() }}' },
            body: fd
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.ok) {
                    mpsAlert.close();
                    mpsAlert.toast('success', 'Documento enviado.');
                    poll(); // busca a mensagem recém-criada do servidor (fonte única)
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
            if (btnAttach.disabled) return;
            renderPickerAnexo();
        });
    }

    // ── Encerrar chamado (sem reload) ────────────────────────────────────────
    var btnEnc = document.getElementById('btn-encerrar');
    if (btnEnc) {
        btnEnc.addEventListener('click', async function () {
            var ok = await mpsAlert.confirm({ message: 'Deseja encerrar este chamado? Não será possível enviar novas mensagens.' });
            if (!ok) return;
            btnEnc.disabled = true;

            fetch('/api/chamado/' + btnEnc.dataset.protocolo + '/encerrar', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': '{{ csrf_token() }}' },
                body: JSON.stringify({})
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.ok) {
                    messagesEl.dataset.status = 'encerrado';
                    setEncerrado();
                    mpsAlert.success('Chamado encerrado.');
                } else {
                    mpsAlert.error(data.message || 'Erro.');
                    btnEnc.disabled = false;
                }
            })
            .catch(function () { mpsAlert.error('Erro na requisição.'); btnEnc.disabled = false; });
        });
    }
}());
</script>
@endpush
