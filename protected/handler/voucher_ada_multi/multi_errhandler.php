<?php
declare(strict_types=1);

function check_ada_errors() {
    if (isset($_SESSION['error_ada'])){
        $errors = $_SESSION['error_ada'];

        $err = "";
        foreach ($errors as $error) {
            $err .=  $error. '  ';
        }
        echo "<script>process_functionAlert('failed!', 'voucher_ada_redirect2')</script>";

        unset($_SESSION['error_ada']);
    }
}