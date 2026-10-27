<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Rota de usuário logado (MPS ou imobiliária) — regras de require_auth(). */
class ExigirLogin
{
    public function handle(Request $request, Closure $next)
    {
        $request->attributes->set('usuario', require_auth());
        return $next($request);
    }
}
