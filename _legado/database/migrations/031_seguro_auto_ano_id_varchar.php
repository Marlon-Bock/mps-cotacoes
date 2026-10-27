<?php
/**
 * Migration 031 — `ano_id` de Seguro Auto passa a VARCHAR.
 * O código do ano na FIPE não é número: vem como "2008-1" (ano + combustível,
 * ver automovel_anos.codigo_externo). Com a coluna INT o MySQL em modo estrito
 * recusava o valor e tanto o envio da cotação quanto o salvar rascunho
 * quebravam. Os dados antigos (NULL e um 42420 solto) sobrevivem à conversão.
 * Executar via: php database/migrate_php.php
 */

foreach (['seguro_auto', 'seguro_auto_rascunho'] as $tabela) {
    try {
        $conn->query("ALTER TABLE {$tabela} MODIFY COLUMN ano_id VARCHAR(12) NULL");
        echo "  ~ {$tabela}.ano_id agora é VARCHAR(12)." . PHP_EOL;
    } catch (mysqli_sql_exception $e) {
        echo "  ! Falha ao alterar {$tabela}.ano_id: " . $e->getMessage() . PHP_EOL;
        throw $e;
    }
}
