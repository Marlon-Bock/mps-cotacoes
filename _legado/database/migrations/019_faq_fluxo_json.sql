-- ═══════════════════════════════════════════════════════════════════════════
-- 019 — Fluxo de perguntas do FAQ: substitui o modelo relacional (018) por
-- um construtor visual de blocos, salvo como um JSON só (uma linha fixa).
--
-- O modelo anterior (faq_fluxo_nos/faq_fluxo_opcoes, nó→opção via FK) não
-- era o que se pedia — o pedido é um construtor de blocos empilháveis
-- (mensagem, mensagem+botão, resposta livre, lista de opções com sub-fluxo
-- aninhado por opção, abrir chamado), editado visualmente e salvo de uma vez.
-- Mais simples guardar a árvore inteira como JSON do que normalizar em tabelas.
--
-- Já aplicado em db_mpsteste (dev). Rodar em db_mpscotacao ao publicar.
-- ═══════════════════════════════════════════════════════════════════════════

DROP TABLE IF EXISTS `faq_fluxo_opcoes`;
DROP TABLE IF EXISTS `faq_fluxo_nos`;

CREATE TABLE IF NOT EXISTS `faq_fluxo_json` (
  `id` int(11) NOT NULL DEFAULT 1,
  `json_data` longtext NOT NULL,
  `atualizado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
