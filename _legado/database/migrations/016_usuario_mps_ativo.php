<?php
/**
 * Migration 016 — Adiciona `ativo` em `usuario` (equipe interna MPS), mesmo
 * padrão já usado em `users.ativo` (migration 012) — permite ativar/inativar
 * um usuário MPS na lista, igual já existe do lado imobiliária.
 * Executar via: php database/migrate_php.php
 */

try {
    $conn->query("ALTER TABLE usuario ADD COLUMN ativo TINYINT(1) NOT NULL DEFAULT 1 AFTER admin");
    echo "  + Coluna ativo adicionada em usuario." . PHP_EOL;
} catch (mysqli_sql_exception $e) {
    if (str_contains($e->getMessage(), 'Duplicate column name')) {
        echo "  ~ Coluna ativo já existe em usuario, pulando." . PHP_EOL;
    } else {
        throw $e;
    }
}
