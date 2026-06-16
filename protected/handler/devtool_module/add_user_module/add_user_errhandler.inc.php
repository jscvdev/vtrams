<?php
declare(strict_types=1);

function check_add_user_errors() {
    if (isset($_SESSION['error_add_user'])){
        $errors = $_SESSION['error_add_user'];

        $err = trim(implode(' ', $errors));
        $errJson = json_encode('Error: ' . $err, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP);
        echo "<script>if (typeof showNotify === 'function') { showNotify($errJson, 'error', 3200); } else if (typeof err_handler_functionAlert === 'function') { err_handler_functionAlert($errJson, 'edit_account_err'); }</script>";

        unset($_SESSION['error_add_user']);
    }
}