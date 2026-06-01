<?php

// Ensure session is initialized using the app's session config (unique name, secure params).
if (session_status() !== PHP_SESSION_ACTIVE) {
    require_once __DIR__ . '/config_session.inc.php';
}

// Load AccessControl class
require_once __DIR__ . '/access_control.inc.php';

// If not logged in, route to 404.
AccessControl::requireLogin();

// Safely derive current file name.
$file_name = basename(htmlspecialchars($_SERVER['PHP_SELF'] ?? ''));

// Use AccessControl class for file-based access control
// This handles both ACL-based and designation-based protections
if (!AccessControl::checkFileAccess($file_name)) {
    require_once __DIR__ . '/../redirects/redirect_config.inc.php';
    redirect_to_internal('route_404');
}
