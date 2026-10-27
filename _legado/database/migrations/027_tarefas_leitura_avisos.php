<?php
/**
 * Migration 027 — leitura de comentários (badge de não lidos por usuário) e
 * controle dos avisos automáticos de prazo (para não notificar duas vezes).
 * Executar via: php database/migrate_php.php
 */
$conn->query("
CREATE TABLE IF NOT EXISTS `tarefas_leitura` (
  `tarefa_id` int(10) unsigned NOT NULL,
  `usuario_id` int(10) unsigned NOT NULL,
  `lido_em` datetime NOT NULL,
  PRIMARY KEY (`tarefa_id`,`usuario_id`),
  KEY `idx_leitura_usuario` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "  + Tabela tarefas_leitura OK." . PHP_EOL;

$conn->query("
CREATE TABLE IF NOT EXISTS `tarefas_avisos` (
  `tarefa_id` int(10) unsigned NOT NULL,
  `tipo` varchar(20) NOT NULL,
  `criado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`tarefa_id`,`tipo`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "  + Tabela tarefas_avisos OK." . PHP_EOL;
