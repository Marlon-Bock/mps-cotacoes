/**
 * Time Picker — inicializa todos os [data-time-picker] na página.
 * Mesmo padrão de abrir/fechar/flip do date-picker.js, mas o dropdown tem
 * duas colunas roláveis (horas/minutos) no lugar do calendário.
 * Valor exposto no input hidden em "HH:MM"; dispara "change" nele quando o
 * usuário escolhe hora ou minuto, igual ao date-picker faz na seleção de dia.
 */
(function () {
    'use strict';

    function pad2(n) { return n < 10 ? '0' + n : '' + n; }

    function initPicker(wrapper) {
        if (wrapper.dataset.timePickerReady) return;
        wrapper.dataset.timePickerReady = '1';

        var trigger     = wrapper.querySelector('.time-picker-trigger');
        var dropdown    = wrapper.querySelector('.time-picker-dropdown');
        var valueEl     = wrapper.querySelector('.time-picker-value');
        var hidden      = wrapper.querySelector('input[type="hidden"]');
        var colHora     = wrapper.querySelector('.time-picker-col[data-col="hora"]');
        var colMinuto   = wrapper.querySelector('.time-picker-col[data-col="minuto"]');
        var placeholder = wrapper.dataset.placeholder || '--:--';

        if (!trigger || !dropdown || !valueEl || !hidden || !colHora || !colMinuto) return;

        var partes = (hidden.value || '').match(/^(\d{2}):(\d{2})$/);
        var hora   = partes ? partes[1] : null;
        var minuto = partes ? partes[2] : null;

        function marcarSelecionado(col, val) {
            col.querySelectorAll('.time-picker-opt').forEach(function (btn) {
                btn.classList.toggle('is-selected', btn.dataset.val === val);
            });
        }

        function rolarAteSelecionado(col) {
            var sel = col.querySelector('.is-selected');
            if (sel) sel.scrollIntoView({ block: 'center' });
            else col.scrollTop = 0;
        }

        function atualizarValor() {
            marcarSelecionado(colHora, hora);
            marcarSelecionado(colMinuto, minuto);

            if (hora !== null && minuto !== null) {
                var hhmm = hora + ':' + minuto;
                hidden.value = hhmm;
                valueEl.textContent = hhmm;
                valueEl.classList.remove('is-placeholder');
            } else {
                hidden.value = '';
                valueEl.textContent = placeholder;
                valueEl.classList.add('is-placeholder');
            }
            hidden.dispatchEvent(new Event('change', { bubbles: true }));
        }

        function open() {
            var triggerRect = trigger.getBoundingClientRect();
            var dropdownH   = 220;
            var spaceBelow  = window.innerHeight - triggerRect.bottom;
            wrapper.classList.toggle('is-flipped', spaceBelow < dropdownH && triggerRect.top > dropdownH);

            wrapper.classList.add('is-open');
            trigger.setAttribute('aria-expanded', 'true');
            rolarAteSelecionado(colHora);
            rolarAteSelecionado(colMinuto);
        }

        function close() {
            wrapper.classList.remove('is-open', 'is-flipped');
            trigger.setAttribute('aria-expanded', 'false');
        }

        trigger.addEventListener('click', function (e) {
            e.stopPropagation();
            wrapper.classList.contains('is-open') ? close() : open();
        });

        dropdown.addEventListener('click', function (e) {
            e.stopPropagation();

            var opt = e.target.closest('.time-picker-opt');
            if (opt) {
                var col = opt.closest('.time-picker-col');
                if (col === colHora)   hora   = opt.dataset.val;
                if (col === colMinuto) minuto = opt.dataset.val;
                atualizarValor();
                return;
            }

            var action = e.target.closest('[data-action]');
            if (action) {
                if (action.dataset.action === 'agora') {
                    var agora = new Date();
                    hora   = pad2(agora.getHours());
                    minuto = pad2(agora.getMinutes());
                    atualizarValor();
                    rolarAteSelecionado(colHora);
                    rolarAteSelecionado(colMinuto);
                } else if (action.dataset.action === 'limpar') {
                    hora = null;
                    minuto = null;
                    atualizarValor();
                    close();
                    trigger.focus();
                }
            }
        });

        dropdown.addEventListener('mousedown', function (e) { e.preventDefault(); });

        document.addEventListener('click', function (e) {
            if (!wrapper.contains(e.target)) close();
        });

        wrapper.addEventListener('focusout', function (e) {
            if (!wrapper.contains(e.relatedTarget)) close();
        });

        trigger.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') close();
        });

        marcarSelecionado(colHora, hora);
        marcarSelecionado(colMinuto, minuto);
    }

    function initAll(root) {
        root.querySelectorAll('[data-time-picker]').forEach(initPicker);
    }

    document.addEventListener('DOMContentLoaded', function () { initAll(document); });

    window.timePickerInit = initAll;
})();
