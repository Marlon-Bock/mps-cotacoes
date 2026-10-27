<?php
/**
 * Bootstrap do sistema — executado uma única vez pelo front controller.
 * Ordem: erro handling → .env → constantes → sessão → dependências
 */

define('BASE_PATH', dirname(__DIR__));

// ── Fuso horário ──────────────────────────────────────────────────────────────
// O MySQL grava NOW() em horário de Brasília (-03). Sem isso o PHP usa o
// date.timezone do servidor — no PHP-FPM 8.2 deste servidor é UTC (3h à
// frente), o que desalinhava date()/time() do PHP com as datas do banco.
date_default_timezone_set('America/Sao_Paulo');

// ── Tratamento de erros ───────────────────────────────────────────────────────
error_reporting(E_ALL);

// Em produção (APP_ENV=production) exibe erros só no log, nunca ao usuário
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// storage/logs é git-ignorado (só o .gitkeep é versionado) — se a pasta não
// existir no servidor, o ini_set do error_log falha em silêncio e todo erro
// fatal vira uma tela em branco sem deixar rastro nenhum.
$logDir = BASE_PATH . '/storage/logs';
if (!is_dir($logDir)) {
    mkdir($logDir, 0775, true);
}
ini_set('error_log', $logDir . '/error.log');

// storage/cache guarda os tokens das APIs das seguradoras e os PDFs — também é
// git-ignorado; sem a pasta o cache falhava em silêncio e cada chamada
// fazia um login novo na API.
if (!is_dir(BASE_PATH . '/storage/cache')) {
    @mkdir(BASE_PATH . '/storage/cache', 0775, true);
}

// ── Carrega .env ──────────────────────────────────────────────────────────────
require_once BASE_PATH . '/src/config/env.php';
load_env(BASE_PATH . '/.env');

// Ativa display_errors em desenvolvimento — mas NUNCA nos endpoints /api/*, onde
// um simples Warning impresso antes do JSON corrompe a resposta e o front cai no
// catch genérico ("Erro na requisição"), escondendo a causa real. No /api/* o
// erro continua indo para storage/logs/error.log.
if (env('APP_ENV', 'production') === 'development' && strpos($_SERVER['REQUEST_URI'] ?? '', '/api/') === false) {
    ini_set('display_errors', '1');
}

// ── Constantes globais ────────────────────────────────────────────────────────
define('APP_URL',    rtrim(env('APP_URL', 'http://localhost'), '/'));
define('MEDIA_URL',  rtrim(env('MEDIA_URL', 'https://media.mesquitaprime.com.br'), '/'));
define('MEDIA_PATH', env('MEDIA_PATH', ''));   // path local se o servidor de mídia for montado

// Feature flags — ligadas em desenvolvimento, desligadas em produção até
// serem validadas de verdade (troca pra sempre-ligado quando for pra ar).
//   FEATURE_EMBED_UI            libera o autoatendimento de embed: a própria
//                               imobiliária ativa em /embed, informando a URL do site.
//   FEATURE_ZAPI_NOTIFICACOES   liga o envio de notificações internas via WhatsApp (Z-API).
$__dev = env('APP_ENV', 'production') === 'development';
define('FEATURE_EMBED_UI', $__dev);
define('FEATURE_ZAPI_NOTIFICACOES', $__dev);
unset($__dev);

// ── Base path (sistema servido em subpasta) ───────────────────────────────────
// O sandbox demo.samisales.com.br publica cada projeto em /<projeto>/ e informa
// o prefixo ao PHP via fastcgi_param SITE_BASE_PATH. Num vhost próprio (raiz do
// domínio) a variável não existe, BASE_URI fica vazio e todas as URLs saem sem
// prefixo — sem precisar mexer no código na hora de promover o ambiente.
$__base = (string) ($_SERVER['SITE_BASE_PATH'] ?? env('BASE_URI', ''));
$__base = '/' . trim($__base, '/');
define('BASE_URI', $__base === '/' ? '' : $__base);
unset($__base);

// ── Detecção de HTTPS (proxy-aware) ────────────────────────────────────────────
$__https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');

/**
 * Monta a Content-Security-Policy do sistema. `$frameAncestors` é a única
 * diretiva que varia — as páginas de embed (src/core/embed.php) precisam
 * liberar o domínio do cliente em vez do padrão 'self'.
 */
function csp_header(string $frameAncestors = "'self'"): string
{
    return "Content-Security-Policy: " .
        "default-src 'self'; " .
        "script-src 'self' 'unsafe-inline' https://challenges.cloudflare.com; " .
        "style-src 'self' 'unsafe-inline'; " .
        "img-src 'self' data: " . MEDIA_URL . "; " .
        "connect-src 'self' https://viacep.com.br; " .
        "object-src 'none'; " .
        "base-uri 'self'; " .
        "frame-src 'self' https://challenges.cloudflare.com; " .
        "frame-ancestors {$frameAncestors}";
}

// ── Headers de segurança (não-CLI) ──────────────────────────────────────────────
if (PHP_SAPI !== 'cli') {
    header('X-Frame-Options: SAMEORIGIN');                        // anti-clickjacking
    header('X-Content-Type-Options: nosniff');                   // anti MIME-sniffing
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header(csp_header());
    if ($__https) {
        header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
    }
}

// ── Sessão ────────────────────────────────────────────────────────────────────
// gc_maxlifetime explícito (em vez de depender do padrão do servidor, tipicamente
// só 24 min) — sem isso, uma aba de login/formulário deixada aberta por mais tempo
// (ex: PC reiniciado, aba restaurada pelo navegador) tem sua sessão apagada no
// servidor antes de enviar o form, e o token CSRF embutido no HTML fica inválido.
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.gc_maxlifetime', 8 * 3600); // 8h — cobre um dia de trabalho com abas abertas
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => BASE_URI . '/',   // isola o cookie dos outros projetos do sandbox
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => $__https,   // só trafega o cookie de sessão em HTTPS
    ]);
    session_start();
}

// ── Dependências core ─────────────────────────────────────────────────────────
require_once BASE_PATH . '/src/config/database.php';
require_once BASE_PATH . '/src/core/auth.php';

// Log central de erros (exceptions não tratadas, erros PHP, fatais) — grava em
// `sistema_erros` pra tela de diagnóstico em /admin/sistema-erros. Precisa vir
// depois de database.php/auth.php (usa db_execute()/auth_user()), mas o mais
// cedo possível pra pegar qualquer coisa que aconteça daqui pra frente.
require_once BASE_PATH . '/src/core/error_logger.php';
sistema_erro_handlers_instalar();

require_once BASE_PATH . '/src/core/csrf.php';
require_once BASE_PATH . '/src/core/embed.php';
require_once BASE_PATH . '/src/core/router.php';
require_once BASE_PATH . '/src/helpers/functions.php';
require_once BASE_PATH . '/src/helpers/media.php';
require_once BASE_PATH . '/src/helpers/icons.php';
require_once BASE_PATH . '/src/helpers/rascunho.php';
require_once BASE_PATH . '/src/helpers/assets.php';
require_once BASE_PATH . '/src/helpers/google.php';
require_once BASE_PATH . '/src/helpers/mailer.php';
require_once BASE_PATH . '/src/helpers/institucional.php';
require_once BASE_PATH . '/src/core/geo.php';

// ── Portão geográfico (fora do BR → Turnstile) ──────────────────────────────────
// Roda em toda requisição, antes do router. Só age se TURNSTILE_SITE_KEY existir.
geo_gate();
