<?php

/**
 * Confirm the session still matches the user_group row (name, ACL, office, password hash, token).
 */
function vtrams_middleware_layer_session_integrity(): void
{
    global $pdo;

    if (!isset($pdo) || !($pdo instanceof PDO)) {
        require_once __DIR__ . '/../../../../dbconnection.inc.php';
    }

    require __DIR__ . '/../user_check.php';
}
