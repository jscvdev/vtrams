<?php

require __DIR__ . "/err_blocker.inc.php";

// Use a unique session name for this app to avoid collisions with other PHP apps.
$sessionName = 'vtrams_session';

// Detect current host and scheme to set proper cookie params for localhost or domain.
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443);

// Set cookie domain: for localhost leave empty (default), for others use the host.
$cookieDomain = (stripos($host, 'localhost') !== false || filter_var($host, FILTER_VALIDATE_IP)) ? '' : $host;

// Only initialize session settings if a session is not already active.
if (session_status() !== PHP_SESSION_ACTIVE) {
    if (session_name() !== $sessionName) {
        session_name($sessionName);
    }

    session_set_cookie_params([
        'lifetime' => 0, // Cookie expires on browser close
        'domain' => $cookieDomain, // Use current host (blank for localhost/IP)
        'path' => '/', // Cookie path
        'secure' => $isHttps, // Only transmit cookies over HTTPS when available
        'httponly' => true, // Make cookies accessible only through HTTP(S), not JavaScript
        'samesite' => 'Lax',
    ]);

    // COOKIE INI
    ini_set('session.use_only_cookies', 1);
    ini_set('session.use_strict_mode', 1);

    // START SESSION
    session_start();
}

// Function to regenerate the session ID
function regenerate_session_id() {
    session_regenerate_id(true); // Regenerate session ID and delete the old session file

    $_SESSION["last_regeneration"] = time(); // Update last regeneration time
}

// Function to check and handle session regeneration
function check_session_regeneration() {
    $interval = 60 * 60 * 3; // 3 hours interval for session regeneration
    // $interval = 60 * 5; // 3 hours interval for session regeneration

    if (isset($_SESSION["emp_id"])) {
        // User is logged in, handle regeneration for logged-in users
        if (!isset($_SESSION["last_regeneration"]) || (time() - $_SESSION["last_regeneration"] >= $interval)) {
            regenerate_session_id();
        }
    } else {
        // User is not logged in, handle regeneration for not logged-in users
        if (!isset($_SESSION["last_regeneration"]) || (time() - $_SESSION["last_regeneration"] >= $interval)) {
            regenerate_session_id();
        }
    }
}

// Call the function to check and handle session regeneration
check_session_regeneration();

?>




