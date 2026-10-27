<?php
/**
 * Script ONE-OFF de remoção do módulo de Tarefas (código já removido do app).
 * Dropa todas as tabelas de tarefas + `setores` e remove as colunas
 * `usuario.setor_id` e `usuario.perm_tarefas_admin`.
 *
 * Segue o mesmo padrão dos runners (database/migrate.php): roda contra DB_TEST.
 * Para produção, rode este mesmo SQL manualmente com um DBA apontando pro
 * banco de produção (DB_NAME) — este script NUNCA toca DB_NAME de propósito.
 *
 * Uso: php database/remover_tarefas.php
 */

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/src/config/env.php';
load_env(BASE_PATH . '/.env');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$database = env('DB_TEST');
if (!$database) {
    fwrite(STDERR, "DB_TEST não definido no .env — abortando para proteger produção." . PHP_EOL);
    exit(1);
}

$conn = new mysqli(env('DB_HOST'), env('DB_USER'), env('DB_PASS'), $database, (int) env('DB_PORT', '3306'));
$conn->set_charset('utf8mb4');
echo "Conectado ao banco: {$database} @ " . env('DB_HOST') . PHP_EOL;

$tabelas = [
    'tarefas_historico',
    'tarefas_compartilhamentos',
    'tarefas_mencoes',
    'tarefas_comentarios_lidas',
    'tarefas_comentarios',
    'tarefas_checklist_itens',
    'tarefas_checklists',
    'tarefas_anexos',
    'tarefas_etiquetas_vinculo',
    'tarefas_responsaveis',
    'tarefas_quadro_etapas',
    'tarefas',
    'tarefas_grupos',
    'tarefas_quadros',
    'tarefas_etiquetas',
    'tarefas_prioridades',
    'tarefas_etapas',
    'tarefas_status',
    'tarefas_categorias',
    'setores',
];

$conn->query('SET FOREIGN_KEY_CHECKS = 0');
foreach ($tabelas as $t) {
    $conn->query("DROP TABLE IF EXISTS `{$t}`");
    echo "  - DROP TABLE {$t}" . PHP_EOL;
}
$conn->query('SET FOREIGN_KEY_CHECKS = 1');

// Colunas em usuario — dropa só se existirem (compatível com MySQL sem IF EXISTS).
foreach (['setor_id', 'perm_tarefas_admin'] as $coluna) {
    $row = $conn->query(
        "SELECT COUNT(*) AS n FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = '{$database}' AND TABLE_NAME = 'usuario' AND COLUMN_NAME = '{$coluna}'"
    )->fetch_assoc();
    if ((int) $row['n'] > 0) {
        $conn->query("ALTER TABLE usuario DROP COLUMN `{$coluna}`");
        echo "  - usuario.{$coluna} removida" . PHP_EOL;
    } else {
        echo "  ~ usuario.{$coluna} não existe, pulando" . PHP_EOL;
    }
}

echo "Remoção concluída." . PHP_EOL;
