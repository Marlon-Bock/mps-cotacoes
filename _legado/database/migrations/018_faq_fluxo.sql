-- ═══════════════════════════════════════════════════════════════════════════
-- 018 — Fluxo de perguntas do FAQ (chat guiado estilo Leadster)
--
-- A MPS monta uma árvore de nós: nós do tipo 'pergunta' têm opções de
-- múltipla escolha (faq_fluxo_opcoes), cada opção aponta pro próximo nó
-- (pergunta ou resposta final). eh_inicial marca por onde o fluxo começa —
-- só um nó deve ter isso ligado por vez (aplicação garante, não o schema).
-- proximo_no_id pode ficar NULL enquanto o admin monta a árvore fora de ordem.
--
-- Já aplicado em db_mpsteste (dev). Rodar em db_mpscotacao ao publicar.
-- ═══════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `faq_fluxo_nos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tipo` enum('pergunta','resposta') NOT NULL DEFAULT 'pergunta',
  `texto` text NOT NULL,
  `eh_inicial` tinyint(1) NOT NULL DEFAULT 0,
  `ordem` int(11) NOT NULL DEFAULT 0,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `faq_fluxo_opcoes` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `no_id` int(11) NOT NULL,
  `texto` varchar(150) NOT NULL,
  `proximo_no_id` int(11) DEFAULT NULL,
  `ordem` int(11) NOT NULL DEFAULT 0,
  `criado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_no` (`no_id`),
  KEY `idx_proximo_no` (`proximo_no_id`),
  CONSTRAINT `fk_opcao_no` FOREIGN KEY (`no_id`) REFERENCES `faq_fluxo_nos` (`id`) ON DELETE CASCADE,
  CONSTRAINT `fk_opcao_proximo` FOREIGN KEY (`proximo_no_id`) REFERENCES `faq_fluxo_nos` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
