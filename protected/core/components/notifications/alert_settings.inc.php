<?php
/**
 * Resolves configurable system name for notification modals.
 */
if (!isset($alert_system_name)) {
    $alert_system_name = 'PENRO Disbursement Voucher System';
    $alertPdo = $pdo ?? null;
    if (!$alertPdo) {
        $dbFile = __DIR__ . '/../../../dbconnection.inc.php';
        if (is_readable($dbFile)) {
            require_once $dbFile;
            $alertPdo = $pdo ?? null;
        }
    }
    if ($alertPdo) {
        require_once __DIR__ . '/../../../page_title_helper.inc.php';
        $alertPageTitleHelper = new PageTitleHelper($alertPdo);
        $alert_system_name = $alertPageTitleHelper->getSystemName();
    }
}
