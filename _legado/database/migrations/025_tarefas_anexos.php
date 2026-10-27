<?php
/**
 * Migration 025 — anexos de tarefas.
 * Guarda os arquivos enviados para cada tarefa (url + metadados).
 * Executar via: php database/migrate_php.php
 */
$conn->query("
CREATE TABLE IF NOT EXISTS `tarefas_anexos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `tarefa_id` int(10) unsigned NOT NULL,
  `url` varchar(500) NOT NULL,
  `nome` varchar(255) NOT NULL,
  `tipo` varchar(20) DEFAULT NULL,
  `tamanho` int(10) unsigned DEFAULT NULL,
  `criado_por` int(10) unsigned DEFAULT NULL,
  `criado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_anexo_tarefa` (`tarefa_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
echo "  + Tabela tarefas_anexos OK." . PHP_EOL;
