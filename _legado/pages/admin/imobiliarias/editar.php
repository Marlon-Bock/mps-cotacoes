<?php
/**
 * GET+POST /admin/imobiliarias/{slug}/editar
 * Formulário para editar uma imobiliária existente — mesmos blocos do
 * cadastro (cliente Sami / busca institucional, endereço, comissões, região).
 */
$current_user = require_admin();

const IMOB_TIPOS_SEGURO = ['fianca' => 'Fiança', 'cap' => 'Título de Capitalização', 'incendio' => 'Incêndio', 'vida' => 'Vida', 'auto' => 'Auto'];

$slug_param = trim($params['slug'] ?? '');
if ($slug_param === '') {
    redirect('/admin/imobiliarias');
}

try {
    $imob = db_fetch_one('SELECT * FROM imobiliarias WHERE slug = ?', ['s', $slug_param]);
} catch (Throwable $e) {
    $imob = null;
}

if (!$imob) {
    redirect('/admin/imobiliarias');
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
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();

    foreach (array_keys($valores) as $campo) {
        if (in_array($campo, ['eh_cliente_sami', 'ativo', 'usa_embed'], true)) continue;
        $valores[$campo] = trim($_POST[$campo] ?? '');
    }
    $valores['eh_cliente_sami'] = ($_POST['eh_cliente_sami'] ?? '') === '1';
    $valores['ativo']           = ($_POST['ativo'] ?? '') === '1';
    $valores['usa_embed']       = ($_POST['usa_embed'] ?? '') === '1';

    if ($valores['nome'] === '') $erros['nome'] = 'Nome é obrigatório.';
    if ($valores['email'] !== '' && !filter_var($valores['email'], FILTER_VALIDATE_EMAIL)) {
        $erros['email'] = 'Informe um e-mail válido.';
    }

    // URL da API SAMILOCA: guarda sempre normalizada (com https, sem barra final).
    require_once BASE_PATH . '/src/helpers/imovel_erp.php';
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
        require_once BASE_PATH . '/src/core/embed.php';
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
        redirect('/admin/imobiliarias');
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

require BASE_PATH . '/pages/partials/app-head.php';
require BASE_PATH . '/pages/partials/nav-admin.php';
?>
<div class="app-shell">
    <?php require BASE_PATH . '/pages/partials/sidebar.php'; ?>
    <div class="app-main">
        <?php require BASE_PATH . '/pages/partials/topbar.php'; ?>
        <main class="app-content">

            <!-- KPIs -->
            <div class="kpi-grid kpi-grid-4">
                <div class="kpi-card">
                    <div class="kpi-card-info">
                        <span>Total de cotações</span>
                        <div class="kpi-card-value"><?= $total_cotacoes ?></div>
                    </div>
                    <div class="kpi-card-icon is-neutral"><?= icon('clipboard-text') ?></div>
                </div>

                <div class="kpi-card">
                    <div class="kpi-card-info">
                        <span>Usuários cadastrados</span>
                        <div class="kpi-card-value"><?= $total_usuarios ?></div>
                    </div>
                    <div class="kpi-card-icon is-neutral"><?= icon('users') ?></div>
                </div>

                <div class="kpi-card">
                    <div class="kpi-card-info">
                        <span>Status</span>
                        <div class="kpi-card-value">
                            <span class="status-pill <?= $valores['ativo'] ? 'status-pill-aprovado' : 'status-pill-recusado' ?>">
                                <?= $valores['ativo'] ? 'Ativa' : 'Inativa' ?>
                            </span>
                        </div>
                    </div>
                    <div class="kpi-card-icon is-neutral"><?= icon('buildings') ?></div>
                </div>

                <div class="kpi-card">
                    <div class="kpi-card-info">
                        <span>Cadastrada em</span>
                        <div class="kpi-card-value"><?= e($criado_em) ?></div>
                    </div>
                    <div class="kpi-card-icon is-neutral"><?= icon('calendar-blank') ?></div>
                </div>
            </div>

            <!-- Formulário de edição -->
            <form method="POST" action="<?= e(BASE_URI) ?>/admin/imobiliarias/<?= e($slug_param) ?>/editar" novalidate class="cotacao-container form-layout-70-30">
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">

                <div class="form-layout-main">

                    <div class="panel">
                        <div class="panel-header">
                            <h2><?= icon('identification-card') ?> Dados da Imobiliária</h2>
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

                                <div class="field visita-imob-field" id="bloco-busca-institucional" <?= $valores['eh_cliente_sami'] ? '' : 'hidden' ?>>
                                    <label class="field-label">Buscar no sistema institucional</label>
                                    <div class="field-input-wrap">
                                        <span class="field-input-icon"><?= icon('magnifying-glass') ?></span>
                                        <input type="text" id="institucional-busca" class="field-input has-icon" placeholder="Digite o nome da imobiliária..." autocomplete="off">
                                    </div>
                                    <input type="hidden" name="institucional_sigla" id="institucional-sigla" value="<?= e($valores['institucional_sigla']) ?>">
                                    <ul class="visita-imob-sugestoes" id="institucional-sugestoes" hidden></ul>
                                    <div class="imob-institucional-chip" id="institucional-chip" <?= $valores['institucional_sigla'] ? '' : 'hidden' ?>>
                                        <span class="status-pill status-pill-pendente" id="institucional-chip-sistema"><?= e($valores['institucional_sistema']) ?></span>
                                        <span id="institucional-chip-texto"><?= e($valores['nome']) ?> (<?= e($valores['institucional_sigla']) ?>)</span>
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
                                <div class="field" id="bloco-institucional-sistema" <?= $valores['eh_cliente_sami'] ? '' : 'hidden' ?>>
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
                                $mostra_samiloca = $valores['eh_cliente_sami']
                                    && strtoupper((string) $valores['institucional_sistema']) === 'SAMILOCA';
                                ?>
                                <div class="field" id="bloco-samiloca-url" <?= $mostra_samiloca ? '' : 'hidden' ?>>
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
                                    <label class="field-label required" for="nome">Nome da imobiliária</label>
                                    <div class="field-input-wrap">
                                        <span class="field-input-icon"><?= icon('buildings') ?></span>
                                        <input
                                            class="field-input has-icon<?= !empty($erros['nome']) ? ' has-error' : '' ?>"
                                            type="text"
                                            id="nome"
                                            name="nome"
                                            value="<?= e($valores['nome']) ?>"
                                            placeholder="Ex.: Imobiliária Central"
                                            required
                                            maxlength="120"
                                        >
                                    </div>
                                    <span class="field-error"><?= e($erros['nome'] ?? '') ?></span>
                                </div>

                                <div class="form-grid">
                                    <div class="field">
                                        <label class="field-label" for="cnpj">CNPJ</label>
                                        <div class="field-input-wrap">
                                            <span class="field-input-icon"><?= icon('identification-card') ?></span>
                                            <input class="field-input has-icon" type="text" id="cnpj" name="cnpj" data-mask="cpfcnpj" value="<?= e($valores['cnpj']) ?>" placeholder="00.000.000/0000-00" maxlength="18">
                                        </div>
                                    </div>
                                    <div class="field">
                                        <label class="field-label" for="telefone">Telefone</label>
                                        <div class="field-input-wrap">
                                            <span class="field-input-icon"><?= icon('phone') ?></span>
                                            <input class="field-input has-icon" type="text" id="telefone" name="telefone" data-mask="phone" value="<?= e($valores['telefone']) ?>" placeholder="(00) 00000-0000" maxlength="20">
                                        </div>
                                    </div>
                                </div>

                                <div class="field">
                                    <label class="field-label" for="email">E-mail</label>
                                    <div class="field-input-wrap">
                                        <span class="field-input-icon"><?= icon('envelope-simple') ?></span>
                                        <input class="field-input has-icon<?= !empty($erros['email']) ? ' has-error' : '' ?>" type="email" id="email" name="email" value="<?= e($valores['email']) ?>" placeholder="contato@imobiliaria.com.br" maxlength="120">
                                    </div>
                                    <span class="field-error"><?= e($erros['email'] ?? '') ?></span>
                                </div>

                            </div>
                        </div>
                    </div>

                    <!-- Endereço -->
                    <div class="panel">
                        <div class="panel-header">
                            <h2><?= icon('map-pin') ?> Endereço</h2>
                        </div>
                        <div class="panel-body">
                            <div class="form-section">
                                <div class="form-grid">
                                    <div class="field">
                                        <label class="field-label" for="cep">CEP</label>
                                        <div class="field-input-wrap">
                                            <span class="field-input-icon"><?= icon('map-pin') ?></span>
                                            <input class="field-input has-icon" type="text" id="cep" name="cep" data-mask="cep" value="<?= e($valores['cep']) ?>" maxlength="9" placeholder="00000-000">
                                        </div>
                                    </div>
                                    <div class="field">
                                        <label class="field-label" for="endereco">Rua/Avenida</label>
                                        <div class="field-input-wrap">
                                            <span class="field-input-icon"><?= icon('house') ?></span>
                                            <input class="field-input has-icon" type="text" id="endereco" name="endereco" value="<?= e($valores['endereco']) ?>" placeholder="Nome da rua/avenida" maxlength="200">
                                        </div>
                                    </div>
                                </div>
                                <div class="form-grid-3">
                                    <div class="field">
                                        <label class="field-label" for="numero">Número</label>
                                        <div class="field-input-wrap">
                                            <span class="field-input-icon"><?= icon('key') ?></span>
                                            <input class="field-input has-icon" type="text" id="numero" name="numero" value="<?= e($valores['numero']) ?>" placeholder="Nº" maxlength="20">
                                        </div>
                                    </div>
                                    <div class="field">
                                        <label class="field-label" for="complemento">Complemento</label>
                                        <div class="field-input-wrap">
                                            <span class="field-input-icon"><?= icon('note-pencil') ?></span>
                                            <input class="field-input has-icon" type="text" id="complemento" name="complemento" value="<?= e($valores['complemento']) ?>" placeholder="Apto, bloco, etc." maxlength="60">
                                        </div>
                                    </div>
                                    <div class="field">
                                        <label class="field-label" for="bairro">Bairro</label>
                                        <div class="field-input-wrap">
                                            <span class="field-input-icon"><?= icon('buildings') ?></span>
                                            <input class="field-input has-icon" type="text" id="bairro" name="bairro" value="<?= e($valores['bairro']) ?>" placeholder="Nome do bairro" maxlength="100">
                                        </div>
                                    </div>
                                </div>
                                <div class="form-grid">
                                    <div class="field">
                                        <label class="field-label" for="cidade">Cidade</label>
                                        <div class="field-input-wrap">
                                            <span class="field-input-icon"><?= icon('buildings') ?></span>
                                            <input class="field-input has-icon" type="text" id="cidade" name="cidade" value="<?= e($valores['cidade']) ?>" placeholder="Nome da cidade" maxlength="100">
                                        </div>
                                    </div>
                                    <div class="field">
                                        <label class="field-label" for="uf">UF</label>
                                        <div class="field-input-wrap">
                                            <span class="field-input-icon"><?= icon('buildings') ?></span>
                                            <input class="field-input has-icon" type="text" id="uf" name="uf" value="<?= e($valores['uf']) ?>" maxlength="2" placeholder="SP">
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="form-layout-side">

                    <!-- Comissões -->
                    <div class="panel">
                        <div class="panel-header">
                            <h2><?= icon('currency-dollar') ?> Comissões</h2>
                        </div>
                        <div class="panel-body">
                            <div class="imob-comissao-lista">
                                <?php foreach (IMOB_TIPOS_SEGURO as $tipo => $label):
                                    $c = $comissoes_atuais[$tipo];
                                    $isValor = $c['modo'] === 'valor';
                                ?>
                                <div class="imob-comissao-row" data-tipo="<?= e($tipo) ?>">
                                    <label class="switch">
                                        <input type="checkbox" class="imob-seguro-check" name="seguro_<?= e($tipo) ?>" value="1" data-tipo="<?= e($tipo) ?>" <?= $c['ativo'] ? 'checked' : '' ?>>
                                        <span class="switch-track"></span>
                                        <span class="switch-label"><?= e($label) ?></span>
                                    </label>
                                    <div class="imob-comissao-controles" data-tipo="<?= e($tipo) ?>" <?= $c['ativo'] ? '' : 'hidden' ?>>
                                        <div class="visita-comissao-modo">
                                            <label class="field-radio"><input type="radio" name="com_modo_<?= e($tipo) ?>" value="percentual" <?= !$isValor ? 'checked' : '' ?>> %</label>
                                            <label class="field-radio"><input type="radio" name="com_modo_<?= e($tipo) ?>" value="valor" <?= $isValor ? 'checked' : '' ?>> R$</label>
                                        </div>
                                        <div class="field-input-wrap imob-comissao-valor-wrap">
                                            <span class="field-input-prefix" <?= $isValor ? '' : 'hidden' ?>>R$</span>
                                            <input type="text" inputmode="decimal" class="field-input <?= $isValor ? 'has-prefix' : 'has-suffix' ?> imob-comissao-valor" name="com_valor_<?= e($tipo) ?>" data-mask="percentual" placeholder="0,00" value="<?= $c['valor'] ? e(number_format((float) $c['valor'], 2, ',', '')) : '' ?>">
                                            <span class="field-input-suffix" <?= $isValor ? 'hidden' : '' ?>>%</span>
                                        </div>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Região -->
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
                                    <?= custom_select('regiao_id', $opcoes_regiao, 'Selecione...', $valores['regiao_id'] ? (string) $valores['regiao_id'] : null) ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Configurações -->
                    <div class="panel">
                        <div class="panel-header">
                            <h2><?= icon('gear') ?> Configurações</h2>
                        </div>
                        <div class="panel-body">

                            <div class="field">
                                <label class="switch">
                                    <input type="checkbox" name="ativo" value="1" <?= $valores['ativo'] ? 'checked' : '' ?>>
                                    <span class="switch-track"></span>
                                    <span class="switch-label">Imobiliária ativa</span>
                                </label>
                                <span class="field-hint">Imobiliárias inativas não conseguem acessar o sistema nem abrir novas cotações.</span>
                            </div>

                            <div class="field">
                                <a href="<?= e(url('/admin/imobiliarias/' . $slug_param . '/usuarios')) ?>" class="btn btn-secondary btn-sm">
                                    <?= icon('users') ?> Usuários da imobiliária
                                </a>
                            </div>

                            <?php if (FEATURE_EMBED_UI): ?>
                            <div class="field">
                                <label class="switch">
                                    <input type="checkbox" id="usa-embed" name="usa_embed" value="1" <?= $valores['usa_embed'] ? 'checked' : '' ?>>
                                    <span class="switch-track"></span>
                                    <span class="switch-label">Usar embed (cotação sem login)?</span>
                                </label>
                                <span class="field-hint">Libera para a imobiliária um link de <code>&lt;iframe&gt;</code> por tipo de cotação, para embutir no sistema do cliente sem precisar de login.</span>
                            </div>

                            <div class="field" id="bloco-embed-dominio" <?= $valores['usa_embed'] ? '' : 'hidden' ?>>
                                <label class="field-label" for="embed_dominio">Domínio autorizado a embutir</label>
                                <div class="field-input-wrap">
                                    <span class="field-input-icon"><?= icon('monitor') ?></span>
                                    <input class="field-input has-icon" type="text" id="embed_dominio" name="embed_dominio" value="<?= e($valores['embed_dominio']) ?>" placeholder="crm.imobiliaria.com.br" maxlength="255">
                                </div>
                                <span class="field-hint">Domínio do site/sistema do cliente onde o iframe vai rodar (sem "https://").</span>
                            </div>
                            <?php endif; ?>

                        </div>
                    </div>

                    <!-- Ações do formulário -->
                    <div class="form-actions form-actions-side">
                        <button type="submit" class="btn btn-primary">
                            <?= icon('check') ?> Salvar alterações
                        </button>
                        <a href="<?= e(url('/admin/imobiliarias')) ?>" class="btn btn-secondary">
                            <?= icon('x') ?> Cancelar
                        </a>
                    </div>

                </div>

            </form>

        </main>
    </div>
</div>
<?php require BASE_PATH . '/pages/partials/app-foot.php'; ?>
