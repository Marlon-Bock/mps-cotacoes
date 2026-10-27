<?php
/**
 * Migration 043 — Adiciona `motivo` em login_tentativas: até aqui só se sabia
 * QUE um login falhou (falhou=1), não POR QUE (e-mail inexistente, senha
 * errada, conta inativa, token CSRF expirado). Ver includes/acao/login.php.
 */

$sql = "ALTER TABLE login_tentativas ADD COLUMN motivo VARCHAR(30) NULL DEFAULT NULL";

try {
    $conn->query($sql);
    echo "  + Coluna motivo adicionada." . PHP_EOL;
} catch (mysqli_sql_exception $e) {
    if (str_contains($e->getMessage(), 'Duplicate column name')) {
        echo "  ~ Coluna motivo já existe, pulando." . PHP_EOL;
    } else {
        throw $e;
    }
}
