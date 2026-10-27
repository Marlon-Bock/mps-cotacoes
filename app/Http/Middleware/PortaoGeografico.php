<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Acesso de fora do Brasil passa pela verificação Turnstile (/verificacao)
 * uma vez por sessão. Desligado sem TURNSTILE_SITE_KEY. IPs locais/privados
 * nunca são desafiados; GEO_FORCE_IPS força a verificação (teste).
 */
class PortaoGeografico
{
    public function handle(Request $request, Closure $next)
    {
        if (env('TURNSTILE_SITE_KEY', '') === '' || session('geo_ok')) {
            return $next($request);
        }

        $ip = cliente_ip();
        if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return $next($request);
        }

        $forcar  = array_filter(array_map('trim', explode(',', (string) env('GEO_FORCE_IPS', ''))));
        $forcado = in_array($ip, $forcar, true);
        $pais    = geo_pais();
        if (!$forcado && ($pais === null || $pais === 'BR')) {
            return $next($request);
        }

        // A própria verificação e o webhook das seguradoras nunca são barrados.
        if ($request->is('verificacao') || $request->is('api/webhook/*')) {
            return $next($request);
        }

        $qs = $request->getQueryString();
        session(['geo_destino' => request_path() . ($qs ? '?' . $qs : '')]);
        return redirect(url('/verificacao'));
    }
}
