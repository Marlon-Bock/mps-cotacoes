<?php
/**
 * GET /admin/visitas/relatorio (aceita ?ag_id=N)
 * Relatório diário de visitas — cada atendente registra as imobiliárias que
 * visitou hoje. Lista, salvar, editar e excluir são tudo via AJAX
 * (/api/admin/visitas-relatorio/*), a URL nunca muda.
 * Se veio de um agendamento pendente (?ag_id=N, ver Agendar Visita), o
 * formulário pré-preenche a imobiliária e, ao salvar, marca o agendamento
 * como concluído.
 */
$current_user = require_admin();

$ag_id = (int) ($_GET['ag_id'] ?? 0);
$agendamento = null;
$corretores_extra_default = [];
if ($ag_id > 0) {
    try {
        $agendamento = db_fetch_one(
            "SELECT id, imobiliaria_id, imobiliaria_nome, titulo, status
             FROM agendamentos_visita WHERE id = ? AND para_usuario_id = ?",
            ['ii', $ag_id, (int) $current_user->uid]
        );
        if ($agendamento) {
            $extras = db_fetch_all(
                'SELECT usuario_id FROM agendamentos_visita_corretores WHERE agendamento_id = ?',
                ['i', $ag_id]
            );
            $corretores_extra_default = array_map(static fn($e) => (int) $e['usuario_id'], $extras);
        }
    } catch (Throwable) {
        $agendamento = null;
    }
}

// Corretores que foram junto (multi-seleção) — o corretor principal é
// sempre o usuário logado (implícito via relatorios_visitas.usuario_id),
// esse campo é só pros colegas adicionais. Ver mecânica em
// admin-visitas-agendar.js/#para-usuario-select — aqui não existe conceito
// de "principal", é uma seleção múltipla simples.
$corretores = [];
try {
    $corretores = db_fetch_all('SELECT id, username, avatar FROM usuario WHERE id != ? ORDER BY username ASC', ['i', (int) $current_user->uid]);
} catch (Throwable) {}
$opcoes_corretor_extra = [];
foreach ($corretores as $c) {
    $opcoes_corretor_extra[(string) $c['id']] = $c['username'];
}

$page_title      = 'Relatório de Visitas';
$page_heading    = 'Relatório de Visitas';
$page_subheading = 'Registre as imobiliárias que você visitou hoje.';
$page_back       = null;
$extra_css       = ['dashboard.css', 'cotacoes.css', 'cotacao.css', 'visitas.css'];
$extra_js        = ['admin-visitas-relatorio.js'];

require BASE_PATH . '/pages/partials/app-head.php';
require BASE_PATH . '/pages/partials/nav-admin.php';
?>
<div class="app-shell">
    <?php require BASE_PATH . '/pages/partials/sidebar.php'; ?>
    <div class="app-main">
        <?php require BASE_PATH . '/pages/partials/topbar.php'; ?>
        <main class="app-content">

            <?php if ($agendamento): ?>
            <div class="alert alert-info">
                <span class="alert-icon"><?= icon('calendar-blank') ?></span>
                Completando o agendamento <strong><?= e($agendamento['titulo']) ?></strong><?= $agendamento['imobiliaria_nome'] ? ' — ' . e($agendamento['imobiliaria_nome']) : '' ?>.
                Ao salvar, ele será marcado como concluído.
            </div>
            <?php endif; ?>

            <div class="form-layout-70-30">
                <div class="form-layout-main">
                    <section class="panel">
                        <div class="panel-header">
                            <h2><?= icon('note-pencil') ?> <span id="visita-form-titulo">Nova visita</span></h2>
                        </div>
                        <div class="panel-body">
                            <form id="form-visita" class="cotacao-container" data-csrf="<?= e(csrf_token()) ?>">
                                <input type="hidden" id="f-item-id" value="">
                                <input type="hidden" id="f-ag-id" value="<?= $agendamento ? (int) $agendamento['id'] : '' ?>">

                                <div class="form-section">
                                <div class="field visita-imob-field">
                                    <label class="field-label">Imobiliária <span class="required">*</span></label>
                                    <div class="field-input-wrap">
                                        <span class="field-input-icon"><?= icon('buildings') ?></span>
                                        <input type="text" id="f-imobiliaria-nome" class="field-input has-icon" placeholder="Buscar imobiliária..." autocomplete="off"
                                               value="<?= $agendamento ? e($agendamento['imobiliaria_nome']) : '' ?>">
                                    </div>
                                    <input type="hidden" id="f-imobiliaria-id" value="<?= $agendamento ? (int) $agendamento['imobiliaria_id'] : '' ?>">
                                    <ul class="visita-imob-sugestoes" id="visita-imob-sugestoes" hidden></ul>
                                </div>

                                <div class="form-grid">
                                    <div class="field">
                                        <label class="field-label">Gerente <span class="required">*</span></label>
                                        <div class="field-input-wrap">
                                            <span class="field-input-icon"><?= icon('user') ?></span>
                                            <input type="text" id="f-gerente" class="field-input has-icon" placeholder="Nome do gerente" required maxlength="120">
                                        </div>
                                    </div>
                                    <div class="field">
                                        <label class="field-label">Atendente</label>
                                        <div class="field-input-wrap">
                                            <span class="field-input-icon"><?= icon('headset') ?></span>
                                            <input type="text" id="f-atendente" class="field-input has-icon" placeholder="Nome do atendente" maxlength="120">
                                        </div>
                                    </div>
                                </div>

                                <div class="form-grid">
                                    <div class="field">
                                        <label class="field-label">Horário de entrada <span class="required">*</span></label>
                                        <?= time_picker('f-entrada') ?>
                                    </div>
                                    <div class="field">
                                        <label class="field-label">Horário de saída <span class="required">*</span></label>
                                        <?= time_picker('f-saida') ?>
                                    </div>
                                </div>

                                <div class="form-grid">
                                    <div class="field">
                                        <label class="field-label">Motivo da visita <span class="required">*</span></label>
                                        <?= custom_select('motivo_visita', [
                                            'Prospecção'          => 'Prospecção',
                                            'Acompanhamento'      => 'Acompanhamento',
                                            'Entrega de material' => 'Entrega de material',
                                            'Reunião'             => 'Reunião',
                                            'Outro'               => 'Outro',
                                        ], 'Selecione...') ?>
                                    </div>
                                    <div class="field">
                                        <label class="field-label">Detalhe do motivo</label>
                                        <div class="field-input-wrap">
                                            <span class="field-input-icon"><?= icon('note-pencil') ?></span>
                                            <input type="text" id="f-motivo-descricao" class="field-input has-icon" placeholder="Detalhe opcional" maxlength="255">
                                        </div>
                                    </div>
                                </div>

                                <div class="field">
                                    <label class="field-label">Corretores que foram junto</label>
                                    <div class="field-input-wrap">
                                        <span class="field-input-icon"><?= icon('headset') ?></span>
                                        <?php
                                        $wrapperAttrsExtra = [
                                            'id'              => 'corretores-extra-select',
                                            'data-multi'      => '1',
                                            'data-selecionados' => implode(',', $corretores_extra_default),
                                        ];
                                        echo custom_select('corretores_extra', $opcoes_corretor_extra, 'Selecione os corretores...', null, 'has-icon', false, $wrapperAttrsExtra);
                                        ?>
                                    </div>
                                </div>

                                <div class="field">
                                    <label class="field-label">Seguros trabalhados na visita</label>
                                    <div class="visita-seguros-grid" id="visita-seguros-grid">
                                        <?php foreach (['fianca' => 'Fiança', 'cap' => 'Título de Capitalização', 'incendio' => 'Incêndio', 'vida' => 'Vida', 'auto' => 'Auto'] as $tipo => $label): ?>
                                        <div class="visita-seguro-item">
                                            <label class="switch">
                                                <input type="checkbox" class="visita-seguro-check" data-tipo="<?= e($tipo) ?>">
                                                <span class="switch-track"></span>
                                                <span class="switch-label"><?= e($label) ?></span>
                                            </label>
                                            <div class="visita-comissao-wrap" data-tipo="<?= e($tipo) ?>" hidden>
                                                <div class="visita-comissao-modo">
                                                    <label class="field-radio"><input type="radio" name="modo-<?= e($tipo) ?>" value="percentual" checked> %</label>
                                                    <label class="field-radio"><input type="radio" name="modo-<?= e($tipo) ?>" value="valor"> R$</label>
                                                </div>
                                                <div class="field-input-wrap visita-comissao-valor-wrap">
                                                    <span class="field-input-prefix" hidden>R$</span>
                                                    <input type="text" inputmode="decimal" class="field-input has-suffix visita-comissao-valor" data-tipo="<?= e($tipo) ?>" data-mask="percentual" placeholder="0,00">
                                                    <span class="field-input-suffix">%</span>
                                                </div>
                                            </div>
                                        </div>
                                        <?php endforeach; ?>
                                    </div>
                                    <div class="field visita-outros-seguros">
                                        <label class="field-label">Outros seguros</label>
                                        <div class="field-input-wrap">
                                            <span class="field-input-icon"><?= icon('clipboard-text') ?></span>
                                            <input type="text" id="f-outros-seguros" class="field-input has-icon" placeholder="Ex: Residencial, Empresarial..." maxlength="180">
                                        </div>
                                    </div>
                                </div>

                                <div class="field">
                                    <label class="switch">
                                        <input type="checkbox" id="f-falta-material">
                                        <span class="switch-track"></span>
                                        <span class="switch-label">Faltou material nesta visita</span>
                                    </label>
                                    <div class="field visita-materiais-wrap" id="f-materiais-wrap" hidden>
                                        <label class="field-label">Quais materiais faltaram?</label>
                                        <div class="field-input-wrap">
                                            <span class="field-input-icon"><?= icon('warning') ?></span>
                                            <input type="text" id="f-materiais-faltantes" class="field-input has-icon" placeholder="Ex: Folder, cartão de visita..." maxlength="255">
                                        </div>
                                    </div>
                                </div>

                                <div class="field">
                                    <label class="field-label">Observações</label>
                                    <textarea id="f-observacoes" class="field-input" placeholder="Alguma observação sobre a visita..." rows="3" maxlength="5000"></textarea>
                                </div>
                                </div>

                                <div class="visita-form-actions">
                                    <button type="submit" class="btn btn-primary" id="btn-salvar-visita">
                                        <?= icon('check') ?> <span id="btn-salvar-visita-label">Salvar visita</span>
                                    </button>
                                    <button type="button" class="btn btn-secondary" id="btn-limpar-visita">Limpar</button>
                                </div>
                            </form>
                        </div>
                    </section>
                </div>

                <div class="panel cot-panel">
                    <div class="panel-header">
                        <h2>Visitas de hoje <span class="badge badge-primary" id="visitas-hoje-badge" hidden>0</span></h2>
                    </div>
                    <div id="visitas-hoje-lista">
                        <div class="panel-body">
                            <div class="empty-state">
                                <div class="empty-state-icon"><?= icon('clipboard-text') ?></div>
                                <p>Carregando...</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </main>
    </div>
</div>

<!-- Corretores (id/username/avatar) — para o multi-select "Corretores que foram junto" -->
<script type="application/json" id="relatorio-usuarios-json"><?= json_encode(
    array_map(static fn($c) => [
        'id'       => (int) $c['id'],
        'username' => $c['username'],
        'avatar'   => $c['avatar'] ?: null,
    ], $corretores),
    JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT
) ?></script>
<?php require BASE_PATH . '/pages/partials/app-foot.php'; ?>
