-- ═══════════════════════════════════════════════════════════════════════════
-- 022 — Seguro Fiança é um produto único (não há mais equipe/horário
-- separado por Residencial x Comercial) — unifica 'fianca_residencial' e
-- 'fianca_comercial' em um só valor 'fianca'. Sem rows existentes até agora
-- (feature recém-criada, ainda não configurada em produção).
-- ═══════════════════════════════════════════════════════════════════════════

ALTER TABLE `horarios_atendimento`
  MODIFY COLUMN `tipo_seguro` enum('fianca','auto','titulo_capitalizacao','condominial','todos') NOT NULL DEFAULT 'todos';

ALTER TABLE `bloqueios_horario`
  MODIFY COLUMN `tipo_seguro` set('fianca','auto','titulo_capitalizacao','condominial') NOT NULL DEFAULT 'fianca,auto,titulo_capitalizacao,condominial';
