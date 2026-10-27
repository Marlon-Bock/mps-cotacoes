<?php
/**
 * Migration 023 — Módulo de Tarefas (gerenciador interno da equipe MPS).
 *
 * Reconstrução do zero após a remoção do módulo antigo (2026-07-23). Modelo novo:
 *  - `usuario.tarefas_gestor` ENUM('S','N') — o "Gestor de Tarefas" vê tudo e gerencia.
 *  - `setores` + vínculo N:N `tarefas_usuario_setor` (um usuário pode ter vários setores).
 *  - Status e etiquetas GLOBAIS (`tarefas_status`, `tarefas_etiquetas`).
 *  - `tarefas` + responsáveis (N:N), etiquetas (N:N) e campos customizados
 *    (`tarefas_campos` / `tarefas_campos_valores`).
 *
 * Executar via: php database/migrate_php.php   (roda SOMENTE em DB_TEST)
 * Para produção, aplicar o mesmo DDL manualmente via DBA (regra do projeto).
 */

// ── Coluna nova em usuario ────────────────────────────────────────────────────
try {
    $conn->query("ALTER TABLE usuario ADD COLUMN tarefas_gestor ENUM('S','N') NOT NULL DEFAULT 'N' AFTER equipe_comercial");
    echo "  + Coluna tarefas_gestor adicionada em usuario." . PHP_EOL;
} catch (mysqli_sql_exception $e) {
    if (str_contains($e->getMessage(), 'Duplicate column name')) {
        echo "  ~ Coluna tarefas_gestor já existe, pulando." . PHP_EOL;
    } else {
        throw $e;
    }
}

// ── Tabelas ────────────────────────────────────────────────────────────────────
$tabelas = [

'setores' => "
CREATE TABLE IF NOT EXISTS `setores` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(120) NOT NULL,
  `cor` varchar(20) NOT NULL DEFAULT '#64748b',
  `ordem` int(11) NOT NULL DEFAULT 0,
  `criado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_setores_ordem` (`ordem`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

'tarefas_usuario_setor' => "
CREATE TABLE IF NOT EXISTS `tarefas_usuario_setor` (
  `usuario_id` int(10) unsigned NOT NULL,
  `setor_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`usuario_id`, `setor_id`),
  KEY `idx_tus_setor` (`setor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

'tarefas_status' => "
CREATE TABLE IF NOT EXISTS `tarefas_status` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(80) NOT NULL,
  `cor` varchar(20) NOT NULL DEFAULT '#64748b',
  `ordem` int(11) NOT NULL DEFAULT 0,
  `is_final` tinyint(1) NOT NULL DEFAULT 0,
  `criado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_status_ordem` (`ordem`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

'tarefas_etiquetas' => "
CREATE TABLE IF NOT EXISTS `tarefas_etiquetas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(80) NOT NULL,
  `cor` varchar(20) NOT NULL DEFAULT '#64748b',
  `criado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

'tarefas' => "
CREATE TABLE IF NOT EXISTS `tarefas` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `titulo` varchar(200) NOT NULL,
  `descricao` text DEFAULT NULL,
  `setor_id` int(10) unsigned DEFAULT NULL,
  `status_id` int(10) unsigned DEFAULT NULL,
  `prioridade` enum('baixa','media','alta') DEFAULT NULL,
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

'tarefas_responsaveis' => "
CREATE TABLE IF NOT EXISTS `tarefas_responsaveis` (
  `tarefa_id` int(10) unsigned NOT NULL,
  `usuario_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`tarefa_id`, `usuario_id`),
  KEY `idx_tresp_usuario` (`usuario_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

'tarefas_etiquetas_vinculo' => "
CREATE TABLE IF NOT EXISTS `tarefas_etiquetas_vinculo` (
  `tarefa_id` int(10) unsigned NOT NULL,
  `etiqueta_id` int(10) unsigned NOT NULL,
  PRIMARY KEY (`tarefa_id`, `etiqueta_id`),
  KEY `idx_tev_etiqueta` (`etiqueta_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

'tarefas_campos' => "
CREATE TABLE IF NOT EXISTS `tarefas_campos` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `nome` varchar(120) NOT NULL,
  `tipo` enum('texto','numero','data','selecao','checkbox') NOT NULL DEFAULT 'texto',
  `opcoes` text DEFAULT NULL,
  `ordem` int(11) NOT NULL DEFAULT 0,
  `ativo` tinyint(1) NOT NULL DEFAULT 1,
  `criado_em` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_campos_ordem` (`ordem`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

'tarefas_campos_valores' => "
CREATE TABLE IF NOT EXISTS `tarefas_campos_valores` (
  `tarefa_id` int(10) unsigned NOT NULL,
  `campo_id` int(10) unsigned NOT NULL,
  `valor` text DEFAULT NULL,
  PRIMARY KEY (`tarefa_id`, `campo_id`),
  KEY `idx_tcv_campo` (`campo_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci",

];

foreach ($tabelas as $nome => $sql) {
    $conn->query($sql);
    echo "  + Tabela {$nome} OK." . PHP_EOL;
}

// ── Seed de status padrão (só se a tabela estiver vazia) ────────────────────────
$temStatus = (int) ($conn->query('SELECT COUNT(*) AS n FROM tarefas_status')->fetch_assoc()['n'] ?? 0);
if ($temStatus === 0) {
    $conn->query("INSERT INTO tarefas_status (nome, cor, ordem, is_final) VALUES
        ('A fazer',      '#64748b', 1, 0),
        ('Em andamento', '#2563eb', 2, 0),
        ('Concluído',    '#16a34a', 3, 1)");
    echo "  + Status padrão inseridos (A fazer / Em andamento / Concluído)." . PHP_EOL;
}
