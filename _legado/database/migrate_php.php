<?php
/**
 * Runner de migrations PHP (arquivos .php em database/migrations/).
 * Uso: php database/migrate_php.php
 */

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/src/config/env.php';
load_env(BASE_PATH . '/.env');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$database = env('DB_TEST');

if (!$database) {
    fwrite(STDERR, "DB_TEST não definido no .env — abortando." . PHP_EOL);
    exit(1);
}

$conn = new mysqli(
    env('DB_HOST'),
    env('DB_USER'),
    env('DB_PASS'),
    $database,
    (int) env('DB_PORT', '3306')
);
$conn->set_charset('utf8mb4');

echo "Conectado ao banco: {$database} @ " . env('DB_HOST') . PHP_EOL;

$files = glob(__DIR__ . '/migrations/*.php');
sort($files);

foreach ($files as $file) {
    echo "Executando " . basename($file) . "..." . PHP_EOL;
    try {
        require $file;
        echo "OK" . PHP_EOL;
    } catch (Throwable $e) {
        echo "ERRO: " . $e->getMessage() . PHP_EOL;
    }
}

echo "Concluído." . PHP_EOL;
