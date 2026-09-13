<?php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../requires_modules/voucher_required.php';
    require_once 'voucher_return.model.inc.php';
    require_once 'voucher_return.ctrl.inc.php';
    require_once __DIR__ . '/../../core/components/helpers/voucher_tracking_helper.inc.php';
    require_once __DIR__ . '/../../core/components/security/access_control.inc.php';

    $success_redirect = 'voucher_retract_approvals_redirect';

    if (!AccessControl::hasRole('System Admin')) {
        echo "<script>process_functionAlert('Access denied. System Admin only.', 'voucher_redirect')</script>";
        $_SESSION['token'] = generateToken();
        die();
    }

    if (isset($_POST['token']) && $_POST['token'] === $_SESSION['token']) {
        $requestId = (int) ($_POST['request_id'] ?? 0);
        $decision = strtolower(voucher_post_string($_POST['retract_decision'] ?? ''));
        $reviewRemarks = voucher_post_string($_POST['review_remarks'] ?? '');

        try {
            voucher_retract_ensure_requests_schema($pdo);

            if ($requestId < 1 || !in_array($decision, ['approve', 'reject'], true)) {
                echo "<script>process_functionAlert('Invalid retract approval request.', '" . $success_redirect . "')</script>";
                $_SESSION['token'] = generateToken();
                die();
            }

            $request = voucher_retract_get_request($pdo, $requestId);
            if ($request === null || (string) ($request['status'] ?? '') !== 'pending') {
                echo "<script>process_functionAlert('Retract request is no longer pending.', '" . $success_redirect . "')</script>";
                $_SESSION['token'] = generateToken();
                die();
            }

            date_default_timezone_set('Asia/Singapore');
            $datetime_action = date('Y-m-d H:i:s', time());
            $reviewed_by = (string) ($_SESSION['logged_user_emp_name'] ?? '');
            $action_from = (string) ($_SESSION['logged_user_section'] ?? '');
            $logged_user_office = voucher_logged_user_office();
            $processing_no = (string) ($request['processing_no'] ?? '');
            $retract_source = (string) ($request['retract_source'] ?? 'incoming');
            $requested_by = (string) ($request['requested_by'] ?? '');
            $requestRemarks = trim((string) ($request['remarks'] ?? ''));
            $combinedRemarks = $requestRemarks;
            if ($reviewRemarks !== '') {
                $combinedRemarks = trim($combinedRemarks . ($combinedRemarks !== '' ? "\n" : '') . 'Approved by: ' . $reviewed_by . ' — ' . $reviewRemarks);
            } elseif ($decision === 'approve') {
                $combinedRemarks = trim($combinedRemarks . ($combinedRemarks !== '' ? "\n" : '') . 'Approved by: ' . $reviewed_by);
            }

            if ($decision === 'reject') {
                handler_execute_writes(
                    $pdo,
                    static function (PDO $pdo) use ($requestId, $reviewed_by, $datetime_action, $reviewRemarks): void {
                        voucher_retract_mark_request_reviewed(
                            $pdo,
                            $requestId,
                            'rejected',
                            $reviewed_by,
                            $datetime_action,
                            $reviewRemarks
                        );
                    },
                    'Retract request rejected.',
                    'Failed to reject retract request.',
                    $success_redirect,
                    static function () use ($processing_no, $reviewed_by, $requested_by): void {
                        AuditHelper::logActivity('retract_reject', "Rejected retract request for voucher: {$processing_no}", [
                            'processing_no' => $processing_no,
                            'requested_by' => $requested_by,
                            'reviewed_by' => $reviewed_by,
                        ], $reviewed_by, $processing_no);
                    }
                );
                die();
            }

            handler_execute_writes(
                $pdo,
                static function (PDO $pdo) use (
                    $requestId,
                    $reviewed_by,
                    $datetime_action,
                    $reviewRemarks,
                    $processing_no,
                    $retract_source,
                    $requested_by,
                    $action_from,
                    $logged_user_office,
                    $combinedRemarks
                ): void {
                    voucher_retract_mark_request_reviewed(
                        $pdo,
                        $requestId,
                        'approved',
                        $reviewed_by,
                        $datetime_action,
                        $reviewRemarks
                    );
                    voucher_retract_apply(
                        $pdo,
                        $processing_no,
                        $retract_source,
                        $requested_by !== '' ? $requested_by : $reviewed_by,
                        $action_from,
                        $logged_user_office,
                        $combinedRemarks,
                        $datetime_action
                    );
                },
                'Retract request approved.',
                'Failed to approve retract request.',
                $success_redirect,
                static function () use ($processing_no, $reviewed_by, $requested_by): void {
                    AuditHelper::logActivity('retracting', "Approved retract for voucher: {$processing_no}", [
                        'processing_no' => $processing_no,
                        'requested_by' => $requested_by,
                        'reviewed_by' => $reviewed_by,
                    ], $reviewed_by, $processing_no);
                }
            );
            die();
        } catch (Throwable $e) {
            echo "<script>process_functionAlert('Retract approval failed!', '" . $success_redirect . "')</script>";
            $_SESSION['token'] = generateToken();
            die();
        }
    }

    echo "<script>process_functionAlert('Invalid token!', '" . $success_redirect . "')</script>";
    $_SESSION['token'] = generateToken();
    die();
}

require_once __DIR__ . '/../../../core/components/redirects/redirect_config.inc.php';
redirect_to('voucher');
die();
