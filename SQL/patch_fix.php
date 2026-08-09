<?php
/**
 * One-time maintenance patch:
 * 1) Backfill missing cashier "Processed by" rows in voucher_action_logs from
 *    voucher_archives, sync process_history on voucher_tracking, and compute
 *    total_processing_time from first receive at Planning/CDS through cashier Processed by.
 * 2) Correct process_history office labels using user_group (actor's registered office).
 * 3) Normalize stored amounts that are whole numbers (e.g. 15000 → 15000.00) across
 *    voucher tables.
 * 4) Rebuild combined remarks on voucher tables from voucher_action_logs.remarks.
 *
 * Usage (CLI):
 *   c:\xampp\php\php.exe SQL/patch_fix.php           # dry-run
 *   c:\xampp\php\php.exe SQL/patch_fix.php --apply   # commit
 *
 * Browser:
 *   http://localhost/vtrams/SQL/patch_fix.php
 *   http://localhost/vtrams/SQL/patch_fix.php?apply=1
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

function out(string $message): void
{
    global $isCli;
    echo $message . ($isCli ? "\n" : "<br>\n");
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
function fix_process_history_offices(PDO $pdo, bool $apply): array
{
    $updatedVouchers = 0;
    $updatedRows = 0;
    $bestByProcessingNo = [];

    foreach (PROCESS_HISTORY_OFFICE_TABLES as $table) {
        try {
            $pdo->query("SELECT 1 FROM `{$table}` LIMIT 1");
        } catch (PDOException) {
            out("Skip {$table}: table not found.");
            continue;
        }

        $columns = [];
        $columnStmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
        if ($columnStmt) {
            while ($columnRow = $columnStmt->fetch(PDO::FETCH_ASSOC)) {
                $field = trim((string) ($columnRow['Field'] ?? ''));
                if ($field !== '') {
                    $columns[$field] = true;
                }
            }
        }

        if (!isset($columns['processing_no'], $columns['process_history'])) {
            out("Skip {$table}: missing processing_no or process_history.");
            continue;
        }

        $selectCols = ['processing_no', 'process_history'];
        if (isset($columns['voucher_type'])) {
            $selectCols[] = 'voucher_type';
        }

        $selectSql = 'SELECT ' . implode(', ', array_map(
            static fn (string $column): string => '`' . $column . '`',
            $selectCols
        )) . " FROM `{$table}` WHERE TRIM(COALESCE(process_history, '')) <> ''";
        $rows = $pdo->query($selectSql)?->fetchAll(PDO::FETCH_ASSOC) ?? [];

        out('Scanning ' . count($rows) . " row(s) in {$table} for process_history office labels...");

        foreach ($rows as $row) {
            $processingNo = trim((string) ($row['processing_no'] ?? ''));
            $history = normalize_process_history($row['process_history'] ?? null);
            if ($processingNo === '' || $history === '') {
                continue;
            }

            $voucherType = trim((string) ($row['voucher_type'] ?? ''));
            if (!isset($bestByProcessingNo[$processingNo])) {
                $bestByProcessingNo[$processingNo] = [
                    'history' => $history,
                    'voucher_type' => $voucherType,
                ];
                continue;
            }

            if (strlen($history) > strlen($bestByProcessingNo[$processingNo]['history'])) {
                $bestByProcessingNo[$processingNo]['history'] = $history;
            }
            if ($bestByProcessingNo[$processingNo]['voucher_type'] === '' && $voucherType !== '') {
                $bestByProcessingNo[$processingNo]['voucher_type'] = $voucherType;
            }
        }
    }

    foreach ($bestByProcessingNo as $processingNo => $data) {
        $history = (string) ($data['history'] ?? '');
        $voucherType = (string) ($data['voucher_type'] ?? '');
        $enriched = voucher_tracking_enrich_process_history_for_return($pdo, $history, $voucherType);
        if ($enriched === $history) {
            continue;
        }

        out("  {$processingNo}: rewrite process_history office labels");
        $updatedVouchers++;

        foreach (PROCESS_HISTORY_OFFICE_TABLES as $table) {
            try {
                $pdo->query("SELECT 1 FROM `{$table}` LIMIT 1");
            } catch (PDOException) {
                continue;
            }

            $columnStmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
            $columns = [];
            if ($columnStmt) {
                while ($columnRow = $columnStmt->fetch(PDO::FETCH_ASSOC)) {
                    $field = trim((string) ($columnRow['Field'] ?? ''));
                    if ($field !== '') {
                        $columns[$field] = true;
                    }
                }
            }
            if (!isset($columns['processing_no'], $columns['process_history'])) {
                continue;
            }

            $countStmt = $pdo->prepare(
                "SELECT COUNT(*) FROM `{$table}`
                 WHERE processing_no = :processing_no
                   AND TRIM(COALESCE(process_history, '')) <> ''
                   AND process_history <> :enriched"
            );
            $countStmt->bindValue(':processing_no', $processingNo, PDO::PARAM_STR);
            $countStmt->bindValue(':enriched', $enriched, PDO::PARAM_STR);
            $countStmt->execute();
            $rowCount = (int) $countStmt->fetchColumn();
            if ($rowCount === 0) {
                continue;
            }

            out("    {$table}: update {$rowCount} row(s)");
            if ($apply) {
                $updateStmt = $pdo->prepare(
                    "UPDATE `{$table}`
                     SET process_history = :enriched
                     WHERE processing_no = :processing_no
                       AND TRIM(COALESCE(process_history, '')) <> ''"
                );
                $updateStmt->bindValue(':enriched', $enriched, PDO::PARAM_STR);
                $updateStmt->bindValue(':processing_no', $processingNo, PDO::PARAM_STR);
                $updateStmt->execute();
            }
            $updatedRows += $rowCount;
        }
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

        $targetRemarks = voucher_tracking_build_combined_remarks_from_action_logs($logs);
        if ($targetRemarks === '') {
            continue;
        }

        $voucherUpdated = false;
        foreach (REMARKS_SYNC_TABLES as $table) {
            try {
                $pdo->query("SELECT 1 FROM `{$table}` LIMIT 1");
            } catch (PDOException) {
                out("Skip {$table}: table not found.");
                continue;
            }

            $columnStmt = $pdo->query("SHOW COLUMNS FROM `{$table}`");
            $columns = [];
            if ($columnStmt) {
                while ($columnRow = $columnStmt->fetch(PDO::FETCH_ASSOC)) {
                    $field = trim((string) ($columnRow['Field'] ?? ''));
                    if ($field !== '') {
                        $columns[$field] = true;
                    }
                }
            }

            if (!isset($columns['processing_no'], $columns['remarks'])) {
                continue;
            }

            $countStmt = $pdo->prepare(
                "SELECT COUNT(*) FROM `{$table}`
                 WHERE processing_no = :processing_no
                   AND TRIM(COALESCE(remarks, '')) <> :target_remarks"
            );
            $countStmt->bindValue(':processing_no', $processingNo, PDO::PARAM_STR);
            $countStmt->bindValue(':target_remarks', $targetRemarks, PDO::PARAM_STR);
            $countStmt->execute();
            $rowCount = (int) $countStmt->fetchColumn();
            if ($rowCount === 0) {
                continue;
            }

            if (!$voucherUpdated) {
                out("  {$processingNo}: rebuild combined remarks");
                $voucherUpdated = true;
                $updatedVouchers++;
            }

            out("    {$table}: update {$rowCount} row(s)");
            if ($apply) {
                $updateStmt = $pdo->prepare(
                    "UPDATE `{$table}`
                     SET remarks = :target_remarks_set
                     WHERE processing_no = :processing_no
                       AND TRIM(COALESCE(remarks, '')) <> :target_remarks_where"
                );
                $updateStmt->bindValue(':target_remarks_set', $targetRemarks, PDO::PARAM_STR);
                $updateStmt->bindValue(':target_remarks_where', $targetRemarks, PDO::PARAM_STR);
                $updateStmt->bindValue(':processing_no', $processingNo, PDO::PARAM_STR);
                $updateStmt->execute();
            }
            $updatedRows += $rowCount;
        }
    }

    return [
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

out('Maintenance patch: Processed by logs + process_history offices + amount decimals + combined remarks');
out('Mode: ' . ($apply ? 'APPLY (updates will be saved)' : 'DRY-RUN (preview only; pass --apply or ?apply=1 to commit)'));
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

try {
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
        $archives,
        $insertStmt,
        $trackingStmt,
        $archiveHistoryStmt,
        $trackingCheck,
        $apply
    ) {
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

        $needsLog = !processed_log_exists($pdo, $processingNo, $actionBy);
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

    out(str_repeat('-', 72));
    out('Phase 2: process_history office labels from user_group');
    $historyOfficeFix = fix_process_history_offices($pdo, $apply);
    $historyOfficeVouchersFixed = $historyOfficeFix['updated_vouchers'];
    $historyOfficeRowsFixed = $historyOfficeFix['updated_rows'];

    out(str_repeat('-', 72));
    out('Phase 3: whole-number amounts → two decimal places');
    $amountFix = fix_whole_number_amounts($pdo, $apply);
    $amountRowsFixed = $amountFix['updated_rows'];
    $amountColumnsFixed = $amountFix['updated_columns'];

    out(str_repeat('-', 72));
    out('Phase 4: combined remarks from voucher_action_logs');
    $remarksFix = fix_combined_remarks_from_action_logs($pdo, $apply);
    $remarksVouchersFixed = $remarksFix['updated_vouchers'];
    $remarksRowsFixed = $remarksFix['updated_rows'];
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
        out("Preview complete. Would insert {$insertedLogs} log(s), update {$updatedArchives} archive history row(s), update {$updatedTracking} tracking row(s) ({$updatedProcessingTime} with total_processing_time), fix {$historyOfficeVouchersFixed} voucher(s) / {$historyOfficeRowsFixed} row(s) with process_history office labels, fix {$amountRowsFixed} amount row(s) ({$amountColumnsFixed} column value(s)), fix {$remarksVouchersFixed} voucher(s) / {$remarksRowsFixed} row(s) with combined remarks, skipped {$skipped}.");
        out('Re-run with --apply (CLI) or ?apply=1 (browser) to save. Use --dry-run to execute then roll back.');
    } else {
        out("Done. Inserted {$insertedLogs} log(s), updated {$updatedArchives} archive history row(s), updated {$updatedTracking} tracking row(s) ({$updatedProcessingTime} with total_processing_time), fixed {$historyOfficeVouchersFixed} voucher(s) / {$historyOfficeRowsFixed} row(s) with process_history office labels, fixed {$amountRowsFixed} amount row(s) ({$amountColumnsFixed} column value(s)), fixed {$remarksVouchersFixed} voucher(s) / {$remarksRowsFixed} row(s) with combined remarks, skipped {$skipped}.");
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
