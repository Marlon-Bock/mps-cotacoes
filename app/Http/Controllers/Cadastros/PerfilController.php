<?php

namespace App\Http\Controllers\Cadastros;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

/**
 * Meu Perfil — comum à imobiliária (/perfil) e à MPS (/admin/perfil).
 * Era pages/perfil.php e includes/acao/perfil-*.php.
 */
class PerfilController extends Controller
{
    /** GET+POST /perfil e /admin/perfil */
    public function index()
    {
        /**
         * GET /perfil
         * Tela de perfil — comum a imobiliária e admin (MPS). A tabela usada
         * depende do `role` do JWT: 'imobiliaria' -> users, 'admin' -> usuario.
         */
        $current_user = require_auth();
        $tabela = $current_user->role === 'admin' ? 'usuario' : 'users';

        // email_cotacao só existe na tabela `usuario` (equipe MPS) — não em `users` (imobiliária).
        $campos = 'id, username, email, telefone, avatar, last_login_at' . ($tabela === 'usuario' ? ', email_cotacao' : '');
        $usuario = db_fetch_one("SELECT {$campos} FROM {$tabela} WHERE id = ?", ['i', $current_user->uid]);
        if (!$usuario) {
            ir_para('/sessao-expirada');
        }

        // ── Últimos acessos ────────────────────────────────────────────────────────
        $ip_atual = $_SERVER['REMOTE_ADDR'] ?? '—';
        $ua_atual = $_SERVER['HTTP_USER_AGENT'] ?? '';

        $historico_acessos = db_fetch_all(
            'SELECT ip, user_agent, criado_em FROM login_historico
             WHERE tabela_origem = ? AND usuario_id = ? ORDER BY criado_em DESC LIMIT 6',
            ['si', $tabela, $current_user->uid]
        );
        foreach ($historico_acessos as &$acesso) {
            $device = ua_parse($acesso['user_agent']);
            $acesso['navegador']   = $device['navegador'];
            $acesso['dispositivo'] = $device['dispositivo'];
            $acesso['tempo']       = tempo_decorrido($acesso['criado_em']);
            $acesso['e_atual']     = $acesso['ip'] === $ip_atual && $acesso['user_agent'] === $ua_atual;
        }
        unset($acesso);

        $sessao_atual = ua_parse($ua_atual);

        $page_title      = 'Meu Perfil';
        $page_heading    = 'Meu Perfil';
        $page_subheading = 'Gerencie seus dados pessoais e sua senha de acesso';
        $page_back       = null;
        $extra_css       = ['cotacao.css', 'perfil.css'];
        $extra_js        = ['perfil.js'];

        return view('cadastros.perfil', get_defined_vars());
    }

    /**
     * POST /api/perfil
     * Atualiza dados pessoais do usuário logado (imobiliária ou admin),
     * com troca de senha opcional. Nunca aceita id/tabela do cliente.
     */
    public function atualizar(Request $request)
    {
        $current_user = require_auth();
        $tabela = $current_user->role === 'admin' ? 'usuario' : 'users';

        $username    = trim((string) $request->input('username', ''));
        $email       = trim((string) $request->input('email', ''));
        $telefone    = trim((string) $request->input('telefone', ''));
        $senha_atual = (string) $request->input('senha_atual', '');
        $nova_senha  = (string) $request->input('nova_senha', '');

        // email_cotacao só existe (e só é editável) na tabela `usuario` (equipe MPS).
        $email_cotacao = $tabela === 'usuario' ? trim((string) $request->input('email_cotacao', '')) : null;

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

        // Só confere quando o e-mail MUDOU. Imobiliária: o mesmo e-mail pode ter
        // vários usuários na própria imobiliária (migration 081), não em outra.
        // Equipe MPS: e-mail único na tabela.
        $email_atual = db_fetch_one("SELECT email FROM {$tabela} WHERE id = ?", ['i', $current_user->uid])['email'] ?? '';
        if (strcasecmp($email, (string) $email_atual) !== 0) {
            $duplicado = $tabela === 'users'
                ? email_usado_em_outra_imobiliaria($email, (int) $current_user->imobiliaria_id, (int) $current_user->uid)
                : (bool) db_fetch_one("SELECT id FROM {$tabela} WHERE email = ? AND id != ?", ['si', $email, $current_user->uid]);
            if ($duplicado) {
                $msg = $tabela === 'users' ? 'Este e-mail já é usado por outra imobiliária.' : 'Este e-mail já está em uso.';
                json_response(['message' => 'Verifique os dados informados.', 'errors' => ['email' => $msg]], 422);
            }
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

        // No antigo, aqui reemitia o JWT com o novo nome/e-mail (auth_emit). Agora o
        // usuário logado é lido do banco a cada requisição — não há o que reemitir.

        json_response(['message' => 'Perfil atualizado com sucesso.']);
    }

    /**
     * POST /api/perfil/avatar
     * Upload do avatar do usuário logado via media server (helper media_upload()).
     */
    public function avatar()
    {
        if ($m = upload_erro_limite()) json_response(['message' => $m], 413);

        $current_user = require_auth();
        $tabela = $current_user->role === 'admin' ? 'usuario' : 'users';

        if (empty($_FILES['avatar'])) {
            json_response(['message' => 'Nenhum arquivo enviado.'], 422);
        }

        $atual = db_fetch_one("SELECT avatar FROM {$tabela} WHERE id = ?", ['i', $current_user->uid]);

        try {
            $url = media_upload($_FILES['avatar'], 'avatars');
        } catch (RuntimeException $e) {
            json_response(['message' => $e->getMessage()], 422);
        }

        db_execute("UPDATE {$tabela} SET avatar = ? WHERE id = ?", ['si', $url, $current_user->uid]);

        if (!empty($atual['avatar'])) {
            try { media_delete($atual['avatar']); } catch (Throwable $e) {}
        }

        json_response(['message' => 'Avatar atualizado com sucesso.', 'avatar' => $url]);
    }
}
