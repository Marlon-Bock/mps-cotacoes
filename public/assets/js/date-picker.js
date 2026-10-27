/**
 * Date Picker — inicializa todos os [data-date-picker] na página.
 * Mesmo padrão de abrir/fechar/flip do custom-select.js, mas o conteúdo
 * do dropdown é um calendário (mês/ano) montado dinamicamente no JS.
 * Valor exposto no input hidden em ISO (YYYY-MM-DD); dispara "change" nele
 * quando o usuário escolhe um dia, igual ao custom-select faz na seleção.
 */
(function () {
    'use strict';

    var MESES = [
        'Janeiro', 'Fevereiro', 'Março', 'Abril', 'Maio', 'Junho',
        'Julho', 'Agosto', 'Setembro', 'Outubro', 'Novembro', 'Dezembro',
    ];

    function pad2(n) { return n < 10 ? '0' + n : '' + n; }

    function toISO(y, m, d) { return y + '-' + pad2(m + 1) + '-' + pad2(d); }

    function toBR(iso) {
        var p = iso.split('-');
        return p[2] + '/' + p[1] + '/' + p[0];
    }

    function initPicker(wrapper) {
        if (wrapper.dataset.datePickerReady) return;
        wrapper.dataset.datePickerReady = '1';

        var trigger     = wrapper.querySelector('.date-picker-trigger');
        var dropdown    = wrapper.querySelector('.date-picker-dropdown');
        var valueEl     = wrapper.querySelector('.date-picker-value');
        var hidden      = wrapper.querySelector('input[type="hidden"]');
        var monthLabel  = wrapper.querySelector('.date-picker-month-label');
        var daysEl      = wrapper.querySelector('.date-picker-days');
        var placeholder = wrapper.dataset.placeholder || 'DD/MM/AAAA';

        if (!trigger || !dropdown || !valueEl || !hidden || !daysEl) return;

        var hoje     = new Date();
        var selected = hidden.value || null;
        var base     = selected ? new Date(selected + 'T00:00:00') : hoje;
        var viewYear  = base.getFullYear();
        var viewMonth = base.getMonth();

        function render() {
            monthLabel.textContent = MESES[viewMonth] + ' ' + viewYear;
            daysEl.innerHTML = '';

            var primeiroDiaSemana = new Date(viewYear, viewMonth, 1).getDay();
            var totalDias         = new Date(viewYear, viewMonth + 1, 0).getDate();
            var diasMesAnterior   = new Date(viewYear, viewMonth, 0).getDate();

            var celulas = [];
            for (var i = 0; i < primeiroDiaSemana; i++) {
                celulas.push({ dia: diasMesAnterior - primeiroDiaSemana + 1 + i, outside: true });
            }
            for (var d = 1; d <= totalDias; d++) {
                celulas.push({ dia: d, outside: false });
            }
            while (celulas.length % 7 !== 0) {
                celulas.push({ dia: celulas.length - (primeiroDiaSemana + totalDias) + 1, outside: true });
            }

            celulas.forEach(function (c) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'date-picker-day';
                btn.textContent = c.dia;

                if (c.outside) {
                    btn.classList.add('is-outside');
                    btn.tabIndex = -1;
                } else {
                    var iso = toISO(viewYear, viewMonth, c.dia);
                    if (iso === toISO(hoje.getFullYear(), hoje.getMonth(), hoje.getDate())) {
                        btn.classList.add('is-today');
                    }
                    if (iso === selected) {
                        btn.classList.add('is-selected');
                    }
                    btn.dataset.iso = iso;
                }
                daysEl.appendChild(btn);
            });
        }

        function setSelected(iso) {
            selected = iso;
            hidden.value = iso || '';
            hidden.dispatchEvent(new Event('change', { bubbles: true }));

            if (iso) {
                valueEl.textContent = toBR(iso);
                valueEl.classList.remove('is-placeholder');
            } else {
                valueEl.textContent = placeholder;
                valueEl.classList.add('is-placeholder');
            }
        }

        function open() {
            var triggerRect = trigger.getBoundingClientRect();
            var dropdownH   = 320;
            var spaceBelow  = window.innerHeight - triggerRect.bottom;
            wrapper.classList.toggle('is-flipped', spaceBelow < dropdownH && triggerRect.top > dropdownH);

            var refBase = selected ? new Date(selected + 'T00:00:00') : hoje;
            viewYear  = refBase.getFullYear();
            viewMonth = refBase.getMonth();
            render();

            wrapper.classList.add('is-open');
            trigger.setAttribute('aria-expanded', 'true');
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

            var nav = e.target.closest('.date-picker-nav');
            if (nav) {
                var dir = parseInt(nav.dataset.nav, 10);
                viewMonth += dir;
                if (viewMonth < 0)  { viewMonth = 11; viewYear--; }
                if (viewMonth > 11) { viewMonth = 0;  viewYear++; }
                render();
                return;
            }

            var dayBtn = e.target.closest('.date-picker-day');
            if (dayBtn && !dayBtn.classList.contains('is-outside')) {
                setSelected(dayBtn.dataset.iso);
                close();
                trigger.focus();
                return;
            }

            var action = e.target.closest('[data-action]');
            if (action) {
                if (action.dataset.action === 'hoje') {
                    setSelected(toISO(hoje.getFullYear(), hoje.getMonth(), hoje.getDate()));
                    close();
                    trigger.focus();
                } else if (action.dataset.action === 'limpar') {
                    setSelected(null);
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
    }

    function initAll(root) {
        root.querySelectorAll('[data-date-picker]').forEach(initPicker);
    }

    document.addEventListener('DOMContentLoaded', function () { initAll(document); });

    window.datePickerInit = initAll;
})();
