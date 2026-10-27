<?php
/**
 * Migration 033 —
 * Suporte a cotação via embed (iframe no site do cliente, sem login):
 * adiciona em `imobiliarias` o toggle `usa_embed`, o token público
 * `embed_token` (identifica a imobiliária nas rotas /embed/{token}/...,
 * sem depender de sessão/JWT) e o domínio autorizado a embutir
 * (`embed_dominio`, usado para restringir o header frame-ancestors).
 * Executar via: php database/migrate_php.php
 */

try {
    $conn->query("ALTER TABLE imobiliarias ADD COLUMN usa_embed TINYINT(1) NOT NULL DEFAULT 0 AFTER ativo");
    echo "  + Coluna usa_embed adicionada em imobiliarias." . PHP_EOL;
} catch (mysqli_sql_exception $e) {
    if (str_contains($e->getMessage(), 'Duplicate column name')) {
        echo "  ~ Coluna usa_embed já existe em imobiliarias, pulando." . PHP_EOL;
    } else {
        throw $e;
    }
}

try {
    $conn->query("ALTER TABLE imobiliarias ADD COLUMN embed_token VARCHAR(64) NULL DEFAULT NULL AFTER usa_embed");
    echo "  + Coluna embed_token adicionada em imobiliarias." . PHP_EOL;
} catch (mysqli_sql_exception $e) {
    if (str_contains($e->getMessage(), 'Duplicate column name')) {
        echo "  ~ Coluna embed_token já existe em imobiliarias, pulando." . PHP_EOL;
    } else {
        throw $e;
    }
}

try {
    $conn->query("ALTER TABLE imobiliarias ADD COLUMN embed_dominio VARCHAR(255) NULL DEFAULT NULL AFTER embed_token");
    echo "  + Coluna embed_dominio adicionada em imobiliarias." . PHP_EOL;
} catch (mysqli_sql_exception $e) {
    if (str_contains($e->getMessage(), 'Duplicate column name')) {
        echo "  ~ Coluna embed_dominio já existe em imobiliarias, pulando." . PHP_EOL;
    } else {
        throw $e;
    }
}

try {
    $conn->query("ALTER TABLE imobiliarias ADD UNIQUE KEY uq_imobiliarias_embed_token (embed_token)");
    echo "  + Índice único uq_imobiliarias_embed_token criado." . PHP_EOL;
} catch (mysqli_sql_exception $e) {
    if (str_contains($e->getMessage(), 'Duplicate key name')) {
        echo "  ~ Índice uq_imobiliarias_embed_token já existe, pulando." . PHP_EOL;
    } else {
        throw $e;
    }
}
