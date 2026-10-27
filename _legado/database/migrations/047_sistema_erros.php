<?php
/**
 * Migration 047 — Log central de erros do sistema (exceptions não tratadas,
 * erros PHP e erros fatais), pra tela de diagnóstico em /admin/sistema-erros.
 * Ver src/core/error_logger.php.
 */

$sql = "CREATE TABLE sistema_erros (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tipo VARCHAR(20) NOT NULL COMMENT 'error|exception|fatal',
    nivel VARCHAR(60) NULL COMMENT 'ex.: E_WARNING, RuntimeException, mysqli_sql_exception',
    mensagem TEXT NOT NULL,
    arquivo VARCHAR(500) NULL,
    linha INT NULL,
    trace TEXT NULL,
    url VARCHAR(500) NULL,
    metodo_http VARCHAR(10) NULL,
    usuario_id INT NULL,
    usuario_tipo VARCHAR(20) NULL COMMENT 'admin|imobiliaria|anonimo',
    criado_em DATETIME NOT NULL,
    KEY idx_criado_em (criado_em),
    KEY idx_tipo (tipo)
)";

try {
    $conn->query($sql);
    echo "  + Tabela sistema_erros criada." . PHP_EOL;
} catch (mysqli_sql_exception $e) {
    if (str_contains($e->getMessage(), 'already exists')) {
        echo "  ~ Tabela sistema_erros já existe, pulando." . PHP_EOL;
    } else {
        throw $e;
    }
}
