/**
 * Horário de Atendimento (admin) — admin-horarios-atendimento.js
 * Dois modais simples (horário por produto / bloqueio temporário), usando
 * date_picker()/time_picker() do projeto (não input nativo). Depois de
 * salvar/excluir só recarrega a página — é uma tela de configuração de baixo
 * tráfego, sem necessidade de um endpoint de lista via AJAX.
 */
(function () {
    'use strict';

    const csrfToken = (document.querySelector('meta[name="csrf-token"]') || {}).content || '';

    function abrir(id) {
        const modal = document.getElementById(id);
        if (modal) modal.hidden = false;
    }
    function fechar(id) {
        const modal = document.getElementById(id);
        if (modal) modal.hidden = true;
    }

    // Mesmo padrão de admin-visitas-agendar.js pra preencher os pickers do projeto.
    function setTimePickerValue(elementId, hhmm) {
        const hidden = document.getElementById(elementId);
        if (!hidden) return;
        const wrapper = hidden.closest('[data-time-picker]');
        hidden.value = hhmm || '';
        if (!wrapper) return;
        const valueEl = wrapper.querySelector('.time-picker-value');
        if (hhmm) {
            valueEl.textContent = hhmm;
            valueEl.classList.remove('is-placeholder');
        } else {
            valueEl.textContent = wrapper.dataset.placeholder || '--:--';
            valueEl.classList.add('is-placeholder');
        }
    }

    function setDatePickerValue(elementId, isoValor) {
        const hidden = document.getElementById(elementId);
        if (!hidden) return;
        const wrapper = hidden.closest('[data-date-picker]');
        hidden.value = isoValor || '';
        if (!wrapper) return;
        const valueEl = wrapper.querySelector('.date-picker-value');
        if (isoValor) {
            const [y, m, d] = isoValor.split('-');
            valueEl.textContent = `${d}/${m}/${y}`;
            valueEl.classList.remove('is-placeholder');
        } else {
            valueEl.textContent = wrapper.dataset.placeholder || 'DD/MM/AAAA';
            valueEl.classList.add('is-placeholder');
        }
    }

    // ── Modal: horário por produto ──────────────────────────────────────────

    const formHorario = document.getElementById('form-horario');

    document.querySelectorAll('[data-action="editar-horario"]').forEach((btn) => {
        btn.addEventListener('click', () => {
            document.getElementById('modal-horario-titulo').textContent = `Horário — ${btn.dataset.label}`;
            document.getElementById('horario-tipo-seguro').value = btn.dataset.tipo;
            setTimePickerValue('horario-hora-inicio', btn.dataset.inicio.slice(0, 5));
            setTimePickerValue('horario-hora-fim', btn.dataset.fim.slice(0, 5));

            const diasAtivos = (btn.dataset.dias || '').split(',').map((d) => d.trim());
            formHorario.querySelectorAll('[name="dias_semana[]"]').forEach((chk) => {
                chk.checked = diasAtivos.includes(chk.value);
            });

            formHorario.querySelector('[name="ativo"]').checked = btn.dataset.ativo === 'S';
            abrir('modal-horario');
        });
    });

    formHorario.addEventListener('submit', (e) => {
        e.preventDefault();

        const payload = {
            tipo_seguro: document.getElementById('horario-tipo-seguro').value,
            hora_inicio: document.getElementById('horario-hora-inicio').value,
            hora_fim: document.getElementById('horario-hora-fim').value,
            dias_semana: [...formHorario.querySelectorAll('[name="dias_semana[]"]:checked')].map((chk) => chk.value),
            ativo: formHorario.querySelector('[name="ativo"]').checked,
        };

        fetch('/api/admin/horario-atendimento/salvar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify(payload),
        })
            .then((r) => r.json())
            .then(async (data) => {
                if (data.ok) {
                    await mpsAlert.success('Horário salvo com sucesso!', { autoCloseMs: 1500 });
                    window.location.reload();
                } else {
                    mpsAlert.error(data.message || 'Erro ao salvar o horário.');
                }
            })
            .catch(() => mpsAlert.error('Erro na requisição. Tente novamente.'));
    });

    // ── Modal: bloqueio temporário ───────────────────────────────────────────

    const formBloqueio = document.getElementById('form-bloqueio');

    document.getElementById('btn-novo-bloqueio').addEventListener('click', () => {
        formBloqueio.reset();
        formBloqueio.querySelector('[name="id"]').value = '';
        setDatePickerValue('bloqueio-data-inicio', '');
        setTimePickerValue('bloqueio-hora-inicio', '');
        setDatePickerValue('bloqueio-data-fim', '');
        setTimePickerValue('bloqueio-hora-fim', '');
        formBloqueio.querySelector('[name="ativo"]').checked = true;
        document.getElementById('modal-bloqueio-titulo').textContent = 'Novo bloqueio';
        abrir('modal-bloqueio');
    });

    document.querySelectorAll('[data-action="editar-bloqueio"]').forEach((btn) => {
        btn.addEventListener('click', () => {
            document.getElementById('modal-bloqueio-titulo').textContent = 'Editar bloqueio';
            formBloqueio.querySelector('[name="id"]').value = btn.dataset.id;
            formBloqueio.querySelector('[name="titulo"]').value = btn.dataset.titulo || '';
            formBloqueio.querySelector('[name="mensagem"]').value = btn.dataset.mensagem || '';
            setDatePickerValue('bloqueio-data-inicio', btn.dataset.dataInicio || '');
            setTimePickerValue('bloqueio-hora-inicio', btn.dataset.horaInicio || '');
            setDatePickerValue('bloqueio-data-fim', btn.dataset.dataFim || '');
            setTimePickerValue('bloqueio-hora-fim', btn.dataset.horaFim || '');

            const produtosAtivos = (btn.dataset.produtos || '').split(',').map((p) => p.trim());
            formBloqueio.querySelectorAll('[name="tipo_seguro[]"]').forEach((chk) => {
                chk.checked = produtosAtivos.includes(chk.value);
            });

            formBloqueio.querySelector('[name="ativo"]').checked = btn.dataset.ativo === 'S';
            abrir('modal-bloqueio');
        });
    });

    formBloqueio.addEventListener('submit', (e) => {
        e.preventDefault();

        const payload = {
            id: formBloqueio.querySelector('[name="id"]').value,
            titulo: formBloqueio.querySelector('[name="titulo"]').value.trim(),
            mensagem: formBloqueio.querySelector('[name="mensagem"]').value.trim(),
            data_inicio: document.getElementById('bloqueio-data-inicio').value,
            hora_inicio: document.getElementById('bloqueio-hora-inicio').value,
            data_fim: document.getElementById('bloqueio-data-fim').value,
            hora_fim: document.getElementById('bloqueio-hora-fim').value,
            tipo_seguro: [...formBloqueio.querySelectorAll('[name="tipo_seguro[]"]:checked')].map((chk) => chk.value),
            ativo: formBloqueio.querySelector('[name="ativo"]').checked,
        };

        fetch('/api/admin/bloqueio-horario/salvar', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
            body: JSON.stringify(payload),
        })
            .then((r) => r.json())
            .then(async (data) => {
                if (data.ok) {
                    await mpsAlert.success('Bloqueio salvo com sucesso!', { autoCloseMs: 1500 });
                    window.location.reload();
                } else {
                    mpsAlert.error(data.message || 'Erro ao salvar o bloqueio.');
                }
            })
            .catch(() => mpsAlert.error('Erro na requisição. Tente novamente.'));
    });

    document.querySelectorAll('[data-action="excluir-bloqueio"]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const ok = await mpsAlert.confirm({ message: 'Excluir este bloqueio?' });
            if (!ok) return;

            fetch('/api/admin/bloqueio-horario/excluir', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                body: JSON.stringify({ id: btn.dataset.id }),
            })
                .then((r) => r.json())
                .then(async (data) => {
                    if (data.ok) {
                        await mpsAlert.success('Bloqueio excluído.', { autoCloseMs: 1500 });
                        window.location.reload();
                    } else {
                        mpsAlert.error(data.message || 'Erro ao excluir o bloqueio.');
                    }
                })
                .catch(() => mpsAlert.error('Erro na requisição. Tente novamente.'));
        });
    });
})();
