<?php
include('../includes/header.php');
require_once __DIR__ . '/../../protected/core/components/helpers/audit_helper.inc.php';
AuditHelper::logPageView('Vouchers');
include('../../protected/handler/voucher_forward_module/voucher_forward_errhandler.inc.php');
include('../../protected/handler/voucher_module/voucher_errhandler.inc.php');
include('../../protected/core/components/notifications/err_handler_custom_alert.php');
require_once __DIR__ . '/../../protected/core/components/notifications/custom_alert.php';
require_once __DIR__ . '/../../protected/core/components/notifications/notification.inc.php';
require_once __DIR__ . '/checklist_config.php';

include 'db_voucher.php';
check_voucher_errors();
check_voucher_forward_errors();

// Centralized voucher types for checklists and dropdowns (from checklist_config + scanned templates)
$voucher_types_for_select = checklist_types_with_labels();

// Ensure logged user name is available
if (empty($_SESSION['logged_user_emp_name']) && !empty($_SESSION['logged_user_emp_id'])) {
    require_once __DIR__ . '/../../protected/dbconnection.inc.php';
    $user_query = "SELECT emp_fn, emp_mi, emp_ln FROM user_group WHERE emp_id = :emp_id LIMIT 1";
    $user_stmt = $pdo->prepare($user_query);
    $user_stmt->bindParam(":emp_id", $_SESSION['logged_user_emp_id']);
    $user_stmt->execute();
    $user_result = $user_stmt->fetch(PDO::FETCH_ASSOC);
    if ($user_result) {
        $formattedName = trim($user_result['emp_fn'] . ' ' . ($user_result['emp_mi'] ?? '') . ' ' . $user_result['emp_ln']);
        $_SESSION['logged_user_emp_name'] = preg_replace('/\s+/', ' ', $formattedName);
    }
}

// Store logged user name for JavaScript
$logged_user_name = htmlspecialchars($_SESSION['logged_user_emp_name'] ?? '', ENT_QUOTES, 'UTF-8');

function session_contains_phrase($phrase)
{
    foreach ($_SESSION as $key => $value) {
        // Use stripos for case-insensitive search
        if (stripos($value, $phrase) !== false) {
            return true;
        }
    }
    return false;
}
?>
<!--=============== MAIN ===============!-->
<div class="main main--voucher-dashboard" id="main">
    <header class="voucher-dashboard-header">
        <h1 class="voucher-dashboard-title">Vouchers</h1>
        <div class="btn warning btn-flex btn-nowrap popupForm-add btn-pad voucher-dashboard-btn-primary" id="voucher-dashboard-new-btn">
            <img src="../assets/icons/add-icon.png" alt="">
            <a id="openPopup">New Voucher</a>
        </div>
    </header>
    <style>
        #voucherFilterForm {
            display: flex;
            align-items: center;
            flex-wrap: nowrap !important;
            width: 100%;
            gap: 10px;
        }

        #voucherFilterForm .filter-chips {
            flex: 0 0 auto;
            flex-wrap: nowrap !important;
        }

        #voucherFilterForm .filter-search {
            flex: 1 1 auto;
            min-width: 0 !important;
        }
    </style>
    <div class="voucher-card voucher-card--filter">
        <div class="filter-toolbar">
            <div class="filter-left">
                <form method="GET" action="" id="voucherFilterForm" class="filter-toolbar-form" onsubmit="return false;">
                    <div class="filter-chips" aria-label="Voucher filter tools">
                        <a class="filter-icon-btn" href="voucher.php" aria-label="Home">
                        </a>
                        <button type="button" class="filter-icon-btn" aria-label="Copy">
                        </button>
                    </div>
                    <div class="filter-search">
                        <input type="text" id="filterInput" placeholder="Search for payee, particulars, processing no., etc" autocomplete="off">
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="popup-form voucher-modal" id="popupForm2">
        <div class="popupForm-box__container">
            <div class="popupForm-header__container">
                <p>New Voucher</p>
                <i class="ri-close-fill close-icon" id="close_popup"></i>
            </div>
            <form action="../../protected/handler/voucher_module/voucher_handler.php" class="f-container" method="post">
                <div class="box-body__container flex-row">
                    <div class="popupForm-body__container">
                        <div class="form-container">
                            <div class="label-input__container">
                                <label for="">Payee</label>
                                <input class="form-custom-input" type="text" name="payee" id="payee_name" value="" data-default="<?php echo $logged_user_name; ?>" placeholder="Payee" required>
                            </div>
                            <div class="label-input__container">
                                <label for="">TIN/Employee No.</label>
                                <input class="form-custom-input" type="text" name="tin_employee_no" id="tin_employee_no" value="" placeholder="TIN/Employee No. (No Dash)">
                            </div>
                            <div class="label-input__container">
                                <label for="address">Address</label>
                                <input class="form-custom-input" type="text" name="address" id="address" value="" placeholder="Address">
                                <span id="address-error" style="color: red; display: none;">Please enter a valid address.</span>
                            </div>

                            <script>
                                document.getElementById('address').addEventListener('input', function() {
                                    const addressInput = document.getElementById('address');
                                    const addressError = document.getElementById('address-error');

                                    // Regex for a basic address format (street number, street name, and optional city)
                                    const addressRegex = /^[0-9]+\s[a-zA-Z0-9\s,.'-]+$|^[a-zA-Z\s,.]+(?:\s[a-zA-Z\s,.]+)*$/;

                                    // Check if the input matches the regex
                                    if (addressRegex.test(addressInput.value)) {
                                        addressError.style.display = 'none'; // Hide error message
                                    } else {
                                        addressError.style.display = 'inline'; // Show error message
                                    }
                                });
                            </script>

                            <div class="label-input__container">
                                <label for="">Voucher Date</label>
                                <input class="form-custom-input" type="date" name="voucher_date" id="voucher_date" value="" required>
                                <script>
                                    const today = new Date().toISOString().split('T')[0]
                                    document.getElementById('voucher_date').value = today;
                                </script>
                            </div>
                        </div>
                    </div>
                    <div class="popupForm-body__container">
                        <div class="form-container">
                            <div class="label-input__container">
                                <label for="">Type</label>
                                <select name="voucher_type" id="type-select" class="form-custom-input" required>
                                    <option value="" disabled selected>Please Select:</option>
                                    <?php foreach ($voucher_types_for_select as $type_value => $type_label): ?>
                                        <option value="<?= htmlspecialchars($type_value, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($type_label, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <!-- Month Year input for Travel Expenses -->
                            <div class="label-input__container" id="month-year-container" style="display: none;">
                                <label for="">Month Year</label>
                                <input class="form-custom-input" type="month" name="month_year" id="month-year" value="" placeholder="Month Year">
                            </div>
                            <!-- eNGP inputs -->
                            <div class="label-input__container" id="engp-quarter-container" style="display: none;">
                                <label for="">Quarter</label>
                                <select class="form-custom-input" name="engp_quarter" id="engp-quarter">
                                    <option value="" disabled selected>Please Select:</option>
                                    <option value="1st">1st</option>
                                    <option value="2nd">2nd</option>
                                    <option value="3rd">3rd</option>
                                    <option value="4th">4th</option>
                                </select>
                            </div>
                            <div class="label-input__container" id="engp-year-container" style="display: none;">
                                <label for="">Year</label>
                                <input class="form-custom-input" type="number" name="engp_year" id="engp-year" value="" placeholder="Year" min="2000" max="2100">
                            </div>
                            <div class="label-input__container" id="engp-area-container" style="display: none;">
                                <label for="">Area (hectares)</label>
                                <input class="form-custom-input" type="text" name="engp_area" id="engp-area" value="" placeholder="Area in hectares">
                            </div>
                            <div class="label-input__container" id="engp-commodity-container" style="display: none;">
                                <label for="">Commodity</label>
                                <input class="form-custom-input" type="text" name="engp_commodity" id="engp-commodity" value="" placeholder="Commodity">
                            </div>
                            <div class="label-input__container" id="engp-location-container" style="display: none;">
                                <label for="">Location</label>
                                <input class="form-custom-input" type="text" name="engp_location" id="engp-location" value="" placeholder="Location">
                            </div>
                            <div class="label-input__container">
                                <label for="">Particulars</label>
                                <textarea name="particulars" id="particulars" cols="30" rows="10" class="multi-line-input form-custom-multi-input" placeholder="Particulars ...." required></textarea>
                                <span id="particulars-error" style="color: red; display: none;">Please edit the particulars before submitting.</span>
                            </div>
                            <script>
                                const particularsMap = {};
                                const voucherTypeValues = <?= json_encode(array_keys($voucher_types_for_select)) ?>;
                                const defaultParticularsGeneric = "For payment as per supporting documents hereto attached in the total amount of ......";
                                const selectEl = document.getElementById("type-select");
                                const particularsEl = document.getElementById("particulars");
                                const errorEl = document.getElementById("particulars-error");
                                const monthYearContainer = document.getElementById("month-year-container");
                                const monthYearInput = document.getElementById("month-year");

                                // eNGP input elements
                                const engpQuarterContainer = document.getElementById("engp-quarter-container");
                                const engpQuarterInput = document.getElementById("engp-quarter");
                                const engpYearContainer = document.getElementById("engp-year-container");
                                const engpYearInput = document.getElementById("engp-year");
                                const engpAreaContainer = document.getElementById("engp-area-container");
                                const engpAreaInput = document.getElementById("engp-area");
                                const engpCommodityContainer = document.getElementById("engp-commodity-container");
                                const engpCommodityInput = document.getElementById("engp-commodity");
                                const engpLocationContainer = document.getElementById("engp-location-container");
                                const engpLocationInput = document.getElementById("engp-location");

                                let defaultParticulars = "";
                                let selectedValue = "";

                                // Function to format month-year value (e.g., "2026-01" to "January 2026")
                                function formatMonthYear(monthYearValue) {
                                    if (!monthYearValue) return "";
                                    const [year, month] = monthYearValue.split("-");
                                    const monthNames = ["January", "February", "March", "April", "May", "June",
                                        "July", "August", "September", "October", "November", "December"
                                    ];
                                    const monthIndex = parseInt(month) - 1;
                                    return monthNames[monthIndex] + " " + year;
                                }

                                // Function to update particulars with month-year value for TEV
                                function updateParticularsWithMonthYear() {
                                    if (selectedValue === "TEV" && monthYearInput.value) {
                                        const formattedMonthYear = formatMonthYear(monthYearInput.value);
                                        const updatedParticulars = defaultParticulars.replace("<MONTH YEAR>", formattedMonthYear);
                                        particularsEl.value = updatedParticulars;
                                    } else if (selectedValue === "TEV") {
                                        // If month-year is cleared, restore default with placeholder
                                        particularsEl.value = defaultParticulars;
                                    }
                                }

                                // Function to update particulars with eNGP values
                                function updateParticularsWithENGP() {
                                    if (selectedValue === "eNGP") {
                                        let updatedParticulars = defaultParticulars;

                                        // Replace all placeholders with actual values or keep placeholders if empty
                                        if (engpQuarterInput.value) {
                                            updatedParticulars = updatedParticulars.replace("<QUARTER>", engpQuarterInput.value);
                                        }

                                        if (engpYearInput.value) {
                                            updatedParticulars = updatedParticulars.replace(/<YEAR>/g, engpYearInput.value);
                                        }

                                        if (engpAreaInput.value) {
                                            updatedParticulars = updatedParticulars.replace("<AREA>", engpAreaInput.value);
                                        }

                                        if (engpCommodityInput.value) {
                                            updatedParticulars = updatedParticulars.replace("<COMMODITY>", engpCommodityInput.value);
                                        }

                                        if (engpLocationInput.value) {
                                            updatedParticulars = updatedParticulars.replace("<LOCATION>", engpLocationInput.value);
                                        }

                                        particularsEl.value = updatedParticulars;
                                    }
                                }

                                selectEl.addEventListener("change", function() {
                                    selectedValue = this.value;
                                    defaultParticulars = particularsMap[selectedValue] || defaultParticularsGeneric;

                                    // Hide all dynamic input containers first
                                    monthYearContainer.style.display = "none";
                                    engpQuarterContainer.style.display = "none";
                                    engpYearContainer.style.display = "none";
                                    engpAreaContainer.style.display = "none";
                                    engpCommodityContainer.style.display = "none";
                                    engpLocationContainer.style.display = "none";

                                    // Clear all dynamic inputs
                                    monthYearInput.value = "";
                                    engpQuarterInput.value = "";
                                    engpYearInput.value = "";
                                    engpAreaInput.value = "";
                                    engpCommodityInput.value = "";
                                    engpLocationInput.value = "";

                                    // Show/hide inputs based on selection
                                    if (selectedValue === "TEV") {
                                        monthYearContainer.style.display = "block";
                                        // Auto-populate with current month-year
                                        const today = new Date();
                                        const year = today.getFullYear();
                                        const month = String(today.getMonth() + 1).padStart(2, '0');
                                        monthYearInput.value = `${year}-${month}`;
                                        // Update particulars with month-year value
                                        updateParticularsWithMonthYear();
                                    } else if (selectedValue === "eNGP") {
                                        // Show all eNGP inputs
                                        engpQuarterContainer.style.display = "block";
                                        engpYearContainer.style.display = "block";
                                        engpAreaContainer.style.display = "block";
                                        engpCommodityContainer.style.display = "block";
                                        engpLocationContainer.style.display = "block";

                                        // Auto-populate Year with current year
                                        engpYearInput.value = new Date().getFullYear();

                                        // Update particulars with eNGP values
                                        updateParticularsWithENGP();
                                    } else {
                                        // For other types (Procurement of Supplies), just show default particulars
                                        particularsEl.value = defaultParticulars;
                                    }
                                    errorEl.style.display = "none"; // hide error when new value is selected
                                });

                                // Update particulars when month-year input changes
                                monthYearInput.addEventListener("change", function() {
                                    updateParticularsWithMonthYear();
                                });

                                // Update particulars when eNGP inputs change
                                engpQuarterInput.addEventListener("change", function() {
                                    updateParticularsWithENGP();
                                });

                                engpYearInput.addEventListener("input", function() {
                                    updateParticularsWithENGP();
                                });

                                engpAreaInput.addEventListener("input", function() {
                                    updateParticularsWithENGP();
                                });

                                engpCommodityInput.addEventListener("input", function() {
                                    updateParticularsWithENGP();
                                });

                                engpLocationInput.addEventListener("input", function() {
                                    updateParticularsWithENGP();
                                });

                                const typesNoParticularsEditRequired = voucherTypeValues;
                                document.getElementById("popupForm2").addEventListener("submit", function(e) {
                                    if (particularsEl.value === defaultParticulars && !typesNoParticularsEditRequired.includes(selectedValue)) {
                                        e.preventDefault(); // prevent form submission
                                        errorEl.style.display = "inline"; // show error
                                        particularsEl.focus();
                                    } else {
                                        errorEl.style.display = "none"; // hide error if edited
                                    }
                                });
                            </script>
                            <div class="label-input__container number-input">
                                <label for="">Amount</label>
                                <input class="form-custom-input"
                                    type="text"
                                    min="1.00"
                                    oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\.\d{2})\d+/g, '$1')"
                                    name="amount"
                                    value="1.00"
                                    placeholder="Amount"
                                    id="amount"
                                    required>
                            </div>
                            <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
                        </div>
                    </div>
                </div>
                <div class="popupForm-footer__container">
                    <div class="footer-button__container">
                        <button class="btn primary" name="save_document" type="submit">Save voucher</button>
                        <button class="btn warning transparent" id="voucher-btn-clear" name="" onclick="functionAlert('Are you sure to clear?', 'voucher-clear')" type="button">CLEAR</button>
                        <button class="btn secondary transparent" id="close_popup2" type="button">CANCEL</button>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <div class="popup-form" id="popupForm">
        <div class="popupForm-box__container">
            <div class="popupForm-header__container">
                <p id="form_title">Forward Document</p>
                <i class="ri-close-fill close-icon" id="close_popup3"></i>
            </div>
            <form action="#" class="f-container" method="post" id="encoded_voucher_form" onsubmit="syncVoucherType()">
                <div class="box-body__container flex-row">
                    <div class="popupForm-body__container">
                        <div class="form-container">
                            <div class="label-input__container">
                                <label for="">Processing No.</label>
                                <input type="text" name="processing_no" class="processing_no form-custom-input" id="processing_no" value="" placeholder="Processing No." required readonly>
                            </div>
                            <div class="label-input__container">
                                <label for="">Payee</label>
                                <input type="text" name="encoded_payee" class="encoded_payee form-custom-input" id="encoded_payee" value="" placeholder="Payee" required readonly>
                            </div>
                            <div class="label-input__container">
                                <label for="">Address</label>
                                <input type="text" name="encoded_address" class="encoded_address form-custom-input" id="encoded_address" value="" placeholder="Address" readonly>
                            </div>
                            <div class="label-input__container">
                                <label for="">Voucher Date</label>
                                <input type="date" name="encoded_voucher_date" class="encoded_voucher_date form-custom-input" id="encoded_voucher_date" value="" required readonly>
                            </div>
                            <div class="label-input__container">
                                <label for="">TIN/Employee No.</label>
                                <input type="text" name="encoded_tin_employee_no" class="encoded_tin_employee_no form-custom-input" id="encoded_tin_employee_no" value="" placeholder="TIN/Employee No." readonly>
                            </div>
                        </div>
                    </div>
                    <div class="popupForm-body__container">
                        <div class="form-container">
                            <div class="label-input__container">
                                <label for="">Particulars</label>
                                <textarea name="encoded_particulars" id="encoded_particulars" cols="30" rows="10" class="multi-line-input encoded_particulars form-custom-multi-input" placeholder="Particulars ...." required readonly></textarea>
                            </div>
                            <div class="label-input__container">
                                <label for="">Amount</label>
                                <input name="string_amount" id="string_amount" class="string_amount form-custom-input" readonly>
                            </div>
                            <div class="label-input__container number-input hidden_input">
                                <label for="">Amount</label>
                                <input class="form-custom-input encoded_amount" id="encoded_amount"
                                    type="text"
                                    min="1"
                                    oninput="this.value = this.value.replace(/[^0-9.]/g, '').replace(/(\.\d{2})\d+/g, '$1')"
                                    name="encoded_amount"
                                    placeholder="Amount"
                                    required>
                            </div>
                            <div class='label-input__container'>
                                <label for=''>Voucher Type</label>
                                <select class='encoded_type form-custom-input' name='encoded_type' id='encoded_type' required>
                                    <option value="" disabled selected>Please Select:</option>
                                    <?php foreach ($voucher_types_for_select as $type_value => $type_label): ?>
                                        <option value="<?= htmlspecialchars($type_value, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($type_label, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class='label-input__container'>
                                <label for=''>Remarks</label>
                                <input type='text' class='remarks form-custom-input' name='remarks' id='remarks' value='' placeholder='Remarks'>
                            </div>
                            <div class="label-input__container hidden_input">
                                <label for="">Encoded_by</label>
                                <input name="encoded_by" id="encoded_by" class="encoded_by">
                            </div>
                            <div class="label-input__container hidden_input">
                                <label for="">Voucher Type</label>
                                <input name="voucher_type" id="voucher_type" class="voucher_type">
                            </div>
                            <div class="label-input__container hidden_input">
                                <label for="">Encoded Type (for submission)</label>
                                <input name="encoded_type" id="encoded_type_hidden" class="encoded_type_hidden">
                            </div>
                            <div class="label-input__container hidden_input">
                                <label for="">Selected COA Options</label>
                                <input type="text" name="selected_coa_options_forward" id="selected_coa_options_forward" class="selected_coa_options_forward" value="">
                            </div>
                            <div class="label-input__container hidden_input">
                                <label for="">COA Category (hidden)</label>
                                <input type="text" name="coa_category_forward" id="coa_category_forward_hidden" value="">
                            </div>
                            <div class="label-input__container hidden_input">
                                <label for="">COA Subsection (hidden)</label>
                                <input type="text" name="coa_subsection_forward" id="coa_subsection_forward_hidden" value="">
                            </div>
                            <div class="label-input__container hidden_input">
                                <label for="">Date/Time Encoded</label>
                                <input name="datetime_encoded" id="datetime_encoded" class="datetime_encoded">
                            </div>
                            <div class="label-input__container hidden_input">
                                <label for="">Encoded From</label>
                                <input name="encoded_from" id="encoded_from" class="encoded_from">
                            </div>
                            <div class="label-input__container hidden_input">
                                <label for="">Slip Printed Flag</label>
                                <input type="hidden" name="slip_printed_flag" id="slip_printed_flag" value="0">
                            </div>
                            <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
                        </div>
                    </div>
                </div>
                <div class="popupForm-footer__container">
                    <div class="footer-button__container">
                        <button class="btn primary transparent" id="confirm_forward_requirements" type="button">CONFIRM</button>
                        <button class="btn warning transparent" id="print_forward_slip" type="button">PRINT SLIP</button>
                        <button class="btn transparent btn-dynamic" name="forward_voucher" type="submit"></button>
                        <button class="btn secondary transparent" id="close_popup4" type="button">CANCEL</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <style>
        #coaOptionsModalForward {
            z-index: 10001;
        }
        #signatoryModal {
            z-index: 10001;
        }
        #coa_modal_overlay_forward {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 10000;
        }
        #signatory_modal_overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            z-index: 10000;
        }
        #coa_options_list_forward label {
            display: flex;
            align-items: center;
            padding: 10px 12px;
            cursor: pointer;
            border-bottom: 1px solid #eee;
            transition: background-color 0.2s;
        }
        #coa_options_list_forward label:hover {
            background-color: #f0f0f0;
        }
        #coa_options_list_forward label:last-child {
            border-bottom: none;
        }
        #coa_options_list_forward input[type="checkbox"] {
            margin-right: 10px;
            cursor: pointer;
        }

        /* Disabled Forward button (before slip is printed) */
        .btn-disabled-forward[disabled] {
            opacity: 0.5;
            cursor: not-allowed;
            pointer-events: none;
        }

        /* Add top spacing in the slip form */
        #encoded_voucher_form {
            margin-top: 0.3in;
        }
    </style>
    <!-- COA Options Modal for Forward Voucher -->
    <div class="popup-form" id="coaOptionsModalForward" style="display: none;">
        <div class="popupForm-box__container">
            <div class="popupForm-header__container">
                <p id="coa_modal_title_forward">COA Requirements</p>
                <i class="ri-close-fill close-icon" id="close_coa_modal_forward"></i>
            </div>
            <div class="f-container">
                <div class="box-body__container flex-row">
                    <div class="popupForm-body__container" style="width: 100%;">
                        <div class="form-container">
                            <div class="label-input__container">
                                <label for="coa_options_checklist_forward">Select COA Requirements <span style="color: red;">*</span></label>
                                <div id="coa_options_list_forward" style="background-color: white; border: 1px solid #ccc; border-radius: 4px; padding: 10px; max-height: 400px; overflow-y: auto;">
                                    <!-- Options will be populated dynamically -->
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="popupForm-footer__container">
                    <div class="footer-button__container" style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                        <p style="margin: 0; font-size: 12px; color: #555; flex: 1; min-width: 200px;">By clicking "Confirm," I certify that all required attachments have been submitted and acknowledge that incomplete submissions may result in processing delays.</p>
                        <button class="btn tertiary" id="coa_modal_select_all_forward" type="button">Select all</button>
                        <button class="btn primary" id="coa_modal_save_forward" type="button">Confirm</button>
                        <button class="btn secondary transparent" id="coa_modal_cancel_forward" type="button">CANCEL</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="overlay" id="coa_modal_overlay_forward" style="display: none;"></div>
    <!-- DV Signatory Selection Modal (before print) -->
    <div class="popup-form" id="signatoryModal" style="display: none;">
        <div class="popupForm-box__container">
            <div class="popupForm-header__container">
                <p id="signatory_modal_title">Select Signatories</p>
                <i class="ri-close-fill close-icon" id="close_signatory_modal"></i>
            </div>
            <div class="f-container">
                <div class="box-body__container flex-row">
                    <div class="popupForm-body__container" style="width: 100%;">
                        <div class="form-container">
                            <p style="margin: 0 0 12px; font-size: 13px; color: #555;">Choose signatories for the printed Disbursement Voucher before proceeding.</p>
                            <div class="label-input__container">
                                <label for="dv_sig_cert_select">A. Certified <span style="color: red;">*</span></label>
                                <select class="form-custom-input" id="dv_sig_cert_select" required></select>
                            </div>
                            <div class="label-input__container">
                                <label for="dv_sig_accounting_select">C. Certified (Accounting) <span style="color: red;">*</span></label>
                                <select class="form-custom-input" id="dv_sig_accounting_select" required></select>
                            </div>
                            <div class="label-input__container">
                                <label for="dv_sig_approved_select">D. Approved for Payment <span style="color: red;">*</span></label>
                                <select class="form-custom-input" id="dv_sig_approved_select" required></select>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="popupForm-footer__container">
                    <div class="footer-button__container">
                        <button class="btn primary" id="signatory_modal_print" type="button">Print Voucher</button>
                        <button class="btn secondary transparent" id="signatory_modal_cancel" type="button">CANCEL</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="overlay" id="signatory_modal_overlay" style="display: none;"></div>
    <div class="overlay" id="overlay"></div>
    <style>
        /* Ensure voucher and COA modals don't exceed 85% of viewport height and space is distributed */
        #popupForm2 .popupForm-box__container,
        #popupForm .popupForm-box__container,
        #coaOptionsModalForward .popupForm-box__container,
        #signatoryModal .popupForm-box__container {
            max-height: 85vh;
            display: flex;
            flex-direction: column;
        }

        #popupForm2 .f-container,
        #popupForm .f-container,
        #coaOptionsModalForward .f-container,
        #signatoryModal .f-container {
            flex: 1;
            display: flex;
            flex-direction: column;
            min-height: 0;
        }

        #popupForm2 .box-body__container,
        #popupForm .box-body__container,
        #coaOptionsModalForward .box-body__container,
        #signatoryModal .box-body__container {
            flex: 1;
            min-height: 0;
            overflow-y: auto;
        }

        #popupForm2 .popupForm-footer__container,
        #popupForm .popupForm-footer__container,
        #coaOptionsModalForward .popupForm-footer__container,
        #signatoryModal .popupForm-footer__container {
            flex-shrink: 0;
        }
    </style>
    <div class="voucher-card voucher-card--table">
        <h2 class="voucher-card-title">Voucher Summary</h2>
        <style>
            .voucher-card--table {
                position: relative;
                display: flex;
                flex-direction: column;
            }

            .voucher-card--table .content-wrapper {
                flex: 1;
                min-height: 0;
                overflow: auto;
                max-height: 70vh;
            }

            .voucher-table-empty-hint {
                width: 100%;
                min-height: 220px;
                justify-content: center;
                align-items: center;
                font-weight: 500;
                color: rgb(107 114 128);
                text-transform: uppercase;
                font-size: 12px;
                letter-spacing: 0.04em;
            }

            .voucher-table-empty-hint p {
                margin: 0;
            }

            .voucher-pagination-footer {
                position: sticky;
                bottom: 0;
                z-index: 5;
                background: #fff;
                border-top: 1px solid rgba(229, 231, 235, 1);
                padding: 10px 0 0;
                margin-top: 0;
            }
        </style>
        <div class="content-wrapper">
            <table class="table content_table content_table--dashboard" id="my-Table">
                <thead>
                    <tr>
                        <th>Processing No.</th>
                        <th>Payee Name</th>
                        <th>Address</th>
                        <th>Particulars</th>
                        <th>Amount</th>
                        <th>Date</th>
                        <th>Type</th>
                        <th>Remarks</th>
                        <th>Forward</th>
                        <th>Edit</th>
                        <th>Print</th>
                    </tr>
                </thead>
                <tbody id="tableBody"></tbody>
            </table>
            <div class="no-display voucher-table-empty-hint" id="voucherNoData" style="display:none;">
                <p>NO DATA TO DISPLAY</p>
            </div>
        </div>
        <div class="voucher-pagination-footer">
            <div class="pagination">
                <div class="pagination_container pagination_container--modern" id="voucherPagination">
                    <div class="pagination_navigation pagination_navigation--modern">
                        <button class="pagination_btn_modern" type="button" id="voucherPrevPage">Previous</button>
                        <div class="pagination_pages pagination_pages--modern" id="voucherPageNumbers"></div>
                        <button class="pagination_btn_modern" type="button" id="voucherNextPage">Next</button>
                    </div>
                    <div class="pagination_info" id="voucherPaginationInfo"></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    // Load table rows async so the page shell renders fast (1000+ rows won't block initial render).
    (function() {
        const tableBody = document.getElementById('tableBody');
        const noData = document.getElementById('voucherNoData');
        const tableWrapper = document.querySelector('.voucher-card--table .content-wrapper');
        const pagWrap = document.getElementById('voucherPagination');
        const pagInfo = document.getElementById('voucherPaginationInfo');
        const pageNumbers = document.getElementById('voucherPageNumbers');
        const prevBtn = document.getElementById('voucherPrevPage');
        const nextBtn = document.getElementById('voucherNextPage');
        if (!tableBody) return;
        const perPage = 50;
        let searchQ = '';
        let debounceTimer = null;
        let currentPage = 1;
        let totalPages = 1;
        let totalRows = 0;
        let activeController = null;
        if (tableWrapper) tableWrapper.setAttribute('data-table-loading', 'true');
        const filterEl = document.getElementById('filterInput');

        function scheduleSearchReload() {
            if (!filterEl) return;
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function() {
                searchQ = String(filterEl.value || '');
                currentPage = 1;
                loadPage(1);
            }, 200);
        }
        if (filterEl) {
            filterEl.addEventListener('input', scheduleSearchReload);
        }

        const typeLabels = <?php echo json_encode($voucher_types_for_select ?? [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

        function escapeHtml(s) {
            return String(s ?? '')
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/\"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function typeBadge(stored) {
            const raw = String(stored ?? '').trim();
            const label = raw ? (typeLabels[raw] || raw) : '';
            if (!label) return '<span class="voucher-type-badge voucher-type-badge--empty">&mdash;</span>';
            return '<span class="voucher-type-badge">' + escapeHtml(label) + '</span>';
        }

        function remarksBadge(text) {
            const t = String(text ?? '').trim();
            if (!t) return '';
            return '<span class="remarks-badge">' + escapeHtml(t) + '</span>';
        }

        function buildPassItemPayload(row) {
            const pn = String(row.processing_no ?? '');
            const list = {};
            const arr = [];
            Object.keys(row || {}).forEach(function(k) {
                arr.push({ key: k, value: String(row[k] ?? '') });
            });
            list[pn] = arr;
            return list;
        }

        function attachPrintHandler(btn, row) {
            btn.addEventListener('click', function() {
                try {
                    const payload = buildPassItemPayload(row);
                    const pn = String(row.processing_no ?? '');
                    if (typeof passItem === 'function') {
                        passItem(payload, pn);
                    }
                } catch (e) {}
                if (typeof openSignatoryModal === 'function') {
                    openSignatoryModal();
                } else {
                    window.print();
                }
            });
        }

        function handleRowAction(row, name) {
            var processing_no = String(row.processing_no || '');
            var payee = String(row.payee || '');
            var address = String(row.address || '');
            var particulars = String(row.particulars || '');
            var amount = String(row.amount || '');
            var voucher_date = String(row.voucher_date || '');
            var encoded_by = String(row.encoded_by || '');
            var datetime_encoded = String(row.datetime_encoded || '');
            var encoded_from = String(row.encoded_from || '');
            var tin_employee_no = String(row.tin_employee_no || '');
            var voucher_type = String(row.voucher_type || '');
            var encodedTypeSelect = document.getElementById('encoded_type');
            var hiddenVoucherType = document.getElementById('voucher_type');
            var slipPrintedInput = document.getElementById('slip_printed_flag');
            var dynamicBtn = document.querySelector('.btn-dynamic');
            var encodedTypeHidden = document.getElementById('encoded_type_hidden');
            var convertedBack = parseFloat(amount.replace(/[,]/g, ''));

            if (document.querySelector('.processing_no')) document.querySelector('.processing_no').value = processing_no;
            if (document.querySelector('.encoded_payee')) document.querySelector('.encoded_payee').value = payee;
            if (document.querySelector('.encoded_address')) document.querySelector('.encoded_address').value = address;
            if (document.querySelector('.encoded_particulars')) document.querySelector('.encoded_particulars').value = particulars;
            if (document.querySelector('.string_amount')) document.querySelector('.string_amount').value = amount;
            if (document.querySelector('.encoded_amount') && !isNaN(convertedBack)) document.querySelector('.encoded_amount').value = convertedBack;
            if (document.querySelector('.encoded_voucher_date')) document.querySelector('.encoded_voucher_date').value = voucher_date;
            if (document.querySelector('.encoded_by')) document.querySelector('.encoded_by').value = encoded_by;
            if (document.querySelector('.datetime_encoded')) document.querySelector('.datetime_encoded').value = datetime_encoded;
            if (document.querySelector('.encoded_from')) document.querySelector('.encoded_from').value = encoded_from;
            if (document.querySelector('.encoded_tin_employee_no')) document.querySelector('.encoded_tin_employee_no').value = tin_employee_no;
            if (encodedTypeSelect) encodedTypeSelect.value = voucher_type;
            if (hiddenVoucherType) hiddenVoucherType.value = voucher_type;
            if (encodedTypeHidden) encodedTypeHidden.value = voucher_type;

            if (typeof openPopup === 'function') openPopup();
            else {
                var popup = document.getElementById('popupForm');
                var overlay = document.getElementById('overlay');
                if (popup) popup.style.display = 'block';
                if (overlay) overlay.style.display = 'block';
            }

            if (slipPrintedInput) slipPrintedInput.value = '0';

            if (name === 'btn-forward') {
                var formF = document.getElementById('encoded_voucher_form');
                if (formF) formF.setAttribute('action', '../../protected/handler/voucher_forward_module/voucher_forward_handler.php');
                if (document.querySelector('.btn-dynamic')) document.querySelector('.btn-dynamic').textContent = 'Forward';
                if (document.getElementById('form_title')) document.getElementById('form_title').textContent = 'Forward Voucher';
                if (document.getElementById('string_amount')) document.getElementById('string_amount').readOnly = true;
                document.querySelectorAll('.processing_no, .encoded_payee, .encoded_address, .encoded_tin_employee_no, .encoded_particulars, .encoded_amount, .encoded_voucher_date').forEach(function(input) { input.setAttribute('readonly', true); });
                if (encodedTypeSelect) encodedTypeSelect.setAttribute('disabled', true);
                if (encodedTypeHidden) encodedTypeHidden.value = voucher_type;
                if (document.querySelector('.btn-dynamic')) {
                    document.querySelector('.btn-dynamic').setAttribute('name', 'forward_voucher');
                    document.querySelector('.btn-dynamic').classList.add('primary');
                    document.querySelector('.btn-dynamic').classList.remove('success');
                }
                if (dynamicBtn) {
                    dynamicBtn.disabled = true;
                    dynamicBtn.classList.add('btn-disabled-forward');
                }
            } else if (name === 'btn-edit') {
                var formE = document.getElementById('encoded_voucher_form');
                if (formE) formE.setAttribute('action', '../../protected/handler/edit_module/edit_voucher_handler.php');
                if (document.querySelector('.btn-dynamic')) document.querySelector('.btn-dynamic').textContent = 'Edit';
                if (document.getElementById('form_title')) document.getElementById('form_title').textContent = 'Edit Voucher';
                if (document.getElementById('string_amount')) document.getElementById('string_amount').readOnly = false;
                if (document.querySelector('.btn-dynamic')) {
                    document.querySelector('.btn-dynamic').setAttribute('name', 'edit_voucher');
                    document.querySelector('.btn-dynamic').classList.add('success');
                    document.querySelector('.btn-dynamic').classList.remove('primary');
                }
                if (dynamicBtn) {
                    dynamicBtn.disabled = false;
                    dynamicBtn.classList.remove('btn-disabled-forward');
                }
                document.querySelectorAll('.processing_no, .encoded_dv_no, .encoded_payee, .encoded_address, .encoded_tin_employee_no, .encoded_particulars, .encoded_amount, .encoded_voucher_date').forEach(function(input) { input.removeAttribute('readonly'); });
                if (encodedTypeSelect) encodedTypeSelect.removeAttribute('disabled');
                if (encodedTypeSelect && encodedTypeHidden) {
                    encodedTypeSelect.onchange = function() { encodedTypeHidden.value = this.value; };
                }
            }
        }

        function setPagination() {
            if (!pagWrap || !prevBtn || !nextBtn || !pagInfo) return;
            const hasRows = totalRows > 0;
            // Keep footer visible even when empty; just disable controls.
            pagWrap.style.display = 'flex';
            prevBtn.disabled = !hasRows || currentPage <= 1;
            nextBtn.disabled = !hasRows || currentPage >= totalPages;
            const start = hasRows ? ((currentPage - 1) * perPage) + 1 : 0;
            const end = hasRows ? Math.min(currentPage * perPage, totalRows) : 0;
            pagInfo.textContent = hasRows ? ('Showing ' + end + ' of ' + totalRows + ' results') : 'NO DATA TO DISPLAY';
            renderPageNumbers();
        }

        function appendPageButton(n) {
            if (!pageNumbers) return;
            const b = document.createElement('button');
            b.type = 'button';
            b.className = 'pagination_page_num' + (n === currentPage ? ' active' : '');
            b.textContent = String(n);
            b.addEventListener('click', function() { if (n !== currentPage) loadPage(n); });
            pageNumbers.appendChild(b);
        }

        function appendEllipsis() {
            if (!pageNumbers) return;
            const e = document.createElement('span');
            e.className = 'pagination_ellipsis';
            e.textContent = '...';
            pageNumbers.appendChild(e);
        }

        function renderPageNumbers() {
            if (!pageNumbers) return;
            pageNumbers.innerHTML = '';
            if (totalRows <= 0) return;
            if (totalPages <= 7) {
                for (let i = 1; i <= totalPages; i++) appendPageButton(i);
                return;
            }
            appendPageButton(1);
            const start = Math.max(2, currentPage - 1);
            const end = Math.min(totalPages - 1, currentPage + 1);
            if (start > 2) appendEllipsis();
            for (let i = start; i <= end; i++) appendPageButton(i);
            if (end < totalPages - 1) appendEllipsis();
            appendPageButton(totalPages);
        }

        function renderRows(data) {
            tableBody.innerHTML = '';
            const frag = document.createDocumentFragment();
            data.forEach(function(row) {
                const tr = document.createElement('tr');
                tr.className = 'voucher-data-row';
                tr.innerHTML =
                    '<td data-label="processing_no">' + escapeHtml(row.processing_no) + '</td>' +
                    '<td data-label="payee">' + escapeHtml(row.payee) + '</td>' +
                    '<td data-label="address">' + escapeHtml(row.address) + '</td>' +
                    '<td data-label="particulars">' + escapeHtml(row.particulars) + '</td>' +
                    '<td data-label="amount" class="amount">' + escapeHtml(row.amount) + '</td>' +
                    '<td data-label="voucher_date">' + escapeHtml(row.voucher_date) + '</td>' +
                    '<td data-label="voucher_type_display" class="voucher-type-cell">' + typeBadge(row.voucher_type) + '</td>' +
                    '<td data-label="return_remarks" class="return-remarks-cell">' + remarksBadge(row.return_remarks) + '</td>' +
                    '<td data-label="encoded_by" class="hidden">' + escapeHtml(row.encoded_by) + '</td>' +
                    '<td data-label="datetime_encoded" class="hidden">' + escapeHtml(row.datetime_encoded) + '</td>' +
                    '<td data-label="encoded_from" class="hidden">' + escapeHtml(row.encoded_from) + '</td>' +
                    '<td data-label="tin_employee_no" class="hidden">' + escapeHtml(row.tin_employee_no) + '</td>' +
                    '<td data-label="voucher_type" class="hidden">' + escapeHtml(row.voucher_type) + '</td>' +
                    '<td data-label=""><button class="btn primary pPop" name="btn-forward" type="button">Forward</button></td>' +
                    '<td data-label=""><button class="btn success pPop" name="btn-edit" type="button">Edit</button></td>' +
                    '<td data-label=""><button class="btn warning" name="btn-gen-slip" type="button">Print</button></td>';
                const printBtn = tr.querySelector('button[name="btn-gen-slip"]');
                if (printBtn) attachPrintHandler(printBtn, row);
                const fwdBtn = tr.querySelector('button[name="btn-forward"]');
                if (fwdBtn) fwdBtn.addEventListener('click', function(e) { e.preventDefault(); e.stopPropagation(); handleRowAction(row, 'btn-forward'); });
                const editBtn = tr.querySelector('button[name="btn-edit"]');
                if (editBtn) editBtn.addEventListener('click', function(e) { e.preventDefault(); e.stopPropagation(); handleRowAction(row, 'btn-edit'); });
                frag.appendChild(tr);
            });
            tableBody.appendChild(frag);
            if (typeof Intl !== 'undefined') {
                document.querySelectorAll('.amount').forEach(function(el) {
                    const num = parseFloat(String(el.innerText).replace(/,/g, ''));
                    if (isNaN(num)) return;
                    el.innerText = new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(num);
                });
            }
        }

        function listUrl(page) {
            var u = '../../protected/handler/fetch_handlers/fetch_my_vouchers.php?page=' + encodeURIComponent(String(page))
                + '&per_page=' + encodeURIComponent(String(perPage));
            if (String(searchQ || '').trim() !== '') {
                u += '&q=' + encodeURIComponent(searchQ);
            }
            return u;
        }

        function loadPage(page) {
            currentPage = page;
            if (activeController) activeController.abort();
            activeController = new AbortController();
            if (tableWrapper) tableWrapper.setAttribute('data-table-loading', 'true');
            fetch(listUrl(page), {
                credentials: 'same-origin',
                signal: activeController.signal
            })
                .then(function(r) {
                    return r.json().then(function(payload) {
                        return { ok: r.ok, status: r.status, payload: payload };
                    });
                })
                .then(function(res) {
                    if (!res.ok) {
                        var msg = (res.payload && res.payload.error) ? res.payload.error : ('Request failed (' + res.status + ')');
                        tableBody.innerHTML = '';
                        if (noData) {
                            noData.innerHTML = '<p>' + escapeHtml(msg) + '</p>';
                            noData.style.display = 'flex';
                        }
                        totalRows = 0;
                        totalPages = 1;
                        setPagination();
                        if (typeof showNotify === 'function') {
                            showNotify(msg, 'warning', 2600);
                        }
                        if (tableWrapper) tableWrapper.setAttribute('data-table-loading', 'false');
                        return;
                    }
                    var payload = res.payload;
                    var data = Array.isArray(payload && payload.data) ? payload.data : [];
                    totalRows = Number(payload && payload.total ? payload.total : data.length);
                    totalPages = Math.max(1, Number(payload && payload.total_pages ? payload.total_pages : 1));
                    if (data.length === 0) {
                        tableBody.innerHTML = '';
                        if (noData) {
                            noData.innerHTML = '<p>NO DATA TO DISPLAY</p>';
                            noData.style.display = 'flex';
                        }
                        if (String(searchQ || '').trim() !== '' && typeof showNotify === 'function') {
                            showNotify('No matching vouchers found for your search.', 'warning', 2200);
                        }
                    } else {
                        if (noData) {
                            noData.innerHTML = '<p>NO DATA TO DISPLAY</p>';
                            noData.style.display = 'none';
                        }
                        renderRows(data);
                    }
                    setPagination();
                    if (tableWrapper) tableWrapper.setAttribute('data-table-loading', 'false');
                })
                .catch(function(err) {
                    if (err && err.name === 'AbortError') return;
                    if (noData) {
                        tableBody.innerHTML = '';
                        noData.innerHTML = '<p>FAILED TO LOAD DATA</p>';
                        noData.style.display = 'flex';
                    }
                    totalRows = 0;
                    totalPages = 1;
                    setPagination();
                    if (tableWrapper) tableWrapper.setAttribute('data-table-loading', 'false');
                });
        }

        if (prevBtn) prevBtn.addEventListener('click', function() { if (currentPage > 1) loadPage(currentPage - 1); });
        if (nextBtn) nextBtn.addEventListener('click', function() { if (currentPage < totalPages) loadPage(currentPage + 1); });
        loadPage(1);
    })();
</script>

<script>
    (function() {
        var inputElement = document.getElementById('string_amount');
        var outputElement = document.getElementById('encoded_amount');
        if (!inputElement || !outputElement) return;
        var lastValue = inputElement.value;

        function checkForChange() {
            var currentValue = inputElement.value;
            if (currentValue !== lastValue) {
                lastValue = currentValue;
                var convertedBack = parseFloat(String(currentValue).replace(/,/g, ''));
                if (!isNaN(convertedBack)) outputElement.value = convertedBack.toFixed(2);
            }
        }
        setInterval(checkForChange, 100);
    })();
</script>


<script>
    $(document).ready(function() {
        $(".prioritized").each(function() {
            if ($(this).text() == "Urgent") {
                $(this).parent().css("background-color", "lightyellow");
                $(this).parent().children('td').css("color", "orangered");
            }
        })
    });
</script>

<!--=============== MAIN.JS ===============!-->
<script src="../../protected/js/main.js"></script>
<script src="../../protected/js/voucher.js"></script>
<script src="../../protected/js/popscript.js"></script>
<script>
    (function() {
        const loggedUserName = <?php echo json_encode($logged_user_name); ?>;

        const LOCKED_PAYEE_TYPES = new Set([
            'Traveling Expenses',
            'PRE-Traveling Expenses',
            // Stored legacy value (label is now "... Salary")
            'Contractual Services or Job Order',
            // In case a future template key uses the new wording
            'Contractual Services or Job Order Salary',
        ]);

        function applyPayeeLocking() {
            const payeeInput = document.getElementById("payee_name");
            const typeSelect = document.getElementById("type-select");
            if (!payeeInput || !typeSelect) return;

            const selectedType = String(typeSelect.value || '').trim();
            const shouldLock = LOCKED_PAYEE_TYPES.has(selectedType);

            if (shouldLock && loggedUserName) {
                payeeInput.value = loggedUserName;
                payeeInput.setAttribute("data-default", loggedUserName);
                payeeInput.setAttribute("data-autofilled", "1");
                payeeInput.readOnly = true;
                payeeInput.style.backgroundColor = "#e9ecef";
                payeeInput.style.color = "#6c757d";
                payeeInput.style.cursor = "not-allowed";
            } else {
                const wasAutofilled = payeeInput.getAttribute("data-autofilled") === "1";
                if (wasAutofilled && payeeInput.value === (payeeInput.getAttribute("data-default") || "")) {
                    payeeInput.value = "";
                }
                payeeInput.removeAttribute("data-autofilled");
                payeeInput.readOnly = false;
                payeeInput.style.backgroundColor = "";
                payeeInput.style.color = "";
                payeeInput.style.cursor = "";
            }
        }

        function initPayeeLocking() {
            const typeSelect = document.getElementById("type-select");
            if (typeSelect) {
                typeSelect.addEventListener("change", applyPayeeLocking);
            }
            applyPayeeLocking();
        }

        // Init on page load
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', initPayeeLocking);
        } else {
            initPayeeLocking();
        }

        // Re-apply when popup opens / becomes visible
        const openPopupBtn = document.getElementById('openPopup');
        const voucherDashboardBtn = document.getElementById('voucher-dashboard-new-btn');
        const popupForm2 = document.getElementById('popupForm2');

        if (openPopupBtn || voucherDashboardBtn) {
            const triggerElement = openPopupBtn || voucherDashboardBtn;
            triggerElement.addEventListener('click', function() {
                setTimeout(applyPayeeLocking, 100);
            });
        }

        if (popupForm2) {
            const observer = new MutationObserver(function(mutations) {
                mutations.forEach(function(mutation) {
                    if (mutation.type === 'attributes' && mutation.attributeName === 'style') {
                        if (popupForm2.style.display === 'block') {
                            setTimeout(applyPayeeLocking, 100);
                        }
                    }
                });
            });
            observer.observe(popupForm2, { attributes: true, attributeFilter: ['style'] });
        }
    })();
</script>
<script>
    (function() {
        window.filterVoucherTable = function() {};
    })();
</script>
<script>
    // Function to sync voucher_type value before form submission
    function syncVoucherType() {
        var encodedTypeSelect = document.getElementById('encoded_type');
        var encodedTypeHidden = document.getElementById('encoded_type_hidden');

        // Always sync the select value to the hidden field before submission
        if (encodedTypeSelect && encodedTypeHidden) {
            encodedTypeHidden.value = encodedTypeSelect.value;
        }
        return true; // Allow form submission
    }
</script>
<script>
    // Forward Voucher: "Confirm" opens the requirements checklist modal (by voucher type).
    (function() {
        const templates = <?php echo json_encode(checklist_get_active_templates(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

        const openBtn = document.getElementById('confirm_forward_requirements');
        const modal = document.getElementById('coaOptionsModalForward');
        const overlay = document.getElementById('coa_modal_overlay_forward');
        const modalTitle = document.getElementById('coa_modal_title_forward');
        const optionsList = document.getElementById('coa_options_list_forward');
        const closeBtn = document.getElementById('close_coa_modal_forward');
        const cancelBtn = document.getElementById('coa_modal_cancel_forward');
        const saveBtn = document.getElementById('coa_modal_save_forward');
        const selectAllBtn = document.getElementById('coa_modal_select_all_forward');

        const hiddenSelected = document.getElementById('selected_coa_options_forward');
        const hiddenCategory = document.getElementById('coa_category_forward_hidden');
        const hiddenSubsection = document.getElementById('coa_subsection_forward_hidden');

        function labelNeedsExtraText(label) {
            const t = String(label || '').trim().toLowerCase();
            return t === 'etc' || t === 'etc.' || t === 'others' || t === 'other';
        }

        function attachExtraTextInput(checkbox, wrapEl, labelText) {
            if (!checkbox || !wrapEl || !labelNeedsExtraText(labelText)) return;

            const extra = document.createElement('input');
            extra.type = 'text';
            extra.className = 'coa-extra-text';
            extra.placeholder = 'Please specify';
            extra.autocomplete = 'off';
            extra.setAttribute('data-coa-extra-text', '1');
            extra.style.cssText = 'margin-left: 10px; flex: 1; min-width: 180px; padding: 6px 8px; border: 1px solid #ccc; border-radius: 4px; font-size: 12px; display: none;';
            wrapEl.appendChild(extra);

            function syncExtraVisibility() {
                const on = !!checkbox.checked;
                extra.style.display = on ? 'block' : 'none';
                extra.disabled = !on;
                if (!on) extra.value = '';
            }
            checkbox.addEventListener('change', syncExtraVisibility);
            syncExtraVisibility();
        }

        function getCurrentVoucherType() {
            const vt = (document.getElementById('voucher_type')?.value ||
                document.getElementById('encoded_type_hidden')?.value ||
                document.getElementById('encoded_type')?.value ||
                '').toString().trim();
            return vt;
        }

        function closeModal() {
            if (modal) modal.style.display = 'none';
            if (overlay) overlay.style.display = 'none';
        }

        function openModal() {
            const voucherType = getCurrentVoucherType();
            if (!voucherType) {
                if (typeof showNotify === 'function') showNotify('Please select a voucher type first.', 'warning', 3000);
                return;
            }

            const t = templates[voucherType] || {
                title: 'SUPPORTING DOCUMENTS',
                items: ['Obligation Request and Status', 'Disbursement Voucher', 'Supporting documents as per checklist', 'Others']
            };

            if (modalTitle) {
                modalTitle.textContent = 'Select COA Requirements - ' + (t.title || voucherType);
            }

            if (optionsList) {
                optionsList.innerHTML = '';

                const header = document.createElement('div');
                header.style.cssText = 'padding: 10px 12px; background-color: #f8f9fa; font-weight: bold; color: #333; border-bottom: 2px solid #667eea;';
                header.textContent = (t.title || voucherType);
                optionsList.appendChild(header);

                const items = Array.isArray(t.items) ? t.items : [];
                if (items.length === 0) {
                    const empty = document.createElement('p');
                    empty.style.cssText = 'padding: 20px; text-align: center; color: #666;';
                    empty.textContent = 'No checklist configured for this voucher type.';
                    optionsList.appendChild(empty);
                } else {
                    function parseChecklistItem(raw) {
                        if (raw && typeof raw === 'object' && !Array.isArray(raw) && raw.label) {
                            const subs = Array.isArray(raw.subitems)
                                ? raw.subitems.map(function(s) { return String(s || '').trim(); }).filter(Boolean)
                                : [];
                            return { label: String(raw.label || '').trim(), subitems: subs };
                        }
                        return { label: String(raw || '').trim(), subitems: [] };
                    }
                    items.forEach((raw, idx) => {
                        const meta = parseChecklistItem(raw);
                        const itemLabel = meta.label;
                        if (!itemLabel) return;

                        const row = document.createElement('div');
                        row.style.cssText = 'display: block; padding: 10px 12px; border-bottom: 1px solid #eee; cursor: pointer;';
                        const inner = document.createElement('div');
                        inner.style.cssText = 'display: flex; align-items: center; gap: 8px;';
                        const checkbox = document.createElement('input');
                        checkbox.type = 'checkbox';
                        checkbox.name = 'coa_options_checklist_forward[]';
                        checkbox.value = itemLabel;
                        checkbox.setAttribute('data-id', String(idx + 1));
                        const span = document.createElement('span');
                        span.textContent = itemLabel;
                        inner.appendChild(checkbox);
                        inner.appendChild(span);
                        attachExtraTextInput(checkbox, inner, itemLabel);
                        row.appendChild(inner);
                        // Allow clicking on the main row text to toggle its checkbox
                        row.addEventListener('click', function(e) {
                            const t = e.target;
                            if (t && t.closest && t.closest('.coa-subitem-row')) return; // ignore sub-items
                            if (t && t.tagName === 'INPUT') return;
                            checkbox.checked = !checkbox.checked;
                            checkbox.dispatchEvent(new Event('change', { bubbles: true }));
                        });
                        if (meta.subitems.length) {
                            const subWrap = document.createElement('div');
                            subWrap.style.cssText = 'margin: 6px 0 0 28px; display: flex; flex-direction: column; gap: 2px;';
                            const subCheckboxes = [];
                            meta.subitems.forEach(function(s, subIdx) {
                                const subRow = document.createElement('div');
                                subRow.className = 'coa-subitem-row';
                                subRow.style.cssText = 'display: flex; align-items: center; gap: 8px; font-size: 12px; color: #444;';
                                const subCheckbox = document.createElement('input');
                                subCheckbox.type = 'checkbox';
                                subCheckbox.name = 'coa_options_checklist_forward[]';
                                subCheckbox.value = String(s || '').trim();
                                subCheckbox.setAttribute('data-id', String(idx + 1) + '-' + String(subIdx + 1));
                                const subSpan = document.createElement('span');
                                subSpan.textContent = String(s || '').trim();
                                subRow.appendChild(subCheckbox);
                                subRow.appendChild(subSpan);
                                attachExtraTextInput(subCheckbox, subRow, String(s || '').trim());
                                subWrap.appendChild(subRow);
                                subCheckboxes.push(subCheckbox);
                            });
                            row.appendChild(subWrap);

                            // Parent follows sub-options: checked if at least one sub is checked (one is enough).
                            // Unchecking parent clears all subs; checking parent with none selected ticks the first sub only.
                            function syncParentFromSubs() {
                                checkbox.checked = subCheckboxes.some(function(cb) {
                                    return cb.checked;
                                });
                            }
                            checkbox.addEventListener('change', function() {
                                const on = checkbox.checked;
                                if (!on) {
                                    subCheckboxes.forEach(function(cb) {
                                        cb.checked = false;
                                    });
                                } else if (!subCheckboxes.some(function(cb) {
                                    return cb.checked;
                                }) && subCheckboxes.length) {
                                    subCheckboxes[0].checked = true;
                                }
                            });
                            subCheckboxes.forEach(function(cb) {
                                cb.addEventListener('change', syncParentFromSubs);
                            });
                            syncParentFromSubs();
                        }
                        optionsList.appendChild(row);
                    });
                }
            }

            // Reset Select-all button label
            if (selectAllBtn) selectAllBtn.textContent = 'Select all';

            // Store the "selection path" in hidden fields for the backend (no UI for these).
            if (hiddenCategory) hiddenCategory.value = voucherType;
            if (hiddenSubsection) hiddenSubsection.value = voucherType;

            if (modal) modal.style.display = 'block';
            if (overlay) overlay.style.display = 'block';
        }

        if (openBtn) openBtn.addEventListener('click', openModal);
        if (closeBtn) closeBtn.addEventListener('click', closeModal);
        if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
        if (overlay) overlay.addEventListener('click', closeModal);

        if (selectAllBtn) {
            selectAllBtn.addEventListener('click', function() {
                if (!optionsList) return;
                const checkboxes = Array.from(optionsList.querySelectorAll('input[type="checkbox"][name="coa_options_checklist_forward[]"]'));
                if (checkboxes.length === 0) return;
                const allChecked = checkboxes.every(cb => cb.checked);
                checkboxes.forEach(cb => {
                    cb.checked = !allChecked;
                    cb.dispatchEvent(new Event('change', { bubbles: true }));
                });
                selectAllBtn.textContent = allChecked ? 'Select all' : 'Unselect all';
            });
        }

        if (saveBtn) {
            saveBtn.addEventListener('click', function() {
                if (!optionsList || !hiddenSelected) return;
                const allCheckboxes = Array.from(optionsList.querySelectorAll('input[type="checkbox"][name="coa_options_checklist_forward[]"]'));
                const checked = allCheckboxes.filter(cb => cb.checked);

                // COA options are not all mandatory; require at least one confirmed selection.
                if (checked.length === 0) {
                    if (typeof showNotify === 'function') showNotify('Please select at least one requirement.', 'warning', 3000);
                    return;
                }

                const selectedOptions = checked.map(cb => ({
                    id: cb.getAttribute('data-id'),
                    value: (function() {
                        const base = cb.parentElement.querySelector('span')?.textContent || cb.value;
                        const extra = cb.parentElement.querySelector('input[type="text"][data-coa-extra-text="1"]');
                        const extraText = String(extra?.value || '').trim();
                        return extraText ? (String(base || '').trim() + ' - ' + extraText) : String(base || '').trim();
                    })(),
                    label: (function() {
                        const base = cb.parentElement.querySelector('span')?.textContent || cb.value;
                        const extra = cb.parentElement.querySelector('input[type="text"][data-coa-extra-text="1"]');
                        const extraText = String(extra?.value || '').trim();
                        return extraText ? (String(base || '').trim() + ' - ' + extraText) : String(base || '').trim();
                    })()
                }));

                hiddenSelected.value = JSON.stringify(selectedOptions);
                hiddenSelected.dispatchEvent(new Event('change', { bubbles: true }));
                closeModal();
            });
        }

        // Prevent forwarding if requirements were not confirmed.
        const forwardForm = document.getElementById('encoded_voucher_form');
        if (forwardForm) {
            forwardForm.addEventListener('submit', function(e) {
                const raw = String(hiddenSelected?.value || '').trim();
                if (!raw) {
                    e.preventDefault();
                    if (typeof showNotify === 'function') showNotify('Please click CONFIRM and select the required checklist items before forwarding.', 'error', 3500);
                    return false;
                }
                return true;
            });
        }
    })();
</script>

<script>
    // DV print: signatory selection modal before window.print()
    (function() {
        const modal = document.getElementById('signatoryModal');
        const overlay = document.getElementById('signatory_modal_overlay');
        const closeBtn = document.getElementById('close_signatory_modal');
        const cancelBtn = document.getElementById('signatory_modal_cancel');
        const printBtn = document.getElementById('signatory_modal_print');
        const certSelect = document.getElementById('dv_sig_cert_select');
        const accountingSelect = document.getElementById('dv_sig_accounting_select');
        const approvedSelect = document.getElementById('dv_sig_approved_select');

        function getSigCfg() {
            return window.DV_SIGNATORY || {};
        }

        function populateSelect(selectEl, roleKeys, labels, defaultKey) {
            if (!selectEl) return;
            selectEl.innerHTML = '';
            const cfg = getSigCfg();
            const byKey = cfg.optionsByKey || {};
            const keys = Array.isArray(roleKeys) ? roleKeys : [];
            let hasDefault = false;

            keys.forEach(function(key) {
                const optData = byKey[key];
                if (!optData) return;
                const option = document.createElement('option');
                option.value = key;
                const label = (labels && labels[key]) ? labels[key] : key;
                option.textContent = optData.name ? (optData.name + ' — ' + label) : label;
                if (defaultKey && key === defaultKey) {
                    option.selected = true;
                    hasDefault = true;
                }
                selectEl.appendChild(option);
            });

            if (!hasDefault && selectEl.options.length > 0) {
                selectEl.selectedIndex = 0;
            }
        }

        function closeSignatoryModal() {
            if (modal) modal.style.display = 'none';
            if (overlay) overlay.style.display = 'none';
        }

        function openSignatoryModal() {
            const cfg = getSigCfg();
            const roles = cfg.roles || {};
            const labels = cfg.labels || {};

            populateSelect(certSelect, roles.cert, labels, cfg.defaultCertKey || '');
            populateSelect(accountingSelect, roles.accounting, labels, roles.accounting && roles.accounting[0] ? roles.accounting[0] : '');
            populateSelect(approvedSelect, roles.approved, labels, roles.approved && roles.approved[0] ? roles.approved[0] : '');

            const anyOptions = (certSelect && certSelect.options.length) ||
                (accountingSelect && accountingSelect.options.length) ||
                (approvedSelect && approvedSelect.options.length);

            if (!anyOptions) {
                if (typeof showNotify === 'function') {
                    showNotify('No signatories configured. Set them up in Utilities first.', 'warning', 3500);
                }
                return;
            }

            if (modal) modal.style.display = 'block';
            if (overlay) overlay.style.display = 'block';
        }

        function validateSelections() {
            if (!certSelect || !certSelect.value) {
                if (typeof showNotify === 'function') showNotify('Please select the A. Certified signatory.', 'warning', 2800);
                return false;
            }
            if (!accountingSelect || !accountingSelect.value) {
                if (typeof showNotify === 'function') showNotify('Please select the C. Certified (Accounting) signatory.', 'warning', 2800);
                return false;
            }
            if (!approvedSelect || !approvedSelect.value) {
                if (typeof showNotify === 'function') showNotify('Please select the D. Approved for Payment signatory.', 'warning', 2800);
                return false;
            }
            return true;
        }

        function proceedToPrint() {
            if (!validateSelections()) return;
            if (typeof buildSignatorySelection !== 'function' || typeof applyDvSignatories !== 'function') {
                window.print();
                return;
            }
            const selection = buildSignatorySelection(
                certSelect.value,
                accountingSelect.value,
                approvedSelect.value
            );
            applyDvSignatories(selection);
            closeSignatoryModal();
            window.print();
        }

        window.openSignatoryModal = openSignatoryModal;

        if (closeBtn) closeBtn.addEventListener('click', closeSignatoryModal);
        if (cancelBtn) cancelBtn.addEventListener('click', closeSignatoryModal);
        if (overlay) overlay.addEventListener('click', closeSignatoryModal);
        if (printBtn) printBtn.addEventListener('click', proceedToPrint);
    })();
</script>
<script>
    // Expose logged user name to external scripts (safe JSON encoding)
    window.__loggedUserEmpName = <?php echo json_encode($_SESSION['logged_user_emp_name'] ?? $logged_user_name ?? ''); ?>;
</script>
<script src="../../protected/js/forward_slip.js"></script>
</body>

</html>