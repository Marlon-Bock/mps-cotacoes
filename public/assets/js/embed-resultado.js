/**
 * Embed da Fiança — resultado da análise na própria página (embed-resultado.js).
 *
 * O cliente da imobiliária envia a ficha pelo site dela (sem login). Em vez de
 * só avisar "enviado" e recarregar, o formulário dá lugar a um painel que
 * acompanha a análise das seguradoras (/api/embed/{token}/cotacao/fianca/
 * {protocolo}/situacao, a cada 4s):
 *   analisando → aguardando;
 *   aprovado   → "a MPS vai entrar em contato com os orçamentos";
 *   recusado   → "Tentar com outro pretendente" (mantém imóvel e valores) ou "Sair";
 *   recebido   → fluxo manual / ajuste: a MPS verifica e entra em contato.
 * Passados ~4 min ainda analisando, mostra "recebido" (a análise segue e a MPS
 * acompanha) pra ninguém ficar preso na tela.
 */
(function () {
    'use strict';

    const INTERVALO_MS = 4000;
    const LIMITE_MS    = 4 * 60 * 1000;
    const CHAVE_IMOVEL = 'embedFiancaImovel';

    // Campos do imóvel e dos valores do aluguel — reaproveitados em "outro pretendente".
    const CAMPOS_IMOVEL = [
        'cep', 'endereco', 'numero', 'complemento', 'bairro', 'cidade', 'uf', 'codigo_imovel',
        'vigencia_contrato', 'indice_reajuste', 'valor_aluguel',
        'valor_agua', 'valor_condominio', 'valor_gas', 'valor_iptu', 'valor_energia',
    ];

    const TEXTOS = {
        analisando: {
            titulo: 'Analisando a ficha…',
            texto: 'Estamos consultando as seguradoras. Isso costuma levar menos de um minuto — não feche esta página.',
        },
        aprovado: {
            titulo: 'Ficha aprovada!',
            texto: 'A MPS vai entrar em contato para apresentar os orçamentos das seguradoras.',
        },
        recusado: {
            titulo: 'Ficha não aprovada',
            texto: 'As seguradoras não aprovaram este pretendente. Você pode tentar com outro pretendente para o mesmo imóvel.',
        },
        recebido: {
            titulo: 'Ficha recebida!',
            texto: 'A MPS vai verificar a análise e entrar em contato com você.',
        },
    };

    function icone(nome) {
        const tpl = document.getElementById('embed-resultado-icones');
        const el  = tpl && tpl.content.querySelector(`[data-icone="${nome}"]`);
        return el ? el.innerHTML : '';
    }

    function esc(s) {
        const d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }

    function form() {
        return document.getElementById('form-fianca');
    }

    function chaveImovel() {
        const tipo = form()?.elements.tipo_ficha?.value || 'residencial';
        return `${CHAVE_IMOVEL}:${tipo}`;
    }

    function guardarImovel() {
        const f = form();
        if (!f) return;
        const dados = {};
        CAMPOS_IMOVEL.forEach((nome) => {
            const el = f.elements[nome];
            if (el && 'value' in el && el.value !== '') dados[nome] = el.value;
            const skip = f.elements[`${nome}_skip`];
            if (skip && skip.value === '1') dados[`${nome}_skip`] = '1';
        });
        try { sessionStorage.setItem(chaveImovel(), JSON.stringify(dados)); } catch (_) { /* sem storage: só recarrega */ }
    }

    // Ao abrir de novo depois de "Tentar com outro pretendente": repõe imóvel e valores.
    function restaurarImovel() {
        const f = form();
        if (!f) return;
        let dados = null;
        try {
            dados = JSON.parse(sessionStorage.getItem(chaveImovel()) || 'null');
            sessionStorage.removeItem(chaveImovel());
        } catch (_) { return; }
        if (!dados) return;

        CAMPOS_IMOVEL.forEach((nome) => {
            const valor = dados[nome];
            const el    = f.elements[nome];
            if (valor !== undefined && el) {
                // Select do sistema: clica na opção (atualiza o texto exibido).
                const opcao = el.type === 'hidden'
                    && el.closest('.custom-select')?.querySelector(`.custom-select-option[data-value="${CSS.escape(valor)}"]`);
                if (opcao) {
                    opcao.click();
                } else {
                    el.value = valor;
                }
            }
            if (dados[`${nome}_skip`] === '1') {
                const botao = f.querySelector(`[data-skip-toggle="${nome}"]`);
                if (botao && botao.getAttribute('aria-pressed') !== 'true') botao.click();
            }
        });
        mpsAlert.toast('info', 'Mantivemos os dados do imóvel. Preencha agora os dados do novo pretendente.');
    }

    let avisoHorario = '';

    function mostrar(estado, protocolo) {
        const f = form();
        const t = TEXTOS[estado] || TEXTOS.recebido;
        let painel = document.getElementById('embed-resultado');
        if (!painel) {
            painel = document.createElement('div');
            painel.id = 'embed-resultado';
            painel.setAttribute('role', 'status');
            painel.setAttribute('aria-live', 'polite');
            f.insertAdjacentElement('afterend', painel);
        }
        f.hidden = true;
        document.querySelector('.stepper-header')?.setAttribute('hidden', '');

        let acoes = '';
        if (estado === 'recusado') {
            acoes = `
                <button type="button" class="btn btn-primary" data-embed-acao="pretendente">${icone('pretendente')} Tentar com outro pretendente</button>
                <button type="button" class="btn btn-secondary" data-embed-acao="sair">${icone('sair')} Sair</button>`;
        } else if (estado !== 'analisando') {
            acoes = `<button type="button" class="btn btn-secondary" data-embed-acao="sair">${icone('nova')} Nova cotação</button>`;
        }

        painel.className = `embed-resultado embed-resultado--${estado}`;
        painel.innerHTML = `
            <span class="embed-resultado-icone">${estado === 'analisando' ? '<span class="embed-resultado-spinner"></span>' : icone(estado)}</span>
            <h2 class="embed-resultado-titulo">${esc(t.titulo)}</h2>
            <p class="embed-resultado-texto">${esc(t.texto)}</p>
            <p class="embed-resultado-protocolo">Protocolo <strong>${esc(protocolo)}</strong></p>
            ${avisoHorario ? `<p class="embed-resultado-aviso">${icone('recebido')}<span>${esc(avisoHorario)}</span></p>` : ''}
            ${acoes ? `<div class="embed-resultado-acoes">${acoes}</div>` : ''}`;

        painel.querySelector('[data-embed-acao="pretendente"]')?.addEventListener('click', () => {
            guardarImovel();
            window.location.reload();
        });
        painel.querySelector('[data-embed-acao="sair"]')?.addEventListener('click', () => {
            window.location.reload();
        });
        painel.scrollIntoView({ behavior: 'smooth', block: 'start' });
    }

    /** Chamado pelo cotacao-fianca.js depois do envio pelo embed. */
    window.embedAcompanharFianca = function (protocolo, acompanhamento, aviso) {
        avisoHorario = aviso || '';   // fora do horário / bloqueio (ex.: equipe em reunião)
        const token  = document.body.dataset.embedToken;
        const inicio = Date.now();
        mostrar('analisando', protocolo);

        const url = `/api/embed/${token}/cotacao/fianca/${encodeURIComponent(protocolo)}/situacao?t=${encodeURIComponent(acompanhamento)}`;
        const consultar = async () => {
            let estado = 'analisando';
            try {
                const resp = await fetch(url, { headers: { Accept: 'application/json' } });
                const data = await resp.json();
                if (resp.ok && data.success) estado = data.estado;
            } catch (_) { /* rede instável: tenta de novo */ }

            if (estado === 'analisando' && Date.now() - inicio < LIMITE_MS) {
                setTimeout(consultar, INTERVALO_MS);
                return;
            }
            mostrar(estado === 'analisando' ? 'recebido' : estado, protocolo);
        };
        setTimeout(consultar, 1500);
    };

    // Depois dos selects do sistema montados (custom-select.js).
    window.addEventListener('load', () => setTimeout(restaurarImovel, 0));
})();
