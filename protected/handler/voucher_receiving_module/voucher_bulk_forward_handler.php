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
require_once __DIR__ . '/voucher_receiving.model.inc.php';
require_once __DIR__ . '/voucher_receiving.ctrl.inc.php';

/** @var PDO $pdo */

if (!function_exists('generateToken')) {
    function generateToken(): string
    {
        return bin2hex(random_bytes(16));
    }
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

if (!voucher_user_has_designation(voucher_logged_user_designations(), 'Liaison Officer')) {
    handler_flush_json_response(['ok' => false, 'error' => 'Bulk forward is available to Liaison Officers only.'], 403);
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

$loggedUserOffice = voucher_logged_user_office();
$senderUdc = trim((string) ($_SESSION['logged_user_udc'] ?? ''));
$actionFrom = trim((string) ($_SESSION['logged_user_section'] ?? ''));
$actionBy = trim((string) ($_SESSION['logged_user_emp_name'] ?? ''));
$udcParam = '%' . $senderUdc . '%';

if ($loggedUserOffice === '' || $senderUdc === '') {
    handler_flush_json_response(['ok' => false, 'error' => 'Not authenticated'], 401);
}

$liaisonResolved = voucher_forward_liaison_icu_receiver($pdo, $loggedUserOffice);
$receiverUdc = $liaisonResolved['receiver_udc'];
$officeTo = $liaisonResolved['office_to'] !== '' ? $liaisonResolved['office_to'] : $loggedUserOffice;
$documentTo = 'ICU';

if ($receiverUdc === '') {
    handler_flush_json_response([
        'ok' => false,
        'error' => $liaisonResolved['temp_errors']['unassigned_udc'] ?? 'No ICU receiver is assigned to accept bulk forwards.',
    ], 422);
}

if (voucher_udcs_excluding($receiverUdc, $senderUdc) === '') {
    handler_flush_json_response(['ok' => false, 'error' => 'Cannot forward vouchers to yourself.'], 422);
}

date_default_timezone_set('Asia/Singapore');
$datetimeAction = date('Y-m-d H:i:s');
$action = 'Forwarded by: ' . $actionBy;

$loadStmt = $pdo->prepare(
    'SELECT * FROM voucher_receiving
     WHERE processing_no = :processing_no
       AND receiver_udc LIKE :receiver_udc
       AND office_to = :office_to
     LIMIT 1'
);

$forwarded = 0;
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
        $senderUdc,
        $receiverUdc,
        $officeTo,
        $documentTo,
        $actionBy,
        $actionFrom,
        $datetimeAction,
        $action,
        &$forwarded,
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
                $errors[] = $processingNo . ': voucher not in your forwarding queue';
                continue;
            }

            $processing_no = $processingNo;
            $dv_no = trim((string) ($row['dv_no'] ?? ''));
            $ors_no = trim((string) ($row['ors_no'] ?? ''));
            $ada_check_no = trim((string) ($row['ada_check_no'] ?? ''));
            $payee = trim((string) ($row['payee'] ?? ''));
            $address = trim((string) ($row['address'] ?? ''));
            $particulars = trim((string) ($row['particulars'] ?? ''));
            $tin_employee_no = trim((string) ($row['tin_employee_no'] ?? ''));
            $amount = trim((string) ($row['amount'] ?? ''));
            voucher_apply_exact_amount($amount);
            $voucher_type = trim((string) ($row['voucher_type'] ?? ''));
            $voucher_date = trim((string) ($row['voucher_date'] ?? ''));
            $office_from = trim((string) ($row['office_from'] ?? $loggedUserOffice));
            $encoded_by = trim((string) ($row['encoded_by'] ?? ''));
            $encoded_from = trim((string) ($row['encoded_from'] ?? ''));
            $datetime_encoded = trim((string) ($row['datetime_encoded'] ?? ''));
            $forwarded_by = trim((string) ($row['forwarded_by'] ?? $actionBy));
            $process_status = trim((string) ($row['process_status'] ?? 'N/A'));
            $combined_remarks = trim((string) ($row['remarks'] ?? 'N/A'));
            if ($combined_remarks === '') {
                $combined_remarks = 'N/A';
            }
            $remarks = 'N/A';
            $coa_options = isset($row['coa_options']) ? trim((string) $row['coa_options']) : null;
            $coa_category = isset($row['coa_category']) ? trim((string) $row['coa_category']) : null;
            $coa_subsection = isset($row['coa_subsection']) ? trim((string) $row['coa_subsection']) : null;
            if ($coa_options === '') {
                $coa_options = null;
            }
            if ($coa_category === '') {
                $coa_category = null;
            }
            if ($coa_subsection === '') {
                $coa_subsection = null;
            }

            $required = [
                'processing_no' => $processing_no,
                'payee' => $payee,
                'particulars' => $particulars,
                'amount' => $amount,
                'voucher_type' => $voucher_type,
                'voucher_date' => $voucher_date,
                'office_from' => $office_from,
                'office_to' => $officeTo,
                'sender_udc' => $senderUdc,
                'receiver_udc' => $receiverUdc,
                'document_to' => $documentTo,
                'encoded_by' => $encoded_by,
                'forwarded_by' => $forwarded_by,
                'datetime_encoded' => $datetime_encoded,
            ];
            $requiredCheck = is_voucher_receiving_required_data_empty($required);
            if ($requiredCheck['is_empty']) {
                $failed++;
                $errors[] = $processingNo . ': missing required voucher data';
                continue;
            }

            voucher_receiving_move_to_incoming(
                $pdo,
                $processing_no,
                $dv_no,
                $ors_no,
                $ada_check_no,
                $payee,
                $address,
                $particulars,
                $tin_employee_no,
                $amount,
                $voucher_type,
                $voucher_date,
                $datetimeAction,
                $senderUdc,
                $receiverUdc,
                $office_from,
                $officeTo,
                $encoded_by,
                $encoded_from,
                $datetime_encoded,
                $forwarded_by,
                $process_status,
                $combined_remarks,
                $remarks,
                $coa_options,
                $coa_category,
                $coa_subsection
            );

            voucher_receiving_move_to_sent(
                $pdo,
                $processing_no,
                $dv_no,
                $ors_no,
                $ada_check_no,
                $payee,
                $address,
                $particulars,
                $tin_employee_no,
                $amount,
                $voucher_type,
                $voucher_date,
                $datetimeAction,
                $senderUdc,
                $receiverUdc,
                $office_from,
                $officeTo,
                $encoded_by,
                $encoded_from,
                $datetime_encoded,
                $forwarded_by,
                $process_status,
                $combined_remarks,
                $remarks,
                $coa_options,
                $coa_category,
                $coa_subsection
            );

            voucher_forward_receiving($pdo, $processing_no);
            update_forwarded_received_voucher(
                $pdo,
                $processing_no,
                $dv_no,
                $ors_no,
                $ada_check_no,
                $action,
                $datetimeAction,
                $combined_remarks
            );

            voucher_log_user_action(
                $pdo,
                $processing_no,
                $ors_no,
                $ada_check_no,
                $dv_no,
                $payee,
                $address,
                $particulars,
                $tin_employee_no,
                $amount,
                $voucher_type,
                $voucher_date,
                $action,
                $actionBy,
                $actionFrom,
                $datetimeAction,
                $office_from,
                $officeTo,
                $encoded_by,
                $remarks
            );

            AuditHelper::logActivity(
                'forwarding',
                "Bulk forwarded voucher: {$processingNo} to {$documentTo}",
                [
                    'processing_no' => $processingNo,
                    'dv_no' => $dv_no,
                    'payee' => $payee,
                    'office_from' => $office_from,
                    'office_to' => $officeTo,
                    'document_to' => $documentTo,
                    'bulk' => true,
                ],
                $actionBy,
                $processingNo
            );

            $forwarded++;
        }

        return true;
    }
);

if (!$tx['ok']) {
    handler_flush_json_response([
        'ok' => false,
        'error' => 'Bulk forward failed: ' . ($tx['error'] ? $tx['error']->getMessage() : 'transaction error'),
    ], 500);
}

$_SESSION['token'] = generateToken();

$message = $forwarded > 0
    ? ('Forwarded ' . $forwarded . ' voucher(s)' . ($failed > 0 ? (', ' . $failed . ' failed') : '') . '.')
    : 'No vouchers were forwarded.';

handler_flush_json_response([
    'ok' => $forwarded > 0,
    'forwarded' => $forwarded,
    'failed' => $failed,
    'errors' => $errors,
    'message' => $message,
    'token' => (string) ($_SESSION['token'] ?? ''),
]);
