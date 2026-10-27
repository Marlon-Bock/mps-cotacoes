<?php
/**
 * Migration 081 — Permite 2 (ou mais) contas de imobiliária com o mesmo
 * e-mail (ex: Imobiliária Antônio Carlos pediu 2 usuários no mesmo e-mail,
 * com senhas diferentes). A tabela `users` tinha DUAS constraints UNIQUE
 * redundantes na coluna email — removidas e substituídas por um índice
 * normal (KEY), só pra manter a busca por e-mail rápida sem travar
 * duplicado.
 *
 * login.php foi ajustado pra testar a senha contra TODAS as contas que
 * batem o e-mail (em vez de pegar só a primeira com LIMIT 1), então contas
 * com e-mail único continuam funcionando exatamente igual — só passa a
 * suportar duplicado quando ele existir de propósito.
 *
 * A tela de criar usuário continua bloqueando e-mail duplicado por padrão
 * (ninguém cria duplicado por acidente) — pra criar um segundo usuário com
 * e-mail repetido, é um INSERT manual, caso a caso.
 */

foreach (['uq_email', 'uq_users_email'] as $constraint) {
    try {
        $conn->query("ALTER TABLE users DROP INDEX {$constraint}");
        echo "  + Constraint {$constraint} removida." . PHP_EOL;
    } catch (Throwable $e) {
        if (str_contains($e->getMessage(), "check that column/key exists") || str_contains($e->getMessage(), "doesn't exist")) {
            echo "  = Constraint {$constraint} já não existia." . PHP_EOL;
        } else {
            throw $e;
        }
    }
}

try {
    $conn->query("ALTER TABLE users ADD INDEX idx_users_email (email)");
    echo "  + Índice idx_users_email (não único) adicionado." . PHP_EOL;
} catch (Throwable $e) {
    if (str_contains($e->getMessage(), 'Duplicate key name')) {
        echo "  = Índice idx_users_email já existia." . PHP_EOL;
    } else {
        throw $e;
    }
}
