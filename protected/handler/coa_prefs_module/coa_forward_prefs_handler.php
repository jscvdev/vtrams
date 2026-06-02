<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../dbconnection.inc.php';
require_once __DIR__ . '/../../core/components/security/config_session.inc.php';
require_once __DIR__ . '/coa_forward_prefs.model.inc.php';

function coa_forward_prefs_json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

$empId = trim((string)($_SESSION['logged_user_emp_id'] ?? ''));
if ($empId === '') {
    coa_forward_prefs_json_response(['ok' => false, 'error' => 'Unauthorized'], 401);
}

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

if ($method === 'GET') {
    $voucherType = trim((string)($_GET['voucher_type'] ?? ''));
    if ($voucherType === '') {
        coa_forward_prefs_json_response(['ok' => false, 'error' => 'voucher_type is required'], 400);
    }

    if (!coa_forward_prefs_is_available($pdo)) {
        coa_forward_prefs_json_response([
            'ok' => true,
            'voucher_type' => $voucherType,
            'items' => [],
        ]);
    }

    try {
        $items = coa_forward_prefs_get($pdo, $empId, $voucherType);
        coa_forward_prefs_json_response([
            'ok' => true,
            'voucher_type' => $voucherType,
            'items' => $items ?? [],
        ]);
    } catch (PDOException $e) {
        error_log('coa_forward_prefs GET: ' . $e->getMessage());
        coa_forward_prefs_json_response(['ok' => false, 'error' => 'Database error'], 500);
    }
}

if ($method === 'POST') {
    $input = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($input)) {
        coa_forward_prefs_json_response(['ok' => false, 'error' => 'Invalid JSON body'], 400);
    }

    $token = (string)($input['token'] ?? '');
    if ($token === '' || !isset($_SESSION['token']) || !hash_equals((string)$_SESSION['token'], $token)) {
        coa_forward_prefs_json_response(['ok' => false, 'error' => 'Invalid token'], 403);
    }

    $voucherType = trim((string)($input['voucher_type'] ?? ''));
    if ($voucherType === '') {
        coa_forward_prefs_json_response(['ok' => false, 'error' => 'voucher_type is required'], 400);
    }

    $selectedOptions = $input['selected_options'] ?? null;
    if (!is_array($selectedOptions) || count($selectedOptions) === 0) {
        coa_forward_prefs_json_response(['ok' => false, 'error' => 'selected_options must be a non-empty array'], 400);
    }

    $encoded = json_encode($selectedOptions, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($encoded === false) {
        coa_forward_prefs_json_response(['ok' => false, 'error' => 'Could not encode selections'], 400);
    }

    if (!coa_forward_prefs_is_available($pdo)) {
        coa_forward_prefs_json_response([
            'ok' => false,
            'error' => 'Saved checklist preferences are not available. Run the user_coa_forward_prefs database migration.',
        ], 503);
    }

    try {
        if (!coa_forward_prefs_save($pdo, $empId, $voucherType, $encoded)) {
            coa_forward_prefs_json_response(['ok' => false, 'error' => 'Could not save preferences'], 500);
        }
        coa_forward_prefs_json_response([
            'ok' => true,
            'voucher_type' => $voucherType,
            'items' => $selectedOptions,
        ]);
    } catch (PDOException $e) {
        error_log('coa_forward_prefs POST: ' . $e->getMessage());
        coa_forward_prefs_json_response(['ok' => false, 'error' => 'Database error'], 500);
    }
}

coa_forward_prefs_json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
