<?php
require __DIR__ . '/config_session.inc.php';
include(__DIR__ . '/../notifications/system_custom_alert.php');
include(__DIR__ . '/err_blocker.inc.php');

// Log logout before destroying session (don't let audit failure block logout)
if (isset($_SESSION['logged_user_emp_name'])) {
    try {
        require_once __DIR__ . '/../helpers/audit_helper.inc.php';
        AuditHelper::logLogout($_SESSION['logged_user_emp_name']);
    } catch (Throwable $e) {
        error_log('Audit logout log failed: ' . $e->getMessage());
    }
}

session_unset();
session_destroy();

echo "<script>login_functionAlert('Logout success!', 'logout_user')</script>";

die();
