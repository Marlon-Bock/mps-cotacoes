-- ==========================================================================
-- Schema do modulo de Tarefas para PRODUCAO (db_mpscotacao)
-- Gerado a partir do db_mpsteste (autoritativo). Aplicar UMA vez.
-- Os CREATE usam IF NOT EXISTS; o ADD COLUMN falha se ja existir (ignore o erro).
-- ==========================================================================

ALTER TABLE `usuario` ADD COLUMN `tarefas_gestor` ENUM('S','N') NOT NULL DEFAULT 'N' AFTER `equipe_comercial`;

CREATE TABLE IF NOT EXISTS `setores` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cor` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#64748b',
  `ordem` int(11) NOT NULL DEFAULT '0',
  `criado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_setores_ordem` (`ordem`)
) ENGINE=InnoDB AUTO_INCREMENT=14 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tarefas_usuario_setor` (
  `usuario_id` int(10) unsigned NOT NULL,
  `setor_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`usuario_id`,`setor_id`),
  KEY `idx_tus_setor` (`setor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tarefas_status` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cor` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#64748b',
  `ordem` int(11) NOT NULL DEFAULT '0',
  `is_final` tinyint(1) NOT NULL DEFAULT '0',
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `criado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status_ordem` (`ordem`)
) ENGINE=InnoDB AUTO_INCREMENT=5 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tarefas_etiquetas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(80) COLLATE utf8mb4_unicode_ci NOT NULL,
  `cor` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '#64748b',
  `criado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tarefas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `titulo` varchar(200) COLLATE utf8mb4_unicode_ci NOT NULL,
  `descricao` text COLLATE utf8mb4_unicode_ci,
  `setor_id` int(10) unsigned DEFAULT NULL,
  `status_id` int(10) unsigned DEFAULT NULL,
  `prioridade` enum('baixa','media','alta') COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `prazo` date DEFAULT NULL,
  `criado_por` int(10) unsigned DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `atualizado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `concluida_em` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_tarefas_setor` (`setor_id`),
  KEY `idx_tarefas_status` (`status_id`),
  KEY `idx_tarefas_prazo` (`prazo`),
  KEY `idx_tarefas_atualizado` (`atualizado_em`)
) ENGINE=InnoDB AUTO_INCREMENT=20 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tarefas_responsaveis` (
  `tarefa_id` int(10) unsigned NOT NULL,
  `usuario_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`tarefa_id`,`usuario_id`),
  KEY `idx_tresp_usuario` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tarefas_etiquetas_vinculo` (
  `tarefa_id` int(10) unsigned NOT NULL,
  `etiqueta_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`tarefa_id`,`etiqueta_id`),
  KEY `idx_tev_etiqueta` (`etiqueta_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tarefas_campos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(120) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` enum('texto','numero','data','selecao','checkbox') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'texto',
  `opcoes` text COLLATE utf8mb4_unicode_ci,
  `ordem` int(11) NOT NULL DEFAULT '0',
  `ativo` tinyint(1) NOT NULL DEFAULT '1',
  `criado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_campos_ordem` (`ordem`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tarefas_campos_valores` (
  `tarefa_id` int(10) unsigned NOT NULL,
  `campo_id` int(10) unsigned NOT NULL,
  `valor` text COLLATE utf8mb4_unicode_ci,
  PRIMARY KEY (`tarefa_id`,`campo_id`),
  KEY `idx_tcv_campo` (`campo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tarefas_anexos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tarefa_id` int(10) unsigned NOT NULL,
  `url` varchar(500) COLLATE utf8mb4_unicode_ci NOT NULL,
  `nome` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tipo` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tamanho` int(10) unsigned DEFAULT NULL,
  `criado_por` int(10) unsigned DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_anexo_tarefa` (`tarefa_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tarefas_comentarios` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tarefa_id` int(10) unsigned NOT NULL,
  `usuario_id` int(10) unsigned DEFAULT NULL,
  `mensagem` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `criado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_coment_tarefa` (`tarefa_id`)
) ENGINE=InnoDB AUTO_INCREMENT=4 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tarefas_comentarios_mencoes` (
  `comentario_id` int(10) unsigned NOT NULL,
  `usuario_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`comentario_id`,`usuario_id`),
  KEY `idx_mencao_usuario` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tarefas_historico` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tarefa_id` int(10) unsigned NOT NULL,
  `usuario_id` int(10) unsigned DEFAULT NULL,
  `acao` varchar(40) COLLATE utf8mb4_unicode_ci NOT NULL,
  `detalhe` text COLLATE utf8mb4_unicode_ci,
  `criado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_hist_tarefa` (`tarefa_id`)
) ENGINE=InnoDB AUTO_INCREMENT=24 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tarefas_leitura` (
  `tarefa_id` int(10) unsigned NOT NULL,
  `usuario_id` int(10) unsigned NOT NULL,
  `lido_em` datetime NOT NULL,
  PRIMARY KEY (`tarefa_id`,`usuario_id`),
  KEY `idx_leitura_usuario` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `tarefas_avisos` (
  `tarefa_id` int(10) unsigned NOT NULL,
  `tipo` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `criado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tarefa_id`,`tipo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Status iniciais (só insere se ainda não houver nenhum)
INSERT INTO `tarefas_status` (nome, cor, ordem, is_final, ativo)
SELECT * FROM (SELECT 'Pendente', '#64748b', 1, 0, 1 UNION ALL SELECT 'Em andamento', '#d97706', 2, 0, 1 UNION ALL SELECT 'Concluído', '#16a34a', 3, 1, 1) s
WHERE NOT EXISTS (SELECT 1 FROM `tarefas_status`);
