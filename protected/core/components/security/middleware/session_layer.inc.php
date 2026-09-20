<?php

/**
 * Session cookie setup and periodic session-id regeneration.
 */
function vtrams_middleware_layer_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        require_once __DIR__ . '/../config_session.inc.php';
    }
}
