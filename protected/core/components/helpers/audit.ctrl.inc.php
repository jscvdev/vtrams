<?php
// UTF-8 Support for special characters (Ñ, á, é, etc.)
require_once __DIR__ . '/../../utf8_helper.inc.php';

require_once __DIR__ . '/../../../dbconnection.inc.php';
require_once __DIR__ . '/../../../core/components/security/config_session.inc.php';
require_once __DIR__ . '/audit.model.inc.php';

class AuditController
{
    private $auditModel;

    public function __construct()
    {
        global $pdo;
        $this->auditModel = new AuditModel($pdo);
    }

    private function requireSystemAdmin()
    {
        if (!isset($_SESSION['acl']) || (int) $_SESSION['acl'] < 999) {
            header('HTTP/1.1 403 Forbidden');
            header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Access denied. System Admin only.']);
            exit;
        }
    }

    public function handleRequest()
    {
        $action = $_GET['action'] ?? '';
        $restrictedActions = ['get_all', 'get_by_id', 'export', 'clear_old', 'clear_all'];
        if (in_array($action, $restrictedActions)) {
            $this->requireSystemAdmin();
        }

        switch ($action) {
            case 'get_all':
                $this->getAllLogs();
                break;
            case 'get_by_id':
                $this->getLogById();
                break;
            case 'export':
                $this->exportLogs();
                break;
            case 'clear_old':
                $this->clearOldLogs();
                break;
            case 'clear_all':
                $this->clearAllLogs();
                break;
            case 'log_activity':
                $this->logActivity();
                break;
            default:
                $this->sendResponse(false, 'Invalid action', null);
        }
    }

    private function getAllLogs()
    {
        try {
            $logs = $this->auditModel->getAllLogs();
            $this->sendResponse(true, 'Logs retrieved successfully', $logs);
        } catch (Exception $e) {
            $this->sendResponse(false, 'Error retrieving logs: ' . $e->getMessage(), null);
        }
    }

    private function getLogById()
    {
        $id = $_GET['id'] ?? 0;

        if (!$id) {
            $this->sendResponse(false, 'Log ID is required', null);
            return;
        }

        try {
            $log = $this->auditModel->getLogById($id);
            if ($log) {
                $this->sendResponse(true, 'Log retrieved successfully', $log);
            } else {
                $this->sendResponse(false, 'Log not found', null);
            }
        } catch (Exception $e) {
            $this->sendResponse(false, 'Error retrieving log: ' . $e->getMessage(), null);
        }
    }

    private function exportLogs()
    {
        try {
            $logs = $this->auditModel->getAllLogs();

            // Set headers for CSV download
            header('Content-Type: text/csv');
            header('Content-Disposition: attachment; filename="audit_logs_' . date('Y-m-d') . '.csv"');

            $output = fopen('php://output', 'w');

            // CSV headers
            fputcsv($output, [
                'ID',
                'User ID',
                'Username',
                'Action Type',
                'Description',
                'Processing No',
                'IP Address',
                'User Agent',
                'Session ID',
                'Request URI',
                'Additional Data',
                'Created At'
            ]);

            // CSV data
            foreach ($logs as $log) {
                fputcsv($output, [
                    $log['id'],
                    $log['user_id'],
                    $log['username'],
                    $log['action_type'],
                    $log['description'],
                    $log['processing_no'] ?? '',
                    $log['ip_address'],
                    $log['user_agent'],
                    $log['session_id'],
                    $log['request_uri'],
                    $log['additional_data'],
                    $log['created_at']
                ]);
            }

            fclose($output);
            exit;
        } catch (Exception $e) {
            $this->sendResponse(false, 'Error exporting logs: ' . $e->getMessage(), null);
        }
    }

    private function clearOldLogs()
    {
        try {
            $days = isset($_GET['days']) ? (int) $_GET['days'] : 30;
            $days = max(1, min(365, $days));
            $deletedCount = $this->auditModel->clearOldLogs($days);
            $this->sendResponse(true, "Cleared {$deletedCount} audit logs older than {$days} days", ['deleted_count' => $deletedCount]);
        } catch (Exception $e) {
            $this->sendResponse(false, 'Error clearing old logs: ' . $e->getMessage(), null);
        }
    }

    private function clearAllLogs()
    {
        try {
            $deletedCount = $this->auditModel->clearAllLogs();
            $this->sendResponse(true, "Cleared all {$deletedCount} audit logs", ['deleted_count' => $deletedCount]);
        } catch (Exception $e) {
            $this->sendResponse(false, 'Error clearing logs: ' . $e->getMessage(), null);
        }
    }

    private function logActivity()
    {
        $data = json_decode(file_get_contents('php://input'), true);

        $required = ['action_type', 'description'];
        foreach ($required as $field) {
            if (!isset($data[$field]) || empty($data[$field])) {
                $this->sendResponse(false, "Field '{$field}' is required", null);
                return;
            }
        }

        try {
            $logId = $this->auditModel->logActivity($data);
            $this->sendResponse(true, 'Activity logged successfully', ['log_id' => $logId]);
        } catch (Exception $e) {
            $this->sendResponse(false, 'Error logging activity: ' . $e->getMessage(), null);
        }
    }

    private function sendResponse($success, $message, $data = null)
    {
        header('Content-Type: application/json');
        echo utf8_json_encode([
            'status' => $success ? 'success' : 'error',
            'message' => $message,
            'data' => $data
        ]);
        exit;
    }
}

// Handle the request
$controller = new AuditController();
$controller->handleRequest();
