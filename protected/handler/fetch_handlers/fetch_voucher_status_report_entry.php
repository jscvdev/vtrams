<?php

declare(strict_types=1);

require __DIR__ . '/../../core/components/security/err_blocker.inc.php';
require __DIR__ . '/../../dbconnection.inc.php';
require __DIR__ . '/../../core/components/security/config_session.inc.php';
require __DIR__ . '/../../core/components/security/router.inc.php';
require_once __DIR__ . '/../../core/components/helpers/voucher_status_report_helper.inc.php';

header('Content-Type: application/json; charset=UTF-8');

if (!class_exists('AccessControl') || !AccessControl::canAccessOverviewReports()) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Access denied']);
    exit;
}

$processingNo = trim((string) ($_GET['processing_no'] ?? ''));
if ($processingNo === '') {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing processing_no']);
    exit;
}

$scope = voucher_status_report_scope($pdo, trim((string) ($_SESSION['logged_user_office'] ?? '')));
try {
    $entry = voucher_status_report_fetch_entry_detail($pdo, $scope, $processingNo);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to load voucher']);
    exit;
}
if ($entry === null) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Voucher not found']);
    exit;
}

echo json_encode([
    'success' => true,
    'entry' => $entry,
], JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
