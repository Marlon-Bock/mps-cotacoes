<?php
/**
 * Migration 053 — Remove as colunas pottencial_* de seguro_fianca, criadas
 * pela migration 040 (integração Pottencial removida do código — ver commit
 * "Remove toda a integração com Segimob e Pottencial do Seguro Fiança").
 * Nada mais lê/grava nelas.
 */

$colunas = [
    'pottencial_quote_id',
    'pottencial_status',
    'pottencial_premio_comercial',
    'pottencial_premio_bruto',
    'pottencial_erro',
    'pottencial_enviado_em',
];

foreach ($colunas as $col) {
    try {
        $conn->query("ALTER TABLE seguro_fianca DROP COLUMN {$col}");
        echo "  - Coluna {$col} removida." . PHP_EOL;
    } catch (mysqli_sql_exception $e) {
        if (str_contains($e->getMessage(), "check that column/key exists") || str_contains($e->getMessage(), "Can't DROP")) {
            echo "  ~ Coluna {$col} já não existe, pulando." . PHP_EOL;
        } else {
            throw $e;
        }
    }
}
