<?php
if (ob_get_level() === 0) {
    ob_start();
}
require_once '../../dbconnection.inc.php';
/** @var PDO $pdo */
require_once '../../core/components/security/config_session.inc.php';
require_once '../../core/components/security/router.inc.php';
require_once '../../core/components/helpers/handler_transaction_helper.inc.php';
require_once '../action_module/voucher_action.model.inc.php';
require_once '../action_module/voucher_action.ctrl.inc.php';
require_once '../../core/components/helpers/amount_helper.inc.php';
require_once '../voucher_archiving_module/voucher_archiving.model.inc.php';
require_once '../../core/components/helpers/voucher_tracking_helper.inc.php';

try {
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    date_default_timezone_set('Asia/Singapore');
    $currTime = date('Y-m-d H:i:s', time());
    $datetime_action = $currTime;

    $action = 'Processed by: ' . $_SESSION['logged_user_emp_name'];
    $action_by = $_SESSION['logged_user_emp_name'];
    $action_from = trim((string) ($_SESSION['logged_user_section'] ?? ''));
    $remarks = 'Payment Processed';

    function normalize_process_history($value)
    {
        if ($value === null) {
            return '';
        }
        $value = (string) $value;
        $value = str_replace("\r\n", "\n", $value);
        $value = str_replace("\r", "\n", $value);
        $value = preg_replace('/\\\\n/', "\n", $value);

        return trim($value);
    }

    function is_sent_required_data_empty(array $variables_to_check)
    {
        $empty_variables = [];
        foreach ($variables_to_check as $var_name => $var_value) {
            if (empty($var_value)) {
                $empty_variables[$var_name] = $var_value;
            }
        }

        return [
            'is_empty' => !empty($empty_variables),
            'empty_variables' => $empty_variables,
        ];
    }

    $tableData = $data['data'] ?? [];
    $variables_to_check = [];

    if (!empty($tableData)) {
        $row = $tableData[0];
        $variables_to_check = [
            'certified_correct' => $row['certified_correct'],
            'approved_by' => $row['approved_by'],
            'agency_authorized_signatory' => $row['agency_authorized_signatory'],
            'ada_check_no' => $row['ada_check_no'] ?? ($row['check_no'] ?? ($row['ada_no'] ?? null)),
            'ada_check_date' => $row['ada_check_date'],
        ];
    }

    $result = is_sent_required_data_empty($variables_to_check);
    if ($result['is_empty']) {
        $empty_value_strings = [];
        foreach ($result['empty_variables'] as $var_name => $var_value) {
            $empty_value_strings[] = "$var_name: $var_value";
        }
        handler_flush_json_response([
            'ok' => false,
            'error' => 'Some data required is missing! Empty values: ' . implode(', ', $empty_value_strings),
            'notify_type' => 'error',
        ], 422);
    }

    handler_json_transaction_response(
        $pdo,
        function (PDO $pdo) use (
            $tableData,
            $action,
            $action_by,
            $action_from,
            $remarks,
            $currTime
        ) {
            $stmt = $pdo->prepare('INSERT INTO voucher_archives (
                processing_no, ors_no, ada_check_no, dv_no, payee, address, particulars, tin_employee_no, amount, voucher_type, certified_correct, approved_by, agency_authorized_signatory, voucher_date, ada_check_date,
                office_to, office_from, encoded_by, datetime_encoded, remarks, datetime_action, action, action_by, process_history
            ) VALUES (
                :processing_no, :ors_no, :ada_check_no, :dv_no, :payee, :address, :particulars, :tin_employee_no, :amount, :voucher_type, :certified_correct, :approved_by, :agency_authorized_signatory, :voucher_date, :ada_check_date,
                :office_to, :office_from, :encoded_by, :datetime_encoded, :remarks, :datetime_action, :action, :action_by, :process_history
            )');
            $delstmt = $pdo->prepare('DELETE FROM voucher_temp WHERE processing_no = :processing_no');
            $trackingStmt = $pdo->prepare(
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

            foreach ($tableData as $row) {
                if (isset($row['amount'])) {
                    $row['amount'] = normalize_amount_string((string) $row['amount']);
                }
                if (isset($row['final_amount'])) {
                    $row['final_amount'] = normalize_amount_string((string) $row['final_amount']);
                }

                $processingNo = trim((string) ($row['processing_no'] ?? ''));
                if ($processingNo === '') {
                    throw new RuntimeException('Missing processing_no in ADA save payload.');
                }

                $process_history = normalize_process_history($row['process_history'] ?? null);
                $ada_check_no = $row['ada_check_no'] ?? ($row['check_no'] ?? ($row['ada_no'] ?? null));
                $ada_check_date = trim((string) ($row['ada_check_date'] ?? ''));
                $office_from = trim((string) ($row['office_from'] ?? ''));

                $stmt->execute([
                    ':processing_no' => $processingNo,
                    ':ors_no' => $row['ors_no'] ?? null,
                    ':ada_check_no' => $ada_check_no,
                    ':dv_no' => $row['dv_no'] ?? null,
                    ':payee' => $row['payee'] ?? null,
                    ':address' => $row['address'] ?? null,
                    ':particulars' => $row['particulars'] ?? null,
                    ':tin_employee_no' => $row['tin_employee_no'] ?? null,
                    ':amount' => $row['final_amount'] ?? null,
                    ':voucher_type' => $row['voucher_type'] ?? null,
                    ':certified_correct' => $row['certified_correct'] ?? null,
                    ':approved_by' => $row['approved_by'] ?? null,
                    ':agency_authorized_signatory' => $row['agency_authorized_signatory'] ?? null,
                    ':voucher_date' => $row['voucher_date'] ?? null,
                    ':ada_check_date' => $ada_check_date !== '' ? $ada_check_date : null,
                    ':office_to' => $row['office_to'] ?? null,
                    ':office_from' => $row['office_from'] ?? null,
                    ':encoded_by' => $row['encoded_by'] ?? null,
                    ':datetime_encoded' => $row['datetime_encoded'] ?? null,
                    ':remarks' => $row['remarks'] ?? null,
                    ':datetime_action' => $currTime,
                    ':action' => $action,
                    ':action_by' => $action_by,
                    ':process_history' => $process_history,
                ]);

                $turnaround_time = voucher_tracking_calculate_total_processing_time(
                    $pdo,
                    $processingNo,
                    $currTime,
                    (string) ($row['voucher_type'] ?? ''),
                    (string) ($row['datetime_encoded'] ?? '')
                );
                $trackingStmt->execute([
                    ':ada_check_no' => trim((string) ($ada_check_no ?? '')),
                    ':ada_check_date' => $ada_check_date,
                    ':voucher_status' => $action,
                    ':status' => 'Paid',
                    ':datetime_status' => $currTime,
                    ':remarks' => $remarks,
                    ':total_processing_time' => $turnaround_time,
                    ':processing_no' => $processingNo,
                ]);
                if ($trackingStmt->rowCount() === 0) {
                    throw new RuntimeException("voucher_tracking update failed for processing_no={$processingNo}");
                }

                voucher_log_user_action(
                    $pdo,
                    $processingNo,
                    (string) ($row['ors_no'] ?? ''),
                    (string) ($ada_check_no ?? ''),
                    (string) ($row['dv_no'] ?? ''),
                    (string) ($row['payee'] ?? ''),
                    (string) ($row['address'] ?? ''),
                    (string) ($row['particulars'] ?? ''),
                    (string) ($row['tin_employee_no'] ?? ''),
                    normalize_amount_string((string) ($row['amount'] ?? '')),
                    (string) ($row['voucher_type'] ?? ''),
                    (string) ($row['voucher_date'] ?? ''),
                    $action,
                    $action_by,
                    $action_from,
                    $currTime,
                    $office_from,
                    (string) ($row['office_to'] ?? ''),
                    (string) ($row['encoded_by'] ?? ''),
                    (string) ($row['remarks'] ?? '')
                );

                $delstmt->execute([':processing_no' => $processingNo]);
                archiving_delete_from_voucher_receiving($pdo, $processingNo);

                $archiveCheck = $pdo->prepare('SELECT COUNT(*) FROM voucher_archives WHERE processing_no = :processing_no');
                $archiveCheck->bindValue(':processing_no', $processingNo, PDO::PARAM_STR);
                $archiveCheck->execute();
                if ((int) $archiveCheck->fetchColumn() === 0) {
                    throw new RuntimeException("Archive verification failed for processing_no={$processingNo}");
                }
            }

            return true;
        },
        'Data saved successfully!',
        'ADA save failed.'
    );
} catch (PDOException $e) {
    handler_flush_json_response([
        'ok' => false,
        'error' => $e->getMessage(),
        'notify_type' => 'error',
    ], 500);
}
