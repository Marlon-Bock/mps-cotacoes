<?php
/** Logo da seguradora no topo do card. Espera: $seguradora (api_seguradora()). */
?>
<?php if ($seguradora['logo']): ?>
<img class="seg-logo-img" src="<?= e(url($seguradora['logo'])) ?>" alt="<?= e($seguradora['nome']) ?>">
<?php else: ?>
<span class="seg-logo-fallback" style="background:<?= e($seguradora['cor']) ?>"><?= e($seguradora['sigla']) ?></span>
<?php endif; ?>
