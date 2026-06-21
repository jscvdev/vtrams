<?php
/**
 * Legacy err-handler redirect helper — no toast on handler markup pages.
 */
if (defined('DVSYS_ERR_HANDLER_ALERT_LOADED')) {
    return;
}
define('DVSYS_ERR_HANDLER_ALERT_LOADED', true);

require_once __DIR__ . '/../redirects/redirect_config.inc.php';
?>
<script>
    window.err_handler_redirectMap = window.err_handler_redirectMap || <?php echo get_redirect_map_js_json(); ?>;

    function err_handler_functionAlert(msg, code, myYes) {
        var text = String(msg || '');
        if (typeof myYes === 'function') {
            myYes();
        }
        try {
            sessionStorage.setItem('dvsys_flash_notify', JSON.stringify({
                message: text,
                type: 'error',
                ms: 5000
            }));
        } catch (e) {}
        var url = window.err_handler_redirectMap && window.err_handler_redirectMap[code];
        if (url) {
            window.location.replace(url);
        }
    }
</script>
