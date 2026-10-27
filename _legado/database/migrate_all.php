<?php
/**
 * Runner ÚNICO de migrations — resolve os 3 problemas dos runners antigos:
 *   1) roda .sql e .php JUNTOS, na ordem numérica real (001b, 002, ... 028);
 *   2) mantém uma tabela de controle `schema_migrations` e NUNCA reaplica
 *      um arquivo já aplicado (evita conflito / erro em .sql não-idempotente);
 *   3) permite rodar em produção de forma explícita, com backup automático antes.
 *
 * USO (rodar no terminal do servidor, a partir da raiz do projeto):
 *
 *   php database/migrate_all.php --pretend         # (padrão: TESTE) só mostra o que rodaria
 *   php database/migrate_all.php                    # aplica pendentes no DB_TEST
 *   php database/migrate_all.php --prod --pretend   # mostra o que rodaria em PRODUÇÃO
 *   php database/migrate_all.php --prod             # aplica em PRODUÇÃO (backup + confirmação)
 *   php database/migrate_all.php --prod --yes       # idem, sem o prompt (automação)
 *   php database/migrate_all.php --baseline         # marca todos como aplicados SEM rodar
 *                                                   #   (use no DB_TEST, que já está migrado à mão)
 *   php database/migrate_all.php --db=clone_ensaio  # alvo explícito (ensaio contra cópia da prod)
 *
 * Convenções:
 *   - Só toca DB_NAME (produção) com a flag --prod. Sem ela, sempre DB_TEST.
 *   - --db=NOME sobrepõe o alvo (para ensaios contra um clone); não faz backup
 *     nem pede confirmação, pois é um banco descartável explícito.
 *   - Para em qualquer erro (não continua para não cascatear).
 */

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/src/config/env.php';
load_env(BASE_PATH . '/.env');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$args     = $argv;
$isProd   = in_array('--prod', $args, true);
$pretend  = in_array('--pretend', $args, true);
$assumeYes= in_array('--yes', $args, true);
$baseline = in_array('--baseline', $args, true);

$dbOverride = '';
foreach ($args as $a) {
    if (strncmp($a, '--db=', 5) === 0) $dbOverride = substr($a, 5);
}

$database = $dbOverride !== '' ? $dbOverride : ($isProd ? env('DB_NAME') : env('DB_TEST'));
if (!$database) {
    fwrite(STDERR, "Banco alvo não definido no .env (" . ($isProd ? 'DB_NAME' : 'DB_TEST') . ").\n");
    exit(1);
}

$alvoLabel = $dbOverride !== '' ? "ALVO EXPLÍCITO ({$database})"
    : ($isProd ? "PRODUÇÃO ({$database})" : "TESTE ({$database})");
echo "== Runner de migrations ==\n";
echo "Alvo: {$alvoLabel} @ " . env('DB_HOST') . "\n";
echo $pretend ? "Modo: PRETEND (nada será executado)\n" : "";
echo $baseline ? "Modo: BASELINE (marca como aplicado sem executar)\n" : "";
echo "\n";

/* ── Confirmação + backup antes de mexer em PRODUÇÃO ─────────────────────── */
if ($isProd && !$pretend && !$baseline) {
    if (!$assumeYes) {
        echo "Você está prestes a MIGRAR A PRODUÇÃO.\n";
        echo "Digite o nome do banco para confirmar ({$database}): ";
        $resp = trim((string) fgets(STDIN));
        if ($resp !== $database) {
            fwrite(STDERR, "Confirmação não confere. Abortando.\n");
            exit(1);
        }
    }
    fazer_backup($database);
}

/* ── Conexão ─────────────────────────────────────────────────────────────── */
$conn = new mysqli(env('DB_HOST'), env('DB_USER'), env('DB_PASS'), $database, (int) env('DB_PORT', '3306'));
$conn->set_charset('utf8mb4');

/* ── Tabela de controle ──────────────────────────────────────────────────── */
$conn->query(
    "CREATE TABLE IF NOT EXISTS schema_migrations (
        arquivo VARCHAR(191) NOT NULL PRIMARY KEY,
        aplicada_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
);

$aplicadas = [];
$res = $conn->query("SELECT arquivo FROM schema_migrations");
while ($row = $res->fetch_row()) {
    $aplicadas[$row[0]] = true;
}

/* ── Lista de migrations em ordem numérica ───────────────────────────────── */
$files = glob(BASE_PATH . '/database/migrations/*.{php,sql}', GLOB_BRACE);
sort($files); // ordem alfabética coincide com a numérica (prefixos zero-padded)

$pendentes = array_filter($files, fn($f) => !isset($aplicadas[basename($f)]));

if (!$pendentes) {
    echo "Nenhuma migration pendente. Banco já está atualizado.\n";
    exit(0);
}

echo count($pendentes) . " migration(s) pendente(s):\n";
foreach ($pendentes as $f) echo "  - " . basename($f) . "\n";
echo "\n";

if ($pretend) {
    echo "(pretend) Nada foi executado.\n";
    exit(0);
}

/* ── Baseline: marca como aplicadas sem rodar ────────────────────────────── */
if ($baseline) {
    $stmt = $conn->prepare("INSERT INTO schema_migrations (arquivo) VALUES (?)");
    foreach ($pendentes as $f) {
        $b = basename($f);
        $stmt->bind_param('s', $b);
        $stmt->execute();
        echo "  ✓ marcada como aplicada: {$b}\n";
    }
    echo "\nBaseline concluído (nada foi executado no schema).\n";
    exit(0);
}

/* ── Execução ────────────────────────────────────────────────────────────── */
$stmt = $conn->prepare("INSERT INTO schema_migrations (arquivo) VALUES (?)");
foreach ($pendentes as $f) {
    $b = basename($f);
    echo str_pad($b, 54, '.');
    try {
        if (substr($f, -4) === '.sql') {
            $sql = file_get_contents($f);
            if ($conn->multi_query($sql)) {
                do { if ($r = $conn->store_result()) $r->free(); }
                while ($conn->more_results() && $conn->next_result());
            }
        } else {
            require $f; // recebe $conn no escopo
        }
        $stmt->bind_param('s', $b);
        $stmt->execute();
        echo " OK\n";
    } catch (Throwable $e) {
        echo " ERRO\n";
        fwrite(STDERR, "\nFalha em {$b}: " . $e->getMessage() . "\n");
        fwrite(STDERR, "Migração INTERROMPIDA. As já aplicadas foram registradas; corrija e rode de novo (continua de onde parou).\n");
        exit(1);
    }
}

echo "\nMigrations aplicadas com sucesso em {$alvoLabel}.\n";

/* ── Backup via mysqldump (aborta a migração se falhar) ──────────────────── */
function fazer_backup(string $database): void
{
    $dir = BASE_PATH . '/database/backups';
    if (!is_dir($dir)) mkdir($dir, 0770, true);
    $arquivo = $dir . '/backup_' . $database . '_' . date('Ymd_His') . '.sql';

    // Arquivo de credenciais temporário (0600) para não expor senha na linha de comando.
    $cnf = tempnam(sys_get_temp_dir(), 'mig');
    file_put_contents($cnf,
        "[client]\nhost=" . env('DB_HOST') . "\nport=" . env('DB_PORT', '3306') .
        "\nuser=" . env('DB_USER') . "\npassword=\"" . env('DB_PASS') . "\"\n"
    );
    chmod($cnf, 0600);

    echo "Fazendo backup de {$database} → {$arquivo} ...\n";
    $cmd = 'mysqldump --defaults-extra-file=' . escapeshellarg($cnf) .
           ' --single-transaction --quick --skip-lock-tables --routines --events ' .
           escapeshellarg($database) . ' > ' . escapeshellarg($arquivo) . ' 2>&1';
    exec($cmd, $out, $rc);
    unlink($cnf);

    if ($rc !== 0 || !is_file($arquivo) || filesize($arquivo) < 1024) {
        fwrite(STDERR, "BACKUP FALHOU (rc={$rc}). Abortando antes de tocar na produção.\n");
        fwrite(STDERR, implode("\n", $out) . "\n");
        exit(1);
    }
    echo "Backup OK (" . round(filesize($arquivo) / 1048576, 1) . " MB).\n\n";
}
