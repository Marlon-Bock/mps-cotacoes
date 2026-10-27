/**
 * /admin/sistema-erros — filtros (status/tipo), diagnóstico e ações, tudo via
 * AJAX, sem navegar nem alterar a URL. Mesmo padrão de admin-chamados-listar.js.
 */
(function () {
    'use strict';

    const linhas    = document.getElementById('sistema-erros-rows');
    const csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

    let statusAtivo = 'pendentes';
    let tipoAtivo   = '';

    function marcarAtivo(seletor, dataAttr, valorAtivo) {
        document.querySelectorAll(seletor).forEach((botao) => {
            const ativo = botao.dataset[dataAttr] === valorAtivo;
            botao.classList.toggle('btn-primary', ativo);
            botao.classList.toggle('btn-secondary', !ativo);
        });
    }

    function ligarBotoesResolvido() {
        linhas.querySelectorAll('.btn-toggle-resolvido').forEach((botao) => {
            botao.addEventListener('click', async () => {
                botao.disabled = true;
                try {
                    const resp = await fetch(`/api/admin/sistema-erros/${botao.dataset.id}/resolver`, {
                        method: 'POST',
                        headers: { 'X-CSRF-Token': csrfToken },
                    });
                    const data = await resp.json();
                    if (!resp.ok || !data.success) {
                        mpsAlert.error(data.message || 'Não foi possível atualizar.');
                        botao.disabled = false;
                        return;
                    }
                    mpsAlert.toast('success', data.resolvido ? 'Marcado como corrigido.' : 'Reaberto.');
                    carregar();
                } catch {
                    mpsAlert.error('Erro na requisição.');
                    botao.disabled = false;
                }
            });
        });
    }

    function carregar() {
        linhas.innerHTML = '<div class="admin-cot-empty">Carregando...</div>';

        const params = new URLSearchParams({ status: statusAtivo, tipo: tipoAtivo });
        fetch('/api/admin/sistema-erros/listar?' + params.toString())
            .then((r) => r.json())
            .then((data) => {
                if (!data.success) {
                    linhas.innerHTML = '<div class="admin-cot-empty">Sem acesso.</div>';
                    return;
                }
                linhas.innerHTML = data.html || '';
                ligarBotoesResolvido();

                document.querySelector('[data-count="pendentes"]').textContent  = data.pendentes;
                document.querySelector('[data-count="resolvidos"]').textContent = data.resolvidos;
                document.querySelector('[data-count="todos"]').textContent      = data.todos;
                document.querySelector('[data-count="exception"]').textContent  = data.tipos.exception;
                document.querySelector('[data-count="error"]').textContent      = data.tipos.error;
                document.querySelector('[data-count="fatal"]').textContent      = data.tipos.fatal;
            })
            .catch(() => {
                linhas.innerHTML = '<div class="admin-cot-empty">Erro ao carregar.</div>';
            });
    }

    document.querySelectorAll('[data-filtro-status]').forEach((botao) => {
        botao.addEventListener('click', () => {
            statusAtivo = botao.dataset.filtroStatus;
            marcarAtivo('[data-filtro-status]', 'filtroStatus', statusAtivo);
            carregar();
        });
    });

    document.querySelectorAll('[data-filtro-tipo]').forEach((botao) => {
        botao.addEventListener('click', () => {
            tipoAtivo = botao.dataset.filtroTipo;
            marcarAtivo('[data-filtro-tipo]', 'filtroTipo', tipoAtivo);
            carregar();
        });
    });

    async function chamarAcao(botao, url, montarTexto) {
        const resultado = document.getElementById('diagnostico-resultado');
        botao.classList.add('is-loading');
        botao.disabled = true;
        resultado.textContent = '';
        try {
            const resp = await fetch(url, { method: 'POST', headers: { 'X-CSRF-Token': csrfToken } });
            const data = await resp.json();
            if (!resp.ok || !data.success) {
                mpsAlert.error(data.message || 'Falha no diagnóstico.');
                return;
            }
            resultado.innerHTML = montarTexto(data);
        } catch {
            mpsAlert.error('Erro na requisição.');
        } finally {
            botao.classList.remove('is-loading');
            botao.disabled = false;
        }
    }

    document.getElementById('btn-diagnostico-permissoes')?.addEventListener('click', function () {
        chamarAcao(this, '/api/admin/diagnostico-permissoes', (data) => {
            return data.resultados.map((r) => {
                const cor = r.ok ? 'var(--color-success)' : 'var(--color-danger)';
                const status = r.ok ? '✓ conseguiu escrever' : '✗ ' + (r.erro || 'falhou');
                return `<strong>${r.local}:</strong> <span style="color:${cor}">${status}</span>`;
            }).join(' &nbsp;|&nbsp; ') + ` <small style="color:var(--color-gray-400)">(rodando como ${data.resultados[0]?.usuario || '?'})</small>`;
        });
    });

    document.getElementById('btn-gerar-erro-teste')?.addEventListener('click', function () {
        chamarAcao(this, '/api/admin/gerar-erro-teste', (data) => {
            setTimeout(carregar, 800);
            return data.message;
        });
    });

    carregar();
})();
