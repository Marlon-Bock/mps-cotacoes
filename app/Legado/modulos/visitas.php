<?php
/**
 * Helpers do módulo Visitas.
 *
 * jwt_encode()/jwt_decode(): o login não usa mais JWT, mas o OAuth do Google
 * Agenda (app/Legado/google.php → google_auth_url()/google_validar_state())
 * ainda assina o "state" com o JWT_SECRET. Eram de _legado/src/core/auth.php.
 * Guardados com function_exists pra não colidir se alguém trouxer o
 * auth.php antigo de volta.
 */

if (!function_exists('jwt_base64url_encode')) {
    function jwt_base64url_encode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }
}

if (!function_exists('jwt_base64url_decode')) {
    function jwt_base64url_decode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}

if (!function_exists('jwt_encode')) {
    function jwt_encode(array $payload, string $secret): string
    {
        $header    = jwt_base64url_encode(json_encode(['alg' => 'HS256', 'typ' => 'JWT']));
        $body      = jwt_base64url_encode(json_encode($payload));
        $signature = jwt_base64url_encode(
            hash_hmac('sha256', "{$header}.{$body}", $secret, true)
        );

        return "{$header}.{$body}.{$signature}";
    }
}

if (!function_exists('jwt_decode')) {
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
}
