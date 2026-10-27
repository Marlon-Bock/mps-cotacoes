<?php

use App\Http\Middleware\ValidarCsrf;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Http\Request;
use Illuminate\Session\TokenMismatchException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // IP real do visitante: o domínio passa pelo Cloudflare, então o REMOTE_ADDR
        // é o IP do Cloudflare. Só confia no X-Forwarded-For quando a requisição vem
        // de fato da rede do Cloudflare (lista oficial em cloudflare.com/ips) — quem
        // acessar o servidor direto não consegue inventar o próprio IP. Usado no
        // bloqueio de login por IP, no limite do embed e nos registros de acesso.
        $middleware->trustProxies(
            at: [
                '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22',
                '141.101.64.0/18', '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20',
                '197.234.240.0/22', '198.41.128.0/17', '162.158.0.0/15', '104.16.0.0/13',
                '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
                '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32',
                '2405:8100::/32', '2a06:98c0::/29', '2c0f:f248::/32',
            ],
            headers: Request::HEADER_X_FORWARDED_FOR,
        );
        $middleware->web(replace: [ValidateCsrfToken::class => ValidarCsrf::class]);
        $middleware->web(append: [\App\Http\Middleware\CabecalhosSeguranca::class, \App\Http\Middleware\PainelPersistente::class, \App\Http\Middleware\PortaoGeografico::class, \App\Http\Middleware\RegistrarAuditoria::class]);
        // Webhook das seguradoras (token na URL) e embed (CSRF próprio, ver app/Legado/embed.php).
        // Login confere o CSRF por conta própria (registra o motivo da falha) — ver AuthController@login.
        $middleware->validateCsrfTokens(except: ['api/webhook/*', 'api/embed/*/cotacao/*', 'api/auth/login']);
        $middleware->alias([
            'mps.auth'  => \App\Http\Middleware\ExigirLogin::class,
            'mps.admin' => \App\Http\Middleware\ExigirAdmin::class,
            'mps.perm'  => \App\Http\Middleware\ExigirPermissao::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Log central (tela /admin/sistema-erros), como no sistema antigo.
        $exceptions->report(function (Throwable $e) {
            sistema_erro_registrar('exception', $e->getMessage(), $e->getFile(), $e->getLine(), $e->getTraceAsString(), get_class($e));
        });

        // Valor recusado pelo MySQL: o usuário corrige o campo (422), não é "erro interno".
        $exceptions->render(function (QueryException $e, Request $request) {
            $msg = sistema_erro_mensagem_dado_invalido($e);
            if ($msg === null) {
                return null;
            }
            return $request->is('api/*') || $request->expectsJson()
                ? response()->json(['success' => false, 'ok' => false, 'message' => $msg], 422)
                : response('<h1>Dados inválidos</h1><p>' . e($msg) . '</p>', 422);
        });

        // Sessão expirada (CSRF inválido): mesma resposta que o front já trata.
        // O Laravel 12 converte TokenMismatchException em HttpException 419 ANTES dos
        // callbacks de render — por isso o teste é pela exceção anterior.
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, Request $request) {
            if (!$e->getPrevious() instanceof TokenMismatchException) {
                return null;
            }
            return response()->json([
                'error'            => 'Token CSRF inválido.',
                'message'          => 'Sua sessão expirou. Atualize a página e tente novamente.',
                'session_expirada' => true,
            ], 403);
        });

        // API sem modo debug: JSON genérico, como antes.
        $exceptions->render(function (Throwable $e, Request $request) {
            if ($request->is('api/*') && !config('app.debug') && !($e instanceof \Symfony\Component\HttpKernel\Exception\HttpExceptionInterface)) {
                return response()->json(['success' => false, 'message' => 'Erro interno. Tente novamente em instantes.'], 500);
            }
            return null;
        });

        $exceptions->dontReport([TokenMismatchException::class]);
    })->create();
