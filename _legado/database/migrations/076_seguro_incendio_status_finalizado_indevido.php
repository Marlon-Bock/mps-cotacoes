<?php
/**
 * Migration 076 — Corrige dados: cotações de Seguro Incêndio marcadas como
 * "Finalizado" indevidamente por um bug em seguro_incendio_recalcular_status()
 * que tratava o status por-imóvel "Aprovado" (proposta enviada, aguardando
 * decisão da imobiliária) como terminal. Recalcula o status agregado de toda
 * remessa que está "Finalizado" mas tem pelo menos um imóvel ainda não
 * decidido pela imobiliária (status 'Aprovado').
 */

$rows = $conn->query(
    "SELECT si.id
     FROM seguro_incendio si
     WHERE si.status = 'Finalizado'
       AND EXISTS (
            SELECT 1 FROM seguro_incendio_imoveis sii
             WHERE sii.seguro_incendio_id = si.id AND sii.status = 'Aprovado'
       )"
)->fetch_all(MYSQLI_ASSOC);

$corrigidos = 0;
foreach ($rows as $r) {
    $conn->query("UPDATE seguro_incendio SET status = 'Aprovado' WHERE id = " . (int) $r['id']);
    $corrigidos++;
}

echo "  + {$corrigidos} cotação(ões) de Seguro Incêndio corrigida(s) de 'Finalizado' para 'Aprovado'." . PHP_EOL;
