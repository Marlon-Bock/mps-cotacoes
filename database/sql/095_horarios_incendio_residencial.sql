-- 095 — Horário de atendimento e bloqueios também para Seguro Incêndio e Seguro Residencial
-- (antes só fianca, auto, titulo_capitalizacao, condominial). Rodar uma vez em cada banco.
ALTER TABLE horarios_atendimento
    MODIFY tipo_seguro ENUM('fianca','auto','titulo_capitalizacao','condominial','incendio','residencial','todos') NOT NULL DEFAULT 'todos';
ALTER TABLE bloqueios_horario
    MODIFY tipo_seguro SET('fianca','auto','titulo_capitalizacao','condominial','incendio','residencial') NOT NULL
        DEFAULT 'fianca,auto,titulo_capitalizacao,condominial,incendio,residencial';
