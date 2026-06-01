<?php
declare(strict_types=1);

require __DIR__ . '/../../core/components/security/err_blocker.inc.php';
require __DIR__ . '/../../dbconnection.inc.php';
require __DIR__ . '/../../core/components/security/config_session.inc.php';
require __DIR__ . '/../../core/components/security/router.inc.php';
require_once __DIR__ . '/../../core/components/helpers/schema_cache_helper.inc.php';

// Get filter parameters
$voucher_type = isset($_GET['voucher_type']) && $_GET['voucher_type'] !== 'all' ? $_GET['voucher_type'] : null;
$municipality = isset($_GET['municipality']) && $_GET['municipality'] !== 'all' ? $_GET['municipality'] : null;
$commodity = isset($_GET['commodity']) && $_GET['commodity'] !== 'all' ? $_GET['commodity'] : null;
$month = isset($_GET['month']) && $_GET['month'] !== 'all' ? $_GET['month'] : null;
$day = isset($_GET['day']) && $_GET['day'] !== 'all' ? $_GET['day'] : null;
$yearDate = isset($_GET['yearDate']) && $_GET['yearDate'] !== 'all' ? $_GET['yearDate'] : null;

function db_table_exists(PDO $pdo, string $table): bool
{
    return schema_table_exists($pdo, $table);
}

/**
 * @return array<string, bool>
 */
function table_column_map(PDO $pdo, string $table): array
{
    return schema_table_column_map($pdo, $table);
}

function table_has_column(PDO $pdo, string $table, string $column): bool
{
    return schema_table_has_column($pdo, $table, $column);
}

function order_clause_for_table(PDO $pdo, string $table, string $alias): string
{
    if (table_has_column($pdo, $table, 'voucher_date')) {
        return ' ORDER BY ' . $alias . '.voucher_date DESC';
    }
    if (table_has_column($pdo, $table, 'id')) {
        return ' ORDER BY ' . $alias . '.id DESC';
    }
    if (table_has_column($pdo, $table, 'processing_no')) {
        return ' ORDER BY ' . $alias . '.processing_no DESC';
    }

    return '';
}

/**
 * Append shared filters; only references columns that exist on this table.
 *
 * @param array<string, mixed> $params
 */
function append_voucher_filters(
    PDO $pdo,
    string $table,
    string $alias,
    string &$query,
    array &$params,
    ?string $voucher_type,
    ?string $municipality,
    ?string $commodity,
    ?string $month,
    ?string $day,
    ?string $yearDate
): void {
    if ($voucher_type && table_has_column($pdo, $table, 'voucher_type')) {
        $query .= ' AND ' . $alias . '.voucher_type = :voucher_type';
        $params[':voucher_type'] = $voucher_type;
    }

    if ($municipality && table_has_column($pdo, $table, 'municipality')) {
        $query .= ' AND ' . $alias . '.municipality = :municipality';
        $params[':municipality'] = $municipality;
    }

    if ($commodity && table_has_column($pdo, $table, 'commodity')) {
        $query .= ' AND ' . $alias . '.commodity = :commodity';
        $params[':commodity'] = $commodity;
    }

    if ($month || $day || $yearDate) {
        if (($month && $day && $yearDate) && table_has_column($pdo, $table, 'voucher_date')) {
            $dateStr = sprintf('%s-%s-%s', $yearDate, $month, $day);
            $query .= ' AND DATE(' . $alias . '.voucher_date) = :date_filter';
            $params[':date_filter'] = $dateStr;
        } elseif (($month && $yearDate) && table_has_column($pdo, $table, 'voucher_date')) {
            $query .= ' AND MONTH(' . $alias . '.voucher_date) = :month AND YEAR(' . $alias . '.voucher_date) = :yearDate';
            $params[':month'] = (int) $month;
            $params[':yearDate'] = (int) $yearDate;
        } elseif ($yearDate && table_has_column($pdo, $table, 'voucher_date')) {
            $query .= ' AND YEAR(' . $alias . '.voucher_date) = :yearDate';
            $params[':yearDate'] = (int) $yearDate;
        }
    }
}

/**
 * @param array<string, mixed> $params
 * @return array<int, array<string, mixed>>
 */
function fetch_rows_for_table(
    PDO $pdo,
    string $table,
    string $alias,
    ?string $voucher_type,
    ?string $municipality,
    ?string $commodity,
    ?string $month,
    ?string $day,
    ?string $yearDate
): array {
    if (!db_table_exists($pdo, $table)) {
        return [];
    }

    $params = [];
    $query = 'SELECT ' . $alias . '.* FROM `' . $table . '` ' . $alias . ' WHERE 1=1';

    append_voucher_filters(
        $pdo,
        $table,
        $alias,
        $query,
        $params,
        $voucher_type,
        $municipality,
        $commodity,
        $month,
        $day,
        $yearDate
    );

    $query .= order_clause_for_table($pdo, $table, $alias);

    $stmt = $pdo->prepare($query);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();

    $out = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        array_walk_recursive($row, function (&$item) {
            if (!is_string($item) || $item === '') {
                return;
            }

            if (function_exists('mb_check_encoding') && function_exists('mb_convert_encoding')) {
                if (!mb_check_encoding($item, 'UTF-8')) {
                    $item = mb_convert_encoding($item, 'UTF-8');
                }

                return;
            }

            if (function_exists('iconv')) {
                $converted = @iconv('UTF-8', 'UTF-8//IGNORE', $item);
                if ($converted !== false) {
                    $item = $converted;
                }
            }
        });
        $out[] = $row;
    }

    return $out;
}

/**
 * Pending vouchers plus archived/completed rows (forwarded vouchers are removed from `vouchers`).
 *
 * @param array<int, array<string, mixed>> $rows
 * @return array<int, array<string, mixed>>
 */
function sort_rows_by_voucher_date_desc(array $rows): array
{
    usort($rows, static function (array $a, array $b): int {
        $da = (string) ($a['voucher_date'] ?? '');
        $db = (string) ($b['voucher_date'] ?? '');

        return strcmp($db, $da);
    });

    return $rows;
}

/**
 * Prefer the first row per processing_no (pending `vouchers` rows are merged before archives).
 *
 * @param array<int, array<string, mixed>> $rows
 * @return array<int, array<string, mixed>>
 */
function dedupe_by_processing_no(array $rows): array
{
    $seen = [];
    $out = [];
    foreach ($rows as $row) {
        $pn = isset($row['processing_no']) ? (string) $row['processing_no'] : '';
        if ($pn !== '') {
            if (isset($seen[$pn])) {
                continue;
            }
            $seen[$pn] = true;
        }
        $out[] = $row;
    }

    return $out;
}

try {
    // Whitelist: only these tables feed the analytics API
    $fetched_data = array_merge(
        fetch_rows_for_table($pdo, 'vouchers', 'v', $voucher_type, $municipality, $commodity, $month, $day, $yearDate),
        fetch_rows_for_table($pdo, 'voucher_archives', 'a', $voucher_type, $municipality, $commodity, $month, $day, $yearDate)
    );

    $fetched_data = dedupe_by_processing_no($fetched_data);
    $fetched_data = sort_rows_by_voucher_date_desc($fetched_data);

    header('Content-Type: application/json; charset=UTF-8');
    $jsonFlags = JSON_UNESCAPED_UNICODE;
    if (defined('JSON_INVALID_UTF8_SUBSTITUTE')) {
        $jsonFlags |= JSON_INVALID_UTF8_SUBSTITUTE;
    }
    $encoded = json_encode($fetched_data, $jsonFlags);
    if ($encoded === false) {
        throw new RuntimeException('JSON encode failed: ' . json_last_error_msg());
    }
    echo $encoded;
} catch (Throwable $e) {
    header('Content-Type: application/json; charset=UTF-8');
    http_response_code(500);
    echo json_encode(['error' => 'Server error', 'detail' => $e->getMessage()]);
}
