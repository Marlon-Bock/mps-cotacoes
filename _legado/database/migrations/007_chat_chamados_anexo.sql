-- ═══════════════════════════════════════════════════════════════════════════
-- 007 — Anexos no chat de chamados (envio de documentos junto com a mensagem)
-- Mesmo padrão da migration 004 (chat da cotação).
--
-- Rodar em db_mpscotacao ao publicar.
-- ═══════════════════════════════════════════════════════════════════════════

ALTER TABLE chamados_respostas
  ADD COLUMN anexo_url  varchar(255) NULL AFTER mensagem,
  ADD COLUMN anexo_nome varchar(255) NULL AFTER anexo_url,
  ADD COLUMN anexo_tipo varchar(60)  NULL AFTER anexo_nome;
