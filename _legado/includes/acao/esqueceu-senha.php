<?php
/**
 * POST /api/auth/esqueceu-senha
 *
 * Procura o e-mail em `users` (imobiliária) e depois `usuario` (MPS) para
 * decidir em qual tabela de reset gravar o token:
 *   users   → reset_senha_users
 *   usuario → reset_senha_usuario
 */

header('Content-Type: application/json; charset=utf-8');
csrf_verify();

$input = json_decode(file_get_contents('php://input'), true) ?? [];
$email = trim($input['email'] ?? '');

if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    json_response(['message' => 'Informe um e-mail válido.', 'errors' => ['email' => 'E-mail inválido.']], 422);
}

// O mesmo e-mail pode ter mais de uma conta de imobiliária (migration 081 —
// cada uma com sua senha). Nesse caso a pessoa escolhe qual conta recuperar:
// sem isso o link ia sempre pra primeira conta e trocava a senha da colega.
$contas = db_fetch_all('SELECT id, username FROM users WHERE email = ? ORDER BY username, id', ['s', $email]);
$usuario = $contas[0] ?? null;
$reset_table = 'reset_senha_users';

if (count($contas) > 1) {
    $escolhido = (int) ($input['usuario_id'] ?? 0);
    $usuario   = null;
    foreach ($contas as $conta) {
        if ((int) $conta['id'] === $escolhido) { $usuario = $conta; break; }
    }
    if (!$usuario) {
        // Ainda não escolheu (ou mandou uma conta que não é deste e-mail):
        // devolve as contas pra tela perguntar qual é a dela.
        json_response([
            'escolher_usuario' => true,
            'message'  => 'Este e-mail tem mais de uma conta. Selecione a sua.',
            'usuarios' => array_map(fn($c) => ['id' => (int) $c['id'], 'nome' => $c['username']], $contas),
        ]);
    }
}

if (!$usuario) {
    $usuario = db_fetch_one('SELECT id, username FROM usuario WHERE email = ? LIMIT 1', ['s', $email]);
    $reset_table = 'reset_senha_usuario';
}

// E-mail não cadastrado — avisa claramente (sistema interno, sem anti-enumeração).
if (!$usuario) {
    json_response([
        'message' => 'E-mail não encontrado em nossa base.',
        'errors'  => ['email' => 'Nenhuma conta usa este e-mail.'],
    ], 404);
}

// Throttle: só permite um pedido por hora. Se já houver um recente, avisa até
// que horário esperar em vez de fingir que enviou.
$recente = db_fetch_one(
    "SELECT created_at FROM {$reset_table}
     WHERE usuario_id = ? AND created_at > (NOW() - INTERVAL 60 MINUTE)
     ORDER BY created_at DESC LIMIT 1",
    ['i', $usuario['id']]
);

if ($recente) {
    $liberaEm = date('H:i', strtotime($recente['created_at']) + 3600);
    json_response([
        'message' => "Já enviamos um link de recuperação há pouco. Verifique seu e-mail (inclusive o spam) ou tente novamente após as {$liberaEm}.",
    ], 429);
}

// Invalida tokens anteriores ainda não usados e gera um novo.
db_execute(
    "UPDATE {$reset_table} SET used_at = NOW() WHERE usuario_id = ? AND used_at IS NULL",
    ['i', $usuario['id']]
);

$token     = bin2hex(random_bytes(32));
$tokenHash = hash('sha256', $token);
$ip        = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$userAgent = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255);

db_execute(
    "INSERT INTO {$reset_table} (usuario_id, token_hash, expires_at, created_at, ip, user_agent)
     VALUES (?, ?, NOW() + INTERVAL 15 MINUTE, NOW(), ?, ?)",
    ['isss', $usuario['id'], $tokenHash, $ip, $userAgent]
);

$link = APP_URL . url('/recuperar-senha/' . $token);
$nome = $usuario['username'] ?? '';

$corpo = '<p style="margin:0 0 14px;">Olá' . ($nome !== '' ? ', ' . e($nome) : '') . '!</p>'
    . '<p style="margin:0 0 14px;">Recebemos um pedido para redefinir a senha da sua conta no MPS Cotações. '
    . 'O link abaixo expira em <strong>15 minutos</strong>.</p>'
    . '<p style="margin:22px 0;">' . email_botao('Criar uma nova senha', $link) . '</p>'
    . '<p style="margin:0;color:#757575;font-size:13px;">Se você não pediu a troca de senha, ignore este e-mail — sua senha continua a mesma.</p>';

if (!send_email($email, 'Recuperação de senha — MPS Cotações', $corpo)) {
    error_log("[ResetSenha] Falha ao enviar e-mail para {$email}. Link: {$link}");
    // Desfaz o token recém-criado para o usuário poder tentar de novo na hora.
    // Precisa APAGAR (não só marcar used_at): o throttle acima conta qualquer
    // linha criada na última hora — marcando como usado, uma falha do SMTP
    // deixava a pessoa 1h sem conseguir pedir o link de novo.
    db_execute("DELETE FROM {$reset_table} WHERE token_hash = ?", ['s', $tokenHash]);
    json_response([
        'message' => 'Não foi possível enviar o e-mail agora. Tente novamente em instantes.',
    ], 500);
}

json_response(['message' => 'Link de recuperação enviado para o seu e-mail.']);
