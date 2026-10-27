{{-- Off-canvas do chat/histórico da cotação (JS em cotacao-chat.js; conteúdo via AJAX). --}}
<div class="cotacao-chat-backdrop" id="cotacao-chat-backdrop"></div>
<div class="cotacao-chat-offcanvas" id="cotacao-chat-offcanvas" role="dialog" aria-modal="true" aria-hidden="true">
    <div class="cotacao-chat-offcanvas-header">
        <div class="cotacao-chat-offcanvas-title">
            <strong id="cotacao-chat-protocolo">—</strong>
            <span id="cotacao-chat-status">Chat da cotação</span>
        </div>
        <button type="button" class="cotacao-chat-offcanvas-close" id="cotacao-chat-close" aria-label="Fechar">
            {!! icon('x') !!}
        </button>
    </div>
    <div class="cotacao-chat-offcanvas-body">
        <div class="chamado-chat-box">
            <div class="chamado-chat-messages" id="cotacao-chat-messages" data-protocolo="" data-last-id="0">
                <div class="chamado-thread-empty">
                    {!! icon('chat-circle') !!}
                    <p>Carregando...</p>
                </div>
            </div>
            <div class="chamado-chat-input-bar" id="cotacao-chat-input-bar">
                <button type="button" class="chamado-attach-btn" id="cotacao-chat-attach" title="Anexar documento">
                    {!! icon('plus') !!}
                </button>
                <textarea id="cotacao-chat-textarea" class="chamado-input-field" rows="1" placeholder="Digite uma mensagem"></textarea>
                <button class="chamado-send-btn" id="cotacao-chat-send">
                    {!! icon('paper-plane-tilt') !!}
                </button>
            </div>
            <div class="chamado-chat-closed" id="cotacao-chat-closed-notice" hidden>
                {!! icon('info') !!}
                <span>Cotação encerrada — não é mais possível enviar mensagens.</span>
            </div>
        </div>
    </div>
</div>
