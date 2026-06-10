<?php
/**
 * One-time fix: backfill missing "Processed By" rows in voucher_action_logs
 * using process_history from voucher_archives, then sync process_history on voucher_tracking.
 *
 * Handles archived vouchers where process_history contains "Processed By" but no
 * matching action log exists, and vouchers that only have "Prepared By" in history
 * (legacy process_voucher flow) by synthesizing a "Processed By" entry from the
 * same person/section/office.
 *
 * Usage (CLI, from project root or SQL folder):
 *   c:\xampp\php\php.exe SQL/fix_missing_processed_by_logs.php           # dry-run
 *   c:\xampp\php\php.exe SQL/fix_missing_processed_by_logs.php --apply   # commit
 *
 * Browser (dry-run unless ?apply=1):
 *   http://localhost/vtrams/SQL/fix_missing_processed_by_logs.php
 *   http://localhost/vtrams/SQL/fix_missing_processed_by_logs.php?apply=1
 */

declare(strict_types=1);

require_once __DIR__ . '/../protected/dbconnection.inc.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    die("Error: Database connection not available. Check protected/dbconnection.inc.php\n");
}

$isCli = PHP_SAPI === 'cli';
$apply = $isCli
    ? in_array('--apply', $argv ?? [], true)
    : isset($_GET['apply']) && (string) $_GET['apply'] === '1';

/**
 * @return list<array{name: string, action: string, section: string, office: string}>
 */
function parse_history_lines(?string $value): array
{
    if ($value === null || trim($value) === '') {
        return [];
    }

    $parsed = [];
    foreach (preg_split('/\r\n|\r|\n/', $value) as $line) {
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

function is_processed_by_action(string $action): bool
{
    return (bool) preg_match('/^Processed\s+By\s*:/i', $action);
}

function is_prepared_by_action(string $action): bool
{
    return (bool) preg_match('/^Prepared\s+By\s*:/i', $action);
}

/**
 * @param list<array{name: string, action: string, section: string, office: string}> $lines
 * @return list<array{name: string, action: string, section: string, office: string, synthesized: bool}>
 */
function resolve_processed_by_lines(array $lines): array
{
    $processed = [];
    foreach ($lines as $line) {
        if (!is_processed_by_action($line['action'])) {
            continue;
        }
        $line['synthesized'] = false;
        $processed[] = $line;
    }

    if ($processed !== []) {
        return $processed;
    }

    foreach ($lines as $line) {
        if (!is_prepared_by_action($line['action'])) {
            continue;
        }
        $name = $line['name'];
        if ($name === '') {
            continue;
        }
        $processed[] = [
            'name' => $name,
            'action' => 'Processed By: ' . $name,
            'section' => $line['section'],
            'office' => $line['office'],
            'synthesized' => true,
        ];
        break;
    }

    return $processed;
}

/**
 * @param list<array{name: string, action: string, section: string, office: string}> $lines
 */
function build_process_history(array $lines): string
{
    $rows = [];
    foreach ($lines as $line) {
        $rows[] = implode(' | ', [
            $line['name'],
            $line['action'],
            $line['section'],
            $line['office'],
        ]);
    }

    return implode("\n", $rows);
}

/**
 * Insert a synthesized Processed By line after the last Prepared By line when missing.
 *
 * @param list<array{name: string, action: string, section: string, office: string}> $lines
 * @return array{changed: bool, lines: list<array{name: string, action: string, section: string, office: string}>}
 */
function append_synthesized_processed_line(array $lines, array $processedLine): array
{
    foreach ($lines as $line) {
        if (is_processed_by_action($line['action'])) {
            return ['changed' => false, 'lines' => $lines];
        }
    }

    $out = [];
    $inserted = false;
    $lastPreparedIdx = null;
    foreach ($lines as $i => $line) {
        if (is_prepared_by_action($line['action'])) {
            $lastPreparedIdx = $i;
        }
    }

    foreach ($lines as $i => $line) {
        $out[] = $line;
        if ($lastPreparedIdx !== null && $i === $lastPreparedIdx && !$inserted) {
            $out[] = [
                'name' => $processedLine['name'],
                'action' => $processedLine['action'],
                'section' => $processedLine['section'],
                'office' => $processedLine['office'],
            ];
            $inserted = true;
        }
    }

    if (!$inserted) {
        $out[] = [
            'name' => $processedLine['name'],
            'action' => $processedLine['action'],
            'section' => $processedLine['section'],
            'office' => $processedLine['office'],
        ];
    }

    return ['changed' => true, 'lines' => $out];
}

function out(string $message): void
{
    global $isCli;
    echo $message . ($isCli ? "\n" : "<br>\n");
}

function processed_by_exists(PDO $pdo, string $processingNo, string $actionBy): bool
{
    $stmt = $pdo->prepare("
        SELECT 1
        FROM voucher_action_logs
        WHERE processing_no = :processing_no
          AND action_by = :action_by
          AND (action LIKE 'Processed By%' OR action LIKE 'Processed by%')
        LIMIT 1
    ");
    $stmt->bindValue(':processing_no', $processingNo, PDO::PARAM_STR);
    $stmt->bindValue(':action_by', $actionBy, PDO::PARAM_STR);
    $stmt->execute();

    return (bool) $stmt->fetchColumn();
}

function fetch_prepared_by_datetime(PDO $pdo, string $processingNo, string $actionBy): ?string
{
    $stmt = $pdo->prepare("
        SELECT datetime_action
        FROM voucher_action_logs
        WHERE processing_no = :processing_no
          AND action_by = :action_by
          AND action LIKE 'Prepared By%'
        ORDER BY datetime_action DESC, id DESC
        LIMIT 1
    ");
    $stmt->bindValue(':processing_no', $processingNo, PDO::PARAM_STR);
    $stmt->bindValue(':action_by', $actionBy, PDO::PARAM_STR);
    $stmt->execute();
    $value = $stmt->fetchColumn();

    return $value !== false ? trim((string) $value) : null;
}

function bump_datetime(string $datetime): string
{
    $ts = strtotime($datetime);
    if ($ts === false) {
        return $datetime;
    }

    return date('Y-m-d H:i:s', $ts + 1);
}

if (!$isCli) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<pre style="font-family:monospace">';
}

out('Fix missing Processed By in voucher_action_logs (source: voucher_archives)');
out('Mode: ' . ($apply ? 'APPLY (updates will be saved)' : 'DRY-RUN (preview only; pass --apply or ?apply=1 to commit)'));
out(str_repeat('-', 72));

$insertedLogs = 0;
$updatedTracking = 0;
$skipped = 0;

try {
    if ($apply) {
        $pdo->beginTransaction();
    }

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
            datetime_action,
            process_history,
            coa_options,
            coa_category,
            coa_subsection
        FROM voucher_archives
        ORDER BY processing_no ASC
    ");

    $archives = $archiveStmt ? $archiveStmt->fetchAll(PDO::FETCH_ASSOC) : [];
    out('Scanning ' . count($archives) . ' archived voucher(s)...');

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
        SET process_history = :process_history
        WHERE processing_no = :processing_no
    ");

    $trackingCheck = $pdo->prepare("
        SELECT process_history
        FROM voucher_tracking
        WHERE processing_no = :processing_no
        LIMIT 1
    ");

    foreach ($archives as $archive) {
        $processingNo = trim((string) ($archive['processing_no'] ?? ''));
        if ($processingNo === '') {
            continue;
        }

        $historyLines = parse_history_lines($archive['process_history'] ?? null);
        $processedLines = resolve_processed_by_lines($historyLines);

        if ($processedLines === []) {
            $skipped++;
            continue;
        }

        $finalHistoryLines = $historyLines;
        $historyChanged = false;
        $insertedForVoucher = 0;

        foreach ($processedLines as $processedLine) {
            $actionBy = $processedLine['name'];
            if ($actionBy === '') {
                continue;
            }

            if (processed_by_exists($pdo, $processingNo, $actionBy)) {
                continue;
            }

            if (!empty($processedLine['synthesized'])) {
                $append = append_synthesized_processed_line($finalHistoryLines, $processedLine);
                if ($append['changed']) {
                    $finalHistoryLines = $append['lines'];
                    $historyChanged = true;
                }
            }

            $datetimeAction = fetch_prepared_by_datetime($pdo, $processingNo, $actionBy);
            if ($datetimeAction === null || $datetimeAction === '') {
                $datetimeAction = trim((string) ($archive['datetime_action'] ?? ''));
            }
            if ($datetimeAction !== '') {
                $datetimeAction = bump_datetime($datetimeAction);
            } else {
                $datetimeAction = date('Y-m-d H:i:s');
            }

            $processHistory = build_process_history($finalHistoryLines);

            out("  {$processingNo}: insert Processed By log for {$actionBy}"
                . (!empty($processedLine['synthesized']) ? ' (synthesized from Prepared By)' : ''));

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
                    ':remarks' => (string) ($archive['remarks'] ?? ''),
                    ':process_history' => $processHistory,
                    ':encoded_by' => (string) ($archive['encoded_by'] ?? ''),
                    ':action' => $processedLine['action'],
                    ':action_by' => $actionBy,
                    ':action_from' => $processedLine['section'],
                    ':datetime_action' => $datetimeAction,
                    ':office_from' => $processedLine['office'] !== ''
                        ? $processedLine['office']
                        : (string) ($archive['office_from'] ?? ''),
                    ':office_to' => (string) ($archive['office_to'] ?? ''),
                    ':coa_options' => $archive['coa_options'] ?? null,
                    ':coa_category' => $archive['coa_category'] ?? null,
                    ':coa_subsection' => $archive['coa_subsection'] ?? null,
                ]);
            }

            $insertedLogs++;
            $insertedForVoucher++;
        }

        $targetHistory = $historyChanged
            ? build_process_history($finalHistoryLines)
            : trim((string) ($archive['process_history'] ?? ''));

        if ($targetHistory === '') {
            continue;
        }

        $trackingCheck->bindValue(':processing_no', $processingNo, PDO::PARAM_STR);
        $trackingCheck->execute();
        $currentTrackingHistory = $trackingCheck->fetchColumn();

        if ($currentTrackingHistory === false) {
            continue;
        }

        $current = trim((string) $currentTrackingHistory);
        $needsTrackingUpdate = $current !== $targetHistory
            && (
                $insertedForVoucher > 0
                || $historyChanged
                || (
                    stripos($targetHistory, 'Processed By') !== false
                    && stripos($current, 'Processed By') === false
                )
            );

        if ($needsTrackingUpdate) {
            out("  {$processingNo}: sync process_history on voucher_tracking");
            if ($apply) {
                $trackingStmt->execute([
                    ':process_history' => $targetHistory,
                    ':processing_no' => $processingNo,
                ]);
            }
            $updatedTracking++;
        }
    }

    if ($apply) {
        $pdo->commit();
        out(str_repeat('-', 72));
        out("Done. Inserted {$insertedLogs} action log(s), updated {$updatedTracking} tracking row(s), skipped {$skipped} archive(s) with no Prepared/Processed data.");
    } else {
        out(str_repeat('-', 72));
        out("Preview complete. Would insert {$insertedLogs} action log(s), update {$updatedTracking} tracking row(s), skipped {$skipped} archive(s) with no Prepared/Processed data.");
        out('Re-run with --apply (CLI) or ?apply=1 (browser) to save.');
    }
} catch (Throwable $e) {
    if ($apply && $pdo->inTransaction()) {
        $pdo->rollBack();
    }
    out('Migration failed: ' . $e->getMessage());
    if (!$isCli) {
        echo '</pre>';
    }
    exit(1);
}

if (!$isCli) {
    echo '</pre>';
}
