<?php
/**
 * GET+POST /admin/imobiliarias/criar
 * Formulário para cadastrar nova imobiliária parceira.
 * Se for cliente Sami, busca no cadastro institucional (somente leitura)
 * pra puxar nome/sistema/endereço automaticamente; senão pede o endereço
 * na mão. Também define as comissões por tipo de seguro e a região.
 */
$current_user = require_admin();

const IMOB_TIPOS_SEGURO = ['fianca' => 'Fiança', 'cap' => 'Título de Capitalização', 'incendio' => 'Incêndio', 'vida' => 'Vida', 'auto' => 'Auto'];

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
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    foreach (array_keys($valores) as $campo) {
        if (in_array($campo, ['eh_cliente_sami', 'usa_embed'], true)) continue;
        $valores[$campo] = trim($_POST[$campo] ?? '');
    }
    $valores['eh_cliente_sami'] = ($_POST['eh_cliente_sami'] ?? '') === '1';
    $valores['usa_embed']       = ($_POST['usa_embed'] ?? '') === '1';

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
        $existente = db_fetch_one('SELECT id FROM users WHERE email = ?', ['s', $valores['admin_email']]);
        if ($existente) $erros['admin_email'] = 'Já existe um usuário com este e-mail.';
    }

    // URL da API SAMILOCA: guarda sempre normalizada (com https, sem barra final).
    require_once BASE_PATH . '/src/helpers/imovel_erp.php';
    $valores['institucional_site'] = samiloca_url_normalizar($valores['institucional_site']);

    if (empty($erros)) {
        $sistema       = $valores['eh_cliente_sami'] ? ($valores['institucional_sistema'] ?: null) : null;
        $sigla_erp     = $valores['eh_cliente_sami'] ? ($valores['institucional_sigla'] ?: null) : null;
        $site_sami     = $valores['eh_cliente_sami'] ? ($valores['institucional_site'] ?: null) : null;
        $samiloca_user = $valores['eh_cliente_sami'] ? ($valores['samiloca_api_user'] ?: null) : null;
        $samiloca_pass = $valores['eh_cliente_sami'] ? ($valores['samiloca_api_pass'] ?: null) : null;

        $slug = gerar_slug_imobiliaria($valores['nome']);

        require_once BASE_PATH . '/src/core/embed.php';
        $embed_token = $valores['usa_embed'] ? embed_gerar_token() : null;

        $valores_bd = [
            $valores['nome'], $slug, $valores['cnpj'] ?: null, $valores['email'] ?: null, $valores['telefone'] ?: null,
            $valores['cep'] ?: null, $valores['endereco'] ?: null, $valores['numero'] ?: null, $valores['complemento'] ?: null,
            $valores['bairro'] ?: null, $valores['cidade'] ?: null, $valores['uf'] ?: null,
            $sistema, $sigla_erp, $site_sami, $samiloca_user, $samiloca_pass, $valores['eh_cliente_sami'] ? 1 : 0,
            $valores['regiao_id'] ? (int) $valores['regiao_id'] : null,
            $valores['usa_embed'] ? 1 : 0, $embed_token, $valores['embed_dominio'] ?: null,
        ];

        $conn = db();
        $conn->begin_transaction();

        try {
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

            $conn->commit();
        } catch (Throwable $e) {
            $conn->rollback();
            error_log('[imobiliarias/criar] Falha ao salvar imobiliária: ' . $e->getMessage());
            $erros['geral'] = 'Não foi possível salvar a imobiliária. Tente novamente ou contate o suporte.';
        }

        if (empty($erros)) {
            flash('success', 'Imobiliária "' . $valores['nome'] . '" criada com sucesso.');
            redirect('/admin/imobiliarias');
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

require BASE_PATH . '/pages/partials/app-head.php';
require BASE_PATH . '/pages/partials/nav-admin.php';
?>
<div class="app-shell">
    <?php require BASE_PATH . '/pages/partials/sidebar.php'; ?>
    <div class="app-main">
        <?php require BASE_PATH . '/pages/partials/topbar.php'; ?>
        <main class="app-content" data-erro-geral="<?= !empty($erros) ? e(!empty($erros['geral']) ? $erros['geral'] : 'Corrija os campos destacados abaixo para continuar.') : '' ?>">

            <form
                method="POST"
                action="<?= e(url('/admin/imobiliarias/criar')) ?>"
                novalidate
                id="form-criar-imobiliaria"
                class="cotacao-container form-layout-70-30"
            >
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">

                <div class="form-layout-main">

                    <!-- Seção: Dados da imobiliária -->
                    <div class="panel">
                        <div class="panel-header">
                            <h2><?= icon('buildings') ?> Dados da imobiliária</h2>
                        </div>
                        <div class="panel-body">
                            <div class="form-section">

                                <div class="field">
                                    <label class="switch">
                                        <input type="checkbox" id="eh-cliente-sami" name="eh_cliente_sami" value="1" <?= $valores['eh_cliente_sami'] ? 'checked' : '' ?>>
                                        <span class="switch-track"></span>
                                        <span class="switch-label">É cliente Sami?</span>
                                    </label>
                                </div>

                                <div class="field visita-imob-field" id="bloco-busca-institucional" hidden>
                                    <label class="field-label">Buscar no sistema institucional</label>
                                    <div class="field-input-wrap">
                                        <span class="field-input-icon"><?= icon('magnifying-glass') ?></span>
                                        <input type="text" id="institucional-busca" class="field-input has-icon" placeholder="Digite o nome da imobiliária..." autocomplete="off">
                                    </div>
                                    <input type="hidden" name="institucional_sigla" id="institucional-sigla" value="<?= e($valores['institucional_sigla']) ?>">
                                    <ul class="visita-imob-sugestoes" id="institucional-sugestoes" hidden></ul>
                                    <div class="imob-institucional-chip" id="institucional-chip" hidden>
                                        <span class="status-pill status-pill-pendente" id="institucional-chip-sistema"></span>
                                        <span id="institucional-chip-texto"></span>
                                        <button type="button" class="imob-institucional-chip-limpar" id="institucional-chip-limpar" title="Limpar seleção"><?= icon('x') ?></button>
                                    </div>
                                </div>

                                <?php
                                /* Sistema: só existem esses 2 na prática pra integração de imóvel por
                                   código (ERP direto ou Samiloca). Vem sozinho da busca institucional,
                                   mas o admin pode trocar manualmente aqui — caso do cliente Sami cujo
                                   cadastro institucional está como ERP++ mas que na prática usa o site
                                   Samiloca (ou vice-versa). */
                                $sistema_eh_samiloca = strtoupper((string) $valores['institucional_sistema']) === 'SAMILOCA';
                                ?>
                                <div class="field" id="bloco-institucional-sistema" hidden>
                                    <label class="field-label">Sistema</label>
                                    <div class="imob-sistema-toggle">
                                        <span class="imob-sistema-toggle-label<?= $sistema_eh_samiloca ? '' : ' is-active' ?>" data-sistema-label="erp">SAMI ERP</span>
                                        <label class="switch">
                                            <input type="checkbox" id="institucional-sistema-toggle" <?= $sistema_eh_samiloca ? 'checked' : '' ?>>
                                            <span class="switch-track"></span>
                                        </label>
                                        <span class="imob-sistema-toggle-label<?= $sistema_eh_samiloca ? ' is-active' : '' ?>" data-sistema-label="samiloca">SAMILOCA</span>
                                    </div>
                                    <input type="hidden" name="institucional_sistema" id="institucional-sistema" value="<?= e($valores['institucional_sistema'] ?: 'ERP++') ?>">
                                    <span class="field-hint">Preenchido automaticamente pela busca acima — troque aqui se o cliente usa outro sistema na prática.</span>
                                </div>

                                <?php
                                /* URL da API SAMILOCA: só aparece para clientes desse sistema. Muda
                                   conforme quem fez o site (domínio próprio ou subdomínio da Sami) e
                                   não existe no cadastro institucional, por isso é informada aqui. */
                                ?>
                                <div class="field" id="bloco-samiloca-url" hidden>
                                    <label class="field-label" for="institucional-site">URL do site (API SAMILOCA)</label>
                                    <div class="imob-samiloca-linha">
                                        <div class="field-input-wrap imob-samiloca-input">
                                            <span class="field-input-icon"><?= icon('monitor') ?></span>
                                            <input type="text" class="field-input has-icon" id="institucional-site" name="institucional_site"
                                                   value="<?= e($valores['institucional_site']) ?>"
                                                   placeholder="https://imobiliaria.samiloca.com.br" autocomplete="off">
                                        </div>
                                        <button type="button" class="btn btn-sm btn-secondary" id="samiloca-detectar"><?= icon('magnifying-glass') ?> Detectar</button>
                                        <button type="button" class="btn btn-sm btn-secondary" id="samiloca-testar"><?= icon('check') ?> Testar</button>
                                    </div>
                                    <span class="field-hint imob-samiloca-status" id="samiloca-status">Usada na busca de imóvel por código na cotação de fiança.</span>

                                    <div class="form-grid" style="margin-top:var(--space-3)">
                                        <div class="field">
                                            <label class="field-label" for="samiloca-api-user">Usuário da API</label>
                                            <div class="field-input-wrap">
                                                <span class="field-input-icon"><?= icon('user') ?></span>
                                                <input type="text" class="field-input has-icon" id="samiloca-api-user" name="samiloca_api_user"
                                                       value="<?= e($valores['samiloca_api_user']) ?>"
                                                       placeholder="api@imobiliaria.com.br" autocomplete="off">
                                            </div>
                                        </div>
                                        <div class="field">
                                            <label class="field-label" for="samiloca-api-pass">Senha da API</label>
                                            <div class="field-input-wrap">
                                                <span class="field-input-icon"><?= icon('lock') ?></span>
                                                <input type="text" class="field-input has-icon" id="samiloca-api-pass" name="samiloca_api_pass"
                                                       value="<?= e($valores['samiloca_api_pass']) ?>"
                                                       placeholder="senha da API" autocomplete="off">
                                            </div>
                                        </div>
                                    </div>
                                    <span class="field-hint">Cada cliente Samiloca tem usuário/senha próprios de API — se não preencher, o sistema usa o par global do .env (nem sempre funciona pra esse cliente).</span>
                                </div>

                                <div class="field">
                                    <label class="field-label required" for="nome">
                                        Nome <span class="required">*</span>
                                        <?php if (!empty($erros['nome'])): ?>
                                            <span class="field-error"><?= e($erros['nome']) ?></span>
                                        <?php endif; ?>
                                    </label>
                                    <div class="field-input-wrap">
                                        <span class="field-input-icon"><?= icon('buildings') ?></span>
                                        <input
                                            class="field-input has-icon<?= !empty($erros['nome']) ? ' is-error' : '' ?>"
                                            type="text"
                                            id="nome"
                                            name="nome"
                                            value="<?= e($valores['nome']) ?>"
                                            placeholder="Ex.: Imobiliária Central"
                                            maxlength="200"
                                            required
                                            autocomplete="organization"
                                        >
                                    </div>
                                </div>

                                <div class="form-grid">
                                    <div class="field">
                                        <label class="field-label" for="cnpj">
                                            CNPJ <span class="required">*</span>
                                            <?php if (!empty($erros['cnpj'])): ?>
                                                <span class="field-error"><?= e($erros['cnpj']) ?></span>
                                            <?php endif; ?>
                                        </label>
                                        <div class="field-input-wrap">
                                            <span class="field-input-icon"><?= icon('identification-card') ?></span>
                                            <input class="field-input has-icon<?= !empty($erros['cnpj']) ? ' is-error' : '' ?>" type="text" id="cnpj" name="cnpj" data-mask="cpfcnpj" value="<?= e($valores['cnpj']) ?>" placeholder="00.000.000/0000-00" maxlength="18" required autocomplete="off">
                                        </div>
                                    </div>
                                    <div class="field">
                                        <label class="field-label" for="telefone">
                                            Telefone <span class="required">*</span>
                                            <?php if (!empty($erros['telefone'])): ?>
                                                <span class="field-error"><?= e($erros['telefone']) ?></span>
                                            <?php endif; ?>
                                        </label>
                                        <div class="field-input-wrap">
                                            <span class="field-input-icon"><?= icon('phone') ?></span>
                                            <input class="field-input has-icon<?= !empty($erros['telefone']) ? ' is-error' : '' ?>" type="text" id="telefone" name="telefone" data-mask="phone" value="<?= e($valores['telefone']) ?>" placeholder="(00) 00000-0000" maxlength="20" required autocomplete="tel">
                                        </div>
                                    </div>
                                </div>

                                <div class="field">
                                    <label class="field-label" for="email">
                                        E-mail <span class="required">*</span>
                                        <?php if (!empty($erros['email'])): ?>
                                            <span class="field-error"><?= e($erros['email']) ?></span>
                                        <?php endif; ?>
                                    </label>
                                    <div class="field-input-wrap">
                                        <span class="field-input-icon"><?= icon('envelope-simple') ?></span>
                                        <input class="field-input has-icon<?= !empty($erros['email']) ? ' is-error' : '' ?>" type="email" id="email" name="email" value="<?= e($valores['email']) ?>" placeholder="contato@imobiliaria.com.br" required autocomplete="email">
                                    </div>
                                </div>

                            </div>
                        </div>
                    </div>

                    <!-- Seção: Endereço -->
                    <div class="panel">
                        <div class="panel-header">
                            <h2><?= icon('map-pin') ?> Endereço</h2>
                        </div>
                        <div class="panel-body">
                            <div class="form-section">
                                <div class="form-grid">
                                    <div class="field">
                                        <label class="field-label" for="cep">
                                            CEP <span class="required">*</span>
                                            <?php if (!empty($erros['cep'])): ?>
                                                <span class="field-error"><?= e($erros['cep']) ?></span>
                                            <?php endif; ?>
                                        </label>
                                        <div class="field-input-wrap">
                                            <span class="field-input-icon"><?= icon('map-pin') ?></span>
                                            <input class="field-input has-icon<?= !empty($erros['cep']) ? ' is-error' : '' ?>" type="text" id="cep" name="cep" data-mask="cep" value="<?= e($valores['cep']) ?>" maxlength="9" placeholder="00000-000" required autocomplete="off">
                                        </div>
                                    </div>
                                    <div class="field">
                                        <label class="field-label" for="endereco">
                                            Rua/Avenida <span class="required">*</span>
                                            <?php if (!empty($erros['endereco'])): ?>
                                                <span class="field-error"><?= e($erros['endereco']) ?></span>
                                            <?php endif; ?>
                                        </label>
                                        <div class="field-input-wrap">
                                            <span class="field-input-icon"><?= icon('house') ?></span>
                                            <input class="field-input has-icon<?= !empty($erros['endereco']) ? ' is-error' : '' ?>" type="text" id="endereco" name="endereco" value="<?= e($valores['endereco']) ?>" placeholder="Nome da rua/avenida" maxlength="200" required autocomplete="off">
                                        </div>
                                    </div>
                                </div>
                                <div class="form-grid-3">
                                    <div class="field">
                                        <label class="field-label" for="numero">
                                            Número <span class="required">*</span>
                                            <?php if (!empty($erros['numero'])): ?>
                                                <span class="field-error"><?= e($erros['numero']) ?></span>
                                            <?php endif; ?>
                                        </label>
                                        <div class="field-input-wrap">
                                            <span class="field-input-icon"><?= icon('key') ?></span>
                                            <input class="field-input has-icon<?= !empty($erros['numero']) ? ' is-error' : '' ?>" type="text" id="numero" name="numero" value="<?= e($valores['numero']) ?>" placeholder="Nº" maxlength="20" required autocomplete="off">
                                        </div>
                                    </div>
                                    <div class="field">
                                        <label class="field-label" for="complemento">Complemento</label>
                                        <div class="field-input-wrap">
                                            <span class="field-input-icon"><?= icon('note-pencil') ?></span>
                                            <input class="field-input has-icon" type="text" id="complemento" name="complemento" value="<?= e($valores['complemento']) ?>" placeholder="Apto, bloco, etc." maxlength="60" autocomplete="off">
                                        </div>
                                    </div>
                                    <div class="field">
                                        <label class="field-label" for="bairro">
                                            Bairro <span class="required">*</span>
                                            <?php if (!empty($erros['bairro'])): ?>
                                                <span class="field-error"><?= e($erros['bairro']) ?></span>
                                            <?php endif; ?>
                                        </label>
                                        <div class="field-input-wrap">
                                            <span class="field-input-icon"><?= icon('buildings') ?></span>
                                            <input class="field-input has-icon<?= !empty($erros['bairro']) ? ' is-error' : '' ?>" type="text" id="bairro" name="bairro" value="<?= e($valores['bairro']) ?>" placeholder="Nome do bairro" maxlength="100" required autocomplete="off">
                                        </div>
                                    </div>
                                </div>
                                <div class="form-grid">
                                    <div class="field">
                                        <label class="field-label" for="cidade">
                                            Cidade <span class="required">*</span>
                                            <?php if (!empty($erros['cidade'])): ?>
                                                <span class="field-error"><?= e($erros['cidade']) ?></span>
                                            <?php endif; ?>
                                        </label>
                                        <div class="field-input-wrap">
                                            <span class="field-input-icon"><?= icon('buildings') ?></span>
                                            <input class="field-input has-icon<?= !empty($erros['cidade']) ? ' is-error' : '' ?>" type="text" id="cidade" name="cidade" value="<?= e($valores['cidade']) ?>" placeholder="Nome da cidade" maxlength="100" required autocomplete="off">
                                        </div>
                                    </div>
                                    <div class="field">
                                        <label class="field-label" for="uf">
                                            UF <span class="required">*</span>
                                            <?php if (!empty($erros['uf'])): ?>
                                                <span class="field-error"><?= e($erros['uf']) ?></span>
                                            <?php endif; ?>
                                        </label>
                                        <div class="field-input-wrap">
                                            <span class="field-input-icon"><?= icon('buildings') ?></span>
                                            <input class="field-input has-icon<?= !empty($erros['uf']) ? ' is-error' : '' ?>" type="text" id="uf" name="uf" value="<?= e($valores['uf']) ?>" maxlength="2" placeholder="SP" required autocomplete="off">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Seção: Usuário administrador -->
                    <div class="panel">
                        <div class="panel-header">
                            <h2><?= icon('user') ?> Usuário administrador</h2>
                        </div>
                        <div class="panel-body">
                            <div class="form-section no-margin-bottom" id="bloco-admin-usuario">
                                <div class="form-grid">
                                    <div class="field">
                                        <label class="field-label" for="admin_username">
                                            Nome <span class="required">*</span>
                                            <?php if (!empty($erros['admin_username'])): ?>
                                                <span class="field-error"><?= e($erros['admin_username']) ?></span>
                                            <?php endif; ?>
                                        </label>
                                        <div class="field-input-wrap">
                                            <span class="field-input-icon"><?= icon('user') ?></span>
                                            <input class="field-input has-icon<?= !empty($erros['admin_username']) ? ' is-error' : '' ?>"
                                                   type="text" id="admin_username" name="admin_username"
                                                   placeholder="Nome completo" value="<?= e($valores['admin_username']) ?>" maxlength="100">
                                        </div>
                                    </div>
                                    <div class="field">
                                        <label class="field-label" for="admin_email">
                                            E-mail <span class="required">*</span>
                                            <?php if (!empty($erros['admin_email'])): ?>
                                                <span class="field-error"><?= e($erros['admin_email']) ?></span>
                                            <?php endif; ?>
                                        </label>
                                        <div class="field-input-wrap">
                                            <span class="field-input-icon"><?= icon('envelope-simple') ?></span>
                                            <input class="field-input has-icon<?= !empty($erros['admin_email']) ? ' is-error' : '' ?>"
                                                   type="email" id="admin_email" name="admin_email"
                                                   placeholder="email@exemplo.com" value="<?= e($valores['admin_email']) ?>" maxlength="255">
                                        </div>
                                    </div>
                                </div>
                                <div class="field span-full">
                                    <label class="field-label" for="admin_telefone">
                                        Telefone <span class="required">*</span>
                                        <?php if (!empty($erros['admin_telefone'])): ?>
                                            <span class="field-error"><?= e($erros['admin_telefone']) ?></span>
                                        <?php endif; ?>
                                    </label>
                                    <div class="field-input-wrap">
                                        <span class="field-input-icon"><?= icon('phone') ?></span>
                                        <input class="field-input has-icon<?= !empty($erros['admin_telefone']) ? ' is-error' : '' ?>" type="text" id="admin_telefone" name="admin_telefone"
                                               data-mask="phone" placeholder="(00) 00000-0000" value="<?= e($valores['admin_telefone']) ?>" maxlength="16" required>
                                    </div>
                                </div>
                                <div class="form-grid">
                                    <div class="field">
                                        <label class="field-label" for="admin_senha">
                                            Senha <span class="required">*</span>
                                            <?php if (!empty($erros['admin_senha'])): ?>
                                                <span class="field-error"><?= e($erros['admin_senha']) ?></span>
                                            <?php endif; ?>
                                        </label>
                                        <div class="field-input-wrap">
                                            <span class="field-input-icon"><?= icon('lock') ?></span>
                                            <input class="field-input has-icon has-icon-both<?= !empty($erros['admin_senha']) ? ' is-error' : '' ?>"
                                                   type="password" id="admin_senha" name="admin_senha"
                                                   minlength="6" autocomplete="new-password" placeholder="••••••••">
                                        <button type="button" class="field-password-toggle" data-toggle="password" data-target="#admin_senha" aria-label="Mostrar senha">
                                            <?= icon('eye', 'icon-eye') ?>
                                            <?= icon('eye-slash', 'icon-eye-slash') ?>
                                        </button>
                                        </div>
                                    </div>
                                    <div class="field">
                                        <label class="field-label" for="admin_senha_confirmar">
                                            Confirmar senha <span class="required">*</span>
                                            <?php if (!empty($erros['admin_senha_confirmar'])): ?>
                                                <span class="field-error"><?= e($erros['admin_senha_confirmar']) ?></span>
                                            <?php endif; ?>
                                        </label>
                                        <div class="field-input-wrap">
                                            <span class="field-input-icon"><?= icon('lock') ?></span>
                                            <input class="field-input has-icon has-icon-both<?= !empty($erros['admin_senha_confirmar']) ? ' is-error' : '' ?>"
                                                   type="password" id="admin_senha_confirmar" name="admin_senha_confirmar"
                                                   autocomplete="new-password" placeholder="••••••••">
                                        <button type="button" class="field-password-toggle" data-toggle="password" data-target="#admin_senha_confirmar" aria-label="Mostrar confirmação de senha">
                                            <?= icon('eye', 'icon-eye') ?>
                                            <?= icon('eye-slash', 'icon-eye-slash') ?>
                                        </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="form-layout-side">

                    <!-- Seção: Comissões -->
                    <div class="panel">
                        <div class="panel-header">
                            <h2><?= icon('currency-dollar') ?> Comissões</h2>
                        </div>
                        <div class="panel-body">
                            <p class="field-hint">Habilite os seguros que essa imobiliária trabalha e defina a comissão de cada um.</p>
                            <div class="imob-comissao-lista">
                                <?php foreach (IMOB_TIPOS_SEGURO as $tipo => $label): ?>
                                <div class="imob-comissao-row" data-tipo="<?= e($tipo) ?>">
                                    <label class="switch">
                                        <input type="checkbox" class="imob-seguro-check" name="seguro_<?= e($tipo) ?>" value="1" data-tipo="<?= e($tipo) ?>">
                                        <span class="switch-track"></span>
                                        <span class="switch-label"><?= e($label) ?></span>
                                    </label>
                                    <div class="imob-comissao-controles" data-tipo="<?= e($tipo) ?>" hidden>
                                        <div class="visita-comissao-modo">
                                            <label class="field-radio"><input type="radio" name="com_modo_<?= e($tipo) ?>" value="percentual" checked> %</label>
                                            <label class="field-radio"><input type="radio" name="com_modo_<?= e($tipo) ?>" value="valor"> R$</label>
                                        </div>
                                        <div class="field-input-wrap imob-comissao-valor-wrap">
                                            <span class="field-input-prefix" hidden>R$</span>
                                            <input type="text" inputmode="decimal" class="field-input has-suffix imob-comissao-valor" name="com_valor_<?= e($tipo) ?>" data-mask="percentual" placeholder="0,00">
                                            <span class="field-input-suffix">%</span>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Seção: Região -->
                    <div class="panel">
                        <div class="panel-header">
                            <h2><?= icon('map-pin') ?> Região</h2>
                        </div>
                        <div class="panel-body">
                            <div class="form-section">
                                <div class="field">
                                    <label class="field-label">
                                        Região de atendimento
                                        <button type="button" class="visita-regiao-cadastrar-btn" id="btn-regiao-cadastrar" title="Gerenciar tipos de região">
                                            <?= icon('plus') ?>
                                        </button>
                                    </label>
                                    <?= custom_select('regiao_id', $opcoes_regiao, 'Selecione...', $valores['regiao_id'] ?: null) ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <?php if (FEATURE_EMBED_UI): ?>
                    <!-- Configurações -->
                    <div class="panel">
                        <div class="panel-header">
                            <h2><?= icon('gear') ?> Configurações</h2>
                        </div>
                        <div class="panel-body">

                            <div class="field">
                                <label class="switch">
                                    <input type="checkbox" id="usa-embed" name="usa_embed" value="1" <?= $valores['usa_embed'] ? 'checked' : '' ?>>
                                    <span class="switch-track"></span>
                                    <span class="switch-label">Usar embed (cotação sem login)?</span>
                                </label>
                                <span class="field-hint">Libera para a imobiliária um link de <code>&lt;iframe&gt;</code> por tipo de cotação, para embutir no sistema do cliente sem precisar de login.</span>
                            </div>

                            <div class="field" id="bloco-embed-dominio" hidden>
                                <label class="field-label" for="embed_dominio">Domínio autorizado a embutir</label>
                                <div class="field-input-wrap">
                                    <span class="field-input-icon"><?= icon('monitor') ?></span>
                                    <input class="field-input has-icon" type="text" id="embed_dominio" name="embed_dominio" value="<?= e($valores['embed_dominio']) ?>" placeholder="crm.imobiliaria.com.br" maxlength="255">
                                </div>
                                <span class="field-hint">Domínio do site/sistema do cliente onde o iframe vai rodar (sem "https://").</span>
                            </div>

                        </div>
                    </div>
                    <?php endif; ?>

                    <!-- Botões de ação -->
                    <div class="form-actions form-actions-side">
                        <button type="submit" class="btn btn-primary">
                            <?= icon('check') ?>
                            Salvar
                        </button>
                        <a href="<?= e(url('/admin/imobiliarias')) ?>" class="btn btn-secondary">
                            Cancelar
                        </a>
                    </div>

                </div>

            </form>

        </main>
    </div>
</div>
<?php require BASE_PATH . '/pages/partials/app-foot.php'; ?>
