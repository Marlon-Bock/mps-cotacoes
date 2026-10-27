<?php
/**
 * Runner simples de migrations — executa todos os .sql em database/migrations/
 * em ordem alfabética. Uso: php database/migrate.php
 */

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/src/config/env.php';
load_env(BASE_PATH . '/.env');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

// SEGURANÇA: migrations rodam exclusivamente contra o banco de TESTES.
// Jamais apontar para DB_NAME (produção) a partir deste script.
$database = env('DB_TEST');

if (!$database) {
    fwrite(STDERR, "DB_TEST não definido no .env — abortando para proteger produção." . PHP_EOL);
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

$files = glob(__DIR__ . '/migrations/*.sql');
sort($files);

foreach ($files as $file) {
    echo "Executando " . basename($file) . "... ";
    $sql = file_get_contents($file);

    try {
        if ($conn->multi_query($sql)) {
            do {
                if ($result = $conn->store_result()) {
                    $result->free();
                }
            } while ($conn->more_results() && $conn->next_result());
        }
        echo "OK" . PHP_EOL;
    } catch (mysqli_sql_exception $e) {
        echo "ERRO: " . $e->getMessage() . PHP_EOL;
    }
}

echo "Migrations concluídas." . PHP_EOL;
