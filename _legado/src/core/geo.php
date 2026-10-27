<?php
/**
 * Portão geográfico + Cloudflare Turnstile.
 *
 * Regra: acessos vindos de FORA do Brasil precisam passar por uma verificação
 * (Turnstile) antes de usar o sistema. Acessos do Brasil entram direto.
 *
 * O país vem do header `CF-IPCountry` (Cloudflare, com o proxy ativo). Só entra
 * em vigor quando TURNSTILE_SITE_KEY está configurado; sem isso, não bloqueia
 * nada (fail-open), pra nunca deixar o sistema inacessível por engano.
 */

/** IP real do cliente (atrás do Cloudflare vem em CF-Connecting-IP). */
function cliente_ip(): string
{
    return $_SERVER['HTTP_CF_CONNECTING_IP']
        ?? $_SERVER['HTTP_X_REAL_IP']
        ?? $_SERVER['REMOTE_ADDR']
        ?? '';
}

/** País do cliente pelo header do Cloudflare. null = desconhecido. */
function geo_pais(): ?string
{
    $c = strtoupper(trim($_SERVER['HTTP_CF_IPCOUNTRY'] ?? ''));
    if ($c === '' || $c === 'XX' || $c === 'T1') return null;  // XX/T1 = desconhecido/Tor
    return $c;
}

/** Verifica o token do Turnstile junto ao Cloudflare. */
function turnstile_verificar(string $token, string $ip): bool
{
    $secret = env('TURNSTILE_SECRET_KEY', '');
    if ($secret === '' || $token === '') return false;

    $ch = curl_init('https://challenges.cloudflare.com/turnstile/v0/siteverify');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query([
            'secret'   => $secret,
            'response' => $token,
            'remoteip' => $ip,
        ]),
    ]);
    $raw = curl_exec($ch);
    curl_close($ch);

    $data = json_decode((string) $raw, true);
    return !empty($data['success']);
}

/**
 * Portão executado em TODA requisição (chamado no bootstrap, antes do router).
 * Redireciona estrangeiros não verificados para /verificacao.
 */
function geo_gate(): void
{
    if (PHP_SAPI === 'cli') return;                       // CLI (cron/migrate) nunca é barrado
    if (env('TURNSTILE_SITE_KEY', '') === '') return;     // recurso desligado sem a site key
    if (!empty($_SESSION['geo_ok'])) return;              // já verificado nesta sessão

    // IPs locais/privados/reservados nunca são desafiados (dev, rede interna).
    $ip = cliente_ip();
    if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
        return;
    }

    // IPs de teste: forçados a passar pela verificação mesmo estando no BR.
    $forcar  = array_filter(array_map('trim', explode(',', env('GEO_FORCE_IPS', ''))));
    $forcado = in_array($ip, $forcar, true);

    $pais = geo_pais();
    if (!$forcado && ($pais === null || $pais === 'BR')) return;  // BR/indeterminado libera, exceto IPs de teste

    // Não redireciona a própria página de verificação (evita loop).
    if (str_starts_with(request_path(), '/verificacao')) return;

    // Guarda o destino original para voltar após a verificação. Sem o base path:
    // quem consome usa redirect(), que reaplica o prefixo.
    $qs = $_SERVER['QUERY_STRING'] ?? '';
    $_SESSION['geo_destino'] = request_path() . ($qs !== '' ? '?' . $qs : '');
    redirect('/verificacao');
}
