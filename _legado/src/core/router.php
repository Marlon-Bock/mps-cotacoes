<?php
/**
 * Router simples baseado em pattern matching de URI.
 *
 * Uso no front controller (public/index.php):
 *   route('GET', '/',                      'pages/index.php');
 *   route('GET', '/cotacao/{protocolo}',   'pages/cotacao.php');   // $params['protocolo']
 *   router_dispatch();
 */

$_ROUTES = [];

/**
 * Caminho da requisição atual já sem o base path e sem barra final.
 * As rotas são sempre declaradas a partir da raiz ('/login'), então tudo que
 * compara caminho com rota deve usar esta função, nunca o REQUEST_URI cru.
 */
function request_path(): string
{
    $uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';

    if (BASE_URI !== '' && str_starts_with($uri, BASE_URI)) {
        $uri = substr($uri, strlen(BASE_URI));
    }

    $uri = '/' . trim($uri, '/');

    return $uri === '//' ? '/' : $uri;
}

/**
 * Registra uma rota.
 *
 * @param string $method  GET | POST | GET+POST
 * @param string $pattern URI com placeholders opcionais: /cotacao/{protocolo}
 * @param string $file    Caminho relativo à BASE_PATH
 */
function route(string $method, string $pattern, string $file): void
{
    global $_ROUTES;

    foreach (explode('+', strtoupper($method)) as $m) {
        $_ROUTES[$m][] = ['pattern' => $pattern, 'file' => $file];
    }
}

/**
 * Tenta casar $uri com $pattern.
 * Retorna array de parâmetros capturados ou false se não casa.
 */
function router_match(string $pattern, string $uri): array|false
{
    // escapa o padrão e converte {param} em grupo nomeado
    $regex = preg_replace('/\{([a-z_]+)\}/', '(?P<$1>[^/]+)', $pattern);
    $regex = '@^' . $regex . '$@';

    if (!preg_match($regex, $uri, $matches)) {
        return false;
    }

    // filtra apenas os grupos nomeados (remove índices numéricos)
    return array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);
}

/**
 * Despacha a requisição atual.
 * Define $params global com os segmentos capturados da URL.
 * Inclui o arquivo da rota ou renderiza 404.
 */
function router_dispatch(): void
{
    global $_ROUTES, $params;

    $method = strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET');
    $uri    = request_path();

    $routes = $_ROUTES[$method] ?? [];

    foreach ($routes as $route) {
        $matched = router_match($route['pattern'], $uri);

        if ($matched !== false) {
            $params = $matched;
            $file   = BASE_PATH . '/' . ltrim($route['file'], '/');

            if (!is_file($file)) {
                error_log("[Router] Arquivo de rota não encontrado: {$file}");
                router_not_found();
                return;
            }

            require $file;
            return;
        }
    }

    router_not_found();
}

function router_not_found(): void
{
    http_response_code(404);
    $file = BASE_PATH . '/pages/errors/404.php';
    if (is_file($file)) require $file;
    else echo '<h1>404 — Página não encontrada</h1>';
}
