<?php

declare(strict_types=1);

require_once __DIR__ . '/../../dbconnection.inc.php';
require_once __DIR__ . '/../../core/components/security/config_session.inc.php';
require_once __DIR__ . '/../../core/components/security/router.inc.php';
require_once __DIR__ . '/../../core/components/helpers/handler_transaction_helper.inc.php';
require_once __DIR__ . '/../../core/components/helpers/voucher_tracking_helper.inc.php';
require_once __DIR__ . '/../../core/components/helpers/amount_helper.inc.php';
require_once __DIR__ . '/../../core/components/helpers/audit_helper.inc.php';
require_once __DIR__ . '/../action_module/voucher_action.model.inc.php';
require_once __DIR__ . '/../action_module/voucher_action.ctrl.inc.php';
require_once __DIR__ . '/voucher_archiving.model.inc.php';
require_once __DIR__ . '/voucher_archiving.ctrl.inc.php';

/** @var PDO $pdo */

if (!function_exists('generateToken')) {
    function generateToken(): string
    {
        return bin2hex(random_bytes(16));
    }
}

function voucher_bulk_pay_normalize_process_history(mixed $value): string
{
    if ($value === null) {
        return '';
    }
    $value = (string) $value;
    $value = str_replace("\r\n", "\n", $value);
    $value = str_replace("\r", "\n", $value);
    $value = preg_replace('/\\\\n/', "\n", $value) ?? $value;

    return trim($value);
}

function voucher_bulk_pay_invalid_ada_check_no(mixed $value): bool
{
    $v = trim((string) $value);

    return $v === '' || strcasecmp($v, 'TBD') === 0;
}

function voucher_bulk_pay_is_payable_transmit(mixed $transmit): bool
{
    $status = trim((string) $transmit);

    return $status === '' || $status === 'No' || $status === 'Yes';
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    handler_flush_json_response(['ok' => false, 'error' => 'Method not allowed'], 405);
}

$raw = file_get_contents('php://input');
$payload = json_decode($raw !== false ? $raw : '', true);
if (!is_array($payload)) {
    handler_flush_json_response(['ok' => false, 'error' => 'Invalid JSON body'], 400);
}

$token = trim((string) ($payload['token'] ?? ''));
if ($token === '' || !isset($_SESSION['token']) || !hash_equals((string) $_SESSION['token'], $token)) {
    handler_flush_json_response(['ok' => false, 'error' => 'Invalid token'], 403);
}

$designations = voucher_logged_user_designations();
$isCashier = voucher_user_has_designation($designations, 'Cashiers Unit')
    || voucher_user_has_designation($designations, 'Cashier');
if (!$isCashier) {
    handler_flush_json_response(['ok' => false, 'error' => 'Bulk pay is available to Cashiers only.'], 403);
}

$processingNos = $payload['processing_nos'] ?? [];
if (!is_array($processingNos)) {
    handler_flush_json_response(['ok' => false, 'error' => 'processing_nos must be an array'], 400);
}

$processingNos = array_values(array_unique(array_filter(array_map(
    static fn($pn): string => trim((string) $pn),
    $processingNos
))));

if ($processingNos === []) {
    handler_flush_json_response(['ok' => false, 'error' => 'No vouchers selected'], 400);
}

$certifiedCorrect = trim((string) ($payload['certified_correct'] ?? ''));
$approvedBy = trim((string) ($payload['approved_by'] ?? ''));
$agencySignatory = trim((string) ($payload['agency_authorized_signatory'] ?? ''));
$adaCheckDate = trim((string) ($payload['ada_check_date'] ?? ''));

$adaCheckNosRaw = $payload['ada_check_nos'] ?? [];
$adaCheckNosMap = [];
if (is_array($adaCheckNosRaw)) {
    foreach ($adaCheckNosRaw as $key => $value) {
        if (is_string($key) && trim($key) !== '') {
            $adaCheckNosMap[trim($key)] = trim((string) $value);
        }
    }
}

$sharedRequired = [
    'certified_correct' => $certifiedCorrect,
    'approved_by' => $approvedBy,
    'agency_authorized_signatory' => $agencySignatory,
    'ada_check_date' => $adaCheckDate,
];
$missingShared = [];
foreach ($sharedRequired as $field => $value) {
    if ($value === '') {
        $missingShared[] = $field;
    }
}
if ($missingShared !== []) {
    handler_flush_json_response([
        'ok' => false,
        'error' => 'Missing required signatory fields: ' . implode(', ', $missingShared),
    ], 422);
}

$loggedUserOffice = voucher_logged_user_office();
$senderUdc = trim((string) ($_SESSION['logged_user_udc'] ?? ''));
$actionFrom = trim((string) ($_SESSION['logged_user_section'] ?? ''));
$actionBy = trim((string) ($_SESSION['logged_user_emp_name'] ?? ''));
$udcParam = '%' . $senderUdc . '%';

if ($loggedUserOffice === '' || $senderUdc === '') {
    handler_flush_json_response(['ok' => false, 'error' => 'Not authenticated'], 401);
}

date_default_timezone_set('Asia/Singapore');
$currTime = date('Y-m-d H:i:s');
$action = 'Processed by: ' . $actionBy;
$remarks = 'Payment Processed';

$loadStmt = $pdo->prepare(
    'SELECT * FROM voucher_receiving
     WHERE processing_no = :processing_no
       AND receiver_udc LIKE :receiver_udc
       AND office_to = :office_to
     LIMIT 1'
);

$archiveInsert = $pdo->prepare('INSERT INTO voucher_archives (
    processing_no, ors_no, ada_check_no, dv_no, payee, address, particulars, tin_employee_no, amount, charged_amount, voucher_type, certified_correct, approved_by, agency_authorized_signatory, voucher_date, ada_check_date,
    office_to, office_from, encoded_by, datetime_encoded, remarks, datetime_action, action, action_by, process_history
) VALUES (
    :processing_no, :ors_no, :ada_check_no, :dv_no, :payee, :address, :particulars, :tin_employee_no, :amount, :charged_amount, :voucher_type, :certified_correct, :approved_by, :agency_authorized_signatory, :voucher_date, :ada_check_date,
    :office_to, :office_from, :encoded_by, :datetime_encoded, :remarks, :datetime_action, :action, :action_by, :process_history
)');
$tempDelete = $pdo->prepare('DELETE FROM voucher_temp WHERE processing_no = :processing_no');
$trackingUpdate = $pdo->prepare(
    'UPDATE voucher_tracking SET
        ada_check_no = :ada_check_no,
        ada_check_date = :ada_check_date,
        voucher_status = :voucher_status,
        status = :status,
        datetime_status = :datetime_status,
        remarks = :remarks,
        total_processing_time = :total_processing_time
     WHERE processing_no = :processing_no'
);

$paid = 0;
$failed = 0;
$errors = [];

pdo_configure($pdo);
if (function_exists('vouchers_bootstrap_schema')) {
    vouchers_bootstrap_schema($pdo);
}

$tx = db_transaction(
    $pdo,
    function (PDO $pdo) use (
        $processingNos,
        $loadStmt,
        $udcParam,
        $loggedUserOffice,
        $archiveInsert,
        $tempDelete,
        $trackingUpdate,
        $certifiedCorrect,
        $approvedBy,
        $agencySignatory,
        $adaCheckDate,
        $adaCheckNosMap,
        $action,
        $actionBy,
        $actionFrom,
        $remarks,
        $currTime,
        &$paid,
        &$failed,
        &$errors
    ) {
        foreach ($processingNos as $processingNo) {
            $loadStmt->bindValue(':processing_no', $processingNo, PDO::PARAM_STR);
            $loadStmt->bindValue(':receiver_udc', $udcParam, PDO::PARAM_STR);
            $loadStmt->bindValue(':office_to', $loggedUserOffice, PDO::PARAM_STR);
            $loadStmt->execute();
            $row = $loadStmt->fetch(PDO::FETCH_ASSOC);
            if (!is_array($row)) {
                $failed++;
                $errors[] = $processingNo . ': voucher not in your pay queue';
                continue;
            }

            if (!voucher_bulk_pay_is_payable_transmit($row['transmit'] ?? '')) {
                $failed++;
                $errors[] = $processingNo . ': voucher is not ready for payment';
                continue;
            }

            if (check_if_voucher_archived_exists($pdo, $processingNo)) {
                $failed++;
                $errors[] = $processingNo . ': voucher is already paid/archived';
                continue;
            }

            $adaCheckNo = trim((string) ($adaCheckNosMap[$processingNo] ?? ($row['ada_check_no'] ?? '')));
            if (voucher_bulk_pay_invalid_ada_check_no($adaCheckNo)) {
                $failed++;
                $errors[] = $processingNo . ': ADA/Check No. is required (cannot be empty or TBD)';
                continue;
            }

            $amounts = voucher_archive_amounts_for_insert($pdo, $processingNo, '');
            $grossAmount = $amounts['gross'];
            $chargedAmount = $amounts['charged'];
            $logAmount = amount_resolve_charged_or_amount($chargedAmount ?? '', $grossAmount);
            $processHistory = voucher_bulk_pay_normalize_process_history($row['process_history'] ?? '');
            $officeFrom = trim((string) ($row['office_from'] ?? ''));

            $archiveInsert->execute([
                ':processing_no' => $processingNo,
                ':ors_no' => $row['ors_no'] ?? null,
                ':ada_check_no' => $adaCheckNo,
                ':dv_no' => $row['dv_no'] ?? null,
                ':payee' => $row['payee'] ?? null,
                ':address' => $row['address'] ?? null,
                ':particulars' => $row['particulars'] ?? null,
                ':tin_employee_no' => $row['tin_employee_no'] ?? null,
                ':amount' => $grossAmount,
                ':charged_amount' => $chargedAmount,
                ':voucher_type' => $row['voucher_type'] ?? null,
                ':certified_correct' => $certifiedCorrect,
                ':approved_by' => $approvedBy,
                ':agency_authorized_signatory' => $agencySignatory,
                ':voucher_date' => $row['voucher_date'] ?? null,
                ':ada_check_date' => $adaCheckDate,
                ':office_to' => $row['office_to'] ?? null,
                ':office_from' => $officeFrom,
                ':encoded_by' => $row['encoded_by'] ?? null,
                ':datetime_encoded' => $row['datetime_encoded'] ?? null,
                ':remarks' => $row['remarks'] ?? null,
                ':datetime_action' => $currTime,
                ':action' => $action,
                ':action_by' => $actionBy,
                ':process_history' => $processHistory,
            ]);

            $turnaroundTime = voucher_tracking_calculate_total_processing_time(
                $pdo,
                $processingNo,
                $currTime,
                (string) ($row['voucher_type'] ?? ''),
                (string) ($row['datetime_encoded'] ?? '')
            );
            $trackingUpdate->execute([
                ':ada_check_no' => $adaCheckNo,
                ':ada_check_date' => $adaCheckDate,
                ':voucher_status' => $action,
                ':status' => 'Paid',
                ':datetime_status' => $currTime,
                ':remarks' => $remarks,
                ':total_processing_time' => $turnaroundTime,
                ':processing_no' => $processingNo,
            ]);
            if ($trackingUpdate->rowCount() === 0) {
                throw new RuntimeException("voucher_tracking update failed for processing_no={$processingNo}");
            }

            voucher_log_user_action(
                $pdo,
                $processingNo,
                (string) ($row['ors_no'] ?? ''),
                $adaCheckNo,
                (string) ($row['dv_no'] ?? ''),
                (string) ($row['payee'] ?? ''),
                (string) ($row['address'] ?? ''),
                (string) ($row['particulars'] ?? ''),
                (string) ($row['tin_employee_no'] ?? ''),
                $logAmount,
                (string) ($row['voucher_type'] ?? ''),
                (string) ($row['voucher_date'] ?? ''),
                $action,
                $actionBy,
                $actionFrom,
                $currTime,
                $officeFrom,
                (string) ($row['office_to'] ?? ''),
                (string) ($row['encoded_by'] ?? ''),
                (string) ($row['remarks'] ?? '')
            );

            $tempDelete->execute([':processing_no' => $processingNo]);
            archiving_delete_from_voucher_receiving($pdo, $processingNo);

            $archiveCheck = $pdo->prepare('SELECT COUNT(*) FROM voucher_archives WHERE processing_no = :processing_no');
            $archiveCheck->bindValue(':processing_no', $processingNo, PDO::PARAM_STR);
            $archiveCheck->execute();
            if ((int) $archiveCheck->fetchColumn() === 0) {
                throw new RuntimeException("Archive verification failed for processing_no={$processingNo}");
            }

            AuditHelper::logActivity(
                'forwarding',
                "Bulk paid voucher: {$processingNo}",
                [
                    'processing_no' => $processingNo,
                    'dv_no' => (string) ($row['dv_no'] ?? ''),
                    'payee' => (string) ($row['payee'] ?? ''),
                    'ada_check_no' => $adaCheckNo,
                    'bulk' => true,
                ],
                $actionBy,
                $processingNo
            );

            $paid++;
        }

        return true;
    }
);

if (!$tx['ok']) {
    handler_flush_json_response([
        'ok' => false,
        'error' => 'Bulk pay failed: ' . ($tx['error'] ? $tx['error']->getMessage() : 'transaction error'),
    ], 500);
}

$_SESSION['token'] = generateToken();

$message = $paid > 0
    ? ('Paid ' . $paid . ' voucher(s)' . ($failed > 0 ? (', ' . $failed . ' failed') : '') . '.')
    : 'No vouchers were paid.';

handler_flush_json_response([
    'ok' => $paid > 0,
    'paid' => $paid,
    'failed' => $failed,
    'errors' => $errors,
    'message' => $message,
    'token' => (string) ($_SESSION['token'] ?? ''),
]);
