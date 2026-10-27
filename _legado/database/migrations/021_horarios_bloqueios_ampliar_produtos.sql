-- ═══════════════════════════════════════════════════════════════════════════
-- 021 — Amplia horarios_atendimento/bloqueios_horario pra cobrir Título de
-- Capitalização e Condominial (antes só cobriam Fiança Residencial/Comercial
-- e Auto). As tabelas já existiam no schema (nunca tinham código usando
-- elas) — aqui só garante que existem (fresh install) e amplia o enum/set
-- pra quem já tinha as tabelas criadas com o escopo antigo.
-- ═══════════════════════════════════════════════════════════════════════════

CREATE TABLE IF NOT EXISTS `horarios_atendimento` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `tipo_seguro` enum('fianca_residencial','fianca_comercial','auto','titulo_capitalizacao','condominial','todos') NOT NULL DEFAULT 'todos',
  `hora_inicio` time NOT NULL DEFAULT '08:30:00',
  `hora_fim` time NOT NULL DEFAULT '18:00:00',
  `dias_semana` varchar(20) NOT NULL DEFAULT '1,2,3,4,5',
  `ativo` enum('S','N') NOT NULL DEFAULT 'S',
  `criado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_tipo_seguro` (`tipo_seguro`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `horarios_atendimento`
  MODIFY COLUMN `tipo_seguro` enum('fianca_residencial','fianca_comercial','auto','titulo_capitalizacao','condominial','todos') NOT NULL DEFAULT 'todos';

CREATE TABLE IF NOT EXISTS `bloqueios_horario` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `titulo` varchar(150) NOT NULL,
  `mensagem` text NOT NULL,
  `data_inicio` datetime NOT NULL,
  `data_fim` datetime NOT NULL,
  `tipo_seguro` set('fianca_residencial','fianca_comercial','auto','titulo_capitalizacao','condominial') NOT NULL DEFAULT 'fianca_residencial,fianca_comercial,auto,titulo_capitalizacao,condominial',
  `ativo` enum('S','N') NOT NULL DEFAULT 'S',
  `criado_por` int(11) DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `bloqueios_horario`
  MODIFY COLUMN `tipo_seguro` set('fianca_residencial','fianca_comercial','auto','titulo_capitalizacao','condominial') NOT NULL DEFAULT 'fianca_residencial,fianca_comercial,auto,titulo_capitalizacao,condominial';
