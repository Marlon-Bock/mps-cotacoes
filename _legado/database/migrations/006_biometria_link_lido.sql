-- ═══════════════════════════════════════════════════════════════════════════
-- 006 — Controle de leitura do link de biometria (grid de cotações da imobiliária)
--
-- Permite destacar (vermelho, igual ao chat com mensagem não lida) o botão de
-- "copiar link da biometria" enquanto a imobiliária ainda não clicou nele, e
-- voltar ao normal assim que for copiado.
--
-- seguro_auto recebe a mesma coluna porque /api/cotacao/{protocolo}/biometria
-- é genérico (cotacao_resolver) e atende os dois tipos que suportam biometria.
--
-- Já aplicado em db_mpsteste (dev). Rodar em db_mpscotacao ao publicar.
-- ═══════════════════════════════════════════════════════════════════════════

ALTER TABLE seguro_fianca
  ADD COLUMN biometria_link_lido_em DATETIME NULL DEFAULT NULL AFTER biometria_status;

ALTER TABLE seguro_auto
  ADD COLUMN biometria_link_lido_em DATETIME NULL DEFAULT NULL AFTER biometria_status;
