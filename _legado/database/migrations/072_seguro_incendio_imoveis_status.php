<?php
/**
 * Migration 072 — Ciclo de vida POR IMÓVEL no Seguro Incêndio. Cada imóvel
 * de uma remessa é um risco segurado independente (locador/locatário/
 * vigência/valor próprios) — a seguradora emite uma apólice por imóvel, e
 * um pode ser aprovado enquanto outro é recusado, sem se afetarem. Antes
 * disso, status/proposta eram únicos pro protocolo inteiro.
 * Mesmas colunas de ciclo de vida que já existem em seguro_incendio
 * (migration 067), agora replicadas em seguro_incendio_imoveis.
 */

$colunas = [
    'status'                 => "ALTER TABLE seguro_incendio_imoveis ADD COLUMN status VARCHAR(60) NOT NULL DEFAULT 'Aguardando análise'",
    'data_status_em_analise' => "ALTER TABLE seguro_incendio_imoveis ADD COLUMN data_status_em_analise DATETIME DEFAULT NULL",
    'data_status_aprovado'   => "ALTER TABLE seguro_incendio_imoveis ADD COLUMN data_status_aprovado DATETIME DEFAULT NULL",
    'data_status_recusado'   => "ALTER TABLE seguro_incendio_imoveis ADD COLUMN data_status_recusado DATETIME DEFAULT NULL",
    'data_atualizacao'       => "ALTER TABLE seguro_incendio_imoveis ADD COLUMN data_atualizacao DATETIME DEFAULT NULL",
];

foreach ($colunas as $nome => $sql) {
    try {
        $conn->query($sql);
        echo "  + Coluna {$nome} adicionada em seguro_incendio_imoveis." . PHP_EOL;
    } catch (Throwable $e) {
        if (str_contains($e->getMessage(), 'Duplicate column name')) {
            echo "  = Coluna {$nome} já existia em seguro_incendio_imoveis." . PHP_EOL;
        } else {
            throw $e;
        }
    }
}
