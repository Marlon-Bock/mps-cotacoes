<?php
/**
 * Suporte a cotação via embed (iframe no site do cliente, sem login).
 *
 * A imobiliária é identificada por um token público fixo (`imobiliarias.
 * embed_token`), nunca por sessão/JWT — dentro de um iframe cross-site o
 * cookie de sessão (SameSite=Lax) não é confiável, então o CSRF do embed
 * também não pode depender de sessão: usa HMAC stateless com JWT_SECRET.
 */

/**
 * Resolve o token de embed para a imobiliária e o usuário "ator" das
 * cotações (o admin da imobiliária). Encerra a requisição com 404 se o
 * token não existir ou o embed estiver desligado.
 */
function embed_resolver(string $token): object
{
    $imob = db_fetch_one(
        'SELECT * FROM imobiliarias WHERE embed_token = ? AND usa_embed = 1',
        ['s', $token]
    );

    if (!$imob) {
        embed_404();
    }

    $admin = db_fetch_one(
        "SELECT id FROM users WHERE imobiliaria_id = ? AND admin = 'S' AND ativo = 1 ORDER BY id ASC LIMIT 1",
        ['i', $imob['id']]
    );

    if (!$admin) {
        json_response(['success' => false, 'message' => 'Imobiliária sem usuário administrador cadastrado — embed indisponível.'], 503);
    }

    return (object) [
        'role'           => 'imobiliaria',
        'imobiliaria_id' => (int) $imob['id'],
        'uid'            => (int) $admin['id'],
        'imob_admin'     => true,
        'perm_cotar'     => 'S',
        'perm_suporte'   => 'S',
        'perm_aceitar'   => 'S',
        'perm_fianca'    => 'S',
        'perm_auto'      => 'S',
        'perm_condo'     => 'S',
        'perm_titulo'    => 'S',
        'perm_incendio'  => 'S',
        'perm_residencial'        => 'S',
        'perm_seguro_residencial' => 'S',
        'nome'           => 'Site da imobiliária (embed)',
        '_imobiliaria'   => $imob,
    ];
}

function embed_404(): never
{
    abort(404);
}

/** Chave derivada do JWT_SECRET, exclusiva para o CSRF stateless do embed. */
function embed_csrf_chave(): string
{
    return hash_hmac('sha256', 'embed-csrf', (string) env('JWT_SECRET'));
}

/**
 * Token CSRF stateless: HMAC do embed_token + janela de tempo (1h), sem
 * tocar em sessão/cookie — funciona mesmo com cookies de terceiros bloqueados.
 */
function embed_csrf_token(string $embed_token): string
{
    $janela = (int) floor(time() / 3600);
    return hash_hmac('sha256', $embed_token . ':' . $janela, embed_csrf_chave());
}

/** Valida o token CSRF do embed, aceitando a janela atual e a anterior. */
function embed_csrf_verify(string $embed_token): void
{
    $token = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
    $janela_atual = (int) floor(time() / 3600);

    foreach ([$janela_atual, $janela_atual - 1] as $janela) {
        $esperado = hash_hmac('sha256', $embed_token . ':' . $janela, embed_csrf_chave());
        if (hash_equals($esperado, (string) $token)) {
            return;
        }
    }

    json_response(['success' => false, 'message' => 'Token CSRF inválido.'], 403);
}

/** Token público fixo do embed — gerado uma única vez por imobiliária. */
function embed_gerar_token(): string
{
    return bin2hex(random_bytes(24));
}

/** Host da URL informada, sem esquema nem barra final (ex.: "cliente.com.br"). */
function embed_dominio_normalizar(string $url): string
{
    $host = parse_url(samiloca_url_normalizar($url), PHP_URL_HOST);
    return $host ? strtolower($host) : '';
}

/**
 * Valida a URL do site que a imobiliária quer autorizar para o embed:
 * bloqueia endereços privados/locais (SSRF, já que a URL vem de um
 * formulário e a checagem sai do servidor) e confirma que o domínio está
 * respondendo antes de liberar.
 *
 * @return array{ok:bool, dominio?:string, erro?:string}
 */
function embed_dominio_testar(string $urlInformada, int $timeout = 10): array
{

    $url = samiloca_url_normalizar($urlInformada);
    if ($url === '') {
        return ['ok' => false, 'erro' => 'Informe a URL do seu site.'];
    }
    if (!samiloca_url_publica($url)) {
        return ['ok' => false, 'erro' => 'Endereço inválido ou não público.'];
    }

    // Redirecionamentos conferidos um a um (ver http_get_url_publica).
    $r = http_get_url_publica($url, [
        CURLOPT_TIMEOUT        => $timeout,
        CURLOPT_SSL_VERIFYPEER => false,
    ]);
    $httpCode = $r['http'];
    $curlErr  = $r['erro'];

    if ($httpCode < 200 || $httpCode >= 500) {
        return ['ok' => false, 'erro' => 'Não foi possível confirmar que o site está no ar' . ($curlErr ? " ({$curlErr})." : '.')];
    }

    return ['ok' => true, 'dominio' => embed_dominio_normalizar($url)];
}

/**
 * Sobrescreve os headers de frame (definidos incondicionalmente em
 * src/bootstrap.php) para liberar o domínio autorizado da imobiliária.
 * Deve ser chamada antes de qualquer output, no topo da página de embed.
 */
function embed_apply_frame_headers(?string $dominio): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }

    $ancestors = "'self'";
    if ($dominio) {
        $dominio = preg_replace('#^https?://#', '', trim($dominio));
        $dominio = rtrim($dominio, '/');
        if ($dominio !== '') {
            $ancestors .= ' https://' . $dominio;
        }
    }

    // Quem monta os headers é o middleware CabecalhosSeguranca (troca o CSP e
    // tira o X-Frame-Options desta resposta).
    request()->attributes->set('embed_frame_ancestors', $ancestors);
}

/**
 * Código de acompanhamento da ficha enviada pelo embed: o formulário consulta a
 * situação (/api/embed/{token}/cotacao/fianca/{protocolo}/situacao?t=) sem login.
 * Assinado com o embed_token + protocolo — trocar o protocolo na URL não funciona.
 */
function embed_acompanhamento_token(string $embed_token, string $protocolo): string
{
    return substr(hash_hmac('sha256', 'embed-acompanhamento:' . $embed_token . ':' . $protocolo, embed_csrf_chave()), 0, 40);
}
