<?php

require_once __DIR__ . '/../designation_limit_helpers.inc.php';

function check_cur_maximum (object $pdo, $formattedDesignation, string $udc){

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
        }
        else {
            if ($row['current_designated'] == $row['max_designated']) {
                $check = true;
                $_SESSION['maxed_designation'] = $designation;
            }
        }
    }

    return $check;
}

function add_new__account (object $pdo, string $emp_id, string $emp_fn, string $emp_mi, string $emp_ln, string $hashedPwd, string $section, string $division, string $formattedPosition, string $access_level, string $udc, string $office, string $emp_tag){

    //INSERT QUERY
    $query = "INSERT INTO user_group (emp_id, emp_fn, emp_mi, emp_ln, password, section, division, designation, access_level, udc, office, emp_tag) VALUES (:emp_id, :emp_fn, :emp_mi, :emp_ln, :password, :section, :division, :designation, :access_level, :udc, :office, :emp_tag)";
    $statement = $pdo->prepare($query);
    $statement->bindParam(":emp_id",$emp_id);
    $statement->bindParam(":emp_fn",$emp_fn);
    $statement->bindParam(":emp_mi",$emp_mi);
    $statement->bindParam(":emp_ln",$emp_ln);
    $statement->bindParam(":password",$hashedPwd);
    $statement->bindParam(":section",$section);
    $statement->bindParam(":division",$division);
    $statement->bindParam(":designation",$formattedPosition);
    $statement->bindParam(":access_level",$access_level);
    $statement->bindParam(":udc",$udc);
    $statement->bindParam(":office",$office);
    $statement->bindParam(":emp_tag",$emp_tag);
    $statement->execute();
}

function update_designations (object $pdo, string $udc, string $formattedDesignation, string $fullName, string $office){

    $office = trim($office);
    if ($office === '') {
        $office = 'None';
    }

    $x = $formattedDesignation;
    $designations = explode(',', $x); // Split the string into an array

    foreach ($designations as $designation) {
        $query = "SELECT designated_udc, designated_office, current_designated, max_designated FROM designation_limit WHERE designation = :formattedPosition";

        $statement = $pdo->prepare($query);
        $statement->bindParam(":formattedPosition", $designation);
        $statement->execute();
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        $query2 = "SELECT * FROM designation_limit";
        $statement2 = $pdo->prepare($query2);
        $statement2->execute();

        while ($row2 = $statement2->fetch(PDO::FETCH_ASSOC)) {
            if ($row2['designated_udc'] == $udc and in_array($row2['designation'], $designations) ) {
            }
            else if ($row2['designated_udc'] == $udc and !in_array($row2['designation'], $designations))
            {
                require_once '../../../includes/dbconnection.inc.php';
                $desig_udc = $row2['designated_udc'];
                // Remove $udc from $desig_udc if it exists in the string
                $updatedUDC = str_replace($udc, '', $desig_udc);
                $query3 = "UPDATE designation_limit SET designated_udc = :udc, current_designated = current_designated - 1 WHERE designation != :designation and designated_udc = :designated_udc";
                $statement3  = $pdo->prepare($query3);
                $statement3->bindParam(":udc", $updatedUDC);
                $statement3->bindParam(":designated_udc", $udc);
                $statement3->bindParam(":designation", $designation);
                $statement3->execute();

                unset_curr_oic($pdo, $fullName);

            }
            else if (str_contains($row2['designated_udc'], $udc) and !in_array($row2['designation'], $designations))
            {
                require_once '../../../includes/dbconnection.inc.php';
                $desig_udc = $row2['designated_udc'];
                // Remove $udc from $desig_udc if it exists in the string
                $updatedUDC = str_replace(',' . $udc, '', $desig_udc);
                $query3 = "UPDATE designation_limit SET designated_udc = :udc, current_designated = current_designated - 1 WHERE designated_udc = :designated_udc";
                $statement3  = $pdo->prepare($query3);
                $statement3->bindParam(":udc", $updatedUDC);
                $statement3->bindParam(":designated_udc", $row2['designated_udc']);
                $statement3->execute();
            }
        }

        $addDC = $row['current_designated'];


        if (str_contains((string) $row['designated_udc'], $udc))
        {
            // DO NOTHING
        }
        else
        {
            $newDC =  $addDC + 1;
            $existingUDCs = array_values(array_filter(array_map('trim', explode(',', (string) $row['designated_udc']))));
            $existingOffices = normalize_designated_offices(array_map('trim', explode(',', (string) ($row['designated_office'] ?? ''))));
            [$existingUDCs, $existingOffices] = append_designated_udc_office($existingUDCs, $existingOffices, $udc, $office);

            $newUDCList = implode(',', $existingUDCs);
            $newOfficeList = implode(',', $existingOffices);

            $query = "UPDATE designation_limit SET designated_udc = :udc, designated_office = :office, current_designated = :current_designated WHERE designation = :designation";
            $statement  = $pdo->prepare($query);
            $statement->bindParam(":udc", $newUDCList);
            $statement->bindParam(":office", $newOfficeList);
            $statement->bindParam(":current_designated", $newDC);
            $statement->bindParam(":designation", $designation);
            $statement->execute();
        }
    }
}
