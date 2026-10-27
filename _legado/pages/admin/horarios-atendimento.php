<?php
/**
 * GET /admin/horarios
 * Configura o horário comercial por produto (horarios_atendimento) e os
 * bloqueios temporários (bloqueios_horario, ex: equipe em reunião). Cotações
 * enviadas fora do horário ou durante um bloqueio continuam sendo recebidas
 * normalmente — o sistema só avisa a imobiliária (ver horario_atendimento_verificar()).
 */
$current_user = require_admin();

$tipos_seguro = [
    'todos'                => 'Todos os produtos (padrão)',
    'fianca'               => 'Seguro Fiança',
    'auto'                 => 'Seguro Auto',
    'titulo_capitalizacao' => 'Título de Capitalização',
    'condominial'          => 'Seguro Condominial',
    'incendio'             => 'Seguro Incêndio',
];

$dias_semana_labels = [1 => 'Seg', 2 => 'Ter', 3 => 'Qua', 4 => 'Qui', 5 => 'Sex', 6 => 'Sáb', 0 => 'Dom'];

$horarios = [];
try {
    foreach (db_fetch_all('SELECT * FROM horarios_atendimento') as $h) {
        $horarios[$h['tipo_seguro']] = $h;
    }
} catch (Throwable $e) {}

$bloqueios = [];
try {
    $bloqueios = db_fetch_all('SELECT * FROM bloqueios_horario ORDER BY data_inicio DESC');
} catch (Throwable $e) {}

function horarioFormatarDias(string $diasSemana, array $labels): string
{
    $dias = array_map('intval', explode(',', $diasSemana));
    return implode(', ', array_map(fn($d) => $labels[$d] ?? (string) $d, $dias));
}

$page_title      = 'Horário de Atendimento';
$page_heading    = 'Horário de Atendimento';
$page_subheading = 'Horário comercial e bloqueios temporários por produto';
$page_back       = null;
$extra_css       = ['dashboard.css', 'cotacoes.css', 'admin-horarios-atendimento.css'];
$extra_js        = ['admin-horarios-atendimento.js'];

require BASE_PATH . '/pages/partials/app-head.php';
require BASE_PATH . '/pages/partials/nav-admin.php';
?>
<div class="app-shell">
    <?php require BASE_PATH . '/pages/partials/sidebar.php'; ?>
    <div class="app-main">
        <?php require BASE_PATH . '/pages/partials/topbar.php'; ?>
        <main class="app-content" data-csrf="<?= e(csrf_token()) ?>">

            <div class="alert alert-info">
                <span class="alert-icon"><?= icon('info') ?></span>
                Cotações enviadas fora do horário ou durante um bloqueio continuam sendo recebidas normalmente — o sistema só avisa a imobiliária que a equipe vai responder no próximo horário útil.
            </div>

            <div class="panel">
                <div class="panel-header">
                    <h2>Horário de atendimento por produto</h2>
                </div>
                <div class="admin-cot-grid-wrap">
                    <div class="admin-cot-row admin-cot-row-horarios admin-cot-head">
                        <div class="admin-cot-cell">Produto</div>
                        <div class="admin-cot-cell">Início</div>
                        <div class="admin-cot-cell">Fim</div>
                        <div class="admin-cot-cell">Dias</div>
                        <div class="admin-cot-cell">Ativo</div>
                        <div class="admin-cot-cell">Ações</div>
                    </div>
                    <?php foreach ($tipos_seguro as $tipo => $label):
                        $h = $horarios[$tipo] ?? null;
                    ?>
                    <div class="admin-cot-row admin-cot-row-horarios">
                        <div class="admin-cot-cell admin-cot-cell-nome"><?= e($label) ?></div>
                        <div class="admin-cot-cell"><?= $h ? e(substr($h['hora_inicio'], 0, 5)) : '—' ?></div>
                        <div class="admin-cot-cell"><?= $h ? e(substr($h['hora_fim'], 0, 5)) : '—' ?></div>
                        <div class="admin-cot-cell"><?= $h ? e(horarioFormatarDias($h['dias_semana'], $dias_semana_labels)) : '—' ?></div>
                        <div class="admin-cot-cell">
                            <?php if ($h && $h['ativo'] === 'S'): ?>
                                <span class="status-pill status-pill-aprovado">Sim</span>
                            <?php elseif ($h): ?>
                                <span class="status-pill status-pill-recusado">Não</span>
                            <?php else: ?>
                                <span class="admin-cot-cell-vazio">Não configurado</span>
                            <?php endif; ?>
                        </div>
                        <div class="admin-cot-cell">
                            <button type="button" class="btn btn-secondary btn-sm" data-action="editar-horario"
                                data-tipo="<?= e($tipo) ?>"
                                data-label="<?= e($label) ?>"
                                data-inicio="<?= e($h['hora_inicio'] ?? '08:30') ?>"
                                data-fim="<?= e($h['hora_fim'] ?? '18:00') ?>"
                                data-dias="<?= e($h['dias_semana'] ?? '1,2,3,4,5') ?>"
                                data-ativo="<?= e($h['ativo'] ?? 'S') ?>">
                                <?= icon('note-pencil') ?> Editar
                            </button>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>

            <div class="panel">
                <div class="panel-header">
                    <h2>Bloqueios temporários</h2>
                    <button type="button" class="btn btn-primary btn-sm" id="btn-novo-bloqueio">
                        <?= icon('plus') ?> Novo bloqueio
                    </button>
                </div>
                <div class="panel-body">
                    <?php if (!$bloqueios): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon"><?= icon('clock') ?></div>
                            <p>Nenhum bloqueio cadastrado. Use "Novo bloqueio" pra avisar a imobiliária quando a equipe estiver indisponível (ex: reunião, feriado).</p>
                        </div>
                    <?php else: foreach ($bloqueios as $b):
                        $produtos = array_filter(array_map('trim', explode(',', $b['tipo_seguro'])));
                        $inicio   = date('d/m/Y H:i', strtotime($b['data_inicio']));
                        $fim      = date('d/m/Y H:i', strtotime($b['data_fim']));
                    ?>
                    <div class="panel-list-item">
                        <div class="panel-list-item-info">
                            <strong><?= e($b['titulo']) ?></strong>
                            <span><?= e($inicio) ?> até <?= e($fim) ?> — <?= e(implode(', ', array_map(fn($p) => $tipos_seguro[$p] ?? $p, $produtos))) ?></span>
                        </div>
                        <div class="horario-bloqueio-acoes">
                            <?php if ($b['ativo'] === 'S'): ?>
                                <span class="status-pill status-pill-aprovado">Ativo</span>
                            <?php else: ?>
                                <span class="status-pill status-pill-recusado">Inativo</span>
                            <?php endif; ?>
                            <button type="button" class="btn btn-secondary btn-sm" data-action="editar-bloqueio"
                                data-id="<?= (int) $b['id'] ?>"
                                data-titulo="<?= e($b['titulo']) ?>"
                                data-mensagem="<?= e($b['mensagem']) ?>"
                                data-data-inicio="<?= e(date('Y-m-d', strtotime($b['data_inicio']))) ?>"
                                data-hora-inicio="<?= e(date('H:i', strtotime($b['data_inicio']))) ?>"
                                data-data-fim="<?= e(date('Y-m-d', strtotime($b['data_fim']))) ?>"
                                data-hora-fim="<?= e(date('H:i', strtotime($b['data_fim']))) ?>"
                                data-produtos="<?= e($b['tipo_seguro']) ?>"
                                data-ativo="<?= e($b['ativo']) ?>">
                                <?= icon('note-pencil') ?>
                            </button>
                            <button type="button" class="btn btn-danger-outline btn-sm" data-action="excluir-bloqueio" data-id="<?= (int) $b['id'] ?>">
                                <?= icon('x') ?>
                            </button>
                        </div>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>

        </main>
    </div>
</div>

<!-- Modal: horário de atendimento -->
<div class="modal" id="modal-horario" hidden>
    <div class="modal-backdrop" data-close="modal-horario"></div>
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 id="modal-horario-titulo">Horário de atendimento</h3>
            <button type="button" class="modal-close" data-close="modal-horario"><?= icon('x') ?></button>
        </div>
        <form id="form-horario" class="modal-body">
            <input type="hidden" id="horario-tipo-seguro" value="">
            <div class="field-row">
                <div class="field">
                    <label class="field-label">Início do atendimento</label>
                    <?= time_picker('horario-hora-inicio', '--:--', '08:30') ?>
                </div>
                <div class="field">
                    <label class="field-label">Fim do atendimento</label>
                    <?= time_picker('horario-hora-fim', '--:--', '18:00') ?>
                </div>
            </div>
            <div class="field">
                <label class="field-label">Dias da semana</label>
                <div class="switch-group">
                    <?php foreach ($dias_semana_labels as $num => $label): ?>
                    <label class="switch">
                        <input type="checkbox" name="dias_semana[]" value="<?= $num ?>">
                        <span class="switch-track"></span>
                        <span class="switch-label"><?= e($label) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <label class="switch">
                <input type="checkbox" name="ativo" value="1" checked>
                <span class="switch-track"></span>
                <span class="switch-label">Horário ativo</span>
            </label>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close="modal-horario">Cancelar</button>
                <button type="submit" class="btn btn-primary"><?= icon('check') ?> Salvar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: bloqueio temporário -->
<div class="modal" id="modal-bloqueio" hidden>
    <div class="modal-backdrop" data-close="modal-bloqueio"></div>
    <div class="modal-dialog">
        <div class="modal-header">
            <h3 id="modal-bloqueio-titulo">Novo bloqueio</h3>
            <button type="button" class="modal-close" data-close="modal-bloqueio"><?= icon('x') ?></button>
        </div>
        <form id="form-bloqueio" class="modal-body">
            <input type="hidden" name="id" value="">
            <div class="field">
                <label class="field-label">Título</label>
                <div class="field-input-wrap">
                    <span class="field-input-icon"><?= icon('note-pencil') ?></span>
                    <input class="field-input has-icon" type="text" name="titulo" placeholder="Ex: Equipe em reunião" required>
                </div>
            </div>
            <div class="field">
                <label class="field-label">Mensagem exibida pra imobiliária</label>
                <textarea class="field-input" name="mensagem" rows="3" placeholder="Ex: A equipe de Seguro Fiança está em reunião." required></textarea>
            </div>
            <div class="field-row">
                <div class="field">
                    <label class="field-label">Data de início</label>
                    <?= date_picker('bloqueio-data-inicio') ?>
                </div>
                <div class="field">
                    <label class="field-label">Hora de início</label>
                    <?= time_picker('bloqueio-hora-inicio') ?>
                </div>
            </div>
            <div class="field-row">
                <div class="field">
                    <label class="field-label">Data de fim</label>
                    <?= date_picker('bloqueio-data-fim') ?>
                </div>
                <div class="field">
                    <label class="field-label">Hora de fim</label>
                    <?= time_picker('bloqueio-hora-fim') ?>
                </div>
            </div>
            <div class="field">
                <label class="field-label">Produtos afetados</label>
                <div class="switch-group">
                    <?php foreach ($tipos_seguro as $tipo => $label): if ($tipo === 'todos') continue; ?>
                    <label class="switch">
                        <input type="checkbox" name="tipo_seguro[]" value="<?= e($tipo) ?>">
                        <span class="switch-track"></span>
                        <span class="switch-label"><?= e($label) ?></span>
                    </label>
                    <?php endforeach; ?>
                </div>
            </div>
            <label class="switch">
                <input type="checkbox" name="ativo" value="1" checked>
                <span class="switch-track"></span>
                <span class="switch-label">Bloqueio ativo</span>
            </label>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-close="modal-bloqueio">Cancelar</button>
                <button type="submit" class="btn btn-primary"><?= icon('check') ?> Salvar</button>
            </div>
        </form>
    </div>
</div>

<?php require BASE_PATH . '/pages/partials/app-foot.php'; ?>
