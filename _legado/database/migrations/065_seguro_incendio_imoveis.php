<?php
/**
 * Migration 065 — Tabela seguro_incendio_imoveis: os imóveis de uma
 * remessa de Seguro Incêndio (1 protocolo pode ter N imóveis, todos com o
 * mesmo peso — não tem "imóvel principal" vs "imóvel extra").
 */

$sql = "CREATE TABLE IF NOT EXISTS seguro_incendio_imoveis (
    id INT AUTO_INCREMENT PRIMARY KEY,
    seguro_incendio_id INT NOT NULL,
    numero_imovel INT NOT NULL DEFAULT 1,
    locador VARCHAR(150) DEFAULT NULL,
    cpf_cnpj_locador VARCHAR(20) DEFAULT NULL,
    locatario VARCHAR(150) DEFAULT NULL,
    cpf_cnpj_locatario VARCHAR(20) DEFAULT NULL,
    tipo_imovel VARCHAR(60) DEFAULT NULL,
    endereco VARCHAR(200) DEFAULT NULL,
    numero VARCHAR(20) DEFAULT NULL,
    complemento VARCHAR(100) DEFAULT NULL,
    bairro VARCHAR(100) DEFAULT NULL,
    cidade VARCHAR(100) DEFAULT NULL,
    uf CHAR(2) DEFAULT NULL,
    cep VARCHAR(10) DEFAULT NULL,
    metragem DECIMAL(10,2) DEFAULT NULL,
    valor_aluguel DECIMAL(10,2) DEFAULT NULL,
    valor_vendaval DECIMAL(10,2) DEFAULT NULL,
    parcelas INT DEFAULT NULL,
    codigo_imovel_sami VARCHAR(30) DEFAULT NULL,
    KEY idx_seguro_incendio_id (seguro_incendio_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$conn->query($sql);
echo "  + Tabela seguro_incendio_imoveis criada (ou já existia)." . PHP_EOL;
