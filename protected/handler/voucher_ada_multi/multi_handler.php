<?php
require_once '../../dbconnection.inc.php';
/** @var PDO $pdo */
require_once '../../core/components/security/config_session.inc.php';
require_once '../../core/components/security/router.inc.php';
require_once '../action_module/voucher_action.model.inc.php';
require_once '../action_module/voucher_action.ctrl.inc.php';
require_once '../voucher_archiving_module/voucher_archiving.model.inc.php';
try {
    // Retrieve JSON data from the request body
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);

    date_default_timezone_set('Asia/Singapore'); // Set timezone to GMT+8
    $currTime = $date = date('Y-m-d H:i:s', time()); // Format the current time

    $datetime_action = $currTime;

    $action = "Processed by: " . $_SESSION['logged_user_emp_name'];
    $action_by  = $_SESSION['logged_user_emp_name'];

    $remarks = "Payment Processed";

    // Normalize process_history formatting so it stays line-delimited.
    // Some clients may send literal "\\n" sequences instead of real newlines.
    function normalize_process_history($value)
    {
        if ($value === null) return '';
        $value = (string)$value;

        // Windows newlines -> unix newlines
        $value = str_replace("\r\n", "\n", $value);
        $value = str_replace("\r", "\n", $value);

        // Convert literal backslash+n into real newlines
        $value = preg_replace('/\\\\n/', "\n", $value);

        return trim($value);
    }

    // Extract table data (which now includes form data in each row)
    $tableData = $data['data'] ?? [];

    // Prepare the SQL query
    $stmt = $pdo->prepare('INSERT INTO voucher_archives (
        processing_no, ors_no, ada_check_no, dv_no, payee, address, particulars, tin_employee_no, amount, voucher_type, certified_correct, approved_by, agency_authorized_signatory, voucher_date, ada_check_date,
        office_to, office_from, encoded_by, datetime_encoded, remarks, datetime_action, action, action_by, process_history
    ) VALUES (
        :processing_no, :ors_no, :ada_check_no, :dv_no, :payee, :address, :particulars, :tin_employee_no, :amount, :voucher_type, :certified_correct, :approved_by, :agency_authorized_signatory, :voucher_date, :ada_check_date,
        :office_to, :office_from, :encoded_by, :datetime_encoded, :remarks, :datetime_action, :action, :action_by, :process_history
    )');
    $delstmt = $pdo->prepare('DELETE FROM voucher_temp WHERE processing_no = :processing_no');
    $logstmt = $pdo->prepare('INSERT INTO voucher_action_logs (
        processing_no, ors_no, ada_check_no, dv_no, payee, address, particulars, amount, voucher_type, voucher_date,
        action, action_by, datetime_action, office_from, office_to, encoded_by, remarks
    ) 
        VALUES (
        :processing_no, :ors_no, :ada_check_no, :dv_no, :payee, :address, :particulars, :amount, :voucher_type, :voucher_date,
        :action, :action_by, :datetime_action, :office_from, :office_to, :encoded_by, :remarks)');
    $updatestmt = $pdo->prepare('UPDATE voucher_tracking SET ada_check_no = :ada_check_no, voucher_status = :voucher_status, datetime_status = :datetime_status, remarks = :remarks, status = :status, total_processing_time = :total_processing_time WHERE processing_no = :processing_no');


    function calculateTurnaroundTime_Archiving($startTimestamp, $endTimestamp)
    {
        // Convert datetime strings to Unix timestamps
        $startTime = strtotime($startTimestamp);
        $endTime = strtotime($endTimestamp);

        // Calculate the difference in seconds
        $durationSeconds = $endTime - $startTime;

        // Calculate days, hours, minutes, and seconds
        $days = floor($durationSeconds / (24 * 3600));
        $remainder = $durationSeconds % (24 * 3600);
        $hours = floor($remainder / 3600);
        $remainder = $remainder % 3600;
        $minutes = floor($remainder / 60);
        $seconds = $remainder % 60;

        // Prepare the output string
        $output = '';
        if ($days > 0) {
            $output .= "$days day" . ($days > 1 ? 's ' : ' ');
        }
        if ($hours > 0) {
            $output .= "$hours hour" . ($hours > 1 ? 's ' : ' ');
        }
        if ($minutes > 0) {
            $output .= "$minutes minute" . ($minutes > 1 ? 's ' : ' ');
        }
        if ($seconds > 0) {
            $output .= "$seconds second" . ($seconds > 1 ? 's ' : ' ');
        }

        return trim($output);
    }

    $variables_to_check = [];

    if (!empty($tableData)) {
        // Get the first row of the table data
        $row = $tableData[0];

        $variables_to_check = array(
            'certified_correct' => $row['certified_correct'],
            'approved_by' => $row['approved_by'],
            'agency_authorized_signatory' => $row['agency_authorized_signatory'],
            // Accept either legacy (check_no/ada_no) or new single field (ada_check_no)
            'ada_check_no' => $row['ada_check_no'] ?? ($row['check_no'] ?? ($row['ada_no'] ?? null)),
            'ada_check_date' => $row['ada_check_date'],
            // Add more mappings as needed
        );
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
            'empty_variables' => $empty_variables
        ];
    }

    //TAKES IN AN ASSOCIATIVE ARRAY (KEY=>VALUE PAIRS)
    $result = is_sent_required_data_empty($variables_to_check);

    //CHECK IF REQUIRED DATA EMPTY
    if ($result['is_empty']) {
        $temp_dump['empty_data'] = "Some data required is missing! Empty values: ";
        $empty_value_strings = [];

        foreach ($result['empty_variables'] as $var_name => $var_value) {
            $empty_value_strings[] = "$var_name: $var_value";
        }

        $temp_dump['empty_data'] .= implode(', ', $empty_value_strings);
    }


    if ($temp_dump) {
        $_SESSION['error_ada'] = $temp_dump;
        // echo "<script>process_functionAlert('failed!', 'voucher_ada_redirect')</script>";
        // die();
        echo 'Failed!';
    } else {
        // Process each row
        foreach ($tableData as $row) {
            $process_history = normalize_process_history($row['process_history'] ?? null);
            $ada_check_no = $row['ada_check_no'] ?? ($row['check_no'] ?? ($row['ada_no'] ?? null));
            // Bind parameters and execute the statement
            $stmt->execute([
                ':processing_no' => $row['processing_no'] ?? null,
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
                ':ada_check_date' => $row['ada_check_date'] ?? null,
                ':office_to' => $row['office_to'] ?? null,
                ':office_from' => $row['office_from'] ?? null,
                ':encoded_by' => $row['encoded_by'] ?? null,
                ':datetime_encoded' => $row['datetime_encoded'] ?? null,
                ':remarks' => $row['remarks'] ?? null,
                ':datetime_action' => $currTime ?? null,
                ':action' => $action ?? null,
                ':action_by' => $action_by ?? null,
                ':process_history' => $process_history,
            ]);
            $logstmt->execute([
                ':processing_no' => $row['processing_no'] ?? null,
                ':ors_no' => $row['ors_no'] ?? null,
                ':ada_check_no' => $ada_check_no,
                ':dv_no' => $row['dv_no'] ?? null,
                ':payee' => $row['payee'] ?? null,
                ':address' => $row['address'] ?? null,
                ':particulars' => $row['particulars'] ?? null,
                ':amount' => $row['amount'] ?? null,
                ':voucher_type' => $row['voucher_type'] ?? null,
                ':voucher_date' => $row['voucher_date'] ?? null,
                ':action' => $action ?? null,
                ':action_by' => $action_by ?? null,
                ':datetime_action' => $currTime ?? null,
                ':office_to' => $row['office_to'] ?? null,
                ':office_from' => $row['office_from'] ?? null,
                ':encoded_by' => $row['encoded_by'] ?? null,
                ':remarks' => $row['remarks'] ?? null,
            ]);
            $turnaround_time = calculateTurnaroundTime_Archiving($row['datetime_encoded'], $currTime);
            $updatestmt->execute([
                ':ada_check_no' => $ada_check_no,
                ':voucher_status' => $action ?? null,
                ':status' => $remarks ?? null,
                ':remarks' => $remarks ?? null,
                ':datetime_status' => $currTime ?? null,
                ':total_processing_time' => $turnaround_time ?? null,
                ':processing_no' => $row['processing_no'] ?? null,
            ]);
            $delstmt->execute([
                ':processing_no' => $row['processing_no'] ?? null,
            ]);
            // Remove from voucher_receiving (same as Archive / ADA completion) so Forwarding list updates.
            $pn = $row['processing_no'] ?? '';
            if ($pn !== '') {
                archiving_delete_from_voucher_receiving($pdo, (string)$pn);
            }
        }
        echo 'Data saved successfully!';
    }
} catch (PDOException $e) {
    echo 'Error: ' . $e->getMessage();
}
