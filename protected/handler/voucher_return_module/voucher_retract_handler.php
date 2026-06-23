<?php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../requires_modules/voucher_required.php';
    require_once 'voucher_return.model.inc.php';
    require_once 'voucher_return.ctrl.inc.php';
    require_once __DIR__ . '/../../core/components/helpers/voucher_tracking_helper.inc.php';
    require_once __DIR__ . '/../action_module/voucher_action.ctrl.inc.php';

    if (isset($_POST['token']) && $_POST['token'] === $_SESSION['token']) {
        $processing_no = voucher_post_string($_POST['processing_no'] ?? '');
        $retract_source = voucher_post_string($_POST['retract_source'] ?? 'incoming');
        $remarks = voucher_post_string($_POST['remarks'] ?? '');

        $success_redirect = $retract_source === 'forwarding'
            ? 'voucher_forwarding_return_redirect'
            : 'voucher_incoming_redirect';
        $_SESSION['voucher_return_redirect_key'] = $retract_source === 'forwarding'
            ? 'voucher_forwarding_return_err_redirect'
            : 'voucher_incoming_return_err_redirect';

        if (!isset($_REQUEST['retract_voucher'])) {
            echo "<script>process_functionAlert('Retract Error: Wrong module used!', '" . $success_redirect . "')</script>";
            $_SESSION['token'] = generateToken();
            die();
        }

        try {
            $temp_dump = [];

            if ($processing_no === '') {
                $temp_dump['empty_data'] = 'Processing number is required.';
            }

            if (!voucher_retract_source_exists($pdo, $processing_no, $retract_source)) {
                $temp_dump['voucher_missing'] = 'Voucher not found in the current queue.';
            }

            $snapshot = voucher_retract_fetch_encode_snapshot($pdo, $processing_no, $retract_source);
            if ($snapshot === null) {
                $temp_dump['snapshot_missing'] = 'Unable to load voucher data for retract.';
            }

            $required = [
                'payee' => (string) ($snapshot['payee'] ?? ''),
                'particulars' => (string) ($snapshot['particulars'] ?? ''),
                'amount' => (string) ($snapshot['amount'] ?? ''),
                'voucher_type' => (string) ($snapshot['voucher_type'] ?? ''),
                'voucher_date' => (string) ($snapshot['voucher_date'] ?? ''),
                'encoded_by' => (string) ($snapshot['encoded_by'] ?? ''),
                'encoded_from' => (string) ($snapshot['encoded_from'] ?? ''),
                'datetime_encoded' => (string) ($snapshot['datetime_encoded'] ?? ''),
            ];
            $requiredCheck = voucher_incoming_return_required_data_empty($required);
            if ($requiredCheck['is_empty']) {
                $temp_dump['empty_data'] = 'Some encoded data required for retract is missing.';
            }

            if ($temp_dump) {
                $_SESSION['error_voucher_return'] = $temp_dump;
                echo "<script>process_functionAlert('Retract failed!', '" . $success_redirect . "')</script>";
                $_SESSION['token'] = generateToken();
                die();
            }

            date_default_timezone_set('Asia/Singapore');
            $datetime_action = date('Y-m-d H:i:s', time());
            $action_by = (string) ($_SESSION['logged_user_emp_name'] ?? '');
            $action_from = (string) ($_SESSION['logged_user_section'] ?? '');
            $logged_user_office = voucher_logged_user_office();

            $encoded_by = (string) ($snapshot['encoded_by'] ?? '');
            $encoded_action = 'Encoded By: ' . $encoded_by;
            $retract_log_action = 'Retracted by: ' . $action_by;

            $resetFields = [
                'processing_no' => $processing_no,
                'payee' => (string) ($snapshot['payee'] ?? ''),
                'address' => (string) ($snapshot['address'] ?? ''),
                'particulars' => (string) ($snapshot['particulars'] ?? ''),
                'tin_employee_no' => (string) ($snapshot['tin_employee_no'] ?? ''),
                'amount' => (string) ($snapshot['amount'] ?? ''),
                'voucher_type' => (string) ($snapshot['voucher_type'] ?? ''),
                'voucher_date' => (string) ($snapshot['voucher_date'] ?? ''),
                'encoded_by' => $encoded_by,
                'encoded_from' => (string) ($snapshot['encoded_from'] ?? ''),
                'datetime_encoded' => (string) ($snapshot['datetime_encoded'] ?? ''),
                'office_from' => (string) ($snapshot['office_from'] ?? ''),
            ];

            voucher_retract_clear_all_queues($pdo, $processing_no);
            voucher_retract_insert_pending($pdo, $resetFields);
            voucher_retract_clear_voucher_return_remarks($pdo, $processing_no);
            voucher_retract_reset_tracking(
                $pdo,
                $processing_no,
                $resetFields,
                $encoded_action,
                (string) ($snapshot['datetime_encoded'] ?? $datetime_action)
            );
            voucher_retract_reset_dv_entry($pdo, $processing_no, $resetFields);

            $log_remarks = trim($remarks);
            if ($log_remarks !== '' && strcasecmp($log_remarks, 'NULL') !== 0) {
                $log_remarks = $action_by . ': ' . $log_remarks;
            } else {
                $log_remarks = '';
            }

            voucher_log_user_action(
                $pdo,
                $processing_no,
                'TBD',
                'TBD',
                'TBD',
                $resetFields['payee'],
                $resetFields['address'],
                $resetFields['particulars'],
                $resetFields['tin_employee_no'],
                $resetFields['amount'],
                $resetFields['voucher_type'],
                $resetFields['voucher_date'],
                $retract_log_action,
                $action_by,
                $action_from,
                $datetime_action,
                $logged_user_office,
                '',
                $encoded_by,
                $log_remarks
            );

            AuditHelper::logActivity('retracting', "Retracted voucher: {$processing_no} retracted by {$action_by}", [
                'processing_no' => $processing_no,
                'encoded_by' => $encoded_by,
                'encoded_from' => $resetFields['encoded_from'],
                'action_by' => $action_by,
            ], $action_by, $processing_no);

            echo "<script>process_functionAlert('Retract success!', '" . $success_redirect . "')</script>";
            $_SESSION['token'] = generateToken();
            die();
        } catch (PDOException $e) {
            $_SESSION['error_voucher_return'] = ['database' => $e->getMessage()];
            echo "<script>process_functionAlert('Retract failed!', '" . $success_redirect . "')</script>";
            $_SESSION['token'] = generateToken();
            die();
        }
    }

    $invalid_redirect = isset($_POST['retract_source']) && $_POST['retract_source'] === 'forwarding'
        ? 'voucher_forwarding_return_redirect'
        : 'voucher_incoming_redirect';
    echo "<script>process_functionAlert('Invalid token!', '" . $invalid_redirect . "')</script>";
    $_SESSION['token'] = generateToken();
    die();
}

require_once __DIR__ . '/../../../core/components/redirects/redirect_config.inc.php';
redirect_to('encode');
die();
