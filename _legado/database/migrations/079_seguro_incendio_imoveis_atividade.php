<?php
/**
 * Migration 079 — Seguro Incêndio: quando o imóvel é comercial, a MPS
 * precisa saber a atividade exercida no local (livraria, consultório
 * médico, salão de beleza etc.) — algumas atividades não são aceitas pelas
 * seguradoras, então essa informação entra na análise desde já.
 */

try {
    $conn->query("ALTER TABLE seguro_incendio_imoveis ADD COLUMN atividade_imovel VARCHAR(150) DEFAULT NULL AFTER tipo_imovel");
    echo "  + Coluna atividade_imovel adicionada em seguro_incendio_imoveis." . PHP_EOL;
} catch (Throwable $e) {
    if (str_contains($e->getMessage(), 'Duplicate column name')) {
        echo "  = Coluna atividade_imovel já existia em seguro_incendio_imoveis." . PHP_EOL;
    } else {
        throw $e;
    }
}
