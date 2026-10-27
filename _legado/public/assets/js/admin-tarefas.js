(function () {
    'use strict';

    var app        = document.querySelector('.app-content[data-gestor]');
    var CSRF        = app ? (app.dataset.csrf || '') : '';
    var IS_GESTOR   = app ? app.dataset.gestor === '1' : false;

    // Usuários MPS (id, nome, avatar, gestor, setores) — compartilhado pelo
    // filtro de responsáveis e pelo autocomplete do modal de setor.
    var USUARIOS_ALL = [];
    try {
        var _uj = document.getElementById('tarefas-usuarios-json');
        if (_uj) USUARIOS_ALL = JSON.parse(_uj.textContent || '[]');
    } catch (e) { USUARIOS_ALL = []; }

    var STATUS_ALL = [];
    try {
        var _sj = document.getElementById('tarefas-status-json');
        if (_sj) STATUS_ALL = JSON.parse(_sj.textContent || '[]');
    } catch (e) { STATUS_ALL = []; }

    var SETORES = [];
    try {
        var _sej = document.getElementById('tarefas-setores-json');
        if (_sej) SETORES = JSON.parse(_sej.textContent || '[]');
    } catch (e) { SETORES = []; }

    // Paleta base para o editor de status.
    var CORES = ['#2563eb', '#16a34a', '#dc2626', '#d97706', '#7c3aed', '#0891b2', '#db2777', '#475569'];

    function escapeAttr(v) { return String(v == null ? '' : v).replace(/"/g, '&quot;'); }
    function avatarHtml(u) {
        if (u.avatar) return '<img class="avatar-sm avatar-sm-img" src="' + escapeAttr(u.avatar) + '" alt="">';
        return '<span class="avatar-sm">' + escapeHtml((u.nome || '').trim().charAt(0).toUpperCase()) + '</span>';
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Utilitários
    // ─────────────────────────────────────────────────────────────────────────
    function el(id) { return document.getElementById(id); }

    function hexToRgba(hex, alpha) {
        var h = (hex || '#64748b').replace('#', '');
        if (h.length === 3) { h = h[0] + h[0] + h[1] + h[1] + h[2] + h[2]; }
        var r = parseInt(h.substring(0, 2), 16);
        var g = parseInt(h.substring(2, 4), 16);
        var b = parseInt(h.substring(4, 6), 16);
        return 'rgba(' + r + ',' + g + ',' + b + ',' + alpha + ')';
    }

    // Texto legível (preto/branco) sobre uma cor de fundo, pela luminância.
    function corContraste(hex) {
        var h = (hex || '#64748b').replace('#', '');
        if (h.length === 3) h = h[0] + h[0] + h[1] + h[1] + h[2] + h[2];
        var r = parseInt(h.substr(0, 2), 16), g = parseInt(h.substr(2, 2), 16), b = parseInt(h.substr(4, 2), 16);
        return (0.299 * r + 0.587 * g + 0.114 * b) / 255 > 0.6 ? '#1a1a1a' : '#ffffff';
    }

    // Aplica as cores das pílulas [data-cor] (padrão data-* + JS, sem style inline no PHP).
    // Status = bloco sólido (estilo Monday); setor/etiqueta = pílula suave (tinta).
    function aplicarCores(container) {
        (container || document).querySelectorAll('.tarefa-pill[data-cor]').forEach(function (pill) {
            var cor = pill.dataset.cor || '#64748b';
            if (pill.classList.contains('tarefa-pill-status')) {
                pill.style.backgroundColor = cor;
                pill.style.borderColor = cor;
                pill.style.color = corContraste(cor);
            } else {
                pill.style.color = cor;
                pill.style.backgroundColor = hexToRgba(cor, 0.14);
                pill.style.borderColor = hexToRgba(cor, 0.35);
            }
        });
    }

    function postJSON(url, body) {
        return fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': CSRF },
            body: JSON.stringify(body || {}),
        }).then(function (r) { return r.json(); });
    }

    // Custom select — set / reset via JS (mesmo padrão de cotacoes.js).
    function setCustomSelect(hidden, value) {
        if (!hidden) return;
        var wrapper = hidden.closest('[data-custom-select]');
        if (!wrapper) return;
        value = value === null || value === undefined ? '' : String(value);
        var valueEl = wrapper.querySelector('.custom-select-value');
        var label = wrapper.dataset.placeholder || 'Selecione';
        var isPlaceholder = true;
        wrapper.querySelectorAll('.custom-select-option').forEach(function (opt) {
            var match = opt.dataset.value === value && value !== '';
            opt.classList.toggle('is-selected', match || (value === '' && opt.dataset.value === ''));
            if (match) { label = opt.textContent; isPlaceholder = false; }
        });
        valueEl.textContent = label;
        valueEl.classList.toggle('is-placeholder', isPlaceholder);
        hidden.value = value;
    }

    function resetCustomSelect(hidden) { setCustomSelect(hidden, ''); }

    // Date picker — set / reset via JS.
    function setDatePicker(hidden, iso) {
        if (!hidden) return;
        var dp = hidden.closest('[data-date-picker]');
        if (!dp) return;
        var valueEl = dp.querySelector('.date-picker-value');
        if (iso && /^\d{4}-\d{2}-\d{2}$/.test(iso)) {
            var p = iso.split('-');
            valueEl.textContent = p[2] + '/' + p[1] + '/' + p[0];
            valueEl.classList.remove('is-placeholder');
            hidden.value = iso;
        } else {
            valueEl.textContent = dp.dataset.placeholder || 'DD/MM/AAAA';
            valueEl.classList.add('is-placeholder');
            hidden.value = '';
        }
    }

    function hiddenByName(name, scope) {
        return (scope || document).querySelector('[data-custom-select] input[name="' + name + '"]');
    }

    // Modais — abrir/fechar por toggle de [hidden] (padrão admin-faq.js).
    function abrir(id) { var m = el(id); if (m) m.hidden = false; }
    function fechar(id) { var m = el(id); if (m) m.hidden = true; }

    document.addEventListener('click', function (e) {
        var closer = e.target.closest('[data-close]');
        if (closer) { fechar(closer.dataset.close); }
    });

    // ─────────────────────────────────────────────────────────────────────────
    // Abas
    // ─────────────────────────────────────────────────────────────────────────
    document.querySelectorAll('.tarefas-tab').forEach(function (tab) {
        tab.addEventListener('click', function () {
            var alvo = tab.dataset.tab;
            document.querySelectorAll('.tarefas-tab').forEach(function (t) { t.classList.toggle('is-active', t === tab); });
            document.querySelectorAll('.tarefas-panel').forEach(function (p) {
                p.classList.toggle('is-active', p.dataset.panel === alvo);
            });
            if (alvo === 'tarefas') {
                // As abas "Tarefas" + setores filtram a lista pelo setor (vazio = todas).
                activeSetor = tab.dataset.setor || '';
                recarregarDaPrimeira();
            }
            if (alvo === 'dashboard') carregarDashboard();
        });
    });

    // ═════════════════════════════════════════════════════════════════════════
    // ABA TAREFAS — lista, filtros, paginação
    // ═════════════════════════════════════════════════════════════════════════
    var lista       = el('tarefas-rows');
    var btnLimpar   = el('btn-limpar');
    var pagInfo     = el('pag-info');
    var btnAnterior = el('pag-anterior');
    var btnProxima  = el('pag-proxima');

    var activeStatus = '';
    var activeSetor  = '';   // definido pelas abas de setor (só gestor)
    var currentPage  = 1;
    var totalPages   = 1;
    var debounceTimer = null;
    var sortBy = '';   // '' | 'status' | 'prazo' — vazio = ordem manual (drag-and-drop)
    var sortDir = 'desc';
    var agruparPor = 'setor';   // recebido do backend a cada carregar(), usado pelo drag-and-drop

    var fBusca       = el('f-busca');
    var fResponsavel = hiddenByName('f_responsavel');   // só existe para gestor
    var fDataDe      = el('f-data-de');
    var fDataAte     = el('f-data-ate');

    function getFiltros() {
        return {
            status:      activeStatus,
            busca:       fBusca ? fBusca.value.trim() : '',
            setor:       activeSetor,
            responsavel: fResponsavel ? fResponsavel.value : '',
            data_de:     fDataDe ? fDataDe.value : '',
            data_ate:    fDataAte ? fDataAte.value : '',
            sort_by:     sortBy,
            sort_dir:    sortDir,
            page:        currentPage,
        };
    }

    function temFiltroAtivo() {
        var f = getFiltros();
        return f.busca || f.responsavel || f.data_de || f.data_ate || activeStatus;
    }


    function carregar(depois) {
        var params = new URLSearchParams(getFiltros());
        lista.innerHTML = '<div class="admin-cot-empty">Carregando...</div>';
        if (btnLimpar) btnLimpar.classList.toggle('is-hidden', !temFiltroAtivo());

        fetch('/api/admin/tarefas/listar?' + params.toString())
            .then(function (r) { return r.json(); })
            .then(function (data) {
                lista.innerHTML = data.html || '';
                agruparPor = data.agrupar_por || 'setor';
                aplicarCores(lista);
                lista.querySelectorAll('.mnd-group-dot[data-cor]').forEach(function (d) { d.style.backgroundColor = d.dataset.cor || '#94a3b8'; });
                lista.querySelectorAll('.mnd-group[data-cor]').forEach(function (g) {
                    var cor = g.dataset.cor || '#94a3b8';
                    var tbl = g.querySelector('.mnd-table');
                    if (tbl) tbl.style.borderLeftColor = cor;
                });
                initSortableGrupos();

                var c = data.contadores || {};
                var elTodos = el('count-todos'); if (elTodos) elTodos.textContent = c.todos || 0;
                var elAtr = el('count-atrasadas'); if (elAtr) elAtr.textContent = c.atrasadas || 0;
                var elArqz = el('count-arquivadas'); if (elArqz) elArqz.textContent = c.arquivadas || 0;
                var st = c.status || {};
                document.querySelectorAll('[id^="count-status-"]').forEach(function (span) {
                    var sid = span.id.replace('count-status-', '');
                    span.textContent = st[sid] || 0;
                });

                currentPage = data.page || 1;
                totalPages  = data.total_pages || 1;
                var total   = data.total || 0;
                var perPage = data.per_page || 20;
                if (total > 0) {
                    var ini = (currentPage - 1) * perPage + 1;
                    var fim = Math.min(total, currentPage * perPage);
                    pagInfo.textContent = 'Mostrando ' + ini + '–' + fim + ' de ' + total;
                } else {
                    pagInfo.textContent = 'Nenhum resultado';
                }
                btnAnterior.disabled = currentPage <= 1;
                btnProxima.disabled  = currentPage >= totalPages;
                if (typeof depois === 'function') depois();
            })
            .catch(function () {
                lista.innerHTML = '<div class="admin-cot-empty">Erro ao carregar tarefas.</div>';
            });
    }

    function irParaPagina(p) { currentPage = p; carregar(); }
    function recarregarDaPrimeira() { irParaPagina(1); }
    function carregarDebounce() { clearTimeout(debounceTimer); debounceTimer = setTimeout(recarregarDaPrimeira, 350); }

    document.querySelectorAll('.status-kpi-card').forEach(function (card) {
        card.addEventListener('click', function (e) {
            e.preventDefault();
            document.querySelectorAll('.status-kpi-card').forEach(function (c) { c.classList.remove('is-active'); });
            card.classList.add('is-active');
            activeStatus = card.dataset.status || '';
            recarregarDaPrimeira();
        });
    });

    if (fBusca) fBusca.addEventListener('input', carregarDebounce);
    [fResponsavel].forEach(function (h) {
        if (h) h.addEventListener('change', recarregarDaPrimeira);
    });
    if (fDataDe) fDataDe.addEventListener('change', recarregarDaPrimeira);
    if (fDataAte) fDataAte.addEventListener('change', recarregarDaPrimeira);

    btnAnterior.addEventListener('click', function () { if (currentPage > 1) irParaPagina(currentPage - 1); });
    btnProxima.addEventListener('click', function () { if (currentPage < totalPages) irParaPagina(currentPage + 1); });

    if (btnLimpar) {
        btnLimpar.addEventListener('click', function () {
            if (fBusca) fBusca.value = '';
            [fResponsavel].forEach(resetCustomSelect);
            setDatePicker(fDataDe, ''); setDatePicker(fDataAte, '');
            activeStatus = '';
            document.querySelectorAll('.status-kpi-card').forEach(function (c) {
                c.classList.toggle('is-active', (c.dataset.status || '') === '');
            });
            recarregarDaPrimeira();
        });
    }

    // Ações nas linhas (editar / excluir) + colapsar grupos + adicionar — delegação.
    lista.addEventListener('click', function (e) {
        // Cabeçalho é recriado a cada carregar() (é HTML do AJAX), então o clique
        // em Status/Prazo precisa ser delegado — não dá pra usar querySelectorAll direto.
        var th = e.target.closest('.th-sortable');
        if (th) {
            var campo = th.dataset.sort;
            if (sortBy === campo) { sortDir = sortDir === 'asc' ? 'desc' : 'asc'; }
            else { sortBy = campo; sortDir = 'asc'; }
            recarregarDaPrimeira();
            return;
        }

        var grupoHead = e.target.closest('.mnd-group-head');
        if (grupoHead) { grupoHead.closest('.mnd-group').classList.toggle('is-collapsed'); return; }

        var addBtn = e.target.closest('[data-action="add-tarefa"]');
        if (addBtn) { criarTarefaRapida(parseInt(addBtn.dataset.setor, 10) || 0, parseInt(addBtn.dataset.status, 10) || 0); return; }

        var abaixoBtn = e.target.closest('[data-action="criar-abaixo"]');
        if (abaixoBtn) {
            var linhaAb = abaixoBtn.closest('.mnd-row');
            var setorAb = linhaAb ? parseInt(linhaAb.querySelector('.mnd-cell-resp') ? linhaAb.querySelector('.mnd-cell-resp').dataset.setorId : '0', 10) : 0;
            criarTarefaRapida(setorAb || 0, 0, { depoisDeId: parseInt(abaixoBtn.dataset.id, 10) || 0 });
            return;
        }

        var arqvBtn = e.target.closest('[data-action="arquivar-tarefa"]');
        if (arqvBtn) {
            var arquivando = arqvBtn.dataset.arquivada !== '1';
            postJSON('/api/admin/tarefas/arquivar', { id: parseInt(arqvBtn.dataset.id, 10), arquivar: arquivando })
                .then(function (d) {
                    if (d.ok) { mpsAlert.toast('success', arquivando ? 'Tarefa arquivada.' : 'Tarefa desarquivada.'); carregar(); }
                    else mpsAlert.toast('error', d.message || 'Erro ao arquivar.');
                }).catch(function () { mpsAlert.toast('error', 'Erro na requisição.'); });
            return;
        }

        var subBadge = e.target.closest('[data-action="toggle-subitens"]');
        if (subBadge) { toggleSubitens(parseInt(subBadge.dataset.id, 10), subBadge); return; }

        var subAcao = e.target.closest('[data-sub-action]');
        if (subAcao) { subitemAcao(subAcao); return; }

        var statusCell = e.target.closest('.mnd-cell-status');
        if (statusCell) {
            var linha = statusCell.closest('.mnd-row');
            if (linha && linha.dataset.id) { abrirStatusMenu(parseInt(linha.dataset.id, 10), statusCell, parseInt(statusCell.dataset.statusId || '0', 10)); return; }
        }
        var respCell = e.target.closest('.mnd-cell-resp');
        if (respCell) {
            var linhaR = respCell.closest('.mnd-row');
            if (linhaR && linhaR.dataset.id) { abrirRespMenu(parseInt(linhaR.dataset.id, 10), respCell); return; }
        }
        var prazoCell = e.target.closest('.mnd-cell-prazo');
        if (prazoCell) {
            var linhaP = prazoCell.closest('.mnd-row');
            if (linhaP && linhaP.dataset.id) { abrirPrazoMenu(parseInt(linhaP.dataset.id, 10), prazoCell); return; }
        }
        var arqCell = e.target.closest('.mnd-cell-arq');
        if (arqCell) {
            var linhaA = arqCell.closest('.mnd-row');
            if (linhaA && linhaA.dataset.id) { abrirArqMenu(parseInt(linhaA.dataset.id, 10), arqCell); return; }
        }
        var msgCell = e.target.closest('.mnd-cell-msg');
        if (msgCell) {
            var linhaM = msgCell.closest('.mnd-row');
            if (linhaM && linhaM.dataset.id) { abrirDetalhe(parseInt(linhaM.dataset.id, 10), msgCell); return; }
        }

        var editBtn = e.target.closest('[data-action="editar-tarefa"]');
        if (editBtn) {
            var linhaE = editBtn.closest('.mnd-row');
            abrirDetalhe(parseInt(editBtn.dataset.id, 10), linhaE ? linhaE.querySelector('.mnd-cell-msg') : null);
            return;
        }

        var delBtn = e.target.closest('[data-action="excluir-tarefa"]');
        if (delBtn) {
            var id = parseInt(delBtn.dataset.id, 10);
            mpsAlert.confirm({ message: 'Excluir esta tarefa? Essa ação não pode ser desfeita.' }).then(function (ok) {
                if (!ok) return;
                mpsAlert.loading('Excluindo...');
                postJSON('/api/admin/tarefas/excluir', { id: id }).then(function (d) {
                    if (d.ok) { mpsAlert.success('Tarefa excluída.'); carregar(); }
                    else mpsAlert.error(d.message || 'Erro ao excluir.');
                }).catch(function () { mpsAlert.error('Erro na requisição.'); });
            });
        }
    });

    // ═════════════════════════════════════════════════════════════════════════
    // DRAG-AND-DROP — reordenar dentro do grupo e mover entre grupos (SortableJS)
    // ═════════════════════════════════════════════════════════════════════════
    var gruposSortable = [];

    function destruirSortableGrupos() {
        gruposSortable.forEach(function (s) { try { s.destroy(); } catch (e) {} });
        gruposSortable = [];
    }

    function initSortableGrupos() {
        destruirSortableGrupos();
        lista.querySelectorAll('.mnd-group-body .mnd-table').forEach(function (tbl) {
            var s = Sortable.create(tbl, {
                group: 'tarefas-mnd',
                handle: '.mnd-drag',
                animation: 150,
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                filter: '.mnd-head, .mnd-add, .mnd-sub-list',
                preventOnFilter: false,
                onMove: function (evt) { return !evt.related.classList.contains('mnd-head') && !evt.related.classList.contains('mnd-add'); },
                onEnd: onDragEnd,
            });
            gruposSortable.push(s);
        });
    }

    function onDragEnd(evt) {
        var row = evt.item;
        if (!row || !row.classList.contains('mnd-row')) return;
        var id = parseInt(row.dataset.id, 10);
        if (!id) return;
        var grupoEl = row.closest('.mnd-group');
        if (!grupoEl) return;
        var grupoId = parseInt(grupoEl.dataset.grupo, 10) || 0;
        var ordenados = Array.prototype.slice.call(grupoEl.querySelectorAll('.mnd-group-body .mnd-row[data-id]'))
            .map(function (r) { return parseInt(r.dataset.id, 10); });

        postJSON('/api/admin/tarefas/mover', { id: id, agrupar_por: agruparPor, grupo_id: grupoId, ordenados: ordenados })
            .then(function (d) { if (!d.ok) { mpsAlert.toast('error', d.message || 'Erro ao mover.'); carregar(); } else { carregar(); } })
            .catch(function () { mpsAlert.toast('error', 'Erro na requisição.'); carregar(); });
    }

    // ═════════════════════════════════════════════════════════════════════════
    // CHECKLIST (subitens) — expansível direto na linha da tarefa
    // ═════════════════════════════════════════════════════════════════════════
    var subitensCache = {};   // tarefa_id => array de subitens
    var subitensAbertos = {}; // tarefa_id => true quando expandido

    function atualizarBadgeSub(tarefaId, total, feitos) {
        var badge = lista.querySelector('.mnd-cell-sub[data-id="' + tarefaId + '"]');
        if (!badge) return;
        badge.dataset.total = total;
        badge.dataset.feitos = feitos;
        badge.innerHTML = '<span class="mnd-sub' + (total > 0 ? ' has-sub' : '') + '">' + ICON_SUB
            + (total > 0 ? '<span class="mnd-sub-n">' + feitos + '/' + total + '</span>' : '') + '</span>';
    }

    function renderSubList(tarefaId) {
        var box = lista.querySelector('.mnd-sub-list[data-sub-de="' + tarefaId + '"]');
        if (!box) return;
        var itens = subitensCache[tarefaId] || [];
        var html = itens.map(function (s) {
            var feito = s.concluido === 'S';
            return '<div class="mnd-sub-item' + (feito ? ' is-feito' : '') + '" data-sub-id="' + s.id + '">' +
                '<button type="button" class="mnd-sub-check" data-sub-action="toggle" data-sub-id="' + s.id + '" aria-label="Concluir">' + (feito ? ICON_CHECK : '') + '</button>' +
                '<span class="mnd-sub-titulo">' + escapeHtml(s.titulo) + '</span>' +
                '<button type="button" class="mnd-sub-del" data-sub-action="excluir" data-sub-id="' + s.id + '" title="Excluir" aria-label="Excluir">' + ICON_TRASH + '</button>' +
            '</div>';
        }).join('');
        html += '<div class="mnd-sub-novo">' +
            '<input type="text" class="mnd-sub-novo-input" data-sub-tarefa="' + tarefaId + '" placeholder="+ Adicionar subitem" maxlength="255">' +
        '</div>';
        box.innerHTML = html;

        var input = box.querySelector('.mnd-sub-novo-input');
        if (input) {
            input.addEventListener('keydown', function (e) {
                if (e.key !== 'Enter') return;
                var v = input.value.trim();
                if (!v) return;
                input.value = '';
                postJSON('/api/admin/tarefas/subitens/salvar', { tarefa_id: tarefaId, id: 0, titulo: v, concluido: 'N' })
                    .then(function (d) {
                        if (!d.ok) { mpsAlert.toast('error', d.message || 'Erro ao adicionar.'); return; }
                        atualizarBadgeSub(tarefaId, d.total, d.feitos);
                        carregarSubitens(tarefaId, true);
                    }).catch(function () { mpsAlert.toast('error', 'Erro na requisição.'); });
            });
        }
    }

    function carregarSubitens(tarefaId, forcar) {
        if (!forcar && subitensCache[tarefaId]) { renderSubList(tarefaId); return; }
        fetch('/api/admin/tarefas/subitens/listar?tarefa_id=' + tarefaId)
            .then(function (r) { return r.json(); })
            .then(function (d) {
                subitensCache[tarefaId] = (d && d.ok) ? (d.subitens || []) : [];
                renderSubList(tarefaId);
            })
            .catch(function () { mpsAlert.toast('error', 'Erro ao carregar checklist.'); });
    }

    function toggleSubitens(tarefaId, badgeEl) {
        var row = badgeEl.closest('.mnd-row');
        var box = row ? row.nextElementSibling : null;
        if (!box || !box.classList.contains('mnd-sub-list')) return;
        var abrir = box.classList.contains('is-hidden');
        box.classList.toggle('is-hidden', !abrir);
        subitensAbertos[tarefaId] = abrir;
        if (abrir) carregarSubitens(tarefaId, false);
    }

    function subitemAcao(btn) {
        var acao = btn.dataset.subAction;
        var subId = parseInt(btn.dataset.subId, 10);
        var box = btn.closest('.mnd-sub-list');
        var tarefaId = box ? parseInt(box.dataset.subDe, 10) : 0;
        if (!tarefaId || !subId) return;

        if (acao === 'toggle') {
            var item = (subitensCache[tarefaId] || []).filter(function (s) { return s.id === subId; })[0];
            var novoValor = item && item.concluido === 'S' ? 'N' : 'S';
            postJSON('/api/admin/tarefas/subitens/salvar', { tarefa_id: tarefaId, id: subId, titulo: '', concluido: novoValor })
                .then(function (d) {
                    if (!d.ok) { mpsAlert.toast('error', d.message || 'Erro ao salvar.'); return; }
                    if (item) item.concluido = novoValor;
                    atualizarBadgeSub(tarefaId, d.total, d.feitos);
                    renderSubList(tarefaId);
                }).catch(function () { mpsAlert.toast('error', 'Erro na requisição.'); });
        } else if (acao === 'excluir') {
            postJSON('/api/admin/tarefas/subitens/excluir', { id: subId })
                .then(function (d) {
                    if (!d.ok) { mpsAlert.toast('error', d.message || 'Erro ao excluir.'); return; }
                    subitensCache[tarefaId] = (subitensCache[tarefaId] || []).filter(function (s) { return s.id !== subId; });
                    atualizarBadgeSub(tarefaId, d.total, d.feitos);
                    renderSubList(tarefaId);
                }).catch(function () { mpsAlert.toast('error', 'Erro na requisição.'); });
        }
    }

    // ── Usuários de um setor (usado pelo popup de responsáveis) ──────────────
    function usuariosDoSetor(setorId) {
        setorId = parseInt(setorId, 10);
        if (!setorId) return [];
        return USUARIOS_ALL.filter(function (u) { return (u.setores || []).indexOf(setorId) !== -1; });
    }

    // ── Helpers de render (usados pela lista e pelo dashboard) ────────────────
    function pillHtml(nome, cor) {
        return '<span class="tarefa-pill" data-cor="' + (cor || '#64748b') + '">' + escapeHtml(nome) + '</span>';
    }

    function escapeHtml(v) { var d = document.createElement('div'); d.textContent = v == null ? '' : v; return d.innerHTML; }

    // ═════════════════════════════════════════════════════════════════════════
    // DASHBOARD (gestor)
    // ═════════════════════════════════════════════════════════════════════════
    function carregarDashboard() {
        if (!IS_GESTOR) return;
        fetch('/api/admin/tarefas/dashboard').then(function (r) { return r.json(); }).then(function (d) {
            if (!d.ok) return;
            el('dash-total').textContent = d.kpis.total;
            el('dash-pendentes').textContent = d.kpis.pendentes;
            el('dash-concluidas').textContent = d.kpis.concluidas;
            el('dash-atrasadas').textContent = d.kpis.atrasadas;
            renderDonut('dash-donut', 'dash-donut-leg', d.por_status);
            renderStackSetor('dash-por-setor', 'dash-setor-leg', d.por_setor);
            renderPrazos(d.prazos);
            renderParadas(d.sem_interacao);
            renderDashLista('dash-por-resp', d.por_resp, false);
        }).catch(function () {});
    }

    // Donut SVG (dasharray em base 100 = %); legenda com cor/nome/percentual.
    function renderDonut(id, legId, itens) {
        var c = el(id), leg = el(legId);
        if (!c) return;
        var total = itens.reduce(function (s, i) { return s + i.total; }, 0);
        if (!total) { c.innerHTML = '<div class="tk-empty">Sem dados</div>'; if (leg) leg.innerHTML = ''; return; }

        var acc = 0, segs = '';
        itens.forEach(function (it) {
            if (!it.total) return;
            var pct = it.total / total * 100;
            var cor = it.cor || '#5b0000';
            segs += '<circle cx="21" cy="21" r="15.915" fill="none" stroke="' + escapeAttr(cor) + '" stroke-width="5.5"'
                + ' stroke-dasharray="' + pct.toFixed(2) + ' ' + (100 - pct).toFixed(2) + '"'
                + ' stroke-dashoffset="' + (25 - acc).toFixed(2) + '"></circle>';
            acc += pct;
        });
        c.innerHTML = '<svg viewBox="0 0 42 42" class="tk-donut-svg" aria-hidden="true">'
            + '<circle cx="21" cy="21" r="15.915" fill="none" stroke="#eeeeee" stroke-width="5.5"></circle>'
            + segs + '</svg>'
            + '<div class="tk-donut-center"><span class="tk-donut-total">' + total + '</span><span class="tk-donut-cap">tarefas</span></div>';

        leg.innerHTML = itens.filter(function (i) { return i.total > 0; }).map(function (it) {
            var pct = Math.round(it.total / total * 100);
            return '<li class="tk-leg-item"><span class="tk-leg-dot" data-cor="' + escapeAttr(it.cor || '#5b0000') + '"></span>'
                + '<span class="tk-leg-nome">' + escapeHtml(it.nome) + '</span>'
                + '<span class="tk-leg-val">' + it.total + ' · ' + pct + '%</span></li>';
        }).join('');
        leg.querySelectorAll('.tk-leg-dot[data-cor]').forEach(function (dt) { dt.style.backgroundColor = dt.dataset.cor; });
    }

    // Barra empilhada por setor: concluídas (verde) / em aberto (neutro) / atrasadas (vermelho).
    function renderStackSetor(id, legId, itens) {
        var leg = el(legId), c = el(id);
        if (leg) leg.innerHTML =
            '<span class="tk-leg-item"><span class="tk-leg-box is-ok"></span>Concluídas</span>'
          + '<span class="tk-leg-item"><span class="tk-leg-box is-open"></span>Em aberto</span>'
          + '<span class="tk-leg-item"><span class="tk-leg-box is-atr"></span>Atrasadas</span>';
        if (!c) return;
        if (!itens.length) { c.innerHTML = '<li class="tk-empty">Sem dados</li>'; return; }

        c.innerHTML = itens.map(function (s) {
            var tot = s.total || 0;
            function seg(v, cls, rot) {
                if (!v) return '';
                return '<span class="tk-stack-seg ' + cls + '" data-w="' + (v / tot * 100) + '" title="' + rot + ': ' + v + '"></span>';
            }
            var bar = tot
                ? '<div class="tk-stack-bar">' + seg(s.concluidas, 'is-ok', 'Concluídas') + seg(s.abertas, 'is-open', 'Em aberto') + seg(s.atrasadas, 'is-atr', 'Atrasadas') + '</div>'
                : '<div class="tk-stack-bar"><span class="tk-stack-seg is-vazio" data-w="100"></span></div>';
            return '<li class="tk-stack-row">'
                + '<div class="tk-stack-top"><span class="tk-dash-dot" data-cor="' + escapeAttr(s.cor || '#5b0000') + '"></span>'
                + '<span class="tk-dash-nome">' + escapeHtml(s.nome) + '</span>'
                + '<span class="tk-stack-tot">' + tot + '</span></div>'
                + bar + '</li>';
        }).join('');
        c.querySelectorAll('.tk-dash-dot[data-cor]').forEach(function (dt) { dt.style.backgroundColor = dt.dataset.cor; });
        c.querySelectorAll('.tk-stack-seg[data-w]').forEach(function (sg) { sg.style.width = sg.dataset.w + '%'; });
    }

    function tileHtml(valor, rotulo, cls) {
        return '<div class="tk-tile ' + cls + '"><span class="tk-tile-num">' + valor + '</span><span class="tk-tile-lbl">' + rotulo + '</span></div>';
    }

    function renderPrazos(p) {
        el('dash-prazo-tiles').innerHTML =
            tileHtml(p.vence_hoje, 'Vence hoje', 'is-atr')
          + tileHtml(p.em_1_3, 'Em 1–3 dias', 'is-warn')
          + tileHtml(p.em_4_7, 'Em 4–7 dias', 'is-ok');
        var box = el('dash-prazo-lista');
        if (!p.lista.length) { box.innerHTML = '<li class="tk-empty">Nenhuma tarefa vencendo nos próximos 7 dias.</li>'; return; }
        box.innerHTML = p.lista.map(function (t) {
            var badge = t.dias <= 0 ? 'Hoje' : ('Em ' + t.dias + 'd');
            var cls = t.dias <= 0 ? 'is-atr' : (t.dias <= 3 ? 'is-warn' : 'is-ok');
            return taskRow(t, '<span class="tk-badge ' + cls + '">' + badge + '</span>');
        }).join('');
        pintarDots(box);
    }

    function renderParadas(s) {
        el('dash-parada-tiles').innerHTML =
            tileHtml(s.b_3_7, '3–7 dias', 'is-warn')
          + tileHtml(s.b_8_14, '8–14 dias', 'is-warn')
          + tileHtml(s.b_15, '15+ dias', 'is-atr');
        var box = el('dash-parada-lista');
        if (!s.lista.length) { box.innerHTML = '<li class="tk-empty">Sem tarefas em aberto.</li>'; return; }
        box.innerHTML = s.lista.map(function (t) {
            var cls = t.dias >= 15 ? 'is-atr' : (t.dias >= 8 ? 'is-warn' : 'is-neutral');
            var txt = t.dias <= 0 ? 'Hoje' : (t.dias + 'd parada');
            return taskRow(t, '<span class="tk-badge ' + cls + '">' + txt + '</span>');
        }).join('');
        pintarDots(box);
    }

    function taskRow(t, right) {
        return '<li class="tk-task-row">'
            + '<span class="tk-task-dot" data-cor="' + escapeAttr(t.setor_cor || '#94a3b8') + '"></span>'
            + '<div class="tk-task-info"><span class="tk-task-nome">' + escapeHtml(t.titulo) + '</span>'
            + '<span class="tk-task-setor">' + escapeHtml(t.setor) + (t.prazo ? ' · ' + escapeHtml(t.prazo) : '') + '</span></div>'
            + right + '</li>';
    }

    function pintarDots(box) {
        box.querySelectorAll('.tk-task-dot[data-cor]').forEach(function (dt) { dt.style.backgroundColor = dt.dataset.cor; });
    }

    function renderDashLista(id, itens, comCor) {
        var c = el(id);
        if (!c) return;
        if (!itens.length) { c.innerHTML = '<li class="tk-dash-empty">Sem dados ainda.</li>'; return; }

        // Barra proporcional ao maior valor da lista (comparação visual entre linhas).
        var max = itens.reduce(function (m, i) { return Math.max(m, i.total); }, 0) || 1;

        c.innerHTML = itens.map(function (item) {
            var pct = Math.round((item.total / max) * 100);
            var cor = (comCor && item.cor) ? item.cor : '#5b0000';
            var label = comCor
                ? '<span class="tk-dash-dot" data-cor="' + escapeAttr(cor) + '"></span><span class="tk-dash-nome">' + escapeHtml(item.nome) + '</span>'
                : '<span class="tk-dash-av">' + avatarHtml({ avatar: item.avatar, nome: item.nome }) + '</span><span class="tk-dash-nome">' + escapeHtml(item.nome) + '</span>';
            return '<li class="tk-dash-row">' +
                '<div class="tk-dash-top">' + label + '<span class="tk-dash-count">' + item.total + '</span></div>' +
                '<div class="tk-dash-bar"><span class="tk-dash-fill" data-cor="' + escapeAttr(cor) + '" data-pct="' + pct + '"></span></div>' +
            '</li>';
        }).join('');

        c.querySelectorAll('.tk-dash-dot[data-cor]').forEach(function (d) { d.style.backgroundColor = d.dataset.cor; });
        c.querySelectorAll('.tk-dash-fill[data-cor]').forEach(function (f) {
            f.style.backgroundColor = f.dataset.cor;
            f.style.width = (f.dataset.pct || 0) + '%';
        });
    }

    // ═════════════════════════════════════════════════════════════════════════
    // PRAZO — calendário flutuante inline (salva via AJAX)
    // ═════════════════════════════════════════════════════════════════════════
    var ICON_CAL = '<svg class="icon" viewBox="0 0 256 256" fill="currentColor" aria-hidden="true"><path d="M208,32H184V24a8,8,0,0,0-16,0v8H88V24a8,8,0,0,0-16,0v8H48A16,16,0,0,0,32,48V208a16,16,0,0,0,16,16H208a16,16,0,0,0,16-16V48A16,16,0,0,0,208,32Zm0,176H48V96H208V208Z"/></svg>';
    var ICON_SUB = '<svg class="icon" viewBox="0 0 256 256" fill="currentColor" aria-hidden="true"><rect x="32" y="48" width="24" height="24" rx="4"/><rect x="72" y="54" width="152" height="12" rx="6"/><rect x="32" y="116" width="24" height="24" rx="4"/><rect x="72" y="122" width="152" height="12" rx="6"/><rect x="32" y="184" width="24" height="24" rx="4"/><rect x="72" y="190" width="152" height="12" rx="6"/></svg>';
    var PRAZO_MESES = ['Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho', 'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro'];
    var prazoMenuEl = null, prazoTask = 0, prazoCell = null, prazoView = null;

    function fecharPrazoMenu() { if (prazoMenuEl) { prazoMenuEl.remove(); prazoMenuEl = null; prazoTask = 0; prazoCell = null; prazoView = null; } }

    function pad2(n) { return n < 10 ? '0' + n : '' + n; }
    function isoDe(y, m, d) { return y + '-' + pad2(m + 1) + '-' + pad2(d); }

    function salvarPrazo(iso) {
        var cell = prazoCell, task = prazoTask;
        // Atualiza a célula na hora.
        cell.dataset.prazo = iso || '';
        if (iso) {
            var p = iso.split('-');
            var hoje = new Date(); hoje.setHours(0, 0, 0, 0);
            var atrasada = new Date(iso + 'T00:00:00') < hoje;
            cell.innerHTML = '<span class="mnd-prazo' + (atrasada ? ' is-atrasada' : '') + '">' + ICON_CAL + p[2] + '/' + p[1] + '/' + p[0] + '</span>';
        } else {
            cell.innerHTML = '<span class="mnd-prazo-vazio">' + ICON_CAL + '</span>';
        }
        fecharPrazoMenu();
        postJSON('/api/admin/tarefas/prazo', { id: task, prazo: iso || '' })
            .then(function (d) { if (!d.ok) mpsAlert.toast('error', d.message || 'Erro ao salvar prazo.'); })
            .catch(function () { mpsAlert.toast('error', 'Erro na requisição.'); });
    }

    function renderPrazoCal() {
        var sel = prazoCell.dataset.prazo || '';
        var hoje = new Date();
        var vy = prazoView.y, vm = prazoView.m;
        var primeiro = new Date(vy, vm, 1).getDay();
        var totalDias = new Date(vy, vm + 1, 0).getDate();
        var hojeIso = isoDe(hoje.getFullYear(), hoje.getMonth(), hoje.getDate());
        var dias = '';
        for (var i = 0; i < primeiro; i++) dias += '<button type="button" class="date-picker-day is-outside" tabindex="-1"></button>';
        for (var d = 1; d <= totalDias; d++) {
            var iso = isoDe(vy, vm, d);
            var cls = 'date-picker-day' + (iso === hojeIso ? ' is-today' : '') + (iso === sel ? ' is-selected' : '');
            dias += '<button type="button" class="' + cls + '" data-iso="' + iso + '">' + d + '</button>';
        }
        prazoMenuEl.innerHTML =
            '<div class="date-picker-cal-header">' +
                '<button type="button" class="date-picker-nav" data-nav="-1" aria-label="Mês anterior">‹</button>' +
                '<span class="date-picker-month-label">' + PRAZO_MESES[vm] + ' ' + vy + '</span>' +
                '<button type="button" class="date-picker-nav" data-nav="1" aria-label="Próximo mês">›</button>' +
            '</div>' +
            '<div class="date-picker-weekdays"><span>D</span><span>S</span><span>T</span><span>Q</span><span>Q</span><span>S</span><span>S</span></div>' +
            '<div class="date-picker-days">' + dias + '</div>' +
            '<div class="date-picker-footer">' +
                '<button type="button" class="date-picker-footer-btn" data-action="hoje">Hoje</button>' +
                '<button type="button" class="date-picker-footer-btn" data-action="limpar">Limpar</button>' +
            '</div>';
    }

    function abrirPrazoMenu(taskId, cell) {
        fecharStatusMenu(); fecharRespMenu(); fecharPrazoMenu(); fecharArqMenu();
        prazoTask = taskId; prazoCell = cell;
        var sel = cell.dataset.prazo || '';
        var base = sel ? new Date(sel + 'T00:00:00') : new Date();
        prazoView = { y: base.getFullYear(), m: base.getMonth() };
        prazoMenuEl = document.createElement('div');
        prazoMenuEl.className = 'prazo-menu';
        document.body.appendChild(prazoMenuEl);
        renderPrazoCal();
        var r = cell.getBoundingClientRect();
        var w = prazoMenuEl.offsetWidth || 260;
        prazoMenuEl.style.top = (r.bottom + 6) + 'px';
        prazoMenuEl.style.left = Math.max(8, Math.min(r.left + r.width / 2 - w / 2, window.innerWidth - 8 - w)) + 'px';

        prazoMenuEl.addEventListener('click', function (e) {
            e.stopPropagation();
            var nav = e.target.closest('.date-picker-nav');
            if (nav) {
                prazoView.m += parseInt(nav.dataset.nav, 10);
                if (prazoView.m < 0) { prazoView.m = 11; prazoView.y--; }
                if (prazoView.m > 11) { prazoView.m = 0; prazoView.y++; }
                renderPrazoCal();
                return;
            }
            var day = e.target.closest('.date-picker-day');
            if (day && !day.classList.contains('is-outside')) { salvarPrazo(day.dataset.iso); return; }
            var acao = e.target.closest('[data-action]');
            if (acao) {
                if (acao.dataset.action === 'hoje') { var h = new Date(); salvarPrazo(isoDe(h.getFullYear(), h.getMonth(), h.getDate())); }
                else if (acao.dataset.action === 'limpar') { salvarPrazo(''); }
            }
        });
    }

    // ═════════════════════════════════════════════════════════════════════════
    // ARQUIVOS — popup inline (upload + listar + excluir, via AJAX)
    // ═════════════════════════════════════════════════════════════════════════
    var ICON_CLIP = '<svg class="icon" viewBox="0 0 448 512" fill="currentColor" aria-hidden="true"><path d="M43.246 466.142c-58.43-60.289-57.341-157.511 1.386-217.581L254.392 34c44.316-45.332 116.351-45.336 160.671 0 43.89 44.894 43.943 117.329 0 162.276L232.214 383.128c-29.855 30.537-78.633 30.111-107.982-.998-28.275-29.97-27.368-77.473 1.452-106.953l143.743-146.835c6.182-6.314 16.312-6.422 22.626-.241l22.861 22.379c6.315 6.182 6.422 16.312.241 22.626L171.427 319.927c-4.932 5.045-5.236 13.428-.648 18.292 4.372 4.634 11.245 4.711 15.688.165l182.849-186.851c19.613-20.062 19.613-52.725-.011-72.798-19.189-19.627-49.957-19.637-69.154 0L90.39 293.295c-34.763 35.56-35.299 93.12-1.191 128.313 34.01 35.093 88.985 35.137 123.058.286l172.06-175.999c6.177-6.319 16.307-6.433 22.626-.256l22.877 22.364c6.319 6.177 6.434 16.307.256 22.626l-172.06 175.998c-59.576 60.938-155.943 60.216-214.77-.485z"/></svg>';
    var ICON_FILE = '<svg class="icon" viewBox="0 0 256 256" fill="currentColor" aria-hidden="true"><path d="M213.66,82.34l-56-56A8,8,0,0,0,152,24H56A16,16,0,0,0,40,40V216a16,16,0,0,0,16,16H200a16,16,0,0,0,16-16V88A8,8,0,0,0,213.66,82.34ZM160,51.31,188.69,80H160ZM200,216H56V40h88V88a8,8,0,0,0,8,8h48V216Z"/></svg>';
    var ICON_CHAT = '<svg class="icon" viewBox="0 0 256 256" fill="currentColor" aria-hidden="true"><path d="M128,24A104,104,0,0,0,36.19,178.61L24.83,210.11a16,16,0,0,0,20.68,20.32l32.71-11.9A104,104,0,1,0,128,24Zm0,192a87.6,87.6,0,0,1-44.24-12,8,8,0,0,0-6.16-.86L48,214.85l11.6-30a8,8,0,0,0-.75-7.31A88,88,0,1,1,128,216Z"/></svg>';
    var arqTask = 0, arqCell = null;
    var ARQ_EXT = ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'gif', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'zip', 'txt', 'csv'];
    var ARQ_MAX = 2 * 1024 * 1024;

    function fecharArqMenu() { /* menu antigo removido — modal mpsAlert gerencia o próprio fechamento */ }

    function arqFormatBytes(n) {
        if (n < 1024) return n + ' B';
        if (n < 1048576) return Math.round(n / 1024) + ' KB';
        return (n / 1048576).toFixed(1) + ' MB';
    }

    function atualizarCelulaArq(n) {
        if (!arqCell) return;
        var antes = parseInt(arqCell.dataset.arq || '0', 10);
        arqCell.dataset.arq = n;
        // No offcanvas o valor lista os nomes dos arquivos, então recarrega o
        // painel inteiro (só quando mudou) em vez de virar um clipe com contador.
        if (arqCell.classList.contains('tk-oc-val')) {
            if (antes !== n) { ocDirty = true; ocRecarregar(); }
            return;
        }
        arqCell.innerHTML = '<span class="mnd-arq' + (n > 0 ? ' has-arq' : '') + '">' + ICON_CLIP + (n > 0 ? '<span class="mnd-arq-n">' + n + '</span>' : '') + '</span>';
    }

    function abrirArqMenu(taskId, cell) {
        fecharStatusMenu(); fecharRespMenu(); fecharPrazoMenu();
        arqTask = taskId; arqCell = cell;
        renderArqModal();
    }

    function renderArqModal() {
        fetch('/api/admin/tarefas/anexos?id=' + arqTask)
            .then(function (r) { return r.json(); })
            .then(function (d) {
                var anexos = (d && d.anexos) || [];
                atualizarCelulaArq(anexos.length);
                var lista = anexos.length
                    ? anexos.map(function (a) {
                        return '<div class="arq-item">' +
                            '<span class="arq-ic">' + ICON_FILE + '</span>' +
                            '<a class="arq-nome" href="' + escapeAttr(a.url) + '" target="_blank" rel="noopener" title="' + escapeAttr(a.nome) + '">' + escapeHtml(a.nome) + '</a>' +
                            '<button type="button" class="arq-del" data-anexo="' + a.id + '" title="Excluir" aria-label="Excluir">&times;</button>' +
                        '</div>';
                    }).join('')
                    : '<div class="arq-vazio">Nenhum arquivo ainda.</div>';

                var html =
                    '<h3 class="mps-alert-title">Arquivos</h3>' +
                    '<div class="arq-lista-modal">' + lista + '</div>' +
                    '<div class="mps-dropzone" id="tk-arq-dropzone" tabindex="0">' +
                        ICON_CLIP.replace('class="icon"', 'class="icon mps-dropzone-icon"') +
                        '<p class="mps-dropzone-text">Arraste um arquivo aqui ou <strong>clique para selecionar</strong></p>' +
                        '<span class="mps-dropzone-hint">PDF, imagens, Office, ZIP — até 2 MB</span>' +
                    '</div>' +
                    '<input type="file" id="tk-arq-input" hidden>' +
                    '<div class="mps-alert-actions">' +
                        '<button type="button" class="btn btn-secondary" data-arq-fechar>Fechar</button>' +
                    '</div>';

                mpsAlert.custom(html, {
                    onMount: function (card, close) {
                        var dz = card.querySelector('#tk-arq-dropzone');
                        var fi = card.querySelector('#tk-arq-input');

                        card.querySelector('[data-arq-fechar]').addEventListener('click', close);

                        card.querySelectorAll('.arq-del').forEach(function (b) {
                            b.addEventListener('click', function () {
                                postJSON('/api/admin/tarefas/anexo/excluir', { anexo_id: parseInt(b.dataset.anexo, 10) })
                                    .then(function (r) { if (r.ok) renderArqModal(); else mpsAlert.toast('error', r.message || 'Erro ao excluir.'); })
                                    .catch(function () { mpsAlert.toast('error', 'Erro na requisição.'); });
                            });
                        });

                        dz.addEventListener('click', function () { fi.click(); });
                        dz.addEventListener('keydown', function (e) {
                            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); fi.click(); }
                        });
                        ['dragenter', 'dragover'].forEach(function (evt) {
                            dz.addEventListener(evt, function (e) { e.preventDefault(); dz.classList.add('is-dragover'); });
                        });
                        ['dragleave', 'drop'].forEach(function (evt) {
                            dz.addEventListener(evt, function (e) { e.preventDefault(); dz.classList.remove('is-dragover'); });
                        });
                        dz.addEventListener('drop', function (e) {
                            var f = e.dataTransfer.files && e.dataTransfer.files[0];
                            if (f) arqSelecionar(f);
                        });
                        fi.addEventListener('change', function () {
                            var f = fi.files && fi.files[0];
                            if (f) arqSelecionar(f);
                        });
                    }
                });
            })
            .catch(function () { mpsAlert.error('Erro ao carregar arquivos.'); });
    }

    function arqSelecionar(file) {
        var ext = (file.name.split('.').pop() || '').toLowerCase();
        if (ARQ_EXT.indexOf(ext) === -1) { mpsAlert.error('Tipo de arquivo não permitido.'); return; }
        if (file.size > ARQ_MAX) { mpsAlert.error('O arquivo excede o limite de 2 MB.'); return; }
        arqPreview(file);
    }

    function arqPreview(file) {
        var html =
            '<h3 class="mps-alert-title">Enviar arquivo</h3>' +
            '<div class="mps-file-preview">' +
                ICON_FILE.replace('class="icon"', 'class="icon mps-file-preview-icon"') +
                '<div class="mps-file-preview-info">' +
                    '<span class="mps-file-preview-nome">' + escapeHtml(file.name) + '</span>' +
                    '<span class="mps-file-preview-tamanho">' + escapeHtml(arqFormatBytes(file.size)) + '</span>' +
                '</div>' +
            '</div>' +
            '<div class="mps-alert-actions">' +
                '<button type="button" class="btn btn-secondary" data-arq-voltar>Voltar</button>' +
                '<button type="button" class="btn btn-primary" data-arq-enviar>Enviar</button>' +
            '</div>';

        mpsAlert.custom(html, {
            onMount: function (card) {
                card.querySelector('[data-arq-voltar]').addEventListener('click', renderArqModal);
                card.querySelector('[data-arq-enviar]').addEventListener('click', function () { arqEnviar(file); });
            }
        });
    }

    function arqEnviar(file) {
        mpsAlert.loading('Enviando arquivo...');
        var fd = new FormData();
        fd.append('id', arqTask);
        fd.append('arquivo', file);
        fetch('/api/admin/tarefas/anexo', { method: 'POST', headers: { 'X-CSRF-Token': CSRF }, body: fd })
            .then(function (r) {
                // Resposta pode não ser JSON (413 do servidor, erro PHP): lê como
                // texto para não mascarar a causa num "Erro na requisição" genérico.
                return r.text().then(function (t) {
                    try { return JSON.parse(t); }
                    catch (e) {
                        console.error('[anexo] resposta não-JSON (HTTP ' + r.status + '):', t);
                        return { ok: false, message: r.status === 413
                            ? 'Arquivo grande demais para o servidor.'
                            : 'O servidor respondeu de forma inesperada (HTTP ' + r.status + ').' };
                    }
                });
            })
            .then(function (d) {
                if (d.ok) { mpsAlert.toast('success', 'Arquivo enviado.'); renderArqModal(); }
                else mpsAlert.error(d.message || 'Erro ao enviar arquivo.');
            })
            .catch(function () { mpsAlert.error('Erro na requisição.'); });
    }

    // ═════════════════════════════════════════════════════════════════════════
    // RESPONSÁVEIS — popup inline (multi-seleção, salva via AJAX)
    // ═════════════════════════════════════════════════════════════════════════
    var respMenuEl = null, respMenuTask = 0, respMenuCell = null, respMenuSel = [];

    function fecharRespMenu() {
        if (respMenuEl) { respMenuEl.remove(); respMenuEl = null; respMenuTask = 0; respMenuCell = null; respMenuSel = []; }
    }

    var ICON_MAGNIFIER = '<svg class="icon" viewBox="0 0 256 256" fill="currentColor" aria-hidden="true"><path d="M229.66,218.34l-50.07-50.06a88.11,88.11,0,1,0-11.31,11.31l50.06,50.07a8,8,0,0,0,11.32-11.32ZM40,112a72,72,0,1,1,72,72A72.08,72.08,0,0,1,40,112Z"/></svg>';

    // Só as pessoas do setor da tarefa; filtra pelo texto digitado.
    function renderRespItens(filtro) {
        var box = respMenuEl.querySelector('.resp-lista');
        var setorId = parseInt(respMenuCell.dataset.setorId || '0', 10);
        var doSetor = usuariosDoSetor(setorId);
        if (!doSetor.length) { box.innerHTML = '<div class="resp-vazio">Nenhum usuário neste setor.</div>'; return; }
        var q = (filtro || '').toLowerCase().trim();
        var lista = q ? doSetor.filter(function (u) { return u.nome.toLowerCase().indexOf(q) !== -1; }) : doSetor;
        if (!lista.length) { box.innerHTML = '<div class="resp-vazio">Nenhuma pessoa encontrada.</div>'; return; }
        box.innerHTML = lista.map(function (u) {
            var sel = respMenuSel.indexOf(u.id) !== -1;
            return '<button type="button" class="resp-opt' + (sel ? ' is-sel' : '') + '" data-id="' + u.id + '">' +
                avatarHtml(u) + '<span class="resp-opt-nome">' + escapeHtml(u.nome) + '</span>' +
                '<span class="resp-check">' + (sel ? ICON_CHECK : '') + '</span></button>';
        }).join('');
    }

    function atualizarCelulaResp() {
        respMenuCell.dataset.resp = respMenuSel.join(',');
        var users = respMenuSel.map(function (id) { return USUARIOS_ALL.filter(function (u) { return u.id === id; })[0]; }).filter(Boolean);
        if (!users.length) { respMenuCell.innerHTML = '<span class="mnd-resp-empty">' + ICON_USER + '</span>'; return; }
        var html = '<span class="mnd-resp">';
        users.slice(0, 4).forEach(function (u) { html += '<span class="mnd-resp-av" title="' + escapeAttr(u.nome) + '">' + avatarHtml(u) + '</span>'; });
        if (users.length > 4) html += '<span class="mnd-resp-mais">+' + (users.length - 4) + '</span>';
        respMenuCell.innerHTML = html + '</span>';
    }

    function abrirRespMenu(taskId, cell) {
        fecharStatusMenu();
        fecharRespMenu();
        fecharPrazoMenu();
        fecharArqMenu();
        respMenuTask = taskId;
        respMenuCell = cell;
        respMenuSel = (cell.dataset.resp || '').split(',').filter(Boolean).map(Number);
        respMenuEl = document.createElement('div');
        respMenuEl.className = 'resp-menu';
        respMenuEl.innerHTML =
            '<div class="resp-search"><span class="resp-search-ic">' + ICON_MAGNIFIER + '</span>' +
            '<input type="text" class="resp-input" placeholder="Buscar pessoa..." autocomplete="off"></div>' +
            '<div class="resp-lista"></div>';
        document.body.appendChild(respMenuEl);
        renderRespItens('');
        var r = cell.getBoundingClientRect();
        var w = respMenuEl.offsetWidth || 250;
        respMenuEl.style.top = (r.bottom + 6) + 'px';
        respMenuEl.style.left = Math.max(8, Math.min(r.left + r.width / 2 - w / 2, window.innerWidth - 8 - w)) + 'px';
        respMenuEl.style.maxHeight = (window.innerHeight - (r.bottom + 6) - 8) + 'px';

        var input = respMenuEl.querySelector('.resp-input');
        input.addEventListener('input', function () { renderRespItens(input.value); });

        respMenuEl.addEventListener('click', function (e) {
            e.stopPropagation();
            var opt = e.target.closest('.resp-opt');
            if (!opt) return;
            var uid = parseInt(opt.dataset.id, 10);
            var i = respMenuSel.indexOf(uid);
            if (i === -1) respMenuSel.push(uid); else respMenuSel.splice(i, 1);
            renderRespItens(input.value);
            atualizarCelulaResp();
            postJSON('/api/admin/tarefas/responsaveis', { id: respMenuTask, usuarios: respMenuSel })
                .then(function (d) { if (!d.ok) mpsAlert.toast('error', d.message || 'Erro ao salvar.'); })
                .catch(function () { mpsAlert.toast('error', 'Erro na requisição.'); });
        });
        setTimeout(function () { input.focus(); }, 30);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // STATUS — popup único: modo "escolher" e modo "editar" (inline, estilo Monday)
    // ═════════════════════════════════════════════════════════════════════════
    var statusMenuEl = null, statusMenuTask = 0, statusAnchor = null, statusSortable = null, statusMenuCurrent = 0;
    var ICON_CHECK = '<svg class="icon" viewBox="0 0 256 256" fill="currentColor" aria-hidden="true"><path d="M229.66,77.66l-128,128a8,8,0,0,1-11.32,0l-56-56a8,8,0,0,1,11.32-11.32L96,188.69,218.34,66.34a8,8,0,0,1,11.32,11.32Z"/></svg>';
    var ICON_TRASH = '<svg class="icon" viewBox="0 0 256 256" fill="currentColor" aria-hidden="true"><rect x="48" y="64" width="160" height="16" rx="4"/><rect x="104" y="40" width="48" height="16" rx="4"/><path d="M64 88 H192 L182 216 Q181 224 173 224 H83 Q75 224 74 216 Z"/></svg>';
    var ICON_USER = '<svg class="icon" viewBox="0 0 256 256" fill="currentColor" aria-hidden="true"><path d="M230.92,212c-15.23-26.33-38.7-45.21-66.09-54.16a72,72,0,1,0-73.66,0C63.78,166.78,40.31,185.66,25.08,212a8,8,0,1,0,13.85,8c18.84-32.56,52.14-52,89.07-52s70.23,19.44,89.07,52a8,8,0,1,0,13.85-8ZM72,96a56,56,0,1,1,56,56A56.06,56.06,0,0,1,72,96Z"/></svg>';
    var ICON_GRIP = '<svg class="icon" viewBox="0 0 256 256" fill="currentColor" aria-hidden="true"><circle cx="96" cy="64" r="14"/><circle cx="160" cy="64" r="14"/><circle cx="96" cy="128" r="14"/><circle cx="160" cy="128" r="14"/><circle cx="96" cy="192" r="14"/><circle cx="160" cy="192" r="14"/></svg>';
    var ICON_PENCIL = '<svg class="icon" viewBox="0 0 256 256" fill="currentColor" aria-hidden="true"><path d="M229.66,58.34l-32-32a8,8,0,0,0-11.32,0l-96,96A8,8,0,0,0,88,128v32a8,8,0,0,0,8,8h32a8,8,0,0,0,5.66-2.34l96-96A8,8,0,0,0,229.66,58.34ZM124.69,152H104V131.31l64-64L188.69,88ZM200,76.69,179.31,56,192,43.31,212.69,64ZM224,128v80a16,16,0,0,1-16,16H48a16,16,0,0,1-16-16V48A16,16,0,0,1,48,32h80a8,8,0,0,1,0,16H48V208H208V128a8,8,0,0,1,16,0Z"/></svg>';

    function destruirSortableStatus() { if (statusSortable) { statusSortable.destroy(); statusSortable = null; } }

    function fecharStatusMenu() {
        destruirSortableStatus();
        fecharCorPop();
        fecharOptsPop();
        var tinhaMenu = !!statusMenuEl;
        if (statusMenuEl) { statusMenuEl.remove(); statusMenuEl = null; statusMenuTask = 0; statusAnchor = null; }
        // Alterações de status foram salvas via AJAX; ao fechar, recarrega a lista
        // uma vez para refletir nomes/cores/ordem/novos status em toda a tela.
        if (tinhaMenu && statusDirty) { statusDirty = false; location.reload(); }
    }

    function posicionarMenu() {
        if (!statusMenuEl || !statusAnchor) return;
        var r = statusAnchor.getBoundingClientRect();
        var w = statusMenuEl.offsetWidth || 240;
        var left = r.left + r.width / 2 - w / 2;   // centralizado sob a célula
        statusMenuEl.style.top = (r.bottom + 6) + 'px';
        statusMenuEl.style.left = Math.max(8, Math.min(left, window.innerWidth - 8 - w)) + 'px';
        statusMenuEl.style.maxHeight = (window.innerHeight - (r.bottom + 6) - 8) + 'px';
    }

    var GRID_CORES = [
        '#037f4c', '#00c875', '#9cd326', '#cab641', '#ffcb00', '#784bd1',
        '#a25ddc', '#0086c0', '#579bfc', '#66ccff', '#bb3354', '#e2445c',
        '#ff158a', '#ff5ac4', '#ff642e', '#fdab3d', '#7f5347', '#c4c4c4',
        '#808080', '#333333', '#4eccc6', '#225091', '#401694', '#5559df'
    ];

    function corGridHtml(corAtual) {
        return GRID_CORES.map(function (c) {
            return '<button type="button" class="st-gcolor' + (c.toLowerCase() === (corAtual || '').toLowerCase() ? ' is-active' : '') + '" data-cor="' + c + '"></button>';
        }).join('');
    }

    function statusRowHtml(s) {
        s = s || { id: 0, nome: '', cor: GRID_CORES[1], is_final: 0, ativo: 1 };
        var inativo = s.ativo === 0;
        return '<div class="st-row' + (inativo ? ' is-inactive' : '') + '" data-id="' + (s.id || 0) + '" data-cor="' + escapeAttr(s.cor) + '" data-final="' + (s.is_final ? 1 : 0) + '" data-ativo="' + (inativo ? 0 : 1) + '">' +
            '<button type="button" class="st-drag" tabindex="-1" aria-label="Reordenar">' + ICON_GRIP + '</button>' +
            '<div class="st-nome-box">' +
                '<button type="button" class="st-cor-btn" aria-label="Cor"></button>' +
                '<input type="text" class="st-nome" value="' + escapeAttr(s.nome) + '" placeholder="Nome do status" maxlength="80">' +
            '</div>' +
            '<button type="button" class="st-opts" aria-label="Opções">&#8942;</button>' +
        '</div>';
    }

    function pintarEditor(scope) {
        var root = scope || statusMenuEl;
        root.querySelectorAll('.st-row').forEach(function (row) {
            var btn = row.querySelector('.st-cor-btn');
            if (btn) btn.style.backgroundColor = row.dataset.cor || GRID_CORES[1];
        });
        root.querySelectorAll('.st-gcolor[data-cor]').forEach(function (b) { b.style.backgroundColor = b.dataset.cor; });
    }

    /**
     * Posiciona um popup fixo ancorado num botão, sem vazar da viewport:
     * abre abaixo se couber, senão acima; limita a altura e prende às bordas.
     * alinhar: 'esquerda' (borda esq. do botão) ou 'direita' (borda dir.).
     */
    function posicionarPop(pop, btn, alinhar) {
        var r = btn.getBoundingClientRect();
        var w = pop.offsetWidth;
        var h = pop.offsetHeight;
        var GAP = 6, MARGEM = 8;
        var abaixo = window.innerHeight - r.bottom - GAP - MARGEM;
        var acima  = r.top - GAP - MARGEM;
        var top;
        if (h <= abaixo || abaixo >= acima) {          // cabe embaixo, ou embaixo sobra mais
            top = r.bottom + GAP;
            pop.style.maxHeight = Math.max(120, abaixo) + 'px';
        } else {                                       // vira para cima
            pop.style.maxHeight = Math.max(120, acima) + 'px';
            top = Math.max(MARGEM, r.top - GAP - Math.min(h, acima));
        }
        var left = alinhar === 'direita' ? r.right - w : r.left;
        pop.style.top  = top + 'px';
        pop.style.left = Math.max(MARGEM, Math.min(left, window.innerWidth - MARGEM - w)) + 'px';
    }

    // ── Grade de cores como POPUP flutuante (não acordeão) ─────────────────────
    var corPop = null, corPopRow = null;

    function fecharCorPop() {
        if (corPop) { corPop.remove(); corPop = null; corPopRow = null; }
    }

    function abrirCorPop(corBtn) {
        var row = corBtn.closest('.st-row');
        if (corPop && corPopRow === row) { fecharCorPop(); return; }  // toggle
        fecharCorPop();
        corPopRow = row;
        corPop = document.createElement('div');
        corPop.className = 'st-cor-pop';
        corPop.innerHTML = corGridHtml(row.dataset.cor);
        document.body.appendChild(corPop);
        corPop.querySelectorAll('.st-gcolor[data-cor]').forEach(function (b) { b.style.backgroundColor = b.dataset.cor; });
        posicionarPop(corPop, corBtn, 'esquerda');
        corPop.addEventListener('click', function (e) {
            e.stopPropagation();
            var gc = e.target.closest('.st-gcolor');
            if (!gc || !corPopRow) return;
            corPopRow.dataset.cor = gc.dataset.cor;
            var b = corPopRow.querySelector('.st-cor-btn');
            if (b) b.style.backgroundColor = gc.dataset.cor;
            var rowRef = corPopRow;
            fecharCorPop();
            salvarStatusRow(rowRef);
        });
    }

    // ── Auto-save (sem botão "Aplicar") ────────────────────────────────────────
    var statusDirty = false;

    function indexDaLinha(row) {
        var lista = statusMenuEl.querySelector('.st-edit-lista');
        return Array.prototype.indexOf.call(lista.children, row) + 1;
    }
    function payloadDaLinha(row, ordem) {
        return {
            entidade: 'status',
            id: parseInt(row.dataset.id, 10) || 0,
            nome: (row.querySelector('.st-nome').value || '').trim() || 'Novo status',
            cor: row.dataset.cor || GRID_CORES[1],
            ordem: ordem,
            is_final: row.dataset.final === '1' ? 1 : 0,
            ativo: row.dataset.ativo === '0' ? 0 : 1,
        };
    }
    function salvarStatusRow(row) {
        statusDirty = true;
        return postJSON('/api/admin/tarefas/config/salvar', payloadDaLinha(row, indexDaLinha(row)))
            .then(function (d) { if (d && d.ok && !(parseInt(row.dataset.id, 10) || 0)) row.dataset.id = d.id; return d; })
            .catch(function () { mpsAlert.toast('error', 'Erro ao salvar.'); });
    }
    function salvarOrdemStatus() {
        statusDirty = true;
        var rows = Array.prototype.slice.call(statusMenuEl.querySelectorAll('.st-edit-lista .st-row'));
        var chain = Promise.resolve();
        rows.forEach(function (row, i) { chain = chain.then(function () { return postJSON('/api/admin/tarefas/config/salvar', payloadDaLinha(row, i + 1)); }); });
        return chain;
    }

    // ── Popup de opções (⋮): conclusão, desativar/ativar, excluir ──────────────
    var optsPop = null, optsRow = null;

    function fecharOptsPop() { if (optsPop) { optsPop.remove(); optsPop = null; optsRow = null; } }

    function optsPopHtml() {
        var final = optsRow.dataset.final === '1';
        var ativo = optsRow.dataset.ativo !== '0';
        return '<button type="button" class="st-opt-item" data-act="final"><span class="st-opt-ic">' + (final ? ICON_CHECK : '') + '</span>Marcar como conclusão</button>' +
               '<button type="button" class="st-opt-item" data-act="ativo"><span class="st-opt-ic">' + (ativo ? ICON_CHECK : '') + '</span>' + (ativo ? 'Desativar status' : 'Ativar status') + '</button>' +
               '<div class="st-opt-sep"></div>' +
               '<button type="button" class="st-opt-item st-opt-danger" data-act="del">' + ICON_TRASH + 'Excluir status</button>';
    }

    function abrirOptsPop(optsBtn) {
        var row = optsBtn.closest('.st-row');
        if (optsPop && optsRow === row) { fecharOptsPop(); return; }
        fecharCorPop(); fecharOptsPop();
        optsRow = row;
        optsPop = document.createElement('div');
        optsPop.className = 'st-opts-pop';
        optsPop.innerHTML = optsPopHtml();
        document.body.appendChild(optsPop);
        posicionarPop(optsPop, optsBtn, 'direita');
        optsPop.addEventListener('click', function (e) {
            e.stopPropagation();
            var item = e.target.closest('.st-opt-item');
            if (!item || !optsRow) return;
            var act = item.dataset.act;
            if (act === 'final') { optsRow.dataset.final = optsRow.dataset.final === '1' ? '0' : '1'; optsPop.innerHTML = optsPopHtml(); salvarStatusRow(optsRow); return; }
            if (act === 'ativo') {
                var novo = optsRow.dataset.ativo === '0' ? '1' : '0';
                optsRow.dataset.ativo = novo;
                optsRow.classList.toggle('is-inactive', novo === '0');
                optsPop.innerHTML = optsPopHtml();
                salvarStatusRow(optsRow);
                return;
            }
            if (act === 'del') {
                var id = parseInt(optsRow.dataset.id, 10) || 0;
                var rowRef = optsRow;
                if (!id) { rowRef.remove(); fecharOptsPop(); return; }
                postJSON('/api/admin/tarefas/config/excluir', { entidade: 'status', id: id }).then(function (d) {
                    if (d.ok) { statusDirty = true; rowRef.remove(); fecharOptsPop(); }
                    else mpsAlert.toast('error', d.message || 'Não é possível excluir um status em uso.');
                }).catch(function () { mpsAlert.toast('error', 'Erro na requisição.'); });
            }
        });
    }

    // Modo "escolher": blocos coloridos para trocar o status da tarefa.
    function renderStatusPick() {
        destruirSortableStatus();
        fecharCorPop();
        fecharOptsPop();
        var m = statusMenuEl;
        m.classList.remove('is-edit');
        var html = STATUS_ALL.filter(function (s) { return s.ativo !== 0; }).map(function (s) {
            var atual = s.id === statusMenuCurrent;
            return '<button type="button" class="st-menu-opt' + (atual ? ' is-current' : '') + '" data-status="' + s.id + '" data-cor="' + escapeAttr(s.cor) + '">' +
                '<span class="st-menu-opt-nome">' + escapeHtml(s.nome) + '</span>' + (atual ? '<span class="st-menu-check">' + ICON_CHECK + '</span>' : '') + '</button>';
        }).join('');
        html += '<button type="button" class="st-menu-opt st-menu-vazio' + (statusMenuCurrent === 0 ? ' is-current' : '') + '" data-status="0"><span class="st-menu-opt-nome">Sem status</span>' + (statusMenuCurrent === 0 ? '<span class="st-menu-check">' + ICON_CHECK + '</span>' : '') + '</button>';
        if (IS_GESTOR) html += '<div class="st-menu-sep"></div><button type="button" class="st-menu-edit">' + ICON_PENCIL + 'Editar status</button>';
        m.innerHTML = html;
        m.querySelectorAll('.st-menu-opt[data-cor]').forEach(function (b) {
            b.style.backgroundColor = b.dataset.cor;
            b.style.color = corContraste(b.dataset.cor);
        });
        posicionarMenu();
    }

    // Modo "editar": mesmas etiquetas, mas editáveis e reordenáveis (arrasto).
    function renderStatusEdit() {
        fecharCorPop();
        fecharOptsPop();
        var m = statusMenuEl;
        m.classList.add('is-edit');
        m.innerHTML =
            '<div class="st-edit-head"><button type="button" class="st-back" aria-label="Voltar">‹</button><span>Editar status</span></div>' +
            '<div class="st-edit-lista">' + STATUS_ALL.map(function (s) { return statusRowHtml(s); }).join('') + '</div>' +
            '<button type="button" class="st-add-inline">+ Novo status</button>';
        pintarEditor(m);
        destruirSortableStatus();
        var lista = m.querySelector('.st-edit-lista');
        if (window.Sortable) statusSortable = window.Sortable.create(lista, { handle: '.st-drag', animation: 150, ghostClass: 'st-row-ghost', onEnd: function () { salvarOrdemStatus(); } });
        posicionarMenu();
    }


    function onStatusMenuClick(e) {
        // Cliques dentro do popup nunca devem borbulhar para o handler de "clicar fora"
        // (senão trocar de modo/re-render fecharia o popup).
        e.stopPropagation();
        var m = statusMenuEl;
        var opt = e.target.closest('.st-menu-opt');
        if (opt) {
            var sid = parseInt(opt.dataset.status, 10) || 0, tid = statusMenuTask;
            fecharStatusMenu();
            postJSON('/api/admin/tarefas/status', { id: tid, status_id: sid }).then(function (d) {
                if (!d.ok) { mpsAlert.error(d.message || 'Erro ao alterar status.'); return; }
                carregar();
                if (ocTaskId === tid) ocRecarregar();
            }).catch(function () { mpsAlert.error('Erro na requisição.'); });
            return;
        }
        if (e.target.closest('.st-menu-edit')) { renderStatusEdit(); return; }
        if (e.target.closest('.st-back')) { renderStatusPick(); return; }

        var corBtn = e.target.closest('.st-cor-btn');
        if (corBtn) { fecharOptsPop(); abrirCorPop(corBtn); return; }

        var opts = e.target.closest('.st-opts');
        if (opts) { abrirOptsPop(opts); return; }

        if (e.target.closest('.st-add-inline')) {
            var lista = m.querySelector('.st-edit-lista');
            lista.insertAdjacentHTML('beforeend', statusRowHtml(null));
            pintarEditor(m);
            var row = lista.lastElementChild;
            row.querySelector('.st-nome').value = 'Novo status';
            salvarStatusRow(row).then(function () {
                var inp = row.querySelector('.st-nome'); inp.focus(); inp.select();
            });
            posicionarMenu();
            return;
        }
    }

    // Renomear salva ao sair do campo (change/blur) — auto-save sem "Aplicar".
    function onStatusMenuChange(e) {
        var nome = e.target.closest('.st-nome');
        if (nome) { salvarStatusRow(nome.closest('.st-row')); }
    }

    function abrirStatusMenu(taskId, anchor, curStatus) {
        fecharStatusMenu();
        fecharRespMenu();
        fecharPrazoMenu();
        fecharArqMenu();
        statusMenuTask = taskId;
        statusMenuCurrent = curStatus || 0;
        statusAnchor = anchor;
        statusMenuEl = document.createElement('div');
        statusMenuEl.className = 'st-menu';
        document.body.appendChild(statusMenuEl);
        statusMenuEl.addEventListener('click', onStatusMenuClick);
        statusMenuEl.addEventListener('change', onStatusMenuChange);
        renderStatusPick();
    }

    // Fecha ao clicar fora (ignora cliques em alertas/toasts e na grade de cor flutuante).
    document.addEventListener('click', function (e) {
        if (corPop && !e.target.closest('.st-cor-pop') && !e.target.closest('.st-cor-btn')) fecharCorPop();
        if (optsPop && !e.target.closest('.st-opts-pop') && !e.target.closest('.st-opts')) fecharOptsPop();
        if (statusMenuEl && !statusMenuEl.contains(e.target)
            && !e.target.closest('.mnd-cell-status')
            && !e.target.closest('.mps-alert-overlay')
            && !e.target.closest('.mps-toast-stack')
            && !e.target.closest('.st-cor-pop')
            && !e.target.closest('.st-opts-pop')) {
            fecharStatusMenu();
        }
        if (respMenuEl && !respMenuEl.contains(e.target)
            && !e.target.closest('.mnd-cell-resp')
            && !e.target.closest('.mps-toast-stack')) {
            fecharRespMenu();
        }
        if (prazoMenuEl && !prazoMenuEl.contains(e.target)
            && !e.target.closest('.mnd-cell-prazo')
            && !e.target.closest('.mps-toast-stack')) {
            fecharPrazoMenu();
        }
    });
    window.addEventListener('scroll', function () {
        if (statusMenuEl) { posicionarMenu(); fecharCorPop(); fecharOptsPop(); }
        if (respMenuEl) fecharRespMenu();
        if (prazoMenuEl) fecharPrazoMenu();
    }, true);

    // ═════════════════════════════════════════════════════════════════════════
    // OFFCANVAS DA TAREFA — resumo, comentários (com menções) e histórico
    // ═════════════════════════════════════════════════════════════════════════
    var ocEl        = el('tk-oc');
    var ocOverlay   = el('tk-oc-overlay');
    var ocTaskId    = 0;
    var ocSetorId   = 0;
    var ocCell      = null;      // célula do balão, para atualizar o contador
    var ocCandidatos = [];       // quem pode ser mencionado nesta tarefa
    var ocDirty     = false;     // houve edição? então recarrega a lista ao fechar

    /** Quem enxerga a tarefa: membros do setor + gestores (mesma regra do backend). */
    function ocMencionaveis(setorId) {
        return USUARIOS_ALL.filter(function (u) {
            return u.gestor || (u.setores || []).indexOf(setorId) !== -1;
        });
    }

    function abrirDetalhe(taskId, cell, focarTitulo) {
        fecharStatusMenu(); fecharRespMenu(); fecharPrazoMenu();
        ocTaskId = taskId;
        ocCell   = cell || null;
        ocDirty  = false;

        el('tk-oc-titulo').value       = '';
        el('tk-oc-setor').textContent  = '';
        el('tk-oc-resumo').innerHTML   = '';
        el('tk-cmt-lista').innerHTML   = '';
        el('tk-hist').innerHTML        = '';
        ocTab('comentarios');

        ocOverlay.classList.remove('is-hidden');
        ocEl.classList.add('is-open');
        ocEl.setAttribute('aria-hidden', 'false');
        document.body.classList.add('tk-oc-lock');

        ocBuscarDetalhe(function () {
            if (focarTitulo) {
                var ti = el('tk-oc-titulo');
                ti.focus();
                ti.setSelectionRange(ti.value.length, ti.value.length);
            }
        });
    }

    function ocBuscarDetalhe(depois) {
        var alvo = ocTaskId;
        fetch('/api/admin/tarefas/detalhe?id=' + alvo)
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (ocTaskId !== alvo) return;   // o painel já mudou de tarefa
                if (!d.ok) { mpsAlert.toast('error', d.message || 'Erro ao carregar a tarefa.'); fecharDetalhe(); return; }
                ocSetorId    = d.tarefa.setor_id;
                ocCandidatos = ocMencionaveis(ocSetorId);
                ocRender(d);
                ocMarcarLido();          // abrir a tarefa já zera o badge na lista
                if (typeof depois === 'function') depois();
            })
            .catch(function () { mpsAlert.toast('error', 'Erro na requisição.'); fecharDetalhe(); });
    }

    /** Recarrega o painel (usado após edições que mexem no histórico). */
    function ocRecarregar() {
        if (!ocTaskId || !ocEl.classList.contains('is-open')) return;
        ocBuscarDetalhe();
    }

    function fecharDetalhe() {
        var precisaRecarregar = ocDirty;
        ocEl.classList.remove('is-open');
        ocEl.setAttribute('aria-hidden', 'true');
        ocOverlay.classList.add('is-hidden');
        document.body.classList.remove('tk-oc-lock');
        ocMencaoFechar();
        ocTaskId = 0; ocCell = null; ocDirty = false;
        if (precisaRecarregar) carregar();
    }

    function ocRender(d) {
        var t = d.tarefa;

        var ti = el('tk-oc-titulo');
        if (document.activeElement !== ti) ti.value = t.titulo || '';

        var setorTag = el('tk-oc-setor');
        setorTag.textContent = t.setor_nome || 'Sem setor';
        setorTag.style.backgroundColor = hexToRgba(t.setor_cor, 0.16);
        setorTag.style.color = t.setor_cor;

        // ── Campos editáveis ──────────────────────────────────────────────────
        // Cada valor carrega os mesmos data-* das células da tabela, então os
        // popups de status/responsável/prazo/arquivo são reaproveitados inteiros.
        var linhas = [];

        linhas.push(ocLinha('Status',
            'data-oc-edit="status" data-status-id="' + t.status_id + '"',
            t.status_nome
                ? '<span class="tarefa-pill tarefa-pill-status" data-cor="' + escapeAttr(t.status_cor) + '">' + escapeHtml(t.status_nome) + '</span>'
                : '<span class="tk-oc-vazio">Definir status</span>'));

        linhas.push(ocLinha('Responsáveis',
            'data-oc-edit="resp" data-setor-id="' + t.setor_id + '" data-resp="' +
                escapeAttr(d.responsaveis.map(function (r) { return r.id; }).join(',')) + '"',
            d.responsaveis.length
                ? '<span class="mnd-resp">' + d.responsaveis.map(function (r) {
                      return '<span class="mnd-resp-av" title="' + escapeAttr(r.username) + '">' +
                          avatarHtml({ avatar: r.avatar, nome: r.username }) + '</span>';
                  }).join('') + '</span>'
                : '<span class="tk-oc-vazio">Atribuir responsável</span>'));

        linhas.push(ocLinha('Prazo',
            'data-oc-edit="prazo" data-prazo="' + escapeAttr(t.prazo || '') + '"',
            t.prazo_label
                ? '<span class="mnd-prazo">' + escapeHtml(t.prazo_label) + '</span>'
                : '<span class="tk-oc-vazio">Definir prazo</span>'));

        linhas.push(ocLinha('Arquivos',
            'data-oc-edit="arq" data-arq="' + d.anexos.length + '"',
            d.anexos.length
                ? '<span class="tk-oc-anexos">' + d.anexos.map(function (a) {
                      return '<span class="tk-oc-anexo">' + escapeHtml(a.nome) + '</span>';
                  }).join('') + '</span>'
                : '<span class="tk-oc-vazio">Anexar arquivo</span>'));

        var criado = escapeHtml(t.criado_em || '');
        if (t.criado_por) criado += ' por ' + escapeHtml(t.criado_por);
        linhas.push(ocLinhaLeitura('Criada em', criado || '<span class="tk-oc-vazio">Sem registro</span>'));

        if (t.concluida_em) {
            linhas.push(ocLinhaLeitura('Concluída em', escapeHtml(t.concluida_em)));
        }

        var html = '<dl class="tk-oc-info">' + linhas.join('') + '</dl>' +
            '<div class="tk-oc-desc">' +
                '<label class="tk-oc-desc-label" for="tk-oc-descricao">Descrição</label>' +
                '<textarea class="tk-oc-desc-input" id="tk-oc-descricao" rows="3" ' +
                    'placeholder="Descreva a tarefa"></textarea>' +
            '</div>';

        el('tk-oc-resumo').innerHTML = html;
        el('tk-oc-descricao').value = t.descricao || '';
        aplicarCores(el('tk-oc-resumo'));

        ocRenderComentarios(d.comentarios);
        ocRenderHistorico(d.historico);
    }

    /** Linha com valor clicável (abre o popup de edição correspondente). */
    function ocLinha(rotulo, attrs, valorHtml) {
        return '<div class="tk-oc-linha">' +
            '<dt class="tk-oc-dt">' + escapeHtml(rotulo) + '</dt>' +
            '<dd class="tk-oc-dd"><div class="tk-oc-val" ' + attrs + ' role="button" tabindex="0">' +
                valorHtml + '</div></dd>' +
        '</div>';
    }

    /** Linha só de leitura (datas de criação/conclusão). */
    function ocLinhaLeitura(rotulo, valorHtml) {
        return '<div class="tk-oc-linha">' +
            '<dt class="tk-oc-dt">' + escapeHtml(rotulo) + '</dt>' +
            '<dd class="tk-oc-dd tk-oc-dd-ro">' + valorHtml + '</dd>' +
        '</div>';
    }

    function ocRenderComentarios(lista) {
        var box = el('tk-cmt-lista');
        if (!lista || !lista.length) {
            box.innerHTML = '<div class="tk-oc-empty">Nenhum comentário ainda. Seja o primeiro a comentar.</div>';
            return;
        }
        box.innerHTML = lista.map(ocComentarioHtml).join('');
        box.scrollTop = box.scrollHeight;
    }

    function ocComentarioHtml(c) {
        return '<article class="tk-cmt">' +
            '<span class="tk-cmt-av">' + avatarHtml({ avatar: c.avatar, nome: c.username }) + '</span>' +
            '<div class="tk-cmt-corpo">' +
                '<header class="tk-cmt-head">' +
                    '<strong class="tk-cmt-autor">' + escapeHtml(c.username || 'Usuário') + '</strong>' +
                    '<span class="tk-cmt-data">' + escapeHtml(c.criado_em || '') + '</span>' +
                '</header>' +
                '<div class="tk-cmt-texto">' + ocTextoComMencoes(c.mensagem, c.mencoes) + '</div>' +
            '</div>' +
        '</article>';
    }

    /** Escapa o texto e destaca as menções confirmadas pelo backend. */
    function ocTextoComMencoes(texto, mencoes) {
        var html = escapeHtml(texto || '');
        (mencoes || []).forEach(function (nome) {
            var alvo = escapeHtml('@' + nome);
            html = html.split(alvo).join('<span class="tk-mencao">' + alvo + '</span>');
        });
        return html.replace(/\n/g, '<br>');
    }

    function ocRenderHistorico(lista) {
        var box = el('tk-hist');
        if (!lista || !lista.length) {
            box.innerHTML = '<li class="tk-oc-empty">Nenhum registro no histórico.</li>';
            return;
        }
        box.innerHTML = lista.map(function (h) {
            return '<li class="tk-hist-item">' +
                '<span class="tk-hist-av">' + avatarHtml({ avatar: h.avatar, nome: h.usuario }) + '</span>' +
                '<div class="tk-hist-corpo">' +
                    '<p class="tk-hist-txt"><strong>' + escapeHtml(h.usuario) + '</strong> ' + escapeHtml(h.frase) + '</p>' +
                    '<span class="tk-hist-data">' + escapeHtml(h.criado_em) + '</span>' +
                '</div>' +
            '</li>';
        }).join('');
    }

    // ── Abas do offcanvas ─────────────────────────────────────────────────────
    function ocTab(nome) {
        ocEl.querySelectorAll('.tk-oc-tab').forEach(function (b) {
            b.classList.toggle('is-active', b.dataset.ocTab === nome);
        });
        ocEl.querySelectorAll('.tk-oc-pane').forEach(function (p) {
            p.classList.toggle('is-hidden', p.dataset.ocPane !== nome);
        });
        el('tk-oc-composer').classList.toggle('is-hidden', nome !== 'comentarios');
    }

    /**
     * "+ Adicionar tarefa": cria a linha em branco já no setor/status do grupo
     * e abre o offcanvas para preencher (sem modal, estilo Monday).
     */
    function criarTarefaRapida(setorId, statusId, opts) {
        opts = opts || {};
        if (!setorId) { escolherSetorEcriar(statusId); return; }
        postJSON('/api/admin/tarefas/criar-rapido', { setor_id: setorId, status_id: statusId || 0, depois_de_id: opts.depoisDeId || 0 })
            .then(function (d) {
                if (!d.ok) { mpsAlert.toast('error', d.message || 'Erro ao criar a tarefa.'); return; }
                carregar(function () {
                    var linha = lista.querySelector('.mnd-row[data-id="' + d.id + '"]');
                    abrirDetalhe(d.id, linha ? linha.querySelector('.mnd-cell-msg') : null, true);
                });
            })
            .catch(function () { mpsAlert.toast('error', 'Erro na requisição.'); });
    }

    /** Em "Todas" não há setor definido pela aba, então pergunta antes de criar. */
    function escolherSetorEcriar(statusId) {
        if (!SETORES.length) { mpsAlert.error('Crie um setor antes de adicionar tarefas.'); return; }
        if (SETORES.length === 1) { criarTarefaRapida(SETORES[0].id, statusId); return; }

        var html = '<h3 class="mps-alert-title">Em qual setor?</h3>' +
            '<div class="tk-setor-pick">' +
            SETORES.map(function (s) {
                return '<button type="button" class="tk-setor-opt" data-setor="' + s.id + '">' +
                    '<span class="tk-setor-dot" data-cor="' + escapeAttr(s.cor || '#94a3b8') + '"></span>' +
                    escapeHtml(s.nome) + '</button>';
            }).join('') +
            '</div>' +
            '<div class="mps-alert-actions"><button type="button" class="btn btn-secondary" data-setor-cancelar>Cancelar</button></div>';

        mpsAlert.custom(html, {
            onMount: function (card, close) {
                card.querySelectorAll('.tk-setor-dot').forEach(function (d) {
                    d.style.backgroundColor = d.dataset.cor;
                });
                card.querySelector('[data-setor-cancelar]').addEventListener('click', close);
                card.querySelectorAll('.tk-setor-opt').forEach(function (b) {
                    b.addEventListener('click', function () {
                        close();
                        criarTarefaRapida(parseInt(b.dataset.setor, 10), statusId);
                    });
                });
            }
        });
    }

    // ── Edição inline dentro do offcanvas ─────────────────────────────────────
    function ocSalvarCampo(campo, valor) {
        if (!ocTaskId) return;
        ocDirty = true;
        postJSON('/api/admin/tarefas/campo', { id: ocTaskId, campo: campo, valor: valor })
            .then(function (d) { if (!d.ok) mpsAlert.toast('error', d.message || 'Erro ao salvar.'); })
            .catch(function () { mpsAlert.toast('error', 'Erro na requisição.'); });
    }

    /** Salva só quando o valor realmente mudou desde que o campo ganhou foco. */
    function ocLigarCampoTexto(input, campo) {
        var original = '';
        input.addEventListener('focus', function () { original = input.value; });
        input.addEventListener('blur', function () {
            var v = input.value.trim();
            if (v === original.trim()) return;
            original = v;
            ocSalvarCampo(campo, v);
        });
    }

    if (ocEl) {
        ocEl.querySelectorAll('.tk-oc-tab').forEach(function (b) {
            b.addEventListener('click', function () { ocTab(b.dataset.ocTab); });
        });

        ocLigarCampoTexto(el('tk-oc-titulo'), 'titulo');
        el('tk-oc-titulo').addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); el('tk-oc-titulo').blur(); }
        });

        // A descrição é recriada a cada render, então usa delegação.
        el('tk-oc-resumo').addEventListener('focusout', function (e) {
            var ta = e.target.closest('#tk-oc-descricao');
            if (ta && ta.value.trim() !== (ta.dataset.original || '')) {
                ta.dataset.original = ta.value.trim();
                ocSalvarCampo('descricao', ta.value.trim());
            }
        });
        el('tk-oc-resumo').addEventListener('focusin', function (e) {
            var ta = e.target.closest('#tk-oc-descricao');
            if (ta) ta.dataset.original = ta.value.trim();
        });

        // Valores clicáveis reaproveitam os popups já existentes da tabela.
        el('tk-oc-resumo').addEventListener('click', function (e) {
            var val = e.target.closest('.tk-oc-val');
            if (!val || !ocTaskId) return;
            // Impede que o handler de "clicar fora" (no document) feche o popup
            // que estamos abrindo agora.
            e.stopPropagation();
            ocDirty = true;
            switch (val.dataset.ocEdit) {
                case 'status': abrirStatusMenu(ocTaskId, val, parseInt(val.dataset.statusId || '0', 10)); break;
                case 'resp':   abrirRespMenu(ocTaskId, val); break;
                case 'prazo':  abrirPrazoMenu(ocTaskId, val); break;
                case 'arq':    abrirArqMenu(ocTaskId, val); break;
            }
        });
        el('tk-oc-close').addEventListener('click', fecharDetalhe);
        ocOverlay.addEventListener('click', fecharDetalhe);
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && ocEl.classList.contains('is-open')) {
                if (mencaoAberta) { ocMencaoFechar(); return; }
                fecharDetalhe();
            }
        });
    }

    // ── Composer: menções com autocomplete ────────────────────────────────────
    var mencaoDrop   = el('tk-mencao-drop');
    var mencaoAberta = false;
    var mencaoInicio = -1;   // índice do '@' que abriu o autocomplete

    function ocMencaoFechar() {
        if (!mencaoDrop) return;
        mencaoDrop.classList.add('is-hidden');
        mencaoDrop.innerHTML = '';
        mencaoAberta = false;
        mencaoInicio = -1;
    }

    function ocMencaoDetectar() {
        var ta = el('tk-cmt-input');
        var pos = ta.selectionStart;
        var m = ta.value.slice(0, pos).match(/@([^@\s]*(?:\s[^@\s]*)?)$/);
        if (!m) { ocMencaoFechar(); return; }

        var termo = m[1].toLowerCase();
        var lista = ocCandidatos.filter(function (u) {
            return termo === '' || (u.nome || '').toLowerCase().indexOf(termo) !== -1;
        }).slice(0, 6);

        if (!lista.length) { ocMencaoFechar(); return; }

        mencaoInicio = pos - m[0].length;
        mencaoDrop.innerHTML = lista.map(function (u, i) {
            return '<button type="button" class="tk-mencao-opt' + (i === 0 ? ' is-active' : '') + '" data-uid="' + u.id + '">' +
                avatarHtml(u) + '<span>' + escapeHtml(u.nome) + '</span></button>';
        }).join('');
        mencaoDrop.classList.remove('is-hidden');
        mencaoAberta = true;
    }

    function ocMencaoInserir(uid) {
        var u = ocCandidatos.filter(function (x) { return x.id === uid; })[0];
        if (!u || mencaoInicio < 0) return;
        var ta = el('tk-cmt-input');
        var antes  = ta.value.slice(0, mencaoInicio);
        var depois = ta.value.slice(ta.selectionStart);
        var texto  = '@' + u.nome + ' ';
        ta.value = antes + texto + depois;
        var caret = (antes + texto).length;
        ocMencaoFechar();
        ta.focus();
        ta.setSelectionRange(caret, caret);
    }

    function ocMencaoMover(passo) {
        var opts = Array.prototype.slice.call(mencaoDrop.querySelectorAll('.tk-mencao-opt'));
        if (!opts.length) return;
        var i = opts.findIndex(function (o) { return o.classList.contains('is-active'); });
        opts.forEach(function (o) { o.classList.remove('is-active'); });
        var novo = (i + passo + opts.length) % opts.length;
        opts[novo].classList.add('is-active');
        opts[novo].scrollIntoView({ block: 'nearest' });
    }

    /** Casa cada @Nome escrito no texto com um usuário mencionável. */
    function ocColetarMencoes(texto) {
        var alvo = (texto || '').toLowerCase();
        var ids  = [];
        ocCandidatos.forEach(function (u) {
            var nome = (u.nome || '').trim().toLowerCase();
            if (nome && alvo.indexOf('@' + nome) !== -1) ids.push(u.id);
        });
        return ids;
    }

    function ocEnviarComentario() {
        var ta = el('tk-cmt-input');
        var msg = ta.value.trim();
        if (!msg || !ocTaskId) return;

        var btn = el('tk-cmt-enviar');
        btn.disabled = true;

        postJSON('/api/admin/tarefas/comentar', {
            id: ocTaskId,
            mensagem: msg,
            mencoes: ocColetarMencoes(msg),
        }).then(function (d) {
            btn.disabled = false;
            if (!d.ok) { mpsAlert.toast('error', d.message || 'Erro ao comentar.'); return; }

            ta.value = '';
            var box = el('tk-cmt-lista');
            var vazio = box.querySelector('.tk-oc-empty');
            if (vazio) box.innerHTML = '';
            box.insertAdjacentHTML('beforeend', ocComentarioHtml(d.comentario));
            box.scrollTop = box.scrollHeight;
            ocIncrementarContador();
        }).catch(function () {
            btn.disabled = false;
            mpsAlert.toast('error', 'Erro na requisição.');
        });
    }

    /** Comentário próprio nunca conta como "não lido", então o badge não volta. */
    function ocIncrementarContador() {
        if (!ocCell) return;
        ocCell.dataset.msg = (parseInt(ocCell.dataset.msg || '0', 10) || 0) + 1;
        ocMarcarLido();
    }

    /** Zera o badge de não lidos da linha (a leitura já foi gravada no servidor). */
    function ocMarcarLido() {
        if (!ocCell) return;
        ocCell.dataset.novo = 0;
        ocCell.innerHTML = '<span class="mnd-msg' +
            (parseInt(ocCell.dataset.msg || '0', 10) > 0 ? ' has-msg' : '') + '">' + ICON_CHAT + '</span>';
    }

    if (el('tk-cmt-input')) {
        var taCmt = el('tk-cmt-input');
        taCmt.addEventListener('input', ocMencaoDetectar);
        taCmt.addEventListener('click', ocMencaoDetectar);
        taCmt.addEventListener('keydown', function (e) {
            if (mencaoAberta) {
                if (e.key === 'ArrowDown') { e.preventDefault(); ocMencaoMover(1); return; }
                if (e.key === 'ArrowUp')   { e.preventDefault(); ocMencaoMover(-1); return; }
                if (e.key === 'Enter' || e.key === 'Tab') {
                    var ativo = mencaoDrop.querySelector('.tk-mencao-opt.is-active');
                    if (ativo) { e.preventDefault(); ocMencaoInserir(parseInt(ativo.dataset.uid, 10)); return; }
                }
                if (e.key === 'Escape') { e.preventDefault(); ocMencaoFechar(); return; }
            }
            if (e.key === 'Enter' && (e.ctrlKey || e.metaKey)) { e.preventDefault(); ocEnviarComentario(); }
        });
        taCmt.addEventListener('blur', function () { setTimeout(ocMencaoFechar, 150); });

        mencaoDrop.addEventListener('mousedown', function (e) {
            var opt = e.target.closest('.tk-mencao-opt');
            if (opt) { e.preventDefault(); ocMencaoInserir(parseInt(opt.dataset.uid, 10)); }
        });

        el('tk-cmt-enviar').addEventListener('click', ocEnviarComentario);
    }

    // ═════════════════════════════════════════════════════════════════════════
    // MODAL: NOVO SETOR (só gestor) — .modal padrão + color picker próprio
    // ═════════════════════════════════════════════════════════════════════════
    (function initNovoSetor() {
        var btn = el('btn-novo-setor');
        if (!btn) return;

        var USUARIOS = USUARIOS_ALL;   // dados/helpers (avatarHtml) vêm do escopo pai

        // ── Color picker próprio (HSV) ─────────────────────────────────────────
        function clamp(v, a, b) { return Math.max(a, Math.min(b, v)); }
        function hsvToRgb(h, s, v) {
            var c = v * s, x = c * (1 - Math.abs((h / 60) % 2 - 1)), m = v - c, r, g, b;
            if (h < 60) { r = c; g = x; b = 0; } else if (h < 120) { r = x; g = c; b = 0; }
            else if (h < 180) { r = 0; g = c; b = x; } else if (h < 240) { r = 0; g = x; b = c; }
            else if (h < 300) { r = x; g = 0; b = c; } else { r = c; g = 0; b = x; }
            return [Math.round((r + m) * 255), Math.round((g + m) * 255), Math.round((b + m) * 255)];
        }
        function rgbToHex(r, g, b) { return '#' + [r, g, b].map(function (n) { return ('0' + n.toString(16)).slice(-2); }).join(''); }
        function hexToRgb(hex) {
            var h = hex.replace('#', '');
            if (h.length === 3) h = h.split('').map(function (c) { return c + c; }).join('');
            return [parseInt(h.substr(0, 2), 16), parseInt(h.substr(2, 2), 16), parseInt(h.substr(4, 2), 16)];
        }
        function rgbToHsv(r, g, b) {
            r /= 255; g /= 255; b /= 255;
            var max = Math.max(r, g, b), min = Math.min(r, g, b), d = max - min, h = 0, s = max === 0 ? 0 : d / max, v = max;
            if (d !== 0) {
                if (max === r) h = ((g - b) / d) % 6;
                else if (max === g) h = (b - r) / d + 2;
                else h = (r - g) / d + 4;
                h *= 60; if (h < 0) h += 360;
            }
            return [h, s, v];
        }
        function dragify(elm, onMove) {
            elm.addEventListener('pointerdown', function (e) {
                e.preventDefault();
                onMove(e);
                function mv(ev) { onMove(ev); }
                function up() { document.removeEventListener('pointermove', mv); document.removeEventListener('pointerup', up); }
                document.addEventListener('pointermove', mv);
                document.addEventListener('pointerup', up);
            });
        }

        var H = 0, S = 1, V = 1;
        var cpTrigger = el('ns-cp-trigger'), cpPop = el('ns-cp-pop'),
            cpSv = el('ns-cp-sv'), cpSvH = el('ns-cp-sv-handle'),
            cpHue = el('ns-cp-hue'), cpHueH = el('ns-cp-hue-handle'),
            cpHexInput = el('ns-cp-hexinput'), corHidden = el('ns-cor'), cpRoot = el('ns-colorpicker');
        var presets = Array.prototype.slice.call(cpRoot.querySelectorAll('.colorpicker-preset'));

        // Pinta os swatches de preset a partir do data-cor (sem style inline no HTML).
        presets.forEach(function (b) { b.style.backgroundColor = b.dataset.cor; });

        function cpSetHex(hex) {
            if (!/^#?[0-9a-fA-F]{6}$/.test(hex)) return false;
            if (hex.charAt(0) !== '#') hex = '#' + hex;
            var rgb = hexToRgb(hex), hsv = rgbToHsv(rgb[0], rgb[1], rgb[2]);
            H = hsv[0]; S = hsv[1]; V = hsv[2];
            return true;
        }
        // Atualiza só os visuais internos do popup (SV/matiz/handles/hex) a partir de H,S,V.
        function cpRenderPicker() {
            var rgb = hsvToRgb(H, S, V);
            cpHexInput.value = rgbToHex(rgb[0], rgb[1], rgb[2]).toUpperCase();
            var hue = hsvToRgb(H, 1, 1);
            cpSv.style.backgroundColor = rgbToHex(hue[0], hue[1], hue[2]);
            cpSvH.style.left = (S * 100) + '%';
            cpSvH.style.top = ((1 - V) * 100) + '%';
            cpHueH.style.left = ((H / 360) * 100) + '%';
        }
        function marcarPreset(btn) {
            presets.forEach(function (b) { b.classList.toggle('is-active', b === btn); });
            cpTrigger.classList.remove('is-active');
            cpTrigger.style.background = '';
        }
        function marcarCustom() {
            presets.forEach(function (b) { b.classList.remove('is-active'); });
            cpTrigger.classList.add('is-active');
            cpTrigger.style.background = corHidden.value;
        }
        // Cor vinda do popup (arraste/hex) — vira "personalizada".
        function onCustomChange(fromHexInput) {
            var rgb = hsvToRgb(H, S, V), hex = rgbToHex(rgb[0], rgb[1], rgb[2]);
            corHidden.value = hex;
            if (!fromHexInput) cpHexInput.value = hex.toUpperCase();
            var hue = hsvToRgb(H, 1, 1);
            cpSv.style.backgroundColor = rgbToHex(hue[0], hue[1], hue[2]);
            cpSvH.style.left = (S * 100) + '%';
            cpSvH.style.top = ((1 - V) * 100) + '%';
            cpHueH.style.left = ((H / 360) * 100) + '%';
            marcarCustom();
        }

        // Clique num preset: seleciona direto e fecha o popup.
        presets.forEach(function (b) {
            b.addEventListener('click', function () {
                corHidden.value = b.dataset.cor;
                cpSetHex(b.dataset.cor);
                cpRenderPicker();
                marcarPreset(b);
                cpPop.classList.add('is-hidden');
            });
        });

        function cpPosition() {
            var r = cpTrigger.getBoundingClientRect();
            var w = cpPop.offsetWidth || 240;
            var left = r.left;
            if (left + w > window.innerWidth - 8) left = window.innerWidth - 8 - w;
            cpPop.style.top = (r.bottom + 6) + 'px';
            cpPop.style.left = Math.max(8, left) + 'px';
        }
        // Botão "Personalizar": abre o popup do seletor livre.
        cpTrigger.addEventListener('click', function () {
            cpPop.classList.toggle('is-hidden');
            if (!cpPop.classList.contains('is-hidden')) { cpRenderPicker(); cpPosition(); }
        });
        window.addEventListener('scroll', function () { if (!cpPop.classList.contains('is-hidden')) cpPosition(); }, true);
        window.addEventListener('resize', function () { if (!cpPop.classList.contains('is-hidden')) cpPosition(); });
        dragify(cpSv, function (e) {
            var r = cpSv.getBoundingClientRect();
            S = clamp((e.clientX - r.left) / r.width, 0, 1);
            V = 1 - clamp((e.clientY - r.top) / r.height, 0, 1);
            onCustomChange();
        });
        dragify(cpHue, function (e) {
            var r = cpHue.getBoundingClientRect();
            H = clamp((e.clientX - r.left) / r.width, 0, 1) * 360;
            onCustomChange();
        });
        cpHexInput.addEventListener('input', function () { if (cpSetHex(cpHexInput.value.trim())) onCustomChange(true); });

        // Estado inicial: primeira cor pronta selecionada.
        function cpReset() {
            corHidden.value = presets[0].dataset.cor;
            cpSetHex(presets[0].dataset.cor);
            cpRenderPicker();
            marcarPreset(presets[0]);
            cpPop.classList.add('is-hidden');
        }
        // Aplica uma cor específica (usado ao editar um setor existente).
        function cpAplicarCor(hex) {
            hex = hex || presets[0].dataset.cor;
            corHidden.value = hex;
            cpSetHex(hex);
            cpRenderPicker();
            var preset = presets.filter(function (b) { return (b.dataset.cor || '').toLowerCase() === hex.toLowerCase(); })[0];
            if (preset) marcarPreset(preset); else marcarCustom();
            cpPop.classList.add('is-hidden');
        }
        cpReset();

        // ── Autocomplete de usuários ───────────────────────────────────────────
        var selecionados = [];
        var uInput = el('ns-user-input'), uDrop = el('ns-user-drop'), uChips = el('ns-user-chips');

        function disponiveis() {
            var ids = selecionados.map(function (s) { return s.id; });
            var q = (uInput.value || '').toLowerCase();
            return USUARIOS.filter(function (u) { return ids.indexOf(u.id) === -1 && u.nome.toLowerCase().indexOf(q) !== -1; });
        }
        function uDropPosition() {
            var r = uInput.getBoundingClientRect();
            uDrop.style.top = (r.bottom + 4) + 'px';
            uDrop.style.left = r.left + 'px';
            uDrop.style.width = r.width + 'px';
        }
        function renderDrop() {
            var lista = disponiveis();
            if (!lista.length) { uDrop.classList.add('is-hidden'); uDrop.innerHTML = ''; return; }
            uDrop.innerHTML = lista.slice(0, 8).map(function (u) {
                return '<div class="tarefa-userpicker-opt" data-id="' + u.id + '">' + avatarHtml(u) + '<span>' + escapeHtml(u.nome) + '</span></div>';
            }).join('');
            uDrop.classList.remove('is-hidden');
            uDropPosition();
        }
        // Reposiciona o dropdown flutuante enquanto aberto.
        window.addEventListener('scroll', function () { if (!uDrop.classList.contains('is-hidden')) uDropPosition(); }, true);
        window.addEventListener('resize', function () { if (!uDrop.classList.contains('is-hidden')) uDropPosition(); });
        function renderChips() {
            uChips.innerHTML = selecionados.map(function (u) {
                return '<span class="tarefa-userpicker-chip">' + avatarHtml(u) +
                    '<span class="tarefa-userpicker-chip-nome">' + escapeHtml(u.nome) + '</span>' +
                    '<button type="button" class="tarefa-userpicker-chip-x" data-id="' + u.id + '" aria-label="Remover">&times;</button></span>';
            }).join('');
        }
        uInput.addEventListener('focus', renderDrop);
        uInput.addEventListener('input', renderDrop);
        uDrop.addEventListener('click', function (e) {
            var opt = e.target.closest('.tarefa-userpicker-opt');
            if (!opt) return;
            var u = USUARIOS.filter(function (x) { return x.id === parseInt(opt.dataset.id, 10); })[0];
            if (u) { selecionados.push(u); renderChips(); uInput.value = ''; uDrop.classList.add('is-hidden'); }
        });
        uChips.addEventListener('click', function (e) {
            var x = e.target.closest('.tarefa-userpicker-chip-x');
            if (!x) return;
            var id = parseInt(x.dataset.id, 10);
            selecionados = selecionados.filter(function (s) { return s.id !== id; });
            renderChips();
        });

        // Fecha o dropdown de usuários e o popover de cor ao clicar fora deles.
        document.addEventListener('click', function (e) {
            if (!e.target.closest('.tarefa-userpicker')) uDrop.classList.add('is-hidden');
            if (!e.target.closest('#ns-colorpicker')) cpPop.classList.add('is-hidden');
        });

        // Setores (id, nome, cor) para o modo edição.
        var SETORES_ALL = [];
        try { var _ss = el('tarefas-setores-json'); if (_ss) SETORES_ALL = JSON.parse(_ss.textContent || '[]'); } catch (e) { SETORES_ALL = []; }

        var erro = el('ns-erro'), nome = el('ns-nome'), idHidden = el('ns-id'), btnExcluir = el('ns-excluir');

        // ── Novo setor (reseta tudo) ───────────────────────────────────────────
        btn.addEventListener('click', function () {
            idHidden.value = '';
            el('modal-setor-titulo').textContent = 'Novo setor';
            btnExcluir.classList.add('is-hidden');
            nome.value = '';
            erro.classList.add('is-hidden');
            selecionados = [];
            renderChips();
            uInput.value = '';
            uDrop.classList.add('is-hidden');
            cpReset();
            abrir('modal-setor');
            setTimeout(function () { nome.focus(); }, 50);
        });

        // ── Editar setor existente ─────────────────────────────────────────────
        function abrirEditarSetor(id) {
            var s = SETORES_ALL.filter(function (x) { return x.id === id; })[0];
            if (!s) return;
            idHidden.value = s.id;
            el('modal-setor-titulo').textContent = 'Editar setor';
            btnExcluir.classList.remove('is-hidden');
            nome.value = s.nome;
            erro.classList.add('is-hidden');
            selecionados = USUARIOS_ALL.filter(function (u) { return (u.setores || []).indexOf(id) !== -1; }).slice();
            renderChips();
            uInput.value = '';
            uDrop.classList.add('is-hidden');
            cpAplicarCor(s.cor);
            abrir('modal-setor');
            setTimeout(function () { nome.focus(); }, 50);
        }
        document.addEventListener('click', function (e) {
            var ed = e.target.closest('[data-edit-setor]');
            if (ed) { e.preventDefault(); abrirEditarSetor(parseInt(ed.dataset.editSetor, 10)); }
        });

        btnExcluir.addEventListener('click', function () {
            var id = parseInt(idHidden.value, 10) || 0;
            if (!id) return;
            mpsAlert.confirm({ message: 'Excluir este setor? As tarefas dele impedem a exclusão.' }).then(function (ok) {
                if (!ok) return;
                mpsAlert.loading('Excluindo...');
                postJSON('/api/admin/tarefas/config/excluir', { entidade: 'setor', id: id }).then(function (d) {
                    if (d.ok) { mpsAlert.success('Setor excluído.').then(function () { location.reload(); }); }
                    else mpsAlert.error(d.message || 'Não foi possível excluir.');
                }).catch(function () { mpsAlert.error('Erro na requisição.'); });
            });
        });

        el('form-setor').addEventListener('submit', function (e) {
            e.preventDefault();
            var nomeVal = nome.value.trim();
            if (!nomeVal) { erro.textContent = 'Informe o nome do setor.'; erro.classList.remove('is-hidden'); return; }
            var editando = (parseInt(idHidden.value, 10) || 0) > 0;
            var payload = {
                entidade: 'setor', id: parseInt(idHidden.value, 10) || 0, nome: nomeVal, cor: corHidden.value, ordem: 0,
                usuarios: selecionados.map(function (s) { return s.id; }),
            };
            mpsAlert.loading('Salvando...');
            postJSON('/api/admin/tarefas/config/salvar', payload).then(function (d) {
                if (d.ok) { fechar('modal-setor'); mpsAlert.success(editando ? 'Setor atualizado.' : 'Setor criado.').then(function () { location.reload(); }); }
                else mpsAlert.error(d.message || 'Erro ao salvar setor.');
            }).catch(function () { mpsAlert.error('Erro na requisição.'); });
        });
    })();

    // Colore as bolinhas de cor dos setores nas abas do topo.
    document.querySelectorAll('.tarefas-tab-dot[data-cor]').forEach(function (dot) {
        dot.style.backgroundColor = dot.dataset.cor || '#64748b';
    });

    // "Nova tarefa" do topo: usa o setor da aba ativa; em "Todas", pergunta qual.
    var btnNova = el('btn-nova-tarefa');
    if (btnNova) {
        btnNova.addEventListener('click', function () {
            criarTarefaRapida(parseInt(activeSetor, 10) || 0, 0);
        });
    }

    // ── Carga inicial ──────────────────────────────────────────────────────────
    carregar();
})();
