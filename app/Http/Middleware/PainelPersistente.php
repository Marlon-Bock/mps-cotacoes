<?php

namespace App\Http\Middleware;

use App\Models\Usuario;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;

/**
 * Painel de TV fica logado o mês inteiro (no antigo: JWT_TTL_PAINEL = 30 dias),
 * mas a sessão do Laravel dura só 3h. No login da conta painel gravamos o cookie
 * PainelPersistente::COOKIE (criptografado pelo EncryptCookies — não dá pra forjar)
 * com o id e a validade; quando a sessão expira, este middleware reloga a TV sozinho.
 */
class PainelPersistente
{
    public const COOKIE = 'mps_painel';
    public const DIAS   = 30;

    public function handle(Request $request, Closure $next)
    {
        $valor = $request->cookie(self::COOKIE);
        if ($valor && !Auth::guard('admin')->check() && !Auth::guard('imobiliaria')->check()) {
            $dados = json_decode((string) $valor, true);
            $conta = is_array($dados) && ($dados['exp'] ?? 0) > time()
                ? Usuario::find((int) ($dados['id'] ?? 0))
                : null;
            // Conta precisa continuar sendo painel e ativa; senão o cookie é descartado.
            if ($conta && ($conta->painel ?? 'N') === 'S' && (int) ($conta->ativo ?? 1) === 1) {
                Auth::guard('admin')->login($conta);
            } else {
                Cookie::queue(Cookie::forget(self::COOKIE));
            }
        }

        return $next($request);
    }

    /** Grava o cookie de 30 dias (chamado no login da conta painel). */
    public static function lembrar(int $usuarioId): void
    {
        $min = self::DIAS * 24 * 60;
        Cookie::queue(self::COOKIE, json_encode(['id' => $usuarioId, 'exp' => time() + $min * 60]), $min);
    }
}
