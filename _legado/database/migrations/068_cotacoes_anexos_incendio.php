<?php
/**
 * Migration 068 — Tabela cotacoes_anexos_incendio (propostas enviadas pela
 * MPS para cotações de Seguro Incêndio), mesmo papel de
 * cotacoes_anexos_condominial/_titulo. Exigida por cotacao-proposta.php,
 * que faz SELECT/INSERT em {$tbl_anexos} = $resolvido['tabela_anexos'].
 */

$sql = "CREATE TABLE IF NOT EXISTS cotacoes_anexos_incendio (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    protocolo VARCHAR(30) NOT NULL,
    nome_arquivo VARCHAR(255) NOT NULL,
    caminho_arquivo VARCHAR(255) NOT NULL,
    tipo_arquivo VARCHAR(60) DEFAULT NULL,
    data_envio DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    usuario_envio VARCHAR(100) DEFAULT NULL,
    status_aprovacao VARCHAR(30) NOT NULL DEFAULT 'Aguardando',
    KEY idx_protocolo (protocolo),
    CONSTRAINT cotacoes_anexos_incendio_fk_protocolo FOREIGN KEY (protocolo)
        REFERENCES seguro_incendio (protocolo) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

$conn->query($sql);
echo "  + Tabela cotacoes_anexos_incendio criada (ou já existia)." . PHP_EOL;
