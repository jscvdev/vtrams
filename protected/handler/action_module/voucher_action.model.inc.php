<?php

declare(strict_types=1);

function voucher_document_user_action(
    object $pdo,
    string $processing_no,
    string $ors_no,
    string $ada_check_no,
    string $dv_no,
    string $payee,
    string $address,
    string $particulars,
    string $tin_employee_no,
    string $amount,
    string $voucher_type,
    string $voucher_date,
    string $action,
    string $action_by,
    string $action_from,
    string $datetime_action,
    string $office_from,
    string $office_to,
    string $encoded_by,
    string $remarks,
    string $coa_options = null,
    string $coa_category = null,
    string $coa_subsection = null
) {
    $query = "INSERT INTO voucher_action_logs (
                    processing_no,
                    ors_no,
                    ada_check_no,
                    dv_no,
                    payee,
                    address,
                    tin_employee_no,
                    particulars,
                    amount,
                    voucher_type,
                    voucher_date,
                    action,
                    action_by,
                    action_from,
                    datetime_action,
                    office_from,
                    office_to,
                    encoded_by,
                    remarks,
                    coa_options,
                    coa_category,
                    coa_subsection
              )
              VALUES (
                    :processing_no,
                    :ors_no,
                    :ada_check_no,
                    :dv_no,
                    :payee,
                    :address,
                    :tin_employee_no,
                    :particulars,
                    :amount,
                    :voucher_type,
                    :voucher_date,
                    :action,
                    :action_by,
                    :action_from,
                    :datetime_action,
                    :office_from,
                    :office_to,
                    :encoded_by,
                    :remarks,
                    :coa_options,
                    :coa_category,
                    :coa_subsection
              )";

    $statement = $pdo->prepare($query);

    $statement->bindParam(":processing_no", $processing_no);
    $statement->bindParam(":ors_no", $ors_no);
    $statement->bindParam(":ada_check_no", $ada_check_no);
    $statement->bindParam(":dv_no", $dv_no);
    $statement->bindParam(":payee", $payee);
    $statement->bindParam(":address", $address);
    $statement->bindParam(":tin_employee_no", $tin_employee_no);
    $statement->bindParam(":particulars", $particulars);
    $statement->bindParam(":amount", $amount);
    $statement->bindParam(":voucher_type", $voucher_type);
    $statement->bindParam(":voucher_date", $voucher_date);
    $statement->bindParam(":action", $action);
    $statement->bindParam(":action_by", $action_by);
    $statement->bindParam(":action_from", $action_from);
    $statement->bindParam(":datetime_action", $datetime_action);
    $statement->bindParam(":office_from", $office_from);
    $statement->bindParam(":office_to", $office_to);
    $statement->bindParam(":encoded_by", $encoded_by);
    $statement->bindParam(":remarks", $remarks);
    $statement->bindParam(":coa_options", $coa_options);
    $statement->bindParam(":coa_category", $coa_category);
    $statement->bindParam(":coa_subsection", $coa_subsection);

    $statement->execute();

    $inserted = $statement->rowCount() > 0;

    // When an action is logged, append
    // "FULL EMPLOYEE NAME | action | section/unit/division | office"
    // to process_history in all relevant voucher tables.
    if ($inserted) {
        $history_line = trim($action_by) . ' | ' . trim($action) . ' | ' . trim($action_from) . ' | ' . trim($office_from);

        // Protect against empty lines; still useful to record that something happened
        if ($history_line !== ' |  |  | ') {
            $tables = [
                'vouchers',
                'voucher_incoming',
                'voucher_receiving',
                'voucher_sent',
                'voucher_archives',
                'voucher_temp',
                'voucher_tracking',
                'voucher_action_logs'
            ];

            foreach ($tables as $table) {
                $sql = "
                    UPDATE {$table}
                    SET process_history = TRIM(BOTH '\n' FROM CONCAT(
                        COALESCE(process_history, ''),
                        CASE
                            WHEN process_history IS NULL OR process_history = '' THEN ''
                            ELSE '\n'
                        END,
                        :history_line
                    ))
                    WHERE processing_no = :processing_no
                ";

                $updateStmt = $pdo->prepare($sql);
                $updateStmt->bindParam(':history_line', $history_line);
                $updateStmt->bindParam(':processing_no', $processing_no);
                $updateStmt->execute();
            }
        }
    }

    return $inserted;
}