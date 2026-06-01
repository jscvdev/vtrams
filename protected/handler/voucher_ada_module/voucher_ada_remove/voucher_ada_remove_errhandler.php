<?php
declare(strict_types=1);

function check_voucher_remove_errors() {
    if (isset($_SESSION['error_voucher_remove'])){
        $errors = $_SESSION['error_voucher_remove'];

        $err = "";
        foreach ($errors as $error) {
            $err .=  $error. '  ';
        }
        echo "<script>alert('Error:   $err')</script>";

        unset($_SESSION['error_voucher_remove']);
    }
}