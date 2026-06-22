<?php
/**
 * One-time database fixes:
 *   1) Ensure user_group login lockout columns (is_blocked, failed_login_attempts)
 *   2) Decode HTML entities: &amp; → & in all string/text columns (voucher tables + user_group)
 *   3) Normalize process_history section labels MSD / Accounting → ACCOUNTING (target employees)
 *
 * Usage (CLI, from project root):
 *   php SQL/fix_database_bugs.php           # dry-run (preview only)
 *   php SQL/fix_database_bugs.php --apply   # write changes
 *
 * Browser (dry-run unless ?apply=1):
 *   http://localhost/vtrams/SQL/fix_database_bugs.php
 *   http://localhost/vtrams/SQL/fix_database_bugs.php?apply=1
 */

declare(strict_types=1);

require_once __DIR__ . '/../protected/dbconnection.inc.php';
require_once __DIR__ . '/../protected/core/components/helpers/user_login_security_helper.inc.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    die("Error: Database connection not available. Check protected/dbconnection.inc.php\n");
}

$isCli = PHP_SAPI === 'cli';
$apply = $isCli
    ? in_array('--apply', $argv ?? [], true)
    : isset($_GET['apply']) && (string) $_GET['apply'] === '1';

$ampSearch = '&amp;';
$ampReplace = '&';

$ampTables = [
    'user_group',
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

$processHistoryTables = [
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

function out(string $message): void
{
    global $isCli;
    echo $message . ($isCli ? "\n" : "<br>\n");
}

function table_exists(PDO $pdo, string $table): bool
{
    try {
        $pdo->query("SELECT 1 FROM `{$table}` LIMIT 1");

        return true;
    } catch (PDOException) {
        return false;
    }
}

/** @return list<string> */
function get_text_columns(PDO $pdo, string $table): array
{
    $stmt = $pdo->prepare(
        'SELECT COLUMN_NAME
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = ?
           AND DATA_TYPE IN ("char", "varchar", "tinytext", "text", "mediumtext", "longtext")
         ORDER BY ORDINAL_POSITION'
    );
    $stmt->execute([$table]);

    $columns = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $columns[] = (string) $row['COLUMN_NAME'];
    }

    return $columns;
}

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

    if (strcasecmp($name, 'NATHALLIE D. BALENA') === 0) {
        return true;
    }

    return false;
}

function section_to_accounting(string $section): ?string
{
    $trimmed = trim($section);
    if ($trimmed === '' || $trimmed === 'ACCOUNTING') {
        return null;
    }

    $upper = strtoupper($trimmed);
    if (in_array($upper, ['MSD', 'ACCOUNTING', 'ACCOUNTING UNIT'], true)) {
        return 'ACCOUNTING';
    }

    return null;
}

/** @param list<string> $targetNames */
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

function run_schema_fix(PDO $pdo, bool $apply): array
{
    if ($apply) {
        user_login_ensure_schema($pdo);
        out('Schema: ensured user_group.is_blocked and user_group.failed_login_attempts');
    } else {
        $missing = [];
        foreach (['is_blocked', 'failed_login_attempts'] as $col) {
            $check = $pdo->query("SHOW COLUMNS FROM `user_group` LIKE '{$col}'");
            if (!$check || $check->rowCount() === 0) {
                $missing[] = $col;
            }
        }
        if ($missing === []) {
            out('Schema: user_group lockout columns already present');
        } else {
            out('Schema: would add user_group columns: ' . implode(', ', $missing));
        }
    }

    return ['ok' => true];
}

function run_amp_fix(PDO $pdo, bool $apply, array $tables, string $search, string $replace): array
{
    $grandCells = 0;
    $grandRows = 0;

    out('');
    out('Fix 2: decode HTML entities (&amp; → &) in string/text columns');
    out(str_repeat('-', 72));

    foreach ($tables as $table) {
        if (!table_exists($pdo, $table)) {
            out("Skip {$table}: table not found.");
            continue;
        }

        $columns = get_text_columns($pdo, $table);
        if ($columns === []) {
            out("Skip {$table}: no string/text columns.");
            continue;
        }

        $tableCells = 0;
        $tableRows = 0;

        foreach ($columns as $column) {
            $likeNeedle = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search);
            $countSql = "SELECT COUNT(*) FROM `{$table}` WHERE `{$column}` LIKE '%{$likeNeedle}%' ESCAPE '\\\\'";
            $count = (int) $pdo->query($countSql)->fetchColumn();

            if ($count === 0) {
                continue;
            }

            out("  {$table}.{$column}: {$count} row(s)");
            $tableCells++;
            $tableRows += $count;

            if ($apply) {
                $updateSql = "UPDATE `{$table}`
                              SET `{$column}` = REPLACE(`{$column}`, :search, :replace)
                              WHERE `{$column}` LIKE :like ESCAPE '\\\\'";
                $update = $pdo->prepare($updateSql);
                $update->bindValue(':search', $search, PDO::PARAM_STR);
                $update->bindValue(':replace', $replace, PDO::PARAM_STR);
                $update->bindValue(':like', '%' . $search . '%', PDO::PARAM_STR);
                $update->execute();
            }
        }

        if ($tableCells === 0) {
            out("{$table}: no matches");
        } else {
            out("{$table}: {$tableCells} column(s), {$tableRows} cell update(s)");
        }

        $grandCells += $tableCells;
        $grandRows += $tableRows;
    }

    return ['cells' => $grandCells, 'rows' => $grandRows];
}

/** @param list<string> $targetNames */
function run_process_history_fix(PDO $pdo, bool $apply, array $tables, array $targetNames): array
{
    $grandRows = 0;
    $grandLines = 0;

    out('');
    out('Fix 3: process_history MSD / Accounting → ACCOUNTING (target employees only)');
    out('Targets: ' . implode(', ', $targetNames));
    out(str_repeat('-', 72));

    foreach ($tables as $table) {
        if (!table_exists($pdo, $table)) {
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
            $tableLines += (int) $result['line_changes'];

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

    return ['rows' => $grandRows, 'lines' => $grandLines];
}

if (!$isCli) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<pre style="font-family:monospace">';
}

out('VTraMS database bug fixes');
out('Mode: ' . ($apply ? 'APPLY (updates will be saved)' : 'DRY-RUN (preview only; pass --apply or ?apply=1 to commit)'));
out(str_repeat('=', 72));
out('');
out('Fix 1: user_group login lockout schema');
out(str_repeat('-', 72));

try {
    if ($apply) {
        $pdo->beginTransaction();
    }

    run_schema_fix($pdo, $apply);
    $ampStats = run_amp_fix($pdo, $apply, $ampTables, $ampSearch, $ampReplace);
    $historyStats = run_process_history_fix($pdo, $apply, $processHistoryTables, $targetNames);

    if ($apply) {
        $pdo->commit();
        out(str_repeat('=', 72));
        out('Done.');
        out('  &amp; fix: ' . $ampStats['cells'] . ' column(s), ' . $ampStats['rows'] . ' cell update(s)');
        out('  process_history: ' . $historyStats['rows'] . ' row(s), ' . $historyStats['lines'] . ' line(s)');
    } else {
        out(str_repeat('=', 72));
        out('Preview complete.');
        out('  &amp; fix: would update ' . $ampStats['cells'] . ' column(s), ' . $ampStats['rows'] . ' cell(s)');
        out('  process_history: would update ' . $historyStats['rows'] . ' row(s), ' . $historyStats['lines'] . ' line(s)');
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
