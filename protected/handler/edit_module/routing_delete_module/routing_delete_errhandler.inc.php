<?php
declare(strict_types=1);

function check_routing_delete_errors() {
    if (isset($_SESSION['error_routing_delete'])){
        $errors = $_SESSION['error_routing_delete'];

        $err = "";
        foreach ($errors as $error) {
            $err .=  $error. '  ';
        }
        echo "<script>err_handler_functionAlert('Error: $err', 'delete_from_routing_document_err')</script>";

        unset($_SESSION['error_routing_delete']);
    }
}