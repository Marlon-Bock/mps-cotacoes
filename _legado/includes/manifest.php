<?php
/**
 * Manifest do PWA — servido via PHP (rota /manifest.json) porque o Nginx
 * bloqueia acesso direto a arquivos *.json (ver snippets/samiblock.conf).
 */

header('Content-Type: application/manifest+json; charset=utf-8');
header('Cache-Control: public, max-age=86400');

echo json_encode([
    'name'             => 'Mesquita Prime - Cotações',
    'short_name'       => 'MPS Cotações',
    'description'      => 'Portal de cotações de seguros Mesquita Prime',
    'start_url'        => url('/'),
    'scope'            => url('/'),
    'display'          => 'standalone',
    'orientation'      => 'portrait-primary',
    'background_color' => '#ffffff',
    'theme_color'      => '#7F1B1B',
    'lang'             => 'pt-BR',
    'icons'            => [
        [
            'src'     => url('/assets/img/logo-mark.png'),
            'sizes'   => '192x192',
            'type'    => 'image/png',
            'purpose' => 'any maskable',
        ],
    ],
    'categories' => ['finance', 'business'],
], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
