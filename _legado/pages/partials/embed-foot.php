<?php
/**
 * Fechamento das páginas de embed — versão reduzida de app-foot.php: sem
 * chat/notificações/session-watchdog (dependem de login), mas mantém
 * csrf-fetch.js (injeta o token stateless em todo POST) e os utilitários de
 * UI que o formulário usa (alertas, selects, datepicker, máscaras).
 * Espera: $extra_js (array opcional de paths em /assets/js/).
 */
$extra_js ??= [];
?>
</main>
<script src="<?= e(asset_v('/assets/js/csrf-fetch.js')) ?>"></script>
<script src="<?= e(asset_v('/assets/js/mpsAlert.js')) ?>"></script>
<script src="<?= e(asset_v('/assets/js/custom-select.js')) ?>"></script>
<script src="<?= e(asset_v('/assets/js/date-picker.js')) ?>"></script>
<script src="<?= e(asset_v('/assets/js/time-picker.js')) ?>"></script>
<script src="<?= e(asset_v('/assets/js/app.js')) ?>"></script>
<?php foreach ($extra_js as $js): ?>
<script src="<?= e(asset_v('/assets/js/' . $js)) ?>"></script>
<?php endforeach; ?>
</body>
</html>
