<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Headers de segurança em toda resposta (no antigo: bloco no src/bootstrap.php).
 * As páginas de embed abrem dentro de iframe no site do cliente: elas chamam
 * embed_apply_frame_headers(), que marca o request com o frame-ancestors liberado —
 * aqui isso troca o CSP e tira o X-Frame-Options.
 */
class CabecalhosSeguranca
{
    public function handle(Request $request, Closure $next)
    {
        $response = $next($request);

        $ancestors = $request->attributes->get('embed_frame_ancestors');
        $h = $response->headers;

        if ($ancestors === null) {
            $h->set('X-Frame-Options', 'SAMEORIGIN');                   // anti-clickjacking
        } else {
            $h->remove('X-Frame-Options');
        }
        $h->set('X-Content-Type-Options', 'nosniff');                   // anti MIME-sniffing
        $h->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        [, $csp] = explode(': ', csp_header($ancestors ?? "'self'"), 2);
        $h->set('Content-Security-Policy', $csp);
        if ($request->isSecure() || $request->header('X-Forwarded-Proto') === 'https') {
            $h->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }

        return $response;
    }
}
