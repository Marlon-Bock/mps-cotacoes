<?php
/**
 * Google OAuth2 + Calendar API v3 — via cURL puro, sem biblioteca cliente
 * (este projeto não usa Composer/vendor). Usado por /admin/visitas/agendar
 * pra sincronizar agendamentos com o Google Calendar do atendente-alvo.
 */

const GOOGLE_OAUTH_AUTH_URL      = 'https://accounts.google.com/o/oauth2/v2/auth';
const GOOGLE_OAUTH_TOKEN_URL     = 'https://oauth2.googleapis.com/token';
const GOOGLE_OAUTH_REVOKE_URL    = 'https://oauth2.googleapis.com/revoke';
const GOOGLE_CALENDAR_EVENTS_URL = 'https://www.googleapis.com/calendar/v3/calendars/primary/events';

/** Faz a chamada HTTP e devolve status + corpo já decodificado, sem ambiguidade em respostas vazias (ex: DELETE 204). */
function google_http(string $method, string $url, array $params = [], array $headers = []): array
{
    $ch   = curl_init();
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 10,
        CURLOPT_HTTPHEADER     => $headers,
    ];

    if ($method === 'GET') {
        $opts[CURLOPT_URL] = $params ? $url . '?' . http_build_query($params) : $url;
    } elseif ($method === 'POST' && !str_contains($url, 'googleapis.com/calendar')) {
        // Endpoints OAuth (token/revoke) esperam form-urlencoded, não JSON.
        $opts[CURLOPT_URL]        = $url;
        $opts[CURLOPT_POST]       = true;
        $opts[CURLOPT_POSTFIELDS] = http_build_query($params);
    } else {
        $opts[CURLOPT_URL]           = $url;
        $opts[CURLOPT_CUSTOMREQUEST] = $method;
        if ($params) {
            $opts[CURLOPT_POSTFIELDS]   = json_encode($params);
            $opts[CURLOPT_HTTPHEADER][] = 'Content-Type: application/json';
        }
    }

    curl_setopt_array($ch, $opts);
    $response = curl_exec($ch);
    $status   = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $erro     = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        error_log("[google_http] cURL erro ({$method} {$url}): {$erro}");
        return ['ok' => false, 'status' => 0, 'data' => null];
    }

    $data = $response !== '' ? json_decode($response, true) : null;
    $ok   = $status >= 200 && $status < 300;

    if (!$ok) {
        error_log("[google_http] HTTP {$status} ({$method} {$url}): {$response}");
    }

    return ['ok' => $ok, 'status' => $status, 'data' => $data];
}

function google_redirect_uri(): string
{
    return APP_URL . '/admin/visitas/google/callback';
}

/** Monta a URL de consentimento OAuth. $state protege o callback contra CSRF (assinado com o mesmo segredo do JWT de sessão). */
function google_auth_url(int $userId): string
{
    // 30 min pra cobrir consentimentos mais demorados (escolha de conta, 2FA, etc.) —
    // 10 min se mostrou curto demais em uso real e derrubava a conexão por expiração do state.
    $state = jwt_encode(['uid' => $userId, 'exp' => time() + 1800], env('JWT_SECRET'));

    $params = [
        'client_id'     => env('GOOGLE_CLIENT_ID'),
        'redirect_uri'  => google_redirect_uri(),
        'response_type' => 'code',
        'scope'         => 'https://www.googleapis.com/auth/calendar',
        'access_type'   => 'offline',
        'prompt'        => 'consent',
        'state'         => $state,
    ];

    return GOOGLE_OAUTH_AUTH_URL . '?' . http_build_query($params);
}

/** Valida o state recebido no callback. Retorna o user_id ou null se inválido/expirado/adulterado. */
function google_validar_state(string $state): ?int
{
    $payload = jwt_decode($state, env('JWT_SECRET'));
    return ($payload && isset($payload->uid)) ? (int) $payload->uid : null;
}

/** Troca o code do OAuth por access_token/refresh_token. Null se falhar. */
function google_exchange_code(string $code): ?array
{
    $resp = google_http('POST', GOOGLE_OAUTH_TOKEN_URL, [
        'code'          => $code,
        'client_id'     => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'redirect_uri'  => google_redirect_uri(),
        'grant_type'    => 'authorization_code',
    ]);

    return ($resp['ok'] && !empty($resp['data']['access_token'])) ? $resp['data'] : null;
}

/** Grava/atualiza (upsert) o token de um usuário. */
function google_save_token(int $userId, array $tokenData): void
{
    $accessToken  = $tokenData['access_token'];
    $refreshToken = $tokenData['refresh_token'] ?? null;
    $expiresAt    = date('Y-m-d H:i:s', time() + (int) ($tokenData['expires_in'] ?? 3600));

    if ($refreshToken) {
        db_execute(
            "INSERT INTO google_tokens (user_id, access_token, refresh_token, expires_at)
             VALUES (?, ?, ?, ?)
             ON DUPLICATE KEY UPDATE access_token = VALUES(access_token), refresh_token = VALUES(refresh_token), expires_at = VALUES(expires_at)",
            ['isss', $userId, $accessToken, $refreshToken, $expiresAt]
        );
    } else {
        // Google só manda refresh_token no primeiro consentimento — se não veio
        // (reconexão), preserva o refresh_token que já existia.
        db_execute(
            "INSERT INTO google_tokens (user_id, access_token, refresh_token, expires_at)
             VALUES (?, ?, '', ?)
             ON DUPLICATE KEY UPDATE access_token = VALUES(access_token), expires_at = VALUES(expires_at)",
            ['iss', $userId, $accessToken, $expiresAt]
        );
    }
}

function google_has_token(int $userId): bool
{
    return (bool) db_fetch_one('SELECT id FROM google_tokens WHERE user_id = ?', ['i', $userId]);
}

/** Retorna um access_token válido (renova via refresh_token se preciso), ou null se não conectado/renovação falhar. */
function google_get_valid_token(int $userId): ?string
{
    $row = db_fetch_one('SELECT access_token, refresh_token, expires_at FROM google_tokens WHERE user_id = ?', ['i', $userId]);
    if (!$row) {
        return null;
    }

    if (strtotime($row['expires_at']) > time() + 60) {
        return $row['access_token'];
    }

    if (empty($row['refresh_token'])) {
        return null;
    }

    $resp = google_http('POST', GOOGLE_OAUTH_TOKEN_URL, [
        'refresh_token' => $row['refresh_token'],
        'client_id'     => env('GOOGLE_CLIENT_ID'),
        'client_secret' => env('GOOGLE_CLIENT_SECRET'),
        'grant_type'    => 'refresh_token',
    ]);

    if (!$resp['ok'] || empty($resp['data']['access_token'])) {
        return null;
    }

    $novoToken = $resp['data']['access_token'];
    $expiresAt = date('Y-m-d H:i:s', time() + (int) ($resp['data']['expires_in'] ?? 3600));
    db_execute('UPDATE google_tokens SET access_token = ?, expires_at = ? WHERE user_id = ?', ['ssi', $novoToken, $expiresAt, $userId]);

    return $novoToken;
}

/** Desconecta a conta Google do usuário: revoga o token (best-effort) e apaga a linha local. */
function google_disconnect(int $userId): void
{
    $row = db_fetch_one('SELECT access_token FROM google_tokens WHERE user_id = ?', ['i', $userId]);
    if ($row) {
        google_http('POST', GOOGLE_OAUTH_REVOKE_URL, ['token' => $row['access_token']]);
    }
    db_execute('DELETE FROM google_tokens WHERE user_id = ?', ['i', $userId]);
}

/** Cria um evento no Google Calendar. Retorna ['id' => ..., 'htmlLink' => ...] ou null se falhar. */
function google_calendar_create_event(string $accessToken, array $evento): ?array
{
    $resp = google_http('POST', GOOGLE_CALENDAR_EVENTS_URL, $evento, ["Authorization: Bearer {$accessToken}"]);
    return ($resp['ok'] && !empty($resp['data']['id'])) ? $resp['data'] : null;
}

function google_calendar_update_event(string $accessToken, string $eventId, array $evento): bool
{
    $url  = GOOGLE_CALENDAR_EVENTS_URL . '/' . rawurlencode($eventId);
    $resp = google_http('PUT', $url, $evento, ["Authorization: Bearer {$accessToken}"]);
    return $resp['ok'];
}

function google_calendar_delete_event(string $accessToken, string $eventId): bool
{
    $url  = GOOGLE_CALENDAR_EVENTS_URL . '/' . rawurlencode($eventId);
    $resp = google_http('DELETE', $url, [], ["Authorization: Bearer {$accessToken}"]);
    // Google responde 410 Gone se o evento já não existe mais lá — trata como sucesso (fim desejado já alcançado).
    return $resp['ok'] || $resp['status'] === 410;
}
