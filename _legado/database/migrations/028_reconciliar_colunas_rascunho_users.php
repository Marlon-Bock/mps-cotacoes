<?php
/**
 * Migration 028 — reconcilia colunas que existiam só no db_mpsteste (criadas à
 * mão no dev) e não tinham migration. Detectadas no ensaio de migração contra
 * uma cópia da produção. Todas são usadas pelo app hoje:
 *   - seguro_(auto|titulo|condominial)_rascunho.protocolo  → SELECT/DELETE de rascunho
 *   - users.slug                                           → editar usuário / rotas
 *   - users.perm_condo / perm_titulo / perm_incendio       → lidas no login
 *
 * Idempotente: cada ALTER é protegido contra "Duplicate column name", então
 * rodar de novo é seguro.
 */

$alteracoes = [
    'seguro_auto_rascunho.protocolo'        => "ALTER TABLE seguro_auto_rascunho ADD COLUMN protocolo VARCHAR(30) NULL",
    'seguro_titulo_rascunho.protocolo'      => "ALTER TABLE seguro_titulo_rascunho ADD COLUMN protocolo VARCHAR(30) NULL",
    'seguro_condominial_rascunho.protocolo' => "ALTER TABLE seguro_condominial_rascunho ADD COLUMN protocolo VARCHAR(30) NULL",
    'users.slug'                            => "ALTER TABLE users ADD COLUMN slug VARCHAR(160) NULL",
    'users.perm_condo'                      => "ALTER TABLE users ADD COLUMN perm_condo ENUM('S','N') NOT NULL DEFAULT 'N'",
    'users.perm_titulo'                     => "ALTER TABLE users ADD COLUMN perm_titulo ENUM('S','N') NOT NULL DEFAULT 'N'",
    'users.perm_incendio'                   => "ALTER TABLE users ADD COLUMN perm_incendio ENUM('S','N') NOT NULL DEFAULT 'N'",
];

foreach ($alteracoes as $rotulo => $sql) {
    try {
        $conn->query($sql);
        echo "  + {$rotulo} adicionada." . PHP_EOL;
    } catch (mysqli_sql_exception $e) {
        if (str_contains($e->getMessage(), 'Duplicate column name')) {
            echo "  ~ {$rotulo} já existe, pulando." . PHP_EOL;
        } else {
            throw $e;
        }
    }
}
