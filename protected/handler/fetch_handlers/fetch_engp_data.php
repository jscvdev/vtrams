<?php
declare(strict_types=1);

require __DIR__ . '/../../core/components/security/err_blocker.inc.php';
require __DIR__ . '/../../dbconnection.inc.php';
require __DIR__ . '/../../core/components/security/config_session.inc.php';
require __DIR__ . '/../../core/components/security/router.inc.php';
require_once __DIR__ . '/../../core/components/security/filter_input.inc.php';
require_once __DIR__ . '/../../core/components/helpers/cursor_pagination_helper.php';

header('Content-Type: application/json; charset=UTF-8');

$municipality = isset($_GET['municipality']) && $_GET['municipality'] !== 'all' ? $_GET['municipality'] : null;
$commodity = isset($_GET['commodity']) && $_GET['commodity'] !== 'all' ? $_GET['commodity'] : null;
$year = isset($_GET['year']) && $_GET['year'] !== 'all' ? $_GET['year'] : null;
$quarter = isset($_GET['quarter']) && $_GET['quarter'] !== 'all' ? $_GET['quarter'] : null;
$cyear = isset($_GET['cyear']) && $_GET['cyear'] !== 'all' ? $_GET['cyear'] : null;
$month = isset($_GET['month']) && $_GET['month'] !== 'all' ? $_GET['month'] : null;
$day = isset($_GET['day']) && $_GET['day'] !== 'all' ? $_GET['day'] : null;
$yearDate = isset($_GET['yearDate']) && $_GET['yearDate'] !== 'all' ? $_GET['yearDate'] : null;
$week = isset($_GET['week']) && $_GET['week'] !== 'all' ? $_GET['week'] : null;

$rawQ = (string) ($_GET['q'] ?? '');
$q = filterInput($rawQ);
if (trim($rawQ) !== '' && $q === '') {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid search query']);

    exit;
}

$perPage = clamp_int($_GET['per_page'] ?? null, 1, 50, 50);
$maxBrowse = 100;
$page = max(1, (int) ($_GET['page'] ?? 1));

$query = "SELECT 
    v.id,
    v.processing_no,
    v.dv_no,
    v.ada_check_no,
    v.payee,
    v.tin_employee_no,
    v.address,
    v.municipality,
    v.barangay,
    v.commodity,
    v.amount,
    v.voucher_type,
    v.voucher_date,
    v.area,
    v.noseed,
    v.particulars,
    v.datetime_encoded,
    v.encoded_from,
    v.encoded_by,
    NULL as year,
    NULL as quarter,
    NULL as cy,
    NULL as received_date,
    NULL as returned_date,
    NULL as transmitted_date,
    NULL as paid_date,
    NULL as quarter_remarks
FROM vouchers v 
WHERE v.voucher_type = 'eNGP'";

$params = [];

if ($municipality) {
    $query .= ' AND v.municipality = :municipality';
    $params[':municipality'] = $municipality;
}

if ($commodity) {
    $query .= ' AND v.commodity = :commodity';
    $params[':commodity'] = $commodity;
}

if ($month || $day || $yearDate) {
    if ($month && $day && $yearDate) {
        $dateStr = sprintf('%s-%s-%s', $yearDate, $month, $day);
        $query .= ' AND DATE(v.voucher_date) = :date_filter';
        $params[':date_filter'] = $dateStr;
    } elseif ($month && $yearDate) {
        $query .= ' AND MONTH(v.voucher_date) = :month AND YEAR(v.voucher_date) = :yearDate';
        $params[':month'] = (int) $month;
        $params[':yearDate'] = (int) $yearDate;
    } elseif ($yearDate) {
        $query .= ' AND YEAR(v.voucher_date) = :yearDate';
        $params[':yearDate'] = (int) $yearDate;
    }
}

$searchSql = '';
if ($q !== '') {
    $pat = '%' . $q . '%';
    $cols = ['processing_no', 'dv_no', 'ada_check_no', 'payee', 'tin_employee_no', 'address', 'particulars', 'municipality', 'commodity'];
    $parts = [];
    foreach ($cols as $i => $col) {
        $ph = ':sq' . $i;
        $parts[] = 'v.`' . $col . '` LIKE ' . $ph;
        $params[$ph] = $pat;
    }
    $searchSql = ' AND (' . implode(' OR ', $parts) . ')';
}
$query .= $searchSql;

$countBase = "SELECT COUNT(*) FROM vouchers v WHERE v.voucher_type = 'eNGP'";
$countQuery = $countBase;
$countParams = [];
if ($municipality) {
    $countQuery .= ' AND v.municipality = :municipality';
    $countParams[':municipality'] = $municipality;
}
if ($commodity) {
    $countQuery .= ' AND v.commodity = :commodity';
    $countParams[':commodity'] = $commodity;
}
if ($month || $day || $yearDate) {
    if ($month && $day && $yearDate) {
        $dateStr = sprintf('%s-%s-%s', $yearDate, $month, $day);
        $countQuery .= ' AND DATE(v.voucher_date) = :date_filter';
        $countParams[':date_filter'] = $dateStr;
    } elseif ($month && $yearDate) {
        $countQuery .= ' AND MONTH(v.voucher_date) = :month AND YEAR(v.voucher_date) = :yearDate';
        $countParams[':month'] = (int) $month;
        $countParams[':yearDate'] = (int) $yearDate;
    } elseif ($yearDate) {
        $countQuery .= ' AND YEAR(v.voucher_date) = :yearDate';
        $countParams[':yearDate'] = (int) $yearDate;
    }
}
if ($q !== '') {
    $pat = '%' . $q . '%';
    $cols = ['processing_no', 'dv_no', 'ada_check_no', 'payee', 'tin_employee_no', 'address', 'particulars', 'municipality', 'commodity'];
    $parts = [];
    foreach ($cols as $i => $col) {
        $ph = ':cq' . $i;
        $parts[] = 'v.`' . $col . '` LIKE ' . $ph;
        $countParams[$ph] = $pat;
    }
    $countQuery .= ' AND (' . implode(' OR ', $parts) . ')';
}

try {
    $countStmt = $pdo->prepare($countQuery);
    foreach ($countParams as $k => $v) {
        $countStmt->bindValue($k, $v);
    }
    $countStmt->execute();
    $dbCount = (int) $countStmt->fetchColumn();

    $displayTotal = min($dbCount, $maxBrowse);
    $totalPages = $displayTotal > 0 ? (int) ceil($displayTotal / $perPage) : 1;
    $page = min($page, max(1, $totalPages));
    $offset = ($page - 1) * $perPage;
    $fetchLimit = $displayTotal > 0 ? min($perPage, max(0, $maxBrowse - $offset)) : 0;

    $query .= ' ORDER BY v.voucher_date DESC LIMIT :lim OFFSET :off';
    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':lim', $fetchLimit, PDO::PARAM_INT);
    $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
    $stmt->execute();

    $fetched_data = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $particulars = $row['particulars'] ?? '';
        if (preg_match('/(\d+)(?:st|nd|rd|th)\s+Quarter/i', $particulars, $quarterMatch)) {
            $row['quarter'] = $quarterMatch[1] . (['1' => 'st', '2' => 'nd', '3' => 'rd', '4' => 'th'][$quarterMatch[1]] ?? 'th');
        }
        if (preg_match('/Year\s+(\d+)/i', $particulars, $yearMatch)) {
            $row['year'] = $yearMatch[1];
        }
        if (preg_match('/C\.Y\s+(\d{4})/i', $particulars, $cyMatch)) {
            $row['cy'] = $cyMatch[1];
        }
        array_walk_recursive($row, static function (&$item): void {
            if (is_string($item) && !mb_check_encoding($item, 'UTF-8')) {
                $item = utf8_encode($item);
            }
        });
        $fetched_data[] = $row;
    }

    echo json_encode([
        'data' => $fetched_data,
        'page' => $page,
        'per_page' => $perPage,
        'total' => $displayTotal,
        'total_pages' => $totalPages,
    ], JSON_UNESCAPED_UNICODE);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
}
