<?php
require __DIR__ . '/config_session.inc.php';
require __DIR__ . '/err_blocker.inc.php';
require_once __DIR__ . '/session_login_helper.inc.php';
require_once __DIR__ . '/../redirects/redirect_config.inc.php';

// Log logout before destroying session (don't let audit failure block logout)
if (isset($_SESSION['logged_user_emp_name'])) {
    try {
        require_once __DIR__ . '/../helpers/audit_helper.inc.php';
        AuditHelper::logLogout($_SESSION['logged_user_emp_name']);
    } catch (Throwable $e) {
        error_log('Audit logout log failed: ' . $e->getMessage());
    }
}

destroy_login_session();

$loginUrl = get_redirect_url('documents_index');
if ($loginUrl === null) {
    http_response_code(500);
    echo 'Login redirect is not configured.';
    exit;
}

header('Location: ' . $loginUrl);
exit;
