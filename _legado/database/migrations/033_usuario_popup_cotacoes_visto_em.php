<?php
/**
 * Migration 033 — Adiciona `popup_cotacoes_visto_em` em `usuario` (equipe MPS).
 * Guarda quando o popup de "cotações em análise sob minha responsabilidade"
 * foi mostrado pela última vez, pra ele reaparecer de vez em quando ao entrar
 * no dashboard (não só no login) sem repetir em toda visita.
 * Executar via: php database/migrate_php.php
 */

try {
    $conn->query("ALTER TABLE usuario ADD COLUMN popup_cotacoes_visto_em DATETIME NULL DEFAULT NULL AFTER last_login_at");
    echo "  + Coluna popup_cotacoes_visto_em adicionada em usuario." . PHP_EOL;
} catch (mysqli_sql_exception $e) {
    if (str_contains($e->getMessage(), 'Duplicate column name')) {
        echo "  ~ Coluna popup_cotacoes_visto_em já existe em usuario, pulando." . PHP_EOL;
    } else {
        throw $e;
    }
}
