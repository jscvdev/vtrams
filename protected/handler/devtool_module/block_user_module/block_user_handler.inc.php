<?php

declare(strict_types=1);

if (empty($_GET['emp_id'])) {
    require_once __DIR__ . '/../../../../core/components/redirects/redirect_config.inc.php';
    redirect_to('devtool');
}

require_once __DIR__ . '/../../../dbconnection.inc.php';
require_once __DIR__ . '/../../../core/components/security/config_session.inc.php';
require_once __DIR__ . '/../../../core/components/security/router.inc.php';
require_once __DIR__ . '/../../../core/components/helpers/user_login_security_helper.inc.php';
require __DIR__ . '/../../../core/components/notifications/custom_process_alert.php';

$emp_id = trim((string) $_GET['emp_id']);

try {
    if ($emp_id === '') {
        echo "<script>process_functionAlert('Block Failed: invalid employee ID.', 'developer_edit_err')</script>";
        die();
    }

    if (!user_login_block($pdo, $emp_id)) {
        echo "<script>process_functionAlert('Block Failed: user not found or already blocked.', 'developer_edit_err')</script>";
        die();
    }

    echo "<script>process_functionAlert('User blocked successfully.', 'developer_edit_success')</script>";
    die();
} catch (PDOException $e) {
    echo "<script>process_functionAlert('Block Failed.', 'developer_edit_err')</script>";
    die();
}
