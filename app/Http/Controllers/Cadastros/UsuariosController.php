<?php

namespace App\Http\Controllers\Cadastros;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

/**
 * Usuários da imobiliária logada (tabela `users`) — só o admin da imobiliária.
 * Era pages/usuarios*.php e includes/acao/usuarios-*.php.
 */
class UsuariosController extends Controller
{
    /** GET /usuarios */
    public function index()
    {
        /**
         * GET /usuarios
         * Lista os usuários da imobiliária logada (apenas para administradores da imobiliária).
         */
        $current_user = require_auth();

        if (!($current_user->imob_admin ?? false)) {
            ir_para('/403');
        }

        $flash_messages = flash_get();

        $page_title      = 'Gerenciar Usuários';
        $page_heading    = 'Gerenciar Usuários';
        $page_subheading = 'Usuários com acesso à sua imobiliária';
        $page_back       = null;
        $extra_css       = ['cotacoes.css'];
        $extra_js        = ['usuarios.js'];

        $main_attrs = ' data-flash="' . e(json_encode($flash_messages)) . '"';

        return view('cadastros.usuarios.index', get_defined_vars());
    }

    /** GET+POST /usuarios/criar */
    public function criar()
    {
        /**
         * GET+POST /usuarios/criar
         * Formulário para criar um novo usuário vinculado à imobiliária logada.
         */
        $current_user = require_auth();

        if (!($current_user->imob_admin ?? false)) {
            ir_para('/403');
        }

        $erros   = [];
        $valores = [
            'username' => '',
            'email'    => '',
            'telefone' => '',
            'admin'    => false,
            'perm_criar_usuarios' => false,
            'perm_cotar'           => false,
            'perm_suporte'         => false,
            'perm_aceitar'         => false,
            'perm_fianca'          => false,
            'perm_auto'            => false,
            'perm_condo'           => false,
            'perm_titulo'          => false,
            'perm_incendio'        => false,
            'perm_seguro_residencial'        => false,
        ];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();

            $valores['username'] = trim($_POST['username'] ?? '');
            $valores['email']    = trim($_POST['email'] ?? '');
            $valores['telefone'] = trim($_POST['telefone'] ?? '');
            $senha               = (string) ($_POST['senha'] ?? '');
            $senha_confirmar      = (string) ($_POST['senha_confirmar'] ?? '');

            $valores['admin']               = ($_POST['admin'] ?? '') === 'S';
            // Admin da imobiliária tem acesso total — força todas as permissões.
            $valores['perm_criar_usuarios'] = $valores['admin'] || ($_POST['perm_criar_usuarios'] ?? '') === 'S';
            $valores['perm_cotar']          = $valores['admin'] || ($_POST['perm_cotar'] ?? '') === 'S';
            $valores['perm_suporte']        = $valores['admin'] || ($_POST['perm_suporte'] ?? '') === 'S';
            $valores['perm_aceitar']        = $valores['admin'] || ($_POST['perm_aceitar'] ?? '') === 'S';
            // Acesso a cada produto só faz sentido se a pessoa pode cotar.
            $valores['perm_fianca']         = $valores['perm_cotar'] && ($valores['admin'] || ($_POST['perm_fianca'] ?? '') === 'S');
            $valores['perm_auto']           = $valores['perm_cotar'] && ($valores['admin'] || ($_POST['perm_auto'] ?? '') === 'S');
            $valores['perm_condo']          = $valores['perm_cotar'] && ($valores['admin'] || ($_POST['perm_condo'] ?? '') === 'S');
            $valores['perm_titulo']         = $valores['perm_cotar'] && ($valores['admin'] || ($_POST['perm_titulo'] ?? '') === 'S');
            $valores['perm_incendio']       = $valores['perm_cotar'] && ($valores['admin'] || ($_POST['perm_incendio'] ?? '') === 'S');
            $valores['perm_seguro_residencial']       = $valores['perm_cotar'] && ($valores['admin'] || ($_POST['perm_seguro_residencial'] ?? '') === 'S');

            if ($valores['username'] === '') $erros['username'] = 'Nome é obrigatório.';
            if ($valores['email'] === '' || !filter_var($valores['email'], FILTER_VALIDATE_EMAIL))
                $erros['email'] = 'Informe um e-mail válido.';
            if ($senha === '' || !senha_valida($senha)) $erros['senha'] = 'A senha deve ter no mínimo 6 caracteres, com 1 maiúscula e 1 número.';
            if ($senha !== $senha_confirmar) $erros['senha_confirmar'] = 'As senhas não coincidem.';

            if (empty($erros)) {
                // Mesmo e-mail pode repetir na própria imobiliária, não em outra.
                if (email_usado_em_outra_imobiliaria($valores['email'], (int) $current_user->imobiliaria_id)) {
                    $erros['email'] = 'Este e-mail já é usado por outra imobiliária.';
                }
            }

            if (empty($erros)) {
                $slug = gerar_slug_usuario($valores['username'], $current_user->imobiliaria_id);

                $novo_id_res = (int) db_execute(
                    "INSERT INTO users
                        (username, slug, email, telefone, password, imobiliaria_id, admin, criado_por,
                         perm_criar_usuarios, perm_cotar, perm_suporte, perm_aceitar,
                         perm_fianca, perm_auto, perm_condo, perm_titulo, perm_incendio, novidade_incendio_visto_em, created_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())",
                    ['sssssisisssssssss',
                        $valores['username'],
                        $slug,
                        $valores['email'],
                        $valores['telefone'] ?: null,
                        password_hash($senha, PASSWORD_DEFAULT),
                        $current_user->imobiliaria_id,
                        $valores['admin'] ? 'S' : 'N',
                        $current_user->uid,
                        $valores['perm_criar_usuarios'] ? 'S' : 'N',
                        $valores['perm_cotar'] ? 'S' : 'N',
                        $valores['perm_suporte'] ? 'S' : 'N',
                        $valores['perm_aceitar'] ? 'S' : 'N',
                        $valores['perm_fianca'] ? 'S' : 'N',
                        $valores['perm_auto'] ? 'S' : 'N',
                        $valores['perm_condo'] ? 'S' : 'N',
                        $valores['perm_titulo'] ? 'S' : 'N',
                        $valores['perm_incendio'] ? 'S' : 'N',
                    ]
                );

                // (id do INSERT acima — era db()->insert_id)

                // Seguro Residencial (migration 094) — fora da query acima, que tem a lista fixa de colunas.

                db_execute('UPDATE users SET perm_seguro_residencial = ? WHERE id = ?', ['si', $valores['perm_seguro_residencial'] ? 'S' : 'N', $novo_id_res]);

                flash('success', 'Usuário "' . $valores['username'] . '" criado com sucesso.');
                ir_para('/usuarios');
            }
        }

        $page_title      = 'Criar Usuário';
        $page_heading    = 'Criar Usuário';
        $page_subheading = 'Adicione um novo usuário com acesso à sua imobiliária';
        $page_back       = ['url' => '/usuarios', 'label' => 'Gerenciar Usuários'];
        $extra_css       = ['cotacao.css'];
        $extra_js        = ['masks.js', 'auth.js', 'senha-validar.js'];

        return view('cadastros.usuarios.criar', get_defined_vars());
    }

    /** GET+POST /usuarios/{slug}/editar */
    public function editar(string $slug)
    {
        $params = ['slug' => $slug];

        /**
         * GET+POST /usuarios/{slug}/editar
         * Edita um usuário vinculado à imobiliária logada.
         */
        $current_user = require_auth();

        if (!($current_user->imob_admin ?? false)) {
            ir_para('/403');
        }

        $slug_param = trim($params['slug'] ?? '');
        if ($slug_param === '') ir_para('/usuarios');

        $usuario = db_fetch_one(
            'SELECT * FROM users WHERE slug = ? AND imobiliaria_id = ?',
            ['si', $slug_param, $current_user->imobiliaria_id]
        );
        if (!$usuario) ir_para('/usuarios');

        $id = (int) $usuario['id'];

        $erros   = [];
        $valores = [
            'username'             => $usuario['username'],
            'email'                => $usuario['email'],
            'telefone'             => $usuario['telefone'] ?? '',
            'admin'                => $usuario['admin'] === 'S',
            'perm_criar_usuarios'  => $usuario['perm_criar_usuarios'] === 'S',
            'perm_cotar'           => $usuario['perm_cotar'] === 'S',
            'perm_suporte'         => $usuario['perm_suporte'] === 'S',
            'perm_aceitar'         => $usuario['perm_aceitar'] === 'S',
            'perm_fianca'          => $usuario['perm_fianca'] === 'S',
            'perm_auto'            => $usuario['perm_auto'] === 'S',
            'perm_condo'           => $usuario['perm_condo'] === 'S',
            'perm_titulo'          => $usuario['perm_titulo'] === 'S',
            'perm_incendio'        => ($usuario['perm_incendio'] ?? 'N') === 'S',
            'perm_seguro_residencial'        => ($usuario['perm_seguro_residencial'] ?? 'N') === 'S',
        ];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();

            $valores['username'] = trim($_POST['username'] ?? '');
            $valores['email']    = trim($_POST['email'] ?? '');
            $valores['telefone'] = trim($_POST['telefone'] ?? '');
            $senha               = (string) ($_POST['senha'] ?? '');
            $senha_confirmar     = (string) ($_POST['senha_confirmar'] ?? '');

            $valores['admin']               = ($_POST['admin'] ?? '') === 'S';
            // Admin da imobiliária tem acesso total — força todas as permissões.
            $valores['perm_criar_usuarios'] = $valores['admin'] || ($_POST['perm_criar_usuarios'] ?? '') === 'S';
            $valores['perm_cotar']          = $valores['admin'] || ($_POST['perm_cotar'] ?? '') === 'S';
            $valores['perm_suporte']        = $valores['admin'] || ($_POST['perm_suporte'] ?? '') === 'S';
            $valores['perm_aceitar']        = $valores['admin'] || ($_POST['perm_aceitar'] ?? '') === 'S';
            // Acesso a cada produto só faz sentido se a pessoa pode cotar.
            $valores['perm_fianca']         = $valores['perm_cotar'] && ($valores['admin'] || ($_POST['perm_fianca'] ?? '') === 'S');
            $valores['perm_auto']           = $valores['perm_cotar'] && ($valores['admin'] || ($_POST['perm_auto'] ?? '') === 'S');
            $valores['perm_condo']          = $valores['perm_cotar'] && ($valores['admin'] || ($_POST['perm_condo'] ?? '') === 'S');
            $valores['perm_titulo']         = $valores['perm_cotar'] && ($valores['admin'] || ($_POST['perm_titulo'] ?? '') === 'S');
            $valores['perm_incendio']       = $valores['perm_cotar'] && ($valores['admin'] || ($_POST['perm_incendio'] ?? '') === 'S');
            $valores['perm_seguro_residencial']       = $valores['perm_cotar'] && ($valores['admin'] || ($_POST['perm_seguro_residencial'] ?? '') === 'S');

            if ($valores['username'] === '') $erros['username'] = 'Nome é obrigatório.';
            if ($valores['email'] === '' || !filter_var($valores['email'], FILTER_VALIDATE_EMAIL))
                $erros['email'] = 'Informe um e-mail válido.';
            if ($senha !== '' && !senha_valida($senha)) $erros['senha'] = 'A senha deve ter no mínimo 6 caracteres, com 1 maiúscula e 1 número.';
            if ($senha !== '' && $senha !== $senha_confirmar) $erros['senha_confirmar'] = 'As senhas não coincidem.';

            if (empty($erros) && $valores['email'] !== $usuario['email']) {
                if (email_usado_em_outra_imobiliaria($valores['email'], (int) $current_user->imobiliaria_id, $id)) {
                    $erros['email'] = 'Este e-mail já é usado por outra imobiliária.';
                }
            }

            if (empty($erros)) {
                $novo_slug = $valores['username'] !== $usuario['username']
                    ? gerar_slug_usuario($valores['username'], $current_user->imobiliaria_id, $id)
                    : $usuario['slug'];

                if ($senha !== '') {
                    db_execute(
                        "UPDATE users SET username = ?, slug = ?, email = ?, telefone = ?, password = ?, admin = ?,
                            perm_criar_usuarios = ?, perm_cotar = ?, perm_suporte = ?, perm_aceitar = ?,
                            perm_fianca = ?, perm_auto = ?, perm_condo = ?, perm_titulo = ?, perm_incendio = ?
                         WHERE id = ?",
                        ['sssssssssssssssi',
                            $valores['username'], $novo_slug, $valores['email'], $valores['telefone'] ?: null,
                            password_hash($senha, PASSWORD_DEFAULT),
                            $valores['admin'] ? 'S' : 'N',
                            $valores['perm_criar_usuarios'] ? 'S' : 'N',
                            $valores['perm_cotar'] ? 'S' : 'N',
                            $valores['perm_suporte'] ? 'S' : 'N',
                            $valores['perm_aceitar'] ? 'S' : 'N',
                            $valores['perm_fianca'] ? 'S' : 'N',
                            $valores['perm_auto'] ? 'S' : 'N',
                            $valores['perm_condo'] ? 'S' : 'N',
                            $valores['perm_titulo'] ? 'S' : 'N',
                            $valores['perm_incendio'] ? 'S' : 'N',
                            $id,
                        ]
                    );
                } else {
                    db_execute(
                        "UPDATE users SET username = ?, slug = ?, email = ?, telefone = ?, admin = ?,
                            perm_criar_usuarios = ?, perm_cotar = ?, perm_suporte = ?, perm_aceitar = ?,
                            perm_fianca = ?, perm_auto = ?, perm_condo = ?, perm_titulo = ?, perm_incendio = ?
                         WHERE id = ?",
                        ['ssssssssssssssi',
                            $valores['username'], $novo_slug, $valores['email'], $valores['telefone'] ?: null,
                            $valores['admin'] ? 'S' : 'N',
                            $valores['perm_criar_usuarios'] ? 'S' : 'N',
                            $valores['perm_cotar'] ? 'S' : 'N',
                            $valores['perm_suporte'] ? 'S' : 'N',
                            $valores['perm_aceitar'] ? 'S' : 'N',
                            $valores['perm_fianca'] ? 'S' : 'N',
                            $valores['perm_auto'] ? 'S' : 'N',
                            $valores['perm_condo'] ? 'S' : 'N',
                            $valores['perm_titulo'] ? 'S' : 'N',
                            $valores['perm_incendio'] ? 'S' : 'N',
                            $id,
                        ]
                    );
                }

                // Seguro Residencial (migration 094) — fora da query acima, que tem a lista fixa de colunas.

                db_execute('UPDATE users SET perm_seguro_residencial = ? WHERE id = ?', ['si', $valores['perm_seguro_residencial'] ? 'S' : 'N', $id]);

                flash('success', 'Usuário "' . $valores['username'] . '" atualizado com sucesso.');
                ir_para('/usuarios');
            }
        }

        $page_title      = 'Editar Usuário';
        $page_heading    = 'Editar Usuário';
        $page_subheading = e($usuario['username']);
        $page_back       = ['url' => '/usuarios', 'label' => 'Gerenciar Usuários'];
        $extra_css       = ['cotacao.css'];
        $extra_js        = ['masks.js', 'auth.js', 'senha-validar.js'];

        return view('cadastros.usuarios.editar', get_defined_vars());
    }

    /**
     * GET /api/usuarios/listar
     * Lista usuários da imobiliária logada, com busca opcional por nome/e-mail.
     */
    public function listar(Request $request)
    {
        $current_user = require_auth();

        if (!($current_user->imob_admin ?? false)) {
            return response()->json(['html' => '<div class="admin-cot-empty">Sem permissão.</div>']);
        }

        $busca = trim((string) $request->query('busca', ''));

        $where  = 'imobiliaria_id = ?';
        $params = ['i', $current_user->imobiliaria_id];

        if ($busca !== '') {
            $where      .= ' AND (username LIKE ? OR email LIKE ?)';
            $params[0]  .= 'ss';
            $params[]    = "%{$busca}%";
            $params[]    = "%{$busca}%";
        }

        $usuarios = [];
        try {
            $usuarios = db_fetch_all(
                "SELECT id, username, slug, email, telefone, admin, avatar, last_login_at
                   FROM users
                  WHERE {$where}
                  ORDER BY username ASC",
                $params
            );
        } catch (Throwable $e) {}

        $html = view('cadastros._partials.usuarios-linhas', ['usuarios' => $usuarios, 'current_user' => $current_user, 'mps' => false])->render();

        return response()->json(['html' => $html]);
    }

    /**
     * POST /api/usuarios/excluir
     * Exclui um usuário da imobiliária logada.
     */
    public function excluir(Request $request)
    {
        $current_user = require_auth();

        if (!($current_user->imob_admin ?? false)) {
            return response()->json(['ok' => false, 'message' => 'Sem permissão.']);
        }

        $id = (int) $request->input('id', 0);

        if ($id <= 0) {
            return response()->json(['ok' => false, 'message' => 'Usuário inválido.']);
        }

        if ($id === (int) $current_user->uid) {
            return response()->json(['ok' => false, 'message' => 'Você não pode excluir sua própria conta.']);
        }

        $usuario = db_fetch_one(
            'SELECT id FROM users WHERE id = ? AND imobiliaria_id = ?',
            ['ii', $id, $current_user->imobiliaria_id]
        );

        if (!$usuario) {
            return response()->json(['ok' => false, 'message' => 'Usuário não encontrado.']);
        }

        db_execute('DELETE FROM users WHERE id = ? AND imobiliaria_id = ?', ['ii', $id, $current_user->imobiliaria_id]);

        return response()->json(['ok' => true]);
    }
}
