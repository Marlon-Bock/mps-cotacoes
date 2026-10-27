<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\Usuario;
use App\Http\Controllers\Auth\DoisFatoresController;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Login, logout, recuperação de senha e sessão.
 * Era: pages/login.php, esqueceu-senha.php, recuperar-senha.php,
 * sessao-expirada.php, logout.php e includes/acao/{login,esqueceu-senha,
 * recuperar-senha,renovar-sessao}.php.
 */
class AuthController extends Controller
{
    private function destinoPorPerfil(?object $user): string
    {
        return match ($user->role ?? '') {
            'painel' => '/painel',
            'admin'  => '/admin',
            default  => '/',
        };
    }

    /** GET /login */
    public function loginForm(Request $request)
    {
        if ($user = auth_user()) {
            return redirect(url($this->destinoPorPerfil($user)));
        }
        return view('auth.login', [
            'redefinida'         => $request->has('redefinida'),
            'turnstile_site_key' => (string) env('TURNSTILE_SITE_KEY', ''),
        ]);
    }

    /**
     * POST /api/auth/login — login único sobre as duas tabelas:
     *   users   → usuários da IMOBILIÁRIA (admin S/N = administra a própria agência)
     *   usuario → equipe MPS (admin S/N = nível elevado; painel = conta da TV)
     */
    public function login(Request $request)
    {
        $email   = trim((string) $request->input('email', ''));
        $senha   = (string) $request->input('senha', '');
        $escolha = $request->input('escolha'); // 'admin' | 'imobiliaria' — só quando o e-mail existe nas duas tabelas
        $ip      = $request->ip() ?? '0.0.0.0';

        // Grava o motivo exato da falha em login_tentativas.
        $registrar_falha = function (string $motivo, string $tipo = 'user') use ($email, $ip): void {
            if ($email === '') return;
            db_execute(
                'INSERT INTO login_tentativas (email, tipo, ip, falhou, motivo, criado_em) VALUES (?, ?, ?, 1, ?, NOW())',
                ['ssss', $email, $tipo, $ip, $motivo]
            );
        };

        // CSRF conferido aqui (rota fora do middleware) só pra registrar o motivo
        // "token_csrf_expirado" — aba de login aberta por muito tempo.
        $csrf_recebido = (string) ($request->input('_csrf') ?? $request->header('X-CSRF-Token', ''));
        if (!hash_equals(csrf_token(), $csrf_recebido)) {
            $registrar_falha('token_csrf_expirado');
            return response()->json([
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
            return response()->json(['message' => 'Verifique os dados informados.', 'errors' => $errors], 422);
        }

        // Bloqueio por IP: 20 falhas em 10 minutos, qualquer e-mail — o bloqueio por
        // e-mail (mais abaixo) sozinho deixa testar uma senha em muitas contas.
        $falhas_ip = db_fetch_one(
            "SELECT COUNT(*) AS total FROM login_tentativas
              WHERE ip = ? AND falhou = 1 AND criado_em > (NOW() - INTERVAL 10 MINUTE)",
            ['s', $ip]
        );
        if (($falhas_ip['total'] ?? 0) >= 20) {
            return response()->json(['message' => 'Muitas tentativas. Aguarde alguns minutos e tente novamente.'], 429);
        }

        // Turnstile (Cloudflare) em todo login — o token vale uma vez; a escolha
        // de perfil (conta MPS + imobiliária) reenvia sem token novo, liberada
        // por 2 minutos pra esse mesmo e-mail (login_escolha_ok).
        if (env('TURNSTILE_SITE_KEY', '') !== '') {
            $escolhaOk = session('login_escolha_ok');
            $semToken  = in_array($escolha, ['admin', 'imobiliaria'], true)
                && is_array($escolhaOk) && ($escolhaOk['email'] ?? '') === $email && ($escolhaOk['ate'] ?? 0) >= time();
            if (!$semToken && !turnstile_verificar((string) $request->input('cf-turnstile-response', ''), $ip)) {
                return response()->json(['message' => 'Confirme que você não é um robô e tente de novo.', 'turnstile' => true], 422);
            }
        }

        // Pode existir mais de uma conta de imobiliária no mesmo e-mail (cada
        // uma com sua senha) — a senha escolhe qual.
        $u_imob_candidatos = db_fetch_all(
            'SELECT u.id, u.password, u.admin, u.imobiliaria_id, u.ativo, i.ativo AS imobiliaria_ativa
               FROM users u
               LEFT JOIN imobiliarias i ON i.id = u.imobiliaria_id
              WHERE u.email = ?',
            ['s', $email]
        );
        $u_imob = null;
        foreach ($u_imob_candidatos as $cand) {
            if (password_verify($senha, $cand['password'])) { $u_imob = $cand; break; }
        }
        // Nenhuma bateu — a primeira mantém as mensagens (conta inativa) e a senha mestra.
        if (!$u_imob && $u_imob_candidatos) {
            $u_imob = $u_imob_candidatos[0];
        }
        $u_admin = db_fetch_one('SELECT * FROM usuario WHERE email = ? LIMIT 1', ['s', $email]);

        // Bloqueio: 5 falhas em 10 minutos, por e-mail.
        $bloqueado = function () use ($email): bool {
            $t = db_fetch_one(
                "SELECT COUNT(*) AS total FROM login_tentativas
                 WHERE email = ? AND falhou = 1 AND criado_em > (NOW() - INTERVAL 10 MINUTE)",
                ['s', $email]
            );
            return ($t['total'] ?? 0) >= 5;
        };
        if (($u_imob || $u_admin) && $bloqueado()) {
            return response()->json(['message' => 'Muitas tentativas. Aguarde alguns minutos e tente novamente.'], 429);
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

            // Senha certa mas conta/imobiliária desativada — mensagem específica.
            if ($senha_ok_imob && !$match_imob) {
                $motivo_msg = !$imob_ativa ? 'A imobiliária está inativa.' : 'Este usuário está desativado.';
                return response()->json(['message' => "{$motivo_msg} Entre em contato com o administrador."], 403);
            }
            if ($senha_ok_admin && !$match_admin) {
                return response()->json(['message' => 'Este usuário está desativado. Entre em contato com o administrador.'], 403);
            }
            return response()->json(['message' => 'E-mail ou senha incorretos.'], 401);
        }

        // Conta nos dois perfis com a mesma senha → exige escolha explícita.
        if ($match_imob && $match_admin && !in_array($escolha, ['admin', 'imobiliaria'], true)) {
            session(['login_escolha_ok' => ['email' => $email, 'ate' => time() + 120]]);
            return response()->json([
                'choice_required' => true,
                'message' => 'Esta conta tem acesso à área administrativa e à imobiliária. Escolha por onde deseja entrar.',
            ]);
        }

        if ($match_admin && ($escolha === 'admin' || !$match_imob)) {
            $usuario = $u_admin; $tabela = 'usuario';
        } else {
            $usuario = $u_imob; $tabela = 'users';
        }

        session()->forget('login_escolha_ok');

        // Dois fatores em todo login (Auth\DoisFatoresController). Com a senha do
        // dia, o código é do Authenticator do suporte da Sami. Fora: a conta do
        // painel de TV (não tem como digitar código).
        $painel = $tabela === 'usuario' && ($usuario['painel'] ?? 'N') === 'S';
        if ($usou_senha_mestra || !$painel) {
            return DoisFatoresController::iniciar($request, $tabela, (int) $usuario['id'], $usou_senha_mestra);
        }

        return $this->concluirLogin($request, $tabela, (int) $usuario['id'], $usou_senha_mestra);
    }

    /**
     * Entra na conta (depois da senha e, quando exigido, dos dois fatores):
     * registra o acesso, avisa o uso da senha do dia, faz o login no guard do
     * perfil e devolve pra onde ir.
     */
    public function concluirLogin(Request $request, string $tabela, int $usuarioId, bool $usou_senha_mestra, ?string $suporte = null)
    {
        $ip      = $request->ip() ?? '0.0.0.0';
        $usuario = ['id' => $usuarioId];

        // Registra o acesso (as tentativas falhas ficam como auditoria).
        db_execute("UPDATE {$tabela} SET last_login_at = NOW() WHERE id = ?", ['i', $usuario['id']]);
        db_execute(
            'INSERT INTO login_historico (tabela_origem, usuario_id, ip, user_agent, criado_em) VALUES (?, ?, ?, ?, NOW())',
            ['siss', $tabela, $usuario['id'], $ip, mb_substr((string) $request->userAgent(), 0, 255)]
        );

        // Auditoria: todo uso da senha mestra fica visível pra quem administra.
        if ($usou_senha_mestra) {
            $nomeConta = (string) db_fetch_one("SELECT username FROM {$tabela} WHERE id = ?", ['i', $usuario['id']])['username'];
            $por = $suporte !== null ? " por {$suporte}" : '';
            error_log("[senha-mestra] Login via senha mestra{$por} — conta: {$nomeConta} ({$tabela}#{$usuario['id']}), IP: {$ip}");
            try {
                $emails = senha_do_dia_emails_autorizados();
                $supervisores = db_fetch_all(
                    'SELECT id FROM usuario WHERE email IN (' . implode(',', array_fill(0, count($emails), '?')) . ')',
                    [str_repeat('s', count($emails)), ...$emails]
                );
                foreach ($supervisores as $sup) {
                    notificar_mps('admin', (int) $sup['id'], 'acao_imob', 'Login com a senha do dia',
                        "A conta \"{$nomeConta}\" foi acessada usando a senha do dia{$por} (IP: {$ip}).");
                }
            } catch (\Throwable) {
                // aviso extra — não pode travar o login
            }
        }

        // Login pelo guard do perfil (sai do outro, se estivesse logado).
        $request->session()->regenerate();
        // Auditoria: tudo o que for feito nesta sessão fica marcado "via senha do dia".
        session(['auditoria_senha_mestra' => $usou_senha_mestra, 'auditoria_senha_mestra_por' => $usou_senha_mestra ? $suporte : null]);
        if ($tabela === 'usuario') {
            Auth::guard('imobiliaria')->logout();
            Auth::guard('admin')->login(Usuario::findOrFail($usuario['id']));
        } else {
            Auth::guard('admin')->logout();
            Auth::guard('imobiliaria')->login(User::findOrFail($usuario['id']));
        }

        $user = auth_user();
        if ($user->role === 'painel') {
            \App\Http\Middleware\PainelPersistente::lembrar((int) $usuario['id']);
        }
        // Popup único no próximo carregamento do dashboard (cotações em análise /
        // pendências da imobiliária) — marcado por login, não repete.
        if ($user->role === 'admin') {
            session(['popup_cotacoes_em_analise' => true]);
        }
        if ($user->role === 'imobiliaria') {
            session(['popup_pendencias_imob' => true]);
        }

        return response()->json([
            'message'  => 'Login realizado com sucesso.',
            'redirect' => url($this->destinoPorPerfil($user)),
        ]);
    }

    /** POST /logout */
    public function logout()
    {
        auth_logout();
        return redirect(url('/login'));
    }

    /** GET /sessao-expirada */
    public function sessaoExpirada()
    {
        return view('auth.sessao-expirada');
    }

    /** POST /api/renovar-sessao — estende a sessão; devolve o novo fim pro watchdog JS. */
    public function renovarSessao(Request $request)
    {
        if (!auth_user()) {
            return response()->json(['success' => false, 'message' => 'Sessão inválida ou expirada.'], 401);
        }
        $request->session()->migrate(); // renova id e o prazo da sessão
        return response()->json(['success' => true, 'exp' => time() + (int) config('session.lifetime') * 60]);
    }

    /** GET /esqueceu-senha */
    public function esqueceuForm()
    {
        return view('auth.esqueceu-senha');
    }

    /**
     * POST /api/auth/esqueceu-senha — procura o e-mail em users (imobiliária)
     * e depois usuario (MPS) pra decidir a tabela de reset:
     * users → reset_senha_users, usuario → reset_senha_usuario.
     */
    public function esqueceu(Request $request)
    {
        $email = trim((string) $request->input('email', ''));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return response()->json(['message' => 'Informe um e-mail válido.', 'errors' => ['email' => 'E-mail inválido.']], 422);
        }

        // Resposta SEMPRE igual — exista ou não conta no e-mail — pra não revelar
        // quem é cliente nem listar nomes de usuário (antes: "e-mail não
        // encontrado" e escolha de conta na tela).
        $resposta = response()->json([
            'message' => 'Se o e-mail estiver cadastrado, você receberá um link de recuperação em instantes. Verifique também o spam.',
        ]);

        // Todas as contas do e-mail: imobiliária (pode haver várias, na mesma
        // imobiliária) e equipe MPS. Um e-mail só, com um link por conta.
        $contas = [];
        foreach (db_fetch_all('SELECT id, username FROM users WHERE email = ? ORDER BY username, id', ['s', $email]) as $c) {
            $contas[] = $c + ['tabela' => 'reset_senha_users'];
        }
        foreach (db_fetch_all('SELECT id, username FROM usuario WHERE email = ? ORDER BY id', ['s', $email]) as $c) {
            $contas[] = $c + ['tabela' => 'reset_senha_usuario'];
        }

        $links = [];
        foreach ($contas as $conta) {
            $reset_table = $conta['tabela'];

            // Um pedido por hora por conta (sem avisar na tela — ver $resposta).
            $recente = db_fetch_one(
                "SELECT id FROM {$reset_table} WHERE usuario_id = ? AND created_at > (NOW() - INTERVAL 60 MINUTE) LIMIT 1",
                ['i', $conta['id']]
            );
            if ($recente) {
                continue;
            }

            // Invalida tokens anteriores e gera um novo.
            db_execute("UPDATE {$reset_table} SET used_at = NOW() WHERE usuario_id = ? AND used_at IS NULL", ['i', $conta['id']]);

            $token     = bin2hex(random_bytes(32));
            $tokenHash = hash('sha256', $token);
            db_execute(
                "INSERT INTO {$reset_table} (usuario_id, token_hash, expires_at, created_at, ip, user_agent)
                 VALUES (?, ?, NOW() + INTERVAL 15 MINUTE, NOW(), ?, ?)",
                ['isss', $conta['id'], $tokenHash, $request->ip() ?? '0.0.0.0', substr((string) $request->userAgent(), 0, 255)]
            );
            $links[] = ['nome' => (string) ($conta['username'] ?? ''), 'url' => url('/recuperar-senha/' . $token),
                        'tabela' => $reset_table, 'hash' => $tokenHash];
        }

        if (!$links) {
            return $resposta;
        }

        $corpo = '<p style="margin:0 0 14px;">Olá!</p>'
            . '<p style="margin:0 0 14px;">Recebemos um pedido para redefinir a senha no MPS Cotações. '
            . 'O link abaixo expira em <strong>15 minutos</strong>.</p>';
        if (count($links) === 1) {
            $corpo .= '<p style="margin:22px 0;">' . email_botao('Criar uma nova senha', $links[0]['url']) . '</p>';
        } else {
            $corpo .= '<p style="margin:0 0 14px;">Este e-mail tem mais de uma conta — use o link da conta que deseja recuperar:</p>';
            foreach ($links as $l) {
                $corpo .= '<p style="margin:14px 0;"><strong>' . e($l['nome'] !== '' ? $l['nome'] : 'Conta') . '</strong><br>'
                    . email_botao('Criar uma nova senha', $l['url']) . '</p>';
            }
        }
        $corpo .= '<p style="margin:0;color:#757575;font-size:13px;">Se você não pediu a troca de senha, ignore este e-mail — sua senha continua a mesma.</p>';

        if (!send_email($email, 'Recuperação de senha — MPS Cotações', $corpo)) {
            // Sem o link no log: o arquivo de log não é um lugar seguro pra ele.
            error_log('[ResetSenha] Falha ao enviar o e-mail de recuperação (' . count($links) . ' conta(s)).');
            // Apaga os tokens (o throttle conta qualquer linha da última hora).
            foreach ($links as $l) {
                db_execute("DELETE FROM {$l['tabela']} WHERE token_hash = ?", ['s', $l['hash']]);
            }
        }

        return $resposta;
    }

    /** GET /recuperar-senha/{token} — só mostra o formulário; o token é consumido no POST. */
    public function recuperarForm(string $token)
    {
        $token_hash = hash('sha256', $token);

        // Token nas duas tabelas de reset (imobiliária e MPS).
        $reset_table = 'reset_senha_users';
        $reset = db_fetch_one(
            'SELECT id, used_at, (expires_at > NOW()) AS dentro_do_prazo FROM reset_senha_users WHERE token_hash = ? LIMIT 1',
            ['s', $token_hash]
        );
        if (!$reset) {
            $reset_table = 'reset_senha_usuario';
            $reset = db_fetch_one(
                'SELECT id, used_at, (expires_at > NOW()) AS dentro_do_prazo FROM reset_senha_usuario WHERE token_hash = ? LIMIT 1',
                ['s', $token_hash]
            );
        }

        // Prazo comparado no MySQL (mesmo relógio que gravou expires_at).
        $token_valido = $reset && !$reset['used_at'] && (int) $reset['dentro_do_prazo'] === 1;

        // Marca a abertura SEM consumir o link (só o POST bem-sucedido gasta o token).
        if ($token_valido) {
            db_execute("UPDATE {$reset_table} SET opened_at = NOW() WHERE id = ? AND opened_at IS NULL", ['i', $reset['id']]);
        }

        return view('auth.recuperar-senha', ['token' => $token, 'token_valido' => $token_valido]);
    }

    /** POST /api/auth/recuperar-senha — valida o token e grava a nova senha. */
    public function recuperar(Request $request)
    {
        $token             = (string) $request->input('token', '');
        $senha             = (string) $request->input('senha', '');
        $senha_confirmacao = (string) $request->input('senha_confirmacao', '');

        $errors = [];
        if (strlen($senha) < 6) {
            $errors['senha'] = 'A senha deve ter no mínimo 6 caracteres.';
        }
        if ($senha !== $senha_confirmacao) {
            $errors['senha_confirmacao'] = 'As senhas não coincidem.';
        }
        if ($errors) {
            return response()->json(['message' => 'Verifique os dados informados.', 'errors' => $errors], 422);
        }

        $token_hash = hash('sha256', $token);
        try {
            DB::transaction(function () use ($token_hash, $senha) {
                // A tabela onde o token for achado define onde a senha muda.
                $reset_table = 'reset_senha_users';
                $users_table = 'users';
                $reset = db_fetch_one(
                    'SELECT id, usuario_id, used_at, (expires_at > NOW()) AS dentro_do_prazo FROM reset_senha_users WHERE token_hash = ? LIMIT 1 FOR UPDATE',
                    ['s', $token_hash]
                );
                if (!$reset) {
                    $reset_table = 'reset_senha_usuario';
                    $users_table = 'usuario';
                    $reset = db_fetch_one(
                        'SELECT id, usuario_id, used_at, (expires_at > NOW()) AS dentro_do_prazo FROM reset_senha_usuario WHERE token_hash = ? LIMIT 1 FOR UPDATE',
                        ['s', $token_hash]
                    );
                }
                // Prazo checado no MySQL (mesmo relógio que gravou expires_at).
                if (!$reset || $reset['used_at'] || (int) $reset['dentro_do_prazo'] !== 1) {
                    json_response(['message' => 'Este link expirou ou já foi utilizado. Solicite um novo.'], 410);
                }
                db_execute("UPDATE {$users_table} SET password = ? WHERE id = ?", ['si', password_hash($senha, PASSWORD_DEFAULT), $reset['usuario_id']]);
                db_execute("UPDATE {$reset_table} SET used_at = NOW() WHERE id = ?", ['i', $reset['id']]);
            });
        } catch (HttpResponseException $e) {
            throw $e;
        } catch (\Throwable $e) {
            error_log('[RecuperarSenha] ' . $e->getMessage());
            return response()->json(['message' => 'Não foi possível redefinir a senha. Tente novamente.'], 500);
        }

        return response()->json(['message' => 'Senha redefinida com sucesso.']);
    }
}
