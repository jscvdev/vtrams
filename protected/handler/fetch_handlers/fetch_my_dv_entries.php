<?php
declare(strict_types=1);

require __DIR__ . '/../../core/components/security/err_blocker.inc.php';
require __DIR__ . '/../../dbconnection.inc.php';
require __DIR__ . '/../../core/components/security/config_session.inc.php';
require __DIR__ . '/../../core/components/security/router.inc.php';
require_once __DIR__ . '/../../core/components/helpers/cursor_pagination_helper.php';
require_once __DIR__ . '/../../core/components/security/filter_input.inc.php';

$jsonFlags = JSON_UNESCAPED_UNICODE;
if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
    $jsonFlags |= JSON_INVALID_UTF8_SUBSTITUTE;
}

try {
    $encodedBy = (string) ($_SESSION['logged_user_emp_name'] ?? '');
    $encodedByEsc = $encodedBy !== '' ? htmlspecialchars($encodedBy, ENT_QUOTES, 'UTF-8') : '';
    $encodedById = (string) ($_SESSION['logged_user_emp_id'] ?? '');

    if ($encodedBy === '' && $encodedById === '') {
        http_response_code(401);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['error' => 'Not authenticated'], $jsonFlags);
        exit;
    }

    $maxTotal = 100;
    $perPage = clamp_int($_GET['per_page'] ?? null, 1, 50, 50);
    $page = isset($_GET['page']) ? max(1, (int) $_GET['page']) : 1;

    $rawQ = (string) ($_GET['q'] ?? '');
    $q = filterInput($rawQ);
    if (trim($rawQ) !== '' && $q === '') {
        http_response_code(400);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['error' => 'Invalid search query'], $jsonFlags);
        exit;
    }

    $byParts = [];
    $params = [];
    if ($encodedBy !== '') {
        $byParts[] = 'encoded_by = :encoded_by';
        $params[':encoded_by'] = [$encodedBy, PDO::PARAM_STR];
    }
    if ($encodedByEsc !== '' && $encodedByEsc !== $encodedBy) {
        $byParts[] = 'encoded_by = :encoded_by_esc';
        $params[':encoded_by_esc'] = [$encodedByEsc, PDO::PARAM_STR];
    }
    if ($encodedById !== '') {
        $byParts[] = 'encoded_by = :encoded_by_id';
        $params[':encoded_by_id'] = [$encodedById, PDO::PARAM_STR];
    }
    if (!$byParts) {
        http_response_code(401);
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode(['error' => 'Not authenticated'], $jsonFlags);
        exit;
    }
    $baseWhere = '(' . implode(' OR ', $byParts) . ')';

    $searchSql = '';
    if ($q !== '') {
        $pat = '%' . $q . '%';
        $cols = [
            'processing_no',
            'payee',
            'address',
            'particulars',
            'dv_no',
            'ada_check_no',
            'ors_no',
            'tin_employee_no',
            'voucher_type',
            'return_remarks',
            'office_from',
            'encoded_from',
        ];
        $parts = [];
        foreach ($cols as $i => $col) {
            $ph = ':sq' . $i;
            $parts[] = $col . ' LIKE ' . $ph;
            $params[$ph] = [$pat, PDO::PARAM_STR];
        }
        $parts[] = 'CAST(amount AS CHAR) LIKE :sq_amt';
        $params[':sq_amt'] = [$pat, PDO::PARAM_STR];
        $searchSql = ' AND (' . implode(' OR ', $parts) . ')';
    }

    $countStmt = $pdo->prepare('SELECT COUNT(*) FROM dv_entries WHERE ' . $baseWhere . $searchSql);
    foreach ($params as $key => $pair) {
        $countStmt->bindValue($key, $pair[0], $pair[1]);
    }
    $countStmt->execute();
    $dbTotal = (int) $countStmt->fetchColumn();

    $effectiveTotal = min($dbTotal, $maxTotal);
    $totalPages = $perPage > 0 ? max(1, (int) ceil($effectiveTotal / $perPage)) : 1;
    $page = min($page, $totalPages);
    $offset = ($page - 1) * $perPage;

    $rows = [];
    $fetchLimit = 0;
    if ($effectiveTotal > 0) {
        $fetchLimit = min($perPage, max(0, $maxTotal - $offset));
    }
    if ($fetchLimit > 0) {
        $stmt = $pdo->prepare(
            'SELECT * FROM dv_entries WHERE ' . $baseWhere . $searchSql . ' ORDER BY processing_no DESC LIMIT :lim OFFSET :off'
        );
        foreach ($params as $key => $pair) {
            $stmt->bindValue($key, $pair[0], $pair[1]);
        }
        $stmt->bindValue(':lim', $fetchLimit, PDO::PARAM_INT);
        $stmt->bindValue(':off', $offset, PDO::PARAM_INT);
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    foreach ($rows as &$row) {
        $row['amount'] = trim((string) ($row['amount'] ?? ''));
        array_walk_recursive($row, static function (&$item): void {
            if (is_string($item) && !mb_check_encoding($item, 'UTF-8')) {
                $item = utf8_encode($item);
            }
        });
    }
    unset($row);

    header('Content-Type: application/json; charset=UTF-8');
    $payload = [
        'data' => $rows,
        'page' => $page,
        'per_page' => $perPage,
        'total' => $effectiveTotal,
        'total_pages' => $totalPages,
    ];
    $encoded = json_encode($payload, $jsonFlags);
    if ($encoded === false) {
        throw new RuntimeException('JSON encode failed: ' . json_last_error_msg());
    }
    echo $encoded;
} catch (Throwable $e) {
    header('Content-Type: application/json; charset=UTF-8');
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'detail' => $e->getMessage()], $jsonFlags);
}

