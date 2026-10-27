<?php
/**
 * Migration 088 — profissão do pretendente (exigida pela Too Seguros).
 *
 * A Too pede, do pretendente responsável pelo aluguel, data de nascimento,
 * renda, vínculo empregatício e profissão. As três primeiras já existem em
 * seguro_fianca; a profissão não (era só da integração antiga).
 */

if (!$conn->query("SHOW COLUMNS FROM seguro_fianca LIKE 'profissao'")->num_rows) {
    $conn->query('ALTER TABLE seguro_fianca ADD COLUMN profissao VARCHAR(100) DEFAULT NULL AFTER vinculo_empregaticio');
}
