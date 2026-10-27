<?php
/**
 * Migration 030 — Adiciona `segurado_uf` em `seguro_auto` e no rascunho.
 * O formulário de Seguro Auto sempre coletou a UF (preenchida junto com o CEP)
 * e as telas de detalhe já a exibem, mas a coluna nunca existiu no banco — o
 * INSERT quebrava com "Unknown column 'segurado_uf'" e o envio da cotação
 * morria com "erro de conexão" na tela.
 * Executar via: php database/migrate_php.php
 */

$adicionar = function (string $tabela) use ($conn) {
    try {
        $conn->query("ALTER TABLE {$tabela} ADD COLUMN segurado_uf VARCHAR(2) NULL AFTER segurado_cidade");
        echo "  + Coluna segurado_uf adicionada em {$tabela}." . PHP_EOL;
    } catch (mysqli_sql_exception $e) {
        if (str_contains($e->getMessage(), 'Duplicate column name')) {
            echo "  ~ Coluna segurado_uf já existe em {$tabela}, pulando." . PHP_EOL;
        } else {
            throw $e;
        }
    }
};

$adicionar('seguro_auto');
$adicionar('seguro_auto_rascunho');
