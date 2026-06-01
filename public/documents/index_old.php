<?php
require_once '../../protected/core/components/security/err_blocker.inc.php';
require_once '../../protected/dbconnection.inc.php';
require_once '../../protected/core/components/security/config_session.inc.php';
require_once '../../protected/register_module/register_errhandler.inc.php';
require_once '../../protected/login_module/login_errhandler.inc.php';

if (isset($_SESSION['logged_in']))
{
    header("Location: /vtrams/public/vouchers/db_voucher.php");
}
?>

<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <base href="/vtrams/public/documents/">
    <title>PENRO-DTS</title>
    <!--PLUGINS-->
    <link rel="stylesheet" href="../styles/css/index.css">
    <link rel="stylesheet" href="../styles/css/loader.css">
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <link rel="shortcut icon" href="../assets/icons/DENR3.ico">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.1.0/remixicon.css"/>
    <?php include '../../protected/core/components/notifications/custom_alert.php';
    check_login_errors();
    check_register_errors();
    ?>
</head>
<div id="pre-loader">
    <div class="loader"></div>
</div>
<body>
    <div class="main_container">
        <div class="content">
            <div class="content_item box1">
                <div class="main_content_wrapper">
                    <div class="slider_wrapper stacked">
                        <div class="slider">
                        </div>
                    </div>
                    <div class="main_content stacked">
                        <div class="logo_container">
                            <img src="../assets/img/DENR3.png" alt="">
                            <h5>DOCUMENT TRACKING SYSTEM</h5>
                        </div>
                        <div class="intro_container">
                            <p>"A nation enjoying and sustaining its natural resources and clean and healthy environment."
                            </p>
                            <p>"To mobilize our citizenry in protecting, conserving, and managing the environment and natural resources for the present and future generations."</p>
                            <p>"Discipline, Excellence, Nobility and Responsibility."</p>
                        </div>
                        <div class="icons_container">
                            <a href="#" target="_blank"><i class='bx bxl-facebook-circle' ></i></a>
                            <a href="#"><i class='bx bx-envelope' target="_blank"></i></a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="content_item box2">
                <div class="login_form">
                    <form action="../../protected/login_module/login_handler.inc.php" method="post" class="form_container">
                        <h2>Login</h2>
                        <div class="input_container">
                            <label for="">Username</label>
                            <span class="icon"><i class='bx bxs-envelope'></i></span>
                            <input type="text" name="emp_id">
                        </div>
                        <div class="input_container">
                            <label for="">Password</label>
                            <span class="icon"><i class='bx bxs-lock-alt'></i></span>
                            <input type="password" name="password">
                        </div>
                        <div class="input_container">
                            <button type="submit" class="btn">Login</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</body>
<script>
    var loader = document.getElementById("pre-loader");

    window.addEventListener("load", function () {
        loader.style.display = "none";
    })
</script>
</html>
