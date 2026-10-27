-- Autor dos eventos automáticos do histórico (API das seguradoras e Segimob)
-- passa a ser só "Seguradoras": a imobiliária vê o histórico e não deve saber
-- por qual canal a ficha foi cotada (manual, API ou Segimob).
-- Ver API_HISTORICO_AUTOR / SEGIMOB_HISTORICO_AUTOR.
UPDATE historico SET usuario_nome = 'Seguradoras' WHERE usuario_nome = 'API das seguradoras';
