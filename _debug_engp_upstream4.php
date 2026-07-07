<?php
require __DIR__ . '/protected/dbconnection.inc.php';
require __DIR__ . '/protected/core/components/helpers/voucher_tracking_helper.inc.php';

foreach (['voucher_receiving', 'voucher_tracking', 'vouchers'] as $table) {
    $stmt = $pdo->query("SELECT processing_no, voucher_type, process_history FROM {$table} WHERE voucher_type LIKE '%NGP%'");
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        if (!voucher_type_is_engp($row['voucher_type'])) {
            continue;
        }
        $hist = (string) $row['process_history'];
        if (!preg_match('/Received by.*(Accounting|ACCOUNTING|Processor)/i', $hist)) {
            continue;
        }
        $up = voucher_forwarding_upstream_routing_complete($pdo, $row['voucher_type'], $hist);
        $tsd = voucher_tracking_history_has_tsd_engp_receive(voucher_tracking_parse_process_history_lines($hist));
        if (!$up) {
            echo "[$table] {$row['processing_no']}\n$hist\n\n";
        }
    }
}
