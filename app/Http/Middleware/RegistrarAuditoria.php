<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Auditoria de cada requisição (ver app/Legado/auditoria.php): abre o
 * contexto (quem, IP, URL, ação, dados enviados) e, no fim — depois até do
 * que roda após a resposta (terminating) —, grava o registro e as alterações
 * do banco.
 *
 * Entra: toda requisição que muda algo (POST/PUT/PATCH/DELETE ou que alterou
 * o banco), tela aberta (HTML) e download (PDF, planilha). Consultas JSON sem
 * alteração (polling das telas) não entram.
 */
class RegistrarAuditoria
{
    public function handle(Request $request, Closure $next): Response
    {
        $inicio = microtime(true);
        $ator   = self::ator($request);

        auditoria_abrir([
            'criado_em'        => auditoria_agora(),
            'canal'            => 'web',
            'ip'               => mb_substr((string) $request->ip(), 0, 45),
            'user_agent'       => mb_substr((string) $request->userAgent(), 0, 255),
            'metodo'           => $request->method(),
            'url'              => mb_substr($request->getRequestUri(), 0, 500),
            'acao'             => self::acao($request),
            'parametros'       => auditoria_json(auditoria_mascarar($request->all() + $request->allFiles())),
        ] + $ator);

        $response = $next($request);

        // Grava por último: o que os controllers deixam pra depois da resposta
        // (terminating registrados antes deste) também entra.
        app()->terminating(static function () use ($request, $response, $inicio, $ator) {
            // Login: quem entrou só é conhecido no fim.
            $complemento = $ator['ator_tipo'] === 'anonimo' ? self::ator($request) : [];
            auditoria_fechar($complemento + [
                'status_http' => $response->getStatusCode(),
                'duracao_ms'  => (int) round((microtime(true) - $inicio) * 1000),
            ], self::registrar($request, $response));
        });

        return $response;
    }

    /** Quem fez: usuário logado (MPS, imobiliária, painel), embed (pela imobiliária do token) ou anônimo. */
    private static function ator(Request $request): array
    {
        $user = auth_user();
        if ($user) {
            return [
                'ator_tipo'        => (string) $user->role,
                'ator_id'          => (int) $user->uid,
                'ator_nome'        => mb_substr((string) ($user->nome ?? '') . (session('auditoria_senha_mestra_por') ? ' (senha do dia: ' . session('auditoria_senha_mestra_por') . ')' : ''), 0, 160),
                'ator_email'       => mb_substr((string) ($user->email ?? ''), 0, 160),
                'imobiliaria_id'   => $user->imobiliaria_id ? (int) $user->imobiliaria_id : null,
                'via_senha_mestra' => session('auditoria_senha_mestra') ? 1 : 0,
            ];
        }
        $token = (string) ($request->route('embed_token') ?? '');
        if ($token !== '') {
            $imob = db_fetch_one('SELECT id, nome FROM imobiliarias WHERE embed_token = ?', ['s', $token]);
            return [
                'ator_tipo'      => 'embed',
                'ator_nome'      => $imob ? 'Embed — ' . mb_substr((string) $imob['nome'], 0, 140) : 'Embed',
                'imobiliaria_id' => $imob ? (int) $imob['id'] : null,
            ];
        }
        return ['ator_tipo' => 'anonimo'];
    }

    /** "Controller@método" (sem o namespace) ou a rota. */
    private static function acao(Request $request): ?string
    {
        $acao = $request->route()?->getActionName();
        if (!$acao || $acao === 'Closure') {
            return $request->route()?->uri();
        }
        return mb_substr(str_replace('App\\Http\\Controllers\\', '', $acao), 0, 160);
    }

    /** Leitura só entra se for tela (HTML) ou download; escrita sempre. */
    private static function registrar(Request $request, Response $response): bool
    {
        if (!in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return true;
        }
        $tipo = (string) $response->headers->get('Content-Type', '');
        return str_contains($tipo, 'text/html')
            || str_contains($tipo, 'application/pdf')
            || str_contains($tipo, 'spreadsheet')
            || str_contains((string) $response->headers->get('Content-Disposition', ''), 'attachment');
    }
}
