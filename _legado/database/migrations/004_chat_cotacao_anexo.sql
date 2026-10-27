-- ═══════════════════════════════════════════════════════════════════════════
-- 004 — Anexos no chat da cotação (envio de documentos junto com a mensagem)
--
-- Rodar em db_mpscotacao ao publicar.
-- ═══════════════════════════════════════════════════════════════════════════

ALTER TABLE cotacao_mensagens
  ADD COLUMN anexo_url  varchar(255) NULL AFTER mensagem,
  ADD COLUMN anexo_nome varchar(255) NULL AFTER anexo_url,
  ADD COLUMN anexo_tipo varchar(60)  NULL AFTER anexo_nome;
