<?php

/**
 * Require an authenticated user and a current single-login session token.
 */
function vtrams_middleware_layer_auth(bool $verifyLoginToken = true): void
{
    global $pdo;

    require_once __DIR__ . '/../access_control.inc.php';

    AccessControl::requireLogin();

    if (!$verifyLoginToken) {
        return;
    }

    if (empty($_SESSION['logged_user_emp_id'])) {
        return;
    }

    if (!isset($pdo) || !($pdo instanceof PDO)) {
        require_once __DIR__ . '/../../../../dbconnection.inc.php';
    }

    require_once __DIR__ . '/../../helpers/user_login_security_helper.inc.php';

    if (!user_login_session_is_current(
        $pdo,
        (string) $_SESSION['logged_user_emp_id'],
        (string) ($_SESSION['login_session_token'] ?? '')
    )) {
        require_once __DIR__ . '/../../redirects/redirect_config.inc.php';
        header('Location: ' . redirect_base_url() . '/protected/core/components/security/logout_handler.inc.php');
        exit;
    }
}
