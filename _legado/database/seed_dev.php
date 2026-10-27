<?php
/**
 * Seed de desenvolvimento — cria usuários de teste no banco DB_TEST,
 * usando o schema real (clonado da produção): tabelas `users` (imobiliária)
 * e `usuario` (MPS). Uso: php database/seed_dev.php
 */

define('BASE_PATH', dirname(__DIR__));
require BASE_PATH . '/src/config/env.php';
load_env(BASE_PATH . '/.env');

mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$database = env('DB_TEST');
if (!$database) {
    fwrite(STDERR, "DB_TEST não definido — abortando." . PHP_EOL);
    exit(1);
}

$conn = new mysqli(env('DB_HOST'), env('DB_USER'), env('DB_PASS'), $database, (int) env('DB_PORT', '3306'));
$conn->set_charset('utf8mb4');

echo "Seed no banco: {$database}" . PHP_EOL;

// ── Imobiliária de teste ──────────────────────────────────────────────────────
$existing = $conn->query("SELECT id FROM imobiliarias WHERE nome = 'Imobiliária Teste' LIMIT 1")->fetch_assoc();

if ($existing) {
    $imobiliariaId = $existing['id'];
} else {
    $conn->query("INSERT INTO imobiliarias (nome) VALUES ('Imobiliária Teste')");
    $imobiliariaId = $conn->insert_id;
}

// ── users: usuário da imobiliária — administrador da própria agência ────────
$senha1 = password_hash('Teste@123', PASSWORD_DEFAULT);
$stmt = $conn->prepare("INSERT INTO users
        (username, email, password, imobiliaria_id, admin, nivel, perm_criar_usuarios, perm_cotar, perm_fianca, perm_auto, perm_suporte, perm_aceitar, perm_residencial, perm_comercial)
    VALUES (?, ?, ?, ?, 'S', 'admin', 'S', 'S', 'S', 'S', 'S', 'S', 'S', 'S')
    ON DUPLICATE KEY UPDATE password = VALUES(password), admin = 'S'");
$username = 'Usuário Imobiliária';
$email = 'imobiliaria@teste.com';
$stmt->bind_param('sssi', $username, $email, $senha1, $imobiliariaId);
$stmt->execute();
echo "users (admin da agência): {$email} / Teste@123" . PHP_EOL;

// ── users: usuário da imobiliária — equipe, sem permissão administrativa ────
$senha2 = password_hash('Equipe@123', PASSWORD_DEFAULT);
$stmt = $conn->prepare("INSERT INTO users
        (username, email, password, imobiliaria_id, admin, nivel, perm_criar_usuarios, perm_cotar, perm_fianca, perm_auto, perm_suporte, perm_aceitar, perm_residencial, perm_comercial)
    VALUES (?, ?, ?, ?, 'N', 'user', 'N', 'S', 'S', 'S', 'S', 'N', 'S', 'S')
    ON DUPLICATE KEY UPDATE password = VALUES(password), admin = 'N'");
$username = 'Usuário Equipe';
$email = 'equipe@teste.com';
$stmt->bind_param('sssi', $username, $email, $senha2, $imobiliariaId);
$stmt->execute();
echo "users (equipe, sem admin): {$email} / Equipe@123" . PHP_EOL;

// ── usuario: equipe interna da MPS ───────────────────────────────────────────
$senha3 = password_hash('Admin@123', PASSWORD_DEFAULT);
$stmt = $conn->prepare("INSERT INTO usuario
        (username, email, email_cotacao, password, admin, perm_fianca, perm_auto, perm_residencial, perm_comercial, perm_suporte, equipe_comercial)
    VALUES (?, ?, ?, ?, 'S', 'S', 'S', 'S', 'S', 'S', 'S')
    ON DUPLICATE KEY UPDATE password = VALUES(password), admin = 'S'");
$username = 'Admin MPS';
$email = 'admin@teste.com';
$emailCotacao = $email;
$stmt->bind_param('ssss', $username, $email, $emailCotacao, $senha3);
$stmt->execute();
echo "usuario (MPS): {$email} / Admin@123" . PHP_EOL;

echo "Seed concluído." . PHP_EOL;
