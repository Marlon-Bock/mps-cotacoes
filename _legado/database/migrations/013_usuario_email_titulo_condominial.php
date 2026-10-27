<?php
/**
 * Migration 013 — Usuários MPS: adiciona recebe_email_titulo e
 * recebe_email_condominial na tabela `usuario`. A tela de preferências de
 * e-mail passou a refletir os 4 tipos reais de cotação (Fiança, Auto, Título
 * de Capitalização, Condominial) em vez da divisão antiga Residencial/
 * Comercial — Fiança já foi unificada, faltavam Título e Condominial.
 * Executar via: php database/migrate_php.php
 */

$colunas = [
    'recebe_email_titulo'      => "ALTER TABLE usuario ADD COLUMN recebe_email_titulo ENUM('S','N') NOT NULL DEFAULT 'N' AFTER recebe_email_comercial",
    'recebe_email_condominial' => "ALTER TABLE usuario ADD COLUMN recebe_email_condominial ENUM('S','N') NOT NULL DEFAULT 'N' AFTER recebe_email_titulo",
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
