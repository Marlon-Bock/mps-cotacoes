<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Constantes que os helpers de app/Legado usam (eram do src/bootstrap.php).
        $base = '/' . trim((string) parse_url((string) config('app.url'), PHP_URL_PATH), '/');
        defined('BASE_URI')   || define('BASE_URI', $base === '/' ? '' : $base);
        defined('APP_URL')    || define('APP_URL', rtrim((string) config('app.url'), '/'));
        defined('MEDIA_URL')  || define('MEDIA_URL', rtrim((string) env('MEDIA_URL', 'https://media.mesquitaprime.com.br'), '/'));
        defined('MEDIA_PATH') || define('MEDIA_PATH', (string) env('MEDIA_PATH', ''));

        // Feature flags (independentes do APP_ENV). Dá pra forçar pelo .env com
        // FEATURE_EMBED_UI / FEATURE_ZAPI_NOTIFICACOES / FEATURE_CANAL_API = 1|0; sem a variável:
        //   - embed: ligado (em uso);
        //   - Z-API: liga sozinha quando ZAPI_INSTANCE_ID e ZAPI_TOKEN estão no .env
        //     (sem credencial nem tenta enviar nem grava falha no log);
        //   - canal "API das seguradoras" no cadastro da imobiliária: desligado por
        //     enquanto (só Manual e Segimob — ver app/Legado/canais.php).
        $flag = static fn (string $nome, bool $padrao) => filter_var(env($nome, $padrao), FILTER_VALIDATE_BOOLEAN);
        $zapi_configurada = (string) env('ZAPI_INSTANCE_ID', '') !== '' && (string) env('ZAPI_TOKEN', '') !== '';
        defined('FEATURE_EMBED_UI')          || define('FEATURE_EMBED_UI', $flag('FEATURE_EMBED_UI', true));
        defined('FEATURE_ZAPI_NOTIFICACOES') || define('FEATURE_ZAPI_NOTIFICACOES', $flag('FEATURE_ZAPI_NOTIFICACOES', $zapi_configurada));
        defined('FEATURE_CANAL_API')         || define('FEATURE_CANAL_API', $flag('FEATURE_CANAL_API', false));
    }

    public function boot(): void
    {
        // Formulários do embed são públicos (sem login): limita envios pra ninguém
        // encher a fila de cotações falsas — por IP e por imobiliária (token).
        \Illuminate\Support\Facades\RateLimiter::for('embed-envio', function (\Illuminate\Http\Request $request) {
            $excedeu = fn () => response()->json([
                'success' => false,
                'message' => 'Muitos envios em pouco tempo. Aguarde alguns minutos e tente novamente.',
            ], 429);
            return [
                \Illuminate\Cache\RateLimiting\Limit::perHour(20)->by('ip:' . $request->ip())->response($excedeu),
                \Illuminate\Cache\RateLimiting\Limit::perHour(100)->by('token:' . $request->route('embed_token'))->response($excedeu),
            ];
        });
        // Acompanhamento do resultado no embed (o formulário consulta a cada poucos segundos).
        \Illuminate\Support\Facades\RateLimiter::for('embed-situacao', fn (\Illuminate\Http\Request $request) =>
            \Illuminate\Cache\RateLimiting\Limit::perHour(600)->by('ip:' . $request->ip()));
        // Busca de imóvel por código dentro do embed (consulta o ERP da imobiliária).
        \Illuminate\Support\Facades\RateLimiter::for('embed-busca', fn (\Illuminate\Http\Request $request) =>
            \Illuminate\Cache\RateLimiting\Limit::perHour(60)->by('ip:' . $request->ip())->response(fn () => response()->json([
                'ok' => false, 'erro' => 'Muitas buscas em pouco tempo. Aguarde alguns minutos.',
            ], 429)));

        // URLs sempre com o APP_URL (https + /seguradora), mesmo atrás de proxy.
        \Illuminate\Support\Facades\URL::forceRootUrl(config('app.url'));
        if (str_starts_with((string) config('app.url'), 'https://')) {
            \Illuminate\Support\Facades\URL::forceScheme('https');
        }

        // Warnings/notices/deprecated do PHP: como no sistema antigo, vão pro
        // log central (sistema_erros) e a requisição segue — o Laravel padrão
        // transformaria em exceção (erro 500) qualquer "Undefined array key".
        $anterior = set_error_handler(null);
        set_error_handler(function (int $nivel, string $mensagem, string $arquivo = '', int $linha = 0) use (&$anterior) {
            if (!(error_reporting() & $nivel)) {
                return false; // suprimido de propósito (@)
            }
            $leves = [
                E_WARNING => 'E_WARNING', E_NOTICE => 'E_NOTICE', E_DEPRECATED => 'E_DEPRECATED',
                E_USER_WARNING => 'E_USER_WARNING', E_USER_NOTICE => 'E_USER_NOTICE', E_USER_DEPRECATED => 'E_USER_DEPRECATED',
            ];
            if (isset($leves[$nivel])) {
                if (!str_contains($arquivo, DIRECTORY_SEPARATOR . 'vendor' . DIRECTORY_SEPARATOR)) {
                    sistema_erro_registrar('error', $mensagem, $arquivo, $linha, null, $leves[$nivel]);
                }
                return true;
            }
            return $anterior ? $anterior($nivel, $mensagem, $arquivo, $linha) : false;
        });
    }
}
