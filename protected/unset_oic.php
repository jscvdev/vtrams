<?php

require 'core/components/security/err_blocker.inc.php';
require 'dbconnection.inc.php';
require 'core/components/security/config_session.inc.php';
require 'core/components/security/router.inc.php';

$unset_query = 'DELETE FROM oic WHERE date_end < NOW()';
$unset_query_statement = $pdo->prepare($unset_query);

if ($unset_query_statement->execute() && $unset_query_statement->rowCount() > 0)
{
    $unset_oic_query = "UPDATE designation_limit SET designated_udc = '', current_designated = 0 WHERE designation = 'Officer-In-Charge (PENR Office)'";
    $unset_oic_query_statement = $pdo->prepare($unset_oic_query);
    if ($unset_oic_query_statement->execute() && $unset_oic_query_statement->rowCount() > 0)
    {
        $select_cur_oic_query = "SELECT * FROM user_group ";
        $select_cur_oic_query_statement = $pdo->prepare($select_cur_oic_query);
        $select_cur_oic_query_statement->execute();

        while ($row2 = $select_cur_oic_query_statement->fetch(PDO::FETCH_ASSOC))
        {
            $x = $row2['designation'];
            $designations = explode(',', $x); // Split the string into an array
            $target = "Officer-In-Charge (PENR Office)";
            if (in_array($target, $designations))
            {
                $target_user = $row2['udc'];
                $target_designation = $row2['designation'];
                $target_designation_array = explode(',', $x);
                $targetIndex = array_search($target, $target_designation_array);
                // REPLACE
                if (end($target_designation_array) === $target)
                {
                    $updatedDesignation = str_replace($target, '', $target_designation);
                    $updatedDesignation = trim($updatedDesignation, ',');
                }
                $update_query = "UPDATE user_group SET designation = :designation WHERE udc = :udc";
                $update_query_statement  = $pdo->prepare($update_query);
                $update_query_statement->bindParam(":designation", $updatedDesignation);
                $update_query_statement->bindParam(":udc", $target_user);
                $update_query_statement->execute();
            }
        }
    }
}