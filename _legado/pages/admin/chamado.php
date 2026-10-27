<?php
/**
 * GET /admin/chamado/{protocolo}  ex: CHA2026-00001
 * Detalhe de um chamado de suporte — chat com a imobiliária, atribuição de
 * atendente e encerramento. Mesma estrutura de pages/chamado.php (lado
 * imobiliária), com esquerda/direita invertidos (admin = direita).
 */
$current_user = require_admin();

$protocolo_param = $params['protocolo'] ?? '';
$id = chamado_id_do_protocolo($protocolo_param);
if ($id <= 0) redirect('/admin/chamados');

$chamado = null;
try {
    $chamado = db_fetch_one(
        'SELECT cs.*, i.nome AS imobiliaria_nome, u.username AS autor_nome_user
           FROM chamados_suporte cs
           LEFT JOIN imobiliarias i ON i.id = cs.imobiliaria_id
           LEFT JOIN users u ON u.id = cs.usuario_id
          WHERE cs.id = ?',
        ['i', $id]
    );
} catch (\Throwable $e) {}

if (!$chamado) redirect('/admin/chamados');

$respostas = [];
try {
    $respostas = db_fetch_all(
        'SELECT * FROM chamados_respostas WHERE chamado_id = ? ORDER BY criado_em ASC',
        ['i', $id]
    );
} catch (\Throwable $e) {}

$atendentes = [];
try {
    $atendentes = db_fetch_all('SELECT id, username FROM usuario ORDER BY username ASC');
} catch (\Throwable $e) {}
$opcoes_atendente = [];
foreach ($atendentes as $a) { $opcoes_atendente[(string) $a['id']] = $a['username']; }

$encerrado   = $chamado['status'] === 'encerrado';
$last_msg_id = !empty($respostas) ? (int) $respostas[count($respostas) - 1]['id'] : 0;

$protocolo    = chamado_protocolo($id, $chamado['criado_em']);
$status_label = chamado_status_label($chamado['status']);
$status_pill  = chamado_status_pill($chamado['status']);

$categoria_label = match ($chamado['categoria'] ?? '') {
    'seguro_fianca_residencial' => 'Seguro Fiança Residencial',
    'seguro_fianca_comercial'   => 'Seguro Fiança Comercial',
    'seguro_auto'               => 'Seguro Auto',
    'sinistro'                  => 'Sinistro',
    'duvidas_gerais'            => 'Dúvidas gerais',
    'outros'                    => 'Outros',
    default                     => $chamado['categoria'] ?? '—',
};

$page_title      = 'Chamado ' . $protocolo;
$page_heading    = 'Chamado ' . $protocolo;
$page_subheading = e($chamado['titulo']);
$page_back       = ['url' => '/admin/chamados', 'label' => 'Chamados'];
$extra_css       = ['dashboard.css', 'cotacao.css', 'cotacao-detalhe.css', 'chamados.css'];
$extra_js        = [];

require BASE_PATH . '/pages/partials/app-head.php';
require BASE_PATH . '/pages/partials/nav-admin.php';
?>
<div class="app-shell">
    <?php require BASE_PATH . '/pages/partials/sidebar.php'; ?>
    <div class="app-main">
        <?php require BASE_PATH . '/pages/partials/topbar.php'; ?>
        <main class="app-content">

            <!-- ── Header do chamado ─────────────────────────────────────── -->
            <div class="panel">
                <div class="panel-body detalhe-panel-header">
                    <div class="dph-top">
                        <span class="dph-tipo"><?= icon('headset') ?> Chamado de Suporte &middot; <?= e($categoria_label) ?></span>
                        <span class="status-pill <?= $status_pill ?>"><?= $status_label ?></span>
                    </div>
                    <span class="dph-proto"><?= e($protocolo) ?></span>
                    <div class="dph-bar">
                        <span class="dph-bar-item">
                            <?= icon('buildings') ?>
                            <span class="dph-bar-label">Imobiliária</span>
                            <span class="dph-bar-value"><?= e($chamado['imobiliaria_nome'] ?? '—') ?></span>
                        </span>
                        <span class="dph-bar-sep">&middot;</span>
                        <span class="dph-bar-item">
                            <?= icon('user') ?>
                            <span class="dph-bar-label">Aberto por</span>
                            <span class="dph-bar-value"><?= e($chamado['autor_nome_user'] ?? '—') ?></span>
                        </span>
                        <span class="dph-bar-sep">&middot;</span>
                        <span class="dph-bar-item">
                            <?= icon('clock') ?>
                            <span class="dph-bar-label">Aberto em</span>
                            <span class="dph-bar-value"><?= e(date('d/m/Y \à\s H:i', strtotime($chamado['criado_em']))) ?></span>
                        </span>
                        <span class="dph-bar-spacer"></span>
                        <?php if (!$encerrado): ?>
                            <button class="btn btn-secondary btn-sm" id="btn-encerrar"
                                    data-protocolo="<?= e($protocolo) ?>">
                                <?= icon('x') ?> Encerrar chamado
                            </button>
                        <?php endif; ?>
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
                             data-protocolo="<?= e($protocolo) ?>"
                             data-last-id="<?= $last_msg_id ?>"
                             data-status="<?= e($chamado['status']) ?>">
                        <?php if (empty($respostas)): ?>
                            <div class="chamado-thread-empty">
                                <?= icon('headset') ?>
                                <p>Nenhuma mensagem ainda.<br>Envie uma resposta abaixo para iniciar o atendimento.</p>
                            </div>
                        <?php else: ?>
                            <div class="chamado-thread">
                                <?php
                                $last_date  = null;
                                $last_autor = null;
                                $last_ts    = null;
                                foreach ($respostas as $idx => $msg):
                                    $eh_admin  = $msg['autor_tipo'] === 'admin';
                                    $autor_key = $msg['autor_tipo'] . '_' . ($msg['autor_id'] ?? '');
                                    $nome_imob = e($msg['autor_nome'] ?: 'Imobiliária');
                                    $msg_date  = date('Y-m-d', strtotime($msg['criado_em']));
                                    $hora      = date('H:i', strtotime($msg['criado_em']));

                                    $ts_atual  = strtotime($msg['criado_em']);
                                    $is_first  = ($autor_key !== $last_autor)
                                              || (abs($ts_atual - ($last_ts ?? 0)) > 300);

                                    $last_autor = $autor_key;
                                    $last_ts    = $ts_atual;

                                    if ($msg_date !== $last_date):
                                        $last_date = $msg_date;
                                ?>
                                    <div class="chamado-thread-date" data-date="<?= e($msg_date) ?>">
                                        <span><?= date('d/m/Y', strtotime($msg['criado_em'])) ?></span>
                                    </div>
                                <?php endif; ?>

                                <?php
                                    $texto_html = trim((string) $msg['mensagem']) !== ''
                                        ? '<div class="chamado-msg-texto">' . nl2br(e($msg['mensagem'])) . '</div>'
                                        : '';
                                    $anexo_html = !empty($msg['anexo_url'])
                                        ? '<a class="chamado-msg-anexo" href="' . e($msg['anexo_url']) . '" target="_blank" rel="noopener">'
                                            . icon('paperclip') . '<span>' . e($msg['anexo_nome'] ?: 'Documento') . '</span></a>'
                                        : '';
                                ?>
                                <?php if ($eh_admin): /* ── Direita (MPS) ── */ ?>
                                    <div class="chamado-msg chamado-msg--direita <?= $is_first ? '' : 'is-continuation' ?>"
                                         data-msg-id="<?= (int) $msg['id'] ?>" data-autor-key="<?= e($autor_key) ?>" data-ts="<?= $ts_atual ?>">
                                        <div class="chamado-msg-bubble chamado-msg-bubble--direita <?= $is_first ? 'is-first' : 'is-continuation' ?>">
                                            <?= $texto_html ?>
                                            <?= $anexo_html ?>
                                            <div class="chamado-msg-meta">
                                                <span class="chamado-msg-data"><?= $hora ?></span>
                                            </div>
                                        </div>
                                    </div>
                                <?php else: /* ── Esquerda (imobiliária) ── */ ?>
                                    <div class="chamado-msg chamado-msg--esquerda <?= $is_first ? '' : 'is-continuation' ?>"
                                         data-msg-id="<?= (int) $msg['id'] ?>" data-autor-key="<?= e($autor_key) ?>" data-ts="<?= $ts_atual ?>">
                                        <div class="chamado-msg-bubble chamado-msg-bubble--esquerda <?= $is_first ? 'is-first' : 'is-continuation' ?>">
                                            <?php if ($is_first): ?>
                                                <span class="chamado-msg-autor"><?= $nome_imob ?></span>
                                            <?php endif; ?>
                                            <?= $texto_html ?>
                                            <?= $anexo_html ?>
                                            <div class="chamado-msg-meta">
                                                <span class="chamado-msg-data"><?= $hora ?></span>
                                            </div>
                                        </div>
                                    </div>
                                <?php endif; ?>

                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        </div>

                        <!-- Barra de input (estilo WhatsApp) -->
                        <?php if ($encerrado): ?>
                            <div class="chamado-chat-closed">
                                <?= icon('info') ?>
                                <span>Este chamado está encerrado e não aceita novas mensagens.</span>
                            </div>
                        <?php else: ?>
                            <div class="chamado-chat-input-bar">
                                <button type="button" class="chamado-attach-btn" id="chamado-chat-attach" title="Anexar documento">
                                    <?= icon('plus') ?>
                                </button>
                                <textarea id="mensagem" name="mensagem"
                                          class="chamado-input-field"
                                          rows="1"
                                          placeholder="Digite uma mensagem"></textarea>
                                <button class="chamado-send-btn" id="btn-responder" data-protocolo="<?= e($protocolo) ?>">
                                    <?= icon('paper-plane-tilt') ?>
                                </button>
                            </div>
                        <?php endif; ?>

                    </div>
                </div>

                <!-- ── Coluna direita: informações + ações rápidas ───────── -->
                <div class="detalhe-right">

                    <div class="panel">
                        <div class="panel-body">
                            <div class="form-section">
                                <h3 class="form-section-title"><?= icon('clipboard-text') ?> Detalhes</h3>
                                <div class="detalhe-fields">

                                    <div class="detalhe-field span-full">
                                        <div class="detalhe-field-label">Título</div>
                                        <div class="detalhe-field-value"><?= e($chamado['titulo'] ?? '—') ?></div>
                                    </div>

                                    <div class="detalhe-field span-full">
                                        <div class="detalhe-field-label">Assunto</div>
                                        <div class="detalhe-field-value"><?= e($chamado['assunto'] ?? '—') ?></div>
                                    </div>

                                    <?php if (!empty($chamado['telefone'])): ?>
                                    <div class="detalhe-field span-full">
                                        <div class="detalhe-field-label">Telefone</div>
                                        <div class="detalhe-field-value"><?= e($chamado['telefone']) ?></div>
                                    </div>
                                    <?php endif; ?>

                                    <?php if (!empty($chamado['email'])): ?>
                                    <div class="detalhe-field span-full">
                                        <div class="detalhe-field-label">E-mail</div>
                                        <div class="detalhe-field-value"><?= e($chamado['email']) ?></div>
                                    </div>
                                    <?php endif; ?>

                                    <div class="detalhe-field span-full">
                                        <div class="detalhe-field-label">Última atualização</div>
                                        <div class="detalhe-field-value" style="color:var(--color-text-muted)">
                                            <?= e(date('d/m/Y \à\s H:i', strtotime($chamado['atualizado_em']))) ?>
                                        </div>
                                    </div>

                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="panel">
                        <div class="panel-header">
                            <h2><?= icon('headset') ?> Ações rápidas</h2>
                        </div>
                        <div class="panel-body">
                            <div class="form-section">
                                <div class="field">
                                    <label class="field-label" for="atendente_id">Atendente responsável</label>
                                    <?= custom_select(
                                        'atendente_id',
                                        $opcoes_atendente,
                                        'Sem atendente',
                                        $chamado['atendente_id'] ? (string) $chamado['atendente_id'] : null,
                                        '',
                                        false,
                                        ['id' => 'atendente-wrap']
                                    ) ?>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

            </div>

        </main>
    </div>
</div>

<script>
(function () {
    var textarea   = document.getElementById('mensagem');
    var btnResp    = document.getElementById('btn-responder');
    var btnAttach  = document.getElementById('chamado-chat-attach');
    var messagesEl = document.getElementById('chamado-messages');
    if (!messagesEl) return;

    var protocolo = messagesEl.dataset.protocolo;
    var lastId    = parseInt(messagesEl.dataset.lastId || '0', 10);
    var csrfToken = <?= json_encode(csrf_token()) ?>;

    var ANEXO_ICON_SVG        = <?= json_encode(icon('paperclip')) ?>;
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
        var ehAdmin    = m.autor_tipo === 'admin';
        var isFirst    = (autorKey !== state.autorKey) || Math.abs(m.ts - state.ts) > 300;

        if (m.data !== state.dateLabel) {
            var dateDiv = document.createElement('div');
            dateDiv.className = 'chamado-thread-date';
            dateDiv.dataset.date = m.data;
            dateDiv.innerHTML = '<span>' + escapeHtml(m.data_label) + '</span>';
            thread.appendChild(dateDiv);
        }

        var msgDiv = document.createElement('div');
        msgDiv.className = 'chamado-msg ' + (ehAdmin ? 'chamado-msg--direita' : 'chamado-msg--esquerda') + (isFirst ? '' : ' is-continuation');
        msgDiv.dataset.msgId    = m.id;
        msgDiv.dataset.autorKey = autorKey;
        msgDiv.dataset.ts       = m.ts;

        var textoStr = (m.mensagem || '').trim();
        var textoHtml = textoStr ? '<div class="chamado-msg-texto">' + escapeHtml(textoStr).replace(/\n/g, '<br>') + '</div>' : '';
        var bubbleClass = 'chamado-msg-bubble ' + (ehAdmin ? 'chamado-msg-bubble--direita' : 'chamado-msg-bubble--esquerda') + ' ' + (isFirst ? 'is-first' : 'is-continuation');
        var autorHtml = (!ehAdmin && isFirst) ? '<span class="chamado-msg-autor">' + escapeHtml(m.autor_nome || 'Imobiliária') + '</span>' : '';

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
                <?= json_encode(icon('info')) ?> +
                '<span>Este chamado está encerrado e não aceita novas mensagens.</span></div>';
        }
        var btnEnc = document.getElementById('btn-encerrar');
        if (btnEnc) btnEnc.remove();
    }

    function poll() {
        fetch('/api/admin/chamado/' + protocolo + '/mensagens?depois_de=' + lastId)
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

    scrollToBottom();

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

            fetch('/api/admin/chamado/' + btnResp.dataset.protocolo + '/responder', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({ mensagem: msg })
            })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                btnResp.disabled = false;
                if (data.ok) {
                    textarea.value = '';
                    textarea.style.height = 'auto';
                    mpsAlert.success('Mensagem enviada.');
                    poll();
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

        fetch('/api/admin/chamado/' + protocolo + '/mensagem-anexo', {
            method: 'POST',
            headers: { 'X-CSRF-Token': csrfToken },
            body: fd
        })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.ok) {
                    mpsAlert.close();
                    mpsAlert.toast('success', 'Documento enviado.');
                    poll();
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

            fetch('/api/admin/chamado/' + btnEnc.dataset.protocolo + '/encerrar', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
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

    // ── Atribuir atendente (sem reload) ──────────────────────────────────────
    var atendenteHidden = document.querySelector('#atendente-wrap input[name="atendente_id"]');
    if (atendenteHidden) {
        atendenteHidden.addEventListener('change', function () {
            fetch('/api/admin/chamado/' + protocolo + '/atribuir', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({ atendente_id: atendenteHidden.value })
            })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.ok) {
                        mpsAlert.toast('success', 'Atendente atualizado.');
                    } else {
                        mpsAlert.error(data.message || 'Erro ao atribuir atendente.');
                    }
                })
                .catch(function () { mpsAlert.error('Erro na requisição.'); });
        });
    }
}());
</script>
<?php require BASE_PATH . '/pages/partials/app-foot.php'; ?>
