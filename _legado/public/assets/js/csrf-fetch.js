/**
 * Anexa automaticamente o header X-CSRF-Token a TODA requisição fetch
 * same-origin que altera estado (POST/PUT/PATCH/DELETE). Assim nenhum ponto de
 * chamada precisa lembrar de enviar o token, e o servidor pode exigir CSRF em
 * todos os endpoints com segurança. Carregado antes de qualquer outro script.
 *
 * Não toca em GET/HEAD nem em requisições cross-origin (media server, FIPE, ERP),
 * e não sobrescreve um X-CSRF-Token já definido manualmente.
 */
(function () {
    'use strict';
    if (!window.fetch) return;

    var meta  = document.querySelector('meta[name="csrf-token"]');
    var token = meta ? meta.content : '';
    if (!token) return;

    var origFetch = window.fetch;

    function mesmaOrigem(url) {
        if (typeof url !== 'string') return true;           // Request relativo do próprio app
        if (url.indexOf('//') === 0) return false;           // protocolo-relativo → trata como externo
        if (url.charAt(0) === '/') return true;              // caminho absoluto same-origin
        return url.indexOf(window.location.origin) === 0;    // URL absoluta do mesmo host
    }

    window.fetch = function (input, init) {
        init = init || {};

        var url = typeof input === 'string' ? input : (input && input.url) || '';
        var method = (init.method || (typeof input === 'object' && input && input.method) || 'GET').toUpperCase();

        if (method !== 'GET' && method !== 'HEAD' && mesmaOrigem(url)) {
            var headers = new Headers(
                init.headers || (typeof input === 'object' && input && input.headers) || {}
            );
            if (!headers.has('X-CSRF-Token')) {
                headers.set('X-CSRF-Token', token);
                init.headers = headers;
            }
        }

        return origFetch.call(this, input, init);
    };
})();
