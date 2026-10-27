<?php
/**
 * Migration 001b — Adiciona colunas ao seguro_fianca para o novo workflow.
 * Executar via: php database/migrate_php.php
 */

// Este arquivo é executado pelo migrate_php.php

$alterations = [
    "ALTER TABLE seguro_fianca ADD COLUMN pre_analise_resultado VARCHAR(20) NULL DEFAULT NULL COMMENT 'aceita|recusada'",
    "ALTER TABLE seguro_fianca ADD COLUMN pre_analise_observacao TEXT NULL DEFAULT NULL",
    "ALTER TABLE seguro_fianca ADD COLUMN biometria_link VARCHAR(500) NULL DEFAULT NULL",
    "ALTER TABLE seguro_fianca ADD COLUMN biometria_status VARCHAR(30) NULL DEFAULT NULL COMMENT 'pendente|enviado|concluida|dispensada'",
    "ALTER TABLE seguro_fianca ADD COLUMN recusa_motivo TEXT NULL DEFAULT NULL",
    "ALTER TABLE seguro_fianca ADD COLUMN data_aceite DATETIME NULL DEFAULT NULL",
    "ALTER TABLE seguro_fianca ADD COLUMN aceite_usuario_id INT NULL DEFAULT NULL",
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
