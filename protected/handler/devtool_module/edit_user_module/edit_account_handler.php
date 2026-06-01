<?php

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../../../dbconnection.inc.php';
    /** @var PDO $pdo */
    require_once '../../../core/components/security/config_session.inc.php';
    require_once '../../../core/components/security/router.inc.php';
    require_once '../../action_module/action.model.inc.php';
    require_once '../../action_module/action.ctrl.inc.php';
    require '../../../core/components/notifications/custom_process_alert.php';
    require 'edit_account_model.inc.php';
    require 'edit_account_ctrl.inc.php';

    $keyList = array(
        "emp_id",
        "emp_fn",
        "emp_mi",
        "emp_ln",
        "section",
        "division",
        "designation",
        "password",
        "acl",
        "udc",
        "office",
        "tag",
    );

    $variable_map = array(
        'emp_id' => 'emp_id',
        'emp_fn' => 'emp_fn',
        'emp_mi' => 'emp_mi',
        'emp_ln' => 'emp_ln',
        'section' => 'section',
        'division' => 'division',
        'designation' => 'designation',
        'password' => 'password',
        'acl' => 'access_level',
        'udc' => 'udc',
        'office' => 'office',
        'tag' => 'emp_tag',
        // Add more mappings as needed
    );

    //LOOP METHOD
    foreach ($keyList as $key) {
        $variable_name = $variable_map[$key];
        if (isset($_POST[$key])) {
            if (is_array($_POST[$key])) {
                $$variable_name = $_POST[$key];
            } else {
                $$variable_name = $_POST[$key];
            }
        } else {
            $$variable_name = "";
        }
    }

    $so_no = "";
    $datetime_start = "";
    $datetime_end = "";
    $fullName = $emp_fn . " " . $emp_mi . " " . $emp_ln;

    //CONDITIONAL DATA
    if (isset($_POST['so_no'])) {
        $so_no = htmlspecialchars($_POST['so_no']);
    }
    if (isset($_POST['datetime_start'])) {
        $datetime_start = htmlspecialchars($_POST['datetime_start']);
    }
    if (isset($_POST['datetime_end'])) {
        $datetime_end = htmlspecialchars($_POST['datetime_end']);
    }

    // `designation` is a multi-select (`designation[]`) but keep this defensive
    // in case it arrives as a string from other clients.
    if (is_array($designation)) {
        $formattedDesignation = implode(',', $designation);
    } elseif (is_string($designation) && trim($designation) !== '') {
        $formattedDesignation = $designation;
    } else {
        $formattedDesignation = '';
    }

    $designation = $formattedDesignation === '' ? [] : explode(',', $formattedDesignation);

    try {
        $temp_dump = [];

        // MULTIPLE FILE UPLOAD SUPPORTED 27/04/2024
        try {
            if (isset($_REQUEST['edit_account'])) {

                function isHashed($string)
                {
                    // Check if the string matches the length of a common hash
                    if (strlen($string) == 32 && preg_match('/^[a-f0-9]{32}$/', $string)) {
                        return true;
                    } elseif (strlen($string) == 40 && preg_match('/^[a-f0-9]{40}$/', $string)) {
                        return true;
                    } elseif (strlen($string) == 64 && preg_match('/^[a-f0-9]{64}$/', $string)) {
                        return true;
                    } elseif (strlen($string) == 60 && preg_match('/^\$2[aby]\$.{56}$/', $string)) {
                        return true;
                    } else {
                        return false;
                    }
                }

                // Check if the password is already hashed
                if (!isHashed($password)) {
                    $options = [
                        'cost' => 12
                    ];
                    $hashedPwd = password_hash($password, PASSWORD_BCRYPT, $options);
                } else {
                    // Password is already hashed, so keep it as it is
                    $hashedPwd = $password;
                }

                $variables_to_check = [
                    '$emp_id' => $emp_id,
                    '$emp_fn' => $emp_fn,
                    '$emp_mi' => $emp_mi,
                    '$emp_ln' => $emp_ln,
                    '$section' => $section,
                    '$designation' => $designation,
                    '$password' => $password,
                    '$access_level' => $access_level,
                    '$udc' => $udc,
                    '$division' => $division,
                    '$office' => $office,
                    '$emp_tag' => $emp_tag,
                ];

                //TAKES IN AN ASSOCIATIVE ARRAY (KEY=>VALUE PAIRS)
                $result = is_dev_edit_required_data_empty($variables_to_check);

                //CHECK IF REQUIRED DATA EMPTY
                if ($result['is_empty']) {
                    $temp_dump['empty_data'] = "Some data required is missing! Empty values: ";
                    $empty_value_strings = [];

                    foreach ($result['empty_variables'] as $var_name => $var_value) {
                        $empty_value_strings[] = "$var_name: $var_value";
                    }

                    $temp_dump['empty_data'] .= implode(', ', $empty_value_strings);
                }

                if (!empty($formattedDesignation)) {
                    //CHECK IF DOCUMENT IS ALREADY ENCODED
                    if (check_if_exists_maximum($pdo, $formattedDesignation, $udc)) {
                        $temp_dump['maximum_designated'] = "" . $_SESSION['maxed_designation'] . ": Already reached the maximum designation!";
                    }
                }

                //CHECK ANY VALIDATION FAILS ELSE PROCEED TO EXECUTE DATABASE QUERY
                if ($temp_dump) {
                    $_SESSION['error_update_account'] = $temp_dump;
                    echo "<script>process_functionAlert('Edit Failed!', 'edit_account_err')</script>";
                    die();
                } else {
                    //LOOP FORMATTED DESIGNATION -> IF ===/ CONTAINS SPECIFIC DESIGNATION (OIC) -> UPDATE USER ACC IF ELSE
                    foreach ($designation as $desig) {
                        if ($desig === "Officer-In-Charge (PENR Office)") {
                            if (update_designation_limit_oic($pdo, $udc, $formattedDesignation, $so_no, $datetime_start, $datetime_end, $fullName) !== false) {
                                update_user__account($pdo, $emp_id, $emp_fn, $emp_mi, $emp_ln, $hashedPwd, $section, $division, $formattedDesignation, $access_level, $emp_tag);
                            } else {
                                echo "<script>process_functionAlert('Edit Failed!', 'developer_edit_failed')</script>";
                                die();
                            }
                        } else {
                            if (update_designation_limit($pdo, $udc, $formattedDesignation, $fullName, $office) !== false) {
                                update_user__account($pdo, $emp_id, $emp_fn, $emp_mi, $emp_ln, $hashedPwd, $section, $division, $formattedDesignation, $access_level, $emp_tag);
                            } else {
                                echo "<script>process_functionAlert('Edit Failed!', 'developer_edit_failed')</script>";
                                die();
                            }
                        }
                    }
                    echo "<script>process_functionAlert('Edit Success!', 'developer_edit_success')</script>";
                    die();
                }
            } else {
                echo "<script>process_functionAlert('Edit Account: Wrong Module Used!', 'developer_edit_err')</script>";
                die();
            }
        } catch (PDOException $e) {
            echo $e->getMessage();
        }

        $pdo = null;
        $statement = null;

        die();
    } catch (PDOException $e) {
        echo $e->getMessage();
    }
} else {
    require_once __DIR__ . '/../../../../core/components/redirects/redirect_config.inc.php';
    redirect_to('devtool');
}
