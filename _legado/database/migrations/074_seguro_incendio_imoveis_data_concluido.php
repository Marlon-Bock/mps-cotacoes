<?php
/**
 * Migration 074 — Falta a coluna de data de conclusão por imóvel: o fluxo
 * "cliente aceita a proposta" (que existe pra Fiança/Condominial via
 * cotacao-aprovar.php, num partial compartilhado) marca a cotação como
 * "Concluída" com data_status_concluido = NOW(). Replicando por imóvel
 * pro Incêndio (ver cotacao-incendio-imovel-aprovar.php).
 */

try {
    $conn->query("ALTER TABLE seguro_incendio_imoveis ADD COLUMN data_status_concluido DATETIME DEFAULT NULL AFTER data_status_recusado");
    echo "  + Coluna data_status_concluido adicionada em seguro_incendio_imoveis." . PHP_EOL;
} catch (Throwable $e) {
    if (str_contains($e->getMessage(), 'Duplicate column name')) {
        echo "  = Coluna data_status_concluido já existia em seguro_incendio_imoveis." . PHP_EOL;
    } else {
        throw $e;
    }
}
