<?php

declare(strict_types=1);

// JSON API: list of sections/units a voucher has passed through (from process_history or action_logs).

header('Content-Type: application/json');

// Map shorthand codes to full labels used in the app.
$sectionMap = [
    'BUDGET'           => 'Budget Unit',
    'BUDGET UNIT'      => 'Budget Unit',
    'ACCOUNTING'       => 'Accounting Unit',
    'ACCOUNTING UNIT'  => 'Accounting Unit',
    'PLANNING'         => 'Planning Section',
    'PLANNING SECTION' => 'Planning Section',
    'CASHIERS'         => 'Cashiers Unit',
    'CASHIERS UNIT'    => 'Cashiers Unit',
    'PENRO OFFICE'     => 'Office of the PENRO',
    'PENRO'            => 'Office of the PENRO',
    'OFFICE OF THE PENRO' => 'Office of the PENRO',
    'ICU'              => 'ICU',
];

try {
    require_once '../requires_modules/voucher_required.php';

    if (!isset($_GET['processing_no']) || trim((string)$_GET['processing_no']) === '') {
        echo json_encode(['success' => false, 'error' => 'Missing processing_no']);
        exit;
    }

    $processing_no = trim((string)$_GET['processing_no']);
    $offices = [];

    // Try process_history from tables that may hold the voucher (incoming first for Incoming page)
    $histTables = ['voucher_incoming', 'voucher_receiving', 'voucher_sent', 'voucher_tracking', 'voucher_archives', 'vouchers'];
    $processHistory = null;

    foreach ($histTables as $table) {
        try {
            $sql = "SELECT process_history FROM `{$table}` WHERE processing_no = :pn LIMIT 1";
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':pn', $processing_no, PDO::PARAM_STR);
            $stmt->execute();
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($row && isset($row['process_history']) && trim((string)$row['process_history']) !== '') {
                $processHistory = $row['process_history'];
                break;
            }
        } catch (Throwable $e) {
            // Column may not exist; skip
            continue;
        }
    }

    if ($processHistory !== null) {
        $lines = preg_split('/\r\n|\r|\n/', (string)$processHistory);
        $seen = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '') continue;

            // Format (new): "FULL EMPLOYEE NAME | action | section/unit/division | office"
            // Format (legacy): "USER : action : section/unit" or "USER : section/unit"
            if (strpos($line, '|') !== false) {
                $parts = preg_split('/\s*\|\s*/', $line, 4);
                $section = isset($parts[2]) ? trim($parts[2]) : null;
            } else {
                $parts = preg_split('/\s*:\s*/', $line, 3);
                $section = isset($parts[2]) ? trim($parts[2]) : (isset($parts[1]) ? trim($parts[1]) : null);
            }
            if ($section === '' || $section === null) continue;

            $rawSection = strtoupper($section);
            $mapped = $sectionMap[$rawSection] ?? $section;
            if ($mapped !== '' && !isset($seen[$mapped])) {
                $seen[$mapped] = true;
                $offices[] = $mapped;
            }
        }
    }

    // Fallback: derive from action_from in voucher_action_logs
    if (empty($offices)) {
        $sql = "
            SELECT DISTINCT action_from AS section_unit
            FROM voucher_action_logs
            WHERE processing_no = :pn
              AND action_from IS NOT NULL
              AND action_from <> ''
            ORDER BY action_from
        ";
        $stmt = $pdo->prepare($sql);
        $stmt->bindParam(':pn', $processing_no, PDO::PARAM_STR);
        $stmt->execute();

        $seen = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $raw = trim((string)($row['section_unit'] ?? ''));
            if ($raw === '') continue;
            $mapped = $sectionMap[strtoupper($raw)] ?? $raw;
            if (!isset($seen[$mapped])) {
                $seen[$mapped] = true;
                $offices[] = $mapped;
            }
        }
    }

    echo json_encode([
        'success' => true,
        'offices' => $offices
    ]);
} catch (Throwable $e) {
    echo json_encode([
        'success' => false,
        'error' => 'Server error',
    ]);
}

