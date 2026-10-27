<?php
/**
 * Migration 069 — Coluna recebe_email_incendio em usuario (preferência de
 * e-mail "Nova cotação de Incêndio", mesmo papel de recebe_email_condominial).
 * Exigida por email_preferencia_coluna('incendio') / notificar_cotacao_email().
 */

try {
    $conn->query("ALTER TABLE usuario ADD COLUMN recebe_email_incendio ENUM('S','N') NOT NULL DEFAULT 'N'");
    echo "  + Coluna recebe_email_incendio adicionada em usuario." . PHP_EOL;
} catch (Throwable $e) {
    if (str_contains($e->getMessage(), 'Duplicate column name')) {
        echo "  = Coluna recebe_email_incendio já existia em usuario." . PHP_EOL;
    } else {
        throw $e;
    }
}
