<?php
require_once __DIR__ . '/../../protected/core/components/helpers/http_cache_helper.inc.php';
send_no_cache_headers();
header('Content-type: text/html; charset=utf-8');
require '../../protected/core/components/security/err_blocker.inc.php';
require '../../protected/dbconnection.inc.php';
require '../../protected/core/components/security/config_session.inc.php';
require '../../protected/core/components/security/router.inc.php';
require '../../protected/core/components/security/user_check.php';
require '../../protected/handler/change_password_module/change_password_errhandler.inc.php';
require_once '../../protected/page_title_helper.inc.php';
require_once __DIR__ . '/../../protected/core/components/helpers/asset_version_helper.inc.php';

$vtramsStylesDir = __DIR__ . '/../styles/css';
$vtramsStylesHref = '/vtrams/public/styles/css/';

check_change_password_errors();

require_once '../../protected/core/components/helpers/table_exists_helper.php';

//VOUCHERS
$fetch_voucher_data_query = "SELECT * FROM vouchers WHERE encoded_by = :encoded_by ORDER BY processing_no DESC";
$fetch_voucher_data = $pdo->prepare($fetch_voucher_data_query);
$fetch_voucher_data->bindParam(":encoded_by", $_SESSION['logged_user_emp_name']);
$fetch_voucher_data->execute();

//FORWARDING
$fetch_voucher_receiving_data_query = "SELECT * FROM voucher_receiving WHERE receiver_udc LIKE :udc and office_to = :office_to ORDER BY processing_no DESC";
$fetch_voucher_receiving_data = $pdo->prepare($fetch_voucher_receiving_data_query);
$udc_param = '%' . $_SESSION["logged_user_udc"] . '%'; // Prepare the parameter with '%' wildcards
$fetch_voucher_receiving_data->bindParam(":udc", $udc_param, PDO::PARAM_STR);
$fetch_voucher_receiving_data->bindParam(":office_to", $_SESSION["logged_user_office"]);
$fetch_voucher_receiving_data->execute();

//INCOMING
$fetch_voucher_incoming_data_query = "SELECT * FROM voucher_incoming WHERE receiver_udc LIKE :udc and office_to = :office_to ORDER BY processing_no DESC";
$fetch_voucher_incoming_data = $pdo->prepare($fetch_voucher_incoming_data_query);
$udc_param = '%' . $_SESSION["logged_user_udc"] . '%'; // Prepare the parameter with '%' wildcards
$fetch_voucher_incoming_data->bindParam(":udc", $udc_param, PDO::PARAM_STR);
$fetch_voucher_incoming_data->bindParam(":office_to", $_SESSION["logged_user_office"]);
$fetch_voucher_incoming_data->execute();

//SENT — match voucher_sent.php: show all vouchers the user sent, any destination office
$fetch_voucher_sent_data_query = "SELECT * FROM voucher_sent WHERE sender_udc LIKE :udc ORDER BY processing_no DESC";
$fetch_voucher_sent_data = $pdo->prepare($fetch_voucher_sent_data_query);
$udc_param = '%' . $_SESSION["logged_user_udc"] . '%'; // Prepare the parameter with '%' wildcards
$fetch_voucher_sent_data->bindParam(":udc", $udc_param, PDO::PARAM_STR);
$fetch_voucher_sent_data->execute();
$target = explode(",", $_SESSION['logged_user_designation']);

function generateToken()
{
    return bin2hex(random_bytes(16)); // Generates a 32-character hexadecimal string
}

if (!isset($_SESSION['token'])) {
    $_SESSION['token'] = generateToken(); // Generate and store the token
}

date_default_timezone_set('Asia/Singapore');

// Load configurable system settings (title/header text)
/** @var PageTitleHelper $pageTitleHelper */
$pageTitleHelper = new PageTitleHelper($pdo);
$browser_title = $pageTitleHelper->getBrowserTitle();
$header_text = $pageTitleHelper->getHeaderText();
?>
<!doctype html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <?php require_once __DIR__ . '/../../protected/core/components/helpers/analytics_guard.inc.php'; ?>
    <meta name="viewport"
        content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title><?php echo htmlspecialchars($browser_title, ENT_QUOTES, 'UTF-8'); ?></title>
    <?php
    $file_name = basename(htmlspecialchars($_SERVER['PHP_SELF']));
    $file_path = htmlspecialchars($_SERVER['PHP_SELF']);

    // Check if the current file is in a section that uses base layout styles
    if (
        strpos($file_path, '/vouchers/') !== false || strpos($file_path, '\\vouchers\\') !== false ||
        strpos($file_path, '/utilities/') !== false || strpos($file_path, '\\utilities\\') !== false
    ) {
        asset_base_stylesheets($vtramsStylesHref, $vtramsStylesDir);
        asset_stylesheet($vtramsStylesHref . 'modern_filter_card.css', $vtramsStylesDir . '/modern_filter_card.css');
    } elseif ($file_name == 'upload.php') {
        asset_base_stylesheets($vtramsStylesHref, $vtramsStylesDir);
        asset_stylesheet($vtramsStylesHref . 'ustyle.css', $vtramsStylesDir . '/ustyle.css');
    }
    if ($file_name == 'devtool.php') {
        asset_base_stylesheets($vtramsStylesHref, $vtramsStylesDir);
    }
    ?>
    <link href='https://unpkg.com/boxicons@2.1.4/css/boxicons.min.css' rel='stylesheet'>
    <!--    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.4.1/dist/css/bootstrap.min.css" integrity="sha384-Vkoo8x4CGsO3+Hhxv8T/Q5PaXtkKtu6ug5TOeNV6gBiFeWPGFN9MuhOf23Q9Ifjh" crossorigin="anonymous">-->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.1.0/remixicon.css" />
    <link rel="shortcut icon" href="../assets/icons/DENR3.ico">
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.6.0/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<div class="overlay" id="header_overlay"></div>
<?php require '../../protected/core/components/notifications/custom_alert.php'; ?>

<body>
    <?php asset_script('../../protected/js/table_loader.js', __DIR__ . '/../../protected/js/table_loader.js'); ?>
    <div class="popup-form3" id="popupForm3">
        <div class="popupForm-box__container">
            <div class="popupForm-header__container">
                <p>Change Password</p>
                <i class="ri-close-fill close-icon" id="change_password_close"></i>
            </div>
            <form action="../../protected/handler/change_password_module/change_password_handler.php" class="f-container" method="post" id="myForm">
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
                        <img src="../assets/img/DENR3.png" alt="">
                    </a>
                    <p><?php echo htmlspecialchars($header_text, ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            </div>
            <div class="sidebar__account">
                <p id="time"></p>

                <img src="../assets/img/denr.png" alt="" class="sidebar__profile">
                <div class="sidebar__name">
                    <h3 class="sidebar__emp_name"><?php echo $_SESSION["logged_user_emp_name"]; ?></h3>
                </div>
                <div class="custom-select">
                    <i class="ri-arrow-drop-down-line select-styled sidebar__link_incoming target_select" id="target_select"></i>
                    <ul class="select-options" id="select">
                        <li data-value="2" id="test_click" onclick="changePassword()"><i class="ri-shield-keyhole-line"></i>Password</li>
                        <li data-value="3" onclick="functionAlert('Are you sure to logout?', 'logout')"><i class="ri-logout-circle-line"></i>Logout</li>
                    </ul>
                    <script>
                        function callCD(param) {
                            var xhr = new XMLHttpRequest();
                            var url = '../../protected/change_type.php?param=' + encodeURIComponent(param);
                            xhr.open('GET', url, true);
                            xhr.send(null);
                            xhr.onload = function() {
                                if (xhr.status === 200 && param === "vouchers") {
                                    window.location.href = '../vouchers/voucher.php';
                                }
                            };
                        }
                    </script>
                </div>
            </div>
        </div>
    </header>
    <!--=============== SIDEBAR ===============!-->
    <div class="sidebar" id="sidebar">
        <nav class="sidebar__container">
            <div class="sidebar__content">
                <?php if (!empty($_SESSION["change_type"]) and $_SESSION["change_type"] === "vouchers") : ?>
                    <div id="tab2">

                        <?php if ($_SESSION['acl'] >= 999) : ?>
                            <div class='sidebar__content cs2'>
                                <h3 class='sidebar__title'>
                                    <span>Tools</span>
                                </h3>
                                <div class='sidebar__list'>
                                    <div class="sidebar-link-container">
                                        <a href='../vouchers/devtool.php' class='sidebar__link'>
                                            <i class='ri-group-line'></i>
                                            <span class='sidebar__link-name'>Users</span>
                                            <span class='sidebar__link-floating'>Users</span>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endif; ?>
                        <?php
                        $can_view_voucher_overview_pages = (
                            $_SESSION["acl"] >= 8
                            || in_array("Budget Unit", $target)
                            || in_array("Cashiers Unit", $target)
                            || in_array("Accounting Unit", $target)
                            || in_array("Accountant III", $target)
                            || in_array("Processor", $target)
                            || in_array("Conservation & Development Section", $target)
                            || in_array("CDS", $target)
                        );
                        $can_view_designations = ($_SESSION["acl"] >= 8);
                        $can_view_performance = true;

                        $show_general_section = ($can_view_voucher_overview_pages || $can_view_designations || $can_view_performance);
                        ?>

                        <?php if ($show_general_section): ?>
                            <h3 class="sidebar__title">
                                <span>General</span>
                            </h3>
                            <div class="sidebar__list">
                                <?php if ($can_view_voucher_overview_pages): ?>
                                    <div class="sidebar-link-container">
                                        <a href="../vouchers/dashboard.php" class="sidebar__link" id="button1">
                                            <i class="ri-dashboard-fill"></i>
                                            <span class="sidebar__link-name">Dashboard</span>
                                            <span class="sidebar__link-floating">Dashboard</span>
                                        </a>
                                    </div>
                                    <?php if (in_array("System Admin", $target)): ?>
                                        <div class="sidebar-link-container">
                                            <a href="../utilities/utilities.php" class="sidebar__link">
                                                <i class="ri-tools-line"></i>
                                                <span class="sidebar__link-name">Utilities</span>
                                                <span class="sidebar__link-floating">Utilities</span>
                                            </a>
                                        </div>
                                        <div class="sidebar-link-container">
                                            <a href="../utilities/checklist.php" class="sidebar__link">
                                                <i class="ri-checkbox-multiple-line"></i>
                                                <span class="sidebar__link-name">Checklist</span>
                                                <span class="sidebar__link-floating">Checklist</span>
                                            </a>
                                        </div>
                                        <div class="sidebar-link-container">
                                            <a href="../utilities/special_access.php" class="sidebar__link">
                                                <i class="ri-route-line"></i>
                                                <span class="sidebar__link-name">Special Access</span>
                                                <span class="sidebar__link-floating">Special Access</span>
                                            </a>
                                        </div>
                                    <?php endif; ?>
                                    <div class="sidebar-link-container">
                                        <a href="../vouchers/voucher_status.php" class="sidebar__link" id="button1">
                                            <i class="ri-notification-badge-line"></i>
                                            <span class="sidebar__link-name">Status</span>
                                            <span class="sidebar__link-floating">Status</span>
                                        </a>
                                    </div>
                                    <div class="sidebar-link-container">
                                        <a href="../vouchers/voucher_system_logs.php" class="sidebar__link">
                                            <i class="ri-search-line"></i>
                                            <span class="sidebar__link-name">Tracking</span>
                                            <span class="sidebar__link-floating">Tracking</span>
                                        </a>
                                    </div>
                                <?php endif; ?>

                                <?php if ($can_view_designations): ?>
                                    <div class="sidebar-link-container">
                                        <a href="../vouchers/designations.php" class="sidebar__link">
                                            <i class="ri-user-star-line"></i>
                                            <span class="sidebar__link-name">Designations</span>
                                            <span class="sidebar__link-floating">Designations</span>
                                        </a>
                                    </div>
                                <?php endif; ?>

                                <?php if ($can_view_performance): ?>
                                    <div class="sidebar-link-container">
                                        <a href="../vouchers/voucher_performance.php" class="sidebar__link">
                                            <i class="ri-bar-chart-box-line"></i>
                                            <span class="sidebar__link-name">Performance</span>
                                            <span class="sidebar__link-floating">Performance</span>
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <div class='sidebar__content'>
                            <h3 class='sidebar__title'>
                                <span>Vouchers</span>
                            </h3>
                            <div class='sidebar__list'>
                                <div class="sidebar-link-container">
                                    <a href='../vouchers/voucher.php' class='sidebar__link'>
                                        <i class='ri-secure-payment-line sidebar__link_incoming' id='vouchers_pending'></i>
                                        <span class='sidebar__link-name'>Voucher</span>
                                        <span class='sidebar__link-floating'>Voucher</span>
                                    </a>
                                </div>
                                <div class="sidebar-link-container">
                                    <a href='../vouchers/voucher_encoded.php' class='sidebar__link'>
                                        <i class='ri-printer-line sidebar__link_incoming'></i>
                                        <span class='sidebar__link-name'>Encoded</span>
                                        <span class='sidebar__link-floating'>Encoded</span>
                                    </a>
                                </div>
                                <?php
                                if (
                                    $_SESSION['acl'] >= 3 or in_array("ICU", $target) or in_array("Planning Section", $target)
                                    or in_array("Budget Unit", $target) or in_array("Accounting Unit", $target)
                                    or in_array("Office of the PENRO", $target) or in_array("Cashiers Unit", $target) or in_array("Processor", $target)
                                ) : ?>
                                    <div class="sidebar-link-container">
                                        <a href='../vouchers/voucher_incoming.php' class='sidebar__link'>
                                            <i class='ri-file-list-3-line sidebar__link_incoming' id='vouchers_incoming'></i>
                                            <span class='sidebar__link-name'>Incoming</span>
                                            <span class='sidebar__link-floating'>Incoming</span>
                                        </a>
                                    </div>
                                    <div class="sidebar-link-container">
                                        <a href='../vouchers/voucher_forwarding.php' class='sidebar__link' id='button1'>
                                            <i class='ri-price-tag-3-line sidebar__link_incoming' id='vouchers_forwarding'></i>
                                            <span class='sidebar__link-name'>Processing</span>
                                            <span class='sidebar__link-floating'>Processing</span>
                                        </a>
                                    </div>
                                    <div class="sidebar-link-container">
                                        <a href='../vouchers/voucher_sent.php' class='sidebar__link'>
                                            <i class='ri-file-paper-2-line sidebar__link_incoming' id='vouchers_sent'></i>
                                            <span class='sidebar__link-name'>Forwarded</span>
                                            <span class='sidebar__link-floating'>Forwarded</span>
                                        </a>
                                    </div>
                                    <?php if ($_SESSION["acl"] >= 8 or in_array("Cashiers Unit", $target)): ?>
                                        <div class="sidebar-link-container">
                                            <a href="../vouchers/voucher_archives.php" class="sidebar__link">
                                                <i class="ri-search-line"></i>
                                                <span class="sidebar__link-name">Processed</span>
                                                <span class="sidebar__link-floating">Processed</span>
                                            </a>
                                        </div>
                                    <?php endif ?>
                            </div>
                        <?php endif ?>
                        </div>
                    </div>
                <?php endif; ?>
                <h3 class="sidebar__title">
                    <span>Others</span>
                </h3>
                <!--=============== SECOND LIST ===============!-->
                <div class="sidebar__list">
                    <?php if (in_array("System Admin", $target)) : ?>
                        <div class="sidebar-link-container">
                            <a href='../vouchers/auditing.php' class='sidebar__link'>
                                <i class='ri-shield-check-line'></i>
                                <span class='sidebar__link-name'>Auditing</span>
                                <span class='sidebar__link-floating'>Auditing</span>
                            </a>
                        </div>
                    <?php endif; ?>
                    <?php if (in_array("System Admin", $target)) : ?>
                        <div class="sidebar-link-container">
                            <a href='../vouchers/settings.php' class='sidebar__link'>
                                <i class='ri-settings-3-line'></i>
                                <span class='sidebar__link-name'>Settings</span>
                                <span class='sidebar__link-floating'>Settings</span>
                            </a>
                        </div>
                    <?php endif; ?>
                    <div class="sidebar-link-container">
                        <a class="sidebar__link" onclick="functionAlert('Are you sure to logout?', 'logout')">
                            <i class="ri-logout-circle-line"></i>
                            <span class="sidebar__link-name">Logout</span>
                            <span class="sidebar__link-floating">Logout</span>
                        </a>
                    </div>
                </div>
            </div>
        </nav>
    </div>

    <script>
        window.addEventListener('load', function() {
            const activeElement = document.querySelector('.active');
            if (activeElement) {
                activeElement.scrollIntoView({
                    behavior: 'smooth',
                    block: 'center'
                });
            }
        });
    </script>

    <script>
        $(document).ready(function() {
            function fetchPHPPage() {
                $.ajax({
                    url: '', // Replace with your PHP file
                    method: 'GET',
                    success: function(response) {},
                    error: function(xhr, status, error) {
                        console.error('Error:', error);
                    }
                });
            }

            // Run fetchPHPPage every 2 seconds
            setInterval(fetchPHPPage, 2000);
        });
    </script>

    <script>
        // Get the current URL
        let currentUrl = window.location.href;

        // Get the page or file name
        let pathname = new URL(currentUrl).pathname;
        let fileName = pathname.split('/').pop();

        // Get all elements with the class 'sidebar__link'
        var elements = document.getElementsByClassName('sidebar__link');

        // Iterate through each element
        for (var i = 0; i < elements.length; i++) {
            var href = elements[i].href; // Get the href attribute of the current element

            // Check if the href contains the target PHP file name
            if (href.includes(fileName)) {
                // Add class to the parent div with class 'sidebar-link-container'
                var parentDiv = elements[i].closest('.sidebar-link-container');
                if (parentDiv) {
                    parentDiv.classList.add('active');
                }
            } else {
                // Optional: Remove class from parent div if it doesn't match
                var parentDiv = elements[i].closest('.sidebar-link-container');
                if (parentDiv) {
                    parentDiv.classList.remove('active');
                }
            }
        }
    </script>


    <script>
        function confirmLogout() {
            var confirmation = confirm("Are you sure to logout?")
            if (confirmation) {
                window.location.href = <?php
                                        require_once __DIR__ . '/../../protected/core/components/redirects/redirect_config.inc.php';
                                        echo json_encode(redirect_base_url() . '/protected/core/components/security/logout_handler.inc.php', JSON_UNESCAPED_SLASHES);
                                        ?>;
                document.getElementById("user-options").selectedIndex = 0;
            } else {
                document.getElementById("user-options").selectedIndex = 0;
            }
        }
    </script>
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

    <!-- NOTIFICATIONS (voucher only) -->

    <?php $rowCount6 = $fetch_voucher_data->rowCount(); ?>
    <?php if (!empty($rowCount6)) { ?>
        <script>
            // Create a style element
            var style = document.createElement('style');

            var count = <?php echo json_encode($rowCount6); ?>;
            // Define the CSS rule with the content for the :after pseudo-element
            var css = '#vouchers_pending:after { display: inline-block; content: "' + count + '"; }';

            // Add the CSS rule to the style element
            style.appendChild(document.createTextNode(css));

            // Insert the style element into the document head
            document.head.appendChild(style);
        </script>
    <?php } ?>


    <?php $rowCount7 = $fetch_voucher_incoming_data->rowCount(); ?>
    <?php if (!empty($rowCount7)) { ?>
        <script>
            // Create a style element
            var style = document.createElement('style');

            var count = <?php echo json_encode($rowCount7); ?>;
            // Define the CSS rule with the content for the :after pseudo-element
            var css = '#vouchers_incoming:after { display: inline-block; content: "' + count + '"; }';
            var css2 = '#target_select:after { display: inline-block; content: "' + count + '"; }';

            // Add the CSS rule to the style element
            style.appendChild(document.createTextNode(css));
            style.appendChild(document.createTextNode(css2));

            // Insert the style element into the document head
            document.head.appendChild(style);
        </script>
    <?php } ?>

    <?php $rowCount8 = $fetch_voucher_receiving_data->rowCount(); ?>
    <?php if (!empty($rowCount8)) { ?>
        <script>
            // Create a style element
            var style = document.createElement('style');

            var count = <?php echo json_encode($rowCount8); ?>;
            // Define the CSS rule with the content for the :after pseudo-element
            var css = '#vouchers_forwarding:after { display: inline-block; content: "' + count + '"; }';

            // Add the CSS rule to the style element
            style.appendChild(document.createTextNode(css));

            // Insert the style element into the document head
            document.head.appendChild(style);
        </script>
    <?php } ?>

    <?php $rowCount9 = $fetch_voucher_sent_data->rowCount(); ?>
    <?php if (!empty($rowCount9)) { ?>
        <script>
            var style = document.createElement('style');
            var count = <?php echo json_encode($rowCount9); ?>;
            var css = '#vouchers_sent:after { display: inline-block; content: "' + count + '"; }';
            style.appendChild(document.createTextNode(css));
            document.head.appendChild(style);
        </script>
    <?php } ?>

    <script>
        var password = document.getElementById("new_password"),
            confirm_password = document.getElementById("confirm_password");

        function validatePassword() {
            if (password.value != confirm_password.value) {
                confirm_password.setCustomValidity("Passwords Don't Match");
            } else {
                confirm_password.setCustomValidity('');
            }
        }

        password.onchange = validatePassword;
        confirm_password.onkeyup = validatePassword;
    </script>