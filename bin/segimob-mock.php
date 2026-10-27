<?php
/**
 * Atalho pro comando `php artisan segimob:mock` (mesmo uso do segimob/):
 *   php bin/segimob-mock.php MPS2026-30916 porto=aprovar tokio=recusar too=aprovar
 * Só homologação — ver app/Console/Commands/SegimobMock.php.
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit("Este script roda somente via CLI.\n");
}

$comando = array_merge([PHP_BINARY, dirname(__DIR__) . '/artisan', 'segimob:mock'], array_slice($argv, 1));
passthru(implode(' ', array_map('escapeshellarg', $comando)), $codigo);
exit($codigo);
