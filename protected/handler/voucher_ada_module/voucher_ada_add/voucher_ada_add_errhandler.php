<?php
declare(strict_types=1);

function check_voucher_add_errors() {
    if (isset($_SESSION['error_voucher_add'])){
        $errors = $_SESSION['error_voucher_add'];

        $err = "";
        foreach ($errors as $error) {
            $err .=  $error. '  ';
        }
        echo "<script>alert('Error:   $err')</script>";

        unset($_SESSION['error_voucher_add']);
    }
}