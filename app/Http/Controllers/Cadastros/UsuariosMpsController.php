<?php

namespace App\Http\Controllers\Cadastros;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;

/**
 * Usuários internos da MPS (tabela `usuario`).
 * Era pages/admin/usuarios*.php e includes/acao/admin-usuarios-*.php.
 */
class UsuariosMpsController extends Controller
{
    /** GET /admin/usuarios */
    public function index()
    {
        /**
         * GET /admin/usuarios
         * Lista todos os usuários internos da MPS (corretores/atendentes/admins).
         * Mesmo padrão de /usuarios (Gerenciar Usuários da imobiliária) — busca via
         * AJAX sem paginação, a URL nunca muda.
         */
        $current_user = require_mps_admin();

        $flash_messages = flash_get();

        $page_title      = 'Usuários MPS';
        $page_heading    = 'Usuários MPS';
        $page_subheading = 'Corretores, atendentes e administradores do sistema';
        $page_back       = null;
        $extra_css       = ['cotacoes.css'];
        $extra_js        = ['admin-usuarios-listar.js'];

        $main_attrs = ' data-flash="' . e(json_encode($flash_messages)) . '"';

        return view('cadastros.usuarios-mps.index', get_defined_vars());
    }

    /** GET+POST /admin/usuarios/criar */
    public function criar()
    {
        /**
         * GET+POST /admin/usuarios/criar
         * Formulário para criar novo usuário MPS (corretor/atendente/admin).
         */
        $current_user = require_mps_admin();

        $setores_disp = [];
        try { $setores_disp = db_fetch_all('SELECT id, nome FROM setores ORDER BY ordem ASC, nome ASC'); } catch (\Throwable $e) {}

        $erros   = [];
        $valores = [
            'username'                  => '',
            'email'                     => '',
            'telefone'                  => '',
            'painel'                    => false,
            'admin'                     => false,
            'perm_fianca'               => false,
            'perm_auto'                 => false,
            'perm_titulo'               => false,
            'perm_condominial'          => false,
            'perm_incendio'             => false,
            'perm_seguro_residencial'             => false,
            'perm_suporte'              => false,
            'equipe_comercial'          => false,
            'tarefas_gestor'            => false,
            'setores'                   => [],
            'recebe_email_fianca'       => false,
            'recebe_email_auto'         => false,
            'recebe_email_titulo'       => false,
            'recebe_email_condominial'  => false,
            'recebe_email_incendio'     => false,
            'recebe_email_seguro_residencial'     => false,
            'recebe_email_suporte'      => false,
        ];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();

            $valores['username'] = trim($_POST['username'] ?? '');
            $valores['email']    = trim($_POST['email'] ?? '');
            $valores['telefone'] = trim($_POST['telefone'] ?? '');
            $senha               = (string) ($_POST['senha'] ?? '');
            $senha_confirmar     = (string) ($_POST['senha_confirmar'] ?? '');

            // Conta do painel de TV não existe mais: o /painel é público (23/10/2026).
            $valores['painel']           = false;
            $valores['admin']            = !$valores['painel'] && ($_POST['admin'] ?? '') === 'S';
            // Admin MPS tem acesso total — força todas as permissões (mesma regra do lado imobiliária).
            // Fiança já unificada — sem a divisão Residencial/Comercial (mesmo caso das prefs de e-mail).
            $valores['perm_fianca']      = $valores['admin'] || ($_POST['perm_fianca'] ?? '') === 'S';
            $valores['perm_auto']        = $valores['admin'] || ($_POST['perm_auto'] ?? '') === 'S';
            $valores['perm_titulo']      = $valores['admin'] || ($_POST['perm_titulo'] ?? '') === 'S';
            $valores['perm_condominial'] = $valores['admin'] || ($_POST['perm_condominial'] ?? '') === 'S';
            $valores['perm_incendio']    = $valores['admin'] || ($_POST['perm_incendio'] ?? '') === 'S';
            $valores['perm_seguro_residencial']    = $valores['admin'] || ($_POST['perm_seguro_residencial'] ?? '') === 'S';
            $valores['perm_suporte']     = $valores['admin'] || ($_POST['perm_suporte'] ?? '') === 'S';
            $valores['equipe_comercial'] = $valores['admin'] || ($_POST['equipe_comercial'] ?? '') === 'S';

            // Tarefas: gestor vê tudo (setor = Administrador); não-gestor recebe setores.
            $valores['tarefas_gestor'] = ($_POST['tarefas_gestor'] ?? '') === 'S';
            $valores['setores']        = array_values(array_unique(array_map('intval', (array) ($_POST['setores'] ?? []))));

            // Preferências de e-mail são pessoais — não seguem o cascade do Administrador.
            // Refletem os 4 tipos reais de cotação (Fiança já unificada, sem a divisão Residencial/Comercial).
            $valores['recebe_email_fianca']      = ($_POST['recebe_email_fianca'] ?? '') === 'S';
            $valores['recebe_email_auto']        = ($_POST['recebe_email_auto'] ?? '') === 'S';
            $valores['recebe_email_titulo']      = ($_POST['recebe_email_titulo'] ?? '') === 'S';
            $valores['recebe_email_condominial'] = ($_POST['recebe_email_condominial'] ?? '') === 'S';
            $valores['recebe_email_incendio']    = ($_POST['recebe_email_incendio'] ?? '') === 'S';
            $valores['recebe_email_seguro_residencial']    = ($_POST['recebe_email_seguro_residencial'] ?? '') === 'S';
            $valores['recebe_email_suporte']     = ($_POST['recebe_email_suporte'] ?? '') === 'S';

            // A conta de exibição não recebe permissão, setor nem e-mail de nada.
            if ($valores['painel']) {
                foreach ([
                    'perm_fianca', 'perm_auto', 'perm_titulo', 'perm_condominial', 'perm_incendio', 'perm_seguro_residencial', 'perm_suporte',
                    'equipe_comercial', 'tarefas_gestor',
                    'recebe_email_fianca', 'recebe_email_auto', 'recebe_email_titulo',
                    'recebe_email_condominial', 'recebe_email_incendio', 'recebe_email_seguro_residencial', 'recebe_email_suporte',
                ] as $campo) {
                    $valores[$campo] = false;
                }
                $valores['setores'] = [];
            }

            if ($valores['username'] === '') $erros['username'] = 'Nome é obrigatório.';
            if ($valores['email'] === '' || !filter_var($valores['email'], FILTER_VALIDATE_EMAIL)) {
                $erros['email'] = 'Informe um e-mail válido.';
            }
            if ($senha === '' || !senha_valida($senha)) $erros['senha'] = 'A senha deve ter no mínimo 6 caracteres, com 1 maiúscula e 1 número.';
            if ($senha !== $senha_confirmar) $erros['senha_confirmar'] = 'As senhas não coincidem.';

            if (empty($erros)) {
                $existente = db_fetch_one('SELECT id FROM usuario WHERE email = ?', ['s', $valores['email']]);
                if ($existente) {
                    $erros['email'] = 'Já existe um usuário com este e-mail.';
                }
            }

            if (empty($erros)) {
                $slug = gerar_slug_usuario_mps($valores['username']);

                $novo_id = (int) db_execute(
                    "INSERT INTO usuario
                        (username, slug, email, email_cotacao, telefone, password, painel, admin,
                         perm_fianca, perm_residencial, perm_comercial, perm_auto, perm_titulo, perm_condominial, perm_incendio, perm_suporte, equipe_comercial, tarefas_gestor,
                         recebe_email_fianca, recebe_email_auto, recebe_email_titulo, recebe_email_condominial, recebe_email_incendio, recebe_email_suporte,
                         updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())",
                    ['ssssssssssssssssssssssss',
                        $valores['username'],
                        $slug,
                        $valores['email'],
                        $valores['email'],
                        $valores['telefone'] ?: null,
                        password_hash($senha, PASSWORD_DEFAULT),
                        $valores['painel'] ? 'S' : 'N',
                        $valores['admin'] ? 'S' : 'N',
                        $valores['perm_fianca'] ? 'S' : 'N',
                        // perm_residencial/perm_comercial mantidas em sincronia com o toggle único de Fiança.
                        $valores['perm_fianca'] ? 'S' : 'N',
                        $valores['perm_fianca'] ? 'S' : 'N',
                        $valores['perm_auto'] ? 'S' : 'N',
                        $valores['perm_titulo'] ? 'S' : 'N',
                        $valores['perm_condominial'] ? 'S' : 'N',
                        $valores['perm_incendio'] ? 'S' : 'N',
                        $valores['perm_suporte'] ? 'S' : 'N',
                        $valores['equipe_comercial'] ? 'S' : 'N',
                        $valores['tarefas_gestor'] ? 'S' : 'N',
                        $valores['recebe_email_fianca'] ? 'S' : 'N',
                        $valores['recebe_email_auto'] ? 'S' : 'N',
                        $valores['recebe_email_titulo'] ? 'S' : 'N',
                        $valores['recebe_email_condominial'] ? 'S' : 'N',
                        $valores['recebe_email_incendio'] ? 'S' : 'N',
                        $valores['recebe_email_suporte'] ? 'S' : 'N',
                    ]
                );

                // Vincula setores (só para não-gestores — gestor vê tudo).
                // (id do INSERT acima — era db()->insert_id)
                if (!$valores['tarefas_gestor'] && $novo_id > 0) {
                    foreach ($valores['setores'] as $sid) {
                        if ($sid > 0) {
                            db_execute('INSERT IGNORE INTO tarefas_usuario_setor (usuario_id, setor_id) VALUES (?, ?)', ['ii', $novo_id, $sid]);
                        }
                    }
                }

                // Seguro Residencial (migration 094) — fora da query acima, que tem a lista fixa de colunas.

                db_execute('UPDATE usuario SET perm_seguro_residencial = ?, recebe_email_seguro_residencial = ? WHERE id = ?', ['ssi', $valores['perm_seguro_residencial'] ? 'S' : 'N', $valores['recebe_email_seguro_residencial'] ? 'S' : 'N', $novo_id]);

                flash('success', 'Usuário "' . $valores['username'] . '" criado com sucesso.');
                ir_para('/admin/usuarios');
            }
        }

        $page_title      = 'Criar Usuário MPS';
        $page_heading    = 'Criar Usuário MPS';
        $page_subheading = 'Adicione um novo corretor, atendente ou administrador';
        $page_back       = ['url' => '/admin/usuarios', 'label' => 'Usuários MPS'];
        $extra_css       = ['cotacao.css'];
        $extra_js        = ['masks.js', 'auth.js', 'senha-validar.js'];

        return view('cadastros.usuarios-mps.criar', get_defined_vars());
    }

    /** GET+POST /admin/usuarios/{slug}/editar */
    public function editar(string $slug)
    {
        $params = ['slug' => $slug];

        /**
         * GET+POST /admin/usuarios/{slug}/editar
         * Formulário para editar usuário MPS existente.
         */
        $current_user = require_mps_admin();

        $slug_param = trim($params['slug'] ?? '');
        if ($slug_param === '') ir_para('/admin/usuarios');

        $usuario = db_fetch_one('SELECT * FROM usuario WHERE slug = ?', ['s', $slug_param]);
        if (!$usuario) ir_para('/admin/usuarios');

        $id = (int) $usuario['id'];

        $setores_disp = [];
        try { $setores_disp = db_fetch_all('SELECT id, nome FROM setores ORDER BY ordem ASC, nome ASC'); } catch (\Throwable $e) {}
        $setores_usuario = [];
        try {
            $rows = db_fetch_all('SELECT setor_id FROM tarefas_usuario_setor WHERE usuario_id = ?', ['i', $id]);
            $setores_usuario = array_map(static fn($r) => (int) $r['setor_id'], $rows);
        } catch (\Throwable $e) {}

        $erros   = [];
        $valores = [
            'username'                  => $usuario['username'],
            'email'                     => $usuario['email'],
            'email_cotacao'             => $usuario['email_cotacao'] ?? '',
            'telefone'                  => $usuario['telefone'] ?? '',
            'painel'                    => ($usuario['painel'] ?? 'N') === 'S',
            'admin'                     => $usuario['admin'] === 'S',
            'perm_fianca'               => $usuario['perm_fianca'] === 'S',
            'perm_auto'                 => $usuario['perm_auto'] === 'S',
            'perm_titulo'               => $usuario['perm_titulo'] === 'S',
            'perm_condominial'          => $usuario['perm_condominial'] === 'S',
            'perm_incendio'             => ($usuario['perm_incendio'] ?? 'N') === 'S',
            'perm_seguro_residencial'             => ($usuario['perm_seguro_residencial'] ?? 'N') === 'S',
            'perm_suporte'              => $usuario['perm_suporte'] === 'S',
            'equipe_comercial'          => $usuario['equipe_comercial'] === 'S',
            'tarefas_gestor'            => ($usuario['tarefas_gestor'] ?? 'N') === 'S',
            'setores'                   => $setores_usuario,
            'recebe_email_fianca'       => $usuario['recebe_email_fianca'] === 'S',
            'recebe_email_auto'         => $usuario['recebe_email_auto'] === 'S',
            'recebe_email_titulo'       => $usuario['recebe_email_titulo'] === 'S',
            'recebe_email_condominial'  => $usuario['recebe_email_condominial'] === 'S',
            'recebe_email_incendio'     => ($usuario['recebe_email_incendio'] ?? 'N') === 'S',
            'recebe_email_seguro_residencial'     => ($usuario['recebe_email_seguro_residencial'] ?? 'N') === 'S',
            'recebe_email_suporte'      => ($usuario['recebe_email_suporte'] ?? 'N') === 'S',
        ];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();

            $valores['username']      = trim($_POST['username'] ?? '');
            $valores['email']         = trim($_POST['email'] ?? '');
            $valores['email_cotacao'] = trim($_POST['email_cotacao'] ?? '');
            $valores['telefone']      = trim($_POST['telefone'] ?? '');
            $senha                    = (string) ($_POST['senha'] ?? '');
            $senha_confirmar          = (string) ($_POST['senha_confirmar'] ?? '');

            // Conta do painel de TV não existe mais: o /painel é público (23/10/2026).
            $valores['painel']           = false;
            $valores['admin']            = !$valores['painel'] && ($_POST['admin'] ?? '') === 'S';
            $valores['perm_fianca']      = $valores['admin'] || ($_POST['perm_fianca'] ?? '') === 'S';
            $valores['perm_auto']        = $valores['admin'] || ($_POST['perm_auto'] ?? '') === 'S';
            $valores['perm_titulo']      = $valores['admin'] || ($_POST['perm_titulo'] ?? '') === 'S';
            $valores['perm_condominial'] = $valores['admin'] || ($_POST['perm_condominial'] ?? '') === 'S';
            $valores['perm_incendio']    = $valores['admin'] || ($_POST['perm_incendio'] ?? '') === 'S';
            $valores['perm_seguro_residencial']    = $valores['admin'] || ($_POST['perm_seguro_residencial'] ?? '') === 'S';
            $valores['perm_suporte']     = $valores['admin'] || ($_POST['perm_suporte'] ?? '') === 'S';
            $valores['equipe_comercial'] = $valores['admin'] || ($_POST['equipe_comercial'] ?? '') === 'S';

            $valores['tarefas_gestor'] = ($_POST['tarefas_gestor'] ?? '') === 'S';
            $valores['setores']        = array_values(array_unique(array_map('intval', (array) ($_POST['setores'] ?? []))));

            $valores['recebe_email_fianca']      = ($_POST['recebe_email_fianca'] ?? '') === 'S';
            $valores['recebe_email_auto']        = ($_POST['recebe_email_auto'] ?? '') === 'S';
            $valores['recebe_email_titulo']      = ($_POST['recebe_email_titulo'] ?? '') === 'S';
            $valores['recebe_email_condominial'] = ($_POST['recebe_email_condominial'] ?? '') === 'S';
            $valores['recebe_email_incendio']    = ($_POST['recebe_email_incendio'] ?? '') === 'S';
            $valores['recebe_email_seguro_residencial']    = ($_POST['recebe_email_seguro_residencial'] ?? '') === 'S';
            $valores['recebe_email_suporte']     = ($_POST['recebe_email_suporte'] ?? '') === 'S';

            // A conta de exibição não recebe permissão, setor nem e-mail de nada.
            if ($valores['painel']) {
                foreach ([
                    'perm_fianca', 'perm_auto', 'perm_titulo', 'perm_condominial', 'perm_incendio', 'perm_seguro_residencial', 'perm_suporte',
                    'equipe_comercial', 'tarefas_gestor',
                    'recebe_email_fianca', 'recebe_email_auto', 'recebe_email_titulo',
                    'recebe_email_condominial', 'recebe_email_incendio', 'recebe_email_seguro_residencial', 'recebe_email_suporte',
                ] as $campo) {
                    $valores[$campo] = false;
                }
                $valores['setores'] = [];
            }

            if ($valores['username'] === '') $erros['username'] = 'Nome é obrigatório.';
            if ($valores['email'] === '' || !filter_var($valores['email'], FILTER_VALIDATE_EMAIL)) {
                $erros['email'] = 'Informe um e-mail válido.';
            }
            if ($valores['email_cotacao'] !== '' && !filter_var($valores['email_cotacao'], FILTER_VALIDATE_EMAIL)) {
                $erros['email_cotacao'] = 'Informe um e-mail válido ou deixe em branco.';
            }
            if ($senha !== '' && !senha_valida($senha)) $erros['senha'] = 'A senha deve ter no mínimo 6 caracteres, com 1 maiúscula e 1 número.';
            if ($senha !== '' && $senha !== $senha_confirmar) $erros['senha_confirmar'] = 'As senhas não coincidem.';

            if (empty($erros) && $valores['email'] !== $usuario['email']) {
                $existente = db_fetch_one('SELECT id FROM usuario WHERE email = ? AND id != ?', ['si', $valores['email'], $id]);
                if ($existente) $erros['email'] = 'Já existe um usuário com este e-mail.';
            }

            if (empty($erros)) {
                $novo_slug = $valores['username'] !== $usuario['username']
                    ? gerar_slug_usuario_mps($valores['username'], $id)
                    : $usuario['slug'];

                $valores_bd = [
                    // email_cotacao é NOT NULL no banco — vazio fica string vazia, não null.
                    $valores['username'], $novo_slug, $valores['email'], $valores['email_cotacao'], $valores['telefone'] ?: null,
                ];
                $tipos_bd = 'sssss';

                if ($senha !== '') {
                    $valores_bd[] = password_hash($senha, PASSWORD_DEFAULT);
                    $tipos_bd    .= 's';
                }

                $valores_bd = array_merge($valores_bd, [
                    $valores['painel'] ? 'S' : 'N',
                    $valores['admin'] ? 'S' : 'N',
                    $valores['perm_fianca'] ? 'S' : 'N',
                    // perm_residencial/perm_comercial mantidas em sincronia com o toggle único de Fiança.
                    $valores['perm_fianca'] ? 'S' : 'N',
                    $valores['perm_fianca'] ? 'S' : 'N',
                    $valores['perm_auto'] ? 'S' : 'N',
                    $valores['perm_titulo'] ? 'S' : 'N',
                    $valores['perm_condominial'] ? 'S' : 'N',
                    $valores['perm_incendio'] ? 'S' : 'N',
                    $valores['perm_suporte'] ? 'S' : 'N',
                    $valores['equipe_comercial'] ? 'S' : 'N',
                    $valores['tarefas_gestor'] ? 'S' : 'N',
                    $valores['recebe_email_fianca'] ? 'S' : 'N',
                    $valores['recebe_email_auto'] ? 'S' : 'N',
                    $valores['recebe_email_titulo'] ? 'S' : 'N',
                    $valores['recebe_email_condominial'] ? 'S' : 'N',
                    $valores['recebe_email_incendio'] ? 'S' : 'N',
                    $valores['recebe_email_suporte'] ? 'S' : 'N',
                ]);
                $tipos_bd .= 'ssssssssssssssssss';

                $valores_bd[] = $id;
                $tipos_bd    .= 'i';

                db_execute(
                    'UPDATE usuario SET username = ?, slug = ?, email = ?, email_cotacao = ?, telefone = ?, ' .
                    ($senha !== '' ? 'password = ?, ' : '') .
                    'painel = ?, admin = ?, perm_fianca = ?, perm_residencial = ?, perm_comercial = ?, perm_auto = ?, perm_titulo = ?, perm_condominial = ?, perm_incendio = ?, perm_suporte = ?, equipe_comercial = ?, tarefas_gestor = ?,
                     recebe_email_fianca = ?, recebe_email_auto = ?, recebe_email_titulo = ?, recebe_email_condominial = ?, recebe_email_incendio = ?, recebe_email_suporte = ?,
                     updated_at = NOW()
                     WHERE id = ?',
                    [$tipos_bd, ...$valores_bd]
                );

                // Sincroniza setores (só para não-gestores — gestor vê tudo).
                db_execute('DELETE FROM tarefas_usuario_setor WHERE usuario_id = ?', ['i', $id]);
                if (!$valores['tarefas_gestor']) {
                    foreach ($valores['setores'] as $sid) {
                        if ($sid > 0) {
                            db_execute('INSERT IGNORE INTO tarefas_usuario_setor (usuario_id, setor_id) VALUES (?, ?)', ['ii', $id, $sid]);
                        }
                    }
                }

                // Seguro Residencial (migration 094) — fora da query acima, que tem a lista fixa de colunas.

                db_execute('UPDATE usuario SET perm_seguro_residencial = ?, recebe_email_seguro_residencial = ? WHERE id = ?', ['ssi', $valores['perm_seguro_residencial'] ? 'S' : 'N', $valores['recebe_email_seguro_residencial'] ? 'S' : 'N', $id]);

                flash('success', 'Usuário "' . $valores['username'] . '" atualizado com sucesso.');
                ir_para('/admin/usuarios');
            }
        }

        $page_title      = 'Editar Usuário MPS — ' . $usuario['username'];
        $page_heading    = 'Editar Usuário MPS';
        $page_subheading = e($usuario['username']);
        $page_back       = ['url' => '/admin/usuarios', 'label' => 'Usuários MPS'];
        $extra_css       = ['cotacao.css'];
        $extra_js        = ['masks.js', 'auth.js', 'senha-validar.js'];

        return view('cadastros.usuarios-mps.editar', get_defined_vars());
    }

    /**
     * GET /api/admin/usuarios/listar
     * Lista os usuários internos da MPS, com busca opcional por nome/e-mail.
     * Mesmo padrão de /api/usuarios/listar (imobiliária) — sem paginação.
     */
    public function listar(Request $request)
    {
        $current_user = require_mps_admin();

        $busca = trim((string) $request->query('busca', ''));

        $where  = '1=1';
        $tipos  = '';
        $params = [];

        if ($busca !== '') {
            $where   .= ' AND (username LIKE ? OR email LIKE ?)';
            $tipos   .= 'ss';
            $params[] = "%{$busca}%";
            $params[] = "%{$busca}%";
        }

        $usuarios = [];
        try {
            $sql = "SELECT id, username, slug, email, telefone, admin, painel, avatar, last_login_at
                      FROM usuario
                     WHERE {$where}
                     ORDER BY username ASC";
            $usuarios = $tipos !== '' ? db_fetch_all($sql, [$tipos, ...$params]) : db_fetch_all($sql);
        } catch (Throwable $e) {}

        $html = view('cadastros._partials.usuarios-linhas', ['usuarios' => $usuarios, 'current_user' => $current_user, 'mps' => true])->render();

        return response()->json(['html' => $html]);
    }

    /**
     * POST /api/admin/usuarios/excluir
     * Exclui um usuário interno da MPS (tabela `usuario`).
     */
    public function excluir(Request $request)
    {
        $current_user = require_mps_admin();

        $id = (int) $request->input('id', 0);

        if ($id <= 0) {
            return response()->json(['ok' => false, 'message' => 'Usuário inválido.']);
        }

        if ($id === (int) $current_user->uid) {
            return response()->json(['ok' => false, 'message' => 'Você não pode excluir sua própria conta.']);
        }

        $usuario = db_fetch_one('SELECT id FROM usuario WHERE id = ?', ['i', $id]);

        if (!$usuario) {
            return response()->json(['ok' => false, 'message' => 'Usuário não encontrado.']);
        }

        db_execute('DELETE FROM usuario WHERE id = ?', ['i', $id]);

        return response()->json(['ok' => true]);
    }
}
