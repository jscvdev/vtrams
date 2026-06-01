<?php
require_once __DIR__ . '/../redirects/redirect_config.inc.php';
require_once __DIR__ . '/alert_settings.inc.php';
?>
<!DOCTYPE html>
<html>

<head>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.1.0/remixicon.css" />
    <script src="https://ajax.googleapis.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>

    <script>
        // Centralized redirect map from core/components/redirects/redirect_config.inc.php
        // Use a global singleton to avoid "already been declared" when this component is included multiple times.
        window.redirectMap = window.redirectMap || <?php echo get_redirect_map_js_json(); ?>;

        function functionAlert(msg, code, myYes) {
            // Use the first #confirm only: pages may load err_handler markup with a duplicate id.
            const confirmBox = $("#confirm").first();
            confirmBox.find(".message").text(msg);

            function handleRedirectOrAction() {
                if (code === "clear") {
                    if (document.getElementById('btn-clear')) {
                        ['encoding_document_title', 'encoding_document_receiver', 'encoding_document_date', 'encoding_document_desc', 'encoding_document_sender']
                        .forEach(id => {
                            const el = document.getElementById(id);
                            if (el) el.value = "";
                        });
                    }
                } else if (code === "voucher-clear") {
                    if (document.getElementById('voucher-btn-clear')) {
                        const payeeEl = document.getElementById('payee_name');
                        const payeeDefault = payeeEl && payeeEl.getAttribute('data-default') ? payeeEl.getAttribute('data-default') : "";
                        const voucherFields = {
                            payee_name: payeeDefault,
                            tin_employee_no: "",
                            address: "",
                            voucher_date: "",
                            particulars: "",
                            amount: "1"
                        };
                        for (let id in voucherFields) {
                            const el = document.getElementById(id);
                            if (el) el.value = voucherFields[id];
                        }
                    }
                } else if (window.redirectMap && window.redirectMap[code]) {
                    window.location.href = window.redirectMap[code];
                }
            }

            function hideConfirm() {
                confirmBox.hide();
                var ov = document.getElementById("overlay3");
                if (ov) ov.style.display = "none";
            }

            confirmBox.find(".yes").off("click").on("click", function(e) {
                e.preventDefault();
                e.stopPropagation();
                hideConfirm();
                handleRedirectOrAction();
                if (typeof myYes === "function") {
                    myYes();
                }
            });

            confirmBox.find(".close").off("click").on("click", function(e) {
                e.preventDefault();
                hideConfirm();
            });

            var confirmEl = confirmBox[0];
            if (confirmEl) confirmEl.style.animation = "slideIn2 0.5s ease";
            var overlayEl = document.getElementById("overlay3");
            if (overlayEl) overlayEl.style.display = "block";
            confirmBox.show();

            function closeOverlay() {
                hideConfirm();
            }

            const overlay = document.getElementById("overlay3");
            if (overlay) {
                overlay.addEventListener("click", closeOverlay);
            }

            $(document).on('keypress', function(e) {
                if (e.key === "Enter") {
                    e.preventDefault();
                    var ok = confirmBox.find(".yes").get(0);
                    if (ok) ok.click();
                }
            });
        }
    </script>

    <style>
        #confirm {
            display: none;
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

        .overlay3 {
            display: none;
            position: fixed;
            width: 100%;
            height: 100%;
            top: 0;
            left: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 9999;
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

<body>
    <div class="overlay3" id="overlay3"></div>
    <div id="confirm">
        <div class="custom_alert-box">
            <div class="top_custom_alert-box">
                <div class="cd-box">
                    <div class="circular-design cd1"></div>
                    <div class="circular-design cd2"></div>
                    <div class="circular-design cd3"></div>
                </div>
                <div class="title-box">
                    <span class="title"><?php echo htmlspecialchars($alert_system_name, ENT_QUOTES, 'UTF-8'); ?></span>
                    <i class="ri-close-circle-line close"></i>
                </div>
            </div>
            <div class="body_custom_alert-box">
                <span class="message"></span>
            </div>
            <div class="footer_custom_alert-box">
                <button type="button" class="yes" id="yes">OK</button>
            </div>
        </div>
    </div>
</body>

</html>