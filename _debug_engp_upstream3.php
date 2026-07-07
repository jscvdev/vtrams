<?php
require __DIR__ . '/protected/dbconnection.inc.php';
require __DIR__ . '/protected/core/components/helpers/voucher_tracking_helper.inc.php';

$stmt = $pdo->query('SELECT processing_no, voucher_type, process_history FROM voucher_receiving');
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if (!voucher_type_is_engp($row['voucher_type'])) {
        continue;
    }
    $up = voucher_forwarding_upstream_routing_complete($pdo, $row['voucher_type'], $row['process_history']);
    $tsd = voucher_tracking_history_has_tsd_engp_receive(voucher_tracking_parse_process_history_lines($row['process_history']));
    $hasAcct = (bool) preg_match('/Received by.*Accounting|Received by.*ACCOUNTING|Received by.*Processor/i', $row['process_history']);
    if (!$up) {
        echo "NO upstream: {$row['processing_no']} tsd_recv=" . ($tsd?'Y':'N') . " at_acct_hist=" . ($hasAcct?'Y':'N') . "\n";
        echo substr($row['process_history'], -500) . "\n\n";
    }
}
