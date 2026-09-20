<?php

/**
 * VTRAMS request middleware kernel.
 *
 * Runs shared security gates before a page or handler does real work.
 * router.inc.php calls vtrams_middleware_run() so existing requires stay valid.
 *
 * See MIDDLEWARE.txt in this folder for usage and rollout notes.
 */

if (!function_exists('vtrams_middleware_defaults')) {
    /**
     * @return array<string, bool>
     */
    function vtrams_middleware_defaults(): array
    {
        return [
            'require_login' => true,
            'verify_login_token' => true,
            'session_integrity' => true,
            'file_acl' => true,
            'issue_csrf' => true,
            'require_csrf' => false,
        ];
    }
}

if (!function_exists('vtrams_middleware_run')) {
    /**
     * Execute the security pipeline for the current HTTP request.
     *
     * @param array<string, bool> $options Override defaults; unknown keys are ignored.
     */
    function vtrams_middleware_run(array $options = []): void
    {
        $options = array_merge(vtrams_middleware_defaults(), $options);

        if (!empty($GLOBALS['vtrams_middleware_completed']) && empty($options['force'])) {
            if (!empty($options['require_csrf'])) {
                require_once __DIR__ . '/middleware/csrf_layer.inc.php';
                vtrams_middleware_csrf_validate();
            }
            return;
        }

        require_once __DIR__ . '/middleware/session_layer.inc.php';
        require_once __DIR__ . '/middleware/auth_layer.inc.php';
        require_once __DIR__ . '/middleware/session_integrity_layer.inc.php';
        require_once __DIR__ . '/middleware/file_acl_layer.inc.php';
        require_once __DIR__ . '/middleware/csrf_layer.inc.php';

        vtrams_middleware_layer_session();

        if (!empty($options['require_login'])) {
            vtrams_middleware_layer_auth(!empty($options['verify_login_token']));
        }

        if (!empty($options['session_integrity'])) {
            vtrams_middleware_layer_session_integrity();
        }

        if (!empty($options['file_acl'])) {
            vtrams_middleware_layer_file_acl();
        }

        if (!empty($options['issue_csrf'])) {
            vtrams_middleware_csrf_issue();
        }

        if (!empty($options['require_csrf'])) {
            vtrams_middleware_csrf_validate();
        }

        $GLOBALS['vtrams_middleware_completed'] = true;
    }
}
