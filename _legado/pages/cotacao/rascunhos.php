<?php
/**
 * GET /cotacao/rascunhos
 * Lista unificada de todos os rascunhos salvos (fiança, auto, título, condominial…)
 * e cotações em pré-análise (Aguardando liberação).
 */

$current_user = require_perm('perm_cotar');

$imobiliaria_id = (int) ($current_user->imobiliaria_id ?? 0);
$usuario_id = (int) ($current_user->uid ?? 0);
$is_admin_imob = !empty($current_user->imob_admin);
$tem_perm = fn(string $perm): bool => $is_admin_imob || ($current_user->{$perm} ?? 'N') === 'S';

// ─── Configuração de produtos com rascunho ──────────────────────────────────
// Para adicionar um novo produto: inclua uma entrada neste array.
// tabela, colunas_select, col_nome (campo descritivo), url_continuar, icon, label.
$produtos = [
    'fianca' => [
        'label' => 'Fiança',
        'icon' => 'clipboard-text',
        'perm' => 'perm_fianca',
        'tabela' => 'seguro_fianca_rascunho',
        'col_updated' => 'atualizado_em',
        'url_fn' => fn(array $r): string =>
            '/cotacao/fianca/' . (strtolower($r['tipo_ficha'] ?? '') === 'comercial' ? 'comercial' : 'residencial')
            . '?rascunho=' . urlencode($r['protocolo']),
        'label_fn' => fn(array $r): string =>
            'Fiança ' . (strtolower($r['tipo_ficha'] ?? '') === 'comercial' ? 'Comercial' : 'Residencial'),
        'detalhe_fn' => fn(array $r): string => trim((string) ($r['nome_completo'] ?? '')) ?: '—',
    ],
    'auto' => [
        'label' => 'Auto',
        'icon' => 'car',
        'perm' => 'perm_auto',
        'tabela' => 'seguro_auto_rascunho',
        'col_updated' => 'updated_at',
        'url_fn' => fn(array $r): string => '/cotacao/auto?rascunho=' . urlencode($r['protocolo']),
        'detalhe_fn' => fn(array $r): string => trim(
            ($r['marca_modelo'] ?? '') .
            ($r['ano_modelo'] ? ' · ' . $r['ano_modelo'] : '') .
            ($r['placa'] ? ' · ' . strtoupper($r['placa']) : '')
        ),
    ],
    'titulo' => [
        'label' => 'Título de Cap.',
        'icon' => 'piggy-bank',
        'perm' => 'perm_titulo',
        'tabela' => 'seguro_titulo_rascunho',
        'col_updated' => 'updated_at',
        'url_fn' => fn(array $r): string => '/cotacao/titulo?rascunho=' . urlencode($r['protocolo']),
        'detalhe_fn' => fn(array $r): string => trim(
            ($r['finalidade'] ?? '') . ($r['inq_nome'] ? ' · ' . $r['inq_nome'] : '')
        ) ?: '—',
    ],
    'condominial' => [
        'label' => 'Condominial',
        'icon' => 'buildings',
        'perm' => 'perm_condo',
        'tabela' => 'seguro_condominial_rascunho',
        'col_updated' => 'updated_at',
        'url_fn' => fn(array $r): string => '/cotacao/condominial?rascunho=' . urlencode($r['protocolo']),
        'detalhe_fn' => fn(array $r): string => trim(
            ($r['imovel_nome'] ?? '') . ($r['imovel_endereco'] ? ' · ' . $r['imovel_endereco'] : '')
        ) ?: '—',
    ],
];

// ─── Busca unificada de rascunhos ───────────────────────────────────────────
$rascunhos = [];

foreach ($produtos as $tipo => $cfg) {
    if (!$tem_perm($cfg['perm'])) {
        continue;
    }
    try {
        $colUpdated = $cfg['col_updated'];
        if ($is_admin_imob) {
            $rows = db_fetch_all(
                "SELECT * FROM {$cfg['tabela']}
                  WHERE imobiliaria_id = ?
               ORDER BY {$colUpdated} DESC",
                ['i', $imobiliaria_id]
            );
        } else {
            $rows = db_fetch_all(
                "SELECT * FROM {$cfg['tabela']}
                  WHERE imobiliaria_id = ? AND usuario_id = ?
               ORDER BY {$colUpdated} DESC",
                ['ii', $imobiliaria_id, $usuario_id]
            );
        }

        foreach ($rows as $row) {
            $rascunhos[] = [
                'tipo' => $tipo,
                'label' => isset($cfg['label_fn']) ? ($cfg['label_fn'])($row) : $cfg['label'],
                'icon' => $cfg['icon'],
                'id' => $row['id'],
                'protocolo' => $row['protocolo'] ?? null,
                'nome_rascunho' => $row['nome_rascunho'] ?? '',
                'detalhe' => ($cfg['detalhe_fn'])($row),
                'url' => ($cfg['url_fn'])($row),
                'updated_at' => $row[$colUpdated] ?? '',
            ];
        }
    } catch (\Throwable $e) {
        error_log("[rascunhos] Erro ao ler {$cfg['tabela']}: " . $e->getMessage());
    }
}

// Ordena todos os rascunhos por data de atualização (mais recente primeiro)
usort($rascunhos, fn($a, $b) => strcmp($b['updated_at'], $a['updated_at']));

// ─── Cotações em pré-análise (Aguardando liberação) ─────────────────────────
// Mesmos status reconhecidos em cotacoes-listar.php como "pendentes de ação da imobiliária".
$pre_analise_statuses = [
    'Aguardando liberação',
    'Aguardando liberacao',
    'Aguardando preenchimento',
    'Pré-análise aceita',
    'Pre-analise aceita',
    'Pré-análise recusada',
    'Pre-analise recusada',
];

// Operador comum só vê as próprias cotações (mesma regra de
// includes/acao/cotacoes-listar.php); admin da imobiliária vê tudo.
$pre_analise = [];
if ($tem_perm('perm_fianca')) {
    try {
        $placeholders = implode(',', array_fill(0, count($pre_analise_statuses), '?'));
        $sql_pre_analise = "SELECT sf.id AS cotacao_id,
                sf.protocolo,
                sf.tipo_ficha,
                COALESCE(NULLIF(sf.nome_completo,''),'—') AS pretendente,
                sf.status,
                COALESCE(sf.data_atualizacao, sf.data_cadastro) AS atualizado_em,
                sf.data_cadastro AS data_criacao,
                ua.username AS atendente_nome,
                ua.avatar AS atendente_avatar
           FROM seguro_fianca sf
           LEFT JOIN usuario ua ON ua.id = sf.atendente_id
          WHERE sf.imobiliaria_id = ? AND sf.status IN ({$placeholders})"
            . (!$is_admin_imob ? " AND sf.usuario_id = ?" : "")
            . " ORDER BY sf.data_cadastro DESC";
        $bind = array_merge(['i' . str_repeat('s', count($pre_analise_statuses)), $imobiliaria_id], $pre_analise_statuses);
        if (!$is_admin_imob) {
            $bind[0] .= 'i';
            $bind[] = $usuario_id;
        }
        $pre_analise = db_fetch_all($sql_pre_analise, $bind);
    } catch (\Throwable $e) {
        $pre_analise = [];
    }
}

$count_rascunhos = count($rascunhos);
$count_pre = count($pre_analise);

// ─── Layout ─────────────────────────────────────────────────────────────────
$page_title = 'Rascunhos';
$page_heading = 'Rascunhos';
$page_subheading = 'Cotações salvas e aguardando liberação';
$page_back = null;
$extra_css = ['dashboard.css', 'cotacoes.css'];
$extra_js = [];

require BASE_PATH . '/pages/partials/app-head.php';
require BASE_PATH . '/pages/partials/nav-imobiliaria.php';
?>
<div class="app-shell">
    <?php require BASE_PATH . '/pages/partials/sidebar.php'; ?>
    <div class="app-main">
        <?php require BASE_PATH . '/pages/partials/topbar.php'; ?>
        <main class="app-content">

            <!-- ═══════════════════════════════════════════════
                 PAINEL: Rascunhos salvos (todos os produtos)
            ════════════════════════════════════════════════ -->
            <div class="panel">
                <div class="panel-header">
                    <h2>
                        <?= icon('clipboard-text') ?>
                        Rascunhos salvos
                        <span id="rascunhos-count-badge" class="badge badge-primary" <?= $count_rascunhos > 0 ? '' : 'hidden' ?>><?= $count_rascunhos ?></span>
                    </h2>
                </div>
                <div class="panel-body p-0" id="rascunhos-panel-body">
                    <div class="empty-state" id="rascunhos-empty-state" <?= $count_rascunhos > 0 ? 'hidden' : '' ?>>
                        <div class="empty-state-icon"><?= icon('clipboard-text') ?></div>
                        <p>Nenhum rascunho salvo<?= $is_admin_imob ? ' pela imobiliária' : '' ?>.<br>
                            Inicie uma cotação para salvar um rascunho.</p>
                    </div>
                    <div class="admin-cot-grid-wrap" id="rascunhos-grid-wrap" <?= $count_rascunhos > 0 ? '' : 'hidden' ?>>
                        <div class="admin-cot-row admin-cot-row-rascunhos admin-cot-head">
                            <div class="admin-cot-cell">Produto</div>
                            <div class="admin-cot-cell">Protocolo</div>
                            <div class="admin-cot-cell">Nome do rascunho</div>
                            <div class="admin-cot-cell admin-cot-cell-nome">Detalhes</div>
                            <div class="admin-cot-cell">Último acesso</div>
                            <div class="admin-cot-cell">Ações</div>
                        </div>
                        <?php foreach ($rascunhos as $r): ?>
                        <div class="admin-cot-row admin-cot-row-rascunhos">
                            <div class="admin-cot-cell"><span class="tipo-pill tipo-pill-<?= e($r['tipo']) ?>"><?= e($r['label']) ?></span></div>
                            <div class="admin-cot-cell">
                                <?php if ($r['protocolo']): ?>
                                <span class="proto-code"><?= e($r['protocolo']) ?></span>
                                <?php else: ?>
                                <span style="color:var(--color-gray-400)">—</span>
                                <?php endif; ?>
                            </div>
                            <div class="admin-cot-cell"><?= e($r['nome_rascunho'] ?: '—') ?></div>
                            <div class="admin-cot-cell admin-cot-cell-nome"><?= e($r['detalhe'] ?: '—') ?></div>
                            <div class="admin-cot-cell" style="color:var(--color-gray-500)"><?= e(date_to_br(substr($r['updated_at'], 0, 10))) ?></div>
                            <div class="admin-cot-cell">
                                <div class="table-actions">
                                    <a href="<?= e(url($r['url'])) ?>" class="btn btn-secondary btn-sm">
                                        <?= icon('note-pencil') ?>
                                        Continuar
                                    </a>
                                    <button type="button" class="btn btn-link btn-sm"
                                        data-action="excluir-rascunho" data-protocolo="<?= e($r['protocolo']) ?>"
                                        data-tipo="<?= e($r['tipo']) ?>">
                                        <?= icon('x') ?>
                                        Excluir
                                    </button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <!-- ═══════════════════════════════════════════════
                 PAINEL: Cotações em Pré-análise
            ════════════════════════════════════════════════ -->
            <div class="panel">
                <div class="panel-header">
                    <h2>
                        <?= icon('lock') ?>
                        Aguardando Liberação
                        <?php if ($count_pre > 0): ?>
                            <span class="badge badge-primary"><?= $count_pre ?></span>
                        <?php endif; ?>
                    </h2>
                </div>
                <div class="panel-body p-0">
                    <?php if ($count_pre === 0): ?>
                        <div class="empty-state">
                            <div class="empty-state-icon"><?= icon('lock') ?></div>
                            <p>Nenhuma cotação aguardando liberação<?= $is_admin_imob ? ' da imobiliária' : '' ?>.</p>
                        </div>
                    <?php else: ?>
                        <div class="admin-cot-grid-wrap">
                            <div class="admin-cot-row admin-cot-row-pre-analise admin-cot-head">
                                <div class="admin-cot-cell">Produto</div>
                                <div class="admin-cot-cell">Protocolo</div>
                                <div class="admin-cot-cell admin-cot-cell-nome">Pretendente</div>
                                <div class="admin-cot-cell">Status</div>
                                <div class="admin-cot-cell">Atendente</div>
                                <div class="admin-cot-cell">Data criação</div>
                                <div class="admin-cot-cell">Última atualização</div>
                                <div class="admin-cot-cell">Ações</div>
                            </div>
                            <?php foreach ($pre_analise as $c):
                                $pode_completar = in_array($c['status'], [
                                    'Pré-análise aceita',
                                    'Pre-analise aceita',
                                    'Pré-análise recusada',
                                    'Pre-analise recusada',
                                    'Aguardando preenchimento',
                                ], true);
                                $tipo_ficha_link = strtolower($c['tipo_ficha'] ?? '') === 'comercial' ? 'comercial' : 'residencial';
                                $produto_label = 'Fiança ' . (strtolower($c['tipo_ficha'] ?? '') === 'comercial' ? 'Comercial' : 'Residencial');
                                $url_acao = $pode_completar
                                    ? '/cotacao/fianca/' . $tipo_ficha_link . '?cotacao=' . urlencode($c['protocolo'])
                                    : '/cotacao/fianca/' . urlencode($c['protocolo']);
                                $atendente_val = (string) ($c['atendente_nome'] ?? '');
                                ?>
                                <div class="admin-cot-row admin-cot-row-pre-analise">
                                    <div class="admin-cot-cell"><span
                                            class="tipo-pill tipo-pill-fianca"><?= e($produto_label) ?></span></div>
                                    <div class="admin-cot-cell"><span class="proto-code"><?= e($c['protocolo']) ?></span></div>
                                    <div class="admin-cot-cell admin-cot-cell-nome"><?= e($c['pretendente'] ?: '—') ?></div>
                                    <div class="admin-cot-cell">
                                        <span class="status-pill <?= e(status_pill_class($c['status'])) ?>">
                                            <?= e(admin_status_label($c['status'])) ?>
                                        </span>
                                    </div>
                                    <div class="admin-cot-cell">
                                        <?php if ($atendente_val !== ''): ?>
                                            <span class="atendente-chip"><?= avatar_sm($c['atendente_avatar'] ?? null, $atendente_val) ?>
                                                <?= e($atendente_val) ?></span>
                                        <?php else: ?>
                                            <span style="color:var(--color-gray-400)">Não atribuído</span>
                                        <?php endif; ?>
                                    </div>
                                    <div class="admin-cot-cell" style="color:var(--color-gray-500)">
                                        <?= $c['data_criacao'] ? e(date('d/m/Y H:i', strtotime($c['data_criacao']))) : '—' ?>
                                    </div>
                                    <div class="admin-cot-cell" style="color:var(--color-gray-500)">
                                        <?= $c['atualizado_em'] ? e(date('d/m/Y H:i', strtotime($c['atualizado_em']))) : '—' ?>
                                    </div>
                                    <div class="admin-cot-cell">
                                        <a href="<?= e(url($url_acao)) ?>"
                                            class="btn btn-sm <?= $pode_completar ? 'btn-primary' : 'btn-secondary' ?>">
                                            <?= icon($pode_completar ? 'note-pencil' : 'eye') ?>
                                            <?= $pode_completar ? 'Completar cotação' : 'Ver detalhes' ?>
                                        </a>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

        </main>
    </div>
</div>
<?php require BASE_PATH . '/pages/partials/app-foot.php'; ?>

<script>
    document.addEventListener('DOMContentLoaded', function () {
        var csrfToken = '<?= e(csrf_token()) ?>';

        document.querySelectorAll('[data-action="excluir-rascunho"]').forEach(function (btn) {
            btn.addEventListener('click', async function () {
                var ok = await mpsAlert.confirm({ message: 'Excluir este rascunho? Essa ação não pode ser desfeita.' });
                if (!ok) return;

                btn.disabled = true;
                var linha = btn.closest('.admin-cot-row');

                mpsAlert.loading('Excluindo rascunho...');

                fetch('/api/cotacao/rascunho/excluir', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': csrfToken },
                    body: JSON.stringify({ tipo: btn.dataset.tipo, protocolo: btn.dataset.protocolo })
                })
                    .then(function (r) { return r.json(); })
                    .then(function (data) {
                        if (data.ok) {
                            if (linha) linha.remove();
                            atualizarEstadoRascunhos();
                            mpsAlert.success('Rascunho excluído com sucesso.');
                        } else {
                            mpsAlert.error(data.message || 'Erro ao excluir rascunho.');
                            btn.disabled = false;
                        }
                    })
                    .catch(function () {
                        mpsAlert.error('Erro na requisição. Tente novamente.');
                        btn.disabled = false;
                    });
            });
        });

        // Se não sobrar nenhum rascunho, troca o grid pelo estado vazio (sem recarregar a página).
        function atualizarEstadoRascunhos() {
            var restantes = document.querySelectorAll('.admin-cot-grid-wrap .admin-cot-row:not(.admin-cot-head)');
            var badge = document.getElementById('rascunhos-count-badge');

            if (badge) {
                if (restantes.length > 0) {
                    badge.hidden = false;
                    badge.textContent = restantes.length;
                } else {
                    badge.hidden = true;
                }
            }

            var gridWrap  = document.getElementById('rascunhos-grid-wrap');
            var emptyState = document.getElementById('rascunhos-empty-state');
            if (gridWrap)   gridWrap.hidden   = restantes.length === 0;
            if (emptyState) emptyState.hidden = restantes.length > 0;
        }
    });
</script>