<?php
/**
 * Migration 090 — a MPS envia a PROPOSTA da seguradora ao cliente assim que
 * o PDF fica pronto (antes da apólice), e a apólice depois (que conclui).
 *
 *   seguro_fianca.api_proposta_enviada_em — proposta (PDF) liberada pra imobiliária
 */

if (!$conn->query("SHOW COLUMNS FROM seguro_fianca LIKE 'api_proposta_enviada_em'")->num_rows) {
    $conn->query('ALTER TABLE seguro_fianca ADD COLUMN api_proposta_enviada_em DATETIME DEFAULT NULL');
}
