-- Documentos do Seguro Fiança depois da pré-aprovação (app/Legado/fianca_documentos.php):
-- um cartão por seguradora (link de biometria + orçamento em PDF, da MPS),
-- contrato de locação (imobiliária) e apólice (MPS). Os arquivos ficam em
-- cotacoes_anexos (tipo_arquivo 'proposta' = orçamento, 'contrato', 'apolice');
-- os links de biometria ganham tabela própria. `seguradora` = chave de
-- FIANCA_SEGURADORAS (NULL = enviado antes dos cartões por seguradora).
CREATE TABLE IF NOT EXISTS seguro_fianca_biometrias (
    id               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    seguro_fianca_id INT NOT NULL,
    protocolo        VARCHAR(20) NOT NULL,
    seguradora       VARCHAR(20) DEFAULT NULL,
    link             VARCHAR(2048) NOT NULL,
    situacao         VARCHAR(20) NOT NULL DEFAULT 'pendente' COMMENT 'pendente|concluida',
    criado_em        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    criado_por       VARCHAR(100) DEFAULT NULL,
    concluida_em     DATETIME DEFAULT NULL,
    concluida_por    VARCHAR(100) DEFAULT NULL,
    PRIMARY KEY (id),
    KEY idx_protocolo (protocolo),
    KEY idx_fianca (seguro_fianca_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE cotacoes_anexos ADD COLUMN seguradora VARCHAR(20) DEFAULT NULL AFTER tipo_arquivo;

-- Link antigo (1 por cotação, fluxo manual) vira a primeira biometria da tabela nova.
INSERT INTO seguro_fianca_biometrias (seguro_fianca_id, protocolo, link, situacao, criado_em, criado_por, concluida_em)
SELECT sf.id, sf.protocolo, sf.biometria_url,
       IF(sf.biometria_status = 'concluida', 'concluida', 'pendente'),
       COALESCE(sf.data_status_em_analise, sf.data_cadastro, NOW()), 'MPS',
       IF(sf.biometria_status = 'concluida', COALESCE(sf.data_atualizacao, NOW()), NULL)
  FROM seguro_fianca sf
 WHERE sf.biometria_url IS NOT NULL AND sf.biometria_url <> ''
   AND NOT EXISTS (SELECT 1 FROM seguro_fianca_biometrias b WHERE b.protocolo = sf.protocolo);

-- Propostas antigas gravadas com o MIME no lugar do tipo — são orçamentos.
UPDATE cotacoes_anexos SET tipo_arquivo = 'proposta' WHERE tipo_arquivo = 'application/pdf' OR tipo_arquivo IS NULL;
