<?php
/**
 * Migration 066 — Coluna perm_incendio na tabela usuario (permissão do
 * lado MPS-admin pra gerenciar Seguro Incêndio). Do lado imobiliária
 * (tabela users) essa coluna já existe.
 */

$sql = "ALTER TABLE usuario ADD COLUMN perm_incendio ENUM('S','N') NOT NULL DEFAULT 'N'";

try {
    $conn->query($sql);
    echo "  + Coluna perm_incendio adicionada em usuario." . PHP_EOL;
} catch (mysqli_sql_exception $e) {
    if (str_contains($e->getMessage(), 'Duplicate column name')) {
        echo "  ~ Coluna perm_incendio já existe em usuario, pulando." . PHP_EOL;
    } else {
        throw $e;
    }
}
