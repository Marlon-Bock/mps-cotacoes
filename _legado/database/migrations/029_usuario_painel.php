<?php
/**
 * Migration 029 — Adiciona `painel` em `usuario` (equipe interna MPS).
 * Marca a conta usada exclusivamente pelo painel de TV (/painel): entra no
 * sistema, mas só enxerga a tela de exibição — sem cotações, tarefas ou admin.
 * Executar via: php database/migrate_php.php
 */

try {
    $conn->query("ALTER TABLE usuario ADD COLUMN painel ENUM('S','N') NOT NULL DEFAULT 'N' AFTER ativo");
    echo "  + Coluna painel adicionada em usuario." . PHP_EOL;
} catch (mysqli_sql_exception $e) {
    if (str_contains($e->getMessage(), 'Duplicate column name')) {
        echo "  ~ Coluna painel já existe em usuario, pulando." . PHP_EOL;
    } else {
        throw $e;
    }
}
