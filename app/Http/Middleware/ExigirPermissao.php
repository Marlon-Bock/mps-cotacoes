<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/**
 * Rota da imobiliária com permissão — regras de require_perm().
 * Uso: ->middleware('mps.perm:perm_cotar,perm_fianca')
 */
class ExigirPermissao
{
    public function handle(Request $request, Closure $next, string ...$perms)
    {
        $request->attributes->set('usuario', require_perm(...$perms));
        return $next($request);
    }
}
