/**
 * Base path do sistema quando ele é servido em uma subpasta do domínio
 * (ex.: /MPS no sandbox demo.samisales.com.br). O valor vem do PHP pela meta
 * <meta name="base-path">, alimentada pela constante BASE_URI. Num vhost
 * próprio, com o sistema na raiz, a meta vem vazia e nada aqui altera as URLs.
 *
 * Prefixa automaticamente todo fetch same-origin, no mesmo espírito do
 * csrf-fetch.js: nenhum ponto de chamada precisa lembrar do prefixo. Para os
 * casos que montam URL na mão (window.location.href), use appUrl().
 *
 * Precisa ser o PRIMEIRO script da página, antes do csrf-fetch.js.
 */
(function () {
    'use strict';

    var meta = document.querySelector('meta[name="base-path"]');
    var base = meta ? meta.content.replace(/\/+$/, '') : '';

    window.APP_BASE = base;

    /** Prefixa um caminho interno ('/api/x') com o base path. */
    window.appUrl = function (path) {
        if (!base || typeof path !== 'string') return path;
        if (path.charAt(0) !== '/') return path;             // relativo ou URL absoluta
        if (path.indexOf('//') === 0) return path;           // protocolo-relativo → externo

        // Já prefixado: evita /MPS/MPS/... se alguém passar o resultado adiante
        if (path === base || path.indexOf(base + '/') === 0) return path;

        return base + path;
    };

    if (!base || !window.fetch) return;

    var origFetch = window.fetch;

    window.fetch = function (input, init) {
        return origFetch.call(this, window.appUrl(input), init);
    };
})();
