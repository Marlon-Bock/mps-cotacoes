<?php
/**
 * Linhas da tabela de /admin/sistema-erros — usada só pelo endpoint AJAX
 * (includes/acao/admin-sistema-erros-listar.php), nunca incluída direto.
 * Espera no escopo: $erros (array de linhas de sistema_erros).
 */
$tipo_label = ['error' => 'Erro PHP', 'exception' => 'Exception', 'fatal' => 'Fatal'];
$tipo_cor   = ['error' => 'is-pendente', 'exception' => 'is-recusada', 'fatal' => 'is-recusada'];
?>
<?php if (!$erros): ?>
<div class="admin-cot-empty">Nenhum erro registrado nesse filtro. 🎉</div>
<?php endif; ?>

<?php foreach ($erros as $erro):
    $resolvido = (bool) $erro['resolvido'];
?>
<div class="admin-cot-row" data-erro-id="<?= (int) $erro['id'] ?>" style="grid-template-columns: 150px 100px 1fr 220px 110px 170px; min-width: 1200px;<?= $resolvido ? ' opacity:.55' : '' ?>">
    <div class="admin-cot-cell" style="color:var(--color-gray-500)">
        <?= e(date('d/m/Y H:i:s', strtotime($erro['criado_em']))) ?>
    </div>
    <div class="admin-cot-cell">
        <span class="badge pott-badge pott-badge--<?= e($tipo_cor[$erro['tipo']] ?? 'is-pendente') ?>">
            <?= e($tipo_label[$erro['tipo']] ?? $erro['tipo']) ?>
        </span>
    </div>
    <div class="admin-cot-cell" style="white-space:normal">
        <?= e($erro['mensagem']) ?>
        <?php if (!empty($erro['nivel'])): ?>
        <br><small style="color:var(--color-gray-400)"><?= e($erro['nivel']) ?></small>
        <?php endif; ?>
        <?php if (!empty($erro['trace'])): ?>
        <details style="margin-top:4px">
            <summary style="cursor:pointer; color:var(--color-gray-500); font-size:var(--font-size-xs)">Stack trace</summary>
            <pre style="white-space:pre-wrap; font-size:var(--font-size-xs); color:var(--color-gray-600); margin-top:4px"><?= e($erro['trace']) ?></pre>
        </details>
        <?php endif; ?>
    </div>
    <div class="admin-cot-cell" style="font-size:var(--font-size-xs); word-break:break-all">
        <?= e($erro['arquivo'] ?? '—') ?><?= $erro['linha'] ? ':' . (int) $erro['linha'] : '' ?>
        <?php if (!empty($erro['url'])): ?>
        <br><span style="color:var(--color-gray-400)"><?= e($erro['metodo_http'] ?? '') ?> <?= e($erro['url']) ?></span>
        <?php endif; ?>
    </div>
    <div class="admin-cot-cell" style="font-size:var(--font-size-xs); color:var(--color-gray-500)">
        <?php if ($erro['usuario_id']): ?>
        #<?= (int) $erro['usuario_id'] ?> (<?= e($erro['usuario_tipo'] ?? '?') ?>)
        <?php else: ?>
        —
        <?php endif; ?>
    </div>
    <div class="admin-cot-cell">
        <?php if ($resolvido): ?>
        <span class="badge pott-badge pott-badge--is-ok" title="Por <?= e($erro['resolvido_por'] ?? '?') ?> em <?= e(date('d/m/Y H:i', strtotime($erro['resolvido_em']))) ?>">
            <?= icon('check-circle') ?> Corrigido
        </span>
        <button type="button" class="btn btn-secondary btn-sm btn-toggle-resolvido" data-id="<?= (int) $erro['id'] ?>" style="margin-top:4px">
            Reabrir
        </button>
        <?php else: ?>
        <button type="button" class="btn btn-primary btn-sm btn-toggle-resolvido" data-id="<?= (int) $erro['id'] ?>">
            Marcar corrigido
        </button>
        <?php endif; ?>
    </div>
</div>
<?php endforeach; ?>
