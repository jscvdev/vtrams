<?php
declare(strict_types=1);

require __DIR__ . '/../../core/components/security/err_blocker.inc.php';
require __DIR__ . '/../../dbconnection.inc.php';
require __DIR__ . '/../../core/components/security/config_session.inc.php';
require __DIR__ . '/../../core/components/security/router.inc.php';
require_once __DIR__ . '/../../core/components/helpers/utilities_signatory_helper.inc.php';

header('Content-Type: application/json; charset=UTF-8');

$jsonFlags = JSON_UNESCAPED_UNICODE;
if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
    $jsonFlags |= JSON_INVALID_UTF8_SUBSTITUTE;
}

try {
    if (trim((string) ($_SESSION['logged_user_emp_name'] ?? '')) === '') {
        http_response_code(401);
        echo json_encode(['error' => 'Not authenticated'], $jsonFlags);
        exit;
    }

    utilities_signatory_ensure_schema($pdo);

    $requestedOffice = (string) ($_GET['office'] ?? '');
    if (utilities_signatory_can_select_office()) {
        $office = utilities_signatory_resolve_office($pdo, $requestedOffice !== '' ? $requestedOffice : null);
    } else {
        $office = utilities_signatory_resolve_office($pdo, utilities_signatory_default_office());
    }

    $payload = utilities_build_dv_signatory_client_payload($pdo, $office);

    echo json_encode(array_merge($payload, [
        'office' => $office,
        'sessionOffice' => utilities_signatory_default_office(),
    ]), $jsonFlags);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to load signatories'], $jsonFlags);
}
