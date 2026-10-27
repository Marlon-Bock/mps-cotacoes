<?php
/**
 * Migration 037 — Adiciona campo de observações/detalhes internos às
 * campanhas (visível só no admin, pra registrar contexto que não vai pro
 * popup/imagem).
 */

$alterations = [
    "ALTER TABLE campanhas ADD COLUMN observacoes TEXT NULL DEFAULT NULL AFTER data_fim",
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
