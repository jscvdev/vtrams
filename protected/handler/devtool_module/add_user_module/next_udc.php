<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../../dbconnection.inc.php';
require_once __DIR__ . '/../../../core/components/security/config_session.inc.php';
require_once __DIR__ . '/../../../core/components/helpers/udc_generator_helper.inc.php';

if (empty($_SESSION['logged_user_emp_name'])) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

try {
    echo json_encode(['udc' => generate_unique_udc($pdo)]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Unable to generate UDC']);
}
