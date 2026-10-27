<?php
/**
 * Migration 093 — Bloqueio de spam dos formulários do site (leads_site).
 * Um IP que manda 5 leads em 20 minutos é bloqueado, com punição crescente:
 *   1ª vez → 3 horas · 2ª vez → 24 horas · 3ª vez → permanente
 * (bloqueado_ate NULL = permanente). Uma linha por IP; `nivel` guarda quantas
 * vezes ele já foi bloqueado. Quem grava é o public/api/leads.php do site.
 * Para desbloquear um IP: DELETE FROM leads_site_bloqueios WHERE ip = '...'.
 * Idempotente.
 */

$conn->query(
    "CREATE TABLE IF NOT EXISTS leads_site_bloqueios (
        id            INT UNSIGNED NOT NULL AUTO_INCREMENT,
        ip            VARCHAR(45)  NOT NULL,
        nivel         TINYINT UNSIGNED NOT NULL DEFAULT 1 COMMENT '1 = 3h, 2 = 24h, 3 = permanente',
        bloqueado_ate DATETIME     NULL COMMENT 'NULL = permanente',
        motivo        VARCHAR(255) NULL,
        criado_em     DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
        atualizado_em DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY uk_leads_site_bloqueios_ip (ip)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
);

echo "  + Tabela leads_site_bloqueios pronta." . PHP_EOL;
