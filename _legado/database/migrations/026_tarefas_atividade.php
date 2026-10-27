<?php
/**
 * Migration 026 — atividade da tarefa: comentários (com menções) e histórico.
 * Alimenta o offcanvas de detalhes da tarefa.
 * Executar via: php database/migrate_php.php
 */
$conn->query("
CREATE TABLE IF NOT EXISTS `tarefas_comentarios` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tarefa_id` int(10) unsigned NOT NULL,
  `usuario_id` int(10) unsigned DEFAULT NULL,
  `mensagem` text NOT NULL,
  `criado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_coment_tarefa` (`tarefa_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "  + Tabela tarefas_comentarios OK." . PHP_EOL;

$conn->query("
CREATE TABLE IF NOT EXISTS `tarefas_comentarios_mencoes` (
  `comentario_id` int(10) unsigned NOT NULL,
  `usuario_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`comentario_id`,`usuario_id`),
  KEY `idx_mencao_usuario` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "  + Tabela tarefas_comentarios_mencoes OK." . PHP_EOL;

$conn->query("
CREATE TABLE IF NOT EXISTS `tarefas_historico` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tarefa_id` int(10) unsigned NOT NULL,
  `usuario_id` int(10) unsigned DEFAULT NULL,
  `acao` varchar(40) NOT NULL,
  `detalhe` text DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_hist_tarefa` (`tarefa_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "  + Tabela tarefas_historico OK." . PHP_EOL;
