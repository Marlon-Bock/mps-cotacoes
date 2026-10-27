<?php
/**
 * Migration 067 — Colunas de data por etapa de status em seguro_incendio,
 * exigidas pelos endpoints genéricos de ação (cotacao-iniciar-analise.php,
 * cotacao-proposta.php, cotacao-recusar-mps.php, cotacao-recusar-cliente.php),
 * que fazem UPDATE {$tbl_cotacao} SET data_status_... independente do produto.
 */

$colunas = [
    'data_status_em_analise' => "ALTER TABLE seguro_incendio ADD COLUMN data_status_em_analise DATETIME DEFAULT NULL",
    'data_status_aprovado'   => "ALTER TABLE seguro_incendio ADD COLUMN data_status_aprovado DATETIME DEFAULT NULL",
    'data_status_recusado'   => "ALTER TABLE seguro_incendio ADD COLUMN data_status_recusado DATETIME DEFAULT NULL",
];

foreach ($colunas as $nome => $sql) {
    try {
        $conn->query($sql);
        echo "  + Coluna {$nome} adicionada em seguro_incendio." . PHP_EOL;
    } catch (Throwable $e) {
        if (str_contains($e->getMessage(), 'Duplicate column name')) {
            echo "  = Coluna {$nome} já existia em seguro_incendio." . PHP_EOL;
        } else {
            throw $e;
        }
    }
}
