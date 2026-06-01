<?php
declare(strict_types=1);

function check_register_errors() {
    if (isset($_SESSION['error_register'])){
        $errors = $_SESSION['error_register'];

        $err = "";
        foreach ($errors as $error) {
            $err .=  $error. '  ';
        }
        echo "<script>alert('Error:   $err')</script>";

        unset($_SESSION['error_register']);
    } else if (isset($_SESSION['success_register']))
    {
        unset($_SESSION['success_register']);
        echo '<script>alert("Register success!")</script>';
    }
}