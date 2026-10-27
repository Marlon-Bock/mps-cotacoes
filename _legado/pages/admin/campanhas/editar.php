<?php
/**
 * GET+POST /admin/campanhas/{id}/editar
 * Formulário para editar uma campanha existente.
 */
$current_user = require_admin();

$id = (int) ($params['id'] ?? 0);
if ($id <= 0) {
    redirect('/admin/campanhas');
}

$campanha = db_fetch_one('SELECT * FROM campanhas WHERE id = ?', ['i', $id]);
if (!$campanha) {
    redirect('/admin/campanhas');
}

const CAMPANHA_POPUP_TIPOS = [
    'fianca'      => 'Fiança (Residencial e Comercial)',
    'auto'        => 'Auto',
    'titulo'      => 'Título de Capitalização',
    'condominial' => 'Condominial',
];

$erros   = [];
$valores = [
    'titulo'           => $campanha['titulo'],
    'data_inicio'      => substr((string) $campanha['data_inicio'], 0, 10),
    'data_fim'         => substr((string) $campanha['data_fim'], 0, 10),
    'observacoes'      => (string) ($campanha['observacoes'] ?? ''),
    'status'           => $campanha['status'],
    'popup_ativo'      => (bool) $campanha['popup_ativo'],
    'popup_fianca'     => (bool) ($campanha['popup_residencial'] || $campanha['popup_comercial']),
    'popup_auto'       => (bool) $campanha['popup_auto'],
    'popup_titulo'     => (bool) $campanha['popup_titulo'],
    'popup_condominial' => (bool) $campanha['popup_condominial'],
];
$imob_vinculadas = array_map('intval', array_column(
    db_fetch_all('SELECT imobiliaria_id FROM campanha_imobiliarias WHERE campanha_id = ?', ['i', $id]),
    'imobiliaria_id'
));

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Arquivo acima do post_max_size: corpo (e CSRF) vêm vazios — erro claro.
    $limite_upload_msg = upload_erro_limite();
    if ($limite_upload_msg !== null) {
        $erros['imagem'] = $limite_upload_msg;
    } else {
    csrf_verify();

    $valores['titulo']      = trim($_POST['titulo'] ?? '');
    $valores['data_inicio'] = trim($_POST['data_inicio'] ?? '');
    $valores['data_fim']    = trim($_POST['data_fim'] ?? '');
    $valores['observacoes'] = trim($_POST['observacoes'] ?? '');
    $valores['status']      = in_array($_POST['status'] ?? '', ['ativo', 'inativo'], true) ? $_POST['status'] : 'ativo';
    $valores['popup_ativo'] = ($_POST['popup_ativo'] ?? '') === '1';
    foreach (array_keys(CAMPANHA_POPUP_TIPOS) as $tipo) {
        $valores["popup_{$tipo}"] = ($_POST["popup_{$tipo}"] ?? '') === '1';
    }
    $imob_vinculadas        = array_map('intval', $_POST['imobiliarias'] ?? []);

    if ($valores['titulo'] === '') $erros['titulo'] = 'Título é obrigatório.';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $valores['data_inicio'])) $erros['data_inicio'] = 'Data de início é obrigatória.';
    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $valores['data_fim']))    $erros['data_fim']    = 'Data de fim é obrigatória.';
    if (empty($erros['data_inicio']) && empty($erros['data_fim']) && $valores['data_fim'] < $valores['data_inicio']) {
        $erros['data_fim'] = 'A data de fim não pode ser anterior à data de início.';
    }

    if (empty($erros)) {
        $imagem_url = $campanha['imagem'];
        if (!empty($_FILES['imagem']['name'])) {
            try {
                $imagem_url = media_upload($_FILES['imagem'], 'campanhas');
            } catch (Throwable $e) {
                $erros['imagem'] = 'Erro ao enviar imagem: ' . $e->getMessage();
            }
        }

        if (empty($erros)) {
            db_execute(
                "UPDATE campanhas
                 SET titulo = ?, imagem = ?, status = ?, data_inicio = ?, data_fim = ?, observacoes = ?,
                     popup_ativo = ?, popup_residencial = ?, popup_comercial = ?, popup_auto = ?, popup_titulo = ?, popup_condominial = ?
                 WHERE id = ?",
                ['ssssssiiiiiii',
                    $valores['titulo'],
                    $imagem_url,
                    $valores['status'],
                    $valores['data_inicio'] . ' 00:00:00',
                    $valores['data_fim'] . ' 23:59:59',
                    $valores['observacoes'] !== '' ? $valores['observacoes'] : null,
                    (int) $valores['popup_ativo'],
                    (int) $valores['popup_fianca'],
                    (int) $valores['popup_fianca'],
                    (int) $valores['popup_auto'],
                    (int) $valores['popup_titulo'],
                    (int) $valores['popup_condominial'],
                    $id,
                ]
            );

            db_execute('DELETE FROM campanha_imobiliarias WHERE campanha_id = ?', ['i', $id]);
            foreach ($imob_vinculadas as $imobId) {
                db_execute(
                    'INSERT INTO campanha_imobiliarias (campanha_id, imobiliaria_id) VALUES (?, ?)',
                    ['ii', $id, $imobId]
                );
            }

            flash('success', 'Campanha "' . $valores['titulo'] . '" atualizada com sucesso.');
            redirect('/admin/campanhas');
        }
    }
    }
}

$imobiliarias = [];
try {
    $imobiliarias = db_fetch_all('SELECT id, nome FROM imobiliarias WHERE ativo = 1 ORDER BY nome ASC');
} catch (Throwable $e) {
    $imobiliarias = [];
}

$page_title      = 'Editar Campanha';
$page_heading    = 'Editar Campanha';
$page_back       = ['url' => '/admin/campanhas', 'label' => 'Campanhas'];
$extra_css       = ['dashboard.css', 'cotacao.css', 'admin-campanhas.css'];
$extra_js        = ['campanha-imob-tags.js'];

require BASE_PATH . '/pages/partials/app-head.php';
require BASE_PATH . '/pages/partials/nav-admin.php';
?>
<div class="app-shell">
    <?php require BASE_PATH . '/pages/partials/sidebar.php'; ?>
    <div class="app-main">
        <?php require BASE_PATH . '/pages/partials/topbar.php'; ?>
        <main class="app-content">
            <form
                method="POST"
                action="<?= e(BASE_URI) ?>/admin/campanhas/<?= (int) $id ?>/editar"
                enctype="multipart/form-data"
                novalidate
                id="form-campanha"
                class="cotacao-container form-layout-70-30"
            >
                <input type="hidden" name="_csrf" value="<?= e(csrf_token()) ?>">

                <div class="form-layout-main">

                    <div class="panel">
                        <div class="panel-header">
                            <h2><?= icon('megaphone') ?> Dados da campanha</h2>
                        </div>
                        <div class="panel-body">
                            <div class="form-section">

                                <div class="field">
                                    <label class="field-label required" for="titulo">
                                        Título
                                        <?php if (!empty($erros['titulo'])): ?>
                                            <span class="field-error"><?= e($erros['titulo']) ?></span>
                                        <?php endif; ?>
                                    </label>
                                    <div class="field-input-wrap">
                                        <span class="field-input-icon"><?= icon('megaphone') ?></span>
                                        <input
                                            class="field-input has-icon<?= !empty($erros['titulo']) ? ' is-error' : '' ?>"
                                            type="text"
                                            id="titulo"
                                            name="titulo"
                                            maxlength="150"
                                            value="<?= e($valores['titulo']) ?>"
                                            placeholder="Título da campanha"
                                        >
                                    </div>
                                </div>

                                <div class="form-grid">
                                    <div class="field">
                                        <label class="field-label required" for="data_inicio">
                                            Data de início
                                            <?php if (!empty($erros['data_inicio'])): ?>
                                                <span class="field-error"><?= e($erros['data_inicio']) ?></span>
                                            <?php endif; ?>
                                        </label>
                                        <?= date_picker('data_inicio', 'DD/MM/AAAA', $valores['data_inicio'] ?: null) ?>
                                    </div>
                                    <div class="field">
                                        <label class="field-label required" for="data_fim">
                                            Data de fim
                                            <?php if (!empty($erros['data_fim'])): ?>
                                                <span class="field-error"><?= e($erros['data_fim']) ?></span>
                                            <?php endif; ?>
                                        </label>
                                        <?= date_picker('data_fim', 'DD/MM/AAAA', $valores['data_fim'] ?: null) ?>
                                    </div>
                                </div>

                                <div class="field">
                                    <label class="field-label" for="status">Status</label>
                                    <?= custom_select('status', ['ativo' => 'Ativa', 'inativo' => 'Inativa'], 'Selecione o status', $valores['status']) ?>
                                </div>

                                <div class="field">
                                    <label class="field-label" for="imagem">
                                        Imagem / Banner
                                        <?php if (!empty($erros['imagem'])): ?>
                                            <span class="field-error"><?= e($erros['imagem']) ?></span>
                                        <?php endif; ?>
                                    </label>
                                    <?php if (!empty($campanha['imagem'])): ?>
                                    <div class="campanha-imagem-atual">
                                        <img src="<?= e($campanha['imagem']) ?>" alt="">
                                    </div>
                                    <?php endif; ?>
                                    <div class="field-file-picker" data-file-picker>
                                        <input type="file" id="imagem" name="imagem" accept="image/png,image/jpeg,image/webp" hidden>
                                        <button type="button" class="btn btn-secondary btn-sm" data-file-picker-trigger><?= icon('clipboard-text') ?> Escolher imagem</button>
                                        <span class="field-file-picker-name" data-file-picker-name>Nenhum arquivo selecionado</span>
                                    </div>
                                    <span class="field-hint">PNG, JPEG ou WEBP — até 2MB. Recomendado: proporção retrato 17:24 (a imagem será cortada para preencher esse formato no popup). Deixe em branco para manter a atual.</span>
                                </div>

                                <div class="field">
                                    <label class="field-label" for="observacoes">Observações / detalhes</label>
                                    <textarea
                                        class="field-input"
                                        id="observacoes"
                                        name="observacoes"
                                        rows="4"
                                        placeholder="Observações internas sobre a campanha (não aparece para a imobiliária)"
                                    ><?= e($valores['observacoes']) ?></textarea>
                                </div>

                            </div>
                        </div>
                    </div>

                    <div class="panel">
                        <div class="panel-header">
                            <h2><?= icon('bell') ?> Popup</h2>
                        </div>
                        <div class="panel-body">
                            <div class="form-section">
                                <div class="field">
                                    <label class="switch">
                                        <input type="checkbox" id="popup-ativo" name="popup_ativo" value="1" <?= $valores['popup_ativo'] ? 'checked' : '' ?>>
                                        <span class="switch-track"></span>
                                        <span class="switch-label">Exibir como popup</span>
                                    </label>
                                    <span class="field-hint">Mostra a imagem da campanha automaticamente para a imobiliária ao abrir a tela escolhida abaixo.</span>
                                </div>

                                <div class="form-section no-margin-bottom" id="bloco-popup-tipos" <?= $valores['popup_ativo'] ? '' : 'hidden' ?>>
                                    <?php foreach (CAMPANHA_POPUP_TIPOS as $tipo => $label): ?>
                                    <div class="field">
                                        <label class="switch">
                                            <input type="checkbox" name="popup_<?= e($tipo) ?>" value="1" <?= $valores["popup_{$tipo}"] ? 'checked' : '' ?>>
                                            <span class="switch-track"></span>
                                            <span class="switch-label"><?= e($label) ?></span>
                                        </label>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                </div>

                <div class="form-layout-side">

                    <div class="panel">
                        <div class="panel-header">
                            <h2><?= icon('buildings') ?> Segmentação</h2>
                        </div>
                        <div class="panel-body campanha-imob-segmentacao">

                            <div class="alert alert-info">
                                <span class="alert-icon"><?= icon('info') ?></span>
                                Selecione as imobiliárias que verão esta campanha. Deixe em branco para exibir a todas.
                            </div>

                            <?php if (empty($imobiliarias)): ?>
                                <div class="empty-state">
                                    <div class="empty-state-icon"><?= icon('buildings') ?></div>
                                    <p>Nenhuma imobiliária ativa encontrada.</p>
                                </div>
                            <?php else: ?>
                                <div class="imob-tags" id="campanha-imob-tags" data-selecionadas="<?= e(implode(',', $imob_vinculadas)) ?>">
                                    <div class="imob-tags-chips" id="campanha-imob-chips"></div>
                                    <div class="imob-tags-search">
                                        <div class="field-input-wrap">
                                            <span class="field-input-icon"><?= icon('magnifying-glass') ?></span>
                                            <input type="text" id="campanha-imob-busca" class="field-input has-icon" placeholder="Buscar imobiliária para adicionar..." autocomplete="off">
                                        </div>
                                        <ul class="imob-tags-suggestions" id="campanha-imob-sugestoes" hidden></ul>
                                    </div>
                                </div>
                                <script type="application/json" id="campanha-imob-dados"><?= json_encode(
                                    array_map(static fn($i) => ['id' => (int) $i['id'], 'nome' => $i['nome']], $imobiliarias),
                                    JSON_UNESCAPED_UNICODE
                                ) ?></script>
                            <?php endif; ?>

                        </div>
                    </div>

                    <div class="form-actions form-actions-side">
                        <button type="submit" class="btn btn-primary">
                            <?= icon('paper-plane-tilt') ?>
                            Salvar
                        </button>
                        <a href="<?= e(url('/admin/campanhas')) ?>" class="btn btn-secondary">
                            Cancelar
                        </a>
                    </div>

                </div>

            </form>
        </main>
    </div>
</div>
<script>
(function () {
    var popupAtivo = document.getElementById('popup-ativo');
    var blocoTipos = document.getElementById('bloco-popup-tipos');
    if (popupAtivo && blocoTipos) {
        popupAtivo.addEventListener('change', function () {
            blocoTipos.hidden = !popupAtivo.checked;
        });
    }

})();
</script>
<?php require BASE_PATH . '/pages/partials/app-foot.php'; ?>
