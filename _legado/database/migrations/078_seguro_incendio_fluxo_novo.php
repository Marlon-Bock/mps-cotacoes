<?php
/**
 * Migration 078 — Novo fluxo do Seguro Incêndio (Dados recebidos → Cotação
 * realizada → PDF da cotação → Aguardando aprovação → Cotação aprovada → PDF
 * da proposta → Aguardando apólice → PDF da apólice → Processo concluído).
 *
 * `fluxo` separa as cotações em andamento (fluxo antigo, backfill 'antigo')
 * das criadas depois do deploy (default 'novo'). Os dois fluxos compartilham
 * os status 'Aguardando análise' e 'Em Analise', então sem essa flag o botão
 * de envio da 'Em Analise' seria ambíguo.
 */

$colunas = [
    'fluxo'                          => "ADD COLUMN fluxo VARCHAR(10) NOT NULL DEFAULT 'antigo'",
    'data_status_cotacao_anexada'    => 'ADD COLUMN data_status_cotacao_anexada DATETIME DEFAULT NULL',
    'data_status_cotacao_aprovada'   => 'ADD COLUMN data_status_cotacao_aprovada DATETIME DEFAULT NULL',
    'data_status_apolice_anexada'    => 'ADD COLUMN data_status_apolice_anexada DATETIME DEFAULT NULL',
];

foreach ($colunas as $nome => $ddl) {
    try {
        $conn->query("ALTER TABLE seguro_incendio_imoveis {$ddl}");
        echo "  + Coluna {$nome} adicionada em seguro_incendio_imoveis." . PHP_EOL;
    } catch (Throwable $e) {
        if (str_contains($e->getMessage(), 'Duplicate column name')) {
            echo "  = Coluna {$nome} já existia em seguro_incendio_imoveis." . PHP_EOL;
        } else {
            throw $e;
        }
    }
}

// Cotações criadas a partir daqui entram no fluxo novo por padrão.
$conn->query("ALTER TABLE seguro_incendio_imoveis ALTER COLUMN fluxo SET DEFAULT 'novo'");
echo "  + Default de fluxo ajustado para 'novo'." . PHP_EOL;
