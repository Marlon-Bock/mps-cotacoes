<?php
/**
 * POST /api/auth/recuperar-senha
 * Valida o token (nas duas tabelas legadas de reset) e grava a nova senha.
 * O token só é consumido aqui — nunca no GET da página.
 */

header('Content-Type: application/json; charset=utf-8');
csrf_verify();

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$token             = (string) ($input['token'] ?? '');
$senha             = (string) ($input['senha'] ?? '');
$senha_confirmacao = (string) ($input['senha_confirmacao'] ?? '');

$errors = [];
if (strlen($senha) < 6) {
    $errors['senha'] = 'A senha deve ter no mínimo 6 caracteres.';
}
if ($senha !== $senha_confirmacao) {
    $errors['senha_confirmacao'] = 'As senhas não coincidem.';
}
if ($errors) {
    json_response(['message' => 'Verifique os dados informados.', 'errors' => $errors], 422);
}

$token_hash = hash('sha256', $token);
$conn = db();
$conn->begin_transaction();

try {
    // Tenta localizar o token em reset_senha_users (imobiliária) e depois
    // reset_senha_usuario (MPS) — a tabela onde for achado define onde
    // a senha (users.password ou usuario.password) será atualizada.
    $reset_table  = 'reset_senha_users';
    $users_table  = 'users';
    $reset = db_fetch_one(
        'SELECT id, usuario_id, expires_at, used_at, (expires_at > NOW()) AS dentro_do_prazo FROM reset_senha_users WHERE token_hash = ? LIMIT 1 FOR UPDATE',
        ['s', $token_hash]
    );

    if (!$reset) {
        $reset_table = 'reset_senha_usuario';
        $users_table = 'usuario';
        $reset = db_fetch_one(
            'SELECT id, usuario_id, expires_at, used_at, (expires_at > NOW()) AS dentro_do_prazo FROM reset_senha_usuario WHERE token_hash = ? LIMIT 1 FOR UPDATE',
            ['s', $token_hash]
        );
    }

    // Prazo checado no MySQL (mesmo relógio do NOW() que gravou expires_at) — ver pages/recuperar-senha.php.
    if (!$reset || $reset['used_at'] || (int) $reset['dentro_do_prazo'] !== 1) {
        $conn->rollback();
        json_response(['message' => 'Este link expirou ou já foi utilizado. Solicite um novo.'], 410);
    }

    db_execute("UPDATE {$users_table} SET password = ? WHERE id = ?", [
        'si', password_hash($senha, PASSWORD_DEFAULT), $reset['usuario_id'],
    ]);

    db_execute("UPDATE {$reset_table} SET used_at = NOW() WHERE id = ?", ['i', $reset['id']]);

    $conn->commit();
} catch (Throwable $e) {
    $conn->rollback();
    error_log('[RecuperarSenha] ' . $e->getMessage());
    json_response(['message' => 'Não foi possível redefinir a senha. Tente novamente.'], 500);
}

json_response(['message' => 'Senha redefinida com sucesso.']);
