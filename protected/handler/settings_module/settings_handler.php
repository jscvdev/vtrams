<?php

require_once __DIR__ . '/../../core/components/redirects/redirect_config.inc.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    require_once __DIR__ . '/../../dbconnection.inc.php';
    require_once __DIR__ . '/../../core/components/security/config_session.inc.php';
    require_once __DIR__ . '/../../core/components/security/router.inc.php';
    require_once __DIR__ . '/../../core/components/notifications/custom_process_alert.php';

    // Allow access if user has System Admin role (any ACL level)
    require_once __DIR__ . '/../../core/components/security/access_control.inc.php';
    if (!AccessControl::hasRole('System Admin')) {
        $_SESSION['settings_error'] = 'Access denied. System Administrator role required.';
        redirect_to('voucher');
    }

    // Check if token is valid
    $tokenIsValid = isset($_POST['token'], $_SESSION['token']) &&
        hash_equals((string) $_SESSION['token'], (string) $_POST['token']);
    if ($tokenIsValid) {
        try {
            if (isset($_REQUEST['update_settings'])) {
                // Get form data
                $system_name = isset($_POST['system_name']) ? trim($_POST['system_name']) : '';
                $page_title = isset($_POST['page_title']) ? trim($_POST['page_title']) : '';
                $company_name = isset($_POST['company_name']) ? trim($_POST['company_name']) : '';
                $browser_title = isset($_POST['browser_title']) ? trim($_POST['browser_title']) : '';
                $header_text = isset($_POST['header_text']) ? trim($_POST['header_text']) : '';

                // Validate required fields
                if (empty($system_name) || empty($page_title) || empty($company_name) || empty($browser_title) || empty($header_text)) {
                    $_SESSION['settings_error'] = 'All fields are required.';
                    redirect_to('settings');
                }

                // Check if settings record exists
                $check_query = "SELECT id FROM system_settings WHERE id = 1";
                $check_stmt = $pdo->prepare($check_query);
                $check_stmt->execute();
                $exists = $check_stmt->fetch(PDO::FETCH_ASSOC);

                if ($exists) {
                    // Update existing settings
                    $update_query = "UPDATE system_settings SET 
                        system_name = :system_name,
                        page_title = :page_title,
                        company_name = :company_name,
                        browser_title = :browser_title,
                        header_text = :header_text
                        WHERE id = 1";

                    $update_stmt = $pdo->prepare($update_query);
                    $update_stmt->bindParam(':system_name', $system_name, PDO::PARAM_STR);
                    $update_stmt->bindParam(':page_title', $page_title, PDO::PARAM_STR);
                    $update_stmt->bindParam(':company_name', $company_name, PDO::PARAM_STR);
                    $update_stmt->bindParam(':browser_title', $browser_title, PDO::PARAM_STR);
                    $update_stmt->bindParam(':header_text', $header_text, PDO::PARAM_STR);
                    $update_stmt->execute();
                } else {
                    // Insert new settings record
                    $insert_query = "INSERT INTO system_settings 
                        (id, system_name, page_title, company_name, browser_title, header_text) 
                        VALUES (1, :system_name, :page_title, :company_name, :browser_title, :header_text)";

                    $insert_stmt = $pdo->prepare($insert_query);
                    $insert_stmt->bindParam(':system_name', $system_name, PDO::PARAM_STR);
                    $insert_stmt->bindParam(':page_title', $page_title, PDO::PARAM_STR);
                    $insert_stmt->bindParam(':company_name', $company_name, PDO::PARAM_STR);
                    $insert_stmt->bindParam(':browser_title', $browser_title, PDO::PARAM_STR);
                    $insert_stmt->bindParam(':header_text', $header_text, PDO::PARAM_STR);
                    $insert_stmt->execute();
                }

                // Generate new token
                if (!function_exists('generateToken')) {
                    function generateToken()
                    {
                        return bin2hex(random_bytes(16));
                    }
                }
                $_SESSION['token'] = generateToken();

                // Success message
                $_SESSION['settings_success'] = 'Settings updated successfully!';
                redirect_to('settings');
            } else {
                $_SESSION['settings_error'] = 'Invalid request.';
                redirect_to('settings');
            }
        } catch (PDOException $e) {
            $_SESSION['settings_error'] = 'Database error: ' . $e->getMessage();
            redirect_to('settings');
        }
    } else {
        // Invalid token
        $_SESSION['settings_error'] = 'Invalid security token. Please try again.';
        redirect_to('settings');
    }
} else {
    redirect_to('settings');
}
