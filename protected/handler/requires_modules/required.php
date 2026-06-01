<?php

require_once '../../dbconnection.inc.php';
require_once '../../core/components/security/config_session.inc.php';
require_once '../../core/components/security/router.inc.php';
require_once '../../core/components/security/err_blocker.inc.php';
require_once '../action_module/action.model.inc.php';
require_once '../action_module/action.ctrl.inc.php';
require '../../core/components/notifications/custom_process_alert.php';

function generateToken() {
    return bin2hex(random_bytes(16)); // Generates a 32-character hexadecimal string
}


?>