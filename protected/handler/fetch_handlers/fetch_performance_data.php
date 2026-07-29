<?php
/**
 * Fetch performance data from voucher_action_logs.
 * Returns counts of Forwarded, Processed, Returned, Received, Transmitted vouchers.
 * Uses COUNT(DISTINCT processing_no) to avoid duplicate entries (one voucher = one count per action type).
 */
require __DIR__ . '/../../core/components/security/err_blocker.inc.php';
require __DIR__ . '/../../dbconnection.inc.php';
require __DIR__ . '/../../core/components/security/config_session.inc.php';
require __DIR__ . '/../../core/components/security/router.inc.php';
require_once __DIR__ . '/../../core/components/security/filter_input.inc.php';
require_once __DIR__ . '/../../core/components/helpers/cursor_pagination_helper.php';
require_once __DIR__ . '/../../core/components/helpers/amount_helper.inc.php';

header('Content-Type: application/json; charset=utf-8');

$office = isset($_GET['office']) && $_GET['office'] !== 'all' ? trim($_GET['office']) : null;
$year   = isset($_GET['year']) && $_GET['year'] !== 'all' ? (int)$_GET['year'] : null;
$month  = isset($_GET['month']) && $_GET['month'] !== 'all' ? (int)$_GET['month'] : null;
$day    = isset($_GET['day']) && $_GET['day'] !== 'all' ? (int)$_GET['day'] : null;
// Individual performance: always restrict to logged-in user (no user filter in UI)
$user   = isset($_GET['user']) && $_GET['user'] !== 'all' ? trim($_GET['user']) : null;
if ($user === null && !empty($_SESSION['logged_user_emp_name'])) {
    $user = $_SESSION['logged_user_emp_name'];
}
$date   = isset($_GET['date']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $_GET['date']) ? $_GET['date'] : null;

$allowedActionTypes = ['Forwarded', 'Processed', 'Returned', 'Received', 'Transmitted', 'Other'];
$rawActionType = trim((string) ($_GET['action_type'] ?? 'all'));
$actionTypeFilter = in_array($rawActionType, $allowedActionTypes, true) ? $rawActionType : null;

$whereParts = ["1=1"];
$params = [];

// Office filter for office-wide views only. Individual performance is scoped by action_by
// so actions taken at other offices (e.g. liaison forwarding from CENRO/PAMO) are included.
if (!$user) {
    if ($office) {
        $whereParts[] = "office_from = :office";
        $params[':office'] = $office;
    } elseif (!empty($_SESSION['logged_user_office'])) {
        $whereParts[] = "office_from = :office";
        $params[':office'] = $_SESSION['logged_user_office'];
    }
}

if ($year && $year >= 1900 && $year <= 2100) {
    $whereParts[] = "YEAR(datetime_action) = :year";
    $params[':year'] = $year;
}
if ($month && $month >= 1 && $month <= 12) {
    $whereParts[] = "MONTH(datetime_action) = :month";
    $params[':month'] = $month;
}
if ($day && $day >= 1 && $day <= 31) {
    $whereParts[] = "DAY(datetime_action) = :day";
    $params[':day'] = $day;
}
if ($date) {
    $whereParts[] = "DATE(datetime_action) = :date";
    $params[':date'] = $date;
}
if ($user) {
    $whereParts[] = "action_by = :user";
    $params[':user'] = $user;
}

$rawQ = (string) ($_GET['q'] ?? '');
$q = filterInput($rawQ);
if (trim($rawQ) !== '' && $q === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid search query']);

    exit;
}
$fullTable = (string) ($_GET['full_table'] ?? '') === '1';
$tablePage = max(1, (int) ($_GET['table_page'] ?? 1));
$tablePerPage = clamp_int($_GET['table_per_page'] ?? null, 1, 50, 50);
$maxTableBrowse = 100;

// Action type mapping (one voucher per action type per day - DISTINCT processing_no)
$actionCase = "
    CASE
        WHEN action LIKE 'Forwarded%' OR action LIKE 'Forwarded By:%' THEN 'Forwarded'
        WHEN action LIKE 'Processed%' OR action LIKE 'Processed By:%' OR action LIKE 'Processed by:%' THEN 'Processed'
        WHEN action LIKE 'Returned%' OR action LIKE 'Returned by:%' THEN 'Returned'
        WHEN action LIKE 'Received%' OR action LIKE 'Received By:%' THEN 'Received'
        WHEN action LIKE 'Transmitted%' THEN 'Transmitted'
        ELSE 'Other'
    END
";
$actionCaseAliased = preg_replace('/\baction\b/', 'a.action', $actionCase);

if ($actionTypeFilter !== null) {
    $whereParts[] = "({$actionCase}) = :action_type";
    $params[':action_type'] = $actionTypeFilter;
}

$whereSQL = implode(" AND ", $whereParts);

try {
    // Overall counts (deduplicated by processing_no per action type)
    $sqlOverall = "
        SELECT
            $actionCase AS action_type,
            COUNT(DISTINCT processing_no) AS cnt
        FROM voucher_action_logs
        WHERE $whereSQL
        GROUP BY action_type
        ORDER BY action_type
    ";
    $stmt = $pdo->prepare($sqlOverall);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->execute();
    $overall = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $overall[$row['action_type']] = (int)$row['cnt'];
    }

    $overallAmount = [];
    $sqlAmountOverall = "
        SELECT
            action_type,
            SUM(voucher_amount) AS total_amount
        FROM (
            SELECT
                $actionCase AS action_type,
                processing_no,
                MAX(amount) AS voucher_amount
            FROM voucher_action_logs
            WHERE $whereSQL
            GROUP BY action_type, processing_no
        ) per_voucher
        GROUP BY action_type
    ";
    $stmt = $pdo->prepare($sqlAmountOverall);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $type = (string) ($row['action_type'] ?? 'Other');
        $sum = amount_resolve_charged_or_amount('', $row['total_amount'] ?? '');
        if ($sum !== '') {
            $overallAmount[$type] = $sum;
        }
    }

    $uniqueVoucherAmounts = [];
    $sqlUniqueAmounts = "
        SELECT processing_no, MAX(amount) AS amount
        FROM voucher_action_logs
        WHERE $whereSQL
        GROUP BY processing_no
    ";
    $stmt = $pdo->prepare($sqlUniqueAmounts);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->execute();
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $pn = trim((string) ($row['processing_no'] ?? ''));
        $amountStr = amount_resolve_charged_or_amount('', $row['amount'] ?? '');
        if ($pn !== '' && $amountStr !== '') {
            $uniqueVoucherAmounts[$pn] = $amountStr;
        }
    }

    // Daily breakdown for charts
    $sqlDaily = "
        SELECT
            DATE_FORMAT(datetime_action, '%Y-%m-%d') AS formatted_date,
            $actionCase AS action_type,
            COUNT(DISTINCT processing_no) AS cnt
        FROM voucher_action_logs
        WHERE $whereSQL
        GROUP BY formatted_date, action_type
        ORDER BY formatted_date, action_type
    ";
    $stmt = $pdo->prepare($sqlDaily);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->execute();
    $daily = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (!isset($daily[$row['formatted_date']])) {
            $daily[$row['formatted_date']] = [];
        }
        $daily[$row['formatted_date']][$row['action_type']] = (int)$row['cnt'];
    }

    // Table data: one row per processing_no (latest log row per voucher).
    $sqlTable = "
        SELECT
            a.processing_no,
            a.dv_no,
            a.payee,
            a.amount,
            $actionCaseAliased AS action_type,
            a.action_by,
            a.datetime_action
        FROM voucher_action_logs a
        INNER JOIN (
            SELECT processing_no, MAX(id) AS max_id
            FROM voucher_action_logs
            WHERE $whereSQL
            GROUP BY processing_no
        ) pick ON a.id = pick.max_id
        ORDER BY a.datetime_action DESC
    ";
    $stmt = $pdo->prepare($sqlTable);
    foreach ($params as $k => $v) {
        $stmt->bindValue($k, $v);
    }
    $stmt->execute();
    $tableRows = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $tableRows[] = [
            'processing_no' => $row['processing_no'],
            'dv_no' => $row['dv_no'],
            'payee' => $row['payee'],
            'amount' => format_amount_display(amount_resolve_charged_or_amount('', $row['amount'] ?? '')),
            'amount_raw' => amount_resolve_charged_or_amount('', $row['amount'] ?? ''),
            'action_type' => $row['action_type'],
            'action_by' => $row['action_by'],
            'datetime_action' => $row['datetime_action'],
        ];
    }

    $filteredTable = $tableRows;
    if ($q !== '') {
        $needle = strtolower($q);
        $filteredTable = array_values(array_filter($filteredTable, static function (array $r) use ($needle): bool {
            $hay = strtolower(implode(' ', [
                (string) ($r['processing_no'] ?? ''),
                (string) ($r['dv_no'] ?? ''),
                (string) ($r['payee'] ?? ''),
                (string) ($r['action_type'] ?? ''),
                (string) ($r['action_by'] ?? ''),
                (string) ($r['datetime_action'] ?? ''),
            ]));

            return str_contains($hay, $needle);
        }));
    }
    $totalFiltered = count($filteredTable);
    if ($fullTable) {
        $tableOut = $filteredTable;
        $tableMeta = [
            'page' => 1,
            'per_page' => count($tableOut),
            'total' => $totalFiltered,
            'total_pages' => 1,
            'capped' => false,
        ];
    } else {
        $cappedTotal = min($totalFiltered, $maxTableBrowse);
        $sliceSource = array_slice($filteredTable, 0, $maxTableBrowse);
        $totalPages = $cappedTotal > 0 ? (int) ceil($cappedTotal / $tablePerPage) : 1;
        $tablePage = min($tablePage, max(1, $totalPages));
        $off = ($tablePage - 1) * $tablePerPage;
        $lim = $cappedTotal > 0 ? min($tablePerPage, max(0, $maxTableBrowse - $off)) : 0;
        $tableOut = $lim > 0 ? array_slice($sliceSource, $off, $lim) : [];
        $tableMeta = [
            'page' => $tablePage,
            'per_page' => $tablePerPage,
            'total' => $cappedTotal,
            'total_pages' => $totalPages,
            'capped' => $totalFiltered > $maxTableBrowse,
        ];
    }

    // Distinct users for filter dropdown (scoped to logged user's office)
    $users = [];
    $officeForUsers = $office ?? ($_SESSION['logged_user_office'] ?? null);
    if ($officeForUsers) {
        $sqlUsers = "SELECT DISTINCT action_by FROM voucher_action_logs WHERE office_from = :office ORDER BY action_by";
        $stmtUsers = $pdo->prepare($sqlUsers);
        $stmtUsers->bindValue(':office', $officeForUsers);
        $stmtUsers->execute();
        while ($r = $stmtUsers->fetch(PDO::FETCH_ASSOC)) {
            $users[] = $r['action_by'];
        }
    }

    $totalAmount = '0';
    $seenAmountPn = [];
    foreach ($filteredTable as $i => $r) {
        $pn = trim((string) ($r['processing_no'] ?? ''));
        if ($pn !== '' && !isset($seenAmountPn[$pn])) {
            $seenAmountPn[$pn] = true;
            $amt = (string) ($r['amount_raw'] ?? '');
            if ($amt === '' && isset($uniqueVoucherAmounts[$pn])) {
                $amt = $uniqueVoucherAmounts[$pn];
            }
            if ($amt !== '') {
                $totalAmount = bcadd($totalAmount, $amt, 2);
            }
        }
        unset($filteredTable[$i]['amount_raw']);
    }
    foreach ($tableOut as $i => $r) {
        unset($tableOut[$i]['amount_raw']);
    }

    $overallAmountFormatted = [];
    foreach ($overallAmount as $type => $sum) {
        $overallAmountFormatted[$type] = format_amount_display($sum);
    }

    echo json_encode([
        'overall' => $overall,
        'overall_amount' => $overallAmountFormatted,
        'total_amount' => format_amount_display($totalAmount),
        'daily' => $daily,
        'table' => $tableOut,
        'table_meta' => $tableMeta,
        'users' => $users,
        'filters' => [
            'office' => $office,
            'year' => $year,
            'month' => $month,
            'day' => $day,
            'user' => $user,
            'date' => $date,
            'q' => $q,
            'action_type' => $actionTypeFilter ?? 'all',
        ],
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
