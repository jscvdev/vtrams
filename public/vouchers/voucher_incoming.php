<?php
include('../includes/header.php');
require_once __DIR__ . '/../../protected/core/components/helpers/audit_helper.inc.php';
AuditHelper::logPageView('Incoming');
include('../../protected/handler/voucher_incoming_module/voucher_incoming_errhandler.inc.php');
include('../../protected/handler/voucher_return_module/voucher_return_errhandler.inc.php');
require '../../protected/core/components/notifications/err_handler_custom_alert.php';
require_once __DIR__ . '/../../protected/core/components/notifications/custom_alert.php';
require_once __DIR__ . '/../../protected/core/components/notifications/notification.inc.php';
check_voucher_incoming_errors();
check_voucher_return_errors();

require_once __DIR__ . '/checklist_config.php';
require_once __DIR__ . '/../../protected/core/components/security/filter_input.inc.php';
require_once __DIR__ . '/../../protected/core/components/helpers/cursor_pagination_helper.php';
require_once __DIR__ . '/../../protected/core/components/helpers/voucher_portal_query_helper.php';
require_once __DIR__ . '/../../protected/core/components/helpers/utilities_return_previous_helper.inc.php';
require_once __DIR__ . '/../../protected/core/components/helpers/voucher_tracking_helper.inc.php';
require_once __DIR__ . '/../../protected/core/components/helpers/amount_helper.inc.php';
utilities_return_previous_ensure_schema($pdo);
$return_previous_allowed_units = utilities_return_previous_active_designations($pdo);
$dashboard_voucher_types = checklist_types_with_labels();
$voucher_type_filter = isset($_GET['voucher_type']) && $_GET['voucher_type'] !== 'all' ? trim((string) $_GET['voucher_type']) : 'all';

$rowsPerPage = clamp_int($_GET['rowsPerPage'] ?? null, 1, 50, 50);
$maxBrowse = 100;
$rawQ = (string) ($_GET['q'] ?? '');
$q = filterInput($rawQ);
$invalidSearch = (trim($rawQ) !== '' && $q === '');

$incomingSearchCols = [
    'processing_no',
    'ors_no',
    'dv_no',
    'ada_check_no',
    'payee',
    'address',
    'particulars',
    'tin_employee_no',
    'voucher_type',
    'remarks',
    'sender_remarks',
    'datetime_forwarded',
    'datetime_encoded',
    'sender_udc',
    'receiver_udc',
    'encoded_by',
    'encoded_from',
    'forwarded_by',
    'process_status',
    'process_history',
    'coa_options',
    'coa_category',
    'coa_subsection',
    'supporting_documents',
];

$searchParams = [];
$searchSql = '';
if (!$invalidSearch && $q !== '') {
    $searchSql = voucher_portal_like_search_fragment($pdo, 'voucher_incoming', $q, $incomingSearchCols, $searchParams);
}

$udc_param = '%' . $_SESSION['logged_user_udc'] . '%';

if ($invalidSearch) {
    $dbCount = 0;
} else {
    $countSql = 'SELECT COUNT(*) AS total FROM voucher_incoming WHERE receiver_udc LIKE :udc AND office_to = :office_to';
    if ($voucher_type_filter !== 'all') {
        $countSql .= ' AND voucher_type = :voucher_type';
    }
    $countSql .= $searchSql;
    $stmtCount = $pdo->prepare($countSql);
    $stmtCount->bindValue(':udc', $udc_param, PDO::PARAM_STR);
    $stmtCount->bindValue(':office_to', $_SESSION['logged_user_office'], PDO::PARAM_STR);
    if ($voucher_type_filter !== 'all') {
        $stmtCount->bindValue(':voucher_type', $voucher_type_filter, PDO::PARAM_STR);
    }
    foreach ($searchParams as $key => $pair) {
        $stmtCount->bindValue($key, $pair[0], $pair[1]);
    }
    $stmtCount->execute();
    $dbCount = (int) $stmtCount->fetch(PDO::FETCH_ASSOC)['total'];
}

$displayTotal = min($dbCount, $maxBrowse);
$totalPages = $displayTotal > 0 ? (int) ceil($displayTotal / $rowsPerPage) : 1;
$currentPage = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$currentPage = max(1, min($currentPage, $totalPages));
$offset = ($currentPage - 1) * $rowsPerPage;
$fetchLimit = $displayTotal > 0 ? min($rowsPerPage, max(0, $maxBrowse - $offset)) : 0;

$dataSql = 'SELECT * FROM voucher_incoming WHERE receiver_udc LIKE :udc AND office_to = :office_to';
if ($voucher_type_filter !== 'all') {
    $dataSql .= ' AND voucher_type = :voucher_type';
}
$dataSql .= $searchSql . ' ORDER BY processing_no DESC LIMIT :lim OFFSET :off';
$fetch_voucher_incoming_data = $pdo->prepare($dataSql);
$fetch_voucher_incoming_data->bindValue(':udc', $udc_param, PDO::PARAM_STR);
$fetch_voucher_incoming_data->bindValue(':office_to', $_SESSION['logged_user_office'], PDO::PARAM_STR);
if ($voucher_type_filter !== 'all') {
    $fetch_voucher_incoming_data->bindValue(':voucher_type', $voucher_type_filter, PDO::PARAM_STR);
}
foreach ($searchParams as $key => $pair) {
    $fetch_voucher_incoming_data->bindValue($key, $pair[0], $pair[1]);
}
$fetch_voucher_incoming_data->bindValue(':lim', $fetchLimit, PDO::PARAM_INT);
$fetch_voucher_incoming_data->bindValue(':off', $offset, PDO::PARAM_INT);
$fetch_voucher_incoming_data->execute();
$incomingRows = $fetch_voucher_incoming_data->fetchAll(PDO::FETCH_ASSOC);
$incomingHistoryMap = voucher_tracking_fetch_display_history_map(
    $pdo,
    array_column($incomingRows, 'processing_no')
);

$totalRows = $displayTotal;

$target = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) ($_SESSION['logged_user_designation'] ?? ''))
)));
$isLiaisonOfficer = in_array('Liaison Officer', $target, true);
$bulkReceiveToken = (string) ($_SESSION['token'] ?? '');
$incoming_logged_user_office = voucher_logged_user_office();
$incoming_is_accounting_role = in_array('Accounting Unit', $target, true)
    || in_array('Processor', $target, true)
    || in_array('Accountant III', $target, true);

?>
<!--=============== MAIN ===============!-->
<div class="main main--voucher-dashboard" id="main">
    <header class="voucher-dashboard-header">
        <h1 class="voucher-dashboard-title">Incoming</h1>
    </header>
    <style>
        /* Keep incoming filter toolbar in one row */
        #incomingFilterForm {
            display: flex;
            align-items: center;
            flex-wrap: nowrap !important;
            width: 100%;
            gap: 10px;
        }

        #incomingFilterForm .filter-chips {
            flex: 0 0 auto;
            flex-wrap: nowrap !important;
        }

        #incomingFilterForm .filter-search {
            flex: 1 1 auto;
            min-width: 0 !important;
        }

        /* Modernized voucher type dropdown (matches current UI sample) */
        #incomingFilterForm .filter-type-select.filter-type-select--modern {
            position: relative;
            display: inline-flex;
            align-items: center;
            min-width: 280px;
            height: 42px;
            padding: 0;
            border: 1px solid #d4dbe6;
            border-radius: 12px;
            background: #fff;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
            overflow: visible;
            z-index: 20;
        }

        #incomingFilterForm .filter-type-select.filter-type-select--modern::after {
            content: "";
            position: absolute;
            right: 14px;
            top: 50%;
            width: 8px;
            height: 8px;
            border-right: 2px solid #6b7280;
            border-bottom: 2px solid #6b7280;
            transform: translateY(-60%) rotate(45deg);
            pointer-events: none;
        }

        #incomingFilterForm .filter-type-select.filter-type-select--modern select {
            width: 100%;
            height: 100%;
            border: none;
            outline: none;
            padding: 0 34px 0 14px;
            background: transparent;
            color: #1f2937;
            font-size: 14px;
            font-weight: 500;
            line-height: 1.25;
            cursor: pointer;
            text-align: left;
            appearance: none;
            -webkit-appearance: none;
            -moz-appearance: none;
        }

        #incomingFilterForm .filter-type-select.filter-type-select--modern.filter-type-select--custom::after {
            transform: translateY(-60%) rotate(45deg);
            transition: transform 120ms ease;
        }

        #incomingFilterForm .filter-type-select.filter-type-select--modern.filter-type-select--custom.is-open::after {
            transform: translateY(-35%) rotate(-135deg);
        }

        #incomingFilterForm .filter-type-select.filter-type-select--modern.filter-type-select--custom .filter-type-trigger {
            width: 100%;
            height: 100%;
            border: none;
            background: transparent;
            text-align: left;
            padding: 0 34px 0 14px;
            color: #1f2937;
            font-size: 14px;
            font-weight: 500;
            line-height: 1.25;
            cursor: pointer;
        }

        #incomingFilterForm .filter-type-select.filter-type-select--modern.filter-type-select--custom .filter-type-menu {
            position: absolute;
            top: calc(100% + 8px);
            left: 0;
            right: 0;
            z-index: 30;
            max-height: 280px;
            overflow-y: auto;
            padding: 6px;
            border-radius: 12px;
            border: 1px solid #d7dee8;
            background: #ffffff;
            box-shadow: 0 12px 28px rgba(15, 23, 42, 0.14);
            display: none;
        }

        #incomingFilterForm .filter-type-select.filter-type-select--modern.filter-type-select--custom.is-open .filter-type-menu {
            display: block;
        }

        #incomingFilterForm .filter-type-select.filter-type-select--modern.filter-type-select--custom .filter-type-option {
            width: 100%;
            border: none;
            background: transparent;
            border-radius: 8px;
            padding: 9px 10px;
            text-align: left;
            font-size: 14px;
            line-height: 1.2;
            color: #1f2937;
            cursor: pointer;
        }

        #incomingFilterForm .filter-type-select.filter-type-select--modern.filter-type-select--custom .filter-type-option:hover {
            background: #f3f6fb;
        }

        #incomingFilterForm .filter-type-select.filter-type-select--modern.filter-type-select--custom .filter-type-option.is-active {
            background: #e8f0ff;
            color: #1d4ed8;
            font-weight: 600;
        }

        #incomingFilterForm .filter-type-select.filter-type-select--modern:hover {
            border-color: #c2ccda;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
        }

        #incomingFilterForm .filter-type-select.filter-type-select--modern:focus-within {
            border-color: #8fb2ff;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
        }

        /* Row burger menu (View / Status Report History) */
        .voucher-row-menu-cell {
            width: 44px;
            padding-left: 4px !important;
            padding-right: 4px !important;
            text-align: center;
            vertical-align: middle;
        }

        .voucher-row-menu {
            position: relative;
            display: inline-flex;
            justify-content: center;
            align-items: center;
        }

        .voucher-row-menu-trigger {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 36px;
            height: 36px;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            background: linear-gradient(180deg, #ffffff 0%, #f8fafc 100%);
            color: #4b5563;
            cursor: pointer;
            box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
            transition: background 120ms ease, border-color 120ms ease, box-shadow 120ms ease, color 120ms ease, transform 120ms ease;
        }

        .voucher-row-menu-trigger:hover,
        .voucher-row-menu.is-open .voucher-row-menu-trigger {
            background: linear-gradient(180deg, #f8fbff 0%, #eef4ff 100%);
            border-color: #c7d7fe;
            color: #1d4ed8;
            box-shadow: 0 4px 12px rgba(59, 130, 246, 0.16);
            transform: translateY(-1px);
        }

        .voucher-row-menu-trigger i {
            font-size: 18px;
            line-height: 1;
        }

        .voucher-row-menu-dropdown {
            position: fixed;
            z-index: 501;
            width: 128px;
            min-width: 128px;
            max-width: 128px;
            padding: 4px;
            border: 1px solid #e5e7eb;
            border-radius: 10px;
            background: #fff;
            box-shadow: 0 12px 28px rgba(15, 23, 42, 0.14);
            display: none;
            box-sizing: border-box;
        }

        .voucher-row-menu-dropdown.is-open {
            display: block;
        }

        .voucher-row-menu-dropdown .voucher-row-menu-item.btn {
            width: 100% !important;
            min-width: 0 !important;
            min-height: 32px;
            margin: 0;
            padding: 6px 8px !important;
            border: none;
            border-radius: 6px;
            background: transparent;
            color: #374151;
            font-size: 12px;
            font-weight: 600;
            letter-spacing: 0.01em;
            display: inline-flex !important;
            align-items: center;
            justify-content: flex-start;
            gap: 8px;
            box-shadow: none;
            box-sizing: border-box;
            transition: background 120ms ease, color 120ms ease;
        }

        .voucher-row-menu-dropdown .voucher-row-menu-item.btn i {
            width: 16px;
            flex: 0 0 16px;
            font-size: 15px;
            color: #6b7280;
            line-height: 1;
            text-align: center;
        }

        .voucher-row-menu-dropdown .voucher-row-menu-item.btn span {
            flex: 1 1 auto;
            line-height: 1.2;
            text-align: left;
        }

        .voucher-row-menu-dropdown .voucher-row-menu-item.btn:hover {
            background: #f3f6fb;
            color: #1d4ed8;
        }

        .voucher-row-menu-dropdown .voucher-row-menu-item.btn:hover i {
            color: #2563eb;
        }

        .voucher-row-menu-dropdown .voucher-row-menu-link {
            display: flex;
            align-items: center;
            justify-content: flex-start;
            gap: 8px;
            width: 100%;
            min-height: 32px;
            border: none;
            border-radius: 6px;
            padding: 6px 8px;
            box-sizing: border-box;
            font-size: 12px;
            font-weight: 600;
            line-height: 1.2;
            letter-spacing: 0.01em;
            color: #374151;
            text-decoration: none;
            background: transparent;
            cursor: pointer;
            transition: background 120ms ease, color 120ms ease;
        }

        .voucher-row-menu-dropdown .voucher-row-menu-link i {
            width: 16px;
            flex: 0 0 16px;
            font-size: 15px;
            color: #6b7280;
            line-height: 1;
            text-align: center;
        }

        .voucher-row-menu-dropdown .voucher-row-menu-link span {
            flex: 1 1 auto;
            text-align: left;
        }

        .voucher-row-menu-dropdown .voucher-row-menu-link:hover {
            background: #f3f6fb;
            color: #1d4ed8;
        }

        .voucher-row-menu-dropdown .voucher-row-menu-link:hover i {
            color: #2563eb;
        }
    </style>
    <div class="voucher-card voucher-card--filter">
        <div class="filter-toolbar">
            <div class="filter-left">
                <form method="GET" action="" id="incomingFilterForm" class="filter-toolbar-form">
                    <div class="filter-chips" aria-label="Voucher filter tools">
                        <a class="filter-icon-btn" href="voucher_incoming.php" aria-label="Home">
                        </a>
                        <button type="button" class="filter-icon-btn" aria-label="Copy">
                        </button>
                        <label class="filter-type-select filter-type-select--modern filter-type-select--custom" id="filterTypeDropdown" aria-label="Filter by voucher type">
                            <?php
                            $active_type_label = 'All Types';
                            foreach ($dashboard_voucher_types as $type_value => $type_label) {
                                if ($voucher_type_filter === (string) $type_value) {
                                    $active_type_label = (string) $type_label;
                                    break;
                                }
                            }
                            ?>
                            <input type="hidden" name="voucher_type" id="filterInputType" value="<?= htmlspecialchars((string) $voucher_type_filter, ENT_QUOTES, 'UTF-8') ?>">
                            <button type="button" class="filter-type-trigger" id="filterTypeTrigger" aria-haspopup="listbox" aria-expanded="false">
                                <?= htmlspecialchars((string) $active_type_label, ENT_QUOTES, 'UTF-8') ?>
                            </button>
                            <div class="filter-type-menu" id="filterTypeMenu" role="listbox" aria-label="Voucher type options">
                                <button type="button" class="filter-type-option <?= $voucher_type_filter === 'all' ? 'is-active' : '' ?>" data-value="all">All Types</button>
                                <?php foreach ($dashboard_voucher_types as $type_value => $type_label): ?>
                                    <button
                                        type="button"
                                        class="filter-type-option <?= $voucher_type_filter === (string) $type_value ? 'is-active' : '' ?>"
                                        data-value="<?= htmlspecialchars((string) $type_value, ENT_QUOTES, 'UTF-8') ?>">
                                        <?= htmlspecialchars((string) $type_label, ENT_QUOTES, 'UTF-8') ?>
                                    </button>
                                <?php endforeach; ?>
                            </div>
                        </label>
                    </div>

                    <div class="filter-search">
                        <input type="text" id="filterInput" name="q" value="<?php echo htmlspecialchars($rawQ, ENT_QUOTES, 'UTF-8'); ?>" placeholder="search" autocomplete="off">
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="popup-form voucher-premium-modal popup-form--compact" id="popupForm">
        <div class="popupForm-box__container">
            <div class="popupForm-header__container">
                <p id="form_title">Forward Document</p>
                <i class="ri-close-fill close-icon" id="close_popup4"></i>
            </div>
            <form action="" class="f-container" method="post" id="myIncomingForm">
                <div class="box-body__container flex-row">
                    <div class="popupForm-body__container">
                        <div class="form-container">
                            <div class="label-input__container">
                                <label for="">Processing No.</label>
                                <input type="text" name="processing_no" class="processing_no form-custom-input" id="processing_no" value="" placeholder="Processing No." required readonly>
                            </div>
                            <div class="label-input__container" id="selected-coa-options-container" style="display: none;">
                                <label for="">Selected COA Requirements</label>
                                <button type="button" id="view_coa_requirements_btn" class="btn primary" style="width: 100%; padding: 10px; font-weight: bold;">View Selected Requirements</button>
                                <p style="font-size: 0.85em; color: #666; margin-top: 5px;">as per coa-circular-no.-2023-004-June-14-2023</p>
                            </div>
                            <div class="label-input__container">
                                <label for="">ORS No.</label>
                                <input type="text" name="ors_no" class="ors_no form-custom-input" id="ors_no" value="" placeholder="ORS No." readonly>
                            </div>
                            <div class="label-input__container">
                                <label for="">ADA/Check No.</label>
                                <input type="text" name="ada_check_no" class="ada_check_no form-custom-input" id="ada_check_no" value="" placeholder="ADA/Check No." readonly>
                            </div>
                            <div class="label-input__container">
                                <label for="">DV No.</label>
                                <input type="text" name="dv_no" class="dv_no form-custom-input" id="dv_no" value="" placeholder="DV No." readonly>
                            </div>

                        </div>
                    </div>
                    <div class="popupForm-body__container">
                        <div class="form-container">
                            <div class="label-input__container">
                                <label for="">Payee</label>
                                <input type="text" name="payee" class="payee form-custom-input" id="payee" value="" placeholder="Payee" required readonly>
                            </div>
                            <div class="label-input__container">
                                <label for="">Address</label>
                                <input type="text" name="address" class="address form-custom-input" id="address" value="" placeholder="Address" required readonly>
                            </div>
                            <div class="label-input__container">
                                <label for="">Particulars</label>
                                <textarea name="particulars" id="particulars" cols="30" rows="10" class="multi-line-input particulars form-custom-multi-input" placeholder="Particulars ...." required readonly></textarea>
                            </div>
                            <div class="label-input__container">
                                <label for="">TIN/Employee No.</label>
                                <input type="text" name="tin_employee_no" class="tin_employee_no form-custom-input" id="tin_employee_no" value="" placeholder="TIN/Employee No." readonly>
                            </div>
                            <div class="label-input__container number-input amount_primary_block">
                                <label for="" class="amount_main_label">Amount</label>
                                <input type="text" name="string_amount" class="string_amount form-custom-input amount_main_display" id="string_amount" placeholder="Amount" required readonly>
                                <input type="hidden" name="amount" class="amount" id="int_amount" value="1">
                                <input type="hidden" name="gross_amount" id="gross_amount" value="">
                                <div class="voucher-amount-split-panel" id="voucherAmountSplitPanel" style="display: none;">
                                    <p class="voucher-amount-split-panel__title">Amount</p>
                                    <div class="voucher-amount-split-panel__body">
                                        <div class="voucher-amount-split-field voucher-amount-split-field--gross original_charged_container" style="display: none;">
                                            <label for="original_string_amount" class="voucher-amount-split-field__label">Gross</label>
                                            <input type="text" name="original_string_amount" class="original_string_amount form-custom-input voucher-amount-split-field__input" id="original_string_amount" placeholder="0.00" readonly>
                                        </div>
                                        <div class="voucher-amount-split-field voucher-amount-split-field--net charged_amount_container" style="display: none;">
                                            <label for="charged_string_amount" class="voucher-amount-split-field__label">Net</label>
                                            <input type="text" name="charged_string_amount" class="charged_string_amount form-custom-input voucher-amount-split-field__input" id="charged_string_amount" placeholder="0.00" readonly>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="label-input__container">
                                <label for="">Voucher Date</label>
                                <input type="date" name="voucher_date" class="voucher_date form-custom-input" id="voucher_date" value="" required readonly>
                            </div>
                            <div class="label-input__container">
                                <label for="">Sender Remarks</label>
                                <input type="text" name="sender_remarks" class="sender_remarks form-custom-input" id="sender_remarks" value="" placeholder="Sender Remarks" readonly>
                            </div>
                            <div class="label-input__container remarks_container hidden_input">
                                <label for="">Remarks</label>
                                <input type="text" name="remarks" class="remarks form-custom-input" id="remarks" value="" placeholder="Remarks">
                            </div>
                            <div class="label-input__container hidden_input">
                                <label for="">Combined Remarks</label>
                                <input type="text" name="combined_remarks" class="combined_remarks form-custom-input" id="combined_remarks" value="" placeholder="Combined Remarks" readonly>
                            </div>
                            <div class="label-input__container hidden_input">
                                <label for="">Office From</label>
                                <input type="text" name="office_from" class="office_from" id="office_from" value="">
                            </div>
                            <div class="label-input__container hidden_input">
                                <label for="">Office To</label>
                                <input type="text" name="office_to" class="office_to" id="office_to" value="">
                            </div>
                            <div class="label-input__container hidden_input">
                                <label for="">SUDC</label>
                                <input type="text" name="sender_udc" class="sender_udc" id="sender_udc" value="">
                            </div>
                            <div class="label-input__container hidden_input">
                                <label for="">RUDC</label>
                                <input type="text" name="receiver_udc" class="receiver_udc" id="receiver_udc" value="">
                            </div>
                            <div class="label-input__container hidden_input">
                                <label for="">Encoded By</label>
                                <input type="text" name="encoded_by" class="encoded_by" id="encoded_by" value="">
                            </div>
                            <div class="label-input__container hidden_input">
                                <label for="">Forwarded By</label>
                                <input type="text" name="forwarded_by" class="forwarded_by" id="forwarded_by" value="">
                            </div>
                            <div class="label-input__container hidden_input">
                                <label for="">Encoded From</label>
                                <input type="text" name="encoded_from" class="encoded_from" id="encoded_from" value="">
                            </div>
                            <div class="label-input__container hidden_input">
                                <label for="">Datetime Encoded</label>
                                <input type="text" name="datetime_encoded" class="datetime_encoded" id="datetime_encoded" value="">
                            </div>
                            <div class="label-input__container hidden_input">
                                <label for="">Process Status</label>
                                <input type="text" name="process_status" class="process_status" id="process_status" value="">
                            </div>
                            <div class="label-input__container hidden_input">
                                <label for="">Process History</label>
                                <textarea name="process_history" class="process_history form-custom-input" id="process_history" rows="4" style="display:none;"></textarea>
                            </div>
                            <div class="label-input__container hidden_input">
                                <label for="">Voucher Type</label>
                                <input type="text" name="voucher_type" class="voucher_type" id="voucher_type" value="">
                            </div>
                            <div class="label-input__container hidden_input">
                                <label for="">Selected COA Options</label>
                                <input type="text" name="selected_coa_options" class="selected_coa_options" id="selected_coa_options" value="">
                            </div>
                            <div class="label-input__container hidden_input">
                                <label for="">COA Category</label>
                                <input type="text" name="coa_category" class="coa_category" id="coa_category" value="">
                            </div>
                            <div class="label-input__container hidden_input">
                                <label for="">COA Subsection</label>
                                <input type="text" name="coa_subsection" class="coa_subsection" id="coa_subsection" value="">
                            </div>
                            <input type="hidden" name="return_destination" id="return_destination" value="">
                            <input type="hidden" name="return_target_section" id="return_target_section" value="">
                            <input type="hidden" name="retract_source" id="retract_source" value="incoming">
                            <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
                        </div>
                    </div>
                </div>
                <div class="popupForm-footer__container">
                    <div class="footer-button__container">
                        <?php if ($_SESSION['acl'] != 888) : ?>
                            <button class="btn transparent btn-dynamic" name="" type="submit"></button>
                        <?php endif; ?>
                        <!-- Hidden submit button used for return flow so that `return_voucher` is present in POST -->
                        <button type="submit" name="return_voucher" id="hidden_return_submit" style="display:none;"></button>
                        <button type="submit" name="retract_voucher" id="hidden_retract_submit" style="display:none;"></button>
                        <button class="btn secondary transparent" id="close_popup3" type="button">CANCEL</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <div class="overlay voucher-premium-overlay" id="overlay"></div>

    <!-- Return Options Popup (triggered by clicking Return button) -->
    <div class="popup-form voucher-premium-modal popup-form--compact" id="returnOptionsPopup" style="display: none;">
        <div class="popupForm-box__container">
            <div class="popupForm-header__container">
                <p>Return Voucher</p>
                <i class="ri-close-fill close-icon" id="close_return_options"></i>
            </div>
            <div class="f-container">
                <div class="box-body__container flex-row">
                    <div class="popupForm-body__container">
                        <div class="form-container">
                            <div class="label-input__container">
                                <label for="">Return to</label>
                                <div class="return-destination-options" style="display: flex; flex-direction: column; gap: 8px; margin-top: 6px;">
                                    <label id="return_previous_sender_option" class="return-option-label" style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                        <input type="radio" name="return_destination_popup" value="previous_sender">
                                        <span>Return to previous process</span>
                                    </label>
                                    <div id="return_office_container" style="margin-left: 26px; margin-top: 4px; display: none;">
                                        <select id="return_office_select" class="form-custom-input" style="width: 100%;">
                                            <option value="" disabled selected>Select previous process</option>
                                        </select>
                                    </div>
                                    <label class="return-option-label" style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                                        <input type="radio" name="return_destination_popup" value="encoder">
                                        <span>Return to encoder</span>
                                    </label>
                                    <label class="return-option-label" style="display: flex; align-items: flex-start; gap: 8px; cursor: pointer;">
                                        <input type="radio" name="return_destination_popup" value="retract" style="margin-top: 3px;">
                                        <span>Retract voucher <span style="display:block; font-size: 12px; color: rgb(75 85 99 / 0.75); font-weight: normal;">Return to encoder and reset all data as if newly encoded (ORS/DV/ADA, COA, remarks, and process history cleared).</span></span>
                                    </label>
                                </div>
                            </div>
                            <div class="label-input__container">
                                <label for="return_remarks_popup">Remarks (optional)</label>
                                <textarea id="return_remarks_popup" class="form-custom-multi-input" rows="3" placeholder="Enter remarks for returning this voucher (optional)"></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="popupForm-footer__container">
                    <div class="footer-button__container">
                        <button class="btn warning" id="confirm_return_options" type="button">Return</button>
                        <button class="btn secondary transparent" id="cancel_return_options" type="button">CANCEL</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="overlay voucher-premium-overlay" id="returnOptionsOverlay" style="display: none;"></div>
    <div class="voucher-card voucher-card--table">
        <h2 class="voucher-card-title">Incoming Summary</h2>
        <style>
            .voucher-card--table {
                position: relative;
                display: flex;
                flex-direction: column;
                flex: 1;
                min-height: 0;
            }

            .main.main--voucher-dashboard {
                height: calc(100dvh - 4rem);
                max-height: calc(100dvh - 4rem);
                overflow: hidden;
                box-sizing: border-box;
                display: flex;
                flex-direction: column;
                gap: 1.25rem;
            }

            .main--voucher-dashboard .voucher-card--table {
                flex: 1;
                min-height: 0;
            }

            .voucher-card--table .content-wrapper {
                flex: 1;
                min-height: 0;
                overflow: auto;
                max-height: none;
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
                position: static;
                background: #fff;
                border-top: 1px solid rgba(229, 231, 235, 1);
                padding: 10px 0 0;
                margin-top: auto;
            }

            .voucher-bulk-action-bar {
                display: none;
                align-items: center;
                flex-wrap: wrap;
                gap: 14px;
                padding: 12px 14px;
                margin-bottom: 12px;
                border: 1px solid #e5e7eb;
                border-radius: 12px;
                background: linear-gradient(180deg, #fafbff 0%, #f3f6fb 100%);
                box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05);
            }

            .voucher-bulk-action-bar.is-visible {
                display: flex;
            }

            .voucher-bulk-action-bar label {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                font-size: 13px;
                font-weight: 500;
                color: #374151;
                margin: 0;
                cursor: pointer;
                user-select: none;
            }

            .voucher-bulk-action-bar label input[type="checkbox"] {
                width: 16px;
                height: 16px;
                cursor: pointer;
                accent-color: #059669;
            }

            .voucher-bulk-receive-btn {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                min-height: 38px;
                padding: 0 16px;
                border: none;
                border-radius: 10px;
                background: linear-gradient(135deg, #059669, #047857);
                color: #fff;
                font-size: 13px;
                font-weight: 600;
                letter-spacing: 0.02em;
                cursor: pointer;
                box-shadow: 0 4px 12px rgba(5, 150, 105, 0.28);
                transition: transform 120ms ease, box-shadow 120ms ease, opacity 120ms ease;
            }

            .voucher-bulk-receive-btn:hover:not(:disabled) {
                transform: translateY(-1px);
                box-shadow: 0 6px 16px rgba(5, 150, 105, 0.34);
            }

            .voucher-bulk-receive-btn:disabled {
                opacity: 0.55;
                cursor: not-allowed;
                box-shadow: none;
            }

            .voucher-bulk-receive-btn i {
                font-size: 16px;
                line-height: 1;
            }

            .voucher-bulk-action-status {
                font-size: 12px;
                color: #6b7280;
                font-weight: 500;
            }

            .voucher-bulk-select-cell {
                width: 42px;
                text-align: center;
            }

            .voucher-bulk-select-cell input[type="checkbox"] {
                width: 16px;
                height: 16px;
                cursor: pointer;
                accent-color: #059669;
            }

            #my-Table .voucher-table-actions-cell {
                width: 1%;
                white-space: nowrap;
                vertical-align: middle;
                padding: 4px 6px !important;
                text-align: right;
            }

            .voucher-table-actions-group {
                display: inline-flex;
                flex-direction: row;
                flex-wrap: nowrap;
                align-items: center;
                justify-content: flex-end;
                gap: 4px;
            }

            #my-Table .voucher-table-actions-group .voucher-table-action-btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 36px;
                min-width: 36px;
                height: 36px;
                min-height: 36px;
                padding: 0;
                border: 1px solid #e2e8f0;
                border-radius: 8px;
                background: #ffffff;
                color: #64748b;
                box-shadow: none;
                font-size: 0;
                line-height: 1;
                cursor: pointer;
                transition: background 120ms ease, border-color 120ms ease, color 120ms ease;
            }

            #my-Table .voucher-table-actions-group .voucher-table-action-btn i {
                font-size: 18px;
                line-height: 1;
            }

            #my-Table .voucher-table-actions-group .voucher-table-action-btn span {
                display: none;
            }

            #my-Table .voucher-table-actions-group .voucher-table-action-btn:hover {
                background: #f1f5f9;
                border-color: #cbd5e1;
                color: #475569;
            }

            #my-Table .voucher-table-actions-group .voucher-table-action-btn:active {
                background: #e2e8f0;
            }
        </style>
        <?php if ($isLiaisonOfficer) : ?>
        <div class="voucher-bulk-action-bar is-visible" id="voucherBulkReceiveBar">
            <label>
                <input type="checkbox" id="voucherBulkSelectAll" aria-label="Select all vouchers on this page">
                Select all on page
            </label>
            <button type="button" class="voucher-bulk-receive-btn" id="voucherBulkReceiveBtn">
                <i class="ri-inbox-archive-line" aria-hidden="true"></i>
                Receive Selected
            </button>
            <span class="voucher-bulk-action-status" id="voucherBulkReceiveStatus"></span>
        </div>
        <?php endif; ?>
        <div class="content-wrapper">
            <table class="table content_table content_table--dashboard" id="my-Table">
                <thead>
                    <tr>
                        <?php if ($isLiaisonOfficer) : ?>
                            <th class="voucher-bulk-select-cell" aria-label="Select for bulk receive"></th>
                        <?php endif; ?>
                        <th class="voucher-row-menu-cell" aria-label="Menu"></th>
                        <th>Processing No.</th>
                        <th>Payee Name</th>
                        <th>Amount</th>
                        <th>Remarks</th>
                        <th class="voucher-table-actions-cell">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    foreach ($incomingRows as $row) {
                        $incoming_process_history = voucher_incoming_load_process_history(
                            $pdo,
                            (string) ($row['processing_no'] ?? ''),
                            (string) ($row['process_history'] ?? '')
                        );
                        $incoming_process_history = voucher_tracking_enrich_process_history_for_return(
                            $pdo,
                            $incoming_process_history,
                            (string) ($row['voucher_type'] ?? '')
                        );
                        $incoming_process_history_display = voucher_tracking_process_history_for_display(
                            $pdo,
                            (string) ($row['processing_no'] ?? ''),
                            $incoming_process_history,
                            $incomingHistoryMap
                        );
                        $incoming_requires_dv = $incoming_is_accounting_role
                            ? voucher_incoming_requires_dv_no(
                                $pdo,
                                (string) ($row['voucher_type'] ?? ''),
                                $incoming_process_history,
                                $incoming_logged_user_office
                            )
                            : false;
                    ?>
                        <tr<?= $incoming_requires_dv ? ' data-requires-dv="1"' : '' ?>>
                            <?php if ($isLiaisonOfficer) : ?>
                                <td class="voucher-bulk-select-cell" data-label="">
                                    <input type="checkbox" class="voucher-bulk-select" value="<?php echo htmlspecialchars((string) $row['processing_no'], ENT_QUOTES, 'UTF-8'); ?>" aria-label="Select voucher <?php echo htmlspecialchars((string) $row['processing_no'], ENT_QUOTES, 'UTF-8'); ?>">
                                </td>
                            <?php endif; ?>
                            <td class="voucher-row-menu-cell" data-label="">
                                <div class="voucher-row-menu">
                                    <button type="button" class="voucher-row-menu-trigger" aria-label="Row actions" aria-haspopup="true" aria-expanded="false">
                                        <i class="ri-more-2-fill" aria-hidden="true"></i>
                                    </button>
                                    <div class="voucher-row-menu-dropdown" role="menu">
                                        <button class="btn tertiary voucher-row-menu-item" name="btn-view" type="button" role="menuitem">
                                            <i class="ri-eye-line" aria-hidden="true"></i>
                                            <span>View</span>
                                        </button>
                                        <a class="voucher-row-menu-link" href="voucher_status_report.php?q=<?php echo htmlspecialchars((string) $row['processing_no'], ENT_QUOTES, 'UTF-8'); ?>" role="menuitem">
                                            <i class="ri-history-line" aria-hidden="true"></i>
                                            <span>History</span>
                                        </a>
                                    </div>
                                </div>
                            </td>
                            <td data-label="processing_no"><?php echo $row['processing_no']; ?></td>
                            <td data-label="ors_no" class="hidden"><?php echo $row['ors_no']; ?></td>
                            <td data-label="dv_no" class="hidden"><?php echo $row['dv_no']; ?></td>
                            <td data-label="ada_check_no" class="hidden"><?php echo $row['ada_check_no']; ?></td>
                            <td data-label="payee"><?php echo $row['payee']; ?></td>
                            <td data-label="address" class="hidden status"><?php echo $row['address']; ?></td>
                            <td data-label="particulars" class="hidden"><?php echo $row['particulars']; ?></td>
                            <?php echo voucher_amount_stack_cell_html($row['amount'] ?? '', $row['charged_amount'] ?? ''); ?>
                            <td data-label="amount_original" class="hidden"><?php echo $row['amount']; ?></td>
                            <td data-label="voucher_date" class="hidden"><?php echo $row['voucher_date']; ?></td>
                            <td data-label="voucher_type_display" class="hidden voucher-type-cell"><?php echo voucher_type_badge_html((string)($row['voucher_type'] ?? '')); ?></td>
                            <?php if (isset($row['charged_amount'])) : ?>
                                <td data-label="charged_amount" class="hidden"><?php echo $row['charged_amount']; ?></td>
                            <?php else : ?>
                                <td data-label="charged_amount" class="hidden"></td>
                            <?php endif; ?>
                            <td data-label="datetime_forwarded" class="hidden"><?php echo $row['datetime_forwarded']; ?></td>
                            <td data-label="tin_employee_no" class="hidden"><?php echo $row['tin_employee_no']; ?></td>
                            <td data-label="office_from" class="hidden"><?php echo $row['office_from']; ?></td>
                            <td data-label="office_to" class="hidden"><?php echo $row['office_to']; ?></td>
                            <td data-label="sender_udc" class="hidden"><?php echo $row['sender_udc']; ?></td>
                            <td data-label="receiver_udc" class="hidden"><?php echo $row['receiver_udc']; ?></td>
                            <td data-label="encoded_by" class="hidden"><?php echo $row['encoded_by']; ?></td>
                            <td data-label="encoded_from" class="hidden"><?php echo $row['encoded_from']; ?></td>
                            <td data-label="datetime_encoded" class="hidden"><?php echo $row['datetime_encoded']; ?></td>
                            <td data-label="forwarded_by" class="hidden"><?php echo $row['forwarded_by']; ?></td>
                            <td data-label="process_status" class="hidden"><?php echo $row['process_status']; ?></td>
                            <td data-label="remarks" class="hidden"><?php echo $row['remarks']; ?></td>
                            <td data-label="sender_remarks" class="return-remarks-cell"><?php
                                $sender_remarks_raw = isset($row['sender_remarks']) ? trim((string)$row['sender_remarks']) : '';

                                // Show only the latest entry from a combined string like:
                                // "Name A: remark..., Name B: remark..., Name C: remark..."
                                // Uses a lookahead to split only on ", NextName:" boundaries (keeps commas inside remarks).
                                $sender_latest = '';
                                if ($sender_remarks_raw !== '' && strcasecmp($sender_remarks_raw, 'N/A') !== 0) {
                                    $pattern = '/(?:^|,\s*)([^,]+?):\s*(.*?)(?=(?:,\s*[^,]+?:\s)|$)/s';
                                    if (preg_match_all($pattern, $sender_remarks_raw, $m) && !empty($m[0])) {
                                        $idx = count($m[0]) - 1;
                                        $name = trim((string)$m[1][$idx]);
                                        $text = trim((string)$m[2][$idx]);
                                        $sender_latest = trim($name . ': ' . $text);
                                    } else {
                                        // Fallback: show raw (already trimmed)
                                        $sender_latest = $sender_remarks_raw;
                                    }
                                }

                                echo $sender_latest !== ''
                                    ? '<span class="remarks-badge" title="' . htmlspecialchars($sender_latest, ENT_QUOTES, 'UTF-8') . '"><span class="remarks-badge-text">' . htmlspecialchars($sender_latest, ENT_QUOTES, 'UTF-8') . '</span></span>'
                                    : '';
                                ?></td>
                            <td class="voucher-table-actions-cell" data-label="actions">
                                <div class="voucher-table-actions-group">
                                    <?php if ($_SESSION["acl"] >= 3) : ?>
                                        <button class="btn pPop voucher-table-action-btn voucher-table-action-btn--receive" id="openPopup" name="btn-receive" type="button" aria-label="Receive" title="Receive"><i class="ri-inbox-archive-line" aria-hidden="true"></i><span>Receive</span></button>
                                    <?php endif; ?>
                                    <button class="btn voucher-table-action-btn voucher-table-action-btn--return" name="btn-return" type="button" aria-label="Return" title="Return"><i class="ri-arrow-go-back-line" aria-hidden="true"></i><span>Return</span></button>
                                </div>
                            </td>
                            <td data-label="voucher_type" class="hidden"><?php echo $row['voucher_type']; ?></td>
                            <td data-label="coa_options" class="hidden"><?php echo isset($row['coa_options']) ? htmlspecialchars($row['coa_options']) : ''; ?></td>
                            <td data-label="coa_category" class="hidden"><?php echo isset($row['coa_category']) ? htmlspecialchars($row['coa_category']) : ''; ?></td>
                            <td data-label="coa_subsection" class="hidden"><?php echo isset($row['coa_subsection']) ? htmlspecialchars($row['coa_subsection']) : ''; ?></td>
                            <td data-label="process_history" class="hidden"><?php echo htmlspecialchars($incoming_process_history, ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="process_history_display" class="hidden"><?php echo htmlspecialchars($incoming_process_history_display, ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php
                    }
                    ?>
                </tbody>
            </table>
            <div class="no-display voucher-table-empty-hint" style="<?php echo $displayTotal < 1 ? 'display:flex;' : 'display:none;'; ?>">
                <p>NO DATA TO DISPLAY</p>
            </div>
        </div>
        <?php
        $startEntry = $displayTotal > 0 ? (($currentPage - 1) * $rowsPerPage) + 1 : 0;
        $endEntry = $displayTotal > 0 ? min($currentPage * $rowsPerPage, $displayTotal) : 0;
        ?>
        <div class="voucher-pagination-footer">
            <div class="pagination">
                <div class="pagination_container pagination_container--modern">
                    <div class="pagination_navigation pagination_navigation--modern">
                        <?php if ($displayTotal < 1) : ?>
                            <button class="pagination_btn_modern" type="button" disabled>Previous</button>
                            <div class="pagination_pages pagination_pages--modern"></div>
                            <button class="pagination_btn_modern" type="button" disabled>Next</button>
                        <?php elseif ($currentPage > 1): ?>
                            <a class="pagination_btn_modern" href="<?php echo htmlspecialchars(build_voucher_portal_page_url($currentPage - 1, $rowsPerPage, $voucher_type_filter, $rawQ), ENT_QUOTES, 'UTF-8'); ?>">Previous</a>
                        <?php else: ?>
                            <button class="pagination_btn_modern" type="button" disabled>Previous</button>
                        <?php endif; ?>

                        <?php if ($displayTotal >= 1) : ?>
                            <div class="pagination_pages pagination_pages--modern">
                                <?php
                                $pageRange = 5;
                                $startPage = max(1, $currentPage - (int)floor($pageRange / 2));
                                $endPage = min($totalPages, $startPage + $pageRange - 1);
                                if ($endPage - $startPage + 1 < $pageRange) {
                                    $startPage = max(1, $endPage - $pageRange + 1);
                                }

                                if ($totalPages <= 7) {
                                    for ($i = 1; $i <= $totalPages; $i++) {
                                        $active = ($i == $currentPage) ? ' active' : '';
                                        echo '<a class="pagination_page_num' . $active . '" href="' . htmlspecialchars(build_voucher_portal_page_url($i, $rowsPerPage, $voucher_type_filter, $rawQ), ENT_QUOTES, 'UTF-8') . '">' . $i . '</a>';
                                    }
                                } else {
                                    echo '<a class="pagination_page_num' . (1 == $currentPage ? ' active' : '') . '" href="' . htmlspecialchars(build_voucher_portal_page_url(1, $rowsPerPage, $voucher_type_filter, $rawQ), ENT_QUOTES, 'UTF-8') . '">1</a>';
                                    if ($startPage > 2) {
                                        echo '<span class="pagination_ellipsis">...</span>';
                                    }
                                    for ($i = max(2, $startPage); $i <= min($totalPages - 1, $endPage); $i++) {
                                        $active = ($i == $currentPage) ? ' active' : '';
                                        echo '<a class="pagination_page_num' . $active . '" href="' . htmlspecialchars(build_voucher_portal_page_url($i, $rowsPerPage, $voucher_type_filter, $rawQ), ENT_QUOTES, 'UTF-8') . '">' . $i . '</a>';
                                    }
                                    if ($endPage < $totalPages - 1) {
                                        echo '<span class="pagination_ellipsis">...</span>';
                                    }
                                    echo '<a class="pagination_page_num' . ($totalPages == $currentPage ? ' active' : '') . '" href="' . htmlspecialchars(build_voucher_portal_page_url($totalPages, $rowsPerPage, $voucher_type_filter, $rawQ), ENT_QUOTES, 'UTF-8') . '">' . $totalPages . '</a>';
                                }
                                ?>
                            </div>

                            <?php if ($currentPage < $totalPages): ?>
                                <a class="pagination_btn_modern" href="<?php echo htmlspecialchars(build_voucher_portal_page_url($currentPage + 1, $rowsPerPage, $voucher_type_filter, $rawQ), ENT_QUOTES, 'UTF-8'); ?>">Next</a>
                            <?php else: ?>
                                <button class="pagination_btn_modern" type="button" disabled>Next</button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <div class="pagination_info">
                        <?php echo $displayTotal < 1 ? 'NO DATA TO DISPLAY' : ($totalRows ? ('Showing ' . $endEntry . ' of ' . $totalRows . ' results') : 'NO DATA TO DISPLAY'); ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="../../protected/js/qr_scanner_search.js"></script>
<script>
    (function() {
        var inp = document.getElementById('filterInput');
        if (!inp) return;
        var initial = String(inp.value || '');
        function applyFilterSearch() {
            var v = String(inp.value || '');
            if (v === initial) return;
            var u = new URL(window.location.href);
            u.searchParams.set('page', '1');
            u.searchParams.set('rowsPerPage', '50');
            if (v === '') {
                u.searchParams.delete('q');
            } else {
                u.searchParams.set('q', v);
            }
            window.location.href = u.toString();
        }
        inp.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                applyFilterSearch();
            }
        });
        if (typeof bindQrScannerSearch === 'function') {
            bindQrScannerSearch({
                inputId: 'filterInput',
                onSubmit: function(value) {
                    inp.value = value;
                    initial = '';
                    applyFilterSearch();
                }
            });
        }
    })();
</script>
<script>
    (function() {
        var typeFilter = document.getElementById('filterInputType');
        var form = document.getElementById('incomingFilterForm');
        var dropdown = document.getElementById('filterTypeDropdown');
        var trigger = document.getElementById('filterTypeTrigger');
        var menu = document.getElementById('filterTypeMenu');
        if (!typeFilter || !form || !dropdown || !trigger || !menu) return;

        function closeDropdown() {
            dropdown.classList.remove('is-open');
            trigger.setAttribute('aria-expanded', 'false');
        }

        trigger.addEventListener('click', function(e) {
            e.stopPropagation();
            var willOpen = !dropdown.classList.contains('is-open');
            if (willOpen) {
                dropdown.classList.add('is-open');
                trigger.setAttribute('aria-expanded', 'true');
            } else {
                closeDropdown();
            }
        });

        menu.querySelectorAll('.filter-type-option').forEach(function(optionBtn) {
            optionBtn.addEventListener('click', function() {
                var selectedValue = this.getAttribute('data-value') || 'all';
                typeFilter.value = selectedValue;
                trigger.textContent = this.textContent.trim();
                menu.querySelectorAll('.filter-type-option').forEach(function(btn) {
                    btn.classList.remove('is-active');
                });
                this.classList.add('is-active');
                closeDropdown();
                form.submit();
            });
        });

        document.addEventListener('click', function(e) {
            if (!dropdown.contains(e.target)) {
                closeDropdown();
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeDropdown();
            }
        });
    })();
</script>
<script>
    (function() {
        var table = document.getElementById('my-Table');
        if (!table) return;

        var contentWrapper = table.closest('.content-wrapper');

        function getMenuDropdown(menu) {
            return menu._portedDropdown || menu.querySelector('.voucher-row-menu-dropdown');
        }

        function resetRowMenuDropdown(menu) {
            var dropdown = getMenuDropdown(menu);
            if (!dropdown) return;

            dropdown.classList.remove('is-open');
            dropdown.style.top = '';
            dropdown.style.left = '';
            dropdown.style.width = '';
            dropdown.style.minWidth = '';
            dropdown.style.maxWidth = '';

            if (menu._portedDropdown) {
                menu.appendChild(dropdown);
                menu._portedDropdown = null;
            }

            dropdown._ownerRow = null;
        }

        function positionRowMenuDropdown(menu) {
            var dropdown = getMenuDropdown(menu);
            var trigger = menu.querySelector('.voucher-row-menu-trigger');
            if (!dropdown || !trigger) return;

            if (!menu._portedDropdown) {
                menu._portedDropdown = dropdown;
                dropdown._ownerRow = menu.closest('tr');
                document.body.appendChild(dropdown);
            }

            dropdown.classList.add('is-open');

            var rect = trigger.getBoundingClientRect();
            var dropdownWidth = dropdown.offsetWidth || 128;
            var centeredLeft = rect.left + (rect.width / 2) - (dropdownWidth / 2);
            dropdown.style.left = Math.max(8, Math.min(centeredLeft, window.innerWidth - dropdownWidth - 8)) + 'px';
            dropdown.style.top = (rect.bottom + 4) + 'px';

            var dropdownRect = dropdown.getBoundingClientRect();
            if (dropdownRect.bottom > window.innerHeight - 8) {
                dropdown.style.top = Math.max(8, rect.top - dropdownRect.height - 4) + 'px';
            }
            dropdownWidth = dropdownRect.width || dropdownWidth;
            centeredLeft = rect.left + (rect.width / 2) - (dropdownWidth / 2);
            dropdown.style.left = Math.max(8, Math.min(centeredLeft, window.innerWidth - dropdownWidth - 8)) + 'px';
        }

        function syncOpenRowMenu() {
            var openMenu = table.querySelector('.voucher-row-menu.is-open');
            if (openMenu) {
                positionRowMenuDropdown(openMenu);
            }
        }

        function closeAllRowMenus(exceptMenu) {
            table.querySelectorAll('.voucher-row-menu.is-open').forEach(function(menu) {
                if (exceptMenu && menu === exceptMenu) {
                    return;
                }
                menu.classList.remove('is-open');
                resetRowMenuDropdown(menu);
                var trigger = menu.querySelector('.voucher-row-menu-trigger');
                if (trigger) {
                    trigger.setAttribute('aria-expanded', 'false');
                }
            });
        }

        table.addEventListener('click', function(e) {
            var trigger = e.target.closest('.voucher-row-menu-trigger');
            if (trigger) {
                e.preventDefault();
                e.stopPropagation();
                var menu = trigger.closest('.voucher-row-menu');
                if (!menu) return;
                var willOpen = !menu.classList.contains('is-open');
                closeAllRowMenus(willOpen ? menu : null);
                menu.classList.toggle('is-open', willOpen);
                trigger.setAttribute('aria-expanded', willOpen ? 'true' : 'false');
                if (willOpen) {
                    positionRowMenuDropdown(menu);
                } else {
                    resetRowMenuDropdown(menu);
                }
                return;
            }

            if (e.target.closest('[name="btn-view"]') || e.target.closest('.voucher-row-menu-link')) {
                closeAllRowMenus();
            }
        });

        document.addEventListener('click', function(e) {
            if (!e.target.closest('.voucher-row-menu') && !e.target.closest('.voucher-row-menu-dropdown')) {
                closeAllRowMenus();
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAllRowMenus();
            }
        });

        if (contentWrapper) {
            contentWrapper.addEventListener('scroll', syncOpenRowMenu, { passive: true });
        }

        window.addEventListener('resize', syncOpenRowMenu);
    })();
</script>
<script>
    (function() {
        var main = document.getElementById('main');
        var tableCard = document.querySelector('.voucher-card--table');
        var tableWrapper = tableCard ? tableCard.querySelector('.content-wrapper') : null;
        if (!main || !tableCard || !tableWrapper) return;

        var layoutTimer = null;

        function fitIncomingViewport() {
            var wrapperTop = tableWrapper.getBoundingClientRect().top;
            var pagination = tableCard.querySelector('.voucher-pagination-footer');
            var paginationHeight = pagination ? pagination.offsetHeight : 0;
            var bottomGap = 20;
            var available = window.innerHeight - wrapperTop - paginationHeight - bottomGap;
            tableWrapper.style.maxHeight = Math.max(160, available) + 'px';
        }

        function scheduleIncomingLayoutSync() {
            if (layoutTimer) {
                clearTimeout(layoutTimer);
            }
            layoutTimer = setTimeout(fitIncomingViewport, 80);
        }

        window.addEventListener('resize', scheduleIncomingLayoutSync);
        window.addEventListener('load', scheduleIncomingLayoutSync);

        if (window.ResizeObserver) {
            var layoutObserver = new ResizeObserver(scheduleIncomingLayoutSync);
            layoutObserver.observe(main);
            layoutObserver.observe(tableCard);
        }

        scheduleIncomingLayoutSync();
    })();
</script>
<?php if ($invalidSearch): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof showNotify === 'function') {
                showNotify('Invalid search: remove special characters or shorten your query.', 'warning', 2600);
            }
        });
    </script>
<?php elseif (trim($rawQ) !== '' && $q !== '' && $displayTotal < 1): ?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            if (typeof showNotify === 'function') {
                showNotify('No matching incoming vouchers for your search.', 'warning', 2200);
            }
        });
    </script>
<?php endif; ?>

<!-- History / Remarks Modal -->
<div class="popup-form voucher-premium-modal voucher-history-modal" id="historyModal" style="display:none;">
    <div class="popupForm-box__container">
        <div class="popupForm-header__container">
            <p>History &amp; Remarks</p>
            <i class="ri-close-fill close-icon" id="close_history_modal"></i>
        </div>
        <div class="f-container">
            <div class="box-body__container">
                <div class="hist-topbar">
                    <div class="hist-pill">
                        <label>Processing No.</label>
                        <input type="text" id="hist_processing_no" class="form-custom-input" readonly>
                    </div>
                </div>

                <div class="hist-grid">
                    <div class="hist-card">
                        <p class="hist-card-title">Sender remarks (latest)</p>
                        <div id="hist_sender_remarks" class="hist-content"></div>
                    </div>
                    <div class="hist-card">
                        <p class="hist-card-title">All remarks (combined)</p>
                        <div id="hist_combined_remarks" class="hist-content"></div>
                    </div>
                    <div class="hist-card hist-card--full">
                        <p class="hist-card-title">Process history</p>
                        <div id="hist_process_history" class="hist-content hist-content--tall"></div>
                    </div>
                </div>
            </div>

            <div class="popupForm-footer__container">
                <div class="footer-button__container">
                    <button class="btn secondary transparent" id="close_history_modal_btn" type="button">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="overlay voucher-premium-overlay" id="historyOverlay" style="display:none;"></div>
<style>
    #coaOptionsModal {
        z-index: 10001;
    }

    #coa_modal_overlay {
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0, 0, 0, 0.5);
        z-index: 10000;
    }

    #coa_options_list label {
        display: flex;
        align-items: center;
        padding: 10px 12px;
        cursor: pointer;
        border-bottom: 1px solid #eee;
        transition: background-color 0.2s;
    }

    #coa_options_list label:hover {
        background-color: #f0f0f0;
    }

    #coa_options_list label:last-child {
        border-bottom: none;
    }

    #coa_options_list input[type="checkbox"] {
        margin-right: 10px;
        cursor: default;
    }

    /* View-only: show tick clearly (some browsers gray out disabled checked boxes). */
    #coa_options_list label.coa-requirement-view-only {
        pointer-events: none;
        user-select: none;
    }

    #coa_options_list label.coa-requirement-view-only input[type="checkbox"] {
        pointer-events: none;
        accent-color: #2563eb;
        opacity: 1;
    }
</style>
<!-- COA Options Modal -->
<div class="popup-form voucher-premium-modal popup-form--compact" id="coaOptionsModal" style="display: none;">
    <div class="popupForm-box__container">
        <div class="popupForm-header__container">
            <p id="coa_modal_title">COA Requirements</p>
            <i class="ri-close-fill close-icon" id="close_coa_modal"></i>
        </div>
        <div class="f-container">
            <div class="box-body__container flex-row">
                <div class="popupForm-body__container" style="width: 100%;">
                    <div class="form-container">
                        <div class="label-input__container">
                            <label for="coa_options_checklist">Select COA Requirements <span style="color: red;">*</span></label>
                            <div id="coa_options_list" style="background-color: white; border: 1px solid #ccc; border-radius: 4px; padding: 10px; max-height: 400px; overflow-y: auto;">
                                <!-- Options will be populated dynamically -->
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="popupForm-footer__container">
                <div class="footer-button__container" style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                    <p style="margin: 0; font-size: 12px; color: #555; flex: 1; min-width: 200px;">By clicking "Confirm," I certify that all required attachments have been submitted and acknowledge that incomplete submissions may result in processing delays.</p>
                    <button class="btn tertiary" id="coa_modal_select_all" type="button">Select all</button>
                    <button class="btn primary" id="coa_modal_save" type="button">Confirm</button>
                    <button class="btn secondary transparent" id="coa_modal_cancel" type="button">CANCEL</button>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="overlay voucher-premium-overlay" id="coa_modal_overlay" style="display: none;"></div>
<script>
    const selectElements2 = document.querySelectorAll(".form-custom-input"); // Get all select elements
    const target2 = "<?php echo $_SESSION['logged_user_designation']; ?>";

    const targetArray2 = target2.split(',').map(function(v) {
        return String(v || '').trim();
    }); // Convert to a trimmed array

    function normalizeIncomingProcessHistory(value) {
        return String(value || '')
            .replace(/\r\n/g, '\n')
            .replace(/\r/g, '\n')
            .replace(/\\n/g, '\n')
            .trim();
    }

    function parseIncomingProcessHistoryLines(value) {
        var normalized = normalizeIncomingProcessHistory(value);
        if (!normalized) {
            return [];
        }

        return normalized.split('\n').map(function(line) {
            return String(line || '').trim();
        }).filter(function(line) {
            return line !== '' && line.indexOf('|') !== -1;
        }).map(function(line) {
            var parts = line.split(/\s*\|\s*/);
            return {
                name: (parts[0] || '').trim(),
                action: (parts[1] || '').trim(),
                section: (parts[2] || '').trim(),
                office: (parts.slice(3).join(' | ') || '').trim()
            };
        });
    }

    function normalizeIncomingHistorySection(section) {
        var key = String(section || '').trim().toUpperCase();
        var map = {
            'PLANNING': 'Planning Section',
            'PLANNING SECTION': 'Planning Section'
        };
        return map[key] || String(section || '').trim();
    }

    function incomingHistoryHasPlanningReceive(lines) {
        for (var i = 0; i < lines.length; i++) {
            if (!/received by/i.test(lines[i].action)) {
                continue;
            }
            if (normalizeIncomingHistorySection(lines[i].section) === 'Planning Section') {
                return true;
            }
        }
        return false;
    }

    function incomingVoucherTypeRequiresDvAlways(voucherType) {
        var value = String(voucherType || '').trim();
        return value.localeCompare('e-NGP Retention', undefined, { sensitivity: 'accent' }) === 0
            || value.localeCompare('e-NGP Seedling Production & MP', undefined, { sensitivity: 'accent' }) === 0;
    }

    function incomingRequiresDvNo(voucherType, processHistory, row) {
        if (row && row.getAttribute('data-requires-dv') === '1') {
            return true;
        }

        if (incomingVoucherTypeRequiresDvAlways(voucherType)) {
            return true;
        }

        return false;
    }

    function applyIncomingDvNoRules(voucherType, processHistory, row) {
        const isAccountingRole = targetArray2.includes("Accounting Unit")
            || targetArray2.includes("Processor")
            || targetArray2.includes("Accountant III");
        const dvInput = document.getElementById("dv_no");
        if (!dvInput || !isAccountingRole) {
            return;
        }

        const requiresDv = incomingRequiresDvNo(voucherType, processHistory, row);
        dvInput.required = requiresDv;
        dvInput.readOnly = !requiresDv;
        dvInput.style.border = requiresDv ? "1px solid red" : "";
    }

    if (targetArray2.includes("Accounting Unit") || targetArray2.includes("Processor") || targetArray2.includes("Accountant III")) {
        // Allow editing of the Charged Amount (Edited) field instead of the main Amount
        const chargedInput = document.getElementById('charged_string_amount');
        const numericAmountInput = document.querySelector('input[name="amount"]');

        if (chargedInput && numericAmountInput) {
            chargedInput.readOnly = false;
            numericAmountInput.readOnly = true;

            chargedInput.addEventListener('input', function() {
                sanitizeAmountInputField(chargedInput);
                syncAmountFields(chargedInput.value, numericAmountInput);
            });

            chargedInput.addEventListener('blur', function() {
                sanitizeAmountInputField(chargedInput);
                syncAmountFields(chargedInput.value, numericAmountInput);
            });
        }
    }
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
<script>
    function confirmReceive() {
        functionAlert('Are you sure to Receive?', 'receive-confirm', function() {
            window.location.href = "forwarding.php";
        });
    }
</script>
<script>
    (function() {
        const isLiaisonOfficer = <?php echo $isLiaisonOfficer ? 'true' : 'false'; ?>;
        const bulkReceiveToken = <?php echo json_encode($bulkReceiveToken, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?>;
        window.bulkReceiveToken = bulkReceiveToken;
        const bulkReceiveUrl = '../../protected/handler/voucher_incoming_module/voucher_bulk_receive_handler.php';
        const bulkSelectAllEl = document.getElementById('voucherBulkSelectAll');
        const bulkReceiveBtn = document.getElementById('voucherBulkReceiveBtn');
        const bulkReceiveStatusEl = document.getElementById('voucherBulkReceiveStatus');
        let bulkReceiveInFlight = false;

        function syncBulkSelectAllState() {
            if (!isLiaisonOfficer || !bulkSelectAllEl) return;
            const boxes = Array.from(document.querySelectorAll('#my-Table input.voucher-bulk-select'));
            if (boxes.length === 0) {
                bulkSelectAllEl.checked = false;
                bulkSelectAllEl.indeterminate = false;
                return;
            }
            const checkedCount = boxes.filter(function(cb) { return cb.checked; }).length;
            bulkSelectAllEl.checked = checkedCount === boxes.length && boxes.length > 0;
            bulkSelectAllEl.indeterminate = false;
        }

        function selectedBulkProcessingNos() {
            if (!isLiaisonOfficer) return [];
            return Array.from(document.querySelectorAll('#my-Table input.voucher-bulk-select:checked'))
                .map(function(cb) { return String(cb.value || '').trim(); })
                .filter(function(pn) { return pn !== ''; });
        }

        function setBulkReceiveStatus(message) {
            if (bulkReceiveStatusEl) {
                bulkReceiveStatusEl.textContent = message || '';
            }
        }

        function runBulkReceive() {
            if (!isLiaisonOfficer || bulkReceiveInFlight) return;
            const processingNos = selectedBulkProcessingNos();
            if (processingNos.length === 0) {
                if (typeof showNotify === 'function') {
                    showNotify('Select at least one voucher to receive.', 'warning', 2800);
                }
                return;
            }
            const confirmMsg = 'Receive ' + processingNos.length + ' selected voucher(s)? They will be processed one at a time.';
            if (!window.confirm(confirmMsg)) {
                return;
            }
            bulkReceiveInFlight = true;
            if (bulkReceiveBtn) bulkReceiveBtn.disabled = true;
            setBulkReceiveStatus('Receiving…');
            fetch(bulkReceiveUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    token: window.bulkReceiveToken || bulkReceiveToken,
                    processing_nos: processingNos
                })
            })
                .then(function(r) {
                    return r.json().then(function(payload) {
                        return { ok: r.ok, payload: payload };
                    });
                })
                .then(function(res) {
                    bulkReceiveInFlight = false;
                    if (bulkReceiveBtn) bulkReceiveBtn.disabled = false;
                    const payload = res.payload || {};
                    if (payload.ok === true) {
                        const msg = payload.message || ('Received ' + (payload.received || 0) + ' voucher(s).');
                        if (payload.token) {
                            window.bulkReceiveToken = payload.token;
                        }
                        setBulkReceiveStatus(msg);
                        if (typeof showNotify === 'function') {
                            showNotify(msg, Number(payload.failed || 0) > 0 ? 'warning' : 'success', 4000);
                        }
                        window.location.reload();
                        return;
                    }
                    const err = payload.error || payload.message || 'Bulk receive failed.';
                    setBulkReceiveStatus('');
                    if (typeof showNotify === 'function') {
                        showNotify(err, 'error', 5000);
                    }
                })
                .catch(function() {
                    bulkReceiveInFlight = false;
                    if (bulkReceiveBtn) bulkReceiveBtn.disabled = false;
                    setBulkReceiveStatus('');
                    if (typeof showNotify === 'function') {
                        showNotify('Bulk receive request failed.', 'error', 4000);
                    }
                });
        }

        if (bulkSelectAllEl) {
            bulkSelectAllEl.addEventListener('change', function() {
                const checked = !!bulkSelectAllEl.checked;
                bulkSelectAllEl.indeterminate = false;
                document.querySelectorAll('#my-Table input.voucher-bulk-select').forEach(function(cb) {
                    cb.checked = checked;
                });
            });
        }
        if (bulkReceiveBtn) {
            bulkReceiveBtn.addEventListener('click', runBulkReceive);
        }
        document.querySelectorAll('#my-Table input.voucher-bulk-select').forEach(function(cb) {
            cb.addEventListener('change', syncBulkSelectAllState);
        });
    })();
</script>
<!--=============== MAIN.JS ===============!-->
<script src="../../protected/js/main.js"></script>
<script src="../../protected/js/amount_helper.js"></script>
<script src="../../protected/js/voucher.js"></script>
<script src="../../protected/js/voucher_process_history_display.js"></script>
<script src="../../protected/js/popscript.js"></script>
<script>
    function escapeHtml(s) {
        if (s == null) return '';
        var div = document.createElement('div');
        div.textContent = s;
        return div.innerHTML;
    }

    function renderProcessHistory(raw) {
        if (!raw || !String(raw).trim()) return '';
        // The DB may store newlines as literal "\n" (backslash + n), so normalize first.
        var normalized = String(raw)
            .replace(/\u00A0/g, ' ')
            .replace(/\r\n/g, '\n')
            .replace(/\r/g, '\n')
            .replace(/\\n/g, '\n')
            .trim();

        var lines = normalized
            .split(/\n+/)
            .map(function(l) {
                return String(l).trim();
            })
            .filter(function(l) {
                return l !== '';
            });
        if (lines.length === 0) return '';
        function simplifyActionLabel(actionText) {
            var txt = String(actionText || '').trim();
            var byMatch = txt.match(/^(.+?)\s+by\b\s*:?\s*.*$/i);
            if (byMatch && byMatch[1]) {
                return byMatch[1].trim();
            }
            return txt;
        }

        var parts, user, action, section, office, html = '<ul class="hist-process-list">';
        for (var i = 0; i < lines.length; i++) {
            if (lines[i].indexOf('|') !== -1) {
                parts = lines[i].split(/\s*\|\s*/);
                user = (parts[0] || '').trim();
                action = simplifyActionLabel((parts[1] || '').trim());
                section = (parts[2] || '').trim();
                office = (parts.slice(3).join(' | ')).trim();
            } else {
                parts = lines[i].split(/\s*:\s*/);
                user = (parts[0] || '').trim();
                if (parts.length >= 3) {
                    action = simplifyActionLabel((parts[1] || '').trim());
                    section = (parts.slice(2).join(' : ')).trim();
                } else {
                    action = '';
                    section = (parts[1] || '').trim();
                }
                office = '';
            }
            html += '<li class="hist-process-item">';
            html += '<span class="hist-process-item__part hist-process-item__part--user"><i class="ri-user-line"></i><span>' + escapeHtml(user) + '</span></span>';
            if (action) {
                html += '<span class="hist-process-sep">·</span>';
                html += '<span class="hist-process-item__part hist-process-item__part--action"><i class="ri-check-double-line"></i><span>' + escapeHtml(action) + '</span></span>';
            }
            html += '<span class="hist-process-sep">·</span>';
            html += '<span class="hist-process-item__part hist-process-item__part--section"><i class="ri-building-4-line"></i><span>' + escapeHtml(section) + '</span></span>';
            if (office) {
                html += '<span class="hist-process-sep">·</span>';
                html += '<span class="hist-process-item__part hist-process-item__part--section"><i class="ri-community-line"></i><span>' + escapeHtml(office) + '</span></span>';
            }
            html += '</li>';
        }
        html += '</ul>';
        return html;
    }

    // Get all buttons with class 'btn-forward'
    var buttons = document.querySelectorAll('.btn');

    // Loop through each button and attach click event listener
    buttons.forEach(function(button) {
        button.addEventListener('click', function() {
            // Get the row associated with the clicked button
            var row = this.closest('tr');
            if (!row) {
                var portaledDropdown = this.closest('.voucher-row-menu-dropdown');
                if (portaledDropdown && portaledDropdown._ownerRow) {
                    row = portaledDropdown._ownerRow;
                }
            }
            if (!row) return;

            var name = this.getAttribute('name');

            // Extract data from the row
            var processing_no = row.querySelector('[data-label="processing_no"]').textContent;
            var ors_no = row.querySelector('[data-label="ors_no"]').textContent;
            var dv_no = row.querySelector('[data-label="dv_no"]').textContent;
            var ada_check_no = row.querySelector('[data-label="ada_check_no"]').textContent;
            var payee = row.querySelector('[data-label="payee"]').textContent;
            var address = row.querySelector('[data-label="address"]').textContent;
            var particulars = row.querySelector('[data-label="particulars"]').textContent;
            var tin_employee_no = row.querySelector('[data-label="tin_employee_no"]').textContent;
            var amountOriginalCell = row.querySelector('[data-label="amount_original"]');
            var amountTd = row.querySelector('[data-label="amount"]');
            var amountOriginal = normalizeAmountInput(amountOriginalCell ? amountOriginalCell.textContent : (amountTd ? (amountTd.getAttribute('data-amount') || amountTd.textContent) : ''));
            var voucher_date = row.querySelector('[data-label="voucher_date"]').textContent;
            var office_to = row.querySelector('[data-label="office_to"]').textContent;
            var office_from = row.querySelector('[data-label="office_from"]').textContent;
            var sender_udc = row.querySelector('[data-label="sender_udc"]').textContent;
            var receiver_udc = row.querySelector('[data-label="receiver_udc"]').textContent;
            var encoded_by = row.querySelector('[data-label="encoded_by"]').textContent;
            var encoded_from = row.querySelector('[data-label="encoded_from"]').textContent;
            var datetime_encoded = row.querySelector('[data-label="datetime_encoded"]').textContent;
            var forwarded_by = row.querySelector('[data-label="forwarded_by"]').textContent;
            var process_status = row.querySelector('[data-label="process_status"]').textContent;
            var remarks = row.querySelector('[data-label="remarks"]').textContent;
            var sender_remarks = (row.querySelector('[data-label="sender_remarks"]')?.textContent || '').trim();
            var voucher_type = row.querySelector('[data-label="voucher_type"]').textContent;
            var process_history_cell = row.querySelector('[data-label="process_history"]');
            var process_history = process_history_cell ? process_history_cell.textContent : '';
            var coa_options_cell = row.querySelector('[data-label="coa_options"]');
            var coa_options = coa_options_cell ? coa_options_cell.textContent : '';
            var coa_category_cell = row.querySelector('[data-label="coa_category"]');
            var coa_category = coa_category_cell ? coa_category_cell.textContent : '';
            var coa_subsection_cell = row.querySelector('[data-label="coa_subsection"]');
            var coa_subsection = coa_subsection_cell ? coa_subsection_cell.textContent : '';
            var charged_amount_cell = row.querySelector('[data-label="charged_amount"]');
            var charged_amount = normalizeAmountInput(charged_amount_cell ? charged_amount_cell.textContent : '');

            // Send it via AJAX to the server
            document.querySelector('.processing_no').value = processing_no;
            document.querySelector('.dv_no').value = dv_no;
            document.querySelector('.ors_no').value = ors_no;
            document.querySelector('.ada_check_no').value = ada_check_no;
            document.querySelector('.payee').value = payee;
            document.querySelector('.address').value = address;
            document.querySelector('.particulars').value = particulars;
            document.querySelector('.tin_employee_no').value = tin_employee_no;
            document.querySelector('.voucher_date').value = voucher_date;
            document.querySelector('.office_from').value = office_from;
            document.querySelector('.office_to').value = office_to;
            document.querySelector('.sender_udc').value = sender_udc;
            document.querySelector('.receiver_udc').value = receiver_udc;
            document.querySelector('.encoded_by').value = encoded_by;
            document.querySelector('.encoded_from').value = encoded_from;
            document.querySelector('.datetime_encoded').value = datetime_encoded;
            document.querySelector('.forwarded_by').value = forwarded_by;
            document.querySelector('.process_status').value = process_status;
            document.querySelector('.sender_remarks').value = sender_remarks;
            document.querySelector('.combined_remarks').value = remarks;
            document.querySelector('.voucher_type').value = voucher_type;
            var processHistoryInput = document.getElementById('process_history');
            if (processHistoryInput) {
                processHistoryInput.value = process_history;
            }

            // Populate COA options if they exist
            const selectedCoaOptionsInput = document.getElementById('selected_coa_options');
            const selectedCoaOptionsContainer = document.getElementById('selected-coa-options-container');
            const viewCoaBtn = document.getElementById('view_coa_requirements_btn');

            if (coa_options && coa_options.trim() !== '') {
                try {
                    const coaOptionsJson = JSON.parse(coa_options);
                    console.log('COA options found:', coaOptionsJson.length, 'options');

                    if (selectedCoaOptionsInput) {
                        selectedCoaOptionsInput.value = coa_options;
                    }

                    // Store the parsed COA options and category/subsection for the view button
                    if (viewCoaBtn) {
                        viewCoaBtn.dataset.coaOptions = coa_options;
                        viewCoaBtn.dataset.coaCategory = coa_category || '';
                        viewCoaBtn.dataset.coaSubsection = coa_subsection || '';
                        console.log('Button dataset updated:', {
                            hasOptions: !!viewCoaBtn.dataset.coaOptions,
                            category: viewCoaBtn.dataset.coaCategory,
                            subsection: viewCoaBtn.dataset.coaSubsection
                        });

                        // Handler should already be attached, but ensure it's working
                        // The handler is attached on page load, so it should work
                    }

                    // Show the button container - do this with high priority
                    if (selectedCoaOptionsContainer) {
                        selectedCoaOptionsContainer.style.display = 'block';
                        selectedCoaOptionsContainer.style.visibility = 'visible';
                        // Force show with important
                        selectedCoaOptionsContainer.setAttribute('style', 'display: block !important; visibility: visible !important;');
                        console.log('Button container shown');
                    } else {
                        console.error('Button container not found!');
                    }
                } catch (e) {
                    console.error('Error parsing COA options:', e);
                }
            } else {
                // Clear COA options if none exist
                console.log('No COA options found in database');
                if (selectedCoaOptionsInput) {
                    selectedCoaOptionsInput.value = '';
                }
                if (selectedCoaOptionsContainer) {
                    selectedCoaOptionsContainer.style.display = 'none';
                }
                if (viewCoaBtn) {
                    viewCoaBtn.dataset.coaOptions = '';
                    viewCoaBtn.dataset.coaCategory = '';
                    viewCoaBtn.dataset.coaSubsection = '';
                }
            }

            const originalStringInput = document.getElementById('original_string_amount');
            const chargedStringInput = document.getElementById('charged_string_amount');
            if (originalStringInput) originalStringInput.disabled = true;
            if (chargedStringInput) chargedStringInput.disabled = true;

            populateAmountSplitView(amountOriginal, charged_amount);

            const grossHiddenInput = document.getElementById('gross_amount');
            if (grossHiddenInput) {
                grossHiddenInput.value = normalizeAmountInput(amountOriginal);
            }

            const stringAmountInput = document.querySelector('.string_amount');
            const hasCharged = hasDistinctNetAmount(amountOriginal, charged_amount);
            if (stringAmountInput && hasCharged) {
                stringAmountInput.removeAttribute('required');
            } else if (stringAmountInput) {
                stringAmountInput.setAttribute('required', 'required');
            }

            if (name === "btn-view") {
                setVoucherPortalViewMode(true);
                document.getElementById("form_title").textContent = "View Voucher";
                if (typeof openPopup === 'function') {
                    openPopup();
                } else {
                    document.getElementById('popupForm').style.display = 'block';
                    document.getElementById('overlay').style.display = 'block';
                }
                return;
            } else if (name === "btn-receive") {
                setVoucherPortalViewMode(false);
                document.getElementById("myIncomingForm").setAttribute('action', '../../protected/handler/voucher_incoming_module/voucher_incoming_handler.php');
                document.querySelector(".btn-dynamic").textContent = "Receive";
                document.getElementById("form_title").textContent = "Receive Voucher";
                document.querySelector(".btn-dynamic").setAttribute("name", "receive_voucher");
                document.querySelector(".btn-dynamic").classList.remove("warning");
                document.querySelector(".btn-dynamic").classList.add("success");
                applyIncomingDvNoRules(voucher_type, process_history, row);
                const isAccountingRole = targetArray2.includes("Accounting Unit")
                    || targetArray2.includes("Processor")
                    || targetArray2.includes("Accountant III");
                if (isAccountingRole && chargedStringInput && hasCharged) {
                    chargedStringInput.disabled = false;
                    chargedStringInput.readOnly = false;
                }
            } else if (name === "btn-return") {
                setVoucherPortalViewMode(false);
                document.getElementById("myIncomingForm").setAttribute('action', '../../protected/handler/voucher_return_module/voucher_return_handler.php');
                document.getElementById("dv_no").required = false;
                document.querySelector(".btn-dynamic").textContent = "Return";
                document.getElementById("form_title").textContent = "Return Voucher";
                document.querySelector(".btn-dynamic").setAttribute("name", "return_voucher");
                document.querySelector(".btn-dynamic").classList.remove("success");
                document.querySelector(".btn-dynamic").classList.add("warning");
                var process_history_cell = row.querySelector('[data-label="process_history"]');
                var process_history = process_history_cell ? process_history_cell.textContent.trim() : '';
                var office_from_cell = row.querySelector('[data-label="office_from"]');
                var office_from = office_from_cell ? office_from_cell.textContent.trim() : '';
                if (typeof openReturnOptionsPopup === 'function') {
                    openReturnOptionsPopup(
                        processing_no,
                        process_history,
                        office_from,
                        String(encoded_from || '').trim(),
                        String(encoded_by || '').trim()
                    );
                }
            }

        });
    });
</script>
<script>
    (function() {
        const form = document.getElementById('myIncomingForm');
        if (!form) return;

        form.addEventListener('submit', function(e) {
            const actionButton = document.querySelector('.btn-dynamic');
            const actionName = actionButton ? actionButton.getAttribute('name') : '';
            const isAccountingRole = targetArray2.includes("Accounting Unit") || targetArray2.includes("Processor") || targetArray2.includes("Accountant III");

            if (actionName === 'receive_voucher' && isAccountingRole) {
                const dvInput = document.getElementById('dv_no');
                if (!dvInput || !dvInput.required) {
                    return;
                }
                const dvValue = dvInput ? String(dvInput.value || '').trim() : '';
                if (dvValue === '' || dvValue.toUpperCase() === 'TBD') {
                    e.preventDefault();
                    if (typeof showNotify === 'function') {
                        showNotify("Please enter a valid DV No. before receiving. Empty or 'TBD' is not allowed.", 'error', 3200);
                    }
                    if (dvInput) dvInput.focus();
                }
            }
        });
    })();
</script>
<script>
    (function() {
        const modal = document.getElementById('historyModal');
        const overlay = document.getElementById('historyOverlay');
        const closeX = document.getElementById('close_history_modal');
        const closeBtn = document.getElementById('close_history_modal_btn');

        function close() {
            if (modal) modal.style.display = 'none';
            if (overlay) overlay.style.display = 'none';
        }

        if (closeX) closeX.addEventListener('click', close);
        if (closeBtn) closeBtn.addEventListener('click', close);
        if (overlay) overlay.addEventListener('click', close);
    })();
</script>
<script>
    // Incoming: COA/checklist selections are NOT edited here (only displayed).
    (function() {
        const viewBtn = document.getElementById('view_coa_requirements_btn');
        const modal = document.getElementById('coaOptionsModal');
        const overlay = document.getElementById('coa_modal_overlay');
        const modalTitle = document.getElementById('coa_modal_title');
        const optionsList = document.getElementById('coa_options_list');

        const closeX = document.getElementById('close_coa_modal');
        const cancelBtn = document.getElementById('coa_modal_cancel');
        const selectAllBtn = document.getElementById('coa_modal_select_all');
        const saveBtn = document.getElementById('coa_modal_save');

        function closeModal() {
            if (modal) modal.style.display = 'none';
            if (overlay) overlay.style.display = 'none';
        }

        if (closeX) closeX.addEventListener('click', closeModal);
        if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
        if (overlay) overlay.addEventListener('click', closeModal);

        if (selectAllBtn) selectAllBtn.disabled = true;
        if (saveBtn) saveBtn.disabled = true;

        function normalizeIncomingCoaSelections(parsed) {
            if (parsed == null) return [];
            if (typeof parsed === 'string') {
                var t = parsed.trim();
                if (!t) return [];
                try {
                    return normalizeIncomingCoaSelections(JSON.parse(t));
                } catch (e) {
                    return [{
                        label: t
                    }];
                }
            }
            if (Array.isArray(parsed)) {
                return parsed;
            }
            if (typeof parsed === 'object') {
                if (Array.isArray(parsed.items)) return parsed.items;
                return Object.keys(parsed).filter(function(k) {
                    return /^\d+$/.test(k);
                }).sort(function(a, b) {
                    return Number(a) - Number(b);
                }).map(function(k) {
                    return parsed[k];
                });
            }
            return [];
        }

        function incomingCoaItemLabel(opt) {
            if (opt == null) return '';
            if (typeof opt === 'string' || typeof opt === 'number') return String(opt).trim();
            if (typeof opt === 'object') {
                return String(opt.label || opt.value || opt.text || '').trim();
            }
            return '';
        }

        if (viewBtn) {
            viewBtn.addEventListener('click', function() {
                const raw = this.dataset.coaOptions || document.getElementById('selected_coa_options')?.value || '';
                const voucherType = document.getElementById('voucher_type')?.value || '';

                if (!raw || String(raw).trim() === '') {
                    if (typeof showNotify === 'function') showNotify('No checklist requirements found for this voucher.', 'warning', 3000);
                    return;
                }

                let selected = [];
                try {
                    selected = normalizeIncomingCoaSelections(JSON.parse(String(raw).trim()));
                } catch (e) {
                    selected = [{
                        label: String(raw)
                    }];
                }

                if (modalTitle) modalTitle.textContent = 'Selected Requirements' + (voucherType ? ' - ' + voucherType : '');
                if (optionsList) {
                    optionsList.innerHTML = '';
                    selected.forEach((opt, idx) => {
                        const labelText = incomingCoaItemLabel(opt);
                        if (!labelText) return;
                        const isChecked = (opt && typeof opt === 'object' && Object.prototype.hasOwnProperty.call(opt, 'checked')) ?
                            (opt.checked !== false && opt.checked !== 0 && opt.checked !== '0') :
                            true;
                        const label = document.createElement('label');
                        label.className = 'coa-requirement-view-only';
                        const checkbox = document.createElement('input');
                        checkbox.type = 'checkbox';
                        checkbox.checked = !!isChecked;
                        checkbox.name = 'coa_options_view_only[]';
                        checkbox.value = labelText;
                        checkbox.setAttribute('data-id', String(opt && typeof opt === 'object' && opt.id != null ? opt.id : idx + 1));
                        checkbox.tabIndex = -1;
                        const span = document.createElement('span');
                        span.textContent = labelText;
                        label.appendChild(checkbox);
                        label.appendChild(span);
                        optionsList.appendChild(label);
                    });
                }

                if (modal) modal.style.display = 'block';
                if (overlay) overlay.style.display = 'block';
            });
        }
    })();
</script>
<script>
    // Return options popup (triggered by Return button in Incoming table)
    (function() {
        const popup = document.getElementById('returnOptionsPopup');
        const overlay = document.getElementById('returnOptionsOverlay');
        const closeBtn = document.getElementById('close_return_options');
        const cancelBtn = document.getElementById('cancel_return_options');
        const confirmBtn = document.getElementById('confirm_return_options');

        var currentUserName = '<?php echo htmlspecialchars($_SESSION["logged_user_emp_name"] ?? "", ENT_QUOTES); ?>';
        var returnPreviousAllowedUnits = <?php echo json_encode(array_values($return_previous_allowed_units), JSON_UNESCAPED_UNICODE); ?>;

        var sectionMap = {
            'BUDGET': 'Budget Unit',
            'BUDGET UNIT': 'Budget Unit',
            'ACCOUNTING': 'Accounting Unit',
            'ACCOUNTING UNIT': 'Accounting Unit',
            'ACCOUNTANT III': 'Accountant III',
            'PLANNING': 'Planning Section',
            'PLANNING SECTION': 'Planning Section',
            'CONSERVATION & DEVELOPMENT SECTION': 'Conservation & Development Section',
            'CDS': 'Conservation & Development Section',
            'TSD-ENGP': 'TSD-ENGP',
            'ENGP FOCAL PERSON': 'TSD-ENGP',
            'CASHIERS': 'Cashiers Unit',
            'CASHIERS UNIT': 'Cashiers Unit',
            'PENRO': 'Office of the PENRO',
            'PENRO OFFICE': 'Office of the PENRO',
            'OFFICE OF THE PENRO': 'Office of the PENRO',
            'ICU': 'ICU'
        };

        function isReturnPreviousUnitAllowed(unitLabel) {
            if (!unitLabel) return false;
            var normalized = normalizeUnitLabel(unitLabel);
            var candidates = [String(unitLabel).trim(), normalized];
            if (String(unitLabel).trim().toUpperCase() === 'ACCOUNTANT III') {
                candidates.push('Accountant III');
            }
            for (var i = 0; i < returnPreviousAllowedUnits.length; i++) {
                var allowed = String(returnPreviousAllowedUnits[i] || '').trim();
                if (!allowed) continue;
                for (var j = 0; j < candidates.length; j++) {
                    if (candidates[j] && candidates[j].toLowerCase() === allowed.toLowerCase()) {
                        return true;
                    }
                }
            }
            return false;
        }

        function normalizeUnitLabel(raw) {
            if (!raw) return '';
            var s = String(raw).trim();
            if (!s) return '';
            var mapped = sectionMap[s.toUpperCase()] || s;
            mapped = String(mapped).trim();
            if (mapped.toUpperCase() === 'TSD') {
                for (var i = 0; i < returnPreviousAllowedUnits.length; i++) {
                    if (String(returnPreviousAllowedUnits[i] || '').trim().toLowerCase() === 'tsd-engp') {
                        return 'TSD-ENGP';
                    }
                }
            }
            return mapped;
        }

        function normalizePersonName(name) {
            return String(name || '')
                .replace(/\bencoded\s+by\b\s*:?/gi, '')
                .replace(/[,]/g, ' ')
                .replace(/\s+/g, ' ')
                .trim()
                .toLowerCase();
        }

        function nameTokens(name) {
            return normalizePersonName(name).split(' ').filter(function(t) {
                return t.length > 1;
            });
        }

        function employeeNamesMatch(a, b) {
            var na = normalizePersonName(a);
            var nb = normalizePersonName(b);
            if (!na || !nb) return false;
            if (na === nb) return true;
            if (na.indexOf(nb) !== -1 || nb.indexOf(na) !== -1) return true;
            var tokensA = nameTokens(a);
            var tokensB = nameTokens(b);
            if (tokensA.length === 0 || tokensB.length === 0) return false;
            var shorter = tokensA.length <= tokensB.length ? tokensA : tokensB;
            var longer = tokensA.length <= tokensB.length ? tokensB : tokensA;
            var longerStr = ' ' + longer.join(' ') + ' ';
            for (var i = 0; i < shorter.length; i++) {
                if (longerStr.indexOf(' ' + shorter[i] + ' ') === -1) {
                    return false;
                }
            }
            return true;
        }

        function parseProcessHistoryLine(line) {
            var user = '';
            var action = '';
            var section = '';
            if (line.indexOf('|') !== -1) {
                var pipeParts = line.split(/\s*\|\s*/);
                user = (pipeParts[0] || '').trim();
                action = (pipeParts[1] || '').trim();
                section = (pipeParts[2] || '').trim();
            } else {
                var colonParts = line.split(/\s*:\s*/);
                user = (colonParts[0] || '').trim();
                if (colonParts.length >= 3) {
                    action = (colonParts[1] || '').trim();
                    section = (colonParts.slice(2).join(' : ')).trim();
                } else {
                    section = (colonParts[1] || '').trim();
                }
            }
            return { user: user, action: action, section: section };
        }

        function isPersonInvolved(user, action, personName) {
            if (!personName) return false;
            if (employeeNamesMatch(user, personName)) return true;
            if (employeeNamesMatch(action, personName)) return true;
            var encodedInAction = String(action || '').match(/encoded\s+by\s*:?\s*(.+)$/i);
            if (encodedInAction && encodedInAction[1] && employeeNamesMatch(encodedInAction[1], personName)) {
                return true;
            }
            return false;
        }

        function parseProcessHistory(processHistory, encodedBy) {
            var offices = [],
                seen = {};
            if (!processHistory || !processHistory.trim()) return offices;
            var normalized = String(processHistory).replace(/\\n/g, '\n');
            var lines = normalized.split(/\r\n|\r|\n/);
            for (var i = 0; i < lines.length; i++) {
                var line = lines[i].trim();
                if (!line) continue;
                var parsed = parseProcessHistoryLine(line);
                if (isPersonInvolved(parsed.user, parsed.action, encodedBy)) continue;
                if (isPersonInvolved(parsed.user, parsed.action, currentUserName)) continue;
                if (!parsed.section) continue;
                var mapped = normalizeUnitLabel(parsed.section);
                if (mapped && !seen[mapped]) {
                    seen[mapped] = true;
                    offices.push(mapped);
                }
            }
            return offices;
        }

        function loadReturnOffices(processingNo, processHistory, officeFrom, encodedFrom, encodedBy) {
            var selectEl = document.getElementById('return_office_select');
            var containerEl = document.getElementById('return_office_container');
            if (!selectEl) return;

            selectEl.innerHTML = '<option value="" disabled selected>Select previous process</option>';
            if (containerEl) containerEl.style.display = 'none';

            // Same unit is allowed when another employee handled that history step.
            // parseProcessHistory skips lines where the current user or encoder was the employee.
            var offices = parseProcessHistory(processHistory || '', encodedBy);
            offices = offices.filter(function(office) {
                return isReturnPreviousUnitAllowed(office);
            });
            if (offices.length === 0 && officeFrom) {
                var fallbackUnit = normalizeUnitLabel(officeFrom);
                if (fallbackUnit && isReturnPreviousUnitAllowed(fallbackUnit)) {
                    offices = [fallbackUnit];
                }
            }

            var previousSenderOption = document.getElementById('return_previous_sender_option');
            if (previousSenderOption) {
                previousSenderOption.style.display = offices.length > 0 ? 'flex' : 'none';
            }

            offices.forEach(function(office) {
                var opt = document.createElement('option');
                opt.value = office;
                opt.textContent = office;
                selectEl.appendChild(opt);
            });
        }

        function updateReturnConfirmLabel() {
            const selected = document.querySelector('input[name="return_destination_popup"]:checked');
            if (!confirmBtn) return;
            confirmBtn.textContent = selected && selected.value === 'retract' ? 'Retract' : 'Return';
        }

        function showPopup(processingNo, processHistory, officeFrom, encodedFrom, encodedBy) {
            if (popup) popup.style.display = 'block';
            if (overlay) overlay.style.display = 'block';
            document.getElementById("confirm_return_options").setAttribute("name", "return_voucher");
            loadReturnOffices(processingNo, processHistory, officeFrom, encodedFrom, encodedBy);
            updateReturnConfirmLabel();
        }

        function hidePopup() {
            if (popup) popup.style.display = 'none';
            if (overlay) overlay.style.display = 'none';

            const radios = document.querySelectorAll('input[name="return_destination_popup"]');
            radios.forEach(r => {
                r.checked = false;
            });

            const returnOfficeContainer = document.getElementById('return_office_container');
            const returnOfficeSelect = document.getElementById('return_office_select');
            if (returnOfficeContainer) {
                returnOfficeContainer.style.display = 'none';
            }
            if (returnOfficeSelect) {
                returnOfficeSelect.selectedIndex = 0;
            }

            const remarksField = document.getElementById('return_remarks_popup');
            if (remarksField) remarksField.value = '';
            updateReturnConfirmLabel();
        }

        // Expose to row click handler
        window.openReturnOptionsPopup = showPopup;

        if (closeBtn) closeBtn.addEventListener('click', hidePopup);
        if (cancelBtn) cancelBtn.addEventListener('click', hidePopup);
        if (overlay) overlay.addEventListener('click', hidePopup);

        // When "previous process" is selected, show the dropdown
        const previousSenderRadio = document.querySelector('input[name="return_destination_popup"][value="previous_sender"]');
        const encoderRadio = document.querySelector('input[name="return_destination_popup"][value="encoder"]');
        const retractRadio = document.querySelector('input[name="return_destination_popup"][value="retract"]');
        const returnOfficeContainer = document.getElementById('return_office_container');

        document.querySelectorAll('input[name="return_destination_popup"]').forEach(function(radio) {
            radio.addEventListener('change', function() {
                if (returnOfficeContainer) {
                    returnOfficeContainer.style.display = (previousSenderRadio && previousSenderRadio.checked) ? 'block' : 'none';
                }
                updateReturnConfirmLabel();
            });
        });

        if (previousSenderRadio && returnOfficeContainer) {
            previousSenderRadio.addEventListener('change', function() {
                if (this.checked) {
                    returnOfficeContainer.style.display = 'block';
                }
            });
        }

        if (encoderRadio && returnOfficeContainer) {
            encoderRadio.addEventListener('change', function() {
                if (this.checked) {
                    returnOfficeContainer.style.display = 'none';
                }
            });
        }

        if (retractRadio && returnOfficeContainer) {
            retractRadio.addEventListener('change', function() {
                if (this.checked) {
                    returnOfficeContainer.style.display = 'none';
                }
            });
        }

        if (confirmBtn) {
            confirmBtn.addEventListener('click', function() {
                const selected = document.querySelector('input[name="return_destination_popup"]:checked');
                if (!selected) {
                    if (typeof showNotify === 'function') {
                        showNotify('Please select where to return the voucher.', 'error', 3000);
                    }
                    return;
                }

                const destinationValue = selected.value;
                const remarksValue = (document.getElementById('return_remarks_popup')?.value || '').trim();

                const destinationInput = document.getElementById('return_destination');
                const returnTargetEl = document.getElementById('return_target_section');
                const remarksInput = document.querySelector('#myIncomingForm .remarks');
                const form = document.getElementById('myIncomingForm');

                if (destinationValue === 'retract') {
                    if (remarksInput) {
                        remarksInput.value = remarksValue === '' ? 'NULL' : remarksValue;
                    }
                    if (form) {
                        form.setAttribute('action', '../../protected/handler/voucher_return_module/voucher_retract_handler.php');
                        const hiddenRetractSubmit = document.getElementById('hidden_retract_submit');
                        if (hiddenRetractSubmit) {
                            hiddenRetractSubmit.click();
                        } else {
                            form.submit();
                        }
                    }
                    hidePopup();
                    return;
                }

                if (destinationInput) destinationInput.value = destinationValue;
                if (returnTargetEl) returnTargetEl.value = '';
                if (destinationValue === 'previous_sender') {
                    const office = document.getElementById('return_office_select')?.value || '';
                    if (!office) {
                        if (typeof showNotify === 'function') {
                            showNotify('Please select the previous process to return to.', 'error', 3000);
                        }
                        return;
                    }
                    if (returnTargetEl) returnTargetEl.value = office;
                }
                if (remarksInput) {
                    // If remarks are empty, send explicit null marker so backend can treat as NULL
                    remarksInput.value = remarksValue === '' ? 'NULL' : remarksValue;
                }

                if (form) {
                    form.setAttribute('action', '../../protected/handler/voucher_return_module/voucher_return_handler.php');
                    // Use a hidden submit button with name="return_voucher" so the handler
                    // sees $_REQUEST['return_voucher'] and does not treat this as a wrong module.
                    const hiddenReturnSubmit = document.getElementById('hidden_return_submit');
                    if (hiddenReturnSubmit) {
                        hiddenReturnSubmit.click();
                    } else {
                        form.submit();
                    }
                }

                hidePopup();
            });
        }
    })();
</script>
<?php require_once __DIR__ . '/../../protected/core/components/notifications/notification_flash.inc.php'; ?>
</body>

</html>