<?php
/**
 * Migration 041 — Log de notificações WhatsApp (Z-API) enviadas ao atendente
 * responsável pela cotação. Ver src/helpers/zapi.php.
 */

$sql = "CREATE TABLE zapi_notificacoes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    atendente_id INT NULL,
    telefone VARCHAR(20) NULL,
    protocolo VARCHAR(20) NULL,
    evento VARCHAR(40) NOT NULL,
    mensagem TEXT NOT NULL,
    sucesso TINYINT(1) NOT NULL DEFAULT 0,
    erro TEXT NULL,
    criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_atendente (atendente_id),
    KEY idx_protocolo (protocolo),
    KEY idx_criado_em (criado_em)
)";

try {
    $conn->query($sql);
    echo "  + Tabela zapi_notificacoes criada." . PHP_EOL;
} catch (mysqli_sql_exception $e) {
    if (str_contains($e->getMessage(), 'already exists')) {
        echo "  ~ Tabela zapi_notificacoes já existe, pulando." . PHP_EOL;
    } else {
        throw $e;
    }
}
