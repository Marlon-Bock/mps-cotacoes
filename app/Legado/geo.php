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

/**
 * IP real do cliente. O request()->ip() do Laravel já resolve o Cloudflare
 * (trustProxies em bootstrap/app.php, só pra rede dele) — ler o
 * CF-Connecting-IP direto deixava qualquer um inventar o IP acessando o
 * servidor sem passar pelo Cloudflare.
 */
function cliente_ip(): string
{
    return (string) (request()->ip() ?? '');
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

// O portão (geo_gate) virou o middleware App\Http\Middleware\PortaoGeografico.
