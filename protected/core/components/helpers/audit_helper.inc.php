<?php
// Use the main PDO connection used by the rest of the system
require_once __DIR__ . '/../../../dbconnection.inc.php';
require_once __DIR__ . '/audit.model.inc.php';

class AuditHelper {
    private static $auditModel = null;
    
    private static function getAuditModel() {
        if (self::$auditModel === null) {
            // Reuse global $pdo from dbconnection.inc.php
            global $pdo;
            self::$auditModel = new AuditModel($pdo);
        }
        return self::$auditModel;
    }
    
    public static function logActivity($actionType, $description, $additionalData = null, $employeeName = null, $processingNo = null) {
        try {
            // Get current user ID from session
            $userId = $_SESSION['user_id'] ?? null;
            
            // Get client information
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'unknown';
            $sessionId = session_id();
            $requestUri = $_SERVER['REQUEST_URI'] ?? 'unknown';
            
            // Prepare additional data as JSON
            $additionalDataJson = $additionalData ? json_encode($additionalData) : null;
            
            $logData = [
                'user_id' => $userId,
                'employee_name' => $employeeName,
                'action_type' => $actionType,
                'description' => $description,
                'processing_no' => $processingNo,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'session_id' => $sessionId,
                'request_uri' => $requestUri,
                'additional_data' => $additionalDataJson
            ];
            
            $auditModel = self::getAuditModel();
            return $auditModel->logActivity($logData);
            
        } catch (Exception $e) {
            // Log error but don't break the main functionality
            error_log("Audit logging error: " . $e->getMessage());
            return false;
        }
    }
    
    // Convenience methods for common actions
    public static function logLogin($username) {
        return self::logActivity('login', "User '{$username}' logged into the system", ['username' => $username]);
    }
    
    public static function logLogout($username) {
        return self::logActivity('logout', "User '{$username}' logged out of the system", ['username' => $username]);
    }
    
    public static function logEmployeeCreate($employeeName, $employeeId) {
        return self::logActivity('create', "Created new employee: {$employeeName}", ['employee_id' => $employeeId, 'employee_name' => $employeeName]);
    }
    
    public static function logEmployeeUpdate($employeeName, $employeeId) {
        return self::logActivity('update', "Updated employee information: {$employeeName}", ['employee_id' => $employeeId, 'employee_name' => $employeeName]);
    }
    
    public static function logEmployeeDelete($employeeName, $employeeId) {
        return self::logActivity('delete', "Deactivated employee: {$employeeName}", ['employee_id' => $employeeId, 'employee_name' => $employeeName]);
    }
    
    public static function logEmployeeHardDelete($employeeName, $employeeId) {
        return self::logActivity('hard_delete', "Permanently deleted employee: {$employeeName}", ['employee_id' => $employeeId, 'employee_name' => $employeeName]);
    }
    
    public static function logPayrollGenerate($periodName, $employeeCount) {
        return self::logActivity('generate', "Generated payroll for period: {$periodName}", ['period_name' => $periodName, 'employee_count' => $employeeCount]);
    }
    
    public static function logPayslipView($payslipId, $employeeName) {
        return self::logActivity('view', "Viewed payslip for: {$employeeName}", ['payslip_id' => $payslipId, 'employee_name' => $employeeName]);
    }
    
    public static function logPayslipPrint($payslipId, $employeeName) {
        return self::logActivity('print', "Printed payslip for: {$employeeName}", ['payslip_id' => $payslipId, 'employee_name' => $employeeName]);
    }
    
    public static function logPayslipExport($format, $recordCount) {
        return self::logActivity('export', "Exported payslips in {$format} format", ['format' => $format, 'record_count' => $recordCount]);
    }
    
    public static function logSettingsUpdate($settingKey, $oldValue, $newValue) {
        return self::logActivity('settings', "Updated setting '{$settingKey}'", ['setting_key' => $settingKey, 'old_value' => $oldValue, 'new_value' => $newValue]);
    }
    
    public static function logReportGenerate($reportType, $filters = []) {
        return self::logActivity('report', "Generated {$reportType} report", ['report_type' => $reportType, 'filters' => $filters]);
    }
    
    public static function logReportExport($reportType, $format, $filters = []) {
        return self::logActivity('export', "Exported {$reportType} report as {$format}", ['report_type' => $reportType, 'format' => $format, 'filters' => $filters]);
    }
    
    public static function logPageView($pageName) {
        // Page views are excluded from audit logs display, so we don't log them
        // Uncomment the line below if you want to log page views again
        // return self::logActivity('view', "Viewed {$pageName} page", ['page' => $pageName]);
        return false;
    }
    
    public static function logError($errorMessage, $context = []) {
        return self::logActivity('error', "System error: {$errorMessage}", array_merge(['error_message' => $errorMessage], $context));
    }
}
