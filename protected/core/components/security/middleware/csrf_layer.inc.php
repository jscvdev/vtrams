<?php

/**
 * CSRF helpers using the existing $_SESSION['token'] used by voucher forms.
 *
 * Issue is always-on for authenticated pipelines. Validation is opt-in
 * (require_csrf option or vtrams_middleware_require_csrf()) so existing
 * POST handlers without a token field are not broken in this first rollout.
 */

if (!function_exists('generateToken')) {
    function generateToken(): string
    {
        return bin2hex(random_bytes(16));
    }
}

if (!function_exists('vtrams_csrf_token')) {
    function vtrams_csrf_token(): string
    {
        vtrams_middleware_csrf_issue();
        return (string) ($_SESSION['token'] ?? '');
    }
}

if (!function_exists('vtrams_csrf_field')) {
    function vtrams_csrf_field(): string
    {
        $token = htmlspecialchars(vtrams_csrf_token(), ENT_QUOTES, 'UTF-8');
        return '<input type="hidden" name="token" value="' . $token . '">';
    }
}

if (!function_exists('vtrams_middleware_csrf_issue')) {
    function vtrams_middleware_csrf_issue(): void
    {
        if (empty($_SESSION['token'])) {
            $_SESSION['token'] = generateToken();
        }
    }
}

if (!function_exists('vtrams_middleware_csrf_request_token')) {
    function vtrams_middleware_csrf_request_token(): string
    {
        if (!empty($_POST['token'])) {
            return (string) $_POST['token'];
        }

        $header = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? $_SERVER['HTTP_X_CSRFTOKEN'] ?? '';
        return is_string($header) ? $header : '';
    }
}

if (!function_exists('vtrams_middleware_csrf_is_mutating')) {
    function vtrams_middleware_csrf_is_mutating(): bool
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        return in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
    }
}

if (!function_exists('vtrams_middleware_csrf_script_is_exempt')) {
    function vtrams_middleware_csrf_script_is_exempt(): bool
    {
        $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? $_SERVER['PHP_SELF'] ?? ''));
        $exempt = [
            '/login_handler.inc.php',
            '/auth.php',
            '/logout_handler.inc.php',
        ];

        foreach ($exempt as $suffix) {
            if ($suffix !== '' && substr($script, -strlen($suffix)) === $suffix) {
                return true;
            }
        }

        return false;
    }
}

if (!function_exists('vtrams_middleware_csrf_validate')) {
    function vtrams_middleware_csrf_validate(): void
    {
        if (!vtrams_middleware_csrf_is_mutating() || vtrams_middleware_csrf_script_is_exempt()) {
            return;
        }

        vtrams_middleware_csrf_issue();

        $sessionToken = (string) ($_SESSION['token'] ?? '');
        $requestToken = vtrams_middleware_csrf_request_token();

        if ($sessionToken === '' || $requestToken === '' || !hash_equals($sessionToken, $requestToken)) {
            $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
            $isJson = strpos($accept, 'application/json') !== false
                || (isset($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string) $_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

            if ($isJson) {
                http_response_code(403);
                header('Content-Type: application/json; charset=UTF-8');
                echo json_encode(['error' => 'Invalid or missing request token']);
                exit;
            }

            require_once __DIR__ . '/../../redirects/redirect_config.inc.php';
            if (function_exists('redirect_to_internal')) {
                redirect_to_internal('route_403');
            }

            http_response_code(403);
            exit;
        }
    }
}

if (!function_exists('vtrams_middleware_require_csrf')) {
    /**
     * Opt-in CSRF check for a mutating handler.
     */
    function vtrams_middleware_require_csrf(): void
    {
        vtrams_middleware_csrf_validate();
    }
}
