<?php

namespace App\Http\Controllers\Cadastros;

use App\Http\Controllers\Controller;
use Exception;
use Illuminate\Http\Request;
use RuntimeException;
use Throwable;
use Illuminate\Support\Facades\DB;

/**
 * Imobiliárias parceiras (visão MPS) e os usuários vinculados a cada uma.
 * Era pages/admin/imobiliarias*.php e includes/acao/admin-imobiliarias-*.php.
 */
class ImobiliariasController extends Controller
{
    /** GET /admin/imobiliarias */
    public function index()
    {
        /**
         * GET /admin/imobiliarias
         * Lista todas as imobiliárias parceiras com busca e filtro por status.
         * Filtros e paginação são tudo via AJAX (/api/admin/imobiliarias/listar),
         * a URL nunca muda.
         */
        $current_user = require_admin();

        $flash_messages = flash_get();

        $page_title      = 'Imobiliárias';
        $page_heading    = 'Imobiliárias';
        $page_subheading = 'Gerencie as imobiliárias parceiras';
        $page_back       = null;
        $extra_css       = ['dashboard.css', 'cotacoes.css'];
        $extra_js        = ['admin-imobiliarias-listar.js'];

        $main_attrs = ' data-flash="' . e(json_encode($flash_messages)) . '"';

        return view('cadastros.imobiliarias.index', get_defined_vars());
    }

    /** GET+POST /admin/imobiliarias/criar */
    public function criar()
    {
        /**
         * GET+POST /admin/imobiliarias/criar
         * Formulário para cadastrar nova imobiliária parceira.
         * Se for cliente Sami, busca no cadastro institucional (somente leitura)
         * pra puxar nome/sistema/endereço automaticamente; senão pede o endereço
         * na mão. Também define as comissões por tipo de seguro e a região.
         */
        $current_user = require_admin();


        $erros   = [];
        $valores = [
            'nome'                  => '',
            'cnpj'                  => '',
            'email'                 => '',
            'telefone'              => '',
            'eh_cliente_sami'       => false,
            'institucional_sigla'   => '',
            'institucional_sistema' => '',
            'institucional_site'    => '',
            'samiloca_api_user'     => '',
            'samiloca_api_pass'     => '',
            'cep'                   => '',
            'endereco'              => '',
            'numero'                => '',
            'complemento'           => '',
            'bairro'                => '',
            'cidade'                => '',
            'uf'                    => '',
            'regiao_id'             => '',
            'usa_embed'             => false,
            'embed_dominio'         => '',
            'admin_username'        => '',
            'admin_email'           => '',
            'admin_telefone'        => '',
            'canais'                => canais_da_imobiliaria(0),
            'segimob_estipulante'   => '',
        ];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();

            foreach (array_keys($valores) as $campo) {
                if (in_array($campo, ['eh_cliente_sami', 'usa_embed', 'canais'], true)) continue;
                $valores[$campo] = trim($_POST[$campo] ?? '');
            }
            $valores['eh_cliente_sami'] = ($_POST['eh_cliente_sami'] ?? '') === '1';
            $valores['usa_embed']       = ($_POST['usa_embed'] ?? '') === '1';
            self::lerCanais($valores, $erros);

            $admin_senha            = (string) ($_POST['admin_senha'] ?? '');
            $admin_senha_confirmar  = (string) ($_POST['admin_senha_confirmar'] ?? '');

            // Todos os campos são obrigatórios, exceto complemento.
            if ($valores['nome'] === '') $erros['nome'] = 'Nome é obrigatório.';
            if ($valores['cnpj'] === '') $erros['cnpj'] = 'CNPJ é obrigatório.';
            if ($valores['email'] === '' || !filter_var($valores['email'], FILTER_VALIDATE_EMAIL)) {
                $erros['email'] = 'Informe um e-mail válido.';
            }
            if ($valores['telefone'] === '') $erros['telefone'] = 'Telefone é obrigatório.';
            if ($valores['cep'] === '') $erros['cep'] = 'CEP é obrigatório.';
            if ($valores['endereco'] === '') $erros['endereco'] = 'Endereço é obrigatório.';
            if ($valores['numero'] === '') $erros['numero'] = 'Número é obrigatório.';
            if ($valores['bairro'] === '') $erros['bairro'] = 'Bairro é obrigatório.';
            if ($valores['cidade'] === '') $erros['cidade'] = 'Cidade é obrigatória.';
            if ($valores['uf'] === '') $erros['uf'] = 'UF é obrigatória.';

            // Usuário administrador agora é sempre obrigatório — toda imobiliária precisa nascer com acesso.
            if ($valores['admin_username'] === '') $erros['admin_username'] = 'Nome é obrigatório.';
            if ($valores['admin_email'] === '' || !filter_var($valores['admin_email'], FILTER_VALIDATE_EMAIL)) {
                $erros['admin_email'] = 'Informe um e-mail válido.';
            }
            if ($valores['admin_telefone'] === '') $erros['admin_telefone'] = 'Telefone é obrigatório.';
            if ($admin_senha === '' || !senha_valida($admin_senha)) $erros['admin_senha'] = 'A senha deve ter no mínimo 6 caracteres, com 1 maiúscula e 1 número.';
            if ($admin_senha !== $admin_senha_confirmar) $erros['admin_senha_confirmar'] = 'As senhas não coincidem.';

            if (empty($erros['admin_email'])) {
                // Imobiliária nova: e-mail de usuário de qualquer outra bloqueia.
                if (email_usado_em_outra_imobiliaria($valores['admin_email'], 0)) {
                    $erros['admin_email'] = 'Este e-mail já é usado por outra imobiliária.';
                }
            }

            // URL da API SAMILOCA: guarda sempre normalizada (com https, sem barra final).
            $valores['institucional_site'] = samiloca_url_normalizar($valores['institucional_site']);

            if (empty($erros)) {
                $sistema       = $valores['eh_cliente_sami'] ? ($valores['institucional_sistema'] ?: null) : null;
                $sigla_erp     = $valores['eh_cliente_sami'] ? ($valores['institucional_sigla'] ?: null) : null;
                $site_sami     = $valores['eh_cliente_sami'] ? ($valores['institucional_site'] ?: null) : null;
                $samiloca_user = $valores['eh_cliente_sami'] ? ($valores['samiloca_api_user'] ?: null) : null;
                $samiloca_pass = $valores['eh_cliente_sami'] ? ($valores['samiloca_api_pass'] ?: null) : null;

                $slug = gerar_slug_imobiliaria($valores['nome']);

                $embed_token = $valores['usa_embed'] ? embed_gerar_token() : null;

                $valores_bd = [
                    $valores['nome'], $slug, $valores['cnpj'] ?: null, $valores['email'] ?: null, $valores['telefone'] ?: null,
                    $valores['cep'] ?: null, $valores['endereco'] ?: null, $valores['numero'] ?: null, $valores['complemento'] ?: null,
                    $valores['bairro'] ?: null, $valores['cidade'] ?: null, $valores['uf'] ?: null,
                    $sistema, $sigla_erp, $site_sami, $samiloca_user, $samiloca_pass, $valores['eh_cliente_sami'] ? 1 : 0,
                    $valores['regiao_id'] ? (int) $valores['regiao_id'] : null,
                    $valores['usa_embed'] ? 1 : 0, $embed_token, $valores['embed_dominio'] ?: null,
                ];

                // Imobiliária + usuário administrador + comissões numa transação só
                // (era $conn->begin_transaction()/commit()/rollback() no mysqli).
                try {
                    DB::transaction(function () use ($valores, $valores_bd, $admin_senha, $current_user) {
                        $imobiliaria_id = db_execute(
                            "INSERT INTO imobiliarias
                                (nome, slug, cnpj, email, telefone, cep, endereco, numero, complemento, bairro, cidade, uf,
                                 sistema, sigla_erp, site_samiloca, samiloca_api_user, samiloca_api_pass, eh_cliente_sami, regiao_id, ativo, usa_embed, embed_token, embed_dominio, criado_em)
                             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 1, ?, ?, ?, NOW())",
                            [str_repeat('s', count($valores_bd)), ...$valores_bd]
                        );

                        $admin_slug = gerar_slug_usuario($valores['admin_username'], (int) $imobiliaria_id);

                        db_execute(
                            "INSERT INTO users
                                (username, slug, email, telefone, password, imobiliaria_id, admin, criado_por,
                                 perm_criar_usuarios, perm_cotar, perm_suporte, perm_aceitar,
                                 perm_fianca, perm_auto, perm_condo, perm_titulo, novidade_incendio_visto_em, created_at)
                             VALUES (?, ?, ?, ?, ?, ?, 'S', ?, 'S', 'S', 'S', 'S', 'S', 'S', 'S', 'S', NOW(), NOW())",
                            ['sssssii',
                                $valores['admin_username'],
                                $admin_slug,
                                $valores['admin_email'],
                                $valores['admin_telefone'],
                                password_hash($admin_senha, PASSWORD_DEFAULT),
                                (int) $imobiliaria_id,
                                // $current_user aqui é sempre um admin MPS (tabela `usuario`), nunca um
                                // registro de `users` — usar o uid dele quebraria a FK fk_users_criado_por.
                                null,
                            ]
                        );

                        foreach (array_keys(IMOB_TIPOS_SEGURO) as $tipo) {
                            if (($_POST["seguro_{$tipo}"] ?? '') !== '1') continue;

                            $modo  = ($_POST["com_modo_{$tipo}"] ?? 'percentual') === 'valor' ? 'valor' : 'percentual';
                            $bruto = trim((string) ($_POST["com_valor_{$tipo}"] ?? ''));
                            if ($bruto === '') continue;
                            $numero = (float) str_replace(['.', ','], ['', '.'], $bruto);
                            if ($numero <= 0) continue;

                            db_execute(
                                "INSERT INTO comissoes_imobiliarias (imobiliaria_id, tipo_seguro, percentual, modo, valor, atualizado_por)
                                 VALUES (?, ?, ?, ?, ?, ?)",
                                ['isdsdi', $imobiliaria_id, $tipo, $modo === 'percentual' ? $numero : 0, $modo, $modo === 'valor' ? $numero : null, (int) $current_user->uid]
                            );
                        }

                        self::salvarCanais((int) $imobiliaria_id, $valores);
                    });
                } catch (Throwable $e) {
                    error_log('[imobiliarias/criar] Falha ao salvar imobiliária: ' . $e->getMessage());
                    $erros['geral'] = 'Não foi possível salvar a imobiliária. Tente novamente ou contate o suporte.';
                }

                if (empty($erros)) {
                    flash('success', 'Imobiliária "' . $valores['nome'] . '" criada com sucesso.');
                    ir_para('/admin/imobiliarias');
                }
            }
        }

        $regioes = [];
        try {
            $regioes = db_fetch_all('SELECT id, nome FROM regioes ORDER BY nome ASC');
        } catch (Throwable) {}
        $opcoes_regiao = [];
        foreach ($regioes as $r) {
            $opcoes_regiao[(string) $r['id']] = $r['nome'];
        }

        $page_title      = 'Nova Imobiliária';
        $page_heading     = 'Nova Imobiliária';
        $page_back       = ['url' => '/admin/imobiliarias', 'label' => 'Imobiliárias'];
        $extra_css       = ['dashboard.css', 'cotacao.css', 'visitas.css', 'imobiliarias.css'];
        $extra_js        = ['admin-imobiliarias-form.js', 'auth.js', 'senha-validar.js'];

        $main_attrs = ' data-erro-geral="' . (!empty($erros) ? e(!empty($erros['geral']) ? $erros['geral'] : 'Corrija os campos destacados abaixo para continuar.') : '') . '"';

        return view('cadastros.imobiliarias.criar', get_defined_vars());
    }

    /** GET+POST /admin/imobiliarias/{slug}/editar */
    public function editar(string $slug)
    {
        $params = ['slug' => $slug];

        /**
         * GET+POST /admin/imobiliarias/{slug}/editar
         * Formulário para editar uma imobiliária existente — mesmos blocos do
         * cadastro (cliente Sami / busca institucional, endereço, comissões, região).
         */
        $current_user = require_admin();


        $slug_param = trim($params['slug'] ?? '');
        if ($slug_param === '') {
            ir_para('/admin/imobiliarias');
        }

        try {
            $imob = db_fetch_one('SELECT * FROM imobiliarias WHERE slug = ?', ['s', $slug_param]);
        } catch (Throwable $e) {
            $imob = null;
        }

        if (!$imob) {
            ir_para('/admin/imobiliarias');
        }

        $id = (int) $imob['id'];

        $erros   = [];
        $valores = [
            'nome'                  => $imob['nome'] ?? '',
            'cnpj'                  => $imob['cnpj'] ?? '',
            'email'                 => $imob['email'] ?? '',
            'telefone'              => $imob['telefone'] ?? '',
            'eh_cliente_sami'       => (bool) ($imob['eh_cliente_sami'] ?? 0),
            'institucional_sigla'   => $imob['sigla_erp'] ?? '',
            'institucional_sistema' => $imob['sistema'] ?? '',
            'institucional_site'    => $imob['site_samiloca'] ?? '',
            'samiloca_api_user'     => $imob['samiloca_api_user'] ?? '',
            'samiloca_api_pass'     => $imob['samiloca_api_pass'] ?? '',
            'cep'                   => $imob['cep'] ?? '',
            'endereco'              => $imob['endereco'] ?? '',
            'numero'                => $imob['numero'] ?? '',
            'complemento'           => $imob['complemento'] ?? '',
            'bairro'                => $imob['bairro'] ?? '',
            'cidade'                => $imob['cidade'] ?? '',
            'uf'                    => $imob['uf'] ?? '',
            'regiao_id'             => $imob['regiao_id'] ?? '',
            'ativo'                 => (bool) ($imob['ativo'] ?? 1),
            'usa_embed'             => (bool) ($imob['usa_embed'] ?? 0),
            'embed_dominio'         => $imob['embed_dominio'] ?? '',
            'canais'                => canais_da_imobiliaria($id),
            'segimob_estipulante'   => (string) ($imob['segimob_estipulante'] ?? ''),
        ];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            csrf_verify();

            foreach (array_keys($valores) as $campo) {
                if (in_array($campo, ['eh_cliente_sami', 'ativo', 'usa_embed', 'canais'], true)) continue;
                $valores[$campo] = trim($_POST[$campo] ?? '');
            }
            $valores['eh_cliente_sami'] = ($_POST['eh_cliente_sami'] ?? '') === '1';
            $valores['ativo']           = ($_POST['ativo'] ?? '') === '1';
            $valores['usa_embed']       = ($_POST['usa_embed'] ?? '') === '1';
            self::lerCanais($valores, $erros);

            if ($valores['nome'] === '') $erros['nome'] = 'Nome é obrigatório.';
            if ($valores['email'] !== '' && !filter_var($valores['email'], FILTER_VALIDATE_EMAIL)) {
                $erros['email'] = 'Informe um e-mail válido.';
            }

            // URL da API SAMILOCA: guarda sempre normalizada (com https, sem barra final).
            $valores['institucional_site'] = samiloca_url_normalizar($valores['institucional_site']);

            if (empty($erros)) {
                $sistema        = $valores['eh_cliente_sami'] ? ($valores['institucional_sistema'] ?: null) : null;
                $sigla_erp      = $valores['eh_cliente_sami'] ? ($valores['institucional_sigla'] ?: null) : null;
                $site_sami      = $valores['eh_cliente_sami'] ? ($valores['institucional_site'] ?: null) : null;
                $samiloca_user  = $valores['eh_cliente_sami'] ? ($valores['samiloca_api_user'] ?: null) : null;
                $samiloca_pass  = $valores['eh_cliente_sami'] ? ($valores['samiloca_api_pass'] ?: null) : null;

                $novo_slug = $valores['nome'] !== $imob['nome']
                    ? gerar_slug_imobiliaria($valores['nome'], $id)
                    : $imob['slug'];

                // Gera o token de embed na primeira vez que o toggle é ligado; depois
                // disso o token nunca muda, mesmo que o embed seja desligado e religado
                // (senão os iframes já colados no site do cliente parariam de funcionar).
                $embed_token = $imob['embed_token'] ?? null;
                if ($valores['usa_embed'] && !$embed_token) {
                    $embed_token = embed_gerar_token();
                }

                $valores_bd = [
                    $valores['nome'], $novo_slug, $valores['cnpj'] ?: null, $valores['email'] ?: null, $valores['telefone'] ?: null,
                    $valores['cep'] ?: null, $valores['endereco'] ?: null, $valores['numero'] ?: null, $valores['complemento'] ?: null,
                    $valores['bairro'] ?: null, $valores['cidade'] ?: null, $valores['uf'] ?: null,
                    $sistema, $sigla_erp, $site_sami, $samiloca_user, $samiloca_pass, $valores['eh_cliente_sami'] ? 1 : 0,
                    $valores['regiao_id'] ? (int) $valores['regiao_id'] : null, $valores['ativo'] ? 1 : 0,
                    $valores['usa_embed'] ? 1 : 0, $embed_token, $valores['embed_dominio'] ?: null,
                    $id,
                ];

                db_execute(
                    "UPDATE imobiliarias
                        SET nome = ?, slug = ?, cnpj = ?, email = ?, telefone = ?, cep = ?, endereco = ?, numero = ?, complemento = ?,
                            bairro = ?, cidade = ?, uf = ?, sistema = ?, sigla_erp = ?, site_samiloca = ?, samiloca_api_user = ?, samiloca_api_pass = ?, eh_cliente_sami = ?,
                            regiao_id = ?, ativo = ?, usa_embed = ?, embed_token = ?, embed_dominio = ?
                     WHERE id = ?",
                    [str_repeat('s', count($valores_bd)), ...$valores_bd]
                );

                self::salvarCanais($id, $valores);

                foreach (array_keys(IMOB_TIPOS_SEGURO) as $tipo) {
                    if (($_POST["seguro_{$tipo}"] ?? '') !== '1') {
                        db_execute('DELETE FROM comissoes_imobiliarias WHERE imobiliaria_id = ? AND tipo_seguro = ?', ['is', $id, $tipo]);
                        continue;
                    }

                    $modo  = ($_POST["com_modo_{$tipo}"] ?? 'percentual') === 'valor' ? 'valor' : 'percentual';
                    $bruto = trim((string) ($_POST["com_valor_{$tipo}"] ?? ''));
                    $numero = $bruto !== '' ? (float) str_replace(['.', ','], ['', '.'], $bruto) : 0;

                    db_execute(
                        "INSERT INTO comissoes_imobiliarias (imobiliaria_id, tipo_seguro, percentual, modo, valor, atualizado_por)
                         VALUES (?, ?, ?, ?, ?, ?)
                         ON DUPLICATE KEY UPDATE percentual = VALUES(percentual), modo = VALUES(modo), valor = VALUES(valor), atualizado_por = VALUES(atualizado_por)",
                        ['isdsdi', $id, $tipo, $modo === 'percentual' ? $numero : 0, $modo, $modo === 'valor' ? $numero : null, (int) $current_user->uid]
                    );
                }

                flash('success', 'Imobiliária "' . $valores['nome'] . '" atualizada com sucesso.');
                ir_para('/admin/imobiliarias');
            }
        }

        $comissoes_atuais = [];
        foreach (array_keys(IMOB_TIPOS_SEGURO) as $tipo) {
            $comissoes_atuais[$tipo] = ['ativo' => false, 'modo' => 'percentual', 'valor' => null];
        }
        try {
            $rows = db_fetch_all('SELECT tipo_seguro, modo, percentual, valor FROM comissoes_imobiliarias WHERE imobiliaria_id = ?', ['i', $id]);
            foreach ($rows as $r) {
                if (isset($comissoes_atuais[$r['tipo_seguro']])) {
                    $comissoes_atuais[$r['tipo_seguro']] = [
                        'ativo' => true,
                        'modo'  => $r['modo'],
                        'valor' => $r['modo'] === 'valor' ? $r['valor'] : $r['percentual'],
                    ];
                }
            }
        } catch (Throwable) {}

        $regioes = [];
        try {
            $regioes = db_fetch_all('SELECT id, nome FROM regioes ORDER BY nome ASC');
        } catch (Throwable) {}
        $opcoes_regiao = [];
        foreach ($regioes as $r) {
            $opcoes_regiao[(string) $r['id']] = $r['nome'];
        }

        $total_cotacoes = safe_count('SELECT COUNT(*) AS total FROM seguro_fianca WHERE imobiliaria_id = ?', ['i', $id])
                        + safe_count('SELECT COUNT(*) AS total FROM seguro_auto   WHERE imobiliaria_id = ?', ['i', $id]);

        $total_usuarios = safe_count('SELECT COUNT(*) AS total FROM users WHERE imobiliaria_id = ?', ['i', $id]);

        $criado_em = !empty($imob['criado_em']) ? date_to_br(substr($imob['criado_em'], 0, 10)) : '—';

        $page_title      = 'Editar Imobiliária — ' . ($imob['nome'] ?? '');
        $page_heading    = 'Editar Imobiliária';
        $page_subheading = e($imob['nome'] ?? '');
        $page_back       = ['url' => '/admin/imobiliarias', 'label' => 'Imobiliárias'];
        $extra_css       = ['dashboard.css', 'cotacao.css', 'visitas.css', 'imobiliarias.css'];
        $extra_js        = ['admin-imobiliarias-form.js'];

        return view('cadastros.imobiliarias.editar', get_defined_vars());
    }

    /** GET /admin/imobiliarias/{slug}/usuarios */
    /**
     * Canais de cotação do formulário (painel _canais): canal de cada produto
     * e o código da imobiliária na Segimob (estipulante) — obrigatório quando
     * a Fiança vai pela Segimob.
     */
    private static function lerCanais(array &$valores, array &$erros): void
    {
        $postados = is_array($_POST['canal'] ?? null) ? $_POST['canal'] : [];
        foreach (canais_por_produto() as $produto => $cfg) {
            $canal = (string) ($postados[$produto] ?? CANAL_MANUAL);
            $valores['canais'][$produto] = isset($cfg['canais'][$canal]) ? $canal : CANAL_MANUAL;
        }

        $valores['segimob_estipulante'] = preg_replace('/\D/', '', (string) ($_POST['segimob_estipulante'] ?? ''));
        if (($valores['canais']['fianca'] ?? CANAL_MANUAL) === CANAL_SEGIMOB && $valores['segimob_estipulante'] === '') {
            $erros['segimob_estipulante'] = 'Escolha a imobiliária na Segimob (estipulante) para cotar o Seguro Fiança por lá.';
        }
    }

    /** Grava os canais de cotação e o estipulante da Segimob da imobiliária. */
    private static function salvarCanais(int $imobiliariaId, array $valores): void
    {
        canais_salvar($imobiliariaId, $valores['canais']);
        db_execute(
            'UPDATE imobiliarias SET segimob_estipulante = ? WHERE id = ?',
            ['ii', $valores['segimob_estipulante'] !== '' ? (int) $valores['segimob_estipulante'] : null, $imobiliariaId]
        );
    }

    public function usuarios(string $slug)
    {
        $params = ['slug' => $slug];

        /**
         * GET /admin/imobiliarias/{slug}/usuarios
         * Lista os usuários vinculados a uma imobiliária específica.
         */
        $current_user = require_admin();

        $flash_messages = flash_get();

        $slug_param = trim($params['slug'] ?? '');

        try {
            $imob = $slug_param !== '' ? db_fetch_one('SELECT id, nome FROM imobiliarias WHERE slug = ?', ['s', $slug_param]) : null;
        } catch (Exception $e) {
            $imob = null;
        }

        if (!$imob) ir_para('/admin/imobiliarias');

        $id = (int) $imob['id'];

        try {
            $usuarios = db_fetch_all(
                'SELECT id, username, slug, email, telefone, admin, ativo, last_login_at FROM users WHERE imobiliaria_id = ? ORDER BY username ASC',
                ['i', $id]
            );
        } catch (Exception $e) {
            $usuarios = [];
        }

        $page_title      = 'Usuários — ' . $imob['nome'];
        $page_heading    = 'Usuários — ' . e($imob['nome']);
        $page_subheading = 'Gerencie os usuários vinculados a esta imobiliária';
        $page_back       = ['url' => '/admin/imobiliarias', 'label' => 'Imobiliárias'];
        $extra_css       = ['dashboard.css', 'cotacoes.css'];
        $extra_js        = ['admin-imobiliarias-usuarios.js'];

        $main_attrs = ' data-flash="' . e(json_encode($flash_messages)) . '"';

        return view('cadastros.imobiliarias.usuarios', get_defined_vars());
    }

    /** GET+POST /admin/imobiliarias/{slug}/usuarios/criar */
    public function usuarioCriar(string $slug)
    {
        $params = ['slug' => $slug];

        /**
         * GET+POST /admin/imobiliarias/{slug}/usuarios/criar
         * Cria um novo usuário vinculado a uma imobiliária (visão admin).
         */
        $current_user = require_admin();

        $slug_param = trim($params['slug'] ?? '');
        if ($slug_param === '') ir_para('/admin/imobiliarias');

        $imob = db_fetch_one('SELECT id, nome, slug FROM imobiliarias WHERE slug = ?', ['s', $slug_param]);
        if (!$imob) ir_para('/admin/imobiliarias');

        $imobiliariaId = (int) $imob['id'];

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
            if ($senha === '' || !senha_valida($senha)) $erros['senha'] = 'A senha deve ter no mínimo 6 caracteres, com 1 maiúscula e 1 número.';
            if ($senha !== $senha_confirmar) $erros['senha_confirmar'] = 'As senhas não coincidem.';

            if (empty($erros)) {
                // Mesmo e-mail pode repetir na própria imobiliária, não em outra.
                if (email_usado_em_outra_imobiliaria($valores['email'], $imobiliariaId)) {
                    $erros['email'] = 'Este e-mail já é usado por outra imobiliária.';
                }
            }

            if (empty($erros)) {
                $slug = gerar_slug_usuario($valores['username'], $imobiliariaId);

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
                        $imobiliariaId,
                        $valores['admin'] ? 'S' : 'N',
                        // $current_user aqui é sempre um admin MPS (tabela `usuario`), nunca um
                        // registro de `users` — usar o uid dele quebraria a FK fk_users_criado_por.
                        null,
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
                ir_para('/admin/imobiliarias/' . $slug_param . '/usuarios');
            }
        }

        $page_title      = 'Criar Usuário — ' . $imob['nome'];
        $page_heading    = 'Criar Usuário';
        $page_subheading = e($imob['nome']);
        $page_back       = ['url' => '/admin/imobiliarias/' . $slug_param . '/usuarios', 'label' => 'Usuários'];
        $extra_css       = ['cotacao.css'];
        $extra_js        = ['masks.js', 'auth.js', 'senha-validar.js'];

        return view('cadastros.imobiliarias.usuario-criar', get_defined_vars());
    }

    /** GET+POST /admin/imobiliarias/{slug}/usuarios/{usuario_slug}/editar */
    public function usuarioEditar(string $slug, string $usuario_slug)
    {
        $params = ['slug' => $slug, 'usuario_slug' => $usuario_slug];

        /**
         * GET+POST /admin/imobiliarias/{slug}/usuarios/{usuario_slug}/editar
         * Edita um usuário vinculado a uma imobiliária (visão admin).
         */
        $current_user = require_admin();

        $slug_param   = trim($params['slug'] ?? '');
        $usuario_slug = trim($params['usuario_slug'] ?? '');
        if ($slug_param === '' || $usuario_slug === '') ir_para('/admin/imobiliarias');

        $imob = db_fetch_one('SELECT id, nome, slug FROM imobiliarias WHERE slug = ?', ['s', $slug_param]);
        if (!$imob) ir_para('/admin/imobiliarias');

        $imobiliariaId = (int) $imob['id'];

        $usuario = db_fetch_one(
            'SELECT * FROM users WHERE slug = ? AND imobiliaria_id = ?',
            ['si', $usuario_slug, $imobiliariaId]
        );
        if (!$usuario) ir_para('/admin/imobiliarias/' . $slug_param . '/usuarios');

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
                if (email_usado_em_outra_imobiliaria($valores['email'], $imobiliariaId, $id)) {
                    $erros['email'] = 'Este e-mail já é usado por outra imobiliária.';
                }
            }

            if (empty($erros)) {
                $novo_slug = $valores['username'] !== $usuario['username']
                    ? gerar_slug_usuario($valores['username'], $imobiliariaId, $id)
                    : $usuario['slug'];

                $valores_bd = [
                    $valores['username'], $novo_slug, $valores['email'], $valores['telefone'] ?: null,
                ];
                $tipos_bd = 'ssss';

                if ($senha !== '') {
                    $valores_bd[] = password_hash($senha, PASSWORD_DEFAULT);
                    $tipos_bd    .= 's';
                }

                $valores_bd = array_merge($valores_bd, [
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
                ]);
                $tipos_bd .= 'ssssssssss';

                $valores_bd[] = $id;
                $tipos_bd    .= 'i';

                db_execute(
                    'UPDATE users SET username = ?, slug = ?, email = ?, telefone = ?, ' .
                    ($senha !== '' ? 'password = ?, ' : '') .
                    'admin = ?, perm_criar_usuarios = ?, perm_cotar = ?, perm_suporte = ?, perm_aceitar = ?,
                     perm_fianca = ?, perm_auto = ?, perm_condo = ?, perm_titulo = ?, perm_incendio = ?
                     WHERE id = ?',
                    [$tipos_bd, ...$valores_bd]
                );

                // Seguro Residencial (migration 094) — fora da query acima, que tem a lista fixa de colunas.

                db_execute('UPDATE users SET perm_seguro_residencial = ? WHERE id = ?', ['si', $valores['perm_seguro_residencial'] ? 'S' : 'N', $id]);

                flash('success', 'Usuário "' . $valores['username'] . '" atualizado com sucesso.');
                ir_para('/admin/imobiliarias/' . $slug_param . '/usuarios');
            }
        }

        $page_title      = 'Editar Usuário — ' . $imob['nome'];
        $page_heading    = 'Editar Usuário';
        $page_subheading = e($usuario['username']) . ' — ' . e($imob['nome']);
        $page_back       = ['url' => '/admin/imobiliarias/' . $slug_param . '/usuarios', 'label' => 'Usuários'];
        $extra_css       = ['cotacao.css'];
        $extra_js        = ['masks.js', 'auth.js', 'senha-validar.js'];

        return view('cadastros.imobiliarias.usuario-editar', get_defined_vars());
    }

    /**
     * GET /api/admin/imobiliarias/buscar-institucional?q=...
     * Busca clientes no cadastro institucional da SAMI (somente leitura), pro
     * cadastro de Imobiliárias preencher nome/sistema/endereço automaticamente
     * quando a imobiliária já é cliente Sami.
     * Retorna JSON { itens: [{ sigla, nome, fantasia, sistema, site, cep,
     * endereco, bairro, cidade, uf, fone }] }
     */
    public function buscarInstitucional(Request $request)
    {
        require_admin();

        $q = trim((string) $request->query('q', ''));

        json_response(['itens' => institucional_buscar_clientes($q)]);
    }

    /**
     * POST /api/admin/imobiliarias/testar-samiloca
     * Body JSON: { url? , nome?, site?, api_user?, api_pass? }
     *
     * Valida a URL base da API SAMILOCA de uma imobiliária:
     *   - com `url`  → testa exatamente essa URL;
     *   - sem `url`  → tenta os padrões conhecidos (site próprio do cliente,
     *                  <apelido>.samiloca.com.br e <apelido>.app.samiloca.com.br)
     *                  e devolve a primeira que responder o JSON do widesys.
     *
     * Retorna { ok, url, erro?, tentativas: [{url, erro}] }
     */
    public function testarSamiloca(Request $request)
    {
        require_admin();

        $url     = trim((string) $request->input('url', ''));
        $nome    = trim((string) $request->input('nome', ''));
        $site    = trim((string) $request->input('site', ''));
        $apiUser = trim((string) $request->input('api_user', ''));
        $apiPass = trim((string) $request->input('api_pass', ''));

        // Teste direto de uma URL informada pelo admin.
        if ($url !== '') {
            json_response(samiloca_api_testar($url, 15, $apiUser, $apiPass));
        }

        // Detecção automática: timeout curto por candidato para a tela não travar.
        $candidatas = samiloca_url_candidatas($nome, $site);
        if (!$candidatas) {
            json_response(['ok' => false, 'url' => '', 'erro' => 'Sem dados para deduzir a URL. Informe manualmente.']);
        }

        $tentativas = [];
        foreach ($candidatas as $candidata) {
            $r = samiloca_api_testar($candidata, 8, $apiUser, $apiPass);
            if ($r['ok']) {
                json_response(['ok' => true, 'url' => $r['url'], 'tentativas' => $tentativas]);
            }
            $tentativas[] = ['url' => $candidata, 'erro' => $r['erro'] ?? ''];
        }

        json_response([
            'ok'         => false,
            'url'        => '',
            'erro'       => 'Nenhum dos endereços conhecidos respondeu a API. Informe a URL manualmente.',
            'tentativas' => $tentativas,
        ]);
    }

    /**
     * GET /api/admin/imobiliarias/listar
     * Lista paginada e filtrável das imobiliárias parceiras.
     * Retorna JSON { html, total, page, per_page, total_pages, contadores }
     */
    public function listar(Request $request)
    {
        require_admin();

        $busca  = trim((string) $request->query('busca', ''));
        $status = trim((string) $request->query('status', ''));
        if (!in_array($status, ['ativo', 'inativo'], true)) $status = '';

        $page     = max(1, (int) $request->query('page', 1));
        $per_page = 20;
        $offset   = ($page - 1) * $per_page;

        $colunas_ordenaveis = ['nome', 'cnpj', 'email', 'telefone', 'ativo', 'criado_em'];
        $sort_by  = in_array($request->query('sort_by', ''), $colunas_ordenaveis, true) ? $request->query('sort_by') : 'nome';
        $sort_dir = strtolower((string) $request->query('sort_dir', 'asc')) === 'desc' ? 'DESC' : 'ASC';

        $where  = '1=1';
        $tipos  = '';
        $params = [];

        if ($busca !== '') {
            $where   .= ' AND (nome LIKE ? OR cnpj LIKE ?)';
            $tipos   .= 'ss';
            $params[] = "%{$busca}%";
            $params[] = "%{$busca}%";
        }
        if ($status !== '') {
            $where   .= ' AND ativo = ?';
            $tipos   .= 'i';
            $params[] = $status === 'ativo' ? 1 : 0;
        }

        $imobiliarias = [];
        $total        = 0;
        $contadores   = ['todas' => 0, 'ativas' => 0, 'inativas' => 0];

        try {
            $sql = "SELECT id, nome, slug, cnpj, email, telefone, ativo, criado_em FROM imobiliarias WHERE {$where} ORDER BY {$sort_by} {$sort_dir} LIMIT ? OFFSET ?";
            $imobiliarias = $tipos !== ''
                ? db_fetch_all($sql, [$tipos . 'ii', ...$params, $per_page, $offset])
                : db_fetch_all($sql, ['ii', $per_page, $offset]);

            $sqlCount = "SELECT COUNT(*) AS total FROM imobiliarias WHERE {$where}";
            $total = (int) ($tipos !== ''
                ? (db_fetch_one($sqlCount, [$tipos, ...$params])['total'] ?? 0)
                : (db_fetch_one($sqlCount)['total'] ?? 0));

            $contadores['todas']    = (int) (db_fetch_one('SELECT COUNT(*) AS total FROM imobiliarias')['total'] ?? 0);
            $contadores['ativas']   = (int) (db_fetch_one('SELECT COUNT(*) AS total FROM imobiliarias WHERE ativo = 1')['total'] ?? 0);
            $contadores['inativas'] = (int) (db_fetch_one('SELECT COUNT(*) AS total FROM imobiliarias WHERE ativo = 0')['total'] ?? 0);
        } catch (Throwable $e) {
            error_log('[imobiliarias-listar] ' . $e->getMessage());
        }

        $total_pages = max(1, (int) ceil($total / $per_page));

        $html = view('cadastros._partials.imobiliarias-linhas', compact('imobiliarias', 'busca', 'status'))->render();

        return response()->json([
            'html'        => $html,
            'total'       => $total,
            'page'        => $page,
            'per_page'    => $per_page,
            'total_pages' => $total_pages,
            'contadores'  => $contadores,
        ]);
    }

    /**
     * POST /api/admin/imobiliarias/usuario-toggle-ativo
     * Ativa ou inativa um usuário vinculado a uma imobiliária.
     */
    public function usuarioToggleAtivo(Request $request)
    {
        require_admin();

        $usuarioId     = (int) $request->input('usuario_id', 0);
        $imobiliariaId = (int) $request->input('imobiliaria_id', 0);

        if ($usuarioId <= 0 || $imobiliariaId <= 0) {
            json_response(['ok' => false, 'message' => 'Usuário inválido.'], 422);
        }

        $usuario = db_fetch_one(
            'SELECT id, ativo FROM users WHERE id = ? AND imobiliaria_id = ?',
            ['ii', $usuarioId, $imobiliariaId]
        );

        if (!$usuario) {
            json_response(['ok' => false, 'message' => 'Usuário não encontrado.'], 404);
        }

        $novoAtivo = $usuario['ativo'] ? 0 : 1;

        db_execute('UPDATE users SET ativo = ? WHERE id = ?', ['ii', $novoAtivo, $usuarioId]);

        json_response(['ok' => true, 'ativo' => (bool) $novoAtivo]);
    }
}
