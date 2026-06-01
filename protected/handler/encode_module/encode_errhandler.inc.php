<?php
declare(strict_types=1);

function check_encode_errors() {
    if (isset($_SESSION['error_encode'])){
        $errors = $_SESSION['error_encode'];

        $err = "";
        foreach ($errors as $error) {
            $err .=  $error. '  ';
        }
        if (isset($_SESSION['purpose_encode']))
        {
            unset($_SESSION['purpose_encode']);
            echo "<script>err_handler_functionAlert('Error: $err', 'encode_document_err_redirect')</script>";
        }
        if (isset($_SESSION['purpose_reply']))
        {
            unset($_SESSION['purpose_reply']);
            echo "<script>err_handler_functionAlert('Error: $err', 'reply_encode_document_err')</script>";
        }
        unset($_SESSION['error_encode']);
        die();
    }
}