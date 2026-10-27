<?php
/**
 * Migration 049 — Forma de pagamento (Boleto/Cartão) no Título de
 * Capitalização, escolhida pela imobiliária no envio da ficha. Faz parte do
 * novo fluxo específico do título: proposta+boleto -> pagamento -> certificado
 * (ver includes/acao/cotacao-titulo-*.php).
 */

try {
    $conn->query("ALTER TABLE titulo_capitalizacao ADD COLUMN forma_pagamento VARCHAR(20) NULL AFTER valor_titulo");
    echo "  + Coluna forma_pagamento adicionada." . PHP_EOL;
} catch (mysqli_sql_exception $e) {
    if (str_contains($e->getMessage(), 'Duplicate column name')) {
        echo "  ~ Coluna forma_pagamento já existe, pulando." . PHP_EOL;
    } else {
        throw $e;
    }
}
