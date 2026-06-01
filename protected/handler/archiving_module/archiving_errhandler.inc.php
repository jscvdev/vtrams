<?php
declare(strict_types=1);

function check_archiving_errors() {
    if (isset($_SESSION['error_archiving'])){
        $errors = $_SESSION['error_archiving'];

        $err = "";
        foreach ($errors as $error) {
            $err .=  $error. '  ';
        }
        echo "<script>err_handler_functionAlert('Error: $err', 'archiving_redirect_err')</script>";

        unset($_SESSION['error_archiving']);
    }
}
