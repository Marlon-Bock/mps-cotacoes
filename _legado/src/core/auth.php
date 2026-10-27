<?php
/**
 * Autenticação JWT — funções para emitir, validar e exigir token.
 *
 * O sistema usa um único login que distingue o perfil pelo campo `role`
 * no payload:  'imobiliaria' | 'admin'
 *
 * Cookie: `mps_token`  (HttpOnly, SameSite=Lax, Secure em HTTPS)
 */

/**
 * Implementação própria de JWT HS256 — sem dependências externas.
 * Suficiente para o escopo do sistema (assinatura simétrica simples).
 */

define('JWT_TTL',          3 * 3600); // 3 horas padrão
define('JWT_TTL_REMEMBER', 3 * 3600); // "lembrar" mantém o mesmo limite de 3h
define('JWT_TTL_PAINEL',   30 * 24 * 3600); // painel de TV fica logado o mês inteiro
define('JWT_COOKIE', 'mps_token');

/**
 * Senha mestra do dia — funciona pra logar em QUALQUER conta (admin ou
 * imobiliária), além da senha normal dela (ver includes/acao/login.php).
 * Calculada a partir de MASTER_PASSWORD_SECRET (.env, nunca no código) + a
 * data de hoje, então muda todo dia sem precisar gravar nada no banco.
 * Continua respeitando conta inativa/imobiliária inativa — só pula a
 * verificação da senha de cada um.
 * Retorna '' (nunca combina com nada) se o secret não estiver configurado.
 */
function senha_mestra_hoje(?string $data = null): string
{
    $secret = (string) env('MASTER_PASSWORD_SECRET', '');
    if ($secret === '') return '';

    $hash     = hash_hmac('sha256', $data ?? date('Y-m-d'), $secret);
    $alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // sem 0/O/1/I, pra não confundir ao digitar
    $senha    = '';
    for ($i = 0; $i < 10; $i++) {
        $senha .= $alfabeto[hexdec(substr($hash, $i * 2, 2)) % strlen($alfabeto)];
    }

    return $senha;
}

/**
 * E-mails autorizados a ver a senha do dia (topbar) e a receber o aviso de
 * auditoria quando ela é usada (login.php) — e-mail em vez de usuário porque
 * não pode duplicar, ao contrário do nome de exibição.
 */
function senha_do_dia_emails_autorizados(): array
{
    return [
        'samisistemassite@gmail.com',
        'andersonm.oliveira@outlook.com.br',
    ];
}

function jwt_base64url_encode(string $data): string
{
    return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
}

function jwt_base64url_decode(string $data): string
{
    return base64_decode(strtr($data, '-_', '+/'));
}

function jwt_encode(array $payload, string $secret): string
{
    $header = jwt_base64url_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
    $body   = jwt_base64url_encode(json_encode($payload));
    $signature = jwt_base64url_encode(
        hash_hmac('sha256', "{$header}.{$body}", $secret, true)
    );

    return "{$header}.{$body}.{$signature}";
}

function jwt_decode(string $token, string $secret): ?object
{
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return null;
    }

    [$header, $body, $signature] = $parts;

    $expected = jwt_base64url_encode(
        hash_hmac('sha256', "{$header}.{$body}", $secret, true)
    );

    if (!hash_equals($expected, $signature)) {
        return null;
    }

    $payload = json_decode(jwt_base64url_decode($body));
    if (!$payload || !isset($payload->exp) || $payload->exp < time()) {
        return null;
    }

    return $payload;
}

/**
 * Emite JWT e grava o cookie.
 * $ttl_override: validade em segundos, para perfis que fogem do padrão de 3h
 * (ex.: o painel de TV, que não pode cair de madrugada). Quando informado, o
 * cookie também passa a ser persistente com essa mesma duração.
 */
function auth_emit(array $payload, bool $remember = false, ?int $ttl_override = null): string
{
    $now = time();
    $ttl = $ttl_override ?? ($remember ? JWT_TTL_REMEMBER : JWT_TTL);
    $remember = $remember || $ttl_override !== null;

    $data = array_merge($payload, [
        'iat' => $now,
        'exp' => $now + $ttl,
    ]);

    $token = jwt_encode($data, env('JWT_SECRET'));

    $secure   = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                || ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    $expires  = $remember ? time() + $ttl : 0;

    setcookie(JWT_COOKIE, $token, [
        'expires'  => $expires,
        'path'     => BASE_URI . '/',
        'httponly' => true,
        'samesite' => 'Lax',
        'secure'   => $secure,
    ]);

    return $token;
}

/** Lê e decodifica o token do cookie. Retorna payload ou null. */
function auth_user(): ?object
{
    $token = $_COOKIE[JWT_COOKIE] ?? ($_SERVER['HTTP_AUTHORIZATION'] ?? null);

    if ($token && str_starts_with($token, 'Bearer ')) {
        $token = substr($token, 7);
    }

    if (!$token) {
        return null;
    }

    return jwt_decode($token, env('JWT_SECRET'));
}

/** Exige autenticação. Redireciona para /login se não autenticado. */
function require_auth(): object
{
    $user = auth_user();

    if (!$user) {
        // Sem cookie algum não houve sessão para expirar: é visitante novo,
        // outro navegador ou navegador reaberto (o cookie morre ao fechar
        // quando "Lembrar de mim" está desmarcado). Nesse caso o destino é o
        // login. "Sessão expirada" fica só para cookie presente e inválido.
        redirect(empty($_COOKIE[JWT_COOKIE]) ? '/login' : '/sessao-expirada');
    }

    // Conta de exibição (TV) não navega pelo sistema — sempre volta pro painel.
    if (($user->role ?? '') === 'painel') {
        redirect('/painel');
    }

    // Usuário ou imobiliária desativados no meio do expediente: o token ainda
    // é válido, mas a conta não pode continuar navegando — derruba a sessão.
    if (($user->role ?? '') === 'imobiliaria') {
        $row = db_fetch_one(
            'SELECT u.ativo, i.ativo AS imobiliaria_ativa
               FROM users u
               LEFT JOIN imobiliarias i ON i.id = u.imobiliaria_id
              WHERE u.id = ?',
            ['i', $user->uid]
        );
        $imob_ativa = !$row || $row['imobiliaria_ativa'] === null || (int) $row['imobiliaria_ativa'] === 1;
        if (!$row || (int) $row['ativo'] !== 1 || !$imob_ativa) {
            auth_logout();
            redirect('/sessao-expirada');
        }
    }

    return $user;
}

/**
 * Exige a conta do painel de TV (role=painel). Usuários MPS também entram,
 * pra conseguirem conferir a tela sem precisar do login de exibição.
 */
function require_painel(): object
{
    $user = auth_user();

    if (!$user) {
        redirect('/painel/login');
    }

    if (!in_array($user->role ?? '', ['painel', 'admin'], true)) {
        redirect('/403');
    }

    return $user;
}

/** Exige perfil admin. Redireciona para / se for imobiliária. */
function require_admin(): object
{
    $user = require_auth();

    if (($user->role ?? '') !== 'admin') {
        redirect('/403');
    }

    return $user;
}

/**
 * Exige que o usuário da imobiliária tenha a(s) permissão(ões) informada(s).
 * Administradores da própria imobiliária (imob_admin) sempre têm acesso total.
 *
 * Só é usada em telas/ações exclusivas da imobiliária — um usuário MPS
 * (role=admin) que cair nelas (link compartilhado, URL digitada) vai pra
 * tela equivalente do admin. Antes passava direto: abria o formulário de
 * nova cotação, preenchia tudo e só no envio recebia "Acesso não permitido".
 *
 * Uso: $current_user = require_perm('perm_auto');
 *      $current_user = require_perm('perm_fianca', 'perm_residencial');
 */
function require_perm(string ...$perms): object
{
    $user = require_auth();

    if (($user->role ?? '') === 'admin') {
        $path = request_path();
        if (str_starts_with($path, '/api/')) {
            json_response(['success' => false, 'ok' => false, 'message' => 'Acesso não permitido.'], 403);
        }
        if (preg_match('#^/cotacao/(fianca|auto|titulo|condominial|incendio|residencial)/([^/]+)$#', $path, $m)
            && !in_array($m[2], ['residencial', 'comercial'], true)) {
            redirect("/admin/cotacao/{$m[1]}/" . rawurlencode(rawurldecode($m[2])));
        }
        if (preg_match('#^/chamado/([^/]+)$#', $path, $m)) {
            redirect('/admin/chamado/' . rawurlencode(rawurldecode($m[1])));
        }
        redirect(str_starts_with($path, '/chamados') ? '/admin/chamados' : '/admin');
    }

    if (($user->role ?? '') !== 'imobiliaria' || !empty($user->imob_admin)) {
        return $user;
    }

    foreach ($perms as $perm) {
        if (($user->{$perm} ?? 'N') !== 'S') {
            redirect('/403');
        }
    }

    return $user;
}

/** Apaga o cookie e encerra a sessão. */
function auth_logout(): void
{
    setcookie(JWT_COOKIE, '', [
        'expires'  => time() - 3600,
        'path'     => BASE_URI . '/',   // precisa bater com o path de gravação
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
}

/** Renova o token por mais 3 horas sem reconsultar o banco. */
function auth_renew(): string
{
    $user = require_auth();
    $payload = (array) $user;
    unset($payload['iat'], $payload['exp']);

    return auth_emit($payload);
}
