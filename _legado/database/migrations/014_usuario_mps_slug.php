<?php
/**
 * Migration 014 — Adiciona `slug` em `usuario` (equipe interna MPS), usado
 * na URL /admin/usuarios/{slug}/editar em vez do id numérico — mesmo padrão
 * já aplicado a `imobiliarias.slug` (migration 012) e `users.slug`.
 * Executar via: php database/migrate_php.php
 */

try {
    $conn->query("ALTER TABLE usuario ADD COLUMN slug VARCHAR(160) NULL DEFAULT NULL AFTER username");
    echo "  + Coluna slug adicionada em usuario." . PHP_EOL;
} catch (mysqli_sql_exception $e) {
    if (str_contains($e->getMessage(), 'Duplicate column name')) {
        echo "  ~ Coluna slug já existe em usuario, pulando." . PHP_EOL;
    } else {
        throw $e;
    }
}

try {
    $conn->query("ALTER TABLE usuario ADD UNIQUE KEY uq_usuario_slug (slug)");
    echo "  + Índice único uq_usuario_slug criado." . PHP_EOL;
} catch (mysqli_sql_exception $e) {
    if (str_contains($e->getMessage(), 'Duplicate key name')) {
        echo "  ~ Índice uq_usuario_slug já existe, pulando." . PHP_EOL;
    } else {
        throw $e;
    }
}

function migration_014_slugify(string $texto): string
{
    static $mapa = [
        'á'=>'a','à'=>'a','ã'=>'a','â'=>'a','ä'=>'a',
        'é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
        'í'=>'i','ì'=>'i','î'=>'i','ï'=>'i',
        'ó'=>'o','ò'=>'o','õ'=>'o','ô'=>'o','ö'=>'o',
        'ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u',
        'ç'=>'c','ñ'=>'n',
    ];
    $texto = mb_strtolower($texto, 'UTF-8');
    $texto = strtr($texto, $mapa);
    $texto = preg_replace('/[^a-z0-9]+/', '-', $texto);
    return trim($texto, '-');
}

$semSlug = $conn->query("SELECT id, username FROM usuario WHERE slug IS NULL OR slug = ''");
$backfilled = 0;
while ($row = $semSlug->fetch_assoc()) {
    $base = migration_014_slugify($row['username']) ?: 'usuario';
    $slug = $base;
    $sufixo = 2;
    while (true) {
        $idEscapado = (int) $row['id'];
        $slugEscapado = $conn->real_escape_string($slug);
        $existe = $conn->query("SELECT id FROM usuario WHERE slug = '{$slugEscapado}' AND id != {$idEscapado}")->fetch_assoc();
        if (!$existe) break;
        $slug = "{$base}-{$sufixo}";
        $sufixo++;
    }
    $slugEscapado = $conn->real_escape_string($slug);
    $conn->query("UPDATE usuario SET slug = '{$slugEscapado}' WHERE id = " . (int) $row['id']);
    $backfilled++;
}
echo "  + {$backfilled} usuário(s) MPS com slug preenchido." . PHP_EOL;
