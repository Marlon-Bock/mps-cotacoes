<?php
/**
 * Núcleo de compatibilidade: as funções do sistema antigo (dev-cotacoes, PHP
 * puro) implementadas sobre o Laravel. Os helpers de app/Legado/*.php e os
 * controllers chamam estas funções com os MESMOS nomes e assinaturas de antes;
 * por baixo é o Laravel (conexão/DB, sessão, auth guards, respostas).
 *
 * Tipos: o antigo usava prepared statement + get_result() (protocolo binário),
 * que devolve INT como int e DECIMAL/datas como string — o PDO configurado em
 * config/database.php faz igual.
 *
 * Diferença importante: no sistema antigo redirect()/json_response() davam
 * exit. Aqui elas LANÇAM a resposta (HttpResponseException) — o Laravel
 * envia e nada depois roda, mesmo comportamento. redirect() é o do Laravel
 * (devolve a resposta: use `return redirect(...)`); para "redirecionar e
 * parar" no meio de um helper, use ir_para().
 */

use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

if (!defined('BASE_PATH')) {
    define('BASE_PATH', dirname(__DIR__, 2));
}

// ─── Banco ──────────────────────────────────────────────────────────────────
// Parâmetros no formato antigo: ['tipos', valor1, valor2, ...] com tipos
// 'i' (int), 'd' (float), 's' (string), 'b' (blob). Os valores são
// convertidos pelo tipo — assim `LIMIT ?` com 'i' vai como inteiro.

/** @internal Converte ['tipos', ...valores] nos valores tipados do PDO. */
function db_parametros(array $params): array
{
    if ($params === []) {
        return [];
    }
    $tipos   = (string) array_shift($params);
    $valores = array_values($params);
    foreach ($valores as $i => $v) {
        if ($v === null) continue;
        $valores[$i] = match ($tipos[$i] ?? 's') {
            'i'     => (int) $v,
            'd'     => (float) $v,
            default => is_bool($v) ? (int) $v : (string) $v,
        };
    }
    return $valores;
}

/** Linhas como arrays associativos (tipos nativos: INT → int, DECIMAL/datas → string — como o mysqli antigo). */
function db_fetch_all(string $sql, array $params = []): array
{
    return array_map(static fn($r) => (array) $r, DB::select($sql, db_parametros($params)));
}

function db_fetch_one(string $sql, array $params = []): ?array
{
    $r = DB::selectOne($sql, db_parametros($params));
    return $r ? (array) $r : null;
}

/**
 * INSERT/UPDATE/DELETE. Retorna o id gerado (INSERT que inseriu) ou o número
 * de linhas afetadas — mesmo contrato do mysqli (insert_id ?: affected_rows).
 * Toda escrita que muda o banco vai pra auditoria (antes/depois — auditoria.php).
 */
function db_execute(string $sql, array $params = []): int
{
    $valores  = db_parametros($params);
    $antes    = auditoria_antes($sql, $valores);
    $afetadas = DB::affectingStatement($sql, $valores);
    $id       = 0;
    if ($afetadas > 0 && preg_match('/^\s*(INSERT|REPLACE)\b/i', $sql)) {
        $id = (int) DB::getPdo()->lastInsertId();
    }
    auditoria_escrita($sql, $valores, $afetadas, $antes, $id ?: null);
    return $id > 0 ? $id : $afetadas;
}

/** Compatível com o antigo db_query(): SELECT → linhas; demais → true. */
function db_query(string $sql, array $params = []): array|bool
{
    if (preg_match('/^\s*(SELECT|SHOW|DESCRIBE|EXPLAIN)\b/i', $sql)) {
        return db_fetch_all($sql, $params);
    }
    $valores = db_parametros($params);
    $antes   = auditoria_antes($sql, $valores);
    if ($antes === null) {
        return DB::statement($sql, $valores);   // DDL e afins: fora da auditoria
    }
    $afetadas = DB::affectingStatement($sql, $valores);
    $id = $afetadas > 0 && preg_match('/^\s*(INSERT|REPLACE)\b/i', $sql) ? (int) DB::getPdo()->lastInsertId() : 0;
    auditoria_escrita($sql, $valores, $afetadas, $antes, $id ?: null);
    return true;
}

/** PDO da conexão do Laravel (o antigo devolvia mysqli). */
function db(): \PDO
{
    return DB::connection()->getPdo();
}

/**
 * Solta a conexão antes de uma chamada de rede longa (API das seguradoras):
 * o Laravel reconecta sozinho na próxima consulta.
 */
function db_fechar_temporariamente(): void
{
    DB::disconnect();
}

// ─── Respostas ────────────────────────────────────────────────────────────────

/** Responde JSON e encerra (lança a resposta — nada depois roda). */
function json_response(mixed $data, int $status = 200): never
{
    throw new HttpResponseException(response()->json($data, $status, [], JSON_UNESCAPED_UNICODE));
}

/** Redireciona e encerra (o antigo redirect() do sistema). Caminho interno ganha o base path. */
function ir_para(string $url): never
{
    throw new HttpResponseException(redirect()->to(url_interna($url)));
}

/** Caminho interno → URL com o base path (externos e protocolo-relativos ficam como estão). */
function url_interna(string $url): string
{
    return str_starts_with($url, '/') && !str_starts_with($url, '//') ? url($url) : $url;
}

/** CSRF agora é validado pelo middleware do Laravel (header X-CSRF-Token ou _token/_csrf). */
function csrf_verify(): void
{
}

/** Caminho da requisição sem o base path, começando com '/'. */
function request_path(): string
{
    return '/' . ltrim(request()->path(), '/');
}

/**
 * Content-Security-Policy do sistema. `$frameAncestors` é a única diretiva que
 * varia — as páginas de embed precisam liberar o domínio do cliente.
 */
function csp_header(string $frameAncestors = "'self'"): string
{
    return "Content-Security-Policy: " .
        "default-src 'self'; " .
        "script-src 'self' 'unsafe-inline' https://challenges.cloudflare.com; " .
        "style-src 'self' 'unsafe-inline'; " .
        // Arquivos antigos (avatares, logos) continuam no media da Mesquita; os
        // novos vão pro MEDIA_URL (assets, até o media vir pra este servidor).
        "img-src 'self' data: " . MEDIA_URL . " https://media.mesquitaprime.com.br; " .
        "connect-src 'self' https://viacep.com.br; " .
        "object-src 'none'; " .
        "base-uri 'self'; " .
        "frame-src 'self' https://challenges.cloudflare.com; " .
        "frame-ancestors {$frameAncestors}";
}

// ─── Autenticação ─────────────────────────────────────────────────────────────
// Dois guards de sessão (config/auth.php): 'admin' (tabela usuario — equipe
// MPS e a conta do painel de TV) e 'imobiliaria' (tabela users). O usuário
// logado é exposto no MESMO formato do antigo payload do JWT (objeto com
// uid, nome, role, imob_admin, mps_admin, imobiliaria_id e perm_*), lido do
// banco a cada requisição — permissão alterada vale na hora.

const PERMS_IMOBILIARIA = [
    'perm_cotar', 'perm_suporte', 'perm_aceitar', 'perm_fianca', 'perm_auto',
    'perm_condo', 'perm_titulo', 'perm_incendio', 'perm_residencial', 'perm_comercial',
    'perm_seguro_residencial',
];
const PERMS_MPS = ['perm_fianca', 'perm_auto', 'perm_titulo', 'perm_condominial', 'perm_incendio', 'perm_seguro_residencial'];

/** Usuário logado (formato do antigo payload) ou null. */
function auth_user(): ?object
{
    if ($u = Auth::guard('admin')->user()) {
        $painel  = ($u->painel ?? 'N') === 'S';
        $payload = [
            'uid'            => (int) $u->id,
            'nome'           => (string) $u->username,
            'email'          => (string) $u->email,
            'role'           => $painel ? 'painel' : 'admin',
            'imob_admin'     => false,
            'mps_admin'      => !$painel && ($u->admin ?? 'N') === 'S',
            'equipe_comercial' => !$painel && ($u->equipe_comercial ?? 'N') === 'S',
            'imobiliaria_id' => null,
        ];
        if (!$painel) {
            foreach (PERMS_MPS as $perm) {
                $payload[$perm] = $u->{$perm} ?? 'N';
            }
        }
        return (object) $payload;
    }

    if ($u = Auth::guard('imobiliaria')->user()) {
        $payload = [
            'uid'            => (int) $u->id,
            'nome'           => (string) $u->username,
            'email'          => (string) $u->email,
            'role'           => 'imobiliaria',
            'imob_admin'     => ($u->admin ?? 'N') === 'S',
            'mps_admin'      => false,
            'imobiliaria_id' => $u->imobiliaria_id !== null ? (int) $u->imobiliaria_id : null,
        ];
        foreach (PERMS_IMOBILIARIA as $perm) {
            $payload[$perm] = $u->{$perm} ?? 'N';
        }
        return (object) $payload;
    }

    return null;
}

/** Exige login. Sem sessão → /login; conta/imobiliária desativada → derruba a sessão. */
function require_auth(): object
{
    $user = auth_user();
    if (!$user) {
        if (request()->expectsJson() || str_starts_with(request_path(), '/api/')) {
            json_response(['success' => false, 'message' => 'Sua sessão expirou. Entre de novo.', 'session_expirada' => true], 401);
        }
        ir_para(request()->hasCookie(config('session.cookie')) ? '/sessao-expirada' : '/login');
    }

    // Conta de exibição (TV) não navega pelo sistema — sempre volta pro painel.
    if ($user->role === 'painel') {
        ir_para('/painel');
    }

    // Usuário ou imobiliária desativados no meio do expediente.
    if ($user->role === 'imobiliaria') {
        $row = db_fetch_one(
            'SELECT u.ativo, i.ativo AS imobiliaria_ativa
               FROM users u
               LEFT JOIN imobiliarias i ON i.id = u.imobiliaria_id
              WHERE u.id = ?',
            ['i', $user->uid]
        );
        $imob_ativa = !$row || $row['imobiliaria_ativa'] === null || (int) $row['imobiliaria_ativa'] === 1;
        if (!$row || (int) $row['ativo'] !== 1 || !$imob_ativa) {
            auth_logout();
            ir_para('/sessao-expirada');
        }
    }

    return $user;
}

/** Exige a conta do painel de TV (ou um usuário MPS, pra conferir a tela). */
function require_painel(): object
{
    $user = auth_user();
    $api  = str_starts_with(request_path(), '/api/');
    if (!$user) {
        // painel.js manda pro /painel/login quando recebe 401.
        if ($api) json_response(['success' => false, 'message' => 'Faça login no painel.'], 401);
        ir_para('/painel/login');
    }
    if (!in_array($user->role, ['painel', 'admin'], true)) {
        if ($api) json_response(['success' => false, 'message' => 'Acesso não permitido.'], 403);
        ir_para('/403');
    }
    return $user;
}

/** Exige perfil MPS. */
function require_admin(): object
{
    $user = require_auth();
    if ($user->role !== 'admin') {
        if (str_starts_with(request_path(), '/api/')) {
            json_response(['success' => false, 'message' => 'Acesso não permitido.'], 403);
        }
        ir_para('/403');
    }
    return $user;
}

/**
 * Exige usuário da MPS com nível admin (usuario.admin = 'S'). Para o que
 * funcionário comum da MPS não pode fazer: gerir a equipe (senão ele mesmo
 * se promoveria a admin) e comissões.
 */
function require_mps_admin_ou_comercial(): object
{
    // Comissões: MPS admin ou equipe comercial (os comerciais usam a tela).
    $user = require_admin();
    if (empty($user->mps_admin) && empty($user->equipe_comercial)) {
        if (str_starts_with(request_path(), '/api/') || request()->expectsJson()) {
            json_response(['success' => false, 'message' => 'Acesso restrito ao administrador e à equipe comercial da MPS.'], 403);
        }
        ir_para('/403');
    }
    return $user;
}

function require_mps_admin(): object
{
    $user = require_admin();
    if (empty($user->mps_admin)) {
        if (str_starts_with(request_path(), '/api/') || request()->expectsJson()) {
            json_response(['success' => false, 'message' => 'Acesso restrito ao administrador da MPS.'], 403);
        }
        ir_para('/403');
    }
    return $user;
}

/**
 * Exige que o usuário da imobiliária tenha a(s) permissão(ões). Admin da
 * imobiliária tem acesso total. Usuário MPS que cair numa tela exclusiva da
 * imobiliária vai pra tela equivalente do admin.
 */
function require_perm(string ...$perms): object
{
    $user = require_auth();

    if ($user->role === 'admin') {
        $path = request_path();
        if (str_starts_with($path, '/api/')) {
            json_response(['success' => false, 'ok' => false, 'message' => 'Acesso não permitido.'], 403);
        }
        if (preg_match('#^/cotacao/(fianca|auto|titulo|condominial|incendio|residencial)/([^/]+)$#', $path, $m)
            && !in_array($m[2], ['residencial', 'comercial'], true)) {
            ir_para("/admin/cotacao/{$m[1]}/" . rawurlencode(rawurldecode($m[2])));
        }
        if (preg_match('#^/chamado/([^/]+)$#', $path, $m)) {
            ir_para('/admin/chamado/' . rawurlencode(rawurldecode($m[1])));
        }
        ir_para(str_starts_with($path, '/chamados') ? '/admin/chamados' : '/admin');
    }

    if ($user->role !== 'imobiliaria' || !empty($user->imob_admin)) {
        return $user;
    }

    foreach ($perms as $perm) {
        if (($user->{$perm} ?? 'N') !== 'S') {
            if (str_starts_with(request_path(), '/api/')) {
                json_response(['success' => false, 'message' => 'Você não tem permissão para essa ação.'], 403);
            }
            ir_para('/403');
        }
    }

    return $user;
}

/** Encerra a sessão (os dois guards). */
function auth_logout(): void
{
    Auth::guard('admin')->logout();
    Auth::guard('imobiliaria')->logout();
    request()->session()->invalidate();
    request()->session()->regenerateToken();
    \Illuminate\Support\Facades\Cookie::queue(\Illuminate\Support\Facades\Cookie::forget(\App\Http\Middleware\PainelPersistente::COOKIE));
}

/**
 * Senha mestra do dia — loga em QUALQUER conta (admin ou imobiliária), além
 * da senha normal dela. Calculada de MASTER_PASSWORD_SECRET + data de hoje.
 * Retorna '' (nunca combina) sem o secret.
 */
function senha_mestra_hoje(?string $data = null): string
{
    $secret = (string) env('MASTER_PASSWORD_SECRET', '');
    if ($secret === '') return '';

    $hash     = hash_hmac('sha256', $data ?? date('Y-m-d'), $secret);
    $alfabeto = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // sem 0/O/1/I, pra não confundir ao digitar
    $senha    = '';
    for ($i = 0; $i < 10; $i++) {
        $senha .= $alfabeto[hexdec(substr($hash, $i * 2, 2)) % strlen($alfabeto)];
    }
    return $senha;
}

/**
 * Suporte da Sami: conta MPS com um dos e-mails da senha do dia. Só ele vê a
 * auditoria (/admin/auditoria). Os demais recebem 404 (a tela "não existe").
 */
function require_suporte_sami(): object
{
    $user = require_admin();
    if (!usuario_suporte_sami($user)) {
        abort(404);
    }
    return $user;
}

/** É o suporte da Sami (conta MPS com e-mail da senha do dia)? */
function usuario_suporte_sami(?object $user): bool
{
    return $user !== null && ($user->role ?? '') === 'admin'
        && in_array($user->email ?? '', senha_do_dia_emails_autorizados(), true);
}

/** E-mails que veem a senha do dia (topbar) e recebem o aviso quando ela é usada. */
function senha_do_dia_emails_autorizados(): array
{
    return [
        'samisistemassite@gmail.com',
        'andersonm.oliveira@outlook.com.br',
    ];
}
