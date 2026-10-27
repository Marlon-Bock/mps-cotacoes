<?php
/**
 * Migration 064 — Tabela seguro_incendio (cabeçalho da remessa de cotação
 * de Seguro Incêndio). Os imóveis em si ficam em seguro_incendio_imoveis
 * (migration 065) — 1 protocolo pode ter N imóveis.
 */

$sql = "CREATE TABLE IF NOT EXISTS seguro_incendio (
    id INT AUTO_INCREMENT PRIMARY KEY,
    protocolo VARCHAR(30) NOT NULL,
    imobiliaria_id INT NOT NULL,
    usuario_id INT DEFAULT NULL,
    atendente_id INT DEFAULT NULL,
    status VARCHAR(60) NOT NULL DEFAULT 'Aguardando análise',
    data_cadastro DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    data_atualizacao DATETIME DEFAULT NULL,
    UNIQUE KEY uq_protocolo (protocolo),
    KEY idx_imobiliaria (imobiliaria_id),
    KEY idx_atendente (atendente_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$conn->query($sql);
echo "  + Tabela seguro_incendio criada (ou já existia)." . PHP_EOL;
