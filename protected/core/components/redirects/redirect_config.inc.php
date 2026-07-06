<?php

/**
 * Centralized redirect configuration for DVSYS
 * Single source of truth for all redirect targets (PHP header + JS window.location)
 *
 * Usage:
 *   PHP:  require_once 'redirect_config.inc.php'; redirect_to('devtool');
 *   JS:   Include this file output via get_redirect_map_js_json() and use redirectMap[code]
 */

if (!function_exists('redirect_base_url')) {
    /**
     * Base URL for the application (e.g. http://localhost/vtrams)
     */
    function redirect_base_url()
    {
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $script = $_SERVER['SCRIPT_NAME'] ?? '';
        // Assume app lives under /vtrams or similar; detect from SCRIPT_NAME or use constant
        $basePath = defined('REDIRECT_WEB_BASE') ? REDIRECT_WEB_BASE : '/vtrams';
        return $scheme . '://' . $host . $basePath;
    }
}

if (!function_exists('redirect_public_path')) {
    /**
     * Path segment for public folder (no leading/trailing slash)
     */
    function redirect_public_path()
    {
        return defined('REDIRECT_PUBLIC_PATH') ? REDIRECT_PUBLIC_PATH : 'public';
    }
}

/**
 * Redirect keys => path relative to public folder (e.g. vouchers/devtool.php, documents/pending.php)
 * Used for both PHP header Location and JS redirect map.
 */
$GLOBALS['REDIRECT_MAP'] = [
    // Vouchers
    'devtool'                          => 'vouchers/devtool.php',
    'settings'                         => 'vouchers/settings.php',
    'voucher'                          => 'vouchers/voucher.php',
    'voucher_encoded'                  => 'vouchers/voucher_encoded.php',
    'voucher_ada'                      => 'vouchers/voucher_ada.php',
    'voucher_sent'                     => 'vouchers/voucher_sent.php',
    'voucher_incoming'                 => 'vouchers/voucher_incoming.php',
    'voucher_forwarding'               => 'vouchers/voucher_forwarding.php',
    'voucher_status_report'            => 'vouchers/voucher_status_report.php',
    'designations'                     => 'vouchers/designations.php',

    // Documents (for app using documents/ as in sys or legacy paths)
    'documents_index'                   => 'documents/index.php',
    'documents_pending'                 => 'documents/pending.php',
    'documents_incoming'                => 'documents/incoming.php',
    'documents_reply'                   => 'documents/reply.php',
    'documents_sent'                    => 'documents/sent.php',
    'documents_forwarding'              => 'documents/forwarding.php',
    'documents_routing_slip'            => 'documents/routing_slip.php',
    'documents_edit_form'               => 'documents/edit_form.php',
    'documents_document_tracking'       => 'documents/document_tracking.php',

    // Login / public
    'login_index'                      => 'documents/index.php',
    'public_index'                     => 'index.php',

    // Encode (voucher encode flow; document encode uses documents_pending)
    'encode'                           => 'vouchers/voucher.php',

    // Internal (relative to protected)
    'route_404'                         => null, // special: path from protected
    'route_403'                         => null, // special: path from protected
];

/**
 * Internal routes (used by router, dbconnection, access_control)
 * Path relative to protected/ when using redirect_to_internal()
 */
$GLOBALS['REDIRECT_INTERNAL_MAP'] = [
    'route_404' => 'routes/404.php',
    'route_403' => 'routes/403.php',
];

/**
 * Map from alert/process codes (used in process_functionAlert and err_handler) to redirect keys.
 * This way we keep one set of paths and map multiple codes to the same target.
 */
$GLOBALS['REDIRECT_CODE_TO_KEY'] = [
    // Login
    'login_records'              => 'voucher',
    'login_default_normal'       => 'voucher',
    'login_default_authorized'   => 'voucher',
    'login_developer'           => 'voucher',
    'login_err'                 => 'documents_index',
    'login_pending'             => 'documents_pending',
    'login_incoming'            => 'documents_incoming',
    'login_developer_page'      => 'devtool',
    'logout_user'               => 'documents_index',
    'error_login'               => 'documents_index',
    'login_err_handler_err'     => 'documents_index',
    'login_err_handler_success' => 'public_index',

    // Developer / devtool
    'edit_account_err'          => 'devtool',
    'developer_edit_success'    => 'devtool',
    'developer_edit_failed'     => 'devtool',
    'developer_edit_err'        => 'devtool',

    // Documents
    'encode_document_process_redirect' => 'documents_pending',
    'pending_forward_redirect'  => 'documents_pending',
    'sent_return_redirect'      => 'documents_sent',
    'edit_pending_redirect'     => 'documents_pending',
    'incoming_redirect'         => 'documents_incoming',
    'return_redirect'           => 'documents_incoming',
    'receiving_redirect'        => 'documents_forwarding',
    'redirect_archiving'        => 'documents_forwarding',
    'edit_routing_document'     => 'documents_routing_slip',
    'change_pw_redirect'        => 'documents_document_tracking',
    'clear_input'               => 'documents_pending',

    // Document error handler codes
    'receiving_err_redirect'   => 'documents_forwarding',
    'archiving_redirect_err'    => 'documents_forwarding',
    'error_reply'               => 'documents_forwarding',
    'change_pw_redirect_err'    => 'documents_document_tracking',
    'encode_document_err_redirect' => 'documents_pending',
    'reply_encode_document_err' => 'documents_reply',
    'delete_from_pending_document_err' => 'documents_pending',
    'delete_from_routing_document_err' => 'documents_routing_slip',
    'pending_forward_err_redirect'    => 'documents_pending',
    'incoming_err_redirect'     => 'documents_incoming',
    'reply_err_redirect'        => 'documents_forwarding',
    'return_err_redirect'       => 'documents_incoming',
    'sent_return_err_redirect'  => 'documents_sent',

    // Vouchers
    'voucher_pending_forward_redirect' => 'voucher',
    'voucher_edit_err'           => 'voucher',
    'voucher_ada_redirect'       => 'voucher_ada',
    'voucher_ada_redirect2'      => 'voucher_ada',
    'voucher_sent_redirect'      => 'voucher_sent',
    'voucher_redirect'           => 'voucher',
    'edit_pending_voucher_redirect' => 'voucher',
    'voucher_incoming_redirect'  => 'voucher_incoming',
    'voucher_receiving_redirect' => 'voucher_forwarding',
    'voucher_archive_redirect'  => 'voucher_forwarding',
    'voucher_err_redirect'      => 'voucher',
    'voucher_receiving_err_redirect' => 'voucher_forwarding',
    'voucher_pending_forward_err_redirect' => 'voucher',
    'voucher_pending_err_redirect' => 'voucher',
    'voucher_incoming_err_redirect' => 'voucher_incoming',
    'voucher_incoming_return_err_redirect' => 'voucher_incoming',
    'voucher_forwarding_return_redirect' => 'voucher_forwarding',
    'voucher_forwarding_return_err_redirect' => 'voucher_forwarding',
    'voucher_sent_err_redirect'  => 'voucher_sent',
    'ada_save_err'              => 'voucher_ada',

    // Designations management
    'designation_success'        => 'designations',
    'designation_err'           => 'designations',
];

/**
 * Resolve a process/err handler redirect code to a full public URL.
 */
function get_redirect_url_by_code(string $code): ?string
{
    $codeToKey = $GLOBALS['REDIRECT_CODE_TO_KEY'] ?? [];
    if (!isset($codeToKey[$code])) {
        return null;
    }

    return get_redirect_url($codeToKey[$code]);
}

/**
 * Get full URL for a redirect key (public page).
 */
function get_redirect_url($key)
{
    $map = $GLOBALS['REDIRECT_MAP'];
    if (!isset($map[$key]) || $map[$key] === null) {
        return null;
    }
    $base = redirect_base_url();
    $public = redirect_public_path();
    return $base . '/' . $public . '/' . $map[$key];
}

/**
 * Get full URL for internal redirect (e.g. 404 pages under protected/).
 * $fromProtectedDir: path relative to protected/ (e.g. routes/404.php).
 */
function get_redirect_internal_url($pathFromProtected)
{
    $base = redirect_base_url();
    return $base . '/protected/' . $pathFromProtected;
}

/**
 * Perform PHP redirect and exit.
 */
function redirect_to($key)
{
    $url = get_redirect_url($key);
    if ($url === null) {
        http_response_code(500);
        echo 'Redirect target is not configured.';
        exit;
    }

    if (!headers_sent()) {
        header('Location: ' . $url);
        exit;
    }

    echo '<script>window.location.href=' . json_encode($url) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"></noscript>';
    exit;
}

/**
 * Redirect using a handler/process alert code (e.g. voucher_incoming_redirect).
 *
 * @return never
 */
function redirect_to_by_code(string $code): void
{
    $url = get_redirect_url_by_code($code);
    if ($url === null) {
        http_response_code(500);
        echo 'Redirect target is not configured for code: ' . htmlspecialchars($code, ENT_QUOTES, 'UTF-8');
        exit;
    }

    if (!headers_sent()) {
        header('Location: ' . $url);
        exit;
    }

    echo '<script>window.location.replace(' . json_encode($url) . ');</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"></noscript>';
    exit;
}

/**
 * Get full URL for internal redirect key (e.g. 404 pages).
 */
function get_redirect_internal_url_by_key($internalKey)
{
    $map = $GLOBALS['REDIRECT_INTERNAL_MAP'];
    if (!isset($map[$internalKey])) {
        return null;
    }
    return redirect_base_url() . '/protected/' . $map[$internalKey];
}

/**
 * Perform PHP redirect to internal route (e.g. 404) and exit.
 */
function redirect_to_internal($internalKey)
{
    $url = get_redirect_internal_url_by_key($internalKey);
    if ($url === null) {
        http_response_code(500);
        echo 'Internal redirect target is not configured.';
        exit;
    }

    if (!headers_sent()) {
        header('Location: ' . $url);
        exit;
    }

    echo '<script>window.location.href=' . json_encode($url) . ';</script>';
    echo '<noscript><meta http-equiv="refresh" content="0;url=' . htmlspecialchars($url, ENT_QUOTES, 'UTF-8') . '"></noscript>';
    exit;
}

/**
 * Build redirect map for JavaScript: code => full URL.
 * Used by custom_process_alert.php and err_handler_custom_alert.php.
 */
function get_redirect_map_js()
{
    $codeToKey = $GLOBALS['REDIRECT_CODE_TO_KEY'];
    $out = [];
    foreach ($codeToKey as $code => $key) {
        $url = get_redirect_url($key);
        if ($url !== null) {
            $out[$code] = $url;
        }
    }
    // Logout and other special URLs
    $base = redirect_base_url();
    $out['logout'] = $base . '/protected/core/components/security/logout_handler.inc.php';
    return $out;
}

/**
 * JSON-encoded redirect map for inline script.
 */
function get_redirect_map_js_json()
{
    return json_encode(get_redirect_map_js(), JSON_UNESCAPED_SLASHES);
}
