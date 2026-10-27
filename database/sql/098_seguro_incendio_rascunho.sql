-- Rascunho do Seguro Incêndio (vindo do cotacoes/, commit 09f074d — migration 083 lá).
-- Diferente dos outros produtos (1 coluna por campo), uma cotação de incêndio
-- tem N imóveis, então os blocos do formulário ficam em imoveis_json
-- (ver rascunho_config('incendio')).
CREATE TABLE IF NOT EXISTS seguro_incendio_rascunho (
    id             INT UNSIGNED NOT NULL AUTO_INCREMENT,
    created_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    nome_rascunho  VARCHAR(160) NOT NULL,
    usuario_id     INT UNSIGNED DEFAULT NULL,
    imobiliaria_id INT UNSIGNED DEFAULT NULL,
    protocolo      VARCHAR(30) DEFAULT NULL,
    imoveis_json   LONGTEXT DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_usuario (usuario_id),
    KEY idx_imobiliaria (imobiliaria_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
