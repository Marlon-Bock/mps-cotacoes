-- 096 — Integração Segimob (Seguro Fiança) e canal de cotação por imobiliária/produto.
-- Rodar uma vez em cada banco. A Segimob só faz a análise (pré-aprovado/recusado —
-- app/Legado/segimob/status.php); a ficha cotada nela grava seguro_fianca.origem = 'segimob'.

-- Canal de cotação de cada produto por imobiliária ('manual' | 'api' | 'segimob' — os
-- canais válidos de cada produto ficam em app/Legado/canais.php). Sem linha = 'manual'.
CREATE TABLE IF NOT EXISTS imobiliaria_canais (
    imobiliaria_id INT NOT NULL,
    produto        VARCHAR(30) NOT NULL,
    canal          VARCHAR(20) NOT NULL DEFAULT 'manual',
    atualizado_em  DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (imobiliaria_id, produto)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Código da imobiliária (estipulante) na Segimob — sem ele a ficha não é cotada lá.
ALTER TABLE imobiliarias ADD COLUMN segimob_estipulante INT DEFAULT NULL;

-- Tipo do imóvel (a Segimob exige; apartamento também exige complemento).
ALTER TABLE seguro_fianca ADD COLUMN tipo_imovel VARCHAR(60) DEFAULT NULL;

CREATE TABLE IF NOT EXISTS `segimob_fichas` (
  `seguro_fianca_id` int(11) NOT NULL,
  `codigo_seguro` int(11) DEFAULT NULL,
  `estipulante` int(11) DEFAULT NULL,
  `status_codigo` varchar(60) DEFAULT NULL,
  `status_descricao` varchar(255) DEFAULT NULL,
  `erro` text,
  `enviado_em` datetime DEFAULT NULL,
  `analise_concluida_em` datetime DEFAULT NULL,
  `atualizado_em` datetime DEFAULT NULL,
  `cotacao_json` text,
  PRIMARY KEY (`seguro_fianca_id`),
  KEY `idx_enviado_em` (`enviado_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
;
CREATE TABLE IF NOT EXISTS `segimob_cotacoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `seguro_fianca_id` int(11) NOT NULL,
  `seguradora_codigo` int(11) NOT NULL,
  `seguradora_nome` varchar(120) DEFAULT NULL,
  `status_codigo` int(11) DEFAULT NULL,
  `status_descricao` varchar(255) DEFAULT NULL,
  `motivo_recusa` text,
  `pendente_biometria` tinyint(1) NOT NULL DEFAULT '0',
  `pendencia` tinyint(1) NOT NULL DEFAULT '0',
  `orcamento_codigo` int(11) DEFAULT NULL,
  `premio_total` decimal(10,2) DEFAULT NULL,
  `premio_liquido` decimal(10,2) DEFAULT NULL,
  `plano` varchar(120) DEFAULT NULL,
  `orcamentos_json` text,
  `resposta_json` text,
  `respondido_em` datetime DEFAULT NULL,
  `atualizado_em` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_ficha_seguradora` (`seguro_fianca_id`,`seguradora_codigo`),
  KEY `idx_seguro_fianca_id` (`seguro_fianca_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
;
CREATE TABLE IF NOT EXISTS `segimob_dominios` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tipo` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `codigo` int(11) NOT NULL,
  `descricao` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `atualizado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tipo_codigo` (`tipo`,`codigo`),
  KEY `idx_tipo` (`tipo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
;
