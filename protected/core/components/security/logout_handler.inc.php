<?php
require __DIR__ . '/config_session.inc.php';
require_once __DIR__ . '/session_login_helper.inc.php';
require_once __DIR__ . '/../redirects/redirect_config.inc.php';

$logoutEmpId = (string) ($_SESSION['logged_user_emp_id'] ?? '');
$logoutToken = (string) ($_SESSION['login_session_token'] ?? '');

// Log logout before destroying session (don't let audit failure block logout)
if (isset($_SESSION['logged_user_emp_name'])) {
    try {
        require_once __DIR__ . '/../helpers/audit_helper.inc.php';
        AuditHelper::logLogout($_SESSION['logged_user_emp_name']);
    } catch (Throwable $e) {
        error_log('Audit logout log failed: ' . $e->getMessage());
    }
}

if ($logoutEmpId !== '' && $logoutToken !== '') {
    try {
        require_once __DIR__ . '/../../../dbconnection.inc.php';
        require_once __DIR__ . '/../helpers/user_login_security_helper.inc.php';
        user_login_clear_active_session_if_current($pdo, $logoutEmpId, $logoutToken);
    } catch (Throwable $e) {
        error_log('Clear active login token failed: ' . $e->getMessage());
    }
}

destroy_login_session();

redirect_to('documents_index');
