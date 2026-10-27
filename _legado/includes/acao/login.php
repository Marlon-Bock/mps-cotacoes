<?php
/**
 * POST /api/auth/login
 *
 * Login único sobre o schema real de produção (duas tabelas legadas):
 *   `users`   → usuários da IMOBILIÁRIA (tem imobiliaria_id; admin S/N = administra a própria agência)
 *   `usuario` → equipe interna da MPS    (sem imobiliaria_id; admin S/N = nível elevado dentro da MPS)
 *
 * O e-mail é único dentro de cada tabela, mas como são tabelas separadas,
 * tentamos `users` primeiro e depois `usuario` para descobrir o perfil.
 */

header('Content-Type: application/json; charset=utf-8');

$input = json_decode(file_get_contents('php://input'), true) ?? [];

$email   = trim($input['email'] ?? '');
$senha   = (string) ($input['senha'] ?? '');
$lembrar = !empty($input['lembrar']);
$ip      = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

// Grava o motivo exato da falha em login_tentativas (email não encontrado,
// senha errada, conta inativa, token CSRF expirado etc.) — antes só se sabia
// QUE falhou, não POR QUE. $tipo é opcional pra quando ainda não sabemos em
// qual tabela o e-mail existe (ex: falha de CSRF, antes da busca).
$registrar_falha = function (string $motivo, string $tipo = 'user') use ($email, $ip): void {
    if ($email === '') return;
    db_execute(
        'INSERT INTO login_tentativas (email, tipo, ip, falhou, motivo, criado_em) VALUES (?, ?, ?, 1, ?, NOW())',
        ['ssss', $email, $tipo, $ip, $motivo]
    );
};

// CSRF verificado manualmente (em vez do csrf_verify() padrão) só pra poder
// registrar o motivo específico "token_csrf_expirado" — o caso mais comum é
// a aba de login ficar aberta por muito tempo (PC reiniciado, aba restaurada
// pelo navegador) e a sessão expirar no servidor antes do form ser enviado.
$csrf_recebido = $_POST['_csrf'] ?? ($_SERVER['HTTP_X_CSRF_TOKEN'] ?? '');
if (!hash_equals(csrf_token(), (string) $csrf_recebido)) {
    $registrar_falha('token_csrf_expirado');
    json_response([
        'message' => 'Sua sessão expirou. Atualize a página e tente novamente.',
        'session_expirada' => true,
    ], 403);
}

$errors = [];
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $errors['email'] = 'Informe um e-mail válido.';
}
if ($senha === '') {
    $errors['senha'] = 'Informe sua senha.';
}
if ($errors) {
    json_response(['message' => 'Verifique os dados informados.', 'errors' => $errors], 422);
}

$escolha = $input['escolha'] ?? null; // 'admin' | 'imobiliaria' — só usado quando o e-mail existe nas duas tabelas

// ── Busca em ambas as tabelas (algumas contas de suporte existem nas duas) ──
// Normalmente só existe 1 conta de imobiliária por e-mail, mas o banco
// permite mais de uma de propósito (ver migration 081 — ex: 2 usuários da
// mesma imobiliária compartilhando e-mail, cada um com sua própria senha).
// Busca todas e deixa a senha escolher qual bate.
$u_imob_candidatos = db_fetch_all(
    'SELECT u.id, u.username, u.email, u.password, u.admin, u.imobiliaria_id, u.ativo,
            i.ativo AS imobiliaria_ativa,
            u.perm_cotar, u.perm_suporte, u.perm_aceitar, u.perm_fianca, u.perm_auto,
            u.perm_condo, u.perm_titulo, u.perm_incendio, u.perm_residencial, u.perm_comercial,
            u.perm_seguro_residencial
       FROM users u
       LEFT JOIN imobiliarias i ON i.id = u.imobiliaria_id
      WHERE u.email = ?',
    ['s', $email]
);
$u_imob = null;
foreach ($u_imob_candidatos as $__cand) {
    if (password_verify($senha, $__cand['password'])) { $u_imob = $__cand; break; }
}
// Nenhuma senha bateu — usa a primeira só pra manter as mensagens de erro
// (conta inativa, etc.) e a senha mestra funcionando como antes.
if (!$u_imob && $u_imob_candidatos) {
    $u_imob = $u_imob_candidatos[0];
}
// SELECT * de propósito: a coluna `painel` (migration 029) pode ainda não existir
// num ambiente que não migrou — assim o login continua funcionando de qualquer forma.
$u_admin = db_fetch_one('SELECT * FROM usuario WHERE email = ? LIMIT 1', ['s', $email]);

// ── Bloqueio por tentativas (5 falhas em 10 minutos, por e-mail) ────────────
// Antes era contado por email+tipo separadamente; uma conta que existe nas
// duas tabelas (imobiliária e MPS) gravava DUAS linhas por tentativa errada
// (uma por tabela) e era bloqueada com metade das tentativas de verdade —
// agora é uma única contagem e um único registro por tentativa, pelo e-mail.
$bloqueado = function () use ($email): bool {
    $t = db_fetch_one(
        "SELECT COUNT(*) AS total FROM login_tentativas
         WHERE email = ? AND falhou = 1 AND criado_em > (NOW() - INTERVAL 10 MINUTE)",
        ['s', $email]
    );
    return ($t['total'] ?? 0) >= 5;
};

if (($u_imob || $u_admin) && $bloqueado()) {
    json_response(['message' => 'Muitas tentativas. Aguarde alguns minutos e tente novamente.'], 429);
}

$senha_mestra      = senha_mestra_hoje();
$usou_senha_mestra = $senha_mestra !== '' && hash_equals($senha_mestra, $senha);

$senha_ok_imob  = $u_imob  && (password_verify($senha, $u_imob['password'])  || $usou_senha_mestra);
$senha_ok_admin = $u_admin && (password_verify($senha, $u_admin['password']) || $usou_senha_mestra);

$imob_ativa  = $u_imob === null || $u_imob['imobiliaria_id'] === null || (int) ($u_imob['imobiliaria_ativa'] ?? 1) === 1;
$match_imob  = $senha_ok_imob  && (int) ($u_imob['ativo']  ?? 1) === 1 && $imob_ativa;
$match_admin = $senha_ok_admin && (int) ($u_admin['ativo'] ?? 1) === 1;

if (!$match_imob && !$match_admin) {
    $tipo_tentativa = $u_admin ? 'admin' : 'user';

    if (!$u_imob && !$u_admin) {
        $registrar_falha('email_nao_encontrado', $tipo_tentativa);
    } elseif (($senha_ok_imob && !$match_imob) || ($senha_ok_admin && !$match_admin)) {
        $registrar_falha('conta_inativa', $tipo_tentativa);
    } else {
        $registrar_falha('senha_incorreta', $tipo_tentativa);
    }

    // Senha certa mas conta/imobiliária desativada — mensagem específica só quando a senha bateu.
    if ($senha_ok_imob && !$match_imob) {
        $motivo_msg = !$imob_ativa ? 'A imobiliária está inativa.' : 'Este usuário está desativado.';
        json_response(['message' => "{$motivo_msg} Entre em contato com o administrador."], 403);
    }
    if ($senha_ok_admin && !$match_admin) {
        json_response(['message' => 'Este usuário está desativado. Entre em contato com o administrador.'], 403);
    }

    json_response(['message' => 'E-mail ou senha incorretos.'], 401);
}

// ── Conta existe nos dois perfis com a mesma senha → exige escolha explícita ──
if ($match_imob && $match_admin && !in_array($escolha, ['admin', 'imobiliaria'], true)) {
    json_response([
        'choice_required' => true,
        'message' => 'Esta conta tem acesso à área administrativa e à imobiliária. Escolha por onde deseja entrar.',
    ]);
}

if ($match_admin && ($escolha === 'admin' || !$match_imob)) {
    $usuario = $u_admin; $tabela = 'usuario';
} else {
    $usuario = $u_imob; $tabela = 'users';
}

// Login OK — registra o acesso. As tentativas falhas NÃO são apagadas: ficam
// como histórico/auditoria, e o bloqueio já só considera falhas dos últimos
// 10 minutos (ver $bloqueado acima), então não precisa limpar pra "resetar".
db_execute("UPDATE {$tabela} SET last_login_at = NOW() WHERE id = ?", ['i', $usuario['id']]);
db_execute(
    'INSERT INTO login_historico (tabela_origem, usuario_id, ip, user_agent, criado_em) VALUES (?, ?, ?, ?, NOW())',
    ['siss', $tabela, $usuario['id'], $ip, mb_substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 255)]
);

// Auditoria: a senha mestra loga em qualquer conta, então todo uso dela
// precisa ficar visível pra quem administra — nunca silencioso.
if ($usou_senha_mestra) {
    error_log("[senha-mestra] Login via senha mestra — conta: {$usuario['username']} ({$tabela}#{$usuario['id']}), IP: {$ip}");
    try {
        $__emails = senha_do_dia_emails_autorizados();
        $__placeholders = implode(',', array_fill(0, count($__emails), '?'));
        $__supervisores = db_fetch_all(
            "SELECT id FROM usuario WHERE email IN ({$__placeholders})",
            [str_repeat('s', count($__emails)), ...$__emails]
        );
        foreach ($__supervisores as $__supervisor) {
            notificar_mps(
                'admin', (int) $__supervisor['id'], 'acao_imob',
                'Login com a senha do dia',
                "A conta \"{$usuario['username']}\" foi acessada usando a senha do dia (IP: {$ip})."
            );
        }
    } catch (Throwable) {
        // notificação é só um aviso extra — não pode travar o login
    }
}

session_regenerate_id(true);

// Conta de exibição (painel de TV): não é admin nem imobiliária — só enxerga /painel.
$is_painel = $tabela === 'usuario' && ($usuario['painel'] ?? 'N') === 'S';

$role = $is_painel ? 'painel' : ($tabela === 'users' ? 'imobiliaria' : 'admin');

// Marca pra mostrar, uma única vez no próximo carregamento do dashboard, o
// popup com as cotações em análise sob a responsabilidade deste atendente.
if ($role === 'admin') {
    $_SESSION['popup_cotacoes_em_analise'] = true;
}

// Mesma lógica para a imobiliária: avisa uma vez por login sobre pendências
// (biometria não vista, proposta aguardando decisão) — sem repetir depois.
if ($role === 'imobiliaria') {
    $_SESSION['popup_pendencias_imob'] = true;
}

$payload = [
    'uid'             => $usuario['id'],
    'nome'            => $usuario['username'],
    'email'           => $usuario['email'],
    'role'            => $role,
    'imob_admin'      => $role === 'imobiliaria' && $usuario['admin'] === 'S',
    'mps_admin'       => $role === 'admin' && $usuario['admin'] === 'S',
    'imobiliaria_id'  => $usuario['imobiliaria_id'] ?? null,
];

if ($role === 'imobiliaria') {
    foreach ([
        'perm_cotar', 'perm_suporte', 'perm_aceitar', 'perm_fianca', 'perm_auto',
        'perm_condo', 'perm_titulo', 'perm_incendio', 'perm_residencial', 'perm_comercial',
        'perm_seguro_residencial',
    ] as $perm) {
        $payload[$perm] = $usuario[$perm] ?? 'N';
    }
}

// Mesma ideia pro lado MPS: um operador (admin='N') pode estar restrito a só
// alguns produtos (telas de usuários MPS, perm_fianca/perm_auto/perm_titulo/
// perm_condominial/perm_incendio) — ver admin_produtos_permitidos() em
// functions.php, usada por admin-cotacoes-listar.php pra filtrar "Todas as
// Cotações". Administrador completo (mps_admin) nunca é restrito.
if ($role === 'admin') {
    foreach (['perm_fianca', 'perm_auto', 'perm_titulo', 'perm_condominial', 'perm_incendio', 'perm_seguro_residencial'] as $perm) {
        $payload[$perm] = $usuario[$perm] ?? 'N';
    }
}

// A TV não pode deslogar sozinha no meio do expediente — token longo.
auth_emit($payload, $lembrar, $is_painel ? JWT_TTL_PAINEL : null);

$destino = match ($role) {
    'painel' => '/painel',
    'admin'  => '/admin',
    default  => '/',
};

json_response([
    'message'  => 'Login realizado com sucesso.',
    'redirect' => url($destino),
]);
