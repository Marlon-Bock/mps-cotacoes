-- ═══════════════════════════════════════════════════════════════════════════
-- 017 — Popup de campanha também para Título e Condominial
--
-- `campanhas` já tinha popup_ativo/popup_residencial/popup_comercial/popup_auto,
-- mas faltavam as colunas de Título de Capitalização e Condominial — o admin
-- agora escolhe em quais das 5 telas de produto o popup aparece.
--
-- Já aplicado em db_mpsteste (dev). Rodar em db_mpscotacao ao publicar.
-- ═══════════════════════════════════════════════════════════════════════════

ALTER TABLE campanhas
  ADD COLUMN popup_titulo TINYINT(1) NOT NULL DEFAULT 0 AFTER popup_auto,
  ADD COLUMN popup_condominial TINYINT(1) NOT NULL DEFAULT 0 AFTER popup_titulo;
