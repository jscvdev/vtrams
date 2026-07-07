<?php
require __DIR__ . '/protected/dbconnection.inc.php';
require __DIR__ . '/protected/core/components/helpers/voucher_tracking_helper.inc.php';
require __DIR__ . '/protected/core/components/helpers/utilities_special_access_helper.inc.php';

echo "=== Special Access Rules ===\n";
foreach ($pdo->query('SELECT voucher_type, forward_designation, sort_order, is_active FROM voucher_special_access ORDER BY voucher_type, sort_order') as $r) {
    echo json_encode($r) . "\n";
}

echo "\n=== Recent e-NGP forwarding rows ===\n";
$stmt = $pdo->query("SELECT processing_no, voucher_type, process_history FROM voucher_receiving WHERE voucher_type LIKE '%NGP%' OR voucher_type LIKE '%ngp%' ORDER BY processing_no DESC LIMIT 5");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo 'PN: ' . $row['processing_no'] . ' type: ' . $row['voucher_type'] . "\n";
    $lines = explode("\n", (string) $row['process_history']);
    foreach (array_slice($lines, -8) as $l) {
        echo '  ' . $l . "\n";
    }
    $up = voucher_forwarding_upstream_routing_complete($pdo, $row['voucher_type'], $row['process_history']);
    $via = voucher_special_access_routes_via_tsd_engp($pdo, $row['voucher_type']);
    $acc = voucher_special_access_routes_to_accounting($pdo, $row['voucher_type']);
    $tsd = voucher_tracking_history_has_tsd_engp_receive(voucher_tracking_parse_process_history_lines($row['process_history']));
    echo '  routes_to_accounting=' . ($acc ? 'true' : 'false')
        . ' via_tsd=' . ($via ? 'true' : 'false')
        . ' tsd_receive=' . ($tsd ? 'true' : 'false')
        . ' upstream=' . ($up ? 'true' : 'false') . "\n\n";
}

echo "\n=== e-NGP at accounting (recent) ===\n";
$stmt = $pdo->query("SELECT processing_no, voucher_type, process_history FROM voucher_receiving WHERE (voucher_type LIKE '%NGP%' OR voucher_type LIKE '%ngp%') AND process_history LIKE '%Accounting%' ORDER BY processing_no DESC LIMIT 5");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
    echo 'PN: ' . $row['processing_no'] . ' type: ' . $row['voucher_type'] . "\n";
    $lines = explode("\n", (string) $row['process_history']);
    foreach (array_slice($lines, -10) as $l) {
        echo '  ' . $l . "\n";
    }
    $up = voucher_forwarding_upstream_routing_complete($pdo, $row['voucher_type'], $row['process_history']);
    $tsd = voucher_tracking_history_has_tsd_engp_receive(voucher_tracking_parse_process_history_lines($row['process_history']));
    echo '  tsd_receive=' . ($tsd ? 'true' : 'false') . ' upstream=' . ($up ? 'true' : 'false') . "\n\n";
}
