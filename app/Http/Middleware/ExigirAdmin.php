<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

/** Rota só da equipe MPS — regras de require_admin(). */
class ExigirAdmin
{
    public function handle(Request $request, Closure $next)
    {
        $request->attributes->set('usuario', require_admin());
        return $next($request);
    }
}
