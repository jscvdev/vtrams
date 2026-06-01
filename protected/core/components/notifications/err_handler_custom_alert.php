<?php
require_once __DIR__ . '/../redirects/redirect_config.inc.php';
?>
<!DOCTYPE html>
<html>

<head>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.1.0/remixicon.css" />
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>
    <script>
        // Centralized redirect map from core/components/redirects/redirect_config.inc.php
        const err_handler_redirectMap = <?php echo get_redirect_map_js_json(); ?>;

        function err_handler_functionAlert(msg, code, myYes) {
            var confirmBox = $("#confirm");
            confirmBox.find(".message").text(msg);

            function doRedirect() {
                if (err_handler_redirectMap[code]) {
                    window.location.href = err_handler_redirectMap[code];
                }
            }

            confirmBox.find(".yes").unbind().click(function() {
                confirmBox.hide();
                document.getElementById("overlay2").style.display = "none";
                doRedirect();
            });

            confirmBox.find(".close").unbind().click(function() {
                confirmBox.hide();
                document.getElementById("overlay2").style.display = "none";
                doRedirect();
            });
            confirmBox.find(".yes").click(myYes);
            document.getElementById("confirm").style.animation = "slideIn2 0.5s ease"
            document.getElementById("overlay2").style.display = "block";
            confirmBox.show();

            function closeOverlay() {
                document.getElementById("overlay2").style.display = "none";
                confirmBox.hide();
                doRedirect();
            }
            if (document.getElementById("overlay2")) {
                document.getElementById("overlay2").addEventListener("click", closeOverlay);
            }

            $(document).on('keypress', function(e) {
                if (e.key === "Enter") {
                    e.preventDefault();
                    document.getElementById("yes").click();
                }
            });
        }
    </script>
    <style>
        #confirm {
            display: none;
            color: #000000;
            border: 1px solid lightslategrey;
            position: fixed;
            width: 400px;
            height: 150px;
            left: 50%;
            top: 10%;
            transform: translate(-50%, -10%);
            border-radius: 8px;
            box-sizing: border-box;
            text-align: center;
            z-index: 99999;
        }

        .footer_custom_alert-box button {
            background-color: #A6EC99;
            color: #000000;
            display: inline-block;
            border-radius: 12px;
            border: 1px solid #aaa;
            font-size: 10px;
            padding: 5px;
            text-align: center;
            width: 60px;
            cursor: pointer;
            float: right;
            margin: 0 5px 5px 0;
        }

        #confirm .message {
            text-align: center;
            font-size: 14px;
        }

        .top_custom_alert-box {
            background: #3B4850;
            border-radius: 8px 8px 0 0;
            width: 100%;
            height: 30px;
            display: flex;
            flex-direction: row;
        }

        .top_custom_alert-box span {
            float: left;
            color: ghostwhite;
            margin-left: 5px;
            margin-top: 7px;
            font-size: 12px;
        }

        .top_custom_alert-box i {
            color: #ffffff;
            margin-top: 5px;
            margin-right: 5px;
            font-size: 17px;
            float: right;
            display: flex;
            flex-direction: row;
        }

        .custom_alert-box {
            background: #FEFEFE;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            height: 100%;
            border-radius: 8px;
        }

        .body_custom_alert-box {
            flex: 1;
            align-content: center;
            font-weight: 600;
        }

        .footer_custom_alert-box {}

        .cd-box {
            display: flex;
            flex-direction: row;
            justify-content: center;
            align-items: center;
            margin-left: 10px;
            gap: 5px;
        }

        .title-box {
            flex: 1;
        }

        .circular-design {
            width: 14px;
            height: 14px;
            border-radius: 20px;
        }

        .cd1 {
            background: #D55F5A;
        }

        .cd2 {
            background: #F7D54A;
        }

        .cd3 {
            background: #76BC71;
        }

        .overlay2 {
            display: none;
            position: fixed;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9998;
        }

        @keyframes slideIn2 {
            from {
                transform: translate(-50%, -200%);
            }

            to {
                transform: translate(-50%, -10%);
            }
        }
    </style>
</head>
<div class="overlay2" id="overlay2"></div>

<body style="background: transparent">
    <div id="confirm">
        <div class="custom_alert-box">
            <div class="top_custom_alert-box">
                <div class="cd-box">
                    <div class="circular-design cd1"></div>
                    <div class="circular-design cd2"></div>
                    <div class="circular-design cd3"></div>
                </div>
                <div class="title-box">
                    <span class="title">PENRO Disbursement Voucher System</span>
                    <i class="ri-close-circle-line close"></i>
                </div>
            </div>
            <div class="body_custom_alert-box">
                <span class="message"></span>
            </div>
            <div class="footer_custom_alert-box">
                <button class="yes" id="yes">OK</button>
            </div>
        </div>
    </div>
</body>

</html>