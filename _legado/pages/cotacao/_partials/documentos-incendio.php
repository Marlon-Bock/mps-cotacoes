<?php
/**
 * Painel "Documentos enviados" do Seguro Incêndio — um acordeão por imóvel da
 * remessa, com cotação, proposta e apólice de cada um. Espera no escopo:
 * $anexos (linhas de cotacoes_anexos_incendio) e $imoveis (linhas de
 * seguro_incendio_imoveis).
 */

$__tipos_documento_incendio = ['cotacao' => 'Cotação', 'proposta' => 'Proposta', 'apolice' => 'Apólice'];
$anexos  ??= [];
$imoveis ??= [];
$__anexos_por_imovel = [];
foreach ($anexos as $a) {
    $__anexos_por_imovel[(int) $a['seguro_incendio_imovel_id']][] = $a;
}
?>
<?php if (!empty($anexos)): ?>
<div class="panel">
    <div class="panel-header"><h3 class="panel-title"><?= icon('list') ?> Documentos enviados</h3></div>
    <div class="panel-body" style="max-height:420px;overflow-y:auto">
        <?php foreach ($imoveis as $__im): ?>
        <?php $__docs = $__anexos_por_imovel[(int) $__im['id']] ?? []; ?>
        <details style="border-bottom:1px solid var(--color-border);padding:var(--space-2) 0">
            <summary style="cursor:pointer;font-weight:600;display:flex;justify-content:space-between;align-items:center">
                <span>Imóvel <?= (int) $__im['numero_imovel'] ?></span>
                <small style="font-weight:400;color:var(--color-gray-500)"><?= count($__docs) ?> documento(s)</small>
            </summary>
            <div style="margin-top:var(--space-2)">
                <?php if (!$__docs): ?>
                <small style="color:var(--color-gray-500)">Nenhum documento enviado ainda.</small>
                <?php endif; ?>
                <?php foreach ($__docs as $a): ?>
                <div style="display:flex;align-items:center;justify-content:space-between;gap:var(--space-2);padding:var(--space-2) 0">
                    <div>
                        <strong><?= e($__tipos_documento_incendio[$a['tipo_arquivo']] ?? 'Documento') ?></strong>
                        <br><small><?= date('d/m/Y H:i', strtotime($a['data_envio'])) ?></small>
                        <span class="status-pill <?= e(status_pill_class($a['status_aprovacao'])) ?>" style="font-size:.7rem"><?= e($a['status_aprovacao']) ?></span>
                    </div>
                    <a href="<?= e($a['caminho_arquivo']) ?>" target="_blank" class="btn btn-secondary" style="padding:var(--space-1) var(--space-2)" title="Abrir arquivo"><?= icon('arrow-right') ?></a>
                </div>
                <?php endforeach; ?>
            </div>
        </details>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>
