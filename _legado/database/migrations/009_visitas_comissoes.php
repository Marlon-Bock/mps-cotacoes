<?php
/**
 * Migration 009 — Adiciona modo (percentual/valor) e valor fixo em
 * comissoes_imobiliarias, pra Relatório e Histórico de Visitas permitirem
 * escolher entre comissão em % ou em R$ por tipo de seguro.
 * Executar via: php database/migrate_php.php
 */

$alterations = [
    "ALTER TABLE comissoes_imobiliarias ADD COLUMN modo ENUM('percentual','valor') NOT NULL DEFAULT 'percentual' AFTER percentual",
    "ALTER TABLE comissoes_imobiliarias ADD COLUMN valor DECIMAL(10,2) NULL DEFAULT NULL AFTER modo",
];

foreach ($alterations as $sql) {
    try {
        $conn->query($sql);
        $col = explode('ADD COLUMN ', $sql)[1];
        $col = explode(' ', $col)[0];
        echo "  + Coluna {$col} adicionada em comissoes_imobiliarias." . PHP_EOL;
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
