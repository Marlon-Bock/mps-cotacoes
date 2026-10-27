<?php
/**
 * Migration 077 — Seguro Incêndio: algumas imobiliárias já sabem o valor
 * de cada cobertura (Incêndio, Danos Elétricos, Impacto de Veículos,
 * Perda de Aluguel, Incêndio do conteúdo do inquilino, Responsabilidade
 * Civil) e preenchem tudo; outras preferem deixar a MPS cotar. Por isso
 * cada cobertura é independente e opcional (igual Vendaval já era), e
 * ganhou um botão "Não informar" próprio no formulário.
 */

$colunas = [
    'valor_incendio'               => 'ADD COLUMN valor_incendio DECIMAL(10,2) DEFAULT NULL AFTER valor_aluguel',
    'valor_danos_eletricos'        => 'ADD COLUMN valor_danos_eletricos DECIMAL(10,2) DEFAULT NULL AFTER valor_incendio',
    'valor_impacto_veiculos'       => 'ADD COLUMN valor_impacto_veiculos DECIMAL(10,2) DEFAULT NULL AFTER valor_danos_eletricos',
    'valor_perda_aluguel'          => 'ADD COLUMN valor_perda_aluguel DECIMAL(10,2) DEFAULT NULL AFTER valor_vendaval',
    'valor_incendio_conteudo'      => 'ADD COLUMN valor_incendio_conteudo DECIMAL(10,2) DEFAULT NULL AFTER valor_perda_aluguel',
    'valor_responsabilidade_civil' => 'ADD COLUMN valor_responsabilidade_civil DECIMAL(10,2) DEFAULT NULL AFTER valor_incendio_conteudo',
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
