<?php
// test_fetch.php
header('Content-Type: application/json; charset=utf-8');
include("core/components/security/config_session.inc.php");
ob_start();

ini_set('display_errors', 0);
error_reporting(E_ALL);
mysqli_report(MYSQLI_REPORT_OFF);

set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

register_shutdown_function(function () {
    $err = error_get_last();
    if ($err !== null) {
        http_response_code(500);
        @ob_end_clean();
        echo json_encode(['error' => 'Fatal error', 'message' => $err['message']]);
        exit;
    }
});

try {
    $conn = new mysqli("localhost", "root", "", "tempodb");
    if ($conn->connect_error) {
        throw new Exception('Database connection failed: ' . $conn->connect_error);
    }

    // ===== FILTER HANDLING =====
    $office = (!empty($_GET['office']) && $_GET['office'] !== 'all') ? trim($_GET['office']) : null;
    $year   = (!empty($_GET['year']) && $_GET['year'] !== 'all') ? intval($_GET['year']) : null;
    if ($year && ($year < 1900 || $year > 2100)) $year = null;
    $month  = (!empty($_GET['month']) && $_GET['month'] !== 'all') ? intval($_GET['month']) : null;
    if ($month && ($month < 1 || $month > 12)) $month = null;
    $user   = (!empty($_GET['user']) && $_GET['user'] !== 'all') ? trim($_GET['user']) : null;

    // ===== WHERE CLAUSES =====
    // For daily/monthly chart
    $whereParts = [];
    if ($office) $whereParts[] = "office_from LIKE '%" . $conn->real_escape_string($office) . "%'";
    if ($year)   $whereParts[] = "YEAR(datetime_action) = " . intval($year);
    if ($month)  $whereParts[] = "MONTH(datetime_action) = " . intval($month);
    if ($user)   $whereParts[] = "action_by = '" . $conn->real_escape_string($user) . "'";
    $whereSQL = $whereParts ? "WHERE " . implode(" AND ", $whereParts) : "";

    // ===== DAILY DATA =====
    $dailyData = [];
    $sqlDaily = "
        SELECT
            DATE_FORMAT(datetime_action, '%Y-%m-%d') AS formatted_date,
            CASE
                WHEN action LIKE 'Forwarded%' THEN 'Forwarded'
                WHEN action LIKE 'Returned%'  THEN 'Returned'
                WHEN action LIKE 'Received%'  THEN 'Received'
                WHEN action LIKE 'Archived%'  THEN 'Archived'
                ELSE 'Other'
            END AS document_status,
            COUNT(DISTINCT document_id) AS count
        FROM action_logs
        $whereSQL
        GROUP BY formatted_date, document_status
        ORDER BY formatted_date, document_status
    ";
    if ($result = $conn->query($sqlDaily)) {
        while ($row = $result->fetch_assoc()) {
            $dailyData[$row['formatted_date']][$row['document_status']] = (int)$row['count'];
        }
        $result->free();
    }

    // ===== MONTHLY DATA =====
    $monthlyData = [];
    $sqlMonthly = "
        SELECT
            DATE_FORMAT(datetime_action, '%Y-%m') AS formatted_month,
            CASE
                WHEN action LIKE 'Forwarded%' THEN 'Forwarded'
                WHEN action LIKE 'Returned%'  THEN 'Returned'
                WHEN action LIKE 'Received%'  THEN 'Received'
                WHEN action LIKE 'Archived%'  THEN 'Archived'
                ELSE 'Other'
            END AS document_status,
            COUNT(DISTINCT document_id) AS count
        FROM action_logs
        $whereSQL
        GROUP BY formatted_month, document_status
        ORDER BY formatted_month, document_status
    ";
    if ($result = $conn->query($sqlMonthly)) {
        while ($row = $result->fetch_assoc()) {
            $monthlyData[$row['formatted_month']][$row['document_status']] = (int)$row['count'];
        }
        $result->free();
    }

    // ===== OVERALL DATA =====
    // Always from ALL data (no filters) so doughnut chart & stat cards show total
    // ===== OVERALL DATA (RESPECT FILTERS) =====
    $overallCounts = [];
    $sqlOverall = "
    SELECT
        CASE
            WHEN action LIKE 'Forwarded%' THEN 'Forwarded'
            WHEN action LIKE 'Returned%'  THEN 'Returned'
            WHEN action LIKE 'Received%'  THEN 'Received'
            WHEN action LIKE 'Archived%'  THEN 'Archived'
            ELSE 'Other'
        END AS document_status,
        COUNT(DISTINCT document_id) AS count
    FROM action_logs
    $whereSQL
    GROUP BY document_status
    ORDER BY document_status
";
    if ($resultOverall = $conn->query($sqlOverall)) {
        while ($row = $resultOverall->fetch_assoc()) {
            $overallCounts[$row['document_status']] = (int)$row['count'];
        }
        $resultOverall->free();
    }


    $recentActions = [];
    $loggedUserOffice = $_SESSION['logged_user_office'] ?? null;

    $recentActions = [];

    // Get logged user's office from session
    $loggedUserOffice = $_SESSION['logged_user_office'] ?? '';

    $sqlRecent = "
SELECT 
    document_id,
    document_title,
    action,
    datetime_action
FROM action_logs
WHERE 1=1
";

    // Always filter by logged user's office if available
    if (!empty($loggedUserOffice)) {
        $sqlRecent .= " AND action_by_from = '" . $conn->real_escape_string($loggedUserOffice) . "'";
    }

    // Year filter
    if (!empty($_GET['year']) && $_GET['year'] != "all") {
        $sqlRecent .= " AND YEAR(datetime_action) = " . intval($_GET['year']);
    }

    // Month filter
    if (!empty($_GET['month']) && $_GET['month'] != "all") {
        $sqlRecent .= " AND MONTH(datetime_action) = " . intval($_GET['month']);
    }

    // User filter — only apply if a user is selected
    if (!empty($_GET['user'])) {
        $sqlRecent .= " AND action_by = '" . $conn->real_escape_string($_GET['user']) . "'";
    }

    $sqlRecent .= " ORDER BY datetime_action DESC LIMIT 5";

    $resultRecent = $conn->query($sqlRecent);

    // Push results into array
    if ($resultRecent && $resultRecent->num_rows > 0) {
        while ($row = $resultRecent->fetch_assoc()) {
            $recentActions[] = [
                'document_id'     => $row['document_id'],
                'document_title'  => $row['document_title'],
                'action'          => $row['action'],
                'datetime_action' => $row['datetime_action'],
            ];
        }
        $resultRecent->free();
    }

    // Return JSON
    echo json_encode(['recent_actions' => $recentActions]);


    @ob_end_clean();
    echo json_encode([
        'daily'   => $dailyData,
        'monthly' => $monthlyData,
        'overall' => $overallCounts,
        'recent_actions'  => $recentActions,
        'debug_user'     => $sqlRecent ?? null
    ]);
    $conn->close();
} catch (Exception $ex) {
    @ob_end_clean();
    error_log("test_fetch.php Exception: " . $ex->getMessage());
    echo json_encode(['error' => 'Internal error', 'message' => substr($ex->getMessage(), 0, 200)]);
}
