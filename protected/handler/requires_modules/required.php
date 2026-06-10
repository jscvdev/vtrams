<?php

require_once '../../dbconnection.inc.php';
require_once '../../core/components/security/config_session.inc.php';
require_once '../../core/components/security/router.inc.php';
require_once '../../core/components/security/err_blocker.inc.php';
require_once '../action_module/action.model.inc.php';
require_once '../action_module/action.ctrl.inc.php';
require_once '../../core/components/helpers/handler_transaction_helper.inc.php';
require '../../core/components/notifications/custom_process_alert.php';

if (isset($pdo) && $pdo instanceof PDO) {
    pdo_configure($pdo);
    if (function_exists('vouchers_bootstrap_schema')) {
        require_once __DIR__ . '/../voucher_module/voucher.model.inc.php';
        vouchers_bootstrap_schema($pdo);
    }
}

function generateToken() {
    return bin2hex(random_bytes(16)); // Generates a 32-character hexadecimal string
}


?>