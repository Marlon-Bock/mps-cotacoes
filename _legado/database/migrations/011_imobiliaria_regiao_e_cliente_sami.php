<?php
/**
 * Migration 011 — Cadastro de Imobiliárias: adiciona o vínculo com região
 * (mesmo cadastro de Ilha/Continente/Online usado no Agendar Visita) e a
 * flag "é cliente Sami" (controla se o formulário busca o endereço no
 * cadastro institucional ou pede preenchimento manual), além de
 * site_samiloca (já existia na tabela mas nunca era gravado por nenhum
 * form — agora é preenchido quando o cliente institucional selecionado usa
 * o sistema SAMILOCA).
 * Executar via: php database/migrate_php.php
 */

$colunas = [
    'regiao_id'        => "ALTER TABLE imobiliarias ADD COLUMN regiao_id INT UNSIGNED NULL DEFAULT NULL AFTER uf",
    'eh_cliente_sami'  => "ALTER TABLE imobiliarias ADD COLUMN eh_cliente_sami TINYINT(1) NOT NULL DEFAULT 0 AFTER regiao_id",
];

foreach ($colunas as $col => $sql) {
    try {
        $conn->query($sql);
        echo "  + Coluna {$col} adicionada em imobiliarias." . PHP_EOL;
    } catch (mysqli_sql_exception $e) {
        if (str_contains($e->getMessage(), 'Duplicate column name')) {
            echo "  ~ Coluna {$col} já existe em imobiliarias, pulando." . PHP_EOL;
        } else {
            throw $e;
        }
    }
}
