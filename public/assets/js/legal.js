/**
 * Páginas legais — índice lateral: marca a seção que está na tela e, no
 * celular, começa recolhido (abre ao tocar em "Nesta página").
 */
(function () {
    'use strict';

    var box = document.querySelector('.legal-indice-box');
    if (box && window.matchMedia('(max-width: 960px)').matches) {
        box.removeAttribute('open');
    }

    var links = Array.prototype.slice.call(document.querySelectorAll('[data-indice]'));
    if (!links.length || !('IntersectionObserver' in window)) return;

    function marcar(id) {
        links.forEach(function (a) {
            a.classList.toggle('is-ativo', a.getAttribute('data-indice') === id);
        });
    }

    var observador = new IntersectionObserver(function (entradas) {
        entradas.forEach(function (e) {
            if (e.isIntersecting) marcar(e.target.id);
        });
    }, { rootMargin: '-90px 0px -60% 0px' });

    links.forEach(function (a) {
        var secao = document.getElementById(a.getAttribute('data-indice'));
        if (secao) observador.observe(secao);
        // No celular, fecha o índice depois de escolher a seção.
        a.addEventListener('click', function () {
            if (box && window.matchMedia('(max-width: 960px)').matches) box.removeAttribute('open');
        });
    });

    marcar(links[0].getAttribute('data-indice'));
})();
