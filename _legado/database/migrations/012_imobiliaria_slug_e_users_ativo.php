<?php
/**
 * Migration 012 —
 * 1) Adiciona `slug` em `imobiliarias` (usado nas URLs /admin/imobiliarias/{slug}/...
 *    em vez do id numérico) e faz o backfill das linhas existentes.
 * 2) Adiciona `ativo` em `users` (tabela de usuários das imobiliárias) — a
 *    tela "Usuários" já lia essa coluna, mas ela nunca existiu de fato, o
 *    que fazia a consulta falhar em silêncio e sempre mostrar lista vazia.
 * Executar via: php database/migrate_php.php
 */

try {
    $conn->query("ALTER TABLE imobiliarias ADD COLUMN slug VARCHAR(160) NULL DEFAULT NULL AFTER nome");
    echo "  + Coluna slug adicionada em imobiliarias." . PHP_EOL;
} catch (mysqli_sql_exception $e) {
    if (str_contains($e->getMessage(), 'Duplicate column name')) {
        echo "  ~ Coluna slug já existe em imobiliarias, pulando." . PHP_EOL;
    } else {
        throw $e;
    }
}

try {
    $conn->query("ALTER TABLE imobiliarias ADD UNIQUE KEY uq_imobiliarias_slug (slug)");
    echo "  + Índice único uq_imobiliarias_slug criado." . PHP_EOL;
} catch (mysqli_sql_exception $e) {
    if (str_contains($e->getMessage(), 'Duplicate key name')) {
        echo "  ~ Índice uq_imobiliarias_slug já existe, pulando." . PHP_EOL;
    } else {
        throw $e;
    }
}

function migration_012_slugify(string $texto): string
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

$semSlug = $conn->query("SELECT id, nome FROM imobiliarias WHERE slug IS NULL OR slug = ''");
$backfilled = 0;
while ($row = $semSlug->fetch_assoc()) {
    $base = migration_012_slugify($row['nome']) ?: 'imobiliaria';
    $slug = $base;
    $sufixo = 2;
    while (true) {
        $idEscapado = (int) $row['id'];
        $slugEscapado = $conn->real_escape_string($slug);
        $existe = $conn->query("SELECT id FROM imobiliarias WHERE slug = '{$slugEscapado}' AND id != {$idEscapado}")->fetch_assoc();
        if (!$existe) break;
        $slug = "{$base}-{$sufixo}";
        $sufixo++;
    }
    $slugEscapado = $conn->real_escape_string($slug);
    $conn->query("UPDATE imobiliarias SET slug = '{$slugEscapado}' WHERE id = " . (int) $row['id']);
    $backfilled++;
}
echo "  + {$backfilled} imobiliária(s) com slug preenchido." . PHP_EOL;

try {
    $conn->query("ALTER TABLE users ADD COLUMN ativo TINYINT(1) NOT NULL DEFAULT 1 AFTER admin");
    echo "  + Coluna ativo adicionada em users." . PHP_EOL;
} catch (mysqli_sql_exception $e) {
    if (str_contains($e->getMessage(), 'Duplicate column name')) {
        echo "  ~ Coluna ativo já existe em users, pulando." . PHP_EOL;
    } else {
        throw $e;
    }
}
