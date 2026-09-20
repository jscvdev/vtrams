<?php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../requires_modules/voucher_required.php';
    require_once 'voucher_return.model.inc.php';
    require_once 'voucher_return.ctrl.inc.php';
    require_once __DIR__ . '/../../core/components/helpers/voucher_tracking_helper.inc.php';
    require_once __DIR__ . '/../action_module/voucher_action.ctrl.inc.php';
    require_once __DIR__ . '/../../core/components/security/access_control.inc.php';

    if (isset($_POST['token']) && $_POST['token'] === $_SESSION['token']) {
        $processing_no = voucher_post_string($_POST['processing_no'] ?? '');
        $retract_source = voucher_post_string($_POST['retract_source'] ?? 'incoming');
        $remarks = voucher_post_string($_POST['remarks'] ?? '');

        if ($retract_source === 'forwarding') {
            $success_redirect = 'voucher_forwarding_return_redirect';
            $_SESSION['voucher_return_redirect_key'] = 'voucher_forwarding_return_err_redirect';
        } elseif ($retract_source === 'pending') {
            $success_redirect = 'voucher_redirect';
            $_SESSION['voucher_return_redirect_key'] = 'voucher_err_redirect';
        } else {
            $success_redirect = 'voucher_incoming_redirect';
            $_SESSION['voucher_return_redirect_key'] = 'voucher_incoming_return_err_redirect';
        }

        if (!isset($_REQUEST['retract_voucher'])) {
            echo "<script>process_functionAlert('Retract Error: Wrong module used!', '" . $success_redirect . "')</script>";
            $_SESSION['token'] = generateToken();
            die();
        }

        try {
            voucher_retract_ensure_requests_schema($pdo);
            $temp_dump = [];

            if ($processing_no === '') {
                $temp_dump['empty_data'] = 'Processing number is required.';
            }

            $locatedSource = voucher_retract_locate_source($pdo, $processing_no, $retract_source);
            if ($locatedSource !== 'tracking' && !voucher_retract_source_exists($pdo, $processing_no, $locatedSource)) {
                $temp_dump['voucher_missing'] = 'Voucher not found in the current queue.';
            }
            if ($locatedSource === 'tracking' && !voucher_retract_tracking_exists($pdo, $processing_no)) {
                $temp_dump['voucher_missing'] = 'Voucher not found in the current queue.';
            }

            $snapshot = voucher_retract_fetch_encode_snapshot($pdo, $processing_no, $locatedSource);
            if ($snapshot === null) {
                $temp_dump['snapshot_missing'] = 'Unable to load voucher data for retract.';
                $snapshot = [];
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
            $isSystemAdmin = AccessControl::hasRole('System Admin');
            $needsApproval = !$isSystemAdmin && voucher_retract_requires_admin_approval(
                $pdo,
                $processing_no,
                $retract_source,
                $action_from,
                (string) ($_SESSION['logged_user_designation'] ?? ''),
                $logged_user_office
            );

            if ($needsApproval) {
                $existing = voucher_retract_find_pending_request($pdo, $processing_no);
                if ($existing !== null) {
                    echo "<script>process_functionAlert('Retract not available: a request is already pending System Admin approval.', '" . $success_redirect . "')</script>";
                    $_SESSION['token'] = generateToken();
                    die();
                }

                $requestRemarks = trim($remarks);
                if ($requestRemarks !== '' && strcasecmp($requestRemarks, 'NULL') === 0) {
                    $requestRemarks = '';
                }

                handler_execute_writes(
                    $pdo,
                    static function (PDO $pdo) use (
                        $processing_no,
                        $retract_source,
                        $action_by,
                        $action_from,
                        $logged_user_office,
                        $requestRemarks,
                        $datetime_action
                    ): void {
                        voucher_retract_insert_request(
                            $pdo,
                            $processing_no,
                            $retract_source,
                            $action_by,
                            $action_from,
                            $logged_user_office,
                            $requestRemarks,
                            $datetime_action
                        );
                    },
                    'Retract request submitted for System Admin approval.',
                    'Retract request failed!',
                    $success_redirect,
                    static function () use ($processing_no, $action_by): void {
                        AuditHelper::logActivity('retract_request', "Requested retract for voucher: {$processing_no}", [
                            'processing_no' => $processing_no,
                            'action_by' => $action_by,
                        ], $action_by, $processing_no);
                    }
                );
                die();
            }

            handler_execute_writes(
                $pdo,
                static function (PDO $pdo) use (
                    $processing_no,
                    $locatedSource,
                    $action_by,
                    $action_from,
                    $logged_user_office,
                    $remarks,
                    $datetime_action
                ): void {
                    voucher_retract_apply(
                        $pdo,
                        $processing_no,
                        $locatedSource,
                        $action_by,
                        $action_from,
                        $logged_user_office,
                        $remarks,
                        $datetime_action
                    );
                },
                'Retract success!',
                'Retract failed!',
                $success_redirect,
                static function () use ($processing_no, $snapshot, $action_by): void {
                    AuditHelper::logActivity('retracting', "Retracted voucher: {$processing_no} retracted by {$action_by}", [
                        'processing_no' => $processing_no,
                        'encoded_by' => (string) ($snapshot['encoded_by'] ?? ''),
                        'encoded_from' => (string) ($snapshot['encoded_from'] ?? ''),
                        'action_by' => $action_by,
                    ], $action_by, $processing_no);
                }
            );
            die();
        } catch (Throwable $e) {
            $_SESSION['error_voucher_return'] = ['database' => $e->getMessage()];
            echo "<script>process_functionAlert('Retract failed!', '" . $success_redirect . "')</script>";
            $_SESSION['token'] = generateToken();
            die();
        }
    }

    $invalid_retract_source = voucher_post_string($_POST['retract_source'] ?? 'incoming');
    if ($invalid_retract_source === 'forwarding') {
        $invalid_redirect = 'voucher_forwarding_return_redirect';
    } elseif ($invalid_retract_source === 'pending') {
        $invalid_redirect = 'voucher_redirect';
    } else {
        $invalid_redirect = 'voucher_incoming_redirect';
    }
    echo "<script>process_functionAlert('Invalid token!', '" . $invalid_redirect . "')</script>";
    $_SESSION['token'] = generateToken();
    die();
}

require_once __DIR__ . '/../../../core/components/redirects/redirect_config.inc.php';
redirect_to('encode');
die();
