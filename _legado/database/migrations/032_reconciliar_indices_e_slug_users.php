<?php
/**
 * Migration 032 — fecha as últimas divergências entre o db_mpsteste e uma
 * cópia migrada da produção, detectadas no ensaio de migração:
 *
 *   1) users.slug ficava NULL em todos os usuários que já existiam antes da
 *      migration 028 (ela cria a coluna mas não faz backfill). A rota
 *      /usuarios/{slug}/editar busca `WHERE slug = ?`, então sem backfill
 *      nenhum usuário antigo de imobiliária conseguiria ser editado.
 *   2) Índice único (imobiliaria_id, slug) em users — o slug é único por
 *      imobiliária, não globalmente.
 *   3) idx_usuario_envio em cotacoes_anexos_condominial / _titulo, que existe
 *      no teste mas não vinha no CREATE TABLE das migrations.
 *   4) comissoes_imobiliarias: `modo` era VARCHAR(12) e `percentual`
 *      DECIMAL(10,2) na produção; no teste são ENUM('percentual','valor') e
 *      DECIMAL(5,2).
 *
 * Idempotente: pode rodar de novo sem efeito colateral.
 */

/* ── 1) Backfill de users.slug ───────────────────────────────────────────── */
function migration_032_slugify(string $texto): string
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

$backfilled = 0;
$rows = $conn->query(
    "SELECT id, username, imobiliaria_id FROM users
      WHERE slug IS NULL OR slug = ''
      ORDER BY id ASC"
);
while ($row = $rows->fetch_assoc()) {
    $id     = (int) $row['id'];
    $imobId = (int) $row['imobiliaria_id'];
    $base   = migration_032_slugify((string) $row['username']) ?: 'usuario';
    $slug   = $base;
    $sufixo = 2;

    // Mesma regra do gerar_slug_usuario(): único por imobiliária.
    while (true) {
        $slugEsc = $conn->real_escape_string($slug);
        $existe  = $conn->query(
            "SELECT id FROM users
              WHERE imobiliaria_id = {$imobId} AND slug = '{$slugEsc}' AND id != {$id}
              LIMIT 1"
        )->fetch_assoc();
        if (!$existe) break;
        $slug = "{$base}-{$sufixo}";
        $sufixo++;
    }

    $slugEsc = $conn->real_escape_string($slug);
    $conn->query("UPDATE users SET slug = '{$slugEsc}' WHERE id = {$id}");
    $backfilled++;
}
echo "  + {$backfilled} usuário(s) de imobiliária com slug preenchido." . PHP_EOL;

/* ── 2/3) Índices que faltavam ───────────────────────────────────────────── */
$indices = [
    'users.uniq_slug_imob' =>
        "ALTER TABLE users ADD UNIQUE KEY uniq_slug_imob (imobiliaria_id, slug)",
    'cotacoes_anexos_condominial.idx_usuario_envio' =>
        "ALTER TABLE cotacoes_anexos_condominial ADD INDEX idx_usuario_envio (usuario_envio)",
    'cotacoes_anexos_titulo.idx_usuario_envio' =>
        "ALTER TABLE cotacoes_anexos_titulo ADD INDEX idx_usuario_envio (usuario_envio)",
];
foreach ($indices as $rotulo => $sql) {
    try {
        $conn->query($sql);
        echo "  + Índice {$rotulo} criado." . PHP_EOL;
    } catch (mysqli_sql_exception $e) {
        if (str_contains($e->getMessage(), 'Duplicate key name')) {
            echo "  ~ Índice {$rotulo} já existe, pulando." . PHP_EOL;
        } else {
            throw $e;
        }
    }
}

/* ── 4) comissoes_imobiliarias: alinha os tipos com o teste ──────────────── */
// Normaliza qualquer valor fora do domínio antes de estreitar pra ENUM, senão
// o MODIFY vira '' em modo permissivo (ou falha em STRICT).
$conn->query("UPDATE comissoes_imobiliarias SET modo = 'percentual' WHERE modo NOT IN ('percentual','valor')");

$fora = $conn->query("SELECT COUNT(*) AS c FROM comissoes_imobiliarias WHERE percentual > 999.99")->fetch_assoc();
if ((int) $fora['c'] > 0) {
    // Não estreita à força: DECIMAL(10,2) na produção é mais largo e inofensivo.
    echo "  ~ {$fora['c']} linha(s) com percentual > 999.99 — percentual mantido em DECIMAL(10,2)." . PHP_EOL;
} else {
    $conn->query("ALTER TABLE comissoes_imobiliarias MODIFY COLUMN percentual DECIMAL(5,2) NOT NULL DEFAULT 0.00");
    echo "  + comissoes_imobiliarias.percentual agora é DECIMAL(5,2)." . PHP_EOL;
}

$conn->query("ALTER TABLE comissoes_imobiliarias MODIFY COLUMN modo ENUM('percentual','valor') NOT NULL DEFAULT 'percentual'");
echo "  + comissoes_imobiliarias.modo agora é ENUM('percentual','valor')." . PHP_EOL;
