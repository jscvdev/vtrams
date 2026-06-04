<?php
/**
 * One-time migration: normalize process_history section labels MSD / Accounting → ACCOUNTING.
 *
 * Each history line uses: "NAME | action | section | office"
 * Only lines for the target employees below are updated; only the third field (section)
 * is changed when it is MSD, Accounting, or Accounting Unit.
 *
 * Usage (CLI, from project root or SQL folder):
 *   php SQL/migrate_process_history_msd_accounting.php           # dry-run (preview only)
 *   php SQL/migrate_process_history_msd_accounting.php --apply   # write changes
 *
 * Browser (dry-run unless ?apply=1):
 *   http://localhost/vtrams/SQL/migrate_process_history_msd_accounting.php
 *   http://localhost/vtrams/SQL/migrate_process_history_msd_accounting.php?apply=1
 */

declare(strict_types=1);

require_once __DIR__ . '/../protected/dbconnection.inc.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    die("Error: Database connection not available. Check protected/dbconnection.inc.php\n");
}

$isCli = PHP_SAPI === 'cli';
$apply = $isCli
    ? in_array('--apply', $argv ?? [], true)
    : isset($_GET['apply']) && (string)$_GET['apply'] === '1';

$tables = [
    'vouchers',
    'voucher_incoming',
    'voucher_receiving',
    'voucher_sent',
    'voucher_archives',
    'voucher_temp',
    'voucher_tracking',
    'voucher_action_logs',
    'dv_entries',
];

/** @var list<string> Only history lines whose first field matches one of these names are updated. */
$targetNames = [
    'GRACILE B. PALCE',
    'DIANA E. COSTUNA',
    'NATHALLIE D. BALEÑA',
];

function line_matches_target_name(string $namePart, array $targetNames): bool
{
    $name = trim($namePart);
    if ($name === '') {
        return false;
    }

    foreach ($targetNames as $target) {
        if (strcasecmp($name, $target) === 0) {
            return true;
        }
    }

    // Common DB encoding variant for Baleña
    if (strcasecmp($name, 'NATHALLIE D. BALENA') === 0) {
        return true;
    }

    return false;
}

/**
 * Return ACCOUNTING when the section should be normalized; null if unchanged.
 */
function section_to_accounting(string $section): ?string
{
    $trimmed = trim($section);
    if ($trimmed === '') {
        return null;
    }

    if ($trimmed === 'ACCOUNTING') {
        return null;
    }

    $upper = strtoupper($trimmed);
    if (in_array($upper, ['MSD', 'ACCOUNTING', 'ACCOUNTING UNIT'], true)) {
        return 'ACCOUNTING';
    }

    return null;
}

/**
 * Rewrite pipe-delimited history lines for target employees only; legacy colon lines are left unchanged.
 *
 * @param list<string> $targetNames
 */
function rewrite_process_history(?string $value, array $targetNames): array
{
    if ($value === null || trim($value) === '') {
        return ['changed' => false, 'value' => $value, 'line_changes' => 0];
    }

    $lines = preg_split('/\r\n|\r|\n/', $value);
    $changed = false;
    $lineChanges = 0;

    foreach ($lines as $i => $line) {
        if (strpos($line, '|') === false) {
            continue;
        }

        $parts = preg_split('/\s*\|\s*/', $line, 4);
        if (!isset($parts[0], $parts[2])) {
            continue;
        }

        if (!line_matches_target_name($parts[0], $targetNames)) {
            continue;
        }

        $replacement = section_to_accounting($parts[2]);
        if ($replacement === null) {
            continue;
        }

        $parts[2] = $replacement;
        $lines[$i] = implode(' | ', $parts);
        $changed = true;
        $lineChanges++;
    }

    if (!$changed) {
        return ['changed' => false, 'value' => $value, 'line_changes' => 0];
    }

    return [
        'changed' => true,
        'value' => implode("\n", $lines),
        'line_changes' => $lineChanges,
    ];
}

function out(string $message): void
{
    global $isCli;
    echo $message . ($isCli ? "\n" : "<br>\n");
}

if (!$isCli) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<pre style="font-family:monospace">';
}

out('process_history migration: MSD / Accounting → ACCOUNTING (target employees only)');
out('Targets: ' . implode(', ', $targetNames));
out('Mode: ' . ($apply ? 'APPLY (updates will be saved)' : 'DRY-RUN (preview only; pass --apply or ?apply=1 to commit)'));
out(str_repeat('-', 72));

$grandRows = 0;
$grandLines = 0;

try {
    if ($apply) {
        $pdo->beginTransaction();
    }

    foreach ($tables as $table) {
        try {
            $pdo->query("SELECT 1 FROM `{$table}` LIMIT 1");
        } catch (PDOException $e) {
            out("Skip {$table}: table not found.");
            continue;
        }

        $colCheck = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE 'process_history'");
        if (!$colCheck || $colCheck->rowCount() === 0) {
            out("Skip {$table}: no process_history column.");
            continue;
        }

        $idCol = 'id';
        $idCheck = $pdo->query("SHOW COLUMNS FROM `{$table}` LIKE 'id'");
        if (!$idCheck || $idCheck->rowCount() === 0) {
            $idCol = null;
        }

        $nameClauses = [];
        foreach ($targetNames as $targetName) {
            $escaped = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $targetName);
            $nameClauses[] = "process_history LIKE '%" . $escaped . "%'";
        }
        $nameClauses[] = "process_history LIKE '%NATHALLIE D. BALENA%'";
        $nameFilter = implode(' OR ', $nameClauses);

        $where = "
            process_history IS NOT NULL
            AND process_history <> ''
            AND ({$nameFilter})
            AND (
                process_history LIKE '%| MSD |%'
                OR process_history LIKE '%| Accounting |%'
                OR process_history LIKE '%| ACCOUNTING UNIT |%'
                OR process_history LIKE '%| Accounting Unit |%'
                OR UPPER(process_history) LIKE '%| MSD |%'
                OR UPPER(process_history) LIKE '%| ACCOUNTING |%'
            )
        ";

        $selectSql = $idCol
            ? "SELECT `{$idCol}` AS row_id, process_history FROM `{$table}` WHERE {$where}"
            : "SELECT process_history FROM `{$table}` WHERE {$where}";

        $stmt = $pdo->query($selectSql);
        $rows = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];

        $tableRows = 0;
        $tableLines = 0;

        foreach ($rows as $row) {
            $result = rewrite_process_history($row['process_history'] ?? null, $targetNames);
            if (!$result['changed']) {
                continue;
            }

            $tableRows++;
            $tableLines += (int)$result['line_changes'];

            $label = $idCol ? "{$table}#{$row['row_id']}" : $table;
            out("  {$label}: {$result['line_changes']} line(s) updated");

            if ($apply) {
                if ($idCol) {
                    $update = $pdo->prepare(
                        "UPDATE `{$table}` SET process_history = :ph WHERE `{$idCol}` = :id"
                    );
                    $update->bindValue(':ph', $result['value'], PDO::PARAM_STR);
                    $update->bindValue(':id', $row['row_id'], PDO::PARAM_INT);
                } else {
                    $update = $pdo->prepare(
                        "UPDATE `{$table}` SET process_history = :ph WHERE process_history = :old"
                    );
                    $update->bindValue(':ph', $result['value'], PDO::PARAM_STR);
                    $update->bindValue(':old', $row['process_history'], PDO::PARAM_STR);
                }
                $update->execute();
            }
        }

        out("{$table}: {$tableRows} row(s), {$tableLines} history line(s)" . ($tableRows ? '' : ' (no changes)'));
        $grandRows += $tableRows;
        $grandLines += $tableLines;
    }

    if ($apply) {
        $pdo->commit();
        out(str_repeat('-', 72));
        out("Done. Committed {$grandRows} row(s), {$grandLines} line(s) normalized to ACCOUNTING.");
    } else {
        out(str_repeat('-', 72));
        out("Preview complete. Would update {$grandRows} row(s), {$grandLines} line(s).");
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
