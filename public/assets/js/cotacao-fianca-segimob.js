/**
 * Segimob (Fiança) — análise nas seguradoras, nas duas pontas:
 *   MPS          painel do detalhe: "Atualizar" (reconsulta as seguradoras);
 *   imobiliária  detalhe da cotação: "Corrigir dados" quando as seguradoras
 *                não aceitaram algum dado.
 * As duas acompanham a análise por polling (situacao) e recarregam quando ela
 * termina — pré-aprovada (segue o painel de documentos) ou recusada.
 */
document.addEventListener('DOMContentLoaded', () => {
    const cfg = window.__SEGIMOB_CONFIG;
    if (!cfg) return;

    const base = `/api/cotacao/fianca/${cfg.protocolo}/segimob`;

    /** POST JSON com CSRF e loading do mpsAlert; devolve o JSON (ou null em falha de rede). */
    async function enviar(mensagemLoading, acao, corpo = {}) {
        mpsAlert.loading(mensagemLoading);
        try {
            const resp = await fetch(`${base}/${acao}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': cfg.csrf },
                body: JSON.stringify(corpo),
            });
            return await resp.json();
        } catch (e) {
            mpsAlert.error('Erro na requisição.');
            return null;
        }
    }

    // ─── Atualizar ───
    document.getElementById('btn-atualizar-seguradoras')?.addEventListener('click', async () => {
        const d = await enviar('Consultando as seguradoras...', 'atualizar');
        if (!d) return;
        if (!d.success) return mpsAlert.error(d.message || 'Erro ao atualizar.');
        window.location.reload();
    });

    // ─── "Corrigir dados": abre o modal com os campos recusados; salvar reenvia ───
    const modalCorrigir = document.getElementById('modal-corrigir');
    const formCorrigir  = document.getElementById('form-corrigir');
    document.getElementById('btn-corrigir-dados')?.addEventListener('click', () => {
        modalCorrigir.hidden = false;
        formCorrigir.querySelector('input')?.focus();
    });
    formCorrigir?.querySelectorAll('[data-mascara="data"]').forEach(el => {
        el.addEventListener('input', () => {
            const v = el.value.replace(/\D/g, '').slice(0, 8);
            el.value = v.replace(/(\d{2})(\d)/, '$1/$2').replace(/(\d{2})\/(\d{2})(\d)/, '$1/$2/$3');
        });
    });
    formCorrigir?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const campos = {};
        formCorrigir.querySelectorAll('input[name]').forEach(el => { campos[el.name] = el.value.trim(); });
        const d = await enviar('Corrigindo e reenviando para as seguradoras...', 'corrigir', { campos });
        if (!d) return;
        if (!d.success) return mpsAlert.error(d.message || 'Erro ao corrigir os dados.');
        modalCorrigir.hidden = true;
        await mpsAlert.success('Dados corrigidos — cotação reenviada!');
        window.location.reload();
    });

    // ─── Acompanhando a análise ───
    // O cron (segimob:acompanhamento) reconsulta a Segimob; aqui só um
    // endpoint leve (banco) a cada 5s, recarregando quando a análise muda de
    // etapa ou termina. Pausa com a aba em segundo plano e para sozinho se a
    // sessão expirou ou depois de 15 min.
    if (cfg.aguardando) {
        const inicio = Date.now();
        const LIMITE_MS = 15 * 60 * 1000;
        const consultar = async () => {
            if (document.hidden) return setTimeout(consultar, 5000);
            if (Date.now() - inicio > LIMITE_MS) return; // análise manual longa: o "Atualizar" continua lá
            let d;
            try {
                const resp = await fetch(`${base}/situacao`);
                if (!(resp.headers.get('Content-Type') || '').includes('application/json')) {
                    return mpsAlert.error('Sua sessão expirou. Entre de novo para acompanhar a cotação.');
                }
                d = await resp.json();
            } catch (_) {
                return setTimeout(consultar, 5000); // rede instável: tenta no próximo ciclo
            }
            if (!d.success) return;
            if (!d.aguardando || d.assinatura !== cfg.assinatura) return window.location.reload();
            setTimeout(consultar, 5000);
        };
        setTimeout(consultar, 5000);
    }
});
