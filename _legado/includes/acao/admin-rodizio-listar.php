<?php
/**
 * AJAX endpoint — GET /api/admin/rodizio/listar
 * Renderiza os painéis dos funis de rodízio (Fiança, Auto, Título de
 * Capitalização, Seguro Condomínio), um por baixo do outro em largura
 * cheia — mesmo padrão visual de "Rascunhos"/"Aguardando Liberação"
 * (admin-cot-row/admin-cot-cell) — com a lista de atendentes de cada um.
 * Chamado no carregamento da página e depois de qualquer ação (adicionar/
 * remover/reordenar/status), sem navegar nem alterar a URL de /admin/rodizio.
 * Retorna JSON { html }
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');

$funis = [];
try {
    $funis = db_fetch_all('SELECT id, nome, tipo_seguro, proximo_idx, ativo FROM funis ORDER BY id ASC');
    foreach ($funis as &$funil) {
        try {
            $funil['corretores'] = db_fetch_all(
                'SELECT fc.id, fc.user_id, fc.ordem, fc.ativo, u.username, u.email, u.avatar
                 FROM funil_corretores fc
                 JOIN usuario u ON u.id = fc.user_id
                 WHERE fc.funil_id = ? ORDER BY fc.ordem ASC',
                ['i', $funil['id']]
            );
        } catch (Throwable) {
            $funil['corretores'] = [];
        }
    }
    unset($funil);
} catch (Throwable) {
    $funis = [];
}

$todos_usuarios = [];
try {
    $todos_usuarios = db_fetch_all('SELECT id, username FROM usuario ORDER BY username ASC');
} catch (Throwable) {}

$icone_por_tipo = [
    'fianca'      => 'house',
    'auto'        => 'car',
    'titulo'      => 'piggy-bank',
    'condominial' => 'buildings',
    'incendio'    => 'fire',
    'residencial' => 'house-line',
];

ob_start();

if (empty($funis)): ?>
<div class="panel">
    <div class="panel-body">
        <div class="empty-state">
            <div class="empty-state-icon"><?= icon('arrows-clockwise') ?></div>
            <p>Nenhum funil de rodízio cadastrado.</p>
        </div>
    </div>
</div>
<?php endif; ?>

<?php foreach ($funis as $funil):
    $corretores    = $funil['corretores'] ?? [];
    $ativos        = array_values(array_filter($corretores, fn($c) => $c['ativo'] === 'S'));
    $total_ativos  = count($ativos);
    $proximo_idx   = $total_ativos > 0 ? ((int) $funil['proximo_idx'] % $total_ativos) : -1;

    $ativo_posicao = [];
    foreach ($ativos as $pos => $a) {
        $ativo_posicao[$a['user_id']] = $pos;
    }

    $ids_no_funil       = array_column($corretores, 'user_id');
    $opcoes_disponiveis = [];
    foreach ($todos_usuarios as $u) {
        if (!in_array((int) $u['id'], $ids_no_funil, true)) {
            $opcoes_disponiveis[(string) $u['id']] = $u['username'];
        }
    }

    $icone_funil = $icone_por_tipo[$funil['tipo_seguro']] ?? 'arrows-clockwise';
?>
<section class="panel cot-panel rodizio-card">
    <div class="panel-header">
        <h2>
            <?= icon($icone_funil) ?>
            <?= e($funil['nome']) ?>
            <?php if ($funil['ativo'] === 'S'): ?>
                <span class="status-pill status-pill-aprovado">Ativo</span>
            <?php else: ?>
                <span class="status-pill status-pill-recusado">Inativo</span>
            <?php endif; ?>
        </h2>
    </div>

    <?php if (empty($corretores)): ?>
        <div class="panel-body">
            <div class="empty-state">
                <div class="empty-state-icon"><?= icon('users') ?></div>
                <p>Nenhum atendente cadastrado neste funil.</p>
            </div>
        </div>
    <?php else: ?>
        <div class="admin-cot-grid-wrap">
            <div class="admin-cot-row admin-cot-row-rodizio admin-cot-head">
                <div class="admin-cot-cell">Ordem</div>
                <div class="admin-cot-cell admin-cot-cell-nome">Atendente</div>
                <div class="admin-cot-cell">Status</div>
                <div class="admin-cot-cell">Próximo</div>
                <div class="admin-cot-cell">Ações</div>
            </div>
            <div class="rodizio-row-list" data-funil-id="<?= (int) $funil['id'] ?>">
                <?php foreach ($corretores as $corretor):
                    $eh_ativo   = $corretor['ativo'] === 'S';
                    $posicao    = $ativo_posicao[$corretor['user_id']] ?? -1;
                    $eh_proximo = $eh_ativo && $total_ativos > 0 && $posicao === $proximo_idx;
                ?>
                <div class="admin-cot-row admin-cot-row-rodizio rodizio-row" draggable="true" data-id="<?= (int) $corretor['id'] ?>">
                    <div class="admin-cot-cell admin-cot-cell-ordem">
                        <span class="rodizio-row-handle" title="Arraste para reordenar"><?= icon('list') ?></span>
                        <?= (int) $corretor['ordem'] ?>
                    </div>
                    <div class="admin-cot-cell admin-cot-cell-nome">
                        <span class="atendente-chip">
                            <?= avatar_sm($corretor['avatar'] ?? null, $corretor['username']) ?>
                            <span class="rodizio-atendente-info">
                                <strong class="rodizio-atendente-nome"><?= e($corretor['username']) ?></strong>
                                <span class="rodizio-atendente-email"><?= e($corretor['email']) ?></span>
                            </span>
                        </span>
                    </div>
                    <div class="admin-cot-cell">
                        <label class="switch rodizio-row-switch">
                            <input type="checkbox" class="rodizio-status-toggle" data-id="<?= (int) $corretor['id'] ?>" <?= $eh_ativo ? 'checked' : '' ?>>
                            <span class="switch-track"></span>
                            <span class="switch-label"><?= $eh_ativo ? 'Ativo' : 'Inativo' ?></span>
                        </label>
                    </div>
                    <div class="admin-cot-cell">
                        <?php if ($eh_proximo): ?>
                            <span class="status-pill status-pill-aprovado">Próximo</span>
                        <?php else: ?>
                            <span class="admin-cot-cell-vazio">—</span>
                        <?php endif; ?>
                    </div>
                    <div class="admin-cot-cell">
                        <button type="button" class="btn btn-danger-outline btn-sm" data-action="rodizio-remover" data-id="<?= (int) $corretor['id'] ?>" title="Remover do funil">
                            <?= icon('x') ?>
                        </button>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <div class="rodizio-add-row" data-funil-id="<?= (int) $funil['id'] ?>">
        <div class="field rodizio-add-select">
            <?= custom_select(
                "add_atendente_{$funil['id']}",
                $opcoes_disponiveis,
                $opcoes_disponiveis ? 'Selecionar atendente...' : 'Todos já estão neste funil',
                null,
                '',
                false,
                ['data-rodizio-add-select' => '1']
            ) ?>
        </div>
        <button type="button" class="btn btn-primary btn-sm" data-action="rodizio-adicionar" data-funil-id="<?= (int) $funil['id'] ?>" title="Adicionar atendente" <?= $opcoes_disponiveis ? '' : 'disabled' ?>>
            <?= icon('plus') ?> Adicionar
        </button>
    </div>
</section>
<?php endforeach;

$html = ob_get_clean();
echo json_encode(['html' => $html]);
