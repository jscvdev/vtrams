<?php
/**
 * One-time maintenance patch:
 * 1) Backfill missing cashier "Processed by" rows in voucher_action_logs from
 *    voucher_archives, sync process_history on voucher_tracking, and compute
 *    total_processing_time from first receive at Planning/CDS through cashier Processed by.
 * 2) Insert missing "Received by" action logs (and process_history lines) after
 *    "Returned by" when the return target's next hop is Forwarded by (back to the
 *    returner or onward reroute) and there was no receive in between. When the
 *    next hop is another "Returned by" from a different person (they used Return
 *    instead of Receive + Forward), convert that log to Received by and insert
 *    Forwarded by.
 * 3) Correct process_history office labels using user_group (actor's registered office).
 * 4) Normalize stored amounts that are whole numbers (e.g. 15000 → 15000.00) across
 *    voucher tables.
 * 5) Clear empty "Name:" / "Name: , Name:" remarks in voucher_action_logs and
 *    voucher tables (persist empty string), then rebuild remaining combined
 *    remarks from action logs that still have real text.
 *
 * Usage (CLI):
 *   c:\xampp\php\php.exe SQL/patch_fix.php           # dry-run
 *   c:\xampp\php\php.exe SQL/patch_fix.php --apply   # commit
 *   c:\xampp\php\php.exe SQL/patch_fix.php --only=receive --apply
 *
 * Browser:
 *   http://localhost/vtrams/SQL/patch_fix.php
 *   http://localhost/vtrams/SQL/patch_fix.php?apply=1
 *   http://localhost/vtrams/SQL/patch_fix.php?only=receive&apply=1
 */

declare(strict_types=1);

require_once __DIR__ . '/../protected/dbconnection.inc.php';
require_once __DIR__ . '/../protected/core/components/helpers/handler_transaction_helper.inc.php';
require_once __DIR__ . '/../protected/core/components/helpers/voucher_tracking_helper.inc.php';
require_once __DIR__ . '/../protected/core/components/helpers/amount_helper.inc.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    die("Error: Database connection not available. Check protected/dbconnection.inc.php\n");
}

$isCli = PHP_SAPI === 'cli';
$apply = $isCli
    ? in_array('--apply', $argv ?? [], true)
    : isset($_GET['apply']) && (string) $_GET['apply'] === '1';
$dryRun = $isCli
    ? in_array('--dry-run', $argv ?? [], true)
    : isset($_GET['dry_run']) && (string) $_GET['dry_run'] === '1';

$only = '';
if ($isCli) {
    foreach ($argv ?? [] as $arg) {
        if (str_starts_with((string) $arg, '--only=')) {
            $only = strtolower(trim(substr((string) $arg, 7)));
            break;
        }
    }
} else {
    $only = strtolower(trim((string) ($_GET['only'] ?? '')));
}
if (!in_array($only, ['', 'all', 'processed', 'receive', 'offices', 'amounts', 'remarks'], true)) {
    $only = '';
}

@ini_set('zlib.output_compression', '0');
@ini_set('implicit_flush', '1');
set_time_limit(0);
ignore_user_abort(true);
if (!$isCli) {
    header('X-Accel-Buffering: no');
    if (function_exists('apache_setenv')) {
        @apache_setenv('no-gzip', '1');
    }
}
while (ob_get_level() > 0) {
    ob_end_flush();
}

function out(string $message): void
{
    global $isCli;
    echo $message . ($isCli ? "\n" : "<br>\n");
    if (ob_get_level() > 0) {
        ob_flush();
    }
    flush();
}

function patch_should_run(string $phase): bool
{
    global $only;
    if ($only === '' || $only === 'all') {
        return true;
    }

    return $only === $phase;
}

/**
 * @return array<string, true>|null
 */
function patch_table_columns(PDO $pdo, string $table): ?array
{
    static $cache = [];
    if (array_key_exists($table, $cache)) {
        return $cache[$table];
    }

    try {
        $stmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
    } catch (PDOException) {
        $cache[$table] = null;

        return null;
    }

    $columns = [];
    if ($stmt) {
        while ($columnRow = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $field = trim((string) ($columnRow['Field'] ?? ''));
            if ($field !== '') {
                $columns[$field] = true;
            }
        }
    }
    $cache[$table] = $columns;

    return $columns;
}

/**
 * @template T
 * @param list<T> $items
 * @return list<list<T>>
 */
function patch_chunks(array $items, int $size = 300): array
{
    if ($items === []) {
        return [];
    }

    return array_chunk(array_values($items), max(1, $size));
}

function normalize_process_history(?string $value): string
{
    if ($value === null) {
        return '';
    }
    $value = str_replace(["\r\n", "\r"], "\n", (string) $value);
    $value = preg_replace('/\\\\n/', "\n", $value) ?? $value;

    return trim($value);
}

/**
 * @return list<array{name: string, action: string, section: string, office: string}>
 */
function parse_history_lines(string $value): array
{
    if ($value === '') {
        return [];
    }

    $parsed = [];
    foreach (preg_split('/\n/', $value) as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '|') === false) {
            continue;
        }

        $parts = preg_split('/\s*\|\s*/', $line, 4);
        if (!isset($parts[0], $parts[1], $parts[2], $parts[3])) {
            continue;
        }

        $parsed[] = [
            'name' => trim($parts[0]),
            'action' => trim($parts[1]),
            'section' => trim($parts[2]),
            'office' => trim($parts[3]),
        ];
    }

    return $parsed;
}

function is_processed_action(string $action): bool
{
    return (bool) preg_match('/^Processed\s+[Bb]y\s*:/', $action);
}

function history_contains_processed_for(string $history, string $actionBy): bool
{
    if ($history === '') {
        return false;
    }

    foreach (parse_history_lines($history) as $line) {
        if (!is_processed_action($line['action'])) {
            continue;
        }
        if (strcasecmp($line['name'], $actionBy) === 0) {
            return true;
        }
    }

    return false;
}

/**
 * Resolve section/unit for the cashier who processed the voucher.
 *
 * @param list<array{name: string, action: string, section: string, office: string}> $lines
 */
function resolve_action_from(array $lines, string $actionBy): string
{
    $fallback = 'CASHIER';

    for ($i = count($lines) - 1; $i >= 0; $i--) {
        $line = $lines[$i];
        if (strcasecmp($line['name'], $actionBy) !== 0) {
            continue;
        }
        if (stripos($line['action'], 'Received by') !== false && $line['section'] !== '') {
            return $line['section'];
        }
    }

    foreach ($lines as $line) {
        if (strcasecmp($line['name'], $actionBy) === 0 && $line['section'] !== '') {
            return $line['section'];
        }
    }

    return $fallback;
}

function append_history_line(string $history, string $line): string
{
    $history = normalize_process_history($history);
    if ($history === '') {
        return $line;
    }

    return $history . "\n" . $line;
}

function build_history_line(string $name, string $action, string $section, string $office): string
{
    return implode(' | ', [$name, $action, $section, $office]);
}

/** @var list<string> */
const PROCESS_HISTORY_OFFICE_TABLES = [
    'vouchers',
    'voucher_incoming',
    'voucher_receiving',
    'voucher_sent',
    'voucher_archives',
    'voucher_temp',
    'voucher_tracking',
    'voucher_action_logs',
];

/**
 * @return array{updated_vouchers: int, updated_rows: int}
 */
function patch_history_update_tables(PDO $pdo, string $processingNo, string $history, bool $apply): int
{
    static $updateStmts = [];
    $updatedRows = 0;

    foreach (PROCESS_HISTORY_OFFICE_TABLES as $table) {
        $columns = patch_table_columns($pdo, $table);
        if ($columns === null || !isset($columns['processing_no'], $columns['process_history'])) {
            continue;
        }
        if (!isset($updateStmts[$table])) {
            $updateStmts[$table] = $pdo->prepare(
                "UPDATE `{$table}`
                 SET process_history = :history
                 WHERE processing_no = :processing_no
                   AND TRIM(COALESCE(process_history, '')) <> ''
                   AND process_history <> :history_where"
            );
        }
        if (!$apply) {
            $updatedRows++;
            continue;
        }
        $updateStmts[$table]->bindValue(':history', $history, PDO::PARAM_STR);
        $updateStmts[$table]->bindValue(':history_where', $history, PDO::PARAM_STR);
        $updateStmts[$table]->bindValue(':processing_no', $processingNo, PDO::PARAM_STR);
        $updateStmts[$table]->execute();
        $updatedRows += $updateStmts[$table]->rowCount();
    }

    return $updatedRows;
}

function fix_process_history_offices(PDO $pdo, bool $apply): array
{
    $updatedVouchers = 0;
    $updatedRows = 0;
    $columns = patch_table_columns($pdo, 'voucher_tracking');
    if ($columns === null || !isset($columns['processing_no'], $columns['process_history'])) {
        out('Skip voucher_tracking: missing process_history.');

        return [
            'updated_vouchers' => 0,
            'updated_rows' => 0,
        ];
    }

    $selectCols = ['processing_no', 'process_history'];
    if (isset($columns['voucher_type'])) {
        $selectCols[] = 'voucher_type';
    }
    $selectSql = 'SELECT ' . implode(', ', array_map(
        static fn (string $column): string => '`' . $column . '`',
        $selectCols
    )) . " FROM voucher_tracking WHERE TRIM(COALESCE(process_history, '')) <> ''";
    $rows = $pdo->query($selectSql)?->fetchAll(PDO::FETCH_ASSOC) ?? [];
    $total = count($rows);
    out("Scanning {$total} voucher_tracking row(s) for process_history office labels...");

    foreach ($rows as $index => $row) {
        $processingNo = trim((string) ($row['processing_no'] ?? ''));
        $history = normalize_process_history($row['process_history'] ?? null);
        if ($processingNo === '' || $history === '') {
            continue;
        }

        $enriched = voucher_tracking_enrich_process_history_for_return(
            $pdo,
            $history,
            trim((string) ($row['voucher_type'] ?? ''))
        );
        if ($enriched === $history) {
            continue;
        }

        if (($index + 1) % 50 === 0) {
            out('  ... scanned ' . ($index + 1) . "/{$total}");
        }
        $updatedVouchers++;
        $updatedRows += patch_history_update_tables($pdo, $processingNo, $enriched, $apply);
    }

    return [
        'updated_vouchers' => $updatedVouchers,
        'updated_rows' => $updatedRows,
    ];
}

/** @var array<string, list<string>> */
const AMOUNT_FIX_TABLES = [
    'vouchers' => ['amount'],
    'voucher_incoming' => ['amount', 'charged_amount'],
    'voucher_receiving' => ['amount', 'charged_amount'],
    'voucher_sent' => ['amount', 'charged_amount'],
    'voucher_archives' => ['amount', 'charged_amount'],
    'voucher_temp' => ['amount', 'charged_amount'],
    'voucher_tracking' => ['amount', 'charged_amount'],
    'voucher_action_logs' => ['amount'],
    'dv_entries' => ['amount'],
];

/**
 * @return array{updated_rows: int, updated_columns: int}
 */
function fix_whole_number_amounts(PDO $pdo, bool $apply): array
{
    $updatedRows = 0;
    $updatedColumns = 0;

    foreach (AMOUNT_FIX_TABLES as $table => $columns) {
        try {
            $pdo->query("SELECT 1 FROM `{$table}` LIMIT 1");
        } catch (PDOException) {
            out("Skip {$table}: table not found.");
            continue;
        }

        $existingColumns = [];
        $columnStmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
        if ($columnStmt) {
            while ($columnRow = $columnStmt->fetch(PDO::FETCH_ASSOC)) {
                $field = trim((string) ($columnRow['Field'] ?? ''));
                if ($field !== '') {
                    $existingColumns[$field] = true;
                }
            }
        }

        $amountColumns = array_values(array_filter(
            $columns,
            static fn (string $column): bool => isset($existingColumns[$column])
        ));
        if ($amountColumns === []) {
            out("Skip {$table}: no amount column(s).");
            continue;
        }

        $idCol = isset($existingColumns['id']) ? 'id' : null;
        $hasProcessingNo = isset($existingColumns['processing_no']);
        $selectCols = array_values(array_unique(array_filter(array_merge(
            $idCol !== null ? [$idCol] : [],
            $hasProcessingNo ? ['processing_no'] : [],
            $amountColumns
        ))));

        $selectSql = 'SELECT ' . implode(', ', array_map(
            static fn (string $column): string => '`' . $column . '`',
            $selectCols
        )) . " FROM `{$table}`";
        $rows = $pdo->query($selectSql)?->fetchAll(PDO::FETCH_ASSOC) ?? [];

        out('Scanning ' . count($rows) . " row(s) in {$table} for whole-number amount(s)...");

        foreach ($rows as $row) {
            $rowUpdates = [];
            foreach ($amountColumns as $column) {
                $current = $row[$column] ?? null;
                if (!amount_stored_value_needs_two_decimals($current)) {
                    continue;
                }

                $fixed = ensure_amount_two_decimals($current);
                if ($fixed === trim(amount_pdo_value_to_string($current))) {
                    continue;
                }

                $rowUpdates[$column] = $fixed;
            }

            if ($rowUpdates === []) {
                continue;
            }

            $label = $hasProcessingNo
                ? trim((string) ($row['processing_no'] ?? ''))
                : ($idCol !== null ? (string) ($row[$idCol] ?? '') : $table);
            if ($label === '') {
                $label = $idCol !== null ? (string) ($row[$idCol] ?? 'unknown') : 'unknown';
            }

            foreach ($rowUpdates as $column => $fixed) {
                $current = trim(amount_pdo_value_to_string($row[$column] ?? ''));
                out("  {$table} {$label}: {$column} {$current} → {$fixed}");
                if ($apply) {
                    if ($idCol !== null) {
                        $updateStmt = $pdo->prepare("UPDATE `{$table}` SET `{$column}` = :amount WHERE `{$idCol}` = :id");
                        $updateStmt->bindValue(':amount', $fixed, PDO::PARAM_STR);
                        $updateStmt->bindValue(':id', $row[$idCol], PDO::PARAM_STR);
                        $updateStmt->execute();
                    } elseif ($hasProcessingNo) {
                        $updateStmt = $pdo->prepare("UPDATE `{$table}` SET `{$column}` = :amount WHERE `processing_no` = :processing_no");
                        $updateStmt->bindValue(':amount', $fixed, PDO::PARAM_STR);
                        $updateStmt->bindValue(':processing_no', $row['processing_no'], PDO::PARAM_STR);
                        $updateStmt->execute();
                    }
                }
                $updatedColumns++;
            }

            $updatedRows++;
        }
    }

    return [
        'updated_rows' => $updatedRows,
        'updated_columns' => $updatedColumns,
    ];
}

/** @var list<string> */
const REMARKS_SYNC_TABLES = [
    'voucher_tracking',
    'voucher_archives',
    'voucher_incoming',
    'voucher_receiving',
    'voucher_sent',
    'voucher_temp',
];

/** @var list<string> */
const EMPTY_REMARKS_STRIP_TABLES = [
    'voucher_action_logs',
    'voucher_tracking',
    'voucher_archives',
    'voucher_incoming',
    'voucher_receiving',
    'voucher_sent',
    'voucher_temp',
    'vouchers',
    'document_tracking',
];

/**
 * @return list<string>
 */
function patch_discover_remark_tables(PDO $pdo): array
{
    $tables = $pdo->query('SHOW TABLES')?->fetchAll(PDO::FETCH_COLUMN) ?: [];
    $found = [];
    foreach ($tables as $table) {
        $table = (string) $table;
        $columns = patch_table_columns($pdo, $table);
        if ($columns === null) {
            continue;
        }
        if (isset($columns['remarks']) || isset($columns['sender_remarks'])) {
            $found[] = $table;
        }
    }

    return $found !== [] ? $found : EMPTY_REMARKS_STRIP_TABLES;
}

function patch_remarks_empty_segment_sql(string $column): string
{
    $quoted = '`' . $column . '`';

    return "TRIM(COALESCE({$quoted}, '')) <> ''"
        . " AND TRIM(COALESCE({$quoted}, '')) <> 'N/A'"
        . " AND {$quoted} LIKE '%:%'";
}

function patch_remarks_name_only_sql(string $column): string
{
    $quoted = '`' . $column . '`';

    return "TRIM(COALESCE({$quoted}, '')) <> ''"
        . " AND TRIM(COALESCE({$quoted}, '')) <> 'N/A'"
        . " AND TRIM({$quoted}) REGEXP '^([^,:]+:[[:space:]]*,[[:space:]]*)*[^,:]+:[[:space:]]*$'";
}

/**
 * Remove "Name:" / "Name: , Name:" combined-remark segments with no text.
 *
 * @return array{updated_vouchers: int, updated_rows: int}
 */
function fix_empty_combined_remark_segments(PDO $pdo, bool $apply): array
{
    $updatedVouchers = 0;
    $updatedRows = 0;
    $touchedProcessingNos = [];
    $tables = patch_discover_remark_tables($pdo);

    foreach ($tables as $table) {
        $columns = patch_table_columns($pdo, $table);
        if ($columns === null) {
            out("Skip {$table}: table not found.");
            continue;
        }

        $remarkColumns = [];
        foreach (['remarks', 'sender_remarks'] as $column) {
            if (isset($columns[$column])) {
                $remarkColumns[] = $column;
            }
        }
        if ($remarkColumns === []) {
            out("Skip {$table}: no remarks column(s).");
            continue;
        }

        foreach ($remarkColumns as $column) {
            $clearSql = "UPDATE `{$table}` SET `{$column}` = '' WHERE " . patch_remarks_name_only_sql($column);
            try {
                if ($apply) {
                    $cleared = $pdo->exec($clearSql);
                    $cleared = is_int($cleared) ? $cleared : 0;
                } else {
                    $countSql = "SELECT COUNT(*) FROM `{$table}` WHERE " . patch_remarks_name_only_sql($column);
                    $cleared = (int) ($pdo->query($countSql)?->fetchColumn() ?: 0);
                }
            } catch (PDOException) {
                $cleared = 0;
            }
            if ($cleared > 0) {
                out("  {$table}.{$column}: cleared {$cleared} name-only empty remark row(s)");
                $updatedRows += $cleared;
            }
        }

        $idCol = isset($columns['id']) ? 'id' : null;
        $hasProcessingNo = isset($columns['processing_no']);
        $selectCols = array_values(array_unique(array_filter(array_merge(
            $idCol !== null ? [$idCol] : [],
            $hasProcessingNo ? ['processing_no'] : [],
            $remarkColumns
        ))));

        $whereParts = [];
        foreach ($remarkColumns as $column) {
            $whereParts[] = patch_remarks_empty_segment_sql($column);
        }

        $selectSql = 'SELECT ' . implode(', ', array_map(
            static fn (string $column): string => '`' . $column . '`',
            $selectCols
        )) . " FROM `{$table}` WHERE " . implode(' OR ', $whereParts);

        try {
            $rows = $pdo->query($selectSql)?->fetchAll(PDO::FETCH_ASSOC) ?? [];
        } catch (PDOException) {
            $fallbackParts = [];
            foreach ($remarkColumns as $column) {
                $fallbackParts[] = patch_remarks_empty_segment_sql($column);
            }
            $fallbackSql = 'SELECT ' . implode(', ', array_map(
                static fn (string $column): string => '`' . $column . '`',
                $selectCols
            )) . " FROM `{$table}` WHERE " . implode(' OR ', $fallbackParts);
            $rows = $pdo->query($fallbackSql)?->fetchAll(PDO::FETCH_ASSOC) ?? [];
        }

        out('Scanning ' . count($rows) . " candidate row(s) in {$table} for empty combined remarks...");
        $exampleCount = 0;

        foreach ($rows as $row) {
            $rowUpdates = [];
            foreach ($remarkColumns as $column) {
                $current = trim((string) ($row[$column] ?? ''));
                if ($current === '' || strcasecmp($current, 'N/A') === 0) {
                    continue;
                }

                $stripped = voucher_tracking_strip_empty_combined_remark_segments($current);
                if ($stripped === $current) {
                    continue;
                }

                $rowUpdates[$column] = $stripped; // empty string clears Name-only concatenations
            }

            if ($rowUpdates === []) {
                continue;
            }

            $processingNo = $hasProcessingNo ? trim((string) ($row['processing_no'] ?? '')) : '';
            $label = $processingNo !== ''
                ? $processingNo
                : ($idCol !== null ? (string) ($row[$idCol] ?? '') : $table);
            if ($label === '') {
                $label = 'unknown';
            }

            foreach ($rowUpdates as $column => $stripped) {
                $preview = $stripped === '' ? '(empty)' : $stripped;
                if ($exampleCount < 20) {
                    out("  {$table} {$label}: {$column} stripped empty remark(s) → {$preview}");
                    $exampleCount++;
                }
                if ($apply) {
                    if ($idCol !== null) {
                        $updateStmt = $pdo->prepare("UPDATE `{$table}` SET `{$column}` = :remarks WHERE `{$idCol}` = :id");
                        $updateStmt->bindValue(':remarks', $stripped, PDO::PARAM_STR);
                        $updateStmt->bindValue(':id', $row[$idCol], PDO::PARAM_STR);
                        $updateStmt->execute();
                    } elseif ($hasProcessingNo) {
                        $updateStmt = $pdo->prepare("UPDATE `{$table}` SET `{$column}` = :remarks WHERE `processing_no` = :processing_no");
                        $updateStmt->bindValue(':remarks', $stripped, PDO::PARAM_STR);
                        $updateStmt->bindValue(':processing_no', $row['processing_no'], PDO::PARAM_STR);
                        $updateStmt->execute();
                    }
                }
            }

            if ($processingNo !== '' && !isset($touchedProcessingNos[$processingNo])) {
                $touchedProcessingNos[$processingNo] = true;
                $updatedVouchers++;
            }
            $updatedRows++;
        }

        if ($updatedRows > 0 && $exampleCount >= 20) {
            out("  ... additional {$table} empty-remark row(s) omitted from log.");
        }
    }

    return [
        'updated_vouchers' => $updatedVouchers,
        'updated_rows' => $updatedRows,
    ];
}

/**
 * @return array{updated_vouchers: int, updated_rows: int}
 */
function fix_combined_remarks_from_action_logs(PDO $pdo, bool $apply): array
{
    $updatedVouchers = 0;
    $updatedRows = 0;

    $processingNos = $pdo->query(
        "SELECT DISTINCT processing_no
         FROM voucher_action_logs
         WHERE TRIM(COALESCE(processing_no, '')) <> ''"
    )?->fetchAll(PDO::FETCH_COLUMN) ?? [];

    out('Scanning ' . count($processingNos) . ' processing_no(s) with action logs for combined remarks...');

    $logsByPn = voucher_tracking_fetch_action_logs_grouped($pdo, array_map(
        static fn($pn): string => trim((string) $pn),
        $processingNos
    ));

    foreach ($processingNos as $processingNo) {
        $processingNo = trim((string) $processingNo);
        if ($processingNo === '') {
            continue;
        }

        $logs = $logsByPn[$processingNo] ?? [];
        if ($logs === []) {
            continue;
        }

        $cleanedLogs = [];
        foreach ($logs as $log) {
            $rawLogRemarks = trim((string) ($log['remarks'] ?? ''));
            $log['remarks'] = voucher_tracking_strip_empty_combined_remark_segments($rawLogRemarks);
            $cleanedLogs[] = $log;
        }
        $logs = $cleanedLogs;

        $targetRemarks = voucher_tracking_build_combined_remarks_from_action_logs($logs);
        $targetRemarks = voucher_tracking_strip_empty_combined_remark_segments($targetRemarks);
        if ($targetRemarks === '') {
            continue;
        }

        $voucherUpdated = false;
        static $remarkUpdateStmts = [];
        foreach (REMARKS_SYNC_TABLES as $table) {
            $columns = patch_table_columns($pdo, $table);
            if ($columns === null) {
                continue;
            }
            if (!isset($columns['processing_no'], $columns['remarks'])) {
                continue;
            }
            if (!isset($remarkUpdateStmts[$table])) {
                $remarkUpdateStmts[$table] = $pdo->prepare(
                    "UPDATE `{$table}`
                     SET remarks = :target_remarks_set
                     WHERE processing_no = :processing_no
                       AND TRIM(COALESCE(remarks, '')) <> :target_remarks_where"
                );
            }

            if ($apply) {
                $remarkUpdateStmts[$table]->bindValue(':target_remarks_set', $targetRemarks, PDO::PARAM_STR);
                $remarkUpdateStmts[$table]->bindValue(':target_remarks_where', $targetRemarks, PDO::PARAM_STR);
                $remarkUpdateStmts[$table]->bindValue(':processing_no', $processingNo, PDO::PARAM_STR);
                $remarkUpdateStmts[$table]->execute();
                $rowCount = $remarkUpdateStmts[$table]->rowCount();
            } else {
                $rowCount = 1;
            }
            if ($rowCount < 1) {
                continue;
            }

            if (!$voucherUpdated) {
                $voucherUpdated = true;
                $updatedVouchers++;
            }
            $updatedRows += $rowCount;
        }
    }

    return [
        'updated_vouchers' => $updatedVouchers,
        'updated_rows' => $updatedRows,
    ];
}

function patch_action_kind(string $action): string
{
    $lower = strtolower(trim($action));
    if (str_contains($lower, 'retracted')) {
        return 'retract';
    }

    return voucher_tracking_action_kind($action);
}

function patch_is_returned_action(string $action): bool
{
    return patch_action_kind($action) === 'return';
}

function patch_names_match(string $left, string $right): bool
{
    return $left !== '' && $right !== '' && strcasecmp($left, $right) === 0;
}

function patch_receive_datetime(string $returnAt, string $nextAt): string
{
    $returnTs = strtotime($returnAt);
    if ($returnTs === false) {
        return $returnAt !== '' ? $returnAt : date('Y-m-d H:i:s');
    }

    $nextTs = strtotime($nextAt);
    if ($nextTs !== false && $nextTs > $returnTs + 1) {
        return date('Y-m-d H:i:s', $returnTs + 1);
    }

    return date('Y-m-d H:i:s', $returnTs);
}

/**
 * Return → Forwarded by the return target (back to the returner or onward reroute),
 * with no receive in between.
 *
 * @param list<array<string, mixed>> $logs
 * @return array{index: int, log: array<string, mixed>}|null
 */
function patch_next_return_reply(array $logs, int $returnIndex): ?array
{
    $returner = trim((string) ($logs[$returnIndex]['action_by'] ?? ''));
    for ($j = $returnIndex + 1, $count = count($logs); $j < $count; $j++) {
        $kind = patch_action_kind((string) ($logs[$j]['action'] ?? ''));
        if ($kind === 'other') {
            continue;
        }
        if ($kind !== 'forward') {
            return null;
        }

        $actor = trim((string) ($logs[$j]['action_by'] ?? ''));
        if ($actor === '' || patch_names_match($actor, $returner)) {
            return null;
        }

        return [
            'index' => $j,
            'log' => $logs[$j],
        ];
    }

    return null;
}

/**
 * Return → Return by a different person (used Return to send it back instead of Receive + Forward).
 *
 * @param list<array<string, mixed>> $logs
 * @return array{index: int, log: array<string, mixed>}|null
 */
function patch_next_mistaken_return_reply(array $logs, int $returnIndex): ?array
{
    $returner = trim((string) ($logs[$returnIndex]['action_by'] ?? ''));
    for ($j = $returnIndex + 1, $count = count($logs); $j < $count; $j++) {
        $kind = patch_action_kind((string) ($logs[$j]['action'] ?? ''));
        if ($kind === 'other') {
            continue;
        }
        if ($kind !== 'return') {
            return null;
        }

        $actor = trim((string) ($logs[$j]['action_by'] ?? ''));
        if ($actor === '' || patch_names_match($actor, $returner)) {
            return null;
        }

        return [
            'index' => $j,
            'log' => $logs[$j],
        ];
    }

    return null;
}

function patch_forward_log_exists(
    array $logs,
    int $fromIndex,
    int $untilIndex,
    string $actor
): bool {
    for ($j = $fromIndex + 1; $j < $untilIndex; $j++) {
        if (patch_action_kind((string) ($logs[$j]['action'] ?? '')) !== 'forward') {
            continue;
        }
        if (patch_names_match(trim((string) ($logs[$j]['action_by'] ?? '')), $actor)) {
            return true;
        }
    }

    return false;
}

function patch_forward_datetime(string $receiveAt, string $nextAt): string
{
    $receiveTs = strtotime($receiveAt);
    if ($receiveTs === false) {
        return $receiveAt !== '' ? $receiveAt : date('Y-m-d H:i:s');
    }

    $nextTs = strtotime($nextAt);
    if ($nextTs !== false && $nextTs > $receiveTs + 1) {
        return date('Y-m-d H:i:s', $receiveTs + 1);
    }

    return date('Y-m-d H:i:s', $receiveTs);
}

function patch_history_convert_nth_actor_return_to_receive_forward(
    string $history,
    string $actor,
    int $occurrence,
    string $receiveLine,
    string $forwardLine
): string {
    $rawLines = preg_split('/\n/', normalize_process_history($history)) ?: [];
    $seen = 0;
    $out = [];
    $converted = false;

    foreach ($rawLines as $index => $raw) {
        if ($converted) {
            $out[] = $raw;
            continue;
        }

        $line = trim($raw);
        if ($line === '' || strpos($line, '|') === false) {
            $out[] = $raw;
            continue;
        }

        $parts = preg_split('/\s*\|\s*/', $line, 4);
        $name = trim((string) ($parts[0] ?? ''));
        $action = trim((string) ($parts[1] ?? ''));
        if (!preg_match('/returned\s+by/i', $action) || !patch_names_match($name, $actor)) {
            $out[] = $raw;
            continue;
        }
        if ($seen !== $occurrence) {
            $seen++;
            $out[] = $raw;
            continue;
        }
        $seen++;
        $converted = true;

        $nextRaw = '';
        for ($k = $index + 1, $total = count($rawLines); $k < $total; $k++) {
            $candidate = trim((string) $rawLines[$k]);
            if ($candidate !== '') {
                $nextRaw = $candidate;
                break;
            }
        }
        $nextName = '';
        $nextAction = '';
        if ($nextRaw !== '' && strpos($nextRaw, '|') !== false) {
            $nextParts = preg_split('/\s*\|\s*/', $nextRaw, 4);
            $nextName = trim((string) ($nextParts[0] ?? ''));
            $nextAction = trim((string) ($nextParts[1] ?? ''));
        }
        $alreadyForward = preg_match('/forwarded\s+by/i', $nextAction) && patch_names_match($nextName, $actor);

        $out[] = $receiveLine;
        if (!$alreadyForward && $forwardLine !== '') {
            $out[] = $forwardLine;
        }
    }

    return trim(implode("\n", $out));
}

function patch_receive_log_exists(
    array $logs,
    int $returnIndex,
    int $nextIndex,
    string $recipient
): bool {
    for ($j = $returnIndex + 1; $j < $nextIndex; $j++) {
        if (patch_action_kind((string) ($logs[$j]['action'] ?? '')) !== 'receive') {
            continue;
        }
        if (patch_names_match(trim((string) ($logs[$j]['action_by'] ?? '')), $recipient)) {
            return true;
        }
    }

    return false;
}

function patch_history_insert_receive_after_nth_return(
    string $history,
    string $returnedBy,
    string $recipient,
    string $receiveLine,
    int $occurrence
): string {
    $rawLines = preg_split('/\n/', normalize_process_history($history)) ?: [];
    $seen = 0;
    $out = [];
    $inserted = false;

    foreach ($rawLines as $index => $raw) {
        $out[] = $raw;
        if ($inserted) {
            continue;
        }

        $line = trim($raw);
        if ($line === '' || strpos($line, '|') === false) {
            continue;
        }

        $parts = preg_split('/\s*\|\s*/', $line, 4);
        if (!isset($parts[0], $parts[1])) {
            continue;
        }

        $name = trim((string) $parts[0]);
        $action = trim((string) $parts[1]);
        if (!preg_match('/returned\s+by/i', $action) || !patch_names_match($name, $returnedBy)) {
            continue;
        }
        if ($seen !== $occurrence) {
            $seen++;
            continue;
        }
        $seen++;

        $nextRaw = '';
        for ($k = $index + 1, $total = count($rawLines); $k < $total; $k++) {
            $candidate = trim((string) $rawLines[$k]);
            if ($candidate !== '') {
                $nextRaw = $candidate;
                break;
            }
        }
        if ($nextRaw !== '' && strpos($nextRaw, '|') !== false) {
            $nextParts = preg_split('/\s*\|\s*/', $nextRaw, 4);
            $nextName = trim((string) ($nextParts[0] ?? ''));
            $nextAction = trim((string) ($nextParts[1] ?? ''));
            if (preg_match('/received\s+by/i', $nextAction) && patch_names_match($nextName, $recipient)) {
                $inserted = true;
                continue;
            }
        }

        $out[] = $receiveLine;
        $inserted = true;
    }

    if (!$inserted && $receiveLine !== '') {
        $out[] = $receiveLine;
    }

    return trim(implode("\n", $out));
}

/**
 * @return array{inserted_logs: int, converted_returns: int, inserted_forwards: int, updated_vouchers: int, updated_rows: int}
 */
function fix_missing_receive_after_return(PDO $pdo, bool $apply): array
{
    $insertedLogs = 0;
    $convertedReturns = 0;
    $insertedForwards = 0;
    $updatedVouchers = 0;
    $updatedRows = 0;

    $processingNos = $pdo->query(
        "SELECT DISTINCT processing_no
         FROM voucher_action_logs
         WHERE TRIM(COALESCE(processing_no, '')) <> ''
           AND action LIKE 'Returned%'"
    )?->fetchAll(PDO::FETCH_COLUMN) ?? [];

    $processingNos = array_values(array_filter(array_map(
        static fn($pn): string => trim((string) $pn),
        $processingNos
    )));
    $totalPn = count($processingNos);
    out("Scanning {$totalPn} processing_no(s) with Returned by logs...");
    if ($processingNos === []) {
        return [
            'inserted_logs' => 0,
            'converted_returns' => 0,
            'inserted_forwards' => 0,
            'updated_vouchers' => 0,
            'updated_rows' => 0,
        ];
    }

    $logColumns = patch_table_columns($pdo, 'voucher_action_logs') ?? [];
    $logSelectCols = [
        'id', 'processing_no', 'action', 'action_by', 'action_from', 'datetime_action',
        'office_from', 'office_to', 'ors_no', 'ada_check_no', 'dv_no', 'payee', 'address',
        'tin_employee_no', 'particulars', 'amount', 'voucher_type', 'voucher_date',
        'encoded_by', 'remarks', 'coa_options', 'coa_category', 'coa_subsection',
    ];
    $logSelectCols = array_values(array_filter(
        $logSelectCols,
        static fn(string $col): bool => isset($logColumns[$col])
    ));
    if ($logSelectCols === []) {
        $logSelectSql = '*';
    } else {
        $logSelectSql = implode(', ', array_map(
            static fn(string $col): string => '`' . $col . '`',
            $logSelectCols
        ));
    }

    $logsByPn = [];
    $trackingHistory = [];
    foreach (patch_chunks($processingNos, 250) as $chunkIndex => $chunk) {
        out('  loading logs chunk ' . ($chunkIndex + 1) . ' (' . count($chunk) . ' processing_no)...');
        $placeholders = implode(',', array_fill(0, count($chunk), '?'));
        $logStmt = $pdo->prepare(
            "SELECT {$logSelectSql}
             FROM voucher_action_logs
             WHERE processing_no IN ({$placeholders})
             ORDER BY processing_no ASC, datetime_action ASC, id ASC"
        );
        foreach ($chunk as $index => $processingNoBind) {
            $logStmt->bindValue($index + 1, $processingNoBind, PDO::PARAM_STR);
        }
        $logStmt->execute();
        while ($row = $logStmt->fetch(PDO::FETCH_ASSOC)) {
            $pn = trim((string) ($row['processing_no'] ?? ''));
            if ($pn === '') {
                continue;
            }
            $logsByPn[$pn][] = $row;
        }

        $histStmt = $pdo->prepare(
            "SELECT processing_no, process_history
             FROM voucher_tracking
             WHERE processing_no IN ({$placeholders})"
        );
        foreach ($chunk as $index => $processingNoBind) {
            $histStmt->bindValue($index + 1, $processingNoBind, PDO::PARAM_STR);
        }
        $histStmt->execute();
        while ($row = $histStmt->fetch(PDO::FETCH_ASSOC)) {
            $pn = trim((string) ($row['processing_no'] ?? ''));
            if ($pn !== '') {
                $trackingHistory[$pn] = normalize_process_history((string) ($row['process_history'] ?? ''));
            }
        }
    }

    $insertStmt = $pdo->prepare("
        INSERT INTO voucher_action_logs (
            processing_no,
            ors_no,
            ada_check_no,
            dv_no,
            payee,
            address,
            tin_employee_no,
            particulars,
            amount,
            voucher_type,
            voucher_date,
            remarks,
            encoded_by,
            action,
            action_by,
            action_from,
            datetime_action,
            office_from,
            office_to,
            coa_options,
            coa_category,
            coa_subsection
        ) VALUES (
            :processing_no,
            :ors_no,
            :ada_check_no,
            :dv_no,
            :payee,
            :address,
            :tin_employee_no,
            :particulars,
            :amount,
            :voucher_type,
            :voucher_date,
            :remarks,
            :encoded_by,
            :action,
            :action_by,
            :action_from,
            :datetime_action,
            :office_from,
            :office_to,
            :coa_options,
            :coa_category,
            :coa_subsection
        )
    ");
    $updateActionStmt = $pdo->prepare(
        'UPDATE voucher_action_logs SET action = :action WHERE id = :id'
    );

    $officeCache = [];
    foreach ($processingNos as $pnIndex => $processingNo) {
        $processingNo = trim((string) $processingNo);
        if ($processingNo === '') {
            continue;
        }

        $logs = $logsByPn[$processingNo] ?? [];
        if ($logs === []) {
            continue;
        }

        $returnOccurrenceByName = [];
        $historyChanged = false;
        $voucherLogged = false;

        if (($pnIndex + 1) % 100 === 0) {
            out('  ... scanned ' . ($pnIndex + 1) . "/{$totalPn}");
        }

        $history = $trackingHistory[$processingNo] ?? '';

        foreach ($logs as $returnIndex => $returnLog) {
            if (!patch_is_returned_action((string) ($returnLog['action'] ?? ''))) {
                continue;
            }

            $returner = trim((string) ($returnLog['action_by'] ?? ''));
            if ($returner === '') {
                continue;
            }

            $occurrence = $returnOccurrenceByName[$returner] ?? 0;
            $returnOccurrenceByName[$returner] = $occurrence + 1;

            $reply = patch_next_return_reply($logs, (int) $returnIndex);
            if ($reply === null) {
                $mistaken = patch_next_mistaken_return_reply($logs, (int) $returnIndex);
                if ($mistaken === null) {
                    continue;
                }

                $mistakenLog = $mistaken['log'];
                $mistakenIndex = (int) $mistaken['index'];
                $actor = trim((string) ($mistakenLog['action_by'] ?? ''));
                if ($actor === '') {
                    continue;
                }

                $untilIndex = count($logs);
                for ($k = $mistakenIndex + 1, $logCount = count($logs); $k < $logCount; $k++) {
                    $kind = patch_action_kind((string) ($logs[$k]['action'] ?? ''));
                    if ($kind !== 'other') {
                        $untilIndex = $k;
                        break;
                    }
                }

                $actorReturnOccurrence = 0;
                for ($k = 0; $k < $mistakenIndex; $k++) {
                    if (
                        patch_is_returned_action((string) ($logs[$k]['action'] ?? ''))
                        && patch_names_match(trim((string) ($logs[$k]['action_by'] ?? '')), $actor)
                    ) {
                        $actorReturnOccurrence++;
                    }
                }

                $actionFrom = trim((string) ($mistakenLog['action_from'] ?? ''));
                if ($actionFrom === '') {
                    $actionFrom = trim((string) ($returnLog['action_from'] ?? ''));
                }
                $officeFrom = trim((string) ($mistakenLog['office_from'] ?? ''));
                if ($officeFrom === '') {
                    $officeFrom = trim((string) ($returnLog['office_to'] ?? ''));
                }
                $officeTo = trim((string) ($mistakenLog['office_to'] ?? ''));
                if ($officeTo === '') {
                    $officeTo = trim((string) ($returnLog['office_from'] ?? ''));
                }
                $officeKey = strtolower($actor) . '|' . $officeFrom . '|' . $officeTo;
                if (!isset($officeCache[$officeKey])) {
                    $officeCache[$officeKey] = voucher_tracking_resolve_office_for_history(
                        $pdo,
                        $actor,
                        $officeFrom,
                        $officeTo
                    );
                }
                $historyOffice = $officeCache[$officeKey];
                $historyOffice = $historyOffice !== '' ? $historyOffice : ($officeFrom !== '' ? $officeFrom : $officeTo);
                $receiveAction = 'Received by: ' . $actor;
                $forwardAction = 'Forwarded by: ' . $actor;
                $receiveLine = build_history_line(
                    $actor,
                    $receiveAction,
                    $actionFrom !== '' ? $actionFrom : 'TBD',
                    $historyOffice
                );
                $forwardLine = build_history_line(
                    $actor,
                    $forwardAction,
                    $actionFrom !== '' ? $actionFrom : 'TBD',
                    $historyOffice
                );
                $newHistory = patch_history_convert_nth_actor_return_to_receive_forward(
                    $history,
                    $actor,
                    $actorReturnOccurrence,
                    $receiveLine,
                    $forwardLine
                );
                $needsHistory = $newHistory !== $history;
                $needsReceiveConvert = patch_is_returned_action((string) ($mistakenLog['action'] ?? ''));
                $needsForwardLog = !patch_forward_log_exists($logs, $mistakenIndex, $untilIndex, $actor);

                if (!$needsReceiveConvert && !$needsForwardLog && !$needsHistory) {
                    continue;
                }

                if (!$voucherLogged) {
                    $voucherLogged = true;
                    $updatedVouchers++;
                }

                $logId = (int) ($mistakenLog['id'] ?? 0);
                if ($needsReceiveConvert) {
                    out("  {$processingNo}: convert Returned by {$actor} → Received by {$actor}");
                    if ($apply && $logId > 0) {
                        $updateActionStmt->bindValue(':action', $receiveAction, PDO::PARAM_STR);
                        $updateActionStmt->bindValue(':id', $logId, PDO::PARAM_INT);
                        $updateActionStmt->execute();
                    }
                    $logs[$mistakenIndex]['action'] = $receiveAction;
                    $convertedReturns++;
                }

                if ($needsForwardLog) {
                    $nextAt = (string) ($logs[$untilIndex]['datetime_action'] ?? '');
                    $forwardAt = patch_forward_datetime((string) ($mistakenLog['datetime_action'] ?? ''), $nextAt);
                    out("  {$processingNo}: insert Forwarded by {$actor} after Received by {$actor}");
                    if ($apply) {
                        $source = $mistakenLog;
                        $insertStmt->execute([
                            ':processing_no' => $processingNo,
                            ':ors_no' => (string) ($source['ors_no'] ?? $returnLog['ors_no'] ?? 'TBD'),
                            ':ada_check_no' => (string) ($source['ada_check_no'] ?? $returnLog['ada_check_no'] ?? 'TBD'),
                            ':dv_no' => (string) ($source['dv_no'] ?? $returnLog['dv_no'] ?? 'TBD'),
                            ':payee' => (string) ($source['payee'] ?? $returnLog['payee'] ?? ''),
                            ':address' => (string) ($source['address'] ?? $returnLog['address'] ?? ''),
                            ':tin_employee_no' => (string) ($source['tin_employee_no'] ?? $returnLog['tin_employee_no'] ?? ''),
                            ':particulars' => (string) ($source['particulars'] ?? $returnLog['particulars'] ?? ''),
                            ':amount' => ensure_amount_two_decimals((string) ($source['amount'] ?? $returnLog['amount'] ?? '0')),
                            ':voucher_type' => (string) ($source['voucher_type'] ?? $returnLog['voucher_type'] ?? ''),
                            ':voucher_date' => (string) ($source['voucher_date'] ?? $returnLog['voucher_date'] ?? ''),
                            ':remarks' => '',
                            ':encoded_by' => (string) ($source['encoded_by'] ?? $returnLog['encoded_by'] ?? ''),
                            ':action' => $forwardAction,
                            ':action_by' => $actor,
                            ':action_from' => $actionFrom,
                            ':datetime_action' => $forwardAt,
                            ':office_from' => $officeFrom,
                            ':office_to' => $officeTo !== '' ? $officeTo : $officeFrom,
                            ':coa_options' => $source['coa_options'] ?? $returnLog['coa_options'] ?? null,
                            ':coa_category' => $source['coa_category'] ?? $returnLog['coa_category'] ?? null,
                            ':coa_subsection' => $source['coa_subsection'] ?? $returnLog['coa_subsection'] ?? null,
                        ]);
                    }
                    $insertedForwards++;
                }

                if ($needsHistory) {
                    out("  {$processingNo}: convert process_history Returned by {$actor} to Received by + Forwarded by");
                    $history = $newHistory;
                    $historyChanged = true;
                }

                continue;
            }

            $nextLog = $reply['log'];
            $recipient = trim((string) ($nextLog['action_by'] ?? ''));
            if ($recipient === '') {
                continue;
            }

            $needsLog = !patch_receive_log_exists($logs, (int) $returnIndex, (int) $reply['index'], $recipient);
            $actionFrom = trim((string) ($nextLog['action_from'] ?? ''));
            if ($actionFrom === '') {
                $actionFrom = trim((string) ($returnLog['action_from'] ?? ''));
            }
            $officeFrom = trim((string) ($nextLog['office_from'] ?? ''));
            $officeTo = trim((string) ($nextLog['office_to'] ?? ''));
            $officeKey = strtolower($recipient) . '|' . $officeFrom . '|' . $officeTo;
            if (!isset($officeCache[$officeKey])) {
                $officeCache[$officeKey] = voucher_tracking_resolve_office_for_history(
                    $pdo,
                    $recipient,
                    $officeFrom !== '' ? $officeFrom : trim((string) ($returnLog['office_to'] ?? '')),
                    $officeTo
                );
            }
            $historyOffice = $officeCache[$officeKey];
            $receiveAction = 'Received by: ' . $recipient;
            $receiveLine = build_history_line(
                $recipient,
                $receiveAction,
                $actionFrom !== '' ? $actionFrom : 'TBD',
                $historyOffice !== '' ? $historyOffice : ($officeTo !== '' ? $officeTo : $officeFrom)
            );
            $newHistory = patch_history_insert_receive_after_nth_return(
                $history,
                $returner,
                $recipient,
                $receiveLine,
                $occurrence
            );
            $needsHistory = $newHistory !== $history;

            if (!$needsLog && !$needsHistory) {
                continue;
            }

            $receiveAt = patch_receive_datetime(
                (string) ($returnLog['datetime_action'] ?? ''),
                (string) ($nextLog['datetime_action'] ?? '')
            );

            if (!$voucherLogged) {
                $voucherLogged = true;
                $updatedVouchers++;
            }

            if ($needsLog) {
                out("  {$processingNo}: insert Received by {$recipient} after Returned by {$returner}");
                if ($apply) {
                    $source = $nextLog;
                    $insertStmt->execute([
                        ':processing_no' => $processingNo,
                        ':ors_no' => (string) ($source['ors_no'] ?? $returnLog['ors_no'] ?? 'TBD'),
                        ':ada_check_no' => (string) ($source['ada_check_no'] ?? $returnLog['ada_check_no'] ?? 'TBD'),
                        ':dv_no' => (string) ($source['dv_no'] ?? $returnLog['dv_no'] ?? 'TBD'),
                        ':payee' => (string) ($source['payee'] ?? $returnLog['payee'] ?? ''),
                        ':address' => (string) ($source['address'] ?? $returnLog['address'] ?? ''),
                        ':tin_employee_no' => (string) ($source['tin_employee_no'] ?? $returnLog['tin_employee_no'] ?? ''),
                        ':particulars' => (string) ($source['particulars'] ?? $returnLog['particulars'] ?? ''),
                        ':amount' => ensure_amount_two_decimals((string) ($source['amount'] ?? $returnLog['amount'] ?? '0')),
                        ':voucher_type' => (string) ($source['voucher_type'] ?? $returnLog['voucher_type'] ?? ''),
                        ':voucher_date' => (string) ($source['voucher_date'] ?? $returnLog['voucher_date'] ?? ''),
                        ':remarks' => '',
                        ':encoded_by' => (string) ($source['encoded_by'] ?? $returnLog['encoded_by'] ?? ''),
                        ':action' => $receiveAction,
                        ':action_by' => $recipient,
                        ':action_from' => $actionFrom,
                        ':datetime_action' => $receiveAt,
                        ':office_from' => $officeFrom !== '' ? $officeFrom : (string) ($returnLog['office_to'] ?? ''),
                        ':office_to' => $officeTo !== '' ? $officeTo : $officeFrom,
                        ':coa_options' => $source['coa_options'] ?? $returnLog['coa_options'] ?? null,
                        ':coa_category' => $source['coa_category'] ?? $returnLog['coa_category'] ?? null,
                        ':coa_subsection' => $source['coa_subsection'] ?? $returnLog['coa_subsection'] ?? null,
                    ]);
                }
                $insertedLogs++;
            }

            if ($needsHistory) {
                out("  {$processingNo}: insert Received by line in process_history after Returned by {$returner}");
                $history = $newHistory;
                $historyChanged = true;
            }
        }

        if (!$historyChanged || $history === '') {
            continue;
        }

        $updatedRows += patch_history_update_tables($pdo, $processingNo, $history, $apply);
    }

    return [
        'inserted_logs' => $insertedLogs,
        'converted_returns' => $convertedReturns,
        'inserted_forwards' => $insertedForwards,
        'updated_vouchers' => $updatedVouchers,
        'updated_rows' => $updatedRows,
    ];
}

function processed_log_exists(PDO $pdo, string $processingNo, string $actionBy): bool
{
    $stmt = $pdo->prepare("
        SELECT 1
        FROM voucher_action_logs
        WHERE processing_no = :processing_no
          AND action_by = :action_by
          AND action LIKE 'Processed%'
        LIMIT 1
    ");
    $stmt->bindValue(':processing_no', $processingNo, PDO::PARAM_STR);
    $stmt->bindValue(':action_by', $actionBy, PDO::PARAM_STR);
    $stmt->execute();

    return (bool) $stmt->fetchColumn();
}

if (!$isCli) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<pre style="font-family:monospace">';
}

out('Maintenance patch: Processed by logs + missing receive-after-return logs + process_history offices + amount decimals + combined remarks');
out('Mode: ' . ($apply ? 'APPLY (updates will be saved)' : 'DRY-RUN (preview only; pass --apply or ?apply=1 to commit)'));
if ($only !== '' && $only !== 'all') {
    out('Only: ' . $only);
}
out(str_repeat('-', 72));

$insertedLogs = 0;
$updatedTracking = 0;
$updatedArchives = 0;
$updatedProcessingTime = 0;
$skipped = 0;
$amountRowsFixed = 0;
$amountColumnsFixed = 0;
$historyOfficeVouchersFixed = 0;
$historyOfficeRowsFixed = 0;
$remarksVouchersFixed = 0;
$remarksRowsFixed = 0;
$receiveLogsInserted = 0;
$receiveConvertedReturns = 0;
$receiveInsertedForwards = 0;
$receiveHistoryVouchersFixed = 0;
$receiveHistoryRowsFixed = 0;

try {
    $archives = [];
    $processedLogKeys = [];
    if (patch_should_run('processed')) {
        $archiveStmt = $pdo->query("
            SELECT
                processing_no,
                ors_no,
                ada_check_no,
                dv_no,
                payee,
                address,
                tin_employee_no,
                particulars,
                amount,
                voucher_type,
                voucher_date,
                encoded_by,
                office_from,
                office_to,
                remarks,
                datetime_encoded,
                datetime_action,
                action,
                action_by,
                process_history,
                coa_options,
                coa_category,
                coa_subsection
            FROM voucher_archives
            WHERE action LIKE 'Processed%'
            ORDER BY processing_no ASC
        ");
        $archives = $archiveStmt ? $archiveStmt->fetchAll(PDO::FETCH_ASSOC) : [];
        out('Scanning ' . count($archives) . ' archive(s) with Processed action...');

        $processedLogStmt = $pdo->query(
            "SELECT processing_no, action_by
             FROM voucher_action_logs
             WHERE action LIKE 'Processed%'"
        );
        if ($processedLogStmt) {
            while ($processedLogRow = $processedLogStmt->fetch(PDO::FETCH_ASSOC)) {
                $key = strtolower(trim((string) ($processedLogRow['processing_no'] ?? '')) . '|' . trim((string) ($processedLogRow['action_by'] ?? '')));
                if ($key !== '|') {
                    $processedLogKeys[$key] = true;
                }
            }
        }
    }

    $insertStmt = $pdo->prepare("
        INSERT INTO voucher_action_logs (
            processing_no,
            ors_no,
            ada_check_no,
            dv_no,
            payee,
            address,
            tin_employee_no,
            particulars,
            amount,
            voucher_type,
            voucher_date,
            remarks,
            process_history,
            encoded_by,
            action,
            action_by,
            action_from,
            datetime_action,
            office_from,
            office_to,
            coa_options,
            coa_category,
            coa_subsection
        ) VALUES (
            :processing_no,
            :ors_no,
            :ada_check_no,
            :dv_no,
            :payee,
            :address,
            :tin_employee_no,
            :particulars,
            :amount,
            :voucher_type,
            :voucher_date,
            :remarks,
            :process_history,
            :encoded_by,
            :action,
            :action_by,
            :action_from,
            :datetime_action,
            :office_from,
            :office_to,
            :coa_options,
            :coa_category,
            :coa_subsection
        )
    ");

    $trackingStmt = $pdo->prepare("
        UPDATE voucher_tracking
        SET process_history = :process_history,
            total_processing_time = :total_processing_time
        WHERE processing_no = :processing_no
    ");

    $trackingCheck = $pdo->prepare("
        SELECT process_history, datetime_encoded, total_processing_time
        FROM voucher_tracking
        WHERE processing_no = :processing_no
        LIMIT 1
    ");

    $archiveHistoryStmt = $pdo->prepare("
        UPDATE voucher_archives
        SET process_history = :process_history
        WHERE processing_no = :processing_no
    ");

    $work = function (PDO $pdo) use (
        &$insertedLogs,
        &$updatedTracking,
        &$updatedArchives,
        &$updatedProcessingTime,
        &$skipped,
        &$amountRowsFixed,
        &$amountColumnsFixed,
        &$historyOfficeVouchersFixed,
        &$historyOfficeRowsFixed,
        &$remarksVouchersFixed,
        &$remarksRowsFixed,
        &$receiveLogsInserted,
        &$receiveConvertedReturns,
        &$receiveInsertedForwards,
        &$receiveHistoryVouchersFixed,
        &$receiveHistoryRowsFixed,
        $archives,
        $insertStmt,
        $trackingStmt,
        $archiveHistoryStmt,
        $trackingCheck,
        $apply,
        $processedLogKeys
    ) {
    if (patch_should_run('processed')) {
    out('Phase 1: missing Processed by action logs');
    foreach ($archives as $archive) {
        $processingNo = trim((string) ($archive['processing_no'] ?? ''));
        $action = trim((string) ($archive['action'] ?? ''));
        $actionBy = trim((string) ($archive['action_by'] ?? ''));
        $officeFrom = trim((string) ($archive['office_from'] ?? ''));

        if ($processingNo === '' || $actionBy === '' || !is_processed_action($action)) {
            $skipped++;
            continue;
        }

        $baseHistory = normalize_process_history($archive['process_history'] ?? null);
        $historyLines = parse_history_lines($baseHistory);
        $actionFrom = resolve_action_from($historyLines, $actionBy);
        $historyOffice = voucher_tracking_resolve_office_for_history(
            $pdo,
            $actionBy,
            $officeFrom,
            trim((string) ($archive['office_to'] ?? ''))
        );
        $processedLine = build_history_line($actionBy, $action, $actionFrom, $historyOffice);

        $targetHistory = history_contains_processed_for($baseHistory, $actionBy)
            ? $baseHistory
            : append_history_line($baseHistory, $processedLine);

        $processedAt = trim((string) ($archive['datetime_action'] ?? ''));
        $encodedAt = trim((string) ($archive['datetime_encoded'] ?? ''));

        $needsLog = !isset($processedLogKeys[strtolower($processingNo . '|' . $actionBy)]);
        $needsArchiveHistory = $targetHistory !== $baseHistory;
        $needsTrackingHistory = false;
        $needsProcessingTime = false;
        $totalProcessingTime = 'TBD';

        $trackingCheck->bindValue(':processing_no', $processingNo, PDO::PARAM_STR);
        $trackingCheck->execute();
        $trackingRow = $trackingCheck->fetch(PDO::FETCH_ASSOC);

        if (is_array($trackingRow)) {
            if ($encodedAt === '') {
                $encodedAt = trim((string) ($trackingRow['datetime_encoded'] ?? ''));
            }
            if ($processedAt !== '') {
                $totalProcessingTime = voucher_tracking_calculate_total_processing_time(
                    $pdo,
                    $processingNo,
                    $processedAt,
                    '',
                    $encodedAt
                );
            }

            $current = normalize_process_history((string) ($trackingRow['process_history'] ?? ''));
            $needsTrackingHistory = $current !== $targetHistory
                && !history_contains_processed_for($current, $actionBy);

            $currentProcessingTime = trim((string) ($trackingRow['total_processing_time'] ?? ''));
            $needsProcessingTime = $totalProcessingTime !== 'TBD'
                && (
                    $currentProcessingTime === ''
                    || strcasecmp($currentProcessingTime, 'TBD') === 0
                    || $currentProcessingTime !== $totalProcessingTime
                );
        }

        if (!$needsLog && !$needsArchiveHistory && !$needsTrackingHistory && !$needsProcessingTime) {
            $skipped++;
            continue;
        }

        if ($needsLog) {
            out("  {$processingNo}: insert action log — {$action}");
            if ($apply) {
                $insertStmt->execute([
                    ':processing_no' => $processingNo,
                    ':ors_no' => (string) ($archive['ors_no'] ?? 'TBD'),
                    ':ada_check_no' => (string) ($archive['ada_check_no'] ?? 'TBD'),
                    ':dv_no' => (string) ($archive['dv_no'] ?? 'TBD'),
                    ':payee' => (string) ($archive['payee'] ?? ''),
                    ':address' => (string) ($archive['address'] ?? ''),
                    ':tin_employee_no' => (string) ($archive['tin_employee_no'] ?? ''),
                    ':particulars' => (string) ($archive['particulars'] ?? ''),
                    ':amount' => ensure_amount_two_decimals((string) ($archive['amount'] ?? '0')),
                    ':voucher_type' => (string) ($archive['voucher_type'] ?? ''),
                    ':voucher_date' => (string) ($archive['voucher_date'] ?? ''),
                    ':remarks' => trim((string) ($archive['remarks'] ?? '')) !== ''
                        ? (string) $archive['remarks']
                        : 'Payment Processed',
                    ':process_history' => $targetHistory,
                    ':encoded_by' => (string) ($archive['encoded_by'] ?? ''),
                    ':action' => $action,
                    ':action_by' => $actionBy,
                    ':action_from' => $actionFrom,
                    ':datetime_action' => trim((string) ($archive['datetime_action'] ?? '')) !== ''
                        ? (string) $archive['datetime_action']
                        : date('Y-m-d H:i:s'),
                    ':office_from' => $officeFrom,
                    ':office_to' => (string) ($archive['office_to'] ?? ''),
                    ':coa_options' => $archive['coa_options'] ?? null,
                    ':coa_category' => $archive['coa_category'] ?? null,
                    ':coa_subsection' => $archive['coa_subsection'] ?? null,
                ]);
            }
            $insertedLogs++;
        }

        if ($needsArchiveHistory) {
            out("  {$processingNo}: append Processed line to voucher_archives.process_history");
            if ($apply) {
                $archiveHistoryStmt->execute([
                    ':process_history' => $targetHistory,
                    ':processing_no' => $processingNo,
                ]);
            }
            $updatedArchives++;
        }

        if ($needsTrackingHistory || $needsProcessingTime) {
            $trackingMessages = [];
            if ($needsTrackingHistory) {
                $trackingMessages[] = 'process_history';
            }
            if ($needsProcessingTime) {
                $trackingMessages[] = "total_processing_time (first process receive → {$processedAt} = {$totalProcessingTime})";
            }
            out('  ' . $processingNo . ': update voucher_tracking — ' . implode(', ', $trackingMessages));

            if ($apply && is_array($trackingRow)) {
                $trackingStmt->execute([
                    ':process_history' => $needsTrackingHistory
                        ? $targetHistory
                        : normalize_process_history((string) ($trackingRow['process_history'] ?? '')),
                    ':total_processing_time' => $needsProcessingTime
                        ? $totalProcessingTime
                        : trim((string) ($trackingRow['total_processing_time'] ?? 'TBD')),
                    ':processing_no' => $processingNo,
                ]);
            }
            $updatedTracking++;
            if ($needsProcessingTime) {
                $updatedProcessingTime++;
            }
        }
    }
    }

    if (patch_should_run('receive')) {
        out(str_repeat('-', 72));
        out('Phase 2: missing Received by logs after Returned by (and Return-after-Return → Receive + Forward)');
        $receiveFix = fix_missing_receive_after_return($pdo, $apply);
        $receiveLogsInserted = $receiveFix['inserted_logs'];
        $receiveConvertedReturns = $receiveFix['converted_returns'];
        $receiveInsertedForwards = $receiveFix['inserted_forwards'];
        $receiveHistoryVouchersFixed = $receiveFix['updated_vouchers'];
        $receiveHistoryRowsFixed = $receiveFix['updated_rows'];
    }

    if (patch_should_run('offices')) {
        out(str_repeat('-', 72));
        out('Phase 3: process_history office labels from user_group');
        $historyOfficeFix = fix_process_history_offices($pdo, $apply);
        $historyOfficeVouchersFixed = $historyOfficeFix['updated_vouchers'];
        $historyOfficeRowsFixed = $historyOfficeFix['updated_rows'];
    }

    if (patch_should_run('amounts')) {
        out(str_repeat('-', 72));
        out('Phase 4: whole-number amounts → two decimal places');
        $amountFix = fix_whole_number_amounts($pdo, $apply);
        $amountRowsFixed = $amountFix['updated_rows'];
        $amountColumnsFixed = $amountFix['updated_columns'];
    }

    if (patch_should_run('remarks')) {
        out(str_repeat('-', 72));
        out('Phase 5: strip empty combined remarks, then rebuild from voucher_action_logs');
        $emptyRemarksFix = fix_empty_combined_remark_segments($pdo, $apply);
        $remarksFix = fix_combined_remarks_from_action_logs($pdo, $apply);
        $remarksVouchersFixed = $emptyRemarksFix['updated_vouchers'] + $remarksFix['updated_vouchers'];
        $remarksRowsFixed = $emptyRemarksFix['updated_rows'] + $remarksFix['updated_rows'];
    }
    };

    $tx = db_transaction($pdo, $work, !$apply || $dryRun);

    if (!$tx['ok']) {
        out('Migration failed: ' . ($tx['error']?->getMessage() ?? 'Unknown error'));
        if (!$isCli) {
            echo '</pre>';
        }
        exit(1);
    }

    out(str_repeat('-', 72));
    if (!$apply || $dryRun) {
        out("Preview complete. Would insert {$insertedLogs} processed log(s), insert {$receiveLogsInserted} receive-after-return log(s), convert {$receiveConvertedReturns} mistaken Return log(s) to Receive and insert {$receiveInsertedForwards} Forward log(s) for {$receiveHistoryVouchersFixed} voucher(s) / {$receiveHistoryRowsFixed} history row(s), update {$updatedArchives} archive history row(s), update {$updatedTracking} tracking row(s) ({$updatedProcessingTime} with total_processing_time), fix {$historyOfficeVouchersFixed} voucher(s) / {$historyOfficeRowsFixed} row(s) with process_history office labels, fix {$amountRowsFixed} amount row(s) ({$amountColumnsFixed} column value(s)), fix {$remarksVouchersFixed} voucher(s) / {$remarksRowsFixed} row(s) with combined remarks, skipped {$skipped}.");
        out('Re-run with --apply (CLI) or ?apply=1 (browser) to save. Use --dry-run to execute then roll back.');
    } else {
        out("Done. Inserted {$insertedLogs} processed log(s), inserted {$receiveLogsInserted} receive-after-return log(s), converted {$receiveConvertedReturns} mistaken Return log(s) to Receive and inserted {$receiveInsertedForwards} Forward log(s) for {$receiveHistoryVouchersFixed} voucher(s) / {$receiveHistoryRowsFixed} history row(s), updated {$updatedArchives} archive history row(s), updated {$updatedTracking} tracking row(s) ({$updatedProcessingTime} with total_processing_time), fixed {$historyOfficeVouchersFixed} voucher(s) / {$historyOfficeRowsFixed} row(s) with process_history office labels, fixed {$amountRowsFixed} amount row(s) ({$amountColumnsFixed} column value(s)), fixed {$remarksVouchersFixed} voucher(s) / {$remarksRowsFixed} row(s) with combined remarks, skipped {$skipped}.");
    }
} catch (Throwable $e) {
    out('Migration failed: ' . $e->getMessage());
    if (!$isCli) {
        echo '</pre>';
    }
    exit(1);
}

if (!$isCli) {
    echo '</pre>';
}
