<?php
/**
 * Migration 035 — Adiciona coluna de URL de destino às notificações, pra
 * dar pra clicar numa notificação (sino ou popup do SO) e ir direto pra
 * cotação/chat correspondente.
 */

$alterations = [
    "ALTER TABLE notificacoes_mps ADD COLUMN url VARCHAR(255) NULL DEFAULT NULL",
];

foreach ($alterations as $sql) {
    try {
        $conn->query($sql);
        $col = explode('ADD COLUMN ', $sql)[1];
        $col = explode(' ', $col)[0];
        echo "  + Coluna {$col} adicionada." . PHP_EOL;
    } catch (mysqli_sql_exception $e) {
        if (str_contains($e->getMessage(), 'Duplicate column name')) {
            $col = explode('ADD COLUMN ', $sql)[1];
            $col = explode(' ', $col)[0];
            echo "  ~ Coluna {$col} já existe, pulando." . PHP_EOL;
        } else {
            throw $e;
        }
    }
}
