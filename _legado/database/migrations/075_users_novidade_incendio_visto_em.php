<?php
/**
 * Migration 075 — Coluna users.novidade_incendio_visto_em: controla o popup
 * "Novidade! Agora você pode cotar Seguro Incêndio" no dashboard da
 * imobiliária. NULL = ainda não viu (mostra o popup uma vez); depois que o
 * usuário fecha, grava NOW() e nunca mais aparece pra ele.
 */

try {
    $conn->query("ALTER TABLE users ADD COLUMN novidade_incendio_visto_em DATETIME NULL DEFAULT NULL");
    echo "  + Coluna novidade_incendio_visto_em adicionada em users." . PHP_EOL;
} catch (Throwable $e) {
    if (str_contains($e->getMessage(), 'Duplicate column name')) {
        echo "  = Coluna novidade_incendio_visto_em já existia em users." . PHP_EOL;
    } else {
        throw $e;
    }
}
