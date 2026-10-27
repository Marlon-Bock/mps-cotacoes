/**
 * API das seguradoras (Fiança) — as duas pontas do fluxo:
 *   MPS          tela de seguradoras: atualizar, reanálise, "Fazer cotação"
 *                (escolhe as seguradoras e envia as propostas ao cliente);
 *   imobiliária  detalhe da cotação: corrigir dados, aceitar a melhor proposta
 *                (dados do proprietário → emissão), recusar todas, link de
 *                biometria e os PDFs depois que a MPS enviar a apólice.
 * As operações na seguradora rodam numa fila no servidor; aqui só se
 * enfileira e acompanha.
 */
document.addEventListener('DOMContentLoaded', () => {
    const cfg = window.__SEGURADORAS_CONFIG;
    if (!cfg) return;

    const base = `/api/cotacao/fianca/${cfg.protocolo}/seguradoras`;

    /** POST JSON com CSRF; devolve o JSON da resposta. */
    async function enviar(url, corpo = {}) {
        const resp = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': cfg.csrf },
            body: JSON.stringify(corpo),
        });
        return resp.json();
    }

    /** Executa a ação com loading do mpsAlert; recarrega no sucesso. */
    async function acao(mensagemLoading, url, corpo, mensagemSucesso, mensagemErro) {
        mpsAlert.loading(mensagemLoading);
        try {
            const d = await enviar(url, corpo);
            if (!d.success) {
                mpsAlert.error(d.message || mensagemErro);
                return false;
            }
            if (mensagemSucesso) await mpsAlert.success(mensagemSucesso);
            window.location.reload();
            return true;
        } catch (e) {
            mpsAlert.error('Erro na requisição.');
            return false;
        }
    }

    // ─── Atualizar (reconsulta a seguradora — até ~1 min) ───
    document.getElementById('btn-atualizar-seguradoras')?.addEventListener('click', () => {
        acao('Consultando a seguradora...', `${base}/atualizar`, {}, null, 'Erro ao atualizar.');
    });

    // ─── "Cliente não quer nenhuma proposta" → encerra por desistência ───
    document.getElementById('btn-desistir-seguradoras')?.addEventListener('click', async () => {
        const ok = await mpsAlert.confirm({
            title: 'Encerrar a cotação?',
            message: 'O cliente não quer nenhuma das propostas: a cotação será encerrada por desistência. Essa ação não pode ser desfeita.',
            confirmText: 'Encerrar',
        });
        if (!ok) return;
        acao('Encerrando a cotação...', `/api/cotacao/${cfg.protocolo}/desistir`, {}, 'Cotação encerrada por desistência.', 'Erro ao encerrar a cotação.');
    });

    // ─── "Escolher proposta": dados do proprietário → emite a apólice ───
    // A seguradora exige o proprietário (locador) como segurado pra emitir,
    // então o botão abre o modal e só o envio do modal enfileira a emissão.
    const modalProposta = document.getElementById('modal-escolher-proposta');
    const formProposta  = document.getElementById('form-escolher-proposta');

    // Campos que só uma seguradora pede (ex: Too Seguros) e, dentro deles,
    // os de pessoa física x jurídica (pelo tamanho do CPF/CNPJ).
    // Seguro Residencial: o modal só tem o pagamento (modal-pagamento-residencial.php).
    const residencial = formProposta?.dataset.produto === 'residencial';

    function ajustarCamposExtras() {
        const seguradora = formProposta.elements.seguradora.value;
        const pj = (formProposta.elements.cpf_cnpj?.value || '').replace(/\D/g, '').length === 14;
        formProposta.querySelectorAll('[data-somente-seguradora]').forEach(el => {
            el.hidden = el.dataset.somenteSeguradora !== seguradora;
        });
        formProposta.querySelectorAll('[data-somente-pf]').forEach(el => { el.hidden = pj; });
        formProposta.querySelectorAll('[data-somente-pj]').forEach(el => { el.hidden = !pj; });
    }

    document.querySelectorAll('.btn-escolher-proposta').forEach(btn => {
        btn.addEventListener('click', () => {
            if (!modalProposta) return;
            formProposta.elements.seguradora.value = btn.dataset.seguradora;
            ajustarCamposExtras();
            modalProposta.hidden = false;
            formProposta.elements.nome_completo?.focus();
        });
    });
    formProposta?.elements.cpf_cnpj?.addEventListener('input', () => ajustarCamposExtras());

    // Máscaras dos campos extras (data DD/MM/AAAA e moeda).
    formProposta?.querySelectorAll('[data-mascara="data"]').forEach(el => {
        el.addEventListener('input', () => {
            const d = el.value.replace(/\D/g, '').slice(0, 8);
            el.value = d.replace(/^(\d{2})(\d)/, '$1/$2').replace(/^(\d{2})\/(\d{2})(\d)/, '$1/$2/$3');
        });
    });
    formProposta?.querySelectorAll('[data-mascara="moeda"]').forEach(el => {
        el.addEventListener('input', () => { el.value = formatarMoeda(el.value); });
    });

    function preencherEndereco(dados) {
        Object.entries(dados).forEach(([campo, valor]) => {
            const el = formProposta.elements[campo];
            if (el && valor) el.value = valor;
        });
    }

    document.getElementById('btn-prop-endereco-imovel')?.addEventListener('click', (e) => {
        const imovel = JSON.parse(e.currentTarget.dataset.imovel || '{}');
        if (imovel.cep) imovel.cep = imovel.cep.replace(/\D/g, '').replace(/(\d{5})(\d{1,3})$/, '$1-$2');
        preencherEndereco(imovel);
    });

    formProposta?.elements.cep?.addEventListener('blur', async () => {
        const cep = formProposta.elements.cep.value.replace(/\D/g, '');
        if (cep.length !== 8) return;
        try {
            const r = await fetch(`https://viacep.com.br/ws/${cep}/json/`);
            const d = await r.json();
            if (d.erro) return;
            preencherEndereco({ endereco: d.logradouro, bairro: d.bairro, cidade: d.localidade, uf: d.uf });
        } catch (_) {}
    });

    formProposta?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const f = formProposta.elements;

        if (residencial) {
            const parcelas = f['parcelas_' + f.seguradora.value]?.value || '1';
            const ok = await acao(
                'Aceitando a proposta na seguradora...',
                `${base}/${f.seguradora.value}/emitir`,
                { pagamento: { parcelas } },
                'Proposta aceita! A MPS vai enviar a apólice.',
                'Erro ao aceitar a proposta.'
            );
            if (ok) modalProposta.hidden = true;
            return;
        }

        const obrigatorios = {
            nome_completo: 'o nome do proprietário',
            cpf_cnpj:      'o CPF/CNPJ do proprietário',
            cep:           'o CEP',
            numero:        'o número',
            endereco:      'o endereço',
            bairro:        'o bairro',
            cidade:        'a cidade',
            uf:            'a UF',
        };
        for (const [campo, rotulo] of Object.entries(obrigatorios)) {
            if (!f[campo].value.trim()) {
                mpsAlert.error(`Informe ${rotulo}.`);
                f[campo].focus();
                return;
            }
        }

        const proprietario = {};
        ['nome_completo', 'cpf_cnpj', 'email', 'tel_celular', 'cep', 'endereco', 'numero', 'complemento', 'bairro', 'cidade', 'uf']
            .forEach(campo => { proprietario[campo] = f[campo].value.trim(); });

        // Too Seguros: dados extras obrigatórios do proprietário.
        if (f.seguradora.value === 'too') {
            const pj = proprietario.cpf_cnpj.replace(/\D/g, '').length === 14;
            const exigidos = { email: 'o e-mail do proprietário', tel_celular: 'o telefone do proprietário' };
            if (pj) {
                exigidos.faturamento = 'o faturamento mensal da empresa';
            } else {
                Object.assign(exigidos, {
                    data_nascimento: 'a data de nascimento do proprietário',
                    estado_civil: 'o estado civil do proprietário',
                    nacionalidade: 'a nacionalidade do proprietário',
                    renda: 'a renda mensal do proprietário',
                    pep: 'se o proprietário é pessoa politicamente exposta',
                });
            }
            for (const [campo, rotulo] of Object.entries(exigidos)) {
                if (!(f[campo]?.value || '').trim()) {
                    mpsAlert.error(`Informe ${rotulo}.`);
                    f[campo]?.focus?.();
                    return;
                }
            }
            ['data_nascimento', 'estado_civil', 'nacionalidade', 'pep'].forEach(campo => {
                proprietario[campo] = (f[campo]?.value || '').trim();
            });
            proprietario.renda       = numeroMoeda(f.renda?.value || '').toFixed(2);
            proprietario.faturamento = numeroMoeda(f.faturamento?.value || '').toFixed(2);
        }

        // A emissão vai pra fila (~1-2 min na seguradora): o servidor só
        // valida e responde; o card passa a mostrar "Gerando a proposta...".
        const ok = await acao(
            'Aceitando a proposta na seguradora...',
            `${base}/${f.seguradora.value}/emitir`,
            { proprietario },
            'Proposta aceita! A MPS vai enviar a apólice.',
            'Erro ao aceitar a proposta.'
        );
        if (ok) modalProposta.hidden = true;
    });

    // ─── "Corrigir dados": a seguradora recusou um dado da ficha ───
    // Só os campos recusados aparecem no modal; ao salvar, a cotação é
    // reenviada pra(s) seguradora(s) que recusaram.
    const modalCorrigir = document.getElementById('modal-corrigir-dados');
    const formCorrigir  = document.getElementById('form-corrigir-dados');

    document.querySelectorAll('.btn-corrigir-dados').forEach(btn => {
        btn.addEventListener('click', () => {
            if (!modalCorrigir) return;
            modalCorrigir.hidden = false;
            formCorrigir.querySelector('input')?.focus();
        });
    });

    /** "R$ 1.234,56" a partir do que foi digitado (só dígitos = centavos). */
    function formatarMoeda(texto) {
        const digitos = texto.replace(/\D/g, '');
        if (!digitos) return '';
        const valor = (parseInt(digitos, 10) / 100).toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
        return `R$ ${valor}`;
    }
    const numeroMoeda = texto => (parseInt(texto.replace(/\D/g, '') || '0', 10) / 100);

    formCorrigir?.querySelectorAll('[data-tipo="moeda"], [data-tipo="aluguel"]').forEach(el => {
        el.addEventListener('input', () => { el.value = formatarMoeda(el.value); });
    });
    formCorrigir?.querySelector('[data-tipo="uf"]')?.addEventListener('input', (e) => {
        e.target.value = e.target.value.replace(/[^a-z]/gi, '').toUpperCase();
    });

    formCorrigir?.elements.cep?.addEventListener('blur', async () => {
        const cep = formCorrigir.elements.cep.value.replace(/\D/g, '');
        if (cep.length !== 8) return;
        try {
            const r = await fetch(`https://viacep.com.br/ws/${cep}/json/`);
            const d = await r.json();
            if (d.erro) {
                marcarErro(formCorrigir.elements.cep, 'CEP não encontrado. Confira os números.');
                return;
            }
            const dados = { endereco: d.logradouro, bairro: d.bairro, cidade: d.localidade, uf: d.uf };
            Object.entries(dados).forEach(([campo, valor]) => {
                const el = formCorrigir.elements[campo];
                if (el && valor) el.value = valor;
            });
        } catch (_) {}
    });

    function marcarErro(el, mensagem) {
        formCorrigir.querySelectorAll('.field.tem-erro').forEach(f => f.classList.remove('tem-erro'));
        el?.closest('.field')?.classList.add('tem-erro');
        el?.focus();
        mpsAlert.error(mensagem);
    }

    formCorrigir?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const campos = {};
        for (const el of formCorrigir.querySelectorAll('input[name]')) {
            const valor = el.value.trim();
            const rotulo = el.closest('.field').querySelector('.field-label').firstChild.textContent.trim();
            const tipo = el.dataset.tipo;
            if (!valor && !['opcional', 'moeda'].includes(tipo)) return marcarErro(el, `Preencha o campo "${rotulo}".`);
            if (tipo === 'nome' && valor.split(/\s+/).length < 2) return marcarErro(el, 'Informe o nome completo (nome e sobrenome).');
            if (tipo === 'cep' && valor.replace(/\D/g, '').length !== 8) return marcarErro(el, 'Informe um CEP válido.');
            if (tipo === 'uf' && !/^[A-Z]{2}$/.test(valor)) return marcarErro(el, 'Informe a UF com 2 letras.');
            if (tipo === 'email' && !/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(valor)) return marcarErro(el, 'Informe um e-mail válido.');
            campos[el.name] = ['moeda', 'aluguel'].includes(tipo) ? numeroMoeda(valor).toFixed(2) : valor;
        }
        if (campos.valor_aluguel !== undefined) {
            const encargos = ['valor_condominio', 'valor_iptu', 'valor_agua', 'valor_energia', 'valor_gas']
                .reduce((soma, c) => soma + parseFloat(campos[c] || 0), 0);
            if (encargos > 2 * parseFloat(campos.valor_aluguel)) {
                return marcarErro(formCorrigir.elements.valor_condominio, 'Os encargos passam de 2x o aluguel.');
            }
        }

        mpsAlert.loading('Reenviando...');
        try {
            const d = await enviar(`${base}/corrigir`, { campos });
            if (!d.success) {
                return d.campo ? marcarErro(formCorrigir.elements[d.campo], d.message) : mpsAlert.error(d.message || 'Erro ao reenviar a cotação.');
            }
            modalCorrigir.hidden = true;
            await mpsAlert.success('Cotação reenviada!');
            window.location.reload();
        } catch (_) {
            mpsAlert.error('Erro na requisição.');
        }
    });

    // ─── Reanálise com outro pretendente (admin) ───
    document.querySelectorAll('.btn-reanalisar').forEach(btn => {
        btn.addEventListener('click', () => {
            const seguradora = btn.dataset.seguradora;
            const cpf  = (document.getElementById(`reanalise-cpf-${seguradora}`)?.value || '').replace(/\D/g, '');
            const nome = (document.getElementById(`reanalise-nome-${seguradora}`)?.value || '').trim();
            if (!nome) return mpsAlert.error('Informe o nome do pretendente adicional.');
            if (!cpf) return mpsAlert.error('Informe o CPF/CNPJ do pretendente adicional.');

            acao(
                'Enviando a reanálise...',
                `${base}/${seguradora}/reanalisar`,
                { cpf_cnpj: cpf, nome_completo: nome },
                'Reanálise enviada para a seguradora.',
                'Erro ao pedir reanálise.'
            );
        });
    });

    // ─── Link de biometria (copia pro pretendente) ───
    document.querySelectorAll('.btn-link-biometria').forEach(btn => {
        btn.addEventListener('click', async () => {
            mpsAlert.loading('Buscando o link de biometria...');
            try {
                const resp = await fetch(`${base}/${btn.dataset.seguradora}/biometria`);
                const d = await resp.json();
                if (!d.success) return mpsAlert.error(d.message || 'Erro ao buscar o link de biometria.');
                try {
                    await navigator.clipboard.writeText(d.url);
                    mpsAlert.success('Link de biometria copiado! Envie pro pretendente.');
                } catch (e) {
                    mpsAlert.error('Não foi possível copiar o link automaticamente.');
                }
            } catch (e) {
                mpsAlert.error('Erro na requisição.');
            }
        });
    });

    // ─── PDF da proposta / apólice ───
    // Busca primeiro (com loading) e só abre depois que o PDF chegou — se a
    // seguradora ainda não gerou (409) ou falhou, só mostra a mensagem.
    function abrirPdf(blob, nomeArquivo) {
        const url = URL.createObjectURL(blob);
        // Depois de um await o navegador pode bloquear a nova aba como popup;
        // nesse caso baixa o arquivo em vez de sair da tela.
        if (!window.open(url, '_blank')) {
            const a = document.createElement('a');
            a.href = url;
            a.download = nomeArquivo;
            document.body.appendChild(a);
            a.click();
            a.remove();
        }
        setTimeout(() => URL.revokeObjectURL(url), 60000);
    }

    document.querySelectorAll('.btn-documento').forEach(btn => {
        btn.addEventListener('click', async () => {
            const { seguradora, tipo } = btn.dataset;
            const falha = 'A seguradora não conseguiu gerar o documento agora. Tente novamente em instantes.';
            mpsAlert.loading(tipo === 'apolice'
                ? 'Buscando a apólice na seguradora... pode levar até 1 minuto.'
                : 'Buscando a proposta na seguradora... pode levar até 1 minuto.');
            try {
                const resp = await fetch(`${base}/${seguradora}/documento/${tipo}`);
                if (!resp.ok) {
                    // Só mostra o texto quando é a nossa mensagem (texto puro) —
                    // nunca uma página de erro HTML (ex: do Cloudflare).
                    const texto = (resp.headers.get('Content-Type') || '').includes('text/html') ? '' : await resp.text();
                    return mpsAlert.error(texto || falha);
                }
                const blob = await resp.blob();
                if (blob.type !== 'application/pdf') return mpsAlert.error(falha);
                mpsAlert.close();
                abrirPdf(blob, `${cfg.protocolo}-${tipo}.pdf`);
            } catch (e) {
                mpsAlert.error('Erro na requisição.');
            }
        });
    });

    // ─── MPS: "Fazer cotação" — envia as seguradoras marcadas ao cliente ───
    const incluir = () => [...document.querySelectorAll('.seg-incluir:checked')];
    const btnEnviarPropostas = document.getElementById('btn-enviar-propostas');
    const contadorPropostas  = document.getElementById('propostas-selecionadas');
    const atualizarSelecao = () => {
        if (!btnEnviarPropostas) return;
        const n = incluir().length;
        btnEnviarPropostas.disabled = n === 0;
        if (contadorPropostas) contadorPropostas.textContent = n === 1 ? '1 seguradora' : `${n} seguradoras`;
    };
    document.querySelectorAll('.seg-incluir').forEach(el => el.addEventListener('change', atualizarSelecao));
    atualizarSelecao();
    btnEnviarPropostas?.addEventListener('click', async () => {
        const marcadas = incluir();
        if (!marcadas.length) return mpsAlert.error('Escolha ao menos uma seguradora para enviar ao cliente.');
        const ok = await mpsAlert.confirm({
            title: 'Enviar propostas ao cliente?',
            message: `O cliente vai ver as propostas de: ${marcadas.map(el => el.dataset.nome).join(', ')} — com os valores e se pedem biometria — e escolher uma.`,
            confirmText: 'Enviar',
            danger: false,
        });
        if (!ok) return;
        mpsAlert.loading('Enviando as propostas ao cliente...');
        try {
            const d = await enviar(`${base}/propostas`, {
                seguradoras: marcadas.map(el => el.value),
                observacao: document.getElementById('propostas-observacao')?.value.trim() || '',
            });
            if (!d.success) return mpsAlert.error(d.message || 'Erro ao enviar as propostas.');
            await mpsAlert.success('Propostas enviadas ao cliente!');
            window.location.href = appUrl(`/admin/cotacao/fianca/${cfg.protocolo}`);
        } catch (e) {
            mpsAlert.error('Erro na requisição.');
        }
    });

    // ─── Biometria pendente (antes das propostas): copiar link / enviar por WhatsApp ───
    // O link vem da seguradora na hora (seguradoras/biometria-link) — a
    // imobiliária não vê qual seguradora pediu.
    async function linkBiometria(n) {
        const resp = await fetch(`${base}/biometria-link?n=${encodeURIComponent(n)}`);
        const d = await resp.json();
        if (!d.success) throw new Error(d.message || 'Não foi possível obter o link de biometria.');
        return d.url;
    }
    document.querySelectorAll('.btn-biometria-copiar').forEach(btn => {
        btn.addEventListener('click', async () => {
            mpsAlert.loading('Buscando o link de biometria...');
            try {
                const url = await linkBiometria(btn.dataset.n);
                try {
                    await navigator.clipboard.writeText(url);
                    mpsAlert.success('Link copiado! Envie para o pretendente.');
                } catch (_) {
                    mpsAlert.error(`Não foi possível copiar automaticamente. Link: ${url}`);
                }
            } catch (e) {
                mpsAlert.error(e.message || 'Erro na requisição.');
            }
        });
    });
    document.querySelectorAll('.btn-biometria-whatsapp').forEach(btn => {
        btn.addEventListener('click', async () => {
            // Abre a aba já no clique (senão o navegador bloqueia como popup depois do await).
            const aba = window.open('', '_blank');
            mpsAlert.loading('Buscando o link de biometria...');
            try {
                const url = await linkBiometria(btn.dataset.n);
                const telefone = (btn.dataset.telefone || '').replace(/\D/g, '');
                const texto = encodeURIComponent(`Olá! Para continuar a análise do seu seguro fiança, faça a biometria facial por este link: ${url}`);
                const destino = `https://wa.me/${telefone.length >= 10 ? '55' + telefone : ''}?text=${texto}`;
                mpsAlert.close();
                if (aba) aba.location.href = destino; else window.location.href = destino;
            } catch (e) {
                aba?.close();
                mpsAlert.error(e.message || 'Erro na requisição.');
            }
        });
    });

    // ─── Imobiliária: recusa todas as propostas (volta pra MPS) ───
    document.getElementById('btn-recusar-propostas')?.addEventListener('click', async () => {
        const ok = await mpsAlert.confirm({
            title: 'Recusar as propostas?',
            message: 'O cliente não quer nenhuma das propostas. A MPS será avisada e pode enviar outras.',
            confirmText: 'Recusar',
        });
        if (!ok) return;
        acao('Registrando a recusa...', `${base}/propostas/recusar`, {}, 'Propostas recusadas. A MPS foi avisada.', 'Erro ao recusar as propostas.');
    });

    // ─── Imobiliária: acompanha o que ela vê (pré-análise, emissão, apólice) ───
    // Endpoint leve (banco) a cada 5s; recarrega quando a assinatura muda.
    // Pausa com a aba em segundo plano e para depois de 15 min.
    if (cfg.aguardandoImobiliaria) {
        const inicio = Date.now();
        const consultarSituacao = async () => {
            if (document.hidden) return setTimeout(consultarSituacao, 5000);
            if (Date.now() - inicio > 15 * 60 * 1000) return;
            let d;
            try {
                const resp = await fetch(`${base}/situacao`);
                if (!(resp.headers.get('Content-Type') || '').includes('application/json')) return;
                d = await resp.json();
            } catch (_) {
                return setTimeout(consultarSituacao, 5000);
            }
            if (!d.success) return;
            if (d.assinatura !== cfg.assinatura) return window.location.reload();
            if (d.aguardando) setTimeout(consultarSituacao, 5000);
        };
        setTimeout(consultarSituacao, 5000);
    }

    // ─── Acompanhando a fila (cotação/emissão/reanálise no worker) ───
    // Consulta um endpoint leve a cada 4s e recarrega quando o trabalho
    // termina. Para sozinho se a sessão expirou (resposta não-JSON) ou o
    // acesso foi negado, e avisa se passar de 10 min (fila parada). Pausa
    // com a aba em segundo plano.
    if (cfg.aguardando) {
        const inicio = Date.now();
        const LIMITE_MS = 10 * 60 * 1000;
        const consultar = async () => {
            if (document.hidden) return setTimeout(consultar, 4000);
            if (Date.now() - inicio > LIMITE_MS) {
                return mpsAlert.error('A seguradora está demorando mais que o normal. Use "Atualizar" daqui a pouco ou fale com a equipe MPS.');
            }
            let d;
            try {
                const resp = await fetch(`${base}/fila`);
                if (!(resp.headers.get('Content-Type') || '').includes('application/json')) {
                    return mpsAlert.error('Sua sessão expirou. Entre de novo para acompanhar a cotação.');
                }
                d = await resp.json();
            } catch (_) {
                return setTimeout(consultar, 4000); // rede instável: tenta no próximo ciclo
            }
            if (!d.success) return mpsAlert.error(d.message || 'Não foi possível acompanhar a cotação.');
            if (!d.ativo) return window.location.reload();
            setTimeout(consultar, 4000);
        };
        setTimeout(consultar, 4000);
    }
});
