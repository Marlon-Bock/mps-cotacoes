<?php
/**
 * Migration 024 — coluna `ativo` em tarefas_status.
 * Permite DESATIVAR um status (some das opções/menus) sem excluir, preservando
 * as tarefas que já o usam. Executar via: php database/migrate_php.php
 */
try {
    $conn->query("ALTER TABLE tarefas_status ADD COLUMN ativo TINYINT(1) NOT NULL DEFAULT 1 AFTER is_final");
    echo "  + Coluna ativo adicionada em tarefas_status." . PHP_EOL;
} catch (mysqli_sql_exception $e) {
    if (str_contains($e->getMessage(), 'Duplicate column name')) {
        echo "  ~ Coluna ativo já existe, pulando." . PHP_EOL;
    } else {
        throw $e;
    }
}
