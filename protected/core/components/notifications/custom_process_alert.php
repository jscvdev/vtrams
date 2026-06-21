<?php
/**
 * Handler redirect helpers — no toast UI on the handler response page.
 */
if (defined('DVSYS_PROCESS_ALERT_LOADED')) {
    return;
}
define('DVSYS_PROCESS_ALERT_LOADED', true);

require_once __DIR__ . '/../redirects/redirect_config.inc.php';
?>
<script>
    window.redirectMap = window.redirectMap || <?php echo get_redirect_map_js_json(); ?>;

    function process_functionAlert(msg, code, myYes) {
        var text = String(msg || '');
        var type = /fail|error|invalid|wrong|not authorized|not available/i.test(text) ? 'error' : 'success';
        if (typeof myYes === 'function') {
            myYes();
            if (!window.redirectMap || !window.redirectMap[code]) {
                return;
            }
        }
        try {
            sessionStorage.setItem('dvsys_flash_notify', JSON.stringify({
                message: text,
                type: type,
                ms: type === 'error' ? 5000 : 2800
            }));
        } catch (e) {}
        var url = window.redirectMap && window.redirectMap[code];
        if (url) {
            window.location.replace(url);
        }
    }
</script>
