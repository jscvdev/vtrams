<?php
/**
 * Render pending flash toasts on destination pages (after err checks).
 * Requires notification.inc.php earlier on the same page.
 */
if (defined('DVSYS_NOTIFICATION_FLASH_LOADED')) {
    return;
}
define('DVSYS_NOTIFICATION_FLASH_LOADED', true);

$__dvsysFlashNotify = null;
if (!empty($_SESSION['flash_notify']) && is_array($_SESSION['flash_notify'])) {
    $__dvsysFlashNotify = $_SESSION['flash_notify'];
    unset($_SESSION['flash_notify']);
}
?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        function displayFlashPayload(flash) {
            if (!flash || !flash.message || typeof showNotify !== 'function') {
                return;
            }
            showNotify(
                String(flash.message),
                flash.type || 'success',
                Number(flash.ms) || 2800
            );
        }

        <?php if (is_array($__dvsysFlashNotify)): ?>
        displayFlashPayload(<?php echo json_encode($__dvsysFlashNotify, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>);
        <?php endif; ?>

        try {
            var raw = sessionStorage.getItem('dvsys_flash_notify');
            if (raw) {
                sessionStorage.removeItem('dvsys_flash_notify');
                displayFlashPayload(JSON.parse(raw));
            }
        } catch (e) {}
    });
</script>
