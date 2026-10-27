<?php
/**
 * GET /admin/visitas/agendar
 * Agenda de visitas futuras, com sincronização de 2 vias com o Google
 * Calendar do atendente-alvo. Lista, salvar, editar e excluir são tudo via
 * AJAX (/api/admin/visitas-agendar/*), a URL nunca muda.
 */
$current_user = require_admin();
$is_mps_admin  = !empty($current_user->mps_admin);

$flash_messages   = flash_get();
$google_conectado = google_has_token((int) $current_user->uid);

$corretores = [];
try {
    $corretores = db_fetch_all('SELECT id, username, avatar FROM usuario ORDER BY username ASC');
} catch (Throwable) {}
$opcoes_corretor = [];
foreach ($corretores as $c) {
    $opcoes_corretor[(string) $c['id']] = $c['username'];
}

$regioes = [];
try {
    $regioes = db_fetch_all('SELECT id, nome, requer_link FROM regioes ORDER BY nome ASC');
} catch (Throwable) {}
$opcoes_regiao = [];
$regioes_requer_link = [];
foreach ($regioes as $r) {
    $opcoes_regiao[(string) $r['id']] = $r['nome'];
    $regioes_requer_link[(string) $r['id']] = (bool) $r['requer_link'];
}

$page_title      = 'Agendar Visita';
$page_heading    = 'Agendar Visita';
$page_subheading = 'Agenda de visitas futuras, sincronizada com o Google Calendar.';
$page_back       = null;
$extra_css       = ['dashboard.css', 'cotacoes.css', 'cotacao.css', 'visitas.css', 'tarefas.css'];
$extra_js        = ['admin-visitas-agendar.js'];

require BASE_PATH . '/pages/partials/app-head.php';
require BASE_PATH . '/pages/partials/nav-admin.php';
?>
<div class="app-shell">
    <?php require BASE_PATH . '/pages/partials/sidebar.php'; ?>
    <div class="app-main">
        <?php require BASE_PATH . '/pages/partials/topbar.php'; ?>
        <main class="app-content" data-mps-admin="<?= $is_mps_admin ? '1' : '0' ?>" data-uid="<?= (int) $current_user->uid ?>">

            <?php foreach ($flash_messages as $msg): ?>
            <div class="alert alert-<?= e($msg['type'] === 'error' ? 'danger' : $msg['type']) ?>">
                <span class="alert-icon"><?= icon($msg['type'] === 'success' ? 'check-circle' : 'warning') ?></span>
                <?= e($msg['message']) ?>
            </div>
            <?php endforeach; ?>

            <section class="panel visita-google-card">
                <div class="panel-body visita-google-card-body">
                    <div class="visita-google-status">
                        <span class="visita-google-dot <?= $google_conectado ? 'is-conectado' : '' ?>"></span>
                        <div>
                            <strong>Google Agenda</strong>
                            <span><?= $google_conectado ? 'Conectado — seus agendamentos sincronizam automaticamente.' : 'Não conectado — os agendamentos ficam só na agenda interna.' ?></span>
                        </div>
                    </div>
                    <?php if ($google_conectado): ?>
                        <button type="button" class="btn btn-danger-outline btn-sm" id="btn-google-desconectar">Desconectar</button>
                    <?php else: ?>
                        <a href="<?= e(url('/admin/visitas/google/conectar')) ?>" class="btn btn-primary btn-sm"><?= icon('calendar-blank') ?> Conectar Google</a>
                    <?php endif; ?>
                </div>
            </section>

            <div class="admin-cot-filterbar">
                <div class="admin-cot-toolbar">
                    <div class="status-kpi-grid">
                        <a href="#" class="status-kpi-card is-active" data-status="">
                            <span class="status-kpi-icon"><?= icon('list') ?></span>
                            <span class="status-kpi-value" id="count-todos">0</span>
                            <span class="status-kpi-label">Todos</span>
                        </a>
                        <a href="#" class="status-kpi-card" data-status="pendente">
                            <span class="status-kpi-icon"><?= icon('clock') ?></span>
                            <span class="status-kpi-value" id="count-pendente">0</span>
                            <span class="status-kpi-label">Pendentes</span>
                        </a>
                        <a href="#" class="status-kpi-card" data-status="concluida">
                            <span class="status-kpi-icon"><?= icon('check-circle') ?></span>
                            <span class="status-kpi-value" id="count-concluida">0</span>
                            <span class="status-kpi-label">Concluídas</span>
                        </a>
                    </div>
                    <div class="admin-cot-toolbar-actions">
                        <button type="button" class="btn btn-primary btn-sm" data-open="modal-agendamento" id="btn-novo-agendamento">
                            <?= icon('plus') ?> Novo Agendamento
                        </button>
                    </div>
                </div>

                <div class="cotacoes-filtros admin-filtros cotacao-container">
                    <div class="field">
                        <label class="field-label">Data</label>
                        <?= date_picker('f-ag-data') ?>
                    </div>
                    <?php if ($is_mps_admin): ?>
                    <div class="field">
                        <label class="field-label">Corretor</label>
                        <div class="field-input-wrap">
                            <span class="field-input-icon"><?= icon('headset') ?></span>
                            <?= custom_select('ag_corretor_id', $opcoes_corretor, 'Todos os corretores', null, 'has-icon') ?>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="panel cot-panel">
                <div id="agendar-lista">
                    <div class="admin-cot-empty">Carregando...</div>
                </div>
            </div>

        </main>
    </div>
</div>

<!-- ═══ Modal: Novo/Editar Agendamento ═══ -->
<div class="modal" id="modal-agendamento" hidden>
    <div class="modal-backdrop" data-close="modal-agendamento"></div>
    <div class="modal-dialog visita-modal-dialog">
        <div class="modal-header">
            <h3 id="modal-agendamento-titulo">Novo agendamento</h3>
            <button type="button" class="modal-close" data-close="modal-agendamento"><?= icon('x') ?></button>
        </div>
        <form id="form-agendamento" class="modal-body cotacao-container" data-csrf="<?= e(csrf_token()) ?>">
            <input type="hidden" id="ag-id" value="">

            <div class="form-section">
            <div class="field">
                <label class="field-label">Agendar para <span class="required">*</span></label>
                <div class="field-input-wrap">
                    <span class="field-input-icon"><?= icon('headset') ?></span>
                    <?php
                    // Multi-seleção: o primeiro marcado (ordem de clique) é o corretor
                    // principal (para_usuario_id — dono do agendamento, permissão/e-mail/
                    // relatório continuam baseados nele); os demais viram corretores_extra.
                    // Não-admin sempre tem o próprio usuário como principal fixo (não
                    // removível), mas pode adicionar colegas do mesmo jeito.
                    $wrapperAttrs = ['id' => 'para-usuario-select', 'data-multi' => '1'];
                    if (!$is_mps_admin) $wrapperAttrs['data-principal-fixo'] = (string) $current_user->uid;
                    echo custom_select('para_usuario_id', $opcoes_corretor, 'Selecione o corretor...', (string) $current_user->uid, 'has-icon', false, $wrapperAttrs);
                    ?>
                </div>
            </div>

            <div class="field">
                <label class="field-label">Título <span class="required">*</span></label>
                <div class="field-input-wrap">
                    <span class="field-input-icon"><?= icon('note-pencil') ?></span>
                    <input type="text" id="ag-titulo" class="field-input has-icon" placeholder="Ex: Visita comercial" maxlength="200" required>
                </div>
            </div>

            <div class="form-grid">
                <div class="field">
                    <label class="field-label">
                        Região
                        <button type="button" class="visita-regiao-cadastrar-btn" id="btn-regiao-cadastrar" title="Gerenciar tipos de região">
                            <?= icon('plus') ?>
                        </button>
                    </label>
                    <?= custom_select('regiao_id', $opcoes_regiao, 'Selecione...') ?>
                </div>
                <div class="field visita-imob-field">
                    <label class="field-label">Imobiliária</label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon"><?= icon('buildings') ?></span>
                        <input type="text" id="ag-imobiliaria-nome" class="field-input has-icon" placeholder="Buscar ou digitar imobiliária..." autocomplete="off">
                    </div>
                    <input type="hidden" id="ag-imobiliaria-id" value="">
                    <ul class="visita-imob-sugestoes" id="ag-imobiliaria-sugestoes" hidden></ul>
                </div>
            </div>

            <div class="field" id="ag-link-reuniao-wrap" hidden>
                <label class="field-label">Link da reunião <span class="required">*</span></label>
                <div class="field-input-wrap">
                    <span class="field-input-icon"><?= icon('monitor') ?></span>
                    <input type="url" id="ag-link-reuniao" class="field-input has-icon" placeholder="https://meet.google.com/...">
                </div>
            </div>

            <div class="form-grid-3">
                <div class="field">
                    <label class="field-label">Data <span class="required">*</span></label>
                    <?= date_picker('ag-data') ?>
                </div>
                <div class="field">
                    <label class="field-label">Hora início <span class="required">*</span></label>
                    <?= time_picker('ag-hora-inicio', '--:--', '09:00') ?>
                </div>
                <div class="field">
                    <label class="field-label">Hora fim <span class="required">*</span></label>
                    <?= time_picker('ag-hora-fim', '--:--', '10:00') ?>
                </div>
            </div>

            <div class="form-grid">
                <div class="field">
                    <label class="field-label">CEP</label>
                    <div class="field-input-wrap">
                        <span class="field-input-icon"><?= icon('map-pin') ?></span>
                        <input type="text" name="ag_cep" class="field-input has-icon" placeholder="00000-000" maxlength="9">
                    </div>
                </div>
                <div class="field">
                    <label class="field-label">Rua/Avenida</label>
                    <div class="field-input-wrap">
                        <input type="text" name="ag_endereco" class="field-input" placeholder="Nome da rua/avenida" maxlength="200">
                    </div>
                </div>
            </div>
            <div class="form-grid">
                <div class="field">
                    <label class="field-label">Número</label>
                    <input type="text" name="ag_numero" class="field-input" placeholder="Nº" maxlength="20">
                </div>
                <div class="field">
                    <label class="field-label">Complemento</label>
                    <input type="text" name="ag_complemento" class="field-input" placeholder="Apto, bloco, etc." maxlength="60">
                </div>
                <div class="field">
                    <label class="field-label">Bairro</label>
                    <input type="text" name="ag_bairro" class="field-input" placeholder="Nome do bairro" maxlength="100">
                </div>
            </div>
            <div class="form-grid">
                <div class="field">
                    <label class="field-label">Cidade</label>
                    <input type="text" name="ag_cidade" class="field-input" placeholder="Nome da cidade" maxlength="100">
                </div>
                <div class="field">
                    <label class="field-label">UF</label>
                    <input type="text" name="ag_uf" class="field-input" maxlength="2" placeholder="SP">
                </div>
            </div>

            <div class="field">
                <label class="field-label">Descrição</label>
                <textarea id="ag-descricao" class="field-input" placeholder="Alguma observação sobre o agendamento..." rows="3" maxlength="2000"></textarea>
            </div>
            </div>
        </form>

        <div class="modal-footer">
            <button type="button" class="btn btn-secondary" data-close="modal-agendamento">Cancelar</button>
            <button type="submit" form="form-agendamento" class="btn btn-primary" id="btn-salvar-agendamento">
                <?= icon('check') ?> Salvar
            </button>
        </div>
    </div>
</div>

<!-- Corretores (id/username/avatar) — autocomplete de corretores adicionais -->
<script type="application/json" id="visita-usuarios-json"><?= json_encode(
    array_map(static fn($c) => [
        'id'       => (int) $c['id'],
        'username' => $c['username'],
        'avatar'   => $c['avatar'] ?: null,
    ], $corretores),
    JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP
) ?></script>

<script>
    window.AGENDAR_REGIOES = <?= json_encode(array_map(
        fn($r) => ['id' => (string) $r['id'], 'nome' => $r['nome'], 'requer_link' => (bool) $r['requer_link']],
        $regioes
    ), JSON_UNESCAPED_UNICODE) ?>;
</script>

<?php require BASE_PATH . '/pages/partials/app-foot.php'; ?>
