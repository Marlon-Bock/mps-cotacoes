<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

/**
 * Páginas do sistema sem regra de negócio: verificação Turnstile, erros,
 * manifesto do PWA e textos legais.
 */
class SistemaController extends Controller
{
    /** GET+POST /verificacao — Turnstile pra acessos de fora do Brasil (ver PortaoGeografico). */
    public function verificacao(Request $request)
    {
        // Já verificado (ou recurso desligado) → não fica preso aqui.
        if (session('geo_ok') || env('TURNSTILE_SITE_KEY', '') === '') {
            return redirect(url(session('geo_destino', '/login')));
        }

        $erro = '';
        if ($request->isMethod('post')) {
            if (turnstile_verificar((string) $request->input('cf-turnstile-response', ''), cliente_ip())) {
                session(['geo_ok' => true]);
                $destino = session()->pull('geo_destino', '/login');
                return redirect(url($destino));
            }
            $erro = 'Verificação falhou. Tente novamente.';
        }

        return view('auth.verificacao', ['erro' => $erro, 'site_key' => env('TURNSTILE_SITE_KEY', '')]);
    }

    public function erro403()
    {
        return response()->view('erros.403', [], 403);
    }

    public function erro404()
    {
        return response()->view('erros.404', [], 404);
    }

    /** GET /manifest.webmanifest — manifesto do PWA. */
    public function manifest()
    {
        return response()->json([
            'name'             => 'Mesquita Prime - Cotações',
            'short_name'       => 'MPS Cotações',
            'description'      => 'Portal de cotações de seguros Mesquita Prime',
            'start_url'        => url('/'),
            'scope'            => url('/'),
            'display'          => 'standalone',
            'orientation'      => 'portrait-primary',
            'background_color' => '#ffffff',
            'theme_color'      => '#7F1B1B',
            'lang'             => 'pt-BR',
            'icons'            => [[
                'src'     => url('/assets/img/logo-mark.png'),
                'sizes'   => '192x192',
                'type'    => 'image/png',
                'purpose' => 'any maskable',
            ]],
            'categories' => ['finance', 'business'],
        ], 200, [
            'Content-Type'  => 'application/manifest+json; charset=utf-8',
            'Cache-Control' => 'public, max-age=86400',
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    }

    public function politica()
    {
        return view('legal.politica-de-privacidade');
    }

    public function termos()
    {
        return view('legal.termos-de-servico');
    }
}
