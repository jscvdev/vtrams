<?php

function check_cur_maximum(object $pdo, $formattedDesignation, string $udc)
{

    $check = false;

    $x = $formattedDesignation;
    $designations = explode(',', $x); // Split the string into an array

    foreach ($designations as $designation) {
        $query = "SELECT current_designated, max_designated, designated_udc FROM designation_limit WHERE designation = :formattedPosition";

        $statement = $pdo->prepare($query);
        $statement->bindParam(":formattedPosition", $designation);
        $statement->execute();
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if ($row['designated_udc'] == $udc) {
            $check = false;
        } else {
            if ($row['current_designated'] == $row['max_designated']) {
                $check = true;
                $_SESSION['maxed_designation'] = $designation;
            }
        }
    }

    return $check;
}

function update_user_account(object $pdo, string $emp_id, string $emp_fn, string $emp_mi, string $emp_ln, string $hashedPwd, string $section, string $division, string $formattedPosition, string $access_level, string $emp_tag)
{

    //INSERT QUERY
    $query = "UPDATE user_group SET emp_fn=:emp_fn, emp_mi=:emp_mi, emp_ln=:emp_ln, password = :password, section=:section, division=:division, designation=:designation, access_level=:access_level, emp_tag=:emp_tag WHERE emp_id=:emp_id";
    $statement = $pdo->prepare($query);
    $statement->bindParam(":emp_id", $emp_id);
    $statement->bindParam(":emp_fn", $emp_fn);
    $statement->bindParam(":emp_mi", $emp_mi);
    $statement->bindParam(":emp_ln", $emp_ln);
    $statement->bindParam(":password", $hashedPwd);
    $statement->bindParam(":section", $section);
    $statement->bindParam(":division", $division);
    $statement->bindParam(":designation", $formattedPosition);
    $statement->bindParam(":access_level", $access_level);
    $statement->bindParam(":emp_tag", $emp_tag);
    $statement->execute();
}

// Place this at the top of your PHP file, outside any other function or class
if (!function_exists('remove_udc_and_office')) {
    /**
     * Remove a UDC and its corresponding office by index from parallel comma-separated lists.
     */
    function remove_udc_and_office(string $udcList, string $officeList, string $udcToRemove): array
    {
        $udcs = array_map('trim', explode(',', $udcList));
        $offices = array_map('trim', explode(',', $officeList));
        $newUdcs = [];
        $newOffices = [];

        foreach ($udcs as $index => $u) {
            if ($u !== $udcToRemove) {
                $newUdcs[] = $u;
                // Replace empty office with "None"
                $newOffices[] = ($offices[$index] ?? '') === '' ? 'None' : $offices[$index];
            }
        }

        return [implode(',', $newUdcs), implode(',', $newOffices)];
    }
}

/**
 * Update designations for a given UDC.
 */
function update_designations(object $pdo, string $udc, string $formattedDesignation, string $fullName, string $office)
{
    $designations = array_map('trim', explode(',', $formattedDesignation));

    // Normalize office input: if empty, set to 'None'
    $office = trim($office);
    if ($office === '') {
        $office = 'None';
    }

    // Fetch all designation limits once
    $query = "SELECT * FROM designation_limit";
    $statement = $pdo->prepare($query);
    $statement->execute();
    $designationLimits = $statement->fetchAll(PDO::FETCH_ASSOC);

    // Remove UDC from designations where it no longer applies
    foreach ($designationLimits as $row2) {
        $designation = $row2['designation'];
        $currentUDCs = trim($row2['designated_udc']);
        $currentOffices = trim($row2['designated_office']);

        if ($currentUDCs === $udc && !in_array($designation, $designations)) {
            // UDC is the only one and it's being removed
            $updatedUDC = '';
            $updatedOffice = '';

            $query3 = "UPDATE designation_limit 
                       SET designated_udc = :udc, 
                           designated_office = :office, 
                           current_designated = current_designated - 1 
                       WHERE designation = :designation";
            $statement3 = $pdo->prepare($query3);
            $statement3->bindParam(":udc", $updatedUDC);
            $statement3->bindParam(":office", $updatedOffice);
            $statement3->bindParam(":designation", $designation);
            $statement3->execute();

            unset_curr_oic($pdo, $fullName);
        } elseif (str_contains($currentUDCs, $udc) && !in_array($designation, $designations)) {
            // Remove from multiple
            list($updatedUDC, $updatedOffice) = remove_udc_and_office($currentUDCs, $currentOffices, $udc);

            $query3 = "UPDATE designation_limit 
                       SET designated_udc = :udc, 
                           designated_office = :office, 
                           current_designated = current_designated - 1 
                       WHERE designation = :designation";
            $statement3 = $pdo->prepare($query3);
            $statement3->bindParam(":udc", $updatedUDC);
            $statement3->bindParam(":office", $updatedOffice);
            $statement3->bindParam(":designation", $designation);
            $statement3->execute();
        }
    }

    // Add or update designations for this UDC
    foreach ($designations as $designation) {
        $query = "SELECT * FROM designation_limit WHERE designation = :designation";
        $statement = $pdo->prepare($query);
        $statement->bindParam(":designation", $designation);
        $statement->execute();
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if (!$row) continue;

        $addDC = (int)$row['current_designated'];
        $existingUDCs = array_filter(array_map('trim', explode(',', $row['designated_udc'])));
        $existingOffices = array_map('trim', explode(',', $row['designated_office']));

        // Normalize existing offices, replace empty with 'None'
        foreach ($existingOffices as &$existingOffice) {
            if ($existingOffice === '') {
                $existingOffice = 'None';
            }
        }
        unset($existingOffice);

        if (!in_array($udc, $existingUDCs)) {
            $existingUDCs[] = $udc;
            $existingOffices[] = $office;

            $newUDCList = implode(',', $existingUDCs);
            $newOfficeList = implode(',', $existingOffices);
            $newDC = $addDC + 1;

            $query = "UPDATE designation_limit 
                      SET designated_udc = :udc, 
                          designated_office = :office, 
                          current_designated = :current_designated 
                      WHERE designation = :designation";
            $statement = $pdo->prepare($query);
            $statement->bindParam(":udc", $newUDCList);
            $statement->bindParam(":office", $newOfficeList);
            $statement->bindParam(":current_designated", $newDC);
            $statement->bindParam(":designation", $designation);
            $statement->execute();
        }
    }
}



function set_curr_oic(object $pdo, string $so_no, string $datetime_start, string $datetime_end, string $fullName)
{
    $set_oic_query = "INSERT INTO oic(so_no,date_start,date_end,oic) VALUES (:so_no,:date_start,:date_end,:oic)";
    $set_oic_pdo = $pdo->prepare($set_oic_query);
    $set_oic_pdo->bindParam(':so_no', $so_no);
    $set_oic_pdo->bindParam(':date_start', $datetime_start);
    $set_oic_pdo->bindParam(':date_end', $datetime_end);
    $set_oic_pdo->bindParam(':oic', $fullName);
    $set_oic_pdo->execute();
}

function unset_curr_oic(object $pdo, string $fullName)
{
    $unset_query = "DELETE FROM oic WHERE oic  = :fullName";
    $unset_pdo = $pdo->prepare($unset_query);
    $unset_pdo->bindParam(':fullName', $fullName);
    $unset_pdo->execute();
}
