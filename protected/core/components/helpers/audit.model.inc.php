<?php
class AuditModel
{
    private $conn;

    public function __construct($connection)
    {
        $this->conn = $connection;
    }

    public function getAllLogs()
    {
        $sql = "
            SELECT al.*,
                u.emp_id AS username,
                TRIM(CONCAT(COALESCE(u.emp_fn,''), ' ', COALESCE(u.emp_mi,''), ' ', COALESCE(u.emp_ln,''))) AS user_display_name
            FROM audit_logs al
            LEFT JOIN user_group u ON al.user_id = u.id
            WHERE NOT (al.action_type = 'view' AND LOWER(al.description) LIKE '%page%')
            ORDER BY al.created_at DESC
            LIMIT 1000
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLogById($id)
    {
        $sql = "
            SELECT al.*,
                u.emp_id AS username,
                TRIM(CONCAT(COALESCE(u.emp_fn,''), ' ', COALESCE(u.emp_mi,''), ' ', COALESCE(u.emp_ln,''))) AS user_display_name
            FROM audit_logs al
            LEFT JOIN user_group u ON al.user_id = u.id
            WHERE al.id = :id
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':id', $id, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function logActivity($data)
    {
        // Check if processing_no column exists
        $hasProcessingNo = false;
        try {
            $checkSql = "SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS 
                        WHERE TABLE_SCHEMA = DATABASE() 
                        AND TABLE_NAME = 'audit_logs' 
                        AND COLUMN_NAME = 'processing_no'";
            $checkStmt = $this->conn->prepare($checkSql);
            $checkStmt->execute();
            $hasProcessingNo = $checkStmt->fetchColumn() > 0;
        } catch (PDOException $e) {
            // If check fails, assume column doesn't exist and continue without it
            $hasProcessingNo = false;
        }

        if ($hasProcessingNo) {
            $sql = "
                INSERT INTO audit_logs (
                    user_id, employee_name, action_type, description, processing_no, ip_address, 
                    user_agent, session_id, request_uri, additional_data, created_at
                ) VALUES (
                    :user_id, :employee_name, :action_type, :description, :processing_no, :ip_address, 
                    :user_agent, :session_id, :request_uri, :additional_data, NOW()
                )
            ";
        } else {
            // Fallback SQL without processing_no column
            $sql = "
                INSERT INTO audit_logs (
                    user_id, employee_name, action_type, description, ip_address, 
                    user_agent, session_id, request_uri, additional_data, created_at
                ) VALUES (
                    :user_id, :employee_name, :action_type, :description, :ip_address, 
                    :user_agent, :session_id, :request_uri, :additional_data, NOW()
                )
            ";
        }

        try {
            $stmt = $this->conn->prepare($sql);
            $stmt->bindParam(':user_id', $data['user_id'], PDO::PARAM_INT);
            $stmt->bindParam(':employee_name', $data['employee_name']);
            $stmt->bindParam(':action_type', $data['action_type']);
            $stmt->bindParam(':description', $data['description']);
            if ($hasProcessingNo) {
                $processingNo = $data['processing_no'] ?? null;
                $stmt->bindParam(':processing_no', $processingNo);
            }
            $stmt->bindParam(':ip_address', $data['ip_address']);
            $stmt->bindParam(':user_agent', $data['user_agent']);
            $stmt->bindParam(':session_id', $data['session_id']);
            $stmt->bindParam(':request_uri', $data['request_uri']);
            $stmt->bindParam(':additional_data', $data['additional_data']);

            $stmt->execute();
            return $this->conn->lastInsertId();
        } catch (PDOException $e) {
            error_log("PDO Error in logActivity: " . $e->getMessage());
            error_log("SQL: " . $sql);
            error_log("Data: " . print_r($data, true));
            // Don't throw - just log the error so it doesn't break the main functionality
            return false;
        }
    }

    public function clearOldLogs($days)
    {
        $sql = "DELETE FROM audit_logs WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':days', $days, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->rowCount();
    }

    public function clearAllLogs()
    {
        $sql = "DELETE FROM audit_logs";
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->rowCount();
    }

    public function getLogsByUser($userId)
    {
        $sql = "
            SELECT al.*,
                u.emp_id AS username,
                TRIM(CONCAT(COALESCE(u.emp_fn,''), ' ', COALESCE(u.emp_mi,''), ' ', COALESCE(u.emp_ln,''))) AS user_display_name
            FROM audit_logs al
            LEFT JOIN user_group u ON al.user_id = u.id
            WHERE al.user_id = :user_id
            ORDER BY al.created_at DESC
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':user_id', $userId, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLogsByAction($actionType)
    {
        $sql = "
            SELECT al.*,
                u.emp_id AS username,
                TRIM(CONCAT(COALESCE(u.emp_fn,''), ' ', COALESCE(u.emp_mi,''), ' ', COALESCE(u.emp_ln,''))) AS user_display_name
            FROM audit_logs al
            LEFT JOIN user_group u ON al.user_id = u.id
            WHERE al.action_type = :action_type
            ORDER BY al.created_at DESC
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':action_type', $actionType);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getLogsByDateRange($startDate, $endDate)
    {
        $sql = "
            SELECT al.*,
                u.emp_id AS username,
                TRIM(CONCAT(COALESCE(u.emp_fn,''), ' ', COALESCE(u.emp_mi,''), ' ', COALESCE(u.emp_ln,''))) AS user_display_name
            FROM audit_logs al
            LEFT JOIN user_group u ON al.user_id = u.id
            WHERE DATE(al.created_at) BETWEEN :start_date AND :end_date
            ORDER BY al.created_at DESC
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':start_date', $startDate);
        $stmt->bindParam(':end_date', $endDate);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getErrorLogs()
    {
        $sql = "
            SELECT al.*,
                u.emp_id AS username,
                TRIM(CONCAT(COALESCE(u.emp_fn,''), ' ', COALESCE(u.emp_mi,''), ' ', COALESCE(u.emp_ln,''))) AS user_display_name
            FROM audit_logs al
            LEFT JOIN user_group u ON al.user_id = u.id
            WHERE al.action_type = 'error'
            ORDER BY al.created_at DESC
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getRecentActivity($limit = 50)
    {
        $sql = "
            SELECT al.*,
                u.emp_id AS username,
                TRIM(CONCAT(COALESCE(u.emp_fn,''), ' ', COALESCE(u.emp_mi,''), ' ', COALESCE(u.emp_ln,''))) AS user_display_name
            FROM audit_logs al
            LEFT JOIN user_group u ON al.user_id = u.id
            ORDER BY al.created_at DESC
            LIMIT :limit
        ";

        $stmt = $this->conn->prepare($sql);
        $stmt->bindParam(':limit', $limit, PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAuditStatistics()
    {
        $stats = [];
        
        $excludeCondition = "NOT (action_type = 'view' AND LOWER(description) LIKE '%page%')";

        // Total logs
        $sql = "SELECT COUNT(*) as total FROM audit_logs WHERE " . $excludeCondition;
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $stats['total_logs'] = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Today's logs
        $sql = "SELECT COUNT(*) as today FROM audit_logs WHERE DATE(created_at) = CURDATE() AND " . $excludeCondition;
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $stats['today_logs'] = $stmt->fetch(PDO::FETCH_ASSOC)['today'];

        // Error logs
        $sql = "SELECT COUNT(*) as errors FROM audit_logs WHERE action_type = 'error' AND " . $excludeCondition;
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $stats['error_logs'] = $stmt->fetch(PDO::FETCH_ASSOC)['errors'];

        // Active users (last 7 days)
        $sql = "SELECT COUNT(DISTINCT user_id) as active FROM audit_logs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) AND " . $excludeCondition;
        $stmt = $this->conn->prepare($sql);
        $stmt->execute();
        $stats['active_users'] = $stmt->fetch(PDO::FETCH_ASSOC)['active'];

        return $stats;
    }
}
