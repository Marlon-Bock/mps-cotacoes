/**
 * Documentos do Seguro Fiança (pages/cotacao/fianca/_documentos.php):
 * biometria, orçamentos, contrato de locação e apólice. Serve a MPS e a
 * imobiliária — o servidor só renderiza os botões que cada lado pode usar.
 */
(function () {
    const painel = document.getElementById('fianca-docs');
    if (!painel) return;
    const proto  = painel.dataset.protocolo;
    const ICONES = JSON.parse(painel.dataset.icones || '{}');

    function enviar(endpoint, dados, carregando) {
        const corpo = new FormData();
        Object.entries(dados).forEach(([k, v]) => corpo.append(k, v));
        mpsAlert.loading(carregando);
        return fetch(`/api/cotacao/${proto}/${endpoint}`, { method: 'POST', body: corpo })
            .then(r => r.json())
            .then(d => {
                if (!d.success) { mpsAlert.error(d.message || 'Tente novamente.'); return; }
                mpsAlert.success(d.message).then(() => location.reload());
            })
            .catch(() => mpsAlert.error('Não foi possível concluir. Tente novamente.'));
    }

    function escapar(texto) {
        const div = document.createElement('div');
        div.textContent = texto;
        return div.innerHTML;
    }

    /**
     * Modal com formulário: ícone, título, texto e os campos (texto, link ou PDF).
     * campos: [{ nome, tipo: 'texto'|'url'|'pdf', rotulo, icone, placeholder }]
     * Chama aoConfirmar(valores) só com tudo preenchido.
     */
    function formulario({ icone, titulo, texto, campos, confirmar, aoConfirmar }) {
        const html = campos.map((c) => c.tipo === 'pdf' ? `
            <div class="field" data-campo="${c.nome}">
                <label class="field-label">${escapar(c.rotulo)} <span class="required">*</span></label>
                <div class="fianca-docs-modal-arquivo">
                    <input type="file" accept=".pdf,application/pdf" hidden>
                    <button type="button" class="btn btn-secondary btn-sm" data-escolher>${ICONES.paperclip || ''} Escolher PDF</button>
                    <span class="fianca-docs-modal-arquivo-nome" data-nome-arquivo>Nenhum arquivo</span>
                </div>
                <span class="mps-alert-field-error" hidden></span>
            </div>` : `
            <div class="field" data-campo="${c.nome}">
                <label class="field-label" for="fd-campo-${c.nome}">${escapar(c.rotulo)} <span class="required">*</span></label>
                <div class="field-input-wrap">
                    <span class="field-input-icon">${ICONES[c.icone] || ''}</span>
                    <input type="${c.tipo === 'url' ? 'url' : 'text'}" id="fd-campo-${c.nome}" class="field-input has-icon" placeholder="${escapar(c.placeholder || '')}" maxlength="${c.tipo === 'url' ? 2048 : 60}" autocomplete="off">
                </div>
                <span class="mps-alert-field-error" hidden></span>
            </div>`).join('');

        mpsAlert.custom(`
            <div class="mps-alert-icon mps-alert-icon-info">${ICONES[icone] || ''}</div>
            <h3 class="mps-alert-title">${escapar(titulo)}</h3>
            <p class="mps-alert-message">${escapar(texto)}</p>
            <div class="fianca-docs-modal-form">${html}</div>
            <div class="mps-alert-actions">
                <button type="button" class="btn btn-secondary" data-cancelar>Cancelar</button>
                <button type="button" class="btn btn-primary" data-confirmar>${escapar(confirmar)}</button>
            </div>
        `, {
            onMount: (card, close) => {
                const erro = (campo, msg) => {
                    const el = card.querySelector(`[data-campo="${campo}"] .mps-alert-field-error`);
                    el.textContent = msg;
                    el.hidden = !msg;
                    card.querySelector(`[data-campo="${campo}"] .field-input`)?.classList.toggle('has-error', !!msg);
                };

                card.querySelectorAll('.fianca-docs-modal-arquivo').forEach((wrap) => {
                    const input = wrap.querySelector('input[type="file"]');
                    wrap.querySelector('[data-escolher]').addEventListener('click', () => input.click());
                    input.addEventListener('change', () => {
                        wrap.querySelector('[data-nome-arquivo]').textContent = input.files[0]?.name || 'Nenhum arquivo';
                        erro(wrap.closest('[data-campo]').dataset.campo, '');
                    });
                });
                card.querySelectorAll('.field-input').forEach((input) => {
                    input.addEventListener('input', () => erro(input.closest('[data-campo]').dataset.campo, ''));
                });
                card.querySelector('.field-input')?.focus();
                card.querySelector('[data-cancelar]').addEventListener('click', close);

                card.querySelector('[data-confirmar]').addEventListener('click', () => {
                    const valores = {};
                    let ok = true;
                    campos.forEach((c) => {
                        const el = card.querySelector(`[data-campo="${c.nome}"] input`);
                        if (c.tipo === 'pdf') {
                            const f = el.files[0];
                            if (!f) { erro(c.nome, 'Escolha o arquivo PDF.'); ok = false; return; }
                            if (!/\.pdf$/i.test(f.name)) { erro(c.nome, 'O arquivo precisa ser um PDF.'); ok = false; return; }
                            valores[c.nome] = f;
                            return;
                        }
                        const v = el.value.trim();
                        if (!v) { erro(c.nome, 'Campo obrigatório.'); ok = false; return; }
                        if (c.tipo === 'url' && !/^https?:\/\/\S+$/i.test(v)) { erro(c.nome, 'Informe um link válido (https://...).'); ok = false; return; }
                        valores[c.nome] = v;
                    });
                    if (ok) aoConfirmar(valores);
                });
            },
        });
    }

    const PDF = { nome: 'arquivo', tipo: 'pdf', rotulo: 'Arquivo PDF' };
    const ANEXOS = {
        orcamento: (btn) => ({
            icone: 'file-pdf', titulo: `Orçamento da ${btn.dataset.nome}`, confirmar: 'Enviar',
            texto: 'A imobiliária vê o orçamento e escolhe o do cliente.',
            campos: [PDF],
            enviar: (v) => enviar('orcamento', { acao: 'enviar', seguradora: btn.dataset.seguradora, ...v }, 'Enviando orçamento...'),
        }),
        contrato: () => ({
            icone: 'file-pdf', titulo: 'Contrato de locação', confirmar: 'Enviar',
            texto: 'A MPS recebe o contrato. Com a apólice anexada, a cotação fica concluída.',
            campos: [PDF],
            enviar: (v) => enviar('contrato', v, 'Enviando contrato...'),
        }),
        apolice: () => ({
            icone: 'file-pdf', titulo: 'Apólice', confirmar: 'Enviar',
            texto: 'A imobiliária recebe a apólice. Com o contrato de locação anexado, a cotação fica concluída.',
            campos: [PDF],
            enviar: (v) => enviar('apolice', v, 'Enviando apólice...'),
        }),
    };

    const confirmar = (message) => mpsAlert.confirm({ message });

    const acoes = {
        'copiar': (btn) => navigator.clipboard.writeText(btn.dataset.link)
            .then(() => mpsAlert.toast('success', 'Link copiado! Envie ao cliente.'))
            .catch(() => mpsAlert.error('Não foi possível copiar o link.')),

        'bio-adicionar': (btn) => formulario({
            icone: 'fingerprint', titulo: `Biometria da ${btn.dataset.nome}`, confirmar: 'Adicionar',
            texto: 'A imobiliária copia o link e envia ao cliente. Depois de adicionado, o link não muda.',
            campos: [{ nome: 'link', tipo: 'url', rotulo: 'Link da biometria', icone: 'fingerprint', placeholder: 'https://...' }],
            aoConfirmar: (v) => enviar('biometria', { acao: 'adicionar', seguradora: btn.dataset.seguradora, ...v }, 'Salvando link...'),
        }),
        'bio-concluir': async (btn) => {
            if (await confirmar('Confirmar que o cliente concluiu esta biometria?')) enviar('biometria', { acao: 'concluir', id: btn.dataset.id }, 'Salvando...');
        },

        // Orçamento (da seguradora do cartão), contrato e apólice: modal com o PDF.
        'anexar': (btn) => {
            const montar = ANEXOS[btn.dataset.tipo];
            if (!montar) return;
            const cfg = montar(btn);
            formulario({ ...cfg, aoConfirmar: cfg.enviar });
        },
        'orc-remover': async (btn) => {
            if (await confirmar('Remover este orçamento?')) enviar('orcamento', { acao: 'remover', id: btn.dataset.id }, 'Removendo...');
        },
        'orc-escolher': async (btn) => {
            if (await confirmar(`Confirmar "${btn.dataset.nome}" como o orçamento escolhido pelo cliente? Os demais serão recusados.`)) {
                enviar('orcamento', { acao: 'escolher', id: btn.dataset.id }, 'Salvando...');
            }
        },
        'orc-recusar': async () => {
            if (await confirmar('Confirmar que o cliente recusou todos os orçamentos?')) enviar('orcamento', { acao: 'recusar' }, 'Salvando...');
        },
    };

    painel.addEventListener('click', (e) => {
        const btn = e.target.closest('[data-acao]');
        if (btn && acoes[btn.dataset.acao]) acoes[btn.dataset.acao](btn);
    });
})();
