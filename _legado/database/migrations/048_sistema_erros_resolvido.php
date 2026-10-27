<?php
/**
 * Migration 048 — Marca de "corrigido" nos erros do sistema, pra distinguir
 * o que já foi tratado do que ainda precisa de atenção em /admin/sistema-erros.
 */

$colunas = [
    "ADD COLUMN resolvido TINYINT(1) NOT NULL DEFAULT 0 AFTER usuario_tipo",
    "ADD COLUMN resolvido_em DATETIME NULL AFTER resolvido",
    "ADD COLUMN resolvido_por VARCHAR(100) NULL AFTER resolvido_em",
];

foreach ($colunas as $alter) {
    try {
        $conn->query("ALTER TABLE sistema_erros {$alter}");
        echo "  + {$alter}" . PHP_EOL;
    } catch (mysqli_sql_exception $e) {
        if (str_contains($e->getMessage(), 'Duplicate column name')) {
            echo "  ~ Coluna já existe, pulando ({$alter})." . PHP_EOL;
        } else {
            throw $e;
        }
    }
}

try {
    $conn->query("ALTER TABLE sistema_erros ADD KEY idx_resolvido (resolvido)");
    echo "  + KEY idx_resolvido" . PHP_EOL;
} catch (mysqli_sql_exception $e) {
    if (str_contains($e->getMessage(), 'Duplicate key name')) {
        echo "  ~ Índice já existe, pulando." . PHP_EOL;
    } else {
        throw $e;
    }
}
