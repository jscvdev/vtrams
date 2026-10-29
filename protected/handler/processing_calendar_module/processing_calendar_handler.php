<?php

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');

require __DIR__ . '/../../core/components/security/err_blocker.inc.php';
require __DIR__ . '/../../dbconnection.inc.php';
require __DIR__ . '/../../core/components/security/config_session.inc.php';
require __DIR__ . '/../../core/components/security/router.inc.php';
require_once __DIR__ . '/../../core/components/security/access_control.inc.php';
require_once __DIR__ . '/../../core/components/helpers/processing_calendar_helper.inc.php';

function processing_calendar_json(array $payload, int $status = 200): void
{
    http_response_code($status);
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if (!AccessControl::canAccessOverviewReports()) {
    processing_calendar_json(['ok' => false, 'error' => 'Access denied'], 403);
}

$canEdit = AccessControl::hasRole('System Admin');
$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));

if ($method === 'GET') {
    $year = (int) ($_GET['year'] ?? date('Y'));
    $month = (int) ($_GET['month'] ?? date('n'));
    if ($year < 2000 || $year > 2100 || $month < 1 || $month > 12) {
        processing_calendar_json(['ok' => false, 'error' => 'Invalid month.'], 400);
    }

    try {
        processing_calendar_json([
            'ok' => true,
            'can_edit' => $canEdit,
            'year' => $year,
            'month' => $month,
            'office_hours' => processing_calendar_office_hours_meta(),
            'event_types' => processing_calendar_event_types(),
            'coverage_options' => processing_calendar_coverage_options(),
            'days' => processing_calendar_fetch_month($pdo, $year, $month),
        ]);
    } catch (Throwable $e) {
        error_log('processing_calendar GET: ' . $e->getMessage());
        processing_calendar_json(['ok' => false, 'error' => 'Server error'], 500);
    }
}

if ($method === 'POST') {
    if (!$canEdit) {
        processing_calendar_json(['ok' => false, 'error' => 'Only System Admin can edit the processing calendar.'], 403);
    }

    $input = json_decode((string) file_get_contents('php://input'), true);
    if (!is_array($input)) {
        processing_calendar_json(['ok' => false, 'error' => 'Invalid JSON body'], 400);
    }

    $token = (string) ($input['token'] ?? '');
    if ($token === '' || !isset($_SESSION['token']) || !hash_equals((string) $_SESSION['token'], $token)) {
        processing_calendar_json(['ok' => false, 'error' => 'Invalid token'], 403);
    }

    $action = strtolower(trim((string) ($input['action'] ?? 'save')));
    $empId = trim((string) ($_SESSION['logged_user_emp_id'] ?? ''));

    try {
        if ($action === 'delete') {
            $id = (int) ($input['id'] ?? 0);
            if (!processing_calendar_delete($pdo, $id)) {
                processing_calendar_json(['ok' => false, 'error' => 'Could not delete that calendar day.'], 400);
            }
            processing_calendar_json(['ok' => true]);
        }

        $result = processing_calendar_save($pdo, $input, $empId);
        if (empty($result['ok'])) {
            processing_calendar_json(['ok' => false, 'error' => (string) ($result['error'] ?? 'Save failed')], 400);
        }
        processing_calendar_json(['ok' => true, 'id' => (int) ($result['id'] ?? 0)]);
    } catch (Throwable $e) {
        error_log('processing_calendar POST: ' . $e->getMessage());
        processing_calendar_json(['ok' => false, 'error' => 'Server error'], 500);
    }
}

processing_calendar_json(['ok' => false, 'error' => 'Method not allowed'], 405);
