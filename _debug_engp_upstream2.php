<?php
require __DIR__ . '/protected/dbconnection.inc.php';
require __DIR__ . '/protected/core/components/helpers/voucher_tracking_helper.inc.php';

$stmt = $pdo->query("SELECT processing_no, voucher_type, process_history FROM voucher_receiving WHERE process_history LIKE '%NGP%' OR voucher_type LIKE '%NGP%'");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    if (!voucher_type_is_engp($row['voucher_type'])) {
        continue;
    }
    $hist = (string) $row['process_history'];
    if (!preg_match('/Accounting|ACCOUNTING|Processor/i', $hist)) {
        continue;
    }
    $up = voucher_forwarding_upstream_routing_complete($pdo, $row['voucher_type'], $hist);
    if (!$up) {
        echo "FAIL upstream: {$row['processing_no']} ({$row['voucher_type']})\n";
        echo $hist . "\n\n";
    }
}

$pn = 'PN-26-07-0181';
$stmt = $pdo->prepare('SELECT processing_no, voucher_type, process_history FROM voucher_receiving WHERE processing_no = ?');
$stmt->execute([$pn]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if ($row) {
    echo "=== $pn ===\n";
    echo $row['process_history'] . "\n";
    $up = voucher_forwarding_upstream_routing_complete($pdo, $row['voucher_type'], $row['process_history']);
    echo 'upstream=' . ($up ? 'true' : 'false') . "\n";
}
