<?php
ob_start();

// Use the central session configuration (secure cookies, regeneration, etc.)
require_once __DIR__ . '/config_session.inc.php';

// Database connection (provides $pdo)
require_once __DIR__ . '/../../../dbconnection.inc.php';

// Reuse existing login module (validation + user fetching)
require_once __DIR__ . '/../../../login_module/login.model.inc.php';
require_once __DIR__ . '/../../../login_module/login.ctrl.inc.php';
require_once __DIR__ . '/../helpers/user_login_security_helper.inc.php';

// Audit helper for logging login events
require_once __DIR__ . '/../helpers/audit_helper.inc.php';

header('Content-Type: application/json');

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $action   = $_POST['action']   ?? null;
    // Map the frontend "username" field to the existing emp_id-based login
    $emp_id   = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($action !== "login") {
        echo json_encode(["status" => "error", "message" => "Invalid action"]);
        exit;
    }

    // Use login_module's validator
    if (is_input_empty($emp_id, $password)) {
        echo json_encode(["status" => "error", "message" => "Username and password are required"]);
        exit;
    }

    try {
        user_login_ensure_schema($pdo);

        // Use login_module's model to get the user from user_group
        $result = get_user($pdo, $emp_id);

        if (is_user_incorrect($result)) {
            AuditHelper::logActivity(
                'login_failed',
                "Failed login attempt for employee ID: {$emp_id}",
                ['emp_id' => $emp_id]
            );
            echo json_encode(["status" => "error", "message" => "Invalid credentials"]);
            exit;
        }

        if (user_login_is_blocked($result)) {
            AuditHelper::logActivity(
                'login_failed',
                "Blocked account login attempt for employee ID: {$emp_id}",
                ['emp_id' => $emp_id]
            );
            echo json_encode(["status" => "error", "message" => user_login_blocked_message()]);
            exit;
        }

        if (is_password_incorrect($password, $result["password"])) {
            $attempts = user_login_record_failed_attempt($pdo, $emp_id);
            AuditHelper::logActivity(
                'login_failed',
                "Failed login attempt for employee ID: {$emp_id}",
                ['emp_id' => $emp_id, 'failed_attempts' => $attempts]
            );

            echo json_encode([
                "status" => "error",
                "message" => user_login_failed_password_message($attempts),
            ]);
            exit;
        }

        user_login_reset_attempts($pdo, $emp_id);

        // Regenerate session ID for security; avoid resetting when session is already active
        session_regenerate_id(true);

        $formattedResultName = explode(" ", $result['emp_fn'] . " " . $result['emp_mi'] . " " . $result['emp_ln']);
        $formattedResultName2  = implode(" ", $formattedResultName);
        $full_name = $formattedResultName2;

        $_SESSION["logged_user_emp_name"] = $full_name;
        $_SESSION["logged_user_emp_id"] = $result['emp_id'];
        $_SESSION["logged_user_office"] = $result['office'];
        $_SESSION["logged_user_section"] = $result['section'];
        $_SESSION["logged_user_division"] = $result['division'];
        $_SESSION["logged_user_password"] = $result['password'];
        $_SESSION["logged_user_udc"] = $result['udc'];
        $_SESSION["change_type"] = "vouchers";

        $_SESSION["last_regeneration"] = time();

        $_SESSION['logged_in'] = "true"; // LOGIN FLAG

        $_SESSION['acl'] = $result['access_level'];
        $_SESSION['logged_user_designation'] = $result['designation'];

        if ($_SESSION['logged_user_section'] != "RECORDS") {
            $_SESSION["routing"] = "new route";
        }

        // Optionally set generic identifiers used by other subsystems (e.g. auditing)
        if (isset($result['id'])) {
            $_SESSION['user_id'] = $result['id'];
        }
        $_SESSION['username'] = $result['emp_id'];

        // Log successful login
        AuditHelper::logLogin($result['emp_id']);

        echo json_encode(["status" => "success", "message" => "Login successful"]);
    } catch (PDOException $e) {
        echo json_encode(["status" => "error", "message" => "Database error: " . $e->getMessage()]);
    }
} else {
    echo json_encode(["status" => "error", "message" => "Invalid request method"]);
}

ob_end_flush();

