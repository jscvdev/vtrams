<?php
/**
 * One-time fix: backfill missing cashier "Processed by" rows in voucher_action_logs
 * from voucher_archives, sync process_history on voucher_tracking, and compute
 * total_processing_time from datetime_encoded through cashier Processed by.
 *
 * Targets archives created by the ADA multi-handler where action is
 * "Processed by: NAME" but the action log insert did not run (or failed),
 * and process_history was never appended with the final Processed line.
 *
 * Usage (CLI):
 *   c:\xampp\php\php.exe SQL/fix_missing_processed_by_logs.php           # dry-run
 *   c:\xampp\php\php.exe SQL/fix_missing_processed_by_logs.php --apply   # commit
 *
 * Browser:
 *   http://localhost/vtrams/SQL/fix_missing_processed_by_logs.php
 *   http://localhost/vtrams/SQL/fix_missing_processed_by_logs.php?apply=1
 */

declare(strict_types=1);

require_once __DIR__ . '/../protected/dbconnection.inc.php';
require_once __DIR__ . '/../protected/core/components/helpers/handler_transaction_helper.inc.php';

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

function calculate_total_processing_time(string $startTimestamp, string $endTimestamp): string
{
    $startTime = strtotime($startTimestamp);
    $endTime = strtotime($endTimestamp);
    if ($startTime === false || $endTime === false || $endTime < $startTime) {
        return 'TBD';
    }

    $durationSeconds = $endTime - $startTime;
    $days = (int) floor($durationSeconds / (24 * 3600));
    $remainder = $durationSeconds % (24 * 3600);
    $hours = (int) floor($remainder / 3600);
    $remainder %= 3600;
    $minutes = (int) floor($remainder / 60);
    $seconds = (int) ($remainder % 60);

    $parts = [];
    if ($days > 0) {
        $parts[] = $days . ' day' . ($days > 1 ? 's' : '');
    }
    if ($hours > 0) {
        $parts[] = $hours . ' hour' . ($hours > 1 ? 's' : '');
    }
    if ($minutes > 0) {
        $parts[] = $minutes . ' minute' . ($minutes > 1 ? 's' : '');
    }
    if ($seconds > 0) {
        $parts[] = $seconds . ' second' . ($seconds > 1 ? 's' : '');
    }

    return $parts !== [] ? implode(' ', $parts) : '0 seconds';
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

out('Fix missing Processed by in voucher_action_logs (source: voucher_archives.action)');
out('Mode: ' . ($apply ? 'APPLY (updates will be saved)' : 'DRY-RUN (preview only; pass --apply or ?apply=1 to commit)'));
out(str_repeat('-', 72));

$insertedLogs = 0;
$updatedTracking = 0;
$updatedArchives = 0;
$updatedProcessingTime = 0;
$skipped = 0;

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
        $archives,
        $insertStmt,
        $trackingStmt,
        $archiveHistoryStmt,
        $trackingCheck,
        $apply
    ) {
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
        $processedLine = build_history_line($actionBy, $action, $actionFrom, $officeFrom);

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
            if ($processedAt !== '' && $encodedAt !== '') {
                $totalProcessingTime = calculate_total_processing_time($encodedAt, $processedAt);
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
                    ':amount' => (string) ($archive['amount'] ?? '0'),
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
                $trackingMessages[] = "total_processing_time ({$encodedAt} → {$processedAt} = {$totalProcessingTime})";
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
        out("Preview complete. Would insert {$insertedLogs} log(s), update {$updatedArchives} archive history row(s), update {$updatedTracking} tracking row(s) ({$updatedProcessingTime} with total_processing_time), skipped {$skipped}.");
        out('Re-run with --apply (CLI) or ?apply=1 (browser) to save. Use --dry-run to execute then roll back.');
    } else {
        out("Done. Inserted {$insertedLogs} log(s), updated {$updatedArchives} archive history row(s), updated {$updatedTracking} tracking row(s) ({$updatedProcessingTime} with total_processing_time), skipped {$skipped}.");
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
