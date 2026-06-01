<?php
session_start();
if (isset($_POST['click_view_btn'])) {
    $id = $_POST['btn-receive'];

    echo $id;
}
?>