<?php
/**
 * Aviso dentro do card da seguradora (erro, alerta, info, sucesso).
 * Ícone + título na 1ª linha; texto, detalhe e ação embaixo, na largura toda.
 *
 * Espera $aviso = [
 *   'tipo'   => 'erro' | 'alerta' | 'info' | 'sucesso',
 *   'titulo' => ?string,
 *   'texto'  => ?string,
 *   'extra'  => ?string HTML (linha menor abaixo do texto),
 *   'acao'   => ?string HTML (botão no rodapé do aviso),
 *   'dica'   => ?string (tooltip — ex: texto técnico pra MPS),
 * ]
 * Sem título, o texto vai na linha do ícone.
 */
$__icones = ['erro' => 'warning', 'alerta' => 'clock', 'info' => 'info', 'sucesso' => 'check-circle'];
$__tipo   = $aviso['tipo'] ?? 'info';
$__titulo = $aviso['titulo'] ?? null;
?>
<div class="seg-aviso seg-aviso--<?= e($__tipo) ?>"<?= !empty($aviso['dica']) ? ' title="' . e($aviso['dica']) . '"' : '' ?>>
    <div class="seg-aviso-cabeca">
        <span class="seg-aviso-icone"><?= icon($__icones[$__tipo] ?? 'info') ?></span>
        <?php if ($__titulo): ?>
        <span class="seg-aviso-titulo"><?= e($__titulo) ?></span>
        <?php else: ?>
        <span class="seg-aviso-texto"><?= e((string) ($aviso['texto'] ?? '')) ?></span>
        <?php endif; ?>
    </div>
    <?php if ($__titulo && !empty($aviso['texto'])): ?>
    <p class="seg-aviso-texto"><?= e($aviso['texto']) ?></p>
    <?php endif; ?>
    <?php if (!empty($aviso['extra'])): ?>
    <p class="seg-aviso-extra"><?= $aviso['extra'] ?></p>
    <?php endif; ?>
    <?php if (!empty($aviso['acao'])): ?>
    <div class="seg-aviso-acao"><?= $aviso['acao'] ?></div>
    <?php endif; ?>
</div>
