<?php
/**
 * Migration 073 — Proposta por imóvel: cotacoes_anexos_incendio ganha
 * seguro_incendio_imovel_id, pra cada PDF de proposta ficar vinculado a UM
 * imóvel da remessa, não ao protocolo inteiro (mesma lógica de status
 * independente por imóvel da migration 072).
 */

try {
    $conn->query("ALTER TABLE cotacoes_anexos_incendio ADD COLUMN seguro_incendio_imovel_id INT DEFAULT NULL AFTER protocolo");
    echo "  + Coluna seguro_incendio_imovel_id adicionada em cotacoes_anexos_incendio." . PHP_EOL;
} catch (Throwable $e) {
    if (str_contains($e->getMessage(), 'Duplicate column name')) {
        echo "  = Coluna seguro_incendio_imovel_id já existia em cotacoes_anexos_incendio." . PHP_EOL;
    } else {
        throw $e;
    }
}

try {
    $conn->query("ALTER TABLE cotacoes_anexos_incendio ADD KEY idx_seguro_incendio_imovel_id (seguro_incendio_imovel_id)");
    echo "  + Índice idx_seguro_incendio_imovel_id adicionado." . PHP_EOL;
} catch (Throwable $e) {
    if (str_contains($e->getMessage(), 'Duplicate key name')) {
        echo "  = Índice idx_seguro_incendio_imovel_id já existia." . PHP_EOL;
    } else {
        throw $e;
    }
}
