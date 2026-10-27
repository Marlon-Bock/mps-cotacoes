<?php
/**
 * Migration 042 — `reset_senha_usuario` (recuperação de senha da conta MPS)
 * não tinha as colunas `ip`/`user_agent` que `reset_senha_users` (imobiliária)
 * já tem. O INSERT de includes/acao/esqueceu-senha.php é genérico pras duas
 * tabelas e sempre grava essas colunas — sem elas, todo pedido de recuperação
 * de senha de uma conta MPS quebrava com "Unknown column 'ip' in field list".
 */

$alterations = [
    "ALTER TABLE reset_senha_usuario ADD COLUMN ip VARCHAR(45) NULL DEFAULT NULL",
    "ALTER TABLE reset_senha_usuario ADD COLUMN user_agent VARCHAR(255) NULL DEFAULT NULL",
];

foreach ($alterations as $sql) {
    try {
        $conn->query($sql);
        $col = explode('ADD COLUMN ', $sql)[1];
        $col = explode(' ', $col)[0];
        echo "  + Coluna {$col} adicionada." . PHP_EOL;
    } catch (mysqli_sql_exception $e) {
        if (str_contains($e->getMessage(), 'Duplicate column name')) {
            $col = explode('ADD COLUMN ', $sql)[1];
            $col = explode(' ', $col)[0];
            echo "  ~ Coluna {$col} já existe, pulando." . PHP_EOL;
        } else {
            throw $e;
        }
    }
}
