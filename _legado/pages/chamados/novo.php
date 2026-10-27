<?php
/**
 * GET+POST /chamados/novo
 * Formulário para abrir um novo chamado de suporte.
 */
$current_user = require_perm('perm_suporte');

// Precisa bater exatamente com o enum de chamados_suporte.categoria no banco.
$categorias = [
    'seguro_fianca_residencial' => 'Seguro Fiança Residencial',
    'seguro_fianca_comercial'   => 'Seguro Fiança Comercial',
    'seguro_auto'               => 'Seguro Auto',
    'sinistro'                  => 'Sinistro',
    'duvidas_gerais'            => 'Dúvidas gerais',
    'outros'                    => 'Outros',
];

$erros    = [];
$valores  = [
    'telefone'  => '',
    'email'     => $current_user->email ?? '',
    'assunto'   => '',
    'titulo'    => '',
    'categoria' => '',
    'mensagem'  => '',
];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Anexo acima do post_max_size: corpo (e CSRF) vêm vazios — mostra o erro real.
    $limite_upload_msg = upload_erro_limite();
    if ($limite_upload_msg !== null) {
        $erros['anexo'] = $limite_upload_msg;
    } else {
    csrf_verify();

    $valores['telefone']  = trim($_POST['telefone'] ?? '');
    $valores['email']     = trim($_POST['email'] ?? '');
    $valores['assunto']   = trim($_POST['assunto'] ?? '');
    $valores['titulo']    = trim($_POST['titulo'] ?? '');
    $valores['categoria'] = trim($_POST['categoria'] ?? '');
    $valores['mensagem']  = trim($_POST['mensagem'] ?? '');

    if ($valores['telefone'] === '')  $erros['telefone']  = 'Telefone é obrigatório.';
    if ($valores['email'] === '' || !filter_var($valores['email'], FILTER_VALIDATE_EMAIL))
        $erros['email'] = 'Informe um e-mail válido.';
    if ($valores['assunto'] === '')   $erros['assunto']   = 'Assunto é obrigatório.';
    if ($valores['titulo'] === '')    $erros['titulo']    = 'Título é obrigatório.';
    if (!array_key_exists($valores['categoria'], $categorias))
        $erros['categoria'] = 'Selecione uma categoria.';
    if ($valores['mensagem'] === '')  $erros['mensagem']  = 'Mensagem é obrigatória.';

    // Upload de anexo (opcional)
    $arquivo_nome = null;
    $arquivo_path = null;
    $arquivo_mime = null;
    $arquivo_tam  = null;

    if (!empty($_FILES['anexo']['name'])) {
        try {
            $url          = media_upload($_FILES['anexo'], 'chamados');
            $arquivo_nome = $_FILES['anexo']['name'];
            $arquivo_path = $url;
            $arquivo_mime = $_FILES['anexo']['type'];
            $arquivo_tam  = (int) $_FILES['anexo']['size'];
        } catch (\Throwable $e) {
            $erros['anexo'] = 'Falha no upload: ' . $e->getMessage();
        }
    }

    if (empty($erros)) {
        $id_chamado = db_execute(
            "INSERT INTO chamados_suporte
                (imobiliaria_id, usuario_id, telefone, email, assunto, categoria, titulo, mensagem,
                 arquivo_nome, arquivo_path, arquivo_mime, arquivo_tamanho, status, criado_em, atualizado_em)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'aguardando_mps', NOW(), NOW())",
            ['iisssssssssi',
                $current_user->imobiliaria_id,
                $current_user->uid,
                $valores['telefone'],
                $valores['email'],
                $valores['assunto'],
                $valores['categoria'],
                $valores['titulo'],
                $valores['mensagem'],
                $arquivo_nome,
                $arquivo_path,
                $arquivo_mime,
                $arquivo_tam,
            ]
        );

        // Avisa a equipe MPS por e-mail (quem optou por receber avisos de suporte).
        notificar_chamado_email(
            $valores['assunto'],
            $valores['titulo'],
            $valores['categoria'],
            (int) $current_user->imobiliaria_id
        );

        redirect('/chamados?novo=' . $id_chamado);
    }
    }
}

$page_title      = 'Novo Chamado';
$page_heading    = 'Abrir Chamado';
$page_subheading = null;
$page_back       = ['url' => '/chamados', 'label' => 'Meus Chamados'];
$extra_css       = ['dashboard.css', 'cotacao.css', 'chamados.css'];
$extra_js        = [];

require BASE_PATH . '/pages/partials/app-head.php';
require BASE_PATH . '/pages/partials/nav-imobiliaria.php';
?>
<div class="app-shell">
    <?php require BASE_PATH . '/pages/partials/sidebar.php'; ?>
    <div class="app-main">
        <?php require BASE_PATH . '/pages/partials/topbar.php'; ?>
        <main class="app-content">

            <div class="cotacao-container">
                <div class="panel">
                    <div class="panel-body">
                        <form id="form-novo-chamado" method="POST" action="<?= e(url('/chamados/novo')) ?>"
                              enctype="multipart/form-data" novalidate>
                            <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">

                            <div class="form-section">
                                <h3 class="form-section-title"><?= icon('headset') ?> Detalhes do chamado</h3>
                                <div class="form-grid">

                                    <!-- Telefone | E-mail -->
                                    <div class="field">
                                        <label class="field-label" for="telefone">
                                            Telefone <span class="required">*</span>
                                            <?php if (!empty($erros['telefone'])): ?>
                                                <span class="field-error"><?= e($erros['telefone']) ?></span>
                                            <?php endif; ?>
                                        </label>
                                        <div class="field-input-wrap">
                                            <span class="field-input-icon"><?= icon('phone') ?></span>
                                            <input class="field-input has-icon<?= !empty($erros['telefone']) ? ' is-error' : '' ?>"
                                                   type="text" id="telefone" name="telefone"
                                                   data-mask="phone"
                                                   placeholder="(00) 00000-0000"
                                                   value="<?= e($valores['telefone']) ?>"
                                                   maxlength="16" required>
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
                                            <input class="field-input has-icon<?= !empty($erros['email']) ? ' is-error' : '' ?>"
                                                   type="email" id="email" name="email"
                                                   placeholder="seu@email.com"
                                                   value="<?= e($valores['email']) ?>"
                                                   maxlength="120" required>
                                        </div>
                                    </div>

                                    <!-- Assunto | Categoria -->
                                    <div class="field">
                                        <label class="field-label" for="assunto">
                                            Assunto <span class="required">*</span>
                                            <?php if (!empty($erros['assunto'])): ?>
                                                <span class="field-error"><?= e($erros['assunto']) ?></span>
                                            <?php endif; ?>
                                        </label>
                                        <div class="field-input-wrap">
                                            <span class="field-input-icon"><?= icon('magnifying-glass') ?></span>
                                            <input class="field-input has-icon<?= !empty($erros['assunto']) ? ' is-error' : '' ?>"
                                                   type="text" id="assunto" name="assunto"
                                                   placeholder="Ex: Dúvida sobre cotação"
                                                   value="<?= e($valores['assunto']) ?>"
                                                   maxlength="150" required>
                                        </div>
                                    </div>

                                    <div class="field">
                                        <label class="field-label" for="categoria">
                                            Categoria <span class="required">*</span>
                                            <?php if (!empty($erros['categoria'])): ?>
                                                <span class="field-error"><?= e($erros['categoria']) ?></span>
                                            <?php endif; ?>
                                        </label>
                                        <div class="field-input-wrap">
                                            <span class="field-input-icon"><?= icon('clipboard-text') ?></span>
                                            <?= custom_select('categoria', $categorias, 'Selecione uma categoria',
                                                $valores['categoria'], 'has-icon' . (!empty($erros['categoria']) ? ' is-error' : ''), true) ?>
                                        </div>
                                    </div>

                                    <!-- Título (full width) -->
                                    <div class="field span-full">
                                        <label class="field-label" for="titulo">
                                            Título <span class="required">*</span>
                                            <?php if (!empty($erros['titulo'])): ?>
                                                <span class="field-error"><?= e($erros['titulo']) ?></span>
                                            <?php endif; ?>
                                        </label>
                                        <div class="field-input-wrap">
                                            <span class="field-input-icon"><?= icon('note-pencil') ?></span>
                                            <input class="field-input has-icon<?= !empty($erros['titulo']) ? ' is-error' : '' ?>"
                                                   type="text" id="titulo" name="titulo"
                                                   placeholder="Ex: Erro ao anexar documentos na cotação"
                                                   value="<?= e($valores['titulo']) ?>"
                                                   maxlength="200" required>
                                        </div>
                                    </div>

                                    <!-- Mensagem (full width) -->
                                    <div class="field span-full">
                                        <label class="field-label" for="mensagem">
                                            Mensagem <span class="required">*</span>
                                            <?php if (!empty($erros['mensagem'])): ?>
                                                <span class="field-error"><?= e($erros['mensagem']) ?></span>
                                            <?php endif; ?>
                                        </label>
                                        <div class="field-input-wrap">
                                            <textarea class="field-input chamado-textarea<?= !empty($erros['mensagem']) ? ' is-error' : '' ?>"
                                                      id="mensagem" name="mensagem"
                                                      placeholder="Descreva detalhadamente sua dúvida ou problema..."
                                                      required><?= e($valores['mensagem']) ?></textarea>
                                        </div>
                                    </div>

                                    <!-- Anexo (full width) -->
                                    <div class="field span-full">
                                        <label class="field-label">
                                            Anexo <span class="field-hint-inline">(opcional)</span>
                                            <?php if (!empty($erros['anexo'])): ?>
                                                <span class="field-error"><?= e($erros['anexo']) ?></span>
                                            <?php endif; ?>
                                        </label>
                                        <div class="field-file-wrap">
                                            <button type="button" class="btn btn-secondary btn-sm" id="btn-anexo">
                                                <?= icon('paperclip') ?> Escolher arquivo
                                            </button>
                                            <span class="field-file-name" id="anexo-nome">Nenhum arquivo escolhido</span>
                                            <input type="file" id="anexo" name="anexo"
                                                   accept=".pdf,.jpg,.jpeg,.png" hidden>
                                        </div>
                                        <span class="field-hint">PDF ou imagem — até 2MB</span>
                                    </div>

                                </div>
                            </div>

                            <div class="stepper-nav">
                                <div></div>
                                <div class="stepper-nav-right">
                                    <button type="submit" class="btn btn-sm btn-primary">
                                        <?= icon('paper-plane-tilt') ?> Abrir chamado
                                    </button>
                                    <a href="<?= e(url('/chamados')) ?>" class="btn btn-sm btn-secondary">Cancelar</a>
                                </div>
                            </div>

                        </form>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>

<script>
(function () {
    const btn  = document.getElementById('btn-anexo');
    const inp  = document.getElementById('anexo');
    const nome = document.getElementById('anexo-nome');
    if (btn && inp) {
        btn.addEventListener('click', () => inp.click());
        inp.addEventListener('change', () => {
            nome.textContent = inp.files[0] ? inp.files[0].name : 'Nenhum arquivo escolhido';
        });
    }

    // Máscara de telefone simples
    const tel = document.getElementById('telefone');
    if (tel) {
        tel.addEventListener('input', function () {
            let d = tel.value.replace(/\D/g, '').slice(0, 11);
            if (d.length === 0) { tel.value = ''; return; }
            if (d.length <= 2)  { tel.value = '(' + d; return; }
            if (d.length <= 6)  { tel.value = '(' + d.slice(0,2) + ') ' + d.slice(2); return; }
            if (d.length <= 10) { tel.value = '(' + d.slice(0,2) + ') ' + d.slice(2,6) + '-' + d.slice(6); return; }
            tel.value = '(' + d.slice(0,2) + ') ' + d.slice(2,7) + '-' + d.slice(7);
        });
    }
}());
</script>
<?php require BASE_PATH . '/pages/partials/app-foot.php'; ?>
