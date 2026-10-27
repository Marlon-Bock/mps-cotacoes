<?php
/**
 * Migration 015 — Usuários MPS: adiciona perm_titulo e perm_condominial na
 * tabela `usuario`. O painel de Permissões passou a refletir os 4 tipos
 * reais de cotação (Fiança unificada, Auto, Título de Capitalização,
 * Condominial) em vez da divisão antiga Residencial/Comercial — faltavam
 * Título e Condominial (mesmo caso das colunas de e-mail, migration 013).
 * Executar via: php database/migrate_php.php
 */

$colunas = [
    'perm_titulo'      => "ALTER TABLE usuario ADD COLUMN perm_titulo ENUM('S','N') NOT NULL DEFAULT 'N' AFTER perm_comercial",
    'perm_condominial' => "ALTER TABLE usuario ADD COLUMN perm_condominial ENUM('S','N') NOT NULL DEFAULT 'N' AFTER perm_titulo",
];

foreach ($colunas as $col => $sql) {
    try {
        $conn->query($sql);
        echo "  + Coluna {$col} adicionada em usuario." . PHP_EOL;
    } catch (mysqli_sql_exception $e) {
        if (str_contains($e->getMessage(), 'Duplicate column name')) {
            echo "  ~ Coluna {$col} já existe em usuario, pulando." . PHP_EOL;
        } else {
            throw $e;
        }
    }
}
