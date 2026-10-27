<?php
/**
 * Scripts comuns de fechamento, antes de </body></html>.
 * Espera: $extra_js (array opcional de paths em /assets/js/)
 */
$extra_js ??= [];
?>
<?php require BASE_PATH . '/pages/partials/cotacao-chat-offcanvas.php'; ?>
<script src="<?= e(asset_v('/assets/js/csrf-fetch.js')) ?>"></script>
<script src="<?= e(asset_v('/assets/js/mpsAlert.js')) ?>"></script>
<script src="<?= e(asset_v('/assets/js/custom-select.js')) ?>"></script>
<script src="<?= e(asset_v('/assets/js/date-picker.js')) ?>"></script>
<script src="<?= e(asset_v('/assets/js/time-picker.js')) ?>"></script>
<script src="<?= e(asset_v('/assets/js/app.js')) ?>"></script>
<script src="<?= e(asset_v('/assets/js/session-watchdog.js')) ?>"></script>
<script src="<?= e(asset_v('/assets/js/notify-sound.js')) ?>"></script>
<script src="<?= e(asset_v('/assets/js/cotacao-chat.js')) ?>"></script>
<script src="<?= e(asset_v('/assets/js/notificacoes-bell.js')) ?>"></script>
<?php if (($current_user->role ?? '') === 'admin'): ?>
<?php endif; ?>
<script src="<?= e(asset_v('/assets/js/vendor/sortable.min.js')) ?>"></script>
<script src="<?= e(asset_v('/assets/js/pwa-install.js')) ?>"></script>
<?php foreach ($extra_js as $js): ?>
<script src="<?= e(asset_v('/assets/js/' . $js)) ?>"></script>
<?php endforeach; ?>
</body>
</html>
