<?php
/**
 * POST /api/perfil
 * Atualiza dados pessoais do usuário logado (imobiliária ou admin),
 * com troca de senha opcional. Nunca aceita id/tabela do cliente.
 */
header('Content-Type: application/json; charset=utf-8');
csrf_verify();

$current_user = require_auth();
$tabela = $current_user->role === 'admin' ? 'usuario' : 'users';

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$username = trim((string) ($input['username'] ?? ''));
$email    = trim((string) ($input['email'] ?? ''));
$telefone = trim((string) ($input['telefone'] ?? ''));
$senha_atual = (string) ($input['senha_atual'] ?? '');
$nova_senha  = (string) ($input['nova_senha'] ?? '');

// email_cotacao só existe (e só é editável) na tabela `usuario` (equipe MPS).
$email_cotacao = $tabela === 'usuario' ? trim((string) ($input['email_cotacao'] ?? '')) : null;

$errors = [];
if ($username === '') $errors['username'] = 'Informe seu nome.';
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) $errors['email'] = 'Informe um e-mail válido.';
if ($email_cotacao !== null && $email_cotacao !== '' && !filter_var($email_cotacao, FILTER_VALIDATE_EMAIL)) {
    $errors['email_cotacao'] = 'Informe um e-mail válido ou deixe em branco.';
}
if ($nova_senha !== '' && strlen($nova_senha) < 6) $errors['nova_senha'] = 'A nova senha deve ter ao menos 6 caracteres.';
if ($nova_senha !== '' && $senha_atual === '') $errors['senha_atual'] = 'Informe a senha atual para definir uma nova.';
if ($errors) {
    json_response(['message' => 'Verifique os dados informados.', 'errors' => $errors], 422);
}

// E-mail único dentro da mesma tabela (exceto o próprio registro) — só quando
// o e-mail MUDOU: contas de imobiliária podem compartilhar e-mail de propósito
// (migration 081), e sem essa condição nenhuma delas conseguia salvar o perfil.
$email_atual = db_fetch_one("SELECT email FROM {$tabela} WHERE id = ?", ['i', $current_user->uid])['email'] ?? '';
$duplicado = strcasecmp($email, (string) $email_atual) !== 0
    ? db_fetch_one("SELECT id FROM {$tabela} WHERE email = ? AND id != ?", ['si', $email, $current_user->uid])
    : null;
if ($duplicado) {
    json_response(['message' => 'Verifique os dados informados.', 'errors' => ['email' => 'Este e-mail já está em uso.']], 422);
}

$valores_bd = [$username, $email, $telefone];
$tipos_bd   = 'sss';

if ($email_cotacao !== null) {
    // email_cotacao é NOT NULL no banco — vazio fica string vazia, não null
    // (resolver_email_cotacao() já trata string vazia como "sem override").
    $valores_bd[] = $email_cotacao;
    $tipos_bd    .= 's';
}

$sql_campos = 'username = ?, email = ?, telefone = ?' . ($email_cotacao !== null ? ', email_cotacao = ?' : '');

if ($nova_senha !== '') {
    $atual = db_fetch_one("SELECT password FROM {$tabela} WHERE id = ?", ['i', $current_user->uid]);
    if (!$atual || !password_verify($senha_atual, $atual['password'])) {
        json_response(['message' => 'Verifique os dados informados.', 'errors' => ['senha_atual' => 'Senha atual incorreta.']], 422);
    }
    $valores_bd[] = password_hash($nova_senha, PASSWORD_DEFAULT);
    $tipos_bd    .= 's';
    $sql_campos  .= ', password = ?';
}

$valores_bd[] = $current_user->uid;
$tipos_bd    .= 'i';

db_execute("UPDATE {$tabela} SET {$sql_campos} WHERE id = ?", [$tipos_bd, ...$valores_bd]);

// Identidade mudou (nome/e-mail) ou senha mudou — reemite o JWT já com os novos dados
auth_emit([
    'uid'            => $current_user->uid,
    'nome'           => $username,
    'email'          => $email,
    'role'           => $current_user->role,
    'imob_admin'     => $current_user->imob_admin ?? false,
    'mps_admin'      => $current_user->mps_admin ?? false,
    'imobiliaria_id' => $current_user->imobiliaria_id ?? null,
]);

json_response(['message' => 'Perfil atualizado com sucesso.']);
