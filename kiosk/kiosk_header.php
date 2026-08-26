<?php
require_once __DIR__ . '/../protected/core/components/helpers/http_cache_helper.inc.php';
send_no_cache_headers();
header('Content-type: text/html; charset=utf-8');
require_once __DIR__ . '/../protected/dbconnection.inc.php';
require_once __DIR__ . '/../protected/page_title_helper.inc.php';
require_once __DIR__ . '/../protected/core/components/helpers/asset_version_helper.inc.php';

/** @var PageTitleHelper $pageTitleHelper */
$pageTitleHelper = new PageTitleHelper($pdo);
$browser_title = $pageTitleHelper->getBrowserTitle();
$header_text = $pageTitleHelper->getHeaderText();

$kioskStylesDir = __DIR__ . '/../public/styles/css';
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <?php require_once __DIR__ . '/../protected/core/components/helpers/analytics_guard.inc.php'; ?>
    <meta name="viewport"
        content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?php echo htmlspecialchars($browser_title, ENT_QUOTES, 'UTF-8'); ?></title>
    <?php
    asset_base_stylesheets('../public/styles/css/', $kioskStylesDir);
    asset_stylesheet('kiosk.css', __DIR__ . '/kiosk.css');
    ?>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.1.0/remixicon.css" />
    <link rel="shortcut icon" href="../public/assets/icons/DENR3.ico">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<div class="overlay" id="header_overlay"></div>
<?php require __DIR__ . '/kiosk_alert.inc.php'; ?>
<?php require_once __DIR__ . '/../protected/core/components/notifications/notification.inc.php'; ?>

<body class="kiosk-body">
    <?php asset_script('../protected/js/table_loader.js', __DIR__ . '/../protected/js/table_loader.js'); ?>
    <div class="popup-form3" id="popupForm3">
        <div class="popupForm-box__container">
            <div class="popupForm-header__container">
                <p>Change Password</p>
                <i class="ri-close-fill close-icon" id="change_password_close"></i>
            </div>
            <form action="../protected/handler/change_password_module/change_password_handler.php" class="f-container" method="post" id="myForm">
                <div class="box-body__container">
                    <div class="popupForm-body__container">
                        <div class="form-container">
                            <div class="label-input__container">
                                <label for="">New Password</label>
                                <input class="form-custom-input" type="text" name="new_password" id="new_password" value="" placeholder="New Password" required>
                            </div>
                            <div class="label-input__container">
                                <label for="">Confirm Password</label>
                                <input class="form-custom-input" type="text" name="confirm_password" id="confirm_password" value="" placeholder="Confirm Password" required>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="popupForm-footer__container">
                    <div class="footer-button__container">
                        <button class="btn success transparent btn-save" name="change_password" type="submit">SAVE</button>
                        <button class="btn secondary transparent btn-close" id="change_password_close2" type="button">CANCEL</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <!--=============== SIDEBAR BACKGROUND ===============!-->
    <!--=============== HEADER ===============!-->
    <header class="header">
        <div class="header__container">
            <div class="menu-logo__container">
                <div class="header__toggle" id="header-toggle">
                    <i class="ri-menu-2-line"></i>
                </div>
                <div class="header-logo__container">
                    <a href="#" class="header__logo">
                        <img src="../public/assets/img/vtlogo2.png" alt="">
                    </a>
                    <p><?php echo htmlspecialchars($header_text, ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            </div>
            <div class="sidebar__account">
                <p id="time"></p>
            </div>
        </div>
    </header>
    <!--=============== SIDEBAR ===============!-->
    <div class="sidebar" id="sidebar">
        <nav class="sidebar__container">
            <div class="sidebar__content">
            </div>
        </nav>
    </div>

    <script>
        function changePassword() {
            function openPopup_header() {
                document.getElementById("popupForm3").style.display = "block";
                document.getElementById("popupForm3").style.animation = "slideIn 0.5s ease"
                document.getElementById("header_overlay").style.display = "block";
            }
            openPopup_header();

            function closePopup_header() {
                document.getElementById("popupForm3").style.display = "none";
                document.getElementById("header_overlay").style.display = "none";
            }

            if (document.getElementById("header_overlay")) {
                document.getElementById("header_overlay").addEventListener("click", closePopup_header);
            }

            if (document.getElementById('change_password_close')) {
                document.getElementById("change_password_close").addEventListener("click", closePopup_header);
            }
            if (document.getElementById('change_password_close2')) {
                document.getElementById("change_password_close2").addEventListener("click", closePopup_header);
            }
        }
    </script>

    <script>
        var password = document.getElementById("new_password"),
            confirm_password = document.getElementById("confirm_password");

        function validatePassword() {
            if (password && confirm_password && password.value != confirm_password.value) {
                confirm_password.setCustomValidity("Passwords Don't Match");
            } else if (confirm_password) {
                confirm_password.setCustomValidity('');
            }
        }

        if (password) password.onchange = validatePassword;
        if (confirm_password) confirm_password.onkeyup = validatePassword;
    </script>