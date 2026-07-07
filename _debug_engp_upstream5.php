<?php
require __DIR__ . '/protected/dbconnection.inc.php';
require __DIR__ . '/protected/core/components/helpers/voucher_tracking_helper.inc.php';

function has_tsd_forward(array $lines): bool {
    foreach ($lines as $line) {
        if (stripos($line['action'], 'Forwarded by') === false) continue;
        $raw = strtoupper(trim($line['section']));
        if (in_array($raw, ['TSD', 'ENGP FOCAL PERSON', 'TSD-ENGP'], true)) return true;
        if (voucher_tracking_normalize_section_label($line['section']) === 'TSD-ENGP') return true;
    }
    return false;
}

$stmt = $pdo->query('SELECT processing_no, voucher_type, process_history FROM voucher_receiving');
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if (!voucher_type_is_engp($row['voucher_type'])) continue;
    $lines = voucher_tracking_parse_process_history_lines($row['process_history']);
    $recv = voucher_tracking_history_has_tsd_engp_receive($lines);
    $fwd = has_tsd_forward($lines);
    $acct = (bool) preg_match('/Received by.*(Accounting|ACCOUNTING|Processor)/i', $row['process_history']);
    if ($acct && !$recv && !$fwd) {
        echo $row['processing_no'] . " acct without tsd step\n";
        echo $row['process_history'] . "\n\n";
    }
}
