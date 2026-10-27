<?php
/**
 * AJAX endpoint — GET /api/admin/gerenciador/timeline?protocolo=X
 * Linha do tempo de uma cotação: eventos de `historico` (mesma fonte usada
 * nas páginas de detalhe de cotação) com o tempo decorrido entre um evento
 * e o anterior. Retorna JSON { html }
 */
$current_user = require_admin();
header('Content-Type: application/json; charset=utf-8');

$protocolo = trim($_GET['protocolo'] ?? '');

$historico = [];
if ($protocolo !== '') {
    try {
        $historico = db_fetch_all(
            'SELECT * FROM historico WHERE protocolo = ? ORDER BY data_evento ASC',
            ['s', $protocolo]
        );
    } catch (Throwable) {}
}

ob_start();

if (empty($historico)): ?>
<div class="admin-cot-empty">Nenhum evento encontrado para esta cotação.</div>
<?php else: ?>
<div class="status-timeline">
    <?php foreach ($historico as $i => $h): ?>
    <div class="timeline-item is-done">
        <div class="timeline-dot"></div>
        <div class="timeline-content">
            <span class="timeline-label">
                <?php if (!empty($h['status_anterior']) && !empty($h['status_novo']) && $h['status_anterior'] !== $h['status_novo']): ?>
                    <strong><?= e($h['status_anterior']) ?></strong> &rarr; <strong><?= e($h['status_novo']) ?></strong>
                <?php elseif (!empty($h['status_novo'])): ?>
                    <strong><?= e($h['status_novo']) ?></strong>
                <?php else: ?>
                    <strong><?= e($h['tipo_evento']) ?></strong>
                <?php endif; ?>
            </span>
            <span class="timeline-meta">
                <?= e($h['usuario_nome'] ?? '—') ?> · <?= e(date('d/m/Y H:i', strtotime($h['data_evento']))) ?>
                <?php if ($i > 0): ?>
                    <?php $delta = strtotime($h['data_evento']) - strtotime($historico[$i - 1]['data_evento']); ?>
                    <?php if ($delta > 0): ?>
                        · <?= e(formatar_duracao($delta)) ?> depois
                    <?php endif; ?>
                <?php endif; ?>
            </span>
            <?php if (!empty($h['observacao'])): ?>
            <span class="timeline-obs"><?= e($h['observacao']) ?></span>
            <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php
$tempo_total = strtotime(end($historico)['data_evento']) - strtotime($historico[0]['data_evento']);
if ($tempo_total > 0): ?>
<p class="field-hint timeline-tempo-total">Tempo total entre o primeiro e o último evento: <strong><?= e(formatar_duracao($tempo_total)) ?></strong></p>
<?php endif;
endif;

$html = ob_get_clean();

echo json_encode(['html' => $html]);
