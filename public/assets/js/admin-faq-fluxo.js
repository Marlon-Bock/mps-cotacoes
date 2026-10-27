/**
 * Fluxo do FAQ — construtor visual de blocos (admin-faq-fluxo.js)
 * Blocos empilháveis, arrastáveis (SortableJS), editados inline. Salva tudo
 * de uma vez como JSON via /api/admin/faq-fluxo/salvar.
 *
 * Estrutura do DOM: zonas "adicionar bloco" (.add-block-zone) e blocos
 * (.flow-wrapper) ficam intercalados como irmãos diretos dentro de
 * #flow-list ou de um .mini-flow-list — nunca uma zona aninhada dentro de
 * um bloco. Inserir um bloco novo = inserir logo depois da zona clicada.
 * Serializar = ler ':scope > .flow-wrapper' na ordem em que estão no DOM.
 */
(function () {
    'use strict';

    /**
     * Ícones Phosphor embutidos como SVG (mesmo conjunto de public/assets/icons/).
     * Embutidos aqui porque este módulo é JS puro, sem acesso ao helper PHP icon().
     */
    const ICONS = {
        plus: '<svg class="icon" aria-hidden="true" viewBox="0 0 256 256" fill="currentColor"><rect x="48" y="116" width="160" height="24" rx="12"/><rect x="116" y="48" width="24" height="160" rx="12"/></svg>',
        x: '<svg class="icon" aria-hidden="true" viewBox="0 0 256 256" fill="currentColor"><path d="M205.66,194.34a8,8,0,0,1-11.32,11.32L128,139.31,61.66,205.66a8,8,0,0,1-11.32-11.32L116.69,128,50.34,61.66A8,8,0,0,1,61.66,50.34L128,116.69l66.34-66.35a8,8,0,0,1,11.32,11.32L139.31,128Z"/></svg>',
        check: '<svg class="icon" aria-hidden="true" viewBox="0 0 256 256" fill="currentColor"><path d="M229.66,77.66l-128,128a8,8,0,0,1-11.32,0l-56-56a8,8,0,0,1,11.32-11.32L96,188.69,218.34,66.34a8,8,0,0,1,11.32,11.32Z"/></svg>',
        'chat-circle': '<svg class="icon" aria-hidden="true" viewBox="0 0 256 256" fill="currentColor"><path d="M128,24A104,104,0,0,0,36.19,178.61L24.83,210.11a16,16,0,0,0,20.68,20.32l32.71-11.9A104,104,0,1,0,128,24Zm0,192a87.6,87.6,0,0,1-44.24-12,8,8,0,0,0-6.16-.86L48,214.85l11.6-30a8,8,0,0,0-.75-7.31A88,88,0,1,1,128,216Z"/></svg>',
        'check-circle': '<svg class="icon" aria-hidden="true" viewBox="0 0 256 256" fill="currentColor"><path d="M173.66,98.34a8,8,0,0,1,0,11.32l-56,56a8,8,0,0,1-11.32,0l-24-24a8,8,0,0,1,11.32-11.32L112,148.69l50.34-50.35A8,8,0,0,1,173.66,98.34ZM232,128A104,104,0,1,1,128,24,104.11,104.11,0,0,1,232,128Zm-16,0a88,88,0,1,0-88,88A88.1,88.1,0,0,0,216,128Z"/></svg>',
        'note-pencil': '<svg class="icon" aria-hidden="true" viewBox="0 0 256 256" fill="currentColor"><path d="M229.66,58.34l-32-32a8,8,0,0,0-11.32,0l-96,96A8,8,0,0,0,88,128v32a8,8,0,0,0,8,8h32a8,8,0,0,0,5.66-2.34l96-96A8,8,0,0,0,229.66,58.34ZM124.69,152H104V131.31l64-64L188.69,88ZM200,76.69,179.31,56,192,43.31,212.69,64ZM224,128v80a16,16,0,0,1-16,16H48a16,16,0,0,1-16-16V48A16,16,0,0,1,48,32h80a8,8,0,0,1,0,16H48V208H208V128a8,8,0,0,1,16,0Z"/></svg>',
        list: '<svg class="icon" aria-hidden="true" viewBox="0 0 256 256" fill="currentColor"><path d="M224,128a8,8,0,0,1-8,8H40a8,8,0,0,1,0-16H216A8,8,0,0,1,224,128ZM40,72H216a8,8,0,0,0,0-16H40a8,8,0,0,0,0,16ZM216,184H40a8,8,0,0,0,0,16H216a8,8,0,0,0,0-16Z"/></svg>',
        ticket: '<svg class="icon" aria-hidden="true" viewBox="0 0 256 256" fill="currentColor"><path d="M232,104a8,8,0,0,0,8-8V64a16,16,0,0,0-16-16H32A16,16,0,0,0,16,64V96a8,8,0,0,0,8,8,24,24,0,0,1,0,48,8,8,0,0,0-8,8v32a16,16,0,0,0,16,16H224a16,16,0,0,0,16-16V160a8,8,0,0,0-8-8,24,24,0,0,1,0-48ZM32,167.2a40,40,0,0,0,0-78.4V64H88V192H32Zm192,0V192H104V64H224V88.8a40,40,0,0,0,0,78.4Z"/></svg>',
        info: '<svg class="icon" aria-hidden="true" viewBox="0 0 256 256" fill="currentColor"><path d="M128,24A104,104,0,1,0,232,128,104.11,104.11,0,0,0,128,24Zm0,192a88,88,0,1,1,88-88A88.1,88.1,0,0,1,128,216Zm16-40a8,8,0,0,1-8,8,16,16,0,0,1-16-16V128a8,8,0,0,1,0-16,16,16,0,0,1,16,16v40A8,8,0,0,1,144,176ZM112,84a12,12,0,1,1,12,12A12,12,0,0,1,112,84Z"/></svg>',
        question: '<svg class="icon" aria-hidden="true" viewBox="0 0 256 256" fill="currentColor"><path d="M140,180a12,12,0,1,1-12-12A12,12,0,0,1,140,180ZM128,72c-22.06,0-40,16.15-40,36v4a8,8,0,0,0,16,0v-4c0-11,10.77-20,24-20s24,9,24,20-10.77,20-24,20a8,8,0,0,0-8,8v8a8,8,0,0,0,16,0v-.72c18.24-3.35,32-17.9,32-35.28C168,88.15,150.06,72,128,72Zm104,56A104,104,0,1,1,128,24,104.11,104.11,0,0,1,232,128Zm-16,0a88,88,0,1,0-88,88A88.1,88.1,0,0,0,216,128Z"/></svg>',
        'identification-card': '<svg class="icon" aria-hidden="true" viewBox="0 0 256 256" fill="currentColor"><path d="M200,112a8,8,0,0,1-8,8H152a8,8,0,0,1,0-16h40A8,8,0,0,1,200,112Zm-8,24H152a8,8,0,0,0,0,16h40a8,8,0,0,0,0-16Zm40-80V200a16,16,0,0,1-16,16H40a16,16,0,0,1-16-16V56A16,16,0,0,1,40,40H216A16,16,0,0,1,232,56ZM216,200V56H40V200H216Zm-80.26-34a8,8,0,1,1-15.5,4c-2.63-10.26-13.06-18-24.25-18s-21.61,7.74-24.25,18a8,8,0,1,1-15.5-4,39.84,39.84,0,0,1,17.19-23.34,32,32,0,1,1,45.12,0A39.76,39.76,0,0,1,135.75,166ZM96,136a16,16,0,1,0-16-16A16,16,0,0,0,96,136Z"/></svg>',
        'caret-down': '<svg class="icon" aria-hidden="true" viewBox="0 0 256 256" fill="currentColor"><path d="M213.66,101.66l-80,80a8,8,0,0,1-11.32,0l-80-80A8,8,0,0,1,53.66,90.34L128,164.69l74.34-74.35a8,8,0,0,1,11.32,11.32Z"/></svg>',
        'arrow-right': '<svg class="icon" aria-hidden="true" viewBox="0 0 256 256" fill="currentColor"><path d="M221.66,133.66l-72,72a8,8,0,0,1-11.32-11.32L196.69,136H40a8,8,0,0,1,0-16H196.69L138.34,61.66a8,8,0,0,1,11.32-11.32l72,72A8,8,0,0,1,221.66,133.66Z"/></svg>',
    };

    // Espelha custom_select() do PHP (functions.php) — como isso roda em blocos
    // criados dinamicamente via JS, não dá pra chamar a função PHP; a marcação
    // e as classes precisam ficar idênticas pro custom-select.js (init global)
    // e o serializador conseguirem tratar igual, venha o bloco do servidor ou daqui.
    function customSelectHtml(opcoes, valorAtual) {
        const valorSel = valorAtual || '';
        const labelSel = opcoes[valorSel] || 'Selecione o tipo';
        const opcoesHtml = Object.keys(opcoes).map((val) => {
            const sel = val === valorSel ? ' is-selected' : '';
            return `<li class="custom-select-option${sel}" data-value="${val}" role="option">${opcoes[val]}</li>`;
        }).join('');

        return `<div data-custom-select class="custom-select" data-placeholder="Selecione o tipo">
            <button type="button" class="custom-select-trigger field-input has-icon flow-input" role="combobox" aria-haspopup="listbox" aria-expanded="false">
                <span class="custom-select-value">${labelSel}</span>
                <span class="custom-select-caret">${icon('caret-down')}</span>
            </button>
            <ul class="custom-select-dropdown" role="listbox">
                <li class="custom-select-option is-placeholder" data-value="" role="option">Selecione o tipo</li>
                ${opcoesHtml}
            </ul>
            <input type="hidden" name="tipo_captura" value="${valorSel}" data-campo="tipo_captura">
        </div>`;
    }
    function icon(name) { return ICONS[name] || ''; }

    const TIPO_LABEL = {
        mensagem: 'Mensagem do Bot',
        mensagem_botao: 'Mensagem com Botão',
        resposta_texto: 'Resposta Livre do Usuário',
        opcoes: 'Lista de Opções',
        chamado: 'Abrir Chamado',
    };
    const TIPO_ICONE = {
        mensagem: 'chat-circle',
        mensagem_botao: 'check-circle',
        resposta_texto: 'note-pencil',
        opcoes: 'list',
        chamado: 'ticket',
    };

    function escHtml(s) {
        return String(s ?? '')
            .replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }

    // ── Templates de campos por tipo (blocos novos, adicionados via JS) ────────

    function camposHtml(tipo) {
        switch (tipo) {
            case 'mensagem':
                return `<div class="flow-field">
                    <label class="flow-field-label">Mensagem do bot</label>
                    <textarea class="field-input flow-input" data-campo="mensagem" rows="2" placeholder="Digite a mensagem que o bot vai enviar..."></textarea>
                </div>`;
            case 'mensagem_botao':
                return `<div class="flow-field">
                    <label class="flow-field-label">Mensagem do bot</label>
                    <textarea class="field-input flow-input" data-campo="mensagem" rows="2" placeholder="Digite a mensagem..."></textarea>
                </div>
                <div class="flow-field">
                    <label class="flow-field-label">Texto do botão</label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon">${icon('check-circle')}</span>
                        <input class="field-input flow-input has-icon" type="text" data-campo="texto_botao" placeholder="Ex: Entendi, continuar">
                    </div>
                </div>`;
            case 'resposta_texto':
                return `<div class="flow-field">
                    <label class="flow-field-label">Pergunta para o usuário</label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon">${icon('question')}</span>
                        <input class="field-input flow-input has-icon" type="text" data-campo="pergunta" placeholder="Ex: Descreva sua dúvida...">
                    </div>
                </div>
                <div class="flow-field">
                    <label class="flow-field-label">Tipo de resposta esperada</label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon">${icon('identification-card')}</span>
                        ${customSelectHtml({ texto: 'Texto livre', nome: 'Nome', telefone: 'Telefone (com máscara)', imobiliaria: 'Nome da imobiliária' }, 'texto')}
                    </div>
                </div>`;
            case 'opcoes':
                return `<div class="flow-field">
                    <label class="flow-field-label">Mensagem antes das opções (opcional)</label>
                    <textarea class="field-input flow-input" data-campo="mensagem" rows="2" placeholder="Ex: Sobre qual assunto você quer falar?"></textarea>
                </div>
                <div class="flow-field">
                    <label class="flow-field-label">Opções</label>
                    <div class="opcoes-list"></div>
                    <button type="button" class="btn-add-opcao" data-action="add-opcao">${icon('plus')} Adicionar opção</button>
                </div>`;
            case 'chamado':
                return `<div class="alert alert-info flow-alert-flush">
                    <span class="alert-icon">${icon('info')}</span>
                    Aqui o usuário vê um botão que leva pra abrir um chamado com a equipe MPS. Bloco terminal — não precisa de mais nada depois dele nesse ramo.
                </div>`;
            default:
                return '';
        }
    }

    function painelTiposHtml() {
        return `
            <div class="add-block-group-title">Mensagens</div>
            <button type="button" class="add-block-option" data-type="mensagem">
                <span class="add-block-option-icon type-mensagem">${icon('chat-circle')}</span>
                <div><strong>Mensagem do Bot</strong><span>Envia texto simples</span></div>
            </button>
            <button type="button" class="add-block-option" data-type="mensagem_botao">
                <span class="add-block-option-icon type-mensagem_botao">${icon('check-circle')}</span>
                <div><strong>Mensagem com Botão</strong><span>Texto com um botão único</span></div>
            </button>
            <button type="button" class="add-block-option" data-type="resposta_texto">
                <span class="add-block-option-icon type-resposta_texto">${icon('note-pencil')}</span>
                <div><strong>Resposta Livre do Usuário</strong><span>Deixa o usuário digitar algo</span></div>
            </button>
            <button type="button" class="add-block-option" data-type="opcoes">
                <span class="add-block-option-icon type-opcoes">${icon('list')}</span>
                <div><strong>Lista de Opções</strong><span>Múltipla escolha, com sub-fluxo</span></div>
            </button>
            <div class="add-block-group-title">Finalização</div>
            <button type="button" class="add-block-option" data-type="chamado">
                <span class="add-block-option-icon type-chamado">${icon('ticket')}</span>
                <div><strong>Abrir Chamado</strong><span>Encerra levando pra /chamados/novo</span></div>
            </button>`;
    }

    // ── Construção de elementos novos ──────────────────────────────────────────

    function buildAddZoneEl() {
        const zone = document.createElement('div');
        zone.className = 'add-block-zone';
        zone.innerHTML = `
            <div class="add-block-line"></div>
            <button type="button" class="btn-add-block" data-action="toggle-add-menu">${icon('plus')}<span>Adicionar bloco</span></button>
            <div class="add-block-panel">${painelTiposHtml()}</div>`;
        return zone;
    }

    function buildBlockEl(tipo) {
        const wrap = document.createElement('div');
        wrap.className = 'flow-wrapper';
        wrap.innerHTML = `
            <div class="flow-block" data-type="${tipo}">
                <div class="flow-block-header">
                    <div class="flow-block-header-left">
                        <span class="block-drag" title="Arrastar">${icon('list')}</span>
                        <span class="flow-type-badge type-${tipo}">${icon(TIPO_ICONE[tipo])} ${TIPO_LABEL[tipo]}</span>
                    </div>
                    <button type="button" class="btn-icon-action" data-action="remover-bloco" title="Excluir bloco">${icon('x')}</button>
                </div>
                <div class="flow-block-body">${camposHtml(tipo)}</div>
            </div>`;
        return wrap;
    }

    function buildOpcaoEl(nome) {
        const item = document.createElement('div');
        item.className = 'opcao-item';
        item.innerHTML = `
            <div class="opcao-row">
                <span class="block-drag" title="Arrastar">${icon('list')}</span>
                <div class="field-input-wrap opcao-nome">
                    <span class="field-input-icon">${icon('note-pencil')}</span>
                    <input class="field-input has-icon" type="text" data-campo="nome" value="${escHtml(nome)}" placeholder="Nome da opção">
                </div>
                <button type="button" class="btn-icon-action" data-action="remover-opcao" title="Remover opção">${icon('x')}</button>
            </div>
            <div class="miniflow-body">
                <div class="mini-flow-list"></div>
            </div>`;
        const miniList = item.querySelector('.mini-flow-list');
        miniList.appendChild(buildAddZoneEl());
        initSortable(miniList, '.flow-wrapper');
        return item;
    }

    // ── Sortable ────────────────────────────────────────────────────────────────

    // O SortableJS só reordena os '.flow-wrapper' — as zonas "Adicionar bloco"
    // ficam paradas onde estavam, então depois de arrastar o encaixe
    // zona/bloco/zona pode quebrar (duas zonas grudadas, sem bloco entre elas).
    // Mais simples que rastrear posição: joga tudo fora e remonta na ordem certa.
    function reconciliarZonas(containerEl) {
        containerEl.querySelectorAll(':scope > .add-block-zone').forEach((z) => z.remove());
        const blocos = [...containerEl.querySelectorAll(':scope > .flow-wrapper')];
        blocos.forEach((bloco) => containerEl.insertBefore(buildAddZoneEl(), bloco));
        containerEl.appendChild(buildAddZoneEl());
    }

    function initSortable(el, draggableSelector) {
        if (!el || el._sortableInit) return;
        el._sortableInit = true;
        Sortable.create(el, {
            handle: '.block-drag',
            draggable: draggableSelector,
            animation: 150,
            ghostClass: 'sortable-ghost',
            chosenClass: 'sortable-chosen',
            onEnd: draggableSelector === '.flow-wrapper' ? () => reconciliarZonas(el) : undefined,
        });
    }

    // ── Painel "Adicionar bloco" — abrir/fechar/posicionar ─────────────────────

    function fecharTodosPaineis() {
        document.querySelectorAll('.add-block-panel.open').forEach((p) => p.classList.remove('open'));
    }

    function posicionarPainel(btn, panel) {
        const PANEL_W = 280;
        const GAP = 6;
        const rect = btn.getBoundingClientRect();
        const vw = window.innerWidth;
        const vh = window.innerHeight;
        const panelH = panel.offsetHeight || 300;

        let left = rect.left + rect.width / 2 - PANEL_W / 2;
        left = Math.max(8, Math.min(left, vw - PANEL_W - 8));

        let top;
        if (rect.bottom + GAP + panelH > vh) {
            top = rect.top - GAP - panelH;
        } else {
            top = rect.bottom + GAP;
        }
        top = Math.max(8, top);

        panel.style.left = left + 'px';
        panel.style.top = top + 'px';
    }

    // ── Adicionar / remover bloco ───────────────────────────────────────────────

    function adicionarBloco(tipo, zona) {
        const wrap = buildBlockEl(tipo);
        const novaZona = buildAddZoneEl();

        zona.insertAdjacentElement('afterend', wrap);
        wrap.insertAdjacentElement('afterend', novaZona);

        document.getElementById('flow-empty')?.remove();

        if (tipo === 'opcoes') {
            initSortable(wrap.querySelector('.opcoes-list'), '.opcao-item');
        }
        if (window.customSelectInit) window.customSelectInit(wrap);

        fecharTodosPaineis();
    }

    function removerBloco(btn) {
        const wrap = btn.closest('.flow-wrapper');
        const zonaSeguinte = wrap.nextElementSibling;
        if (zonaSeguinte && zonaSeguinte.classList.contains('add-block-zone')) {
            zonaSeguinte.remove();
        }
        wrap.remove();

        const flowList = document.getElementById('flow-list');
        if (!flowList.querySelector('.flow-wrapper')) {
            const vazio = document.createElement('div');
            vazio.className = 'flow-empty';
            vazio.id = 'flow-empty';
            vazio.innerHTML = `
                <div class="flow-empty-icon">${icon('chat-circle')}</div>
                <div class="flow-empty-title">Nenhum bloco criado ainda</div>
                <div class="flow-empty-sub">Use o botão abaixo para adicionar o primeiro bloco do fluxo</div>`;
            flowList.prepend(vazio);
        }
    }

    async function adicionarOpcao(btn) {
        const nome = await mpsAlert.prompt({
            title: 'Nova opção',
            message: 'Nome do botão que o usuário vai ver.',
            placeholder: 'Ex: Quero saber mais',
            confirmText: 'Adicionar',
        });
        if (!nome || !nome.trim()) return;

        const lista = btn.previousElementSibling; // .opcoes-list
        const item = buildOpcaoEl(nome.trim());
        lista.appendChild(item);
        initSortable(lista, '.opcao-item');
    }

    function removerOpcao(btn) {
        btn.closest('.opcao-item').remove();
    }

    // ── Delegação global de cliques ─────────────────────────────────────────────

    document.addEventListener('click', (e) => {
        const btnToggle = e.target.closest('[data-action="toggle-add-menu"]');
        if (btnToggle) {
            const panel = btnToggle.nextElementSibling;
            const jaAberto = panel.classList.contains('open');
            fecharTodosPaineis();
            if (!jaAberto) {
                panel.classList.add('open');
                posicionarPainel(btnToggle, panel);
            }
            return;
        }

        const opcaoTipo = e.target.closest('.add-block-option');
        if (opcaoTipo) {
            const zona = opcaoTipo.closest('.add-block-zone');
            if (zona) adicionarBloco(opcaoTipo.dataset.type, zona);
            return;
        }

        const btnRemoverBloco = e.target.closest('[data-action="remover-bloco"]');
        if (btnRemoverBloco) {
            removerBloco(btnRemoverBloco);
            return;
        }

        const btnAddOpcao = e.target.closest('[data-action="add-opcao"]');
        if (btnAddOpcao) {
            adicionarOpcao(btnAddOpcao);
            return;
        }

        const btnRemoverOpcao = e.target.closest('[data-action="remover-opcao"]');
        if (btnRemoverOpcao) {
            removerOpcao(btnRemoverOpcao);
            return;
        }

        if (!e.target.closest('.add-block-zone')) {
            fecharTodosPaineis();
        }
    });

    // ── Serialização (DOM → objeto) ──────────────────────────────────────────────

    function serializarLista(listaEl) {
        return [...listaEl.querySelectorAll(':scope > .flow-wrapper')].map((wrap) =>
            serializarBloco(wrap.querySelector('.flow-block'))
        );
    }

    function serializarBloco(blocoEl) {
        const tipo = blocoEl.dataset.type;
        const bloco = { type: tipo };

        if (tipo === 'opcoes') {
            const mensagemEl = blocoEl.querySelector(':scope > .flow-block-body [data-campo="mensagem"]');
            bloco.mensagem = mensagemEl ? mensagemEl.value.trim() : '';
            const opcoesList = blocoEl.querySelector(':scope > .flow-block-body .opcoes-list');
            bloco.opcoes = opcoesList
                ? [...opcoesList.querySelectorAll(':scope > .opcao-item')].map(serializarOpcao)
                : [];
        } else {
            blocoEl.querySelectorAll(':scope > .flow-block-body [data-campo]').forEach((el) => {
                // custom_select() guarda o valor real num <input type=hidden> dentro do wrapper.
                const valorEl = el.matches('[data-custom-select]') ? el.querySelector('input[type="hidden"]') : el;
                bloco[el.dataset.campo] = valorEl ? valorEl.value.trim() : '';
            });
        }

        return bloco;
    }

    function serializarOpcao(opcaoEl) {
        const nomeEl = opcaoEl.querySelector('[data-campo="nome"]');
        const miniList = opcaoEl.querySelector(':scope > .miniflow-body > .mini-flow-list');
        return {
            nome: nomeEl ? nomeEl.value.trim() : '',
            minifluxo: miniList ? serializarLista(miniList) : [],
        };
    }

    // ── Salvar ────────────────────────────────────────────────────────────────

    document.getElementById('btn-salvar-fluxo').addEventListener('click', () => {
        const csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';
        const flow = serializarLista(document.getElementById('flow-list'));

        mpsAlert.loading('Salvando fluxo...');

        fetch('/api/admin/faq-fluxo/salvar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify({ flow }),
        })
            .then((r) => r.json())
            .then((data) => {
                if (data.ok) {
                    mpsAlert.success('Fluxo salvo com sucesso!');
                } else {
                    mpsAlert.error(data.message || 'Erro ao salvar o fluxo.');
                }
            })
            .catch(() => mpsAlert.error('Erro na requisição. Tente novamente.'));
    });

    // ── Testar fluxo (roda o fluxo em edição, sem precisar salvar antes) ───────
    // Mesmo motor de public/assets/js/faq-fluxo.js, duplicado aqui porque roda
    // sobre os blocos AINDA NÃO salvos (serializados direto do DOM do
    // construtor), não sobre o que está no banco. Não grava em
    // faq_atendimentos — é só um teste do admin, não um atendimento real.

    const testarModal    = document.getElementById('modal-testar-fluxo');
    const testarThread   = document.getElementById('testar-fluxo-thread');
    const testarInputBar = document.getElementById('testar-fluxo-input-bar');
    const testarInput    = document.getElementById('testar-fluxo-input');
    const testarSend     = document.getElementById('testar-fluxo-send');

    let testarAguardandoTexto = null;
    let testarTipoCapturaAtual = 'texto';

    function testarMascararTelefone(valor) {
        const d = valor.replace(/\D/g, '').slice(0, 11);
        if (d.length <= 2) return d.replace(/^(\d*)/, '($1');
        if (d.length <= 6) return d.replace(/^(\d{2})(\d*)/, '($1) $2');
        if (d.length <= 10) return d.replace(/^(\d{2})(\d{4})(\d*)/, '($1) $2-$3');
        return d.replace(/^(\d{2})(\d{5})(\d*)/, '($1) $2-$3');
    }

    function testarEscapeHtml(texto) {
        const div = document.createElement('div');
        div.textContent = texto;
        return div.innerHTML;
    }

    function testarScrollFim() {
        const box = testarThread.closest('.chamado-chat-messages');
        if (box) box.scrollTop = box.scrollHeight;
    }

    function testarCriarBolha(texto, lado) {
        const linha = document.createElement('div');
        linha.className = `chamado-msg chamado-msg--${lado} is-first`;
        const bolha = document.createElement('div');
        bolha.className = `chamado-msg-bubble chamado-msg-bubble--${lado} is-first`;
        const p = document.createElement('p');
        p.className = 'chamado-msg-texto';
        p.innerHTML = testarEscapeHtml(texto).replace(/\n/g, '<br>');
        bolha.appendChild(p);
        linha.appendChild(bolha);
        return linha;
    }

    function testarBolhaBotComDelay(texto) {
        return new Promise((resolve) => {
            const linha = document.createElement('div');
            linha.className = 'chamado-msg chamado-msg--esquerda is-first';
            const bolha = document.createElement('div');
            bolha.className = 'chamado-msg-bubble chamado-msg-bubble--esquerda is-first chamado-msg-typing';
            bolha.innerHTML = '<span></span><span></span><span></span>';
            linha.appendChild(bolha);
            testarThread.appendChild(linha);
            testarScrollFim();

            const delay = Math.min(Math.max((texto || '').length * 18, 400), 1600);
            setTimeout(() => {
                bolha.classList.remove('chamado-msg-typing');
                bolha.innerHTML = '';
                const p = document.createElement('p');
                p.className = 'chamado-msg-texto';
                p.innerHTML = testarEscapeHtml(texto || '').replace(/\n/g, '<br>');
                bolha.appendChild(p);
                testarScrollFim();
                resolve();
            }, delay);
        });
    }

    function testarMostrarOpcoes(opcoes, aoEscolher) {
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
            btn.innerHTML = `<span>${testarEscapeHtml(op.label)}</span>${icon('arrow-right')}`;
            btn.addEventListener('click', () => {
                linha.remove();
                testarThread.appendChild(testarCriarBolha(op.label, 'direita'));
                testarScrollFim();
                aoEscolher(op);
            });
            wrap.appendChild(btn);
        });

        bolha.appendChild(wrap);
        linha.appendChild(bolha);
        testarThread.appendChild(linha);
        testarScrollFim();
    }

    function testarMostrarInputTexto(tipoCaptura) {
        testarTipoCapturaAtual = tipoCaptura || 'texto';
        return new Promise((resolve) => {
            testarAguardandoTexto = resolve;
            testarInputBar.hidden = false;
            testarInput.value = '';
            testarInput.type = testarTipoCapturaAtual === 'telefone' ? 'tel' : 'text';
            testarInput.oninput = testarTipoCapturaAtual === 'telefone'
                ? () => { testarInput.value = testarMascararTelefone(testarInput.value); }
                : null;
            testarInput.focus();
        });
    }

    function testarEnviarTexto() {
        const valor = testarInput.value.trim();
        if (!valor || !testarAguardandoTexto) return;
        testarInputBar.hidden = true;
        testarThread.appendChild(testarCriarBolha(valor, 'direita'));
        testarScrollFim();
        const resolve = testarAguardandoTexto;
        testarAguardandoTexto = null;
        testarInput.value = '';
        resolve(valor);
    }

    testarSend.addEventListener('click', testarEnviarTexto);
    testarInput.addEventListener('keydown', (e) => { if (e.key === 'Enter') testarEnviarTexto(); });

    function testarRunFlow(blocos) {
        return blocos.reduce((p, bloco) => p.then(() => testarProcessBlock(bloco)), Promise.resolve());
    }

    function testarProcessBlock(bloco) {
        switch (bloco.type) {
            case 'mensagem':
                return testarBolhaBotComDelay(bloco.mensagem || '');

            case 'mensagem_botao': {
                const antesBotao = bloco.mensagem ? testarBolhaBotComDelay(bloco.mensagem) : Promise.resolve();
                return antesBotao.then(() => new Promise((resolve) => {
                    testarMostrarOpcoes([{ label: bloco.texto_botao || 'Continuar', valor: null }], () => resolve());
                }));
            }

            case 'resposta_texto':
                return testarBolhaBotComDelay(bloco.pergunta || '').then(() => testarMostrarInputTexto(bloco.tipo_captura));

            case 'opcoes': {
                const opcoes = (bloco.opcoes || []).map((op) => ({ label: op.nome, minifluxo: op.minifluxo || [] }));
                const mostrar = () => new Promise((resolve) => {
                    if (!opcoes.length) { resolve([]); return; }
                    testarMostrarOpcoes(opcoes, (escolhida) => resolve(escolhida.minifluxo));
                });
                const p = bloco.mensagem ? testarBolhaBotComDelay(bloco.mensagem) : Promise.resolve();
                return p.then(mostrar).then((minifluxo) => testarRunFlow(minifluxo));
            }

            case 'chamado':
                return testarBolhaBotComDelay('Vou te ajudar a abrir um chamado com nossa equipe.').then(() => {
                    const acoes = document.createElement('div');
                    acoes.className = 'faq-fluxo-final-acoes';
                    const btnChamado = document.createElement('button');
                    btnChamado.type = 'button';
                    btnChamado.className = 'btn btn-primary btn-sm';
                    btnChamado.disabled = true;
                    btnChamado.title = 'No fluxo real, isso leva pra /chamados/novo';
                    btnChamado.textContent = 'Abrir chamado (preview)';
                    acoes.appendChild(btnChamado);
                    testarThread.appendChild(acoes);
                    testarScrollFim();
                });

            default:
                return Promise.resolve();
        }
    }

    function testarMostrarEncerramento() {
        const acoes = document.createElement('div');
        acoes.className = 'faq-fluxo-final-acoes';
        const btnRecomecar = document.createElement('button');
        btnRecomecar.type = 'button';
        btnRecomecar.className = 'btn btn-secondary btn-sm';
        btnRecomecar.textContent = 'Recomeçar teste';
        btnRecomecar.addEventListener('click', testarIniciar);
        acoes.appendChild(btnRecomecar);
        testarThread.appendChild(acoes);
        testarScrollFim();
    }

    function testarIniciar() {
        testarThread.innerHTML = '';
        testarInputBar.hidden = true;
        testarAguardandoTexto = null;

        const flow = serializarLista(document.getElementById('flow-list'));
        if (!flow.length) {
            testarThread.innerHTML = '<div class="chamado-thread-empty"><p>Adicione pelo menos um bloco antes de testar.</p></div>';
            return;
        }
        testarRunFlow(flow).then(testarMostrarEncerramento);
    }

    document.getElementById('btn-testar-fluxo').addEventListener('click', () => {
        testarModal.hidden = false;
        testarIniciar();
    });

    // ── Init ──────────────────────────────────────────────────────────────────

    initSortable(document.getElementById('flow-list'), '.flow-wrapper');
    document.querySelectorAll('.mini-flow-list').forEach((el) => initSortable(el, '.flow-wrapper'));
    document.querySelectorAll('.opcoes-list').forEach((el) => initSortable(el, '.opcao-item'));
})();
