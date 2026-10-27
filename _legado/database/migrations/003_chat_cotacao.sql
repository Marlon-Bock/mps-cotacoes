-- ═══════════════════════════════════════════════════════════════════════════
-- 003 — Chat da cotação + histórico completo para os 4 produtos
--
-- Já aplicado em db_mpsteste (dev). Rodar em db_mpscotacao ao publicar.
-- ═══════════════════════════════════════════════════════════════════════════

-- Mensagens do chat por cotação (MPS ↔ imobiliária), chaveado por protocolo.
CREATE TABLE IF NOT EXISTS cotacao_mensagens (
  id int(10) unsigned NOT NULL AUTO_INCREMENT,
  protocolo varchar(50) NOT NULL,
  autor_tipo enum('admin','imobiliaria') NOT NULL,
  autor_id int(11) NOT NULL,
  autor_nome varchar(120) NOT NULL,
  mensagem text NOT NULL,
  criado_em datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  KEY idx_protocolo_data (protocolo, criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ponteiro de leitura por usuário/papel — alimenta o badge de "não lida".
CREATE TABLE IF NOT EXISTS cotacao_mensagens_lidas (
  protocolo varchar(50) NOT NULL,
  usuario_id int(11) NOT NULL,
  papel enum('admin','imobiliaria') NOT NULL,
  ultima_id_lida int(10) unsigned NOT NULL DEFAULT 0,
  atualizado_em datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (protocolo, usuario_id, papel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Anexos/propostas de título e condominial (mesmo papel de cotacoes_anexos
-- da fiança — usuario_envio guarda o NOME, seguindo o padrão da fiança).
CREATE TABLE IF NOT EXISTS cotacoes_anexos_titulo (
  id int(10) unsigned NOT NULL AUTO_INCREMENT,
  protocolo varchar(30) NOT NULL,
  nome_arquivo varchar(255) NOT NULL,
  caminho_arquivo varchar(255) NOT NULL,
  tipo_arquivo varchar(60) DEFAULT NULL,
  data_envio datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  usuario_envio varchar(100) DEFAULT NULL,
  status_aprovacao varchar(30) NOT NULL DEFAULT 'Aguardando',
  PRIMARY KEY (id),
  KEY idx_protocolo (protocolo),
  CONSTRAINT cotacoes_anexos_titulo_fk_protocolo FOREIGN KEY (protocolo)
    REFERENCES titulo_capitalizacao (protocolo) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS cotacoes_anexos_condominial (
  id int(10) unsigned NOT NULL AUTO_INCREMENT,
  protocolo varchar(30) NOT NULL,
  nome_arquivo varchar(255) NOT NULL,
  caminho_arquivo varchar(255) NOT NULL,
  tipo_arquivo varchar(60) DEFAULT NULL,
  data_envio datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  usuario_envio varchar(100) DEFAULT NULL,
  status_aprovacao varchar(30) NOT NULL DEFAULT 'Aguardando',
  PRIMARY KEY (id),
  KEY idx_protocolo (protocolo),
  CONSTRAINT cotacoes_anexos_condominial_fk_protocolo FOREIGN KEY (protocolo)
    REFERENCES seguro_condominial (protocolo) ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- cotacoes_anexos_auto.usuario_envio era int (id), mas todo o código grava o
-- nome (padrão da fiança) — uniformizado para varchar.
ALTER TABLE cotacoes_anexos_auto MODIFY usuario_envio varchar(100) NULL;

-- Título e condominial não tinham colunas de workflow (atendente/datas de
-- status) — necessárias agora que a progressão de status vale pros 4 tipos.
ALTER TABLE titulo_capitalizacao
  ADD COLUMN atendente_id INT NULL AFTER usuario_id,
  ADD COLUMN data_atualizacao DATETIME NULL AFTER criado_em,
  ADD COLUMN data_status_em_analise DATETIME NULL AFTER data_atualizacao,
  ADD COLUMN data_status_aprovado DATETIME NULL AFTER data_status_em_analise,
  ADD COLUMN data_status_concluido DATETIME NULL AFTER data_status_aprovado,
  ADD COLUMN data_status_recusado DATETIME NULL AFTER data_status_concluido;

ALTER TABLE seguro_condominial
  ADD COLUMN atendente_id INT NULL AFTER usuario_id,
  ADD COLUMN data_atualizacao DATETIME NULL AFTER criado_em,
  ADD COLUMN data_status_em_analise DATETIME NULL AFTER data_atualizacao,
  ADD COLUMN data_status_aprovado DATETIME NULL AFTER data_status_em_analise,
  ADD COLUMN data_status_concluido DATETIME NULL AFTER data_status_aprovado,
  ADD COLUMN data_status_recusado DATETIME NULL AFTER data_status_concluido;
