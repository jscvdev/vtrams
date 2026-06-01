<?php
session_start();
$emp_name = $emp_id = $section = $password = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once '../core/components/security/err_blocker.inc.php';
    require_once '../dbconnection.inc.php';
    require_once 'register.model.inc.php';
    require_once 'register.ctrl.inc.php';

    $emp_fn = $_POST['emp_fn'];
    $emp_mi = $_POST['emp_mi'];
    $emp_ln = $_POST['emp_ln'];
    $emp_id = $_POST['emp_id'];
    $section = $_POST['section'];
    $office = $_POST['office'];
    $password = $_POST['password'];

    try {

        $temp_dump = [];

        //ERR HANDLERS
        if (is_input_empty($emp_id, $emp_fn, $emp_mi, $emp_ln, $section, $office, $password)) {
            $temp_dump["empty_input"] = "Fill in all required fields!";
        }

        if (!is_name($emp_fn, $emp_mi, $emp_ln)) {
            $temp_dump['invalid_name'] = "The employee name is invalid!";
        }

        if (!is_emp_id($emp_id)) {
            $temp_dump['invalid_emp_id'] = "The employee ID is invalid!";
        }

        if (is_user_exists($pdo, $emp_id)) {
            $temp_dump["user_taken"] = "employee id is already taken!";
        }

        require_once '../core/components/security/config_session.inc.php';

        function generateUniqueRandomString($length = 5): string
        {
            $characters = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
            $randomString = '';
            $isUnique = false;

            // Keep generating random strings until we find a unique one
            while (!$isUnique) {
                $randomString = '';
                for ($i = 0; $i < $length; $i++) {
                    $randomString .= $characters[rand(0, strlen($characters) - 1)];
                }

                // Check if the generated string already exists in the database
                $existingString = checkIfStringExistsInDatabase($randomString);

                if (!$existingString) {
                    $isUnique = true;
                }
            }

            return $randomString;
        }

        // Function to check if the generated string already exists in the database
        function checkIfStringExistsInDatabase($randomString): bool
        {
            require '../dbconnection.inc.php';

            $query = "SELECT * FROM user_group WHERE udc = :udc";
            $statement = $pdo->prepare($query);
            $statement->bindParam(':udc', $randomString, PDO::PARAM_STR);
            $statement->execute();

            return $statement->rowCount() > 0;
        }

        // Generate a unique random 5-character string
        $randomString = generateUniqueRandomString();

        if ($temp_dump) {
            $_SESSION["error_register"] = $temp_dump;
            require_once __DIR__ . '/../core/components/redirects/redirect_config.inc.php';
            redirect_to('public_index');
        } else {
            create_user($pdo, $emp_id, $emp_fn, $emp_mi, $emp_ln, $section, $office, $password, $randomString);
            $_SESSION['success_register'] = "Registration successful!";
            require_once __DIR__ . '/../core/components/redirects/redirect_config.inc.php';
            redirect_to('public_index');
        }
    } catch (PDOException $e) {
        die("Query failed: " . $e->getMessage());
    }
} else {
    require_once __DIR__ . '/../core/components/redirects/redirect_config.inc.php';
    redirect_to('public_index');
}
