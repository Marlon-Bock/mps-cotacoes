-- ═══════════════════════════════════════════════════════════════════════════
-- 005 — usuario_tipo em `historico`
--
-- Sem isso, a contagem de "não lidas" do chat da cotação (cotacao_chat_unread /
-- cotacao_unread_map) não tinha como saber se um evento de status foi causado
-- pelo próprio usuário que está olhando (ex: admin envia proposta e o próprio
-- admin vê "1 não lida" ao abrir o chat da cotação em seguida) — usuario_id
-- sozinho é ambíguo entre as tabelas `usuario` (admin) e `users` (imobiliária).
--
-- Rodar em db_mpscotacao ao publicar.
-- ═══════════════════════════════════════════════════════════════════════════

ALTER TABLE historico
  ADD COLUMN usuario_tipo ENUM('admin','imobiliaria') NULL AFTER usuario_id;
