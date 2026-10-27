-- ═══════════════════════════════════════════════════════════════════════════
-- 020 — Sessões do chat guiado do FAQ ("Atendimentos")
--
-- Registra progressivamente (a cada resposta, mesmo que a pessoa abandone no
-- meio) quem usou o chat, os dados que digitou (nome/telefone/imobiliária,
-- quando o fluxo pede), o caminho de opções percorrido e se chegou a abrir
-- um chamado — pra alimentar o relatório em /admin/faq/atendimentos.
-- ═══════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `faq_atendimentos` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `sessao_token` varchar(64) NOT NULL,
  `usuario_id` int(11) NOT NULL,
  `usuario_tipo` enum('admin','imobiliaria') NOT NULL,
  `usuario_nome` varchar(150) DEFAULT NULL,
  `imobiliaria_id` int(11) DEFAULT NULL,
  `nome_informado` varchar(150) DEFAULT NULL,
  `telefone_informado` varchar(30) DEFAULT NULL,
  `imobiliaria_informada` varchar(150) DEFAULT NULL,
  `caminho` text DEFAULT NULL,
  `chamado_aberto` tinyint(1) NOT NULL DEFAULT 0,
  `concluido` tinyint(1) NOT NULL DEFAULT 0,
  `criado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_faq_atend_sessao` (`sessao_token`),
  KEY `idx_faq_atend_usuario` (`usuario_id`, `usuario_tipo`),
  KEY `idx_faq_atend_imob` (`imobiliaria_id`),
  KEY `idx_faq_atend_criado` (`criado_em`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
