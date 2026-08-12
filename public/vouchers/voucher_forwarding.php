<?php
include('../includes/header.php');
require_once __DIR__ . '/../../protected/core/components/helpers/audit_helper.inc.php';
AuditHelper::logPageView('Voucher Forwarding');
include('../../protected/handler/voucher_receiving_module/voucher_receiving_errhandler.inc.php');
include('../../protected/handler/voucher_archiving_module/voucher_archiving_errhandler.inc.php');
include('../../protected/handler/voucher_return_module/voucher_return_errhandler.inc.php');
require '../../protected/core/components/notifications/err_handler_custom_alert.php';
require_once __DIR__ . '/../../protected/core/components/notifications/custom_alert.php';
require_once __DIR__ . '/../../protected/core/components/notifications/notification.inc.php';

check_voucher_receiving_errors();
check_voucher_archiving_errors();
check_voucher_return_errors();

require_once __DIR__ . '/checklist_config.php';
require_once __DIR__ . '/../../protected/core/components/security/filter_input.inc.php';
require_once __DIR__ . '/../../protected/core/components/helpers/cursor_pagination_helper.php';
require_once __DIR__ . '/../../protected/core/components/helpers/voucher_portal_query_helper.php';
require_once __DIR__ . '/../../protected/core/components/helpers/voucher_tracking_helper.inc.php';
require_once __DIR__ . '/../../protected/core/components/helpers/amount_helper.inc.php';
require_once __DIR__ . '/../../protected/core/components/helpers/utilities_return_previous_helper.inc.php';
utilities_return_previous_ensure_schema($pdo);
$return_previous_allowed_units = utilities_return_previous_active_designations($pdo);
$dashboard_voucher_types = checklist_types_with_labels();
$voucher_type_filter = isset($_GET['voucher_type']) && $_GET['voucher_type'] !== 'all' ? trim((string) $_GET['voucher_type']) : 'all';

$rowsPerPage = clamp_int($_GET['rowsPerPage'] ?? null, 1, 50, 50);
$maxBrowse = 100;
$rawQ = (string) ($_GET['q'] ?? '');
$q = filterInput($rawQ);
$invalidSearch = (trim($rawQ) !== '' && $q === '');

$receivingSearchCols = [
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
    'transmit',
    'process_history',
    'coa_options',
    'coa_category',
    'coa_subsection',
    'supporting_documents',
];

$searchParams = [];
$searchSql = '';
if (!$invalidSearch && $q !== '') {
    $searchSql = voucher_portal_like_search_fragment($pdo, 'voucher_receiving', $q, $receivingSearchCols, $searchParams);
}

$udc_param = '%' . $_SESSION['logged_user_udc'] . '%';

if ($invalidSearch) {
    $dbCount = 0;
} else {
    $countSql = 'SELECT COUNT(*) AS total FROM voucher_receiving WHERE receiver_udc LIKE :udc AND office_to = :office_to';
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

$dataSql = 'SELECT * FROM voucher_receiving WHERE receiver_udc LIKE :udc AND office_to = :office_to';
if ($voucher_type_filter !== 'all') {
    $dataSql .= ' AND voucher_type = :voucher_type';
}
$dataSql .= $searchSql . ' ORDER BY processing_no DESC LIMIT :lim OFFSET :off';
$fetch_voucher_receiving_data = $pdo->prepare($dataSql);
$fetch_voucher_receiving_data->bindValue(':udc', $udc_param, PDO::PARAM_STR);
$fetch_voucher_receiving_data->bindValue(':office_to', $_SESSION['logged_user_office'], PDO::PARAM_STR);
if ($voucher_type_filter !== 'all') {
    $fetch_voucher_receiving_data->bindValue(':voucher_type', $voucher_type_filter, PDO::PARAM_STR);
}
foreach ($searchParams as $key => $pair) {
    $fetch_voucher_receiving_data->bindValue($key, $pair[0], $pair[1]);
}
$fetch_voucher_receiving_data->bindValue(':lim', $fetchLimit, PDO::PARAM_INT);
$fetch_voucher_receiving_data->bindValue(':off', $offset, PDO::PARAM_INT);
$fetch_voucher_receiving_data->execute();
$forwardingRows = $fetch_voucher_receiving_data->fetchAll(PDO::FETCH_ASSOC);
$forwardingHistoryMap = voucher_tracking_fetch_display_history_map(
    $pdo,
    array_column($forwardingRows, 'processing_no')
);

$totalRows = $displayTotal;

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

$target = array_values(array_filter(array_map(
    'trim',
    explode(',', (string) ($_SESSION['logged_user_designation'] ?? ''))
)));
$isLiaisonOfficer = in_array('Liaison Officer', $target, true);
$bulkForwardToken = (string) ($_SESSION['token'] ?? '');
$showCashierArchiveCol = in_array("Cashiers Unit", $target, true) || in_array("Cashier", $target, true);
$isCashierBulkPay = $showCashierArchiveCol;
$hideForwardForCashiersUnit = in_array("Cashiers Unit", $target, true);
$showForwardCol = !$hideForwardForCashiersUnit;
$showProcessCol = in_array("Accounting Unit", $target) || in_array("Processor", $target);
$isAlternateWorkflowRole = $showProcessCol || in_array('ICU', $target, true);
$loggedUserOffice = voucher_logged_user_office();
$showTransmitCol = (
    (!in_array("Accountant III", $target) && in_array("Accounting Unit", $target))
    || (in_array("Budget Unit", $target) && !in_array("Budget Officer", $target))
    || in_array("Office of the PENRO", $target)
    || (in_array("Planning Section", $target) && !in_array("Planning Section Chief", $target))
    || in_array("Processor", $target)
);
$showEditCol = (
    in_array('Accounting Unit', $target, true)
    || in_array('Processor', $target, true)
    || in_array('Accountant III', $target, true)
    || in_array('Budget Unit', $target, true)
    || in_array('Cashiers Unit', $target, true)
    || in_array('Budget Officer', $target, true)
    || in_array('ICU', $target, true)
);

$ada_options = [];
$ada_option_defaults = [];
$ada_signatory_bundles_indexed = [];
$ada_signatory_offices = [];
$ada_can_select_signatory_office = false;
$ada_default_office = '';
if ($showCashierArchiveCol) {
    try {
        require_once __DIR__ . '/../../protected/core/components/helpers/utilities_signatory_helper.inc.php';
        utilities_signatory_ensure_schema($pdo);
        $ada_signatory_bundles_indexed = utilities_fetch_ada_signatory_bundles_indexed($pdo);
        $ada_default_office = utilities_signatory_default_office();
        $ada_can_select_signatory_office = utilities_signatory_can_select_office();
        $ada_signatory_offices = utilities_signatory_fetch_offices($pdo);
        $adaBundle = utilities_fetch_ada_signatory_bundle($pdo, $ada_default_office);
        $ada_options = $adaBundle['options'];
        $ada_option_defaults = $adaBundle['defaults'];
    } catch (Throwable $e) {
        $ada_options = [];
        $ada_option_defaults = [];
        $ada_signatory_bundles_indexed = [];
        $ada_signatory_offices = [];
        $ada_can_select_signatory_office = false;
        $ada_default_office = '';
    }
}
?>
<!--=============== MAIN ===============!-->
<div class="main main--voucher-dashboard" id="main">
    <header class="voucher-dashboard-header">
        <h1 class="voucher-dashboard-title">Forwarding</h1>
    </header>
    <style>
        /* Keep forwarding filter toolbar in one row */
        #forwardingFilterForm {
            display: flex;
            align-items: center;
            flex-wrap: nowrap !important;
            width: 100%;
            gap: 10px;
        }

        #forwardingFilterForm .filter-chips {
            flex: 0 0 auto;
            flex-wrap: nowrap !important;
        }

        #forwardingFilterForm .filter-search {
            flex: 1 1 auto;
            min-width: 0 !important;
        }

        /* Modernized voucher type dropdown (matches incoming toolbar) */
        #forwardingFilterForm .filter-type-select.filter-type-select--modern {
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

        #forwardingFilterForm .filter-type-select.filter-type-select--modern::after {
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

        #forwardingFilterForm .filter-type-select.filter-type-select--modern select {
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

        #forwardingFilterForm .filter-type-select.filter-type-select--modern.filter-type-select--custom::after {
            transform: translateY(-60%) rotate(45deg);
            transition: transform 120ms ease;
        }

        #forwardingFilterForm .filter-type-select.filter-type-select--modern.filter-type-select--custom.is-open::after {
            transform: translateY(-35%) rotate(-135deg);
        }

        #forwardingFilterForm .filter-type-select.filter-type-select--modern.filter-type-select--custom .filter-type-trigger {
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

        #forwardingFilterForm .filter-type-select.filter-type-select--modern.filter-type-select--custom .filter-type-menu {
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

        #forwardingFilterForm .filter-type-select.filter-type-select--modern.filter-type-select--custom.is-open .filter-type-menu {
            display: block;
        }

        #forwardingFilterForm .filter-type-select.filter-type-select--modern.filter-type-select--custom .filter-type-option {
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

        #forwardingFilterForm .filter-type-select.filter-type-select--modern.filter-type-select--custom .filter-type-option:hover {
            background: #f3f6fb;
        }

        #forwardingFilterForm .filter-type-select.filter-type-select--modern.filter-type-select--custom .filter-type-option.is-active {
            background: #e8f0ff;
            color: #1d4ed8;
            font-weight: 600;
        }

        #forwardingFilterForm .filter-type-select.filter-type-select--modern:hover {
            border-color: #c2ccda;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
        }

        #forwardingFilterForm .filter-type-select.filter-type-select--modern:focus-within {
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
                <form method="GET" action="" id="forwardingFilterForm" class="filter-toolbar-form">
                    <div class="filter-chips" aria-label="Voucher filter tools">
                        <a class="filter-icon-btn" href="voucher_forwarding.php" aria-label="Home">
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
            <form action="#" class="f-container" method="post" id="myForm_Forwarding">
                <div class="box-body__container flex-row">
                    <div class="popupForm-body__container">
                        <div class="form-container">
                            <div class="label-input__container">
                                <label for="">Processing No.</label>
                                <input type="text" name="processing_no" class="processing_no form-custom-input" id="processing_no" value="" placeholder="Processing No." required readonly>
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
                            <div class="label-input__container">
                                <label for="">Payee</label>
                                <input type="text" name="payee" class="payee form-custom-input" id="payee" value="" placeholder="Payee" required readonly>
                            </div>
                            <div class="label-input__container">
                                <label for="">Address</label>
                                <input type="text" name="address" class="address form-custom-input" id="address" value="" placeholder="Address" required readonly>
                            </div>
                        </div>
                    </div>
                    <div class="popupForm-body__container">
                        <div class="form-container">
                            <div class="label-input__container">
                                <label for="">Particulars</label>
                                <textarea name="particulars" id="particulars" cols="30" rows="10" class="multi-line-input particulars form-custom-multi-input" placeholder="Particulars ...." required readonly></textarea>
                            </div>
                            <div class='label-input__container input-dynamic'>
                                <label for=''>Forward To</label>
                                <select name='document_to' class='form-custom-input' id="document_to" required<?= $isLiaisonOfficer ? ' data-liaison-only="1"' : '' ?>>
                                    <?php if ($isLiaisonOfficer) : ?>
                                        <option value='ICU' selected>ICU</option>
                                    <?php else : ?>
                                        <option value="" disabled selected>Please Select</option>
                                        <?php if (in_array("ICU", $target, true)) : ?>
                                            <option value='Budget Unit'>Budget Unit</option>
                                            <option value='Accounting Unit'>Accounting Unit</option>
                                            <option value='Planning Section'>Planning Section</option>
                                        <?php elseif (in_array("Planning Section", $target)) : ?>
                                            <option value='Budget Unit'>Budget Unit</option>
                                            <?php if (!in_array("Planning Section Chief", $target)) : ?>
                                                <option value='Planning Section Chief' class="Planning_Officer">Planning Section Chief</option>
                                            <?php endif ?>
                                        <?php elseif (in_array("Conservation & Development Section", $target)) : ?>
                                            <option value='Accounting Unit'>Accounting Unit</option>
                                        <?php elseif (in_array("Budget Unit", $target)) : ?>
                                            <option value='Accounting Unit'>Accounting Unit</option>
                                            <option value='Accountant III' class="processed">Chief Accountant</option>
                                            <?php if (!in_array("Budget Officer", $target)) : ?>
                                                <option value='Budget Officer' class="Budget_Officer">Budget Officer</option>
                                            <?php endif ?>
                                        <?php elseif (in_array("Accounting Unit", $target) or in_array("Processor", $target)) : ?>
                                            <?php if (in_array("Accounting Unit", $target)) : ?>
                                                <optgroup label="Processed" class="Processed">
                                                    <option value='Accountant III' class="processed">Chief Accountant</option>
                                                    <option value='Office of the PENRO' class="processed">Office of the PENRO</option>
                                                    <option value='Cashiers Unit' class="processed">Cashiers Unit</option>
                                                </optgroup>
                                                <?php if (in_array("Processor", $target)) : ?>
                                                    <optgroup label="Processor" class="Processor">
                                                        <?php if (!isset($_SESSION['logged_user_udc']) || $_SESSION['logged_user_udc'] !== '4HyLy') : ?>
                                                            <option value='4HyLy'>1. Marife C. Briton</option>
                                                        <?php endif; ?>
                                                        <?php if (!isset($_SESSION['logged_user_udc']) || $_SESSION['logged_user_udc'] !== 'YS9M3') : ?>
                                                            <option value='YS9M3'>2. Diana E. Costuna </option>
                                                        <?php endif; ?>
                                                        <?php if (!isset($_SESSION['logged_user_udc']) || $_SESSION['logged_user_udc'] !== 's1JxV') : ?>
                                                            <option value='s1JxV'>3. Gracile B. Palce</option>
                                                        <?php endif; ?>
                                                    </optgroup>
                                                <?php endif; ?>
                                            <?php elseif (!in_array("Accounting Unit", $target) and in_array("Processor", $target)) : ?>
                                                <option value='Accountant III' class="processed">Chief Accountant</option>
                                                <option value='Office of the PENRO' class="processed">Office of the PENRO</option>
                                                <option value='Cashiers Unit' class="processed">Cashiers Unit</option>
                                            <?php endif ?>
                                        <?php elseif (in_array("Office of the PENRO", $target)) : ?>
                                            <option value='Cashiers Unit'>Cashiers Unit</option>
                                        <?php elseif (in_array("Cashiers Unit", $target)) : ?>
                                            <option value='Accountant III'>Chief Accountant</option>
                                            <option value='Office of the PENRO'>Office of the PENRO</option>
                                            <?php if (!in_array("Cashier", $target)) : ?>
                                                <option value='Cashier' class="Cashier_Officer">Cashier</option>
                                            <?php endif ?>
                                        <?php else : ?>
                                            <option value='Accounting Unit'>Accounting Unit</option>
                                            <option value='Office of the PENRO' class="processed">Office of the PENRO</option>
                                            <option value='Cashiers Unit'>Cashiers Unit</option>
                                        <?php endif ?>
                                    <?php endif ?>
                                </select>
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
                            <div class='label-input__container'>
                                <label for=''>Remarks</label>
                                <input type='text' class='remarks form-custom-input' name='remarks' id='remarks' value='' placeholder='Remarks'>
                            </div>
                            <div class='label-input__container hidden_input'>
                                <label for=''>Combined Remarks</label>
                                <input type='text' class='combined_remarks form-custom-input' name='combined_remarks' id='combined_remarks' value='' placeholder='Combined Remarks'>
                            </div>
                            <div class="label-input__container hidden_input">
                                <label for="">Process History</label>
                                <textarea name="process_history" class="process_history form-custom-input" id="process_history" rows="4" style="resize: none;"></textarea>
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
                                <label for="">Encoded From</label>
                                <input type="text" name="encoded_from" class="encoded_from" id="encoded_from" value="">
                            </div>
                            <div class="label-input__container hidden_input">
                                <label for="">Date/Time Encoded</label>
                                <input type="text" name="datetime_encoded" class="datetime_encoded" id="datetime_encoded" value="">
                            </div>
                            <div class="label-input__container hidden_input">
                                <label for="">Forwarded By</label>
                                <input type="text" name="forwarded_by" class="forwarded_by" id="forwarded_by" value="">
                            </div>
                            <div class="label-input__container hidden_input">
                                <label for="">Sender Remarks</label>
                                <input type="text" name="sender_remarks" class="sender_remarks" id="sender_remarks" value="">
                            </div>
                            <input type="hidden" name="return_destination" id="return_destination" value="">
                            <input type="hidden" name="return_target_section" id="return_target_section" value="">
                            <input type="hidden" name="return_source" id="return_source" value="forwarding">
                            <input type="hidden" name="retract_source" id="retract_source" value="forwarding">
                            <div class="label-input__container hidden_input">
                                <label for="">Priority</label>
                                <input type="text" name="priority" class="priority form-custom-input" id="priority" value="">
                            </div>
                            <div class="label-input__container hidden_input">
                                <label for="">Transmit Status</label>
                                <input type="text" name="process_status" class="process_status" id="process_status" value="">
                            </div>
                            <div class="label-input__container hidden_input">
                                <label for="">Voucher Type</label>
                                <input type="text" name="voucher_type" class="voucher_type" id="voucher_type" value="">
                            </div>
                            <div class="label-input__container hidden_input">
                                <label for="">Selected COA Options (Forward)</label>
                                <input type="text" name="selected_coa_options_forward" class="selected_coa_options_forward" id="selected_coa_options_forward" value="">
                            </div>
                            <div class="label-input__container hidden_input">
                                <label for="">COA Category (Forward)</label>
                                <input type="text" name="coa_category_forward" class="coa_category_forward" id="coa_category_forward" value="">
                            </div>
                            <div class="label-input__container hidden_input">
                                <label for="">COA Subsection (Forward)</label>
                                <input type="text" name="coa_subsection_forward" class="coa_subsection_forward" id="coa_subsection_forward" value="">
                            </div>
                            <input type="hidden" name="token" value="<?php echo $_SESSION['token']; ?>">
                        </div>
                    </div>
                </div>
                <div class="popupForm-footer__container">
                    <div class="footer-button__container">
                        <button class="btn transparent btn-dynamic" name="" type="submit"></button>
                        <button type="submit" name="return_voucher" id="hidden_return_submit" style="display:none;"></button>
                        <button type="submit" name="retract_voucher" id="hidden_retract_submit" style="display:none;"></button>
                        <button class="btn secondary transparent" id="close_popup3" type="button">CANCEL</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <div class="overlay voucher-premium-overlay" id="overlay"></div>

    <!-- Return Options Popup -->
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
                                        <span>Retract voucher <span style="display:block; font-size: 12px; color: rgb(75 85 99 / 0.75); font-weight: normal;">Return to encoder and reset data for re-use.</span></span>
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
        <h2 class="voucher-card-title">Forwarding Summary</h2>
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


            .voucher-amount-split-panel {
                margin-top: 8px;
                padding: 14px 16px;
                border-radius: 12px;
                border: 1px solid #e2e8f0;
                background: #fafafa;
            }

            .voucher-amount-split-panel__title {
                margin: 0 0 12px;
                font-size: 11px;
                font-weight: 600;
                letter-spacing: 0.06em;
                text-transform: uppercase;
                color: #64748b;
            }

            .voucher-amount-split-panel__body {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 8px;
            }

            @media (max-width: 640px) {
                .voucher-amount-split-panel__body {
                    grid-template-columns: 1fr;
                }
            }

            .voucher-amount-split-field {
                display: flex;
                flex-direction: column;
                gap: 6px;
                min-width: 0;
            }

            .voucher-amount-split-field__label {
                margin: 0;
                font-size: 10px;
                font-weight: 600;
                letter-spacing: 0.08em;
                text-transform: uppercase;
            }

            .voucher-amount-split-field--gross .voucher-amount-split-field__label {
                color: #16a34a;
            }

            .voucher-amount-split-field--net .voucher-amount-split-field__label {
                color: #2563eb;
            }

            .voucher-amount-split-field__input {
                width: 100%;
                border-radius: 8px !important;
                border: 1px solid #e2e8f0 !important;
                background: #ffffff !important;
                padding: 10px 12px !important;
                font-size: 15px !important;
                font-weight: 600 !important;
                font-variant-numeric: tabular-nums;
                color: #0f172a !important;
                transition: border-color 140ms ease, box-shadow 140ms ease;
            }

            .voucher-amount-split-field__input:focus {
                outline: none;
                border-color: #cbd5e1 !important;
                box-shadow: 0 0 0 3px rgba(148, 163, 184, 0.15);
            }

            .voucher-amount-split-field--gross .voucher-amount-split-field__input:not([readonly]):focus {
                border-color: #86efac !important;
                box-shadow: 0 0 0 3px rgba(34, 197, 94, 0.12);
            }

            .voucher-amount-split-field--net .voucher-amount-split-field__input:not([readonly]):focus {
                border-color: #93c5fd !important;
                box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.12);
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

            #my-Table .voucher-table-actions-group .btn {
                display: inline-flex !important;
                align-items: center;
                justify-content: center;
                width: 36px !important;
                min-width: 36px !important;
                height: 36px !important;
                min-height: 36px !important;
                padding: 0 !important;
                border: 1px solid #e2e8f0 !important;
                border-radius: 8px !important;
                background: #ffffff !important;
                color: #64748b !important;
                box-shadow: none !important;
                font-size: 0 !important;
                line-height: 1;
                cursor: pointer;
                transition: background 120ms ease, border-color 120ms ease, color 120ms ease;
            }

            #my-Table .voucher-table-actions-group .btn i {
                font-size: 18px;
                line-height: 1;
            }

            #my-Table .voucher-table-actions-group .btn span {
                display: none;
            }

            #my-Table .voucher-table-actions-group .btn:hover {
                background: #f1f5f9 !important;
                border-color: #cbd5e1 !important;
                color: #475569 !important;
                transform: none;
            }

            #my-Table .voucher-table-actions-group .btn:active {
                background: #e2e8f0 !important;
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

            .voucher-bulk-forward-bar {
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

            .voucher-bulk-forward-bar.is-visible {
                display: flex;
            }

            .voucher-bulk-forward-bar label {
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

            .voucher-bulk-forward-bar label input[type="checkbox"] {
                width: 16px;
                height: 16px;
                cursor: pointer;
                accent-color: #2563eb;
            }

            .voucher-bulk-forward-btn {
                display: inline-flex;
                align-items: center;
                gap: 8px;
                min-height: 38px;
                padding: 0 16px;
                border: none;
                border-radius: 10px;
                background: linear-gradient(135deg, #2563eb, #1d4ed8);
                color: #fff;
                font-size: 13px;
                font-weight: 600;
                letter-spacing: 0.02em;
                cursor: pointer;
                box-shadow: 0 4px 12px rgba(37, 99, 235, 0.28);
                transition: transform 120ms ease, box-shadow 120ms ease, opacity 120ms ease;
            }

            .voucher-bulk-forward-btn:hover:not(:disabled) {
                transform: translateY(-1px);
                box-shadow: 0 6px 16px rgba(37, 99, 235, 0.34);
            }

            .voucher-bulk-forward-btn--pay {
                background: linear-gradient(135deg, #dc2626, #b91c1c);
                box-shadow: 0 4px 12px rgba(220, 38, 38, 0.28);
            }

            .voucher-bulk-forward-btn--pay:hover:not(:disabled) {
                box-shadow: 0 6px 16px rgba(220, 38, 38, 0.34);
            }

            .voucher-bulk-forward-btn:disabled {
                opacity: 0.55;
                cursor: not-allowed;
                box-shadow: none;
            }

            .voucher-bulk-forward-btn i {
                font-size: 16px;
                line-height: 1;
            }

            .voucher-bulk-forward-status {
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
                accent-color: #2563eb;
            }

            .bulk-pay-voucher-table {
                width: 100%;
                border-collapse: collapse;
                font-size: 13px;
            }

            .bulk-pay-voucher-table th,
            .bulk-pay-voucher-table td {
                padding: 10px 12px;
                border-bottom: 1px solid #e5e7eb;
                text-align: left;
                vertical-align: middle;
            }

            .bulk-pay-voucher-table th {
                background: #f8fafc;
                font-size: 11px;
                font-weight: 600;
                letter-spacing: 0.04em;
                text-transform: uppercase;
                color: #64748b;
            }

            .bulk-pay-ada-field {
                margin-bottom: 8px;
            }

            .bulk-pay-ada-field label {
                display: block;
                margin-bottom: 6px;
                font-size: 13px;
                font-weight: 600;
                color: #334155;
            }

            .bulk-pay-ada-field input.form-custom-input {
                width: 100%;
                max-width: 100%;
                text-align: center !important;
                padding: 5px;
            }

            .bulk-pay-voucher-hint {
                margin: 0 0 14px;
                font-size: 13px;
                color: #64748b;
            }

            .bulk-pay-voucher-list-wrap {
                max-height: 360px;
                overflow: auto;
                margin: 0;
                border: 1px solid #e5e7eb;
                border-radius: 10px;
            }

            #overlayBulkPayVoucher {
                z-index: 10000;
            }

            #bulkPayVoucherModal {
                z-index: 10001;
            }

            #bulkPayVoucherModal .popupForm-body__container {
                padding: 20px 24px 8px;
            }

            #bulkPayVoucherModal .popupForm-footer__container {
                padding: 12px 24px 20px;
            }
        </style>
        <?php if ($isLiaisonOfficer) : ?>
            <div class="voucher-bulk-forward-bar is-visible" id="voucherBulkForwardBar">
                <label>
                    <input type="checkbox" id="voucherBulkSelectAll" aria-label="Select all vouchers on this page">
                    Select all on page
                </label>
                <button type="button" class="voucher-bulk-forward-btn" id="voucherBulkForwardBtn">
                    <i class="ri-share-forward-line" aria-hidden="true"></i>
                    Forward Selected
                </button>
                <span class="voucher-bulk-forward-status" id="voucherBulkForwardStatus"></span>
            </div>
        <?php elseif ($isCashierBulkPay) : ?>
            <div class="voucher-bulk-forward-bar is-visible" id="voucherBulkPayBar">
                <label>
                    <input type="checkbox" id="voucherBulkPaySelectAll" aria-label="Select all payable vouchers on this page">
                    Select all on page
                </label>
                <button type="button" class="voucher-bulk-forward-btn voucher-bulk-forward-btn--pay" id="voucherBulkPayBtn">
                    <i class="ri-wallet-3-line" aria-hidden="true"></i>
                    Pay Selected
                </button>
                <span class="voucher-bulk-forward-status" id="voucherBulkPayStatus"></span>
            </div>
        <?php endif; ?>
        <div class="content-wrapper">
            <table class="table content_table content_table--dashboard" id="my-Table">
                <thead>
                    <tr>
                        <?php if ($isLiaisonOfficer && $showForwardCol) : ?>
                            <th class="voucher-bulk-select-cell" aria-label="Select for bulk forward"></th>
                        <?php elseif ($isCashierBulkPay) : ?>
                            <th class="voucher-bulk-select-cell" aria-label="Select for bulk pay"></th>
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
                    foreach ($forwardingRows as $row) {
                        $forwarding_process_history = voucher_tracking_enrich_process_history_for_return(
                            $pdo,
                            (string) ($row['process_history'] ?? ''),
                            (string) ($row['voucher_type'] ?? '')
                        );
                        $forwarding_process_history_display = voucher_tracking_process_history_for_display(
                            $pdo,
                            (string) ($row['processing_no'] ?? ''),
                            $forwarding_process_history,
                            $forwardingHistoryMap
                        );
                        $rowTransmitStatus = trim((string) ($row['transmit'] ?? ''));
                        $rowTransmitEmpty = $rowTransmitStatus === 'No' || $rowTransmitStatus === '';
                        $rowTransmitYes = $rowTransmitStatus === 'Yes';
                        $rowBulkPayable = $isCashierBulkPay
                            && (in_array('Cashiers Unit', $target, true) || in_array('Cashier', $target, true))
                            && ($rowTransmitEmpty || $rowTransmitYes);
                    ?>
                        <tr>
                            <?php if ($isLiaisonOfficer && $showForwardCol) : ?>
                                <td class="voucher-bulk-select-cell" data-label="">
                                    <input type="checkbox" class="voucher-bulk-select" value="<?php echo htmlspecialchars((string) $row['processing_no'], ENT_QUOTES, 'UTF-8'); ?>" aria-label="Select voucher <?php echo htmlspecialchars((string) $row['processing_no'], ENT_QUOTES, 'UTF-8'); ?>">
                                </td>
                            <?php elseif ($isCashierBulkPay) : ?>
                                <td class="voucher-bulk-select-cell" data-label="">
                                    <?php if ($rowBulkPayable) : ?>
                                        <input type="checkbox" class="voucher-bulk-pay-select" value="<?php echo htmlspecialchars((string) $row['processing_no'], ENT_QUOTES, 'UTF-8'); ?>" aria-label="Select voucher <?php echo htmlspecialchars((string) $row['processing_no'], ENT_QUOTES, 'UTF-8'); ?> for bulk pay">
                                    <?php endif; ?>
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
                            <td data-label="voucher_date" class="hidden"><?php echo $row['voucher_date']; ?></td>
                            <td data-label="voucher_type_display" class="hidden voucher-type-cell"><?php echo voucher_type_badge_html((string)($row['voucher_type'] ?? '')); ?></td>
                            <td data-label="datetime_forwarded" class="hidden"><?php echo $row['datetime_forwarded']; ?></td>
                            <td data-label="sender_remarks" class="return-remarks-cell">
                                <?php
                                $fwd_remarks_raw = isset($row['sender_remarks']) ? trim((string)$row['sender_remarks']) : '';

                                // Show only the latest entry from a combined string like:
                                // "Name A: remark..., Name B: remark..., Name C: remark..."
                                // Uses a lookahead to split only on ", NextName:" boundaries (keeps commas inside remarks).
                                $fwd_latest = '';
                                if ($fwd_remarks_raw !== '' && strcasecmp($fwd_remarks_raw, 'N/A') !== 0) {
                                    $pattern = '/(?:^|,\s*)([^,]+?):\s*(.*?)(?=(?:,\s*[^,]+?:\s)|$)/s';
                                    if (preg_match_all($pattern, $fwd_remarks_raw, $m) && !empty($m[0])) {
                                        $idx = count($m[0]) - 1;
                                        $name = trim((string)$m[1][$idx]);
                                        $text = trim((string)$m[2][$idx]);
                                        $fwd_latest = trim($name . ': ' . $text);
                                    } else {
                                        $fwd_latest = $fwd_remarks_raw;
                                    }
                                }

                                if ($fwd_latest !== '') :
                                ?>
                                    <span class="remarks-badge"><?php echo htmlspecialchars($fwd_latest); ?></span>
                                <?php else : ?>

                                <?php endif; ?>
                            </td>
                            <?php
                            $processStatus     = $row['process_status'] ?? '';
                            $transmitStatus    = $row['transmit'] ?? '';
                            $processEmpty      = $processStatus === '' || $processStatus === "N/A";
                            $transmitEmpty     = $transmitStatus === 'No' || $transmitStatus === "";
                            $processProcessed  = $processStatus === "Processed";
                            $processProcessing = $processStatus === "Processing";
                            $transmitYes       = $transmitStatus === "Yes";
                            $transmitDone      = $transmitStatus === "Done";

                            $roleAccounting    = in_array("Accounting Unit", $target);
                            $roleProcessor     = in_array("Processor", $target);
                            $roleAccountantIII = in_array("Accountant III", $target);
                            $roleBudget        = in_array("Budget Unit", $target);
                            $roleBudgetOfficer = in_array("Budget Officer", $target);
                            $roleCashiers      = in_array("Cashiers Unit", $target);
                            $roleCashier       = in_array("Cashier", $target);
                            $rolePlanning      = in_array("Planning Section", $target);
                            $rolePlanningChief = in_array("Planning Section Chief", $target);
                            $roleOfficePenro   = in_array("Office of the PENRO", $target);
                            $roleIcu           = in_array("ICU", $target, true);

                            $forwardHtml = '';
                            $processHtml = '';
                            $transmitHtml = '';
                            $archiveHtml = '';
                            $treatAsSameOfficeWorkflow = voucher_forwarding_treat_as_same_office_workflow(
                                (string) ($row['voucher_type'] ?? ''),
                                $forwarding_process_history,
                                $loggedUserOffice
                            );
                            $upstreamRoutingComplete = voucher_forwarding_upstream_routing_complete(
                                $pdo,
                                (string) ($row['voucher_type'] ?? ''),
                                $forwarding_process_history
                            );
                            $canShowProcess = ($roleAccounting || $roleProcessor) && $upstreamRoutingComplete;

                            if ($isAlternateWorkflowRole && !$treatAsSameOfficeWorkflow) {
                                if ($showForwardCol && !$roleCashiers) {
                                    if ($canShowProcess && $processProcessed && ($transmitEmpty || $transmitDone)) {
                                        $forwardHtml = '<button class="btn voucher-table-action-btn pPop" id="openPopup" name="btn-forward" type="button" onclick="hideProcessors()" aria-label="Forward" title="Forward"><i class="ri-send-plane-line" aria-hidden="true"></i><span>Forward</span></button>';
                                    } else {
                                        $forwardHtml = '<button class="btn voucher-table-action-btn pPop" id="openPopup" name="btn-forward" type="button" aria-label="Forward" title="Forward"><i class="ri-send-plane-line" aria-hidden="true"></i><span>Forward</span></button>';
                                    }
                                }
                                if ($roleAccounting || $roleProcessor) {
                                    $processHtml = voucher_forwarding_process_action_html($processStatus);
                                }
                                if ($canShowProcess && $processProcessed && !$roleAccountantIII) {
                                    if ($transmitEmpty) {
                                        $transmitHtml = '<button class="btn voucher-table-action-btn pPop" id="openPopup" name="btn-transmit" type="button" aria-label="Transmit" title="Transmit"><i class="ri-share-forward-line" aria-hidden="true"></i><span>Transmit</span></button>';
                                    } elseif ($transmitYes) {
                                        $transmitHtml = '<button class="btn voucher-table-action-btn pPop" id="openPopup" name="btn-re_transmit" type="button" aria-label="Re-transmit" title="Re-transmit"><i class="ri-refresh-line" aria-hidden="true"></i><span>Re-transmit</span></button>';
                                    }
                                }
                            } elseif ($roleAccounting || $roleProcessor) {
                                if (!$roleCashiers) {
                                    if ($processProcessed && $roleAccountantIII) {
                                        $forwardHtml = '<button class="btn voucher-table-action-btn pPop" id="openPopup" name="btn-forward" type="button" onclick="hideProcessors()" aria-label="Forward" title="Forward"><i class="ri-send-plane-line" aria-hidden="true"></i><span>Forward</span></button>';
                                    } elseif ($processProcessed && ($transmitEmpty || $transmitDone)) {
                                        $forwardHtml = '<button class="btn voucher-table-action-btn pPop" id="openPopup" name="btn-forward" type="button" onclick="hideProcessors()" aria-label="Forward" title="Forward"><i class="ri-send-plane-line" aria-hidden="true"></i><span>Forward</span></button>';
                                    } else {
                                        $forwardHtml = '<button class="btn voucher-table-action-btn pPop" id="openPopup" name="btn-forward" type="button" aria-label="Forward" title="Forward"><i class="ri-send-plane-line" aria-hidden="true"></i><span>Forward</span></button>';
                                    }
                                }
                                $processHtml = voucher_forwarding_process_action_html($processStatus);
                                if ($canShowProcess && $processProcessed && !$roleAccountantIII) {
                                    if ($transmitEmpty) {
                                        $transmitHtml = '<button class="btn voucher-table-action-btn pPop" id="openPopup" name="btn-transmit" type="button" aria-label="Transmit" title="Transmit"><i class="ri-share-forward-line" aria-hidden="true"></i><span>Transmit</span></button>';
                                    } elseif ($transmitYes) {
                                        $transmitHtml = '<button class="btn voucher-table-action-btn pPop" id="openPopup" name="btn-re_transmit" type="button" aria-label="Re-transmit" title="Re-transmit"><i class="ri-refresh-line" aria-hidden="true"></i><span>Re-transmit</span></button>';
                                    }
                                }
                                if (($roleCashiers || $roleCashier) && ($transmitEmpty || $transmitYes)) {
                                    $archiveHtml = '<button class="btn voucher-table-action-btn pPop" id="openPopup" name="btn-pay" type="button" aria-label="Pay" title="Pay"><i class="ri-wallet-3-line" aria-hidden="true"></i><span>Pay</span></button>';
                                }
                            } elseif ($roleIcu && $showForwardCol && !$roleCashiers) {
                                if ($processProcessed && ($transmitEmpty || $transmitDone)) {
                                    $forwardHtml = '<button class="btn voucher-table-action-btn pPop" id="openPopup" name="btn-forward" type="button" onclick="hideProcessors()" aria-label="Forward" title="Forward"><i class="ri-send-plane-line" aria-hidden="true"></i><span>Forward</span></button>';
                                } else {
                                    $forwardHtml = '<button class="btn voucher-table-action-btn pPop" id="openPopup" name="btn-forward" type="button" aria-label="Forward" title="Forward"><i class="ri-send-plane-line" aria-hidden="true"></i><span>Forward</span></button>';
                                }
                            } elseif ($roleCashiers || $roleBudget || $rolePlanning || $roleOfficePenro) {
                                if (!$roleCashiers && (!$roleBudgetOfficer || !$roleCashier)) {
                                    if ($roleBudget && $transmitEmpty) {
                                        $forwardHtml = '<button class="btn voucher-table-action-btn pPop" id="openPopup" name="btn-forward" type="button" aria-label="Forward" title="Forward"><i class="ri-send-plane-line" aria-hidden="true"></i><span>Forward</span></button>';
                                    } elseif ($roleBudget && $transmitDone) {
                                        $forwardHtml = '<button class="btn voucher-table-action-btn pPop" id="openPopup" name="btn-forward" onclick="hideBudgetOfficerChief()" type="button" aria-label="Forward" title="Forward"><i class="ri-send-plane-line" aria-hidden="true"></i><span>Forward</span></button>';
                                    } elseif ($roleCashiers && $transmitDone) {
                                        $forwardHtml = '<button class="btn voucher-table-action-btn pPop" id="openPopup" name="btn-forward" onclick="hideCashierOfficerChief()" type="button" aria-label="Forward" title="Forward"><i class="ri-send-plane-line" aria-hidden="true"></i><span>Forward</span></button>';
                                    } elseif ($roleCashiers && $transmitEmpty) {
                                        $forwardHtml = '<button class="btn voucher-table-action-btn pPop" id="openPopup" name="btn-forward" type="button" aria-label="Forward" title="Forward"><i class="ri-send-plane-line" aria-hidden="true"></i><span>Forward</span></button>';
                                    } elseif ($roleOfficePenro && ($transmitEmpty || $transmitDone)) {
                                        $forwardHtml = '<button class="btn voucher-table-action-btn pPop" id="openPopup" name="btn-forward" type="button" aria-label="Forward" title="Forward"><i class="ri-send-plane-line" aria-hidden="true"></i><span>Forward</span></button>';
                                    } elseif ($rolePlanning && $transmitEmpty) {
                                        $forwardHtml = '<button class="btn voucher-table-action-btn pPop" id="openPopup" name="btn-forward" type="button" aria-label="Forward" title="Forward"><i class="ri-send-plane-line" aria-hidden="true"></i><span>Forward</span></button>';
                                    } elseif ($rolePlanning && $transmitDone) {
                                        $forwardHtml = '<button class="btn voucher-table-action-btn pPop" id="openPopup" name="btn-forward" onclick="hidePlanningOfficerChief()" type="button" aria-label="Forward" title="Forward"><i class="ri-send-plane-line" aria-hidden="true"></i><span>Forward</span></button>';
                                    }
                                }
                                if (
                                    ($roleBudget && !$roleBudgetOfficer && $transmitEmpty)
                                    || ($roleOfficePenro && $transmitEmpty)
                                    || ($rolePlanning && !$rolePlanningChief && $transmitEmpty)
                                ) {
                                    $transmitHtml = '<button class="btn voucher-table-action-btn pPop" id="openPopup" name="btn-transmit" type="button" aria-label="Transmit" title="Transmit"><i class="ri-share-forward-line" aria-hidden="true"></i><span>Transmit</span></button>';
                                } elseif (
                                    ($roleBudget && !$roleBudgetOfficer && $transmitYes)
                                    || ($roleOfficePenro && $transmitYes)
                                    || ($rolePlanning && !$rolePlanningChief && $transmitYes)
                                ) {
                                    $transmitHtml = '<button class="btn voucher-table-action-btn pPop" id="openPopup" name="btn-re_transmit" type="button" aria-label="Re-transmit" title="Re-transmit"><i class="ri-refresh-line" aria-hidden="true"></i><span>Re-transmit</span></button>';
                                }
                                if (($roleCashiers || $roleCashier) && ($transmitEmpty || $transmitYes)) {
                                    $archiveHtml = '<button class="btn voucher-table-action-btn pPop" id="openPopup" name="btn-pay" type="button" aria-label="Pay" title="Pay"><i class="ri-wallet-3-line" aria-hidden="true"></i><span>Pay</span></button>';
                                }
                            } elseif (!$roleCashiers) {
                                $forwardHtml = '<button class="btn voucher-table-action-btn pPop" id="openPopup" name="btn-forward" type="button" aria-label="Forward" title="Forward"><i class="ri-send-plane-line" aria-hidden="true"></i><span>Forward</span></button>';
                            }
                            ?>
                            <td class="voucher-table-actions-cell" data-label="actions">
                                <div class="voucher-table-actions-group">
                                    <button class="btn voucher-table-action-btn voucher-table-action-btn--return" name="btn-return" type="button" aria-label="Return" title="Return"><i class="ri-arrow-go-back-line" aria-hidden="true"></i><span>Return</span></button>
                                    <?php if ($showEditCol) : ?>
                                        <button class="btn voucher-table-action-btn pPop" id="openPopup" name="btn-edit_amount" type="button" aria-label="Edit" title="Edit"><i class="ri-edit-line" aria-hidden="true"></i><span>Edit</span></button>
                                    <?php endif; ?>
                                    <?php
                                    if ($showForwardCol && $forwardHtml !== '') {
                                        echo $forwardHtml;
                                    }
                                    if ($showProcessCol && $processHtml !== '') {
                                        echo $processHtml;
                                    }
                                    if ($showTransmitCol && $transmitHtml !== '') {
                                        echo $transmitHtml;
                                    }
                                    if ($showCashierArchiveCol && $archiveHtml !== '') {
                                        echo $archiveHtml;
                                    }
                                    ?>
                                </div>
                            </td>
                            <td data-label="amount_original" class="hidden"><?php echo $row['amount']; ?></td>
                            <td data-label="combined_remarks" class="hidden"><?php echo $row['remarks']; ?></td>
                            <td data-label="tin_employee_no" class="hidden"><?php echo $row['tin_employee_no']; ?></td>
                            <td data-label="office_from" class="hidden"><?php echo $row['office_from']; ?></td>
                            <td data-label="office_to" class="hidden"><?php echo $row['office_to']; ?></td>
                            <td data-label="sender_udc" class="hidden"><?php echo $row['sender_udc']; ?></td>
                            <td data-label="receiver_udc" class="hidden"><?php echo $row['receiver_udc']; ?></td>
                            <td data-label="encoded_by" class="hidden"><?php echo $row['encoded_by']; ?></td>
                            <td data-label="encoded_from" class="hidden"><?php echo $row['encoded_from']; ?></td>
                            <td data-label="datetime_encoded" class="hidden"><?php echo $row['datetime_encoded']; ?></td>
                            <td data-label="forwarded_by" class="hidden"><?php echo isset($row['forwarded_by']) ? htmlspecialchars((string)$row['forwarded_by']) : ''; ?></td>
                            <td data-label="sender_remarks_raw" class="hidden"><?php echo isset($row['sender_remarks']) ? htmlspecialchars((string)$row['sender_remarks']) : ''; ?></td>
                            <td data-label="priority" class="hidden"><?php echo isset($row['priority']) ? htmlspecialchars((string)$row['priority']) : ''; ?></td>
                            <td data-label="process_status" class="hidden"><?php echo $row['process_status']; ?></td>
                            <td data-label="voucher_type" class="hidden"><?php echo $row['voucher_type']; ?></td>
                            <td data-label="process_history" class="hidden"><?php echo htmlspecialchars($forwarding_process_history, ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="process_history_display" class="hidden"><?php echo htmlspecialchars($forwarding_process_history_display, ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="coa_options" class="hidden"><?php echo isset($row['coa_options']) ? htmlspecialchars((string)$row['coa_options']) : ''; ?></td>
                            <td data-label="coa_category" class="hidden"><?php echo isset($row['coa_category']) ? htmlspecialchars((string)$row['coa_category']) : ''; ?></td>
                            <td data-label="coa_subsection" class="hidden"><?php echo isset($row['coa_subsection']) ? htmlspecialchars((string)$row['coa_subsection']) : ''; ?></td>
                            <td data-label="charged_amount" class="hidden"><?php echo isset($row['charged_amount']) ? htmlspecialchars((string)$row['charged_amount']) : ''; ?></td>
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
        var form = document.getElementById('forwardingFilterForm');
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
            contentWrapper.addEventListener('scroll', syncOpenRowMenu, {
                passive: true
            });
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

        function fitTableViewport() {
            var wrapperTop = tableWrapper.getBoundingClientRect().top;
            var pagination = tableCard.querySelector('.voucher-pagination-footer');
            var paginationHeight = pagination ? pagination.offsetHeight : 0;
            var available = window.innerHeight - wrapperTop - paginationHeight - 20;
            tableWrapper.style.maxHeight = Math.max(160, available) + 'px';
        }

        function scheduleLayoutSync() {
            if (layoutTimer) {
                clearTimeout(layoutTimer);
            }
            layoutTimer = setTimeout(fitTableViewport, 80);
        }

        window.addEventListener('resize', scheduleLayoutSync);
        window.addEventListener('load', scheduleLayoutSync);

        if (window.ResizeObserver) {
            var layoutObserver = new ResizeObserver(scheduleLayoutSync);
            layoutObserver.observe(main);
            layoutObserver.observe(tableCard);
        }

        scheduleLayoutSync();
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
                showNotify('No matching forwarding vouchers for your search.', 'warning', 2200);
            }
        });
    </script>
<?php endif; ?>

<script>
    function hideProcessors() {
        document.querySelector(".Accounting_Officer").style.display = 'none'; //HIDE ACCOUNTING OFFICER OPTIONS
        document.querySelector(".Processor").style.display = 'none';
        document.querySelector(".Processed").style.display = 'block';
    }

    function showProcessors() {
        document.querySelector(".Processor").style.display = 'block';
    }

    function hideForwardOptions() {
        document.querySelector(".Processed").style.display = 'none';
    }

    function hideBudgetOfficerChief() {
        document.querySelector(".Budget_Officer").style.display = 'none';
    }

    function hidePlanningOfficerChief() {
        document.querySelector(".Planning_Officer").style.display = 'none';
    }

    function hideCashierOfficer() {
        document.querySelector(".Cashier_Officer").style.display = 'none';
    }
</script>

<script>
    const selectElements2 = document.querySelectorAll(".form-custom-input"); // Get all form elements
    const target2 = "<?php echo $_SESSION['logged_user_designation']; ?>";
    const isLiaisonOfficer = <?php echo $isLiaisonOfficer ? 'true' : 'false'; ?>;
    const bulkForwardToken = <?php echo json_encode($bulkForwardToken, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE); ?>;
    window.bulkForwardToken = bulkForwardToken;
    const bulkForwardUrl = '../../protected/handler/voucher_receiving_module/voucher_bulk_forward_handler.php';
    const bulkSelectAllEl = document.getElementById('voucherBulkSelectAll');
    const bulkForwardBtn = document.getElementById('voucherBulkForwardBtn');
    const bulkForwardStatusEl = document.getElementById('voucherBulkForwardStatus');
    let bulkForwardInFlight = false;

    function syncBulkSelectAllState() {
        if (!isLiaisonOfficer || !bulkSelectAllEl) return;
        const boxes = Array.from(document.querySelectorAll('#my-Table input.voucher-bulk-select'));
        if (boxes.length === 0) {
            bulkSelectAllEl.checked = false;
            bulkSelectAllEl.indeterminate = false;
            return;
        }
        const checkedCount = boxes.filter(function(cb) {
            return cb.checked;
        }).length;
        bulkSelectAllEl.checked = checkedCount === boxes.length && boxes.length > 0;
        bulkSelectAllEl.indeterminate = false;
    }

    function selectedBulkProcessingNos() {
        if (!isLiaisonOfficer) return [];
        return Array.from(document.querySelectorAll('#my-Table input.voucher-bulk-select:checked'))
            .map(function(cb) {
                return String(cb.value || '').trim();
            })
            .filter(function(pn) {
                return pn !== '';
            });
    }

    function setBulkForwardStatus(message) {
        if (bulkForwardStatusEl) {
            bulkForwardStatusEl.textContent = message || '';
        }
    }

    function runBulkForward() {
        if (!isLiaisonOfficer || bulkForwardInFlight) return;
        const processingNos = selectedBulkProcessingNos();
        if (processingNos.length === 0) {
            if (typeof showNotify === 'function') {
                showNotify('Select at least one voucher to forward.', 'warning', 2800);
            }
            return;
        }
        const confirmMsg = 'Forward ' + processingNos.length + ' selected voucher(s) to ICU? They will be processed one at a time.';
        const proceed = function() {
            bulkForwardInFlight = true;
            if (bulkForwardBtn) bulkForwardBtn.disabled = true;
            setBulkForwardStatus('Forwarding…');
            fetch(bulkForwardUrl, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        token: window.bulkForwardToken || bulkForwardToken,
                        processing_nos: processingNos
                    })
                })
                .then(function(r) {
                    return r.json().then(function(payload) {
                        return {
                            ok: r.ok,
                            payload: payload
                        };
                    });
                })
                .then(function(res) {
                    bulkForwardInFlight = false;
                    if (bulkForwardBtn) bulkForwardBtn.disabled = false;
                    const payload = res.payload || {};
                    if (payload.ok === true) {
                        const msg = payload.message || ('Forwarded ' + (payload.forwarded || 0) + ' voucher(s).');
                        if (payload.token) {
                            window.bulkForwardToken = payload.token;
                        }
                        setBulkForwardStatus(msg);
                        if (typeof showNotify === 'function') {
                            showNotify(msg, Number(payload.failed || 0) > 0 ? 'warning' : 'success', 4000);
                        }
                        window.location.reload();
                        return;
                    }
                    const err = payload.error || payload.message || 'Bulk forward failed.';
                    setBulkForwardStatus('');
                    if (typeof showNotify === 'function') {
                        showNotify(err, 'error', 5000);
                    }
                })
                .catch(function() {
                    bulkForwardInFlight = false;
                    if (bulkForwardBtn) bulkForwardBtn.disabled = false;
                    setBulkForwardStatus('');
                    if (typeof showNotify === 'function') {
                        showNotify('Bulk forward request failed.', 'error', 4000);
                    }
                });
        };
        if (window.confirm(confirmMsg)) {
            proceed();
        }
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
    if (bulkForwardBtn) {
        bulkForwardBtn.addEventListener('click', runBulkForward);
    }
    document.querySelectorAll('#my-Table input.voucher-bulk-select').forEach(function(cb) {
        cb.addEventListener('change', syncBulkSelectAllState);
    });

    const loggedUserOffice = <?= json_encode(
                                    $loggedUserOffice,
                                    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE
                                ) ?>;

    const docToSelect = document.getElementById('document_to');
    const docToOriginalHtml = docToSelect ? docToSelect.innerHTML : '';

    function normalizeForwardingProcessHistory(value) {
        return String(value || '')
            .replace(/\r\n/g, '\n')
            .replace(/\r/g, '\n')
            .replace(/\\n/g, '\n')
            .trim();
    }

    function parseForwardingProcessHistoryLines(value) {
        var normalized = normalizeForwardingProcessHistory(value);
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

    function forwardingHistoryOriginOffice(lines) {
        for (var i = 0; i < lines.length; i++) {
            if (/encoded by/i.test(lines[i].action)) {
                return lines[i].office;
            }
        }
        return lines.length ? lines[0].office : '';
    }

    function forwardingOfficesMatch(left, right) {
        left = String(left || '').trim();
        right = String(right || '').trim();
        if (!left || !right) {
            return false;
        }
        return left.toUpperCase() === right.toUpperCase();
    }

    function isAlternateForwardingRole() {
        return targetArray2.includes('Accounting Unit') ||
            targetArray2.includes('Processor') ||
            targetArray2.includes('ICU');
    }

    function isEngpVoucherType(voucherType) {
        var value = String(voucherType || '').trim();
        if (!value) {
            return false;
        }
        if (/e-?\s*ngp/i.test(value)) {
            return true;
        }
        var collapsed = value.toLowerCase().replace(/[^a-z0-9]+/g, '');
        return collapsed.indexOf('engp') === 0;
    }

    function forwardingTreatAsSameOfficeWorkflow(voucherType, processHistory) {
        if (isEngpVoucherType(voucherType)) {
            return true;
        }
        return forwardingSameOfficeOrigin(processHistory);
    }

    function forwardingSameOfficeOrigin(processHistory) {
        var lines = parseForwardingProcessHistoryLines(processHistory);
        if (!lines.length) {
            return false;
        }
        return forwardingOfficesMatch(forwardingHistoryOriginOffice(lines), loggedUserOffice);
    }

    function hideOwnDesignationOptions(selectElement) {
        if (!selectElement || !selectElement.options || !selectElement.options.length) {
            return;
        }
        Array.from(selectElement.options).forEach(function(option) {
            if (targetArray2.includes(option.value)) {
                option.classList.add('hidden');
            }
        });
    }

    function restoreForwardDestinationOptions() {
        if (!docToSelect || !docToOriginalHtml) {
            return;
        }
        docToSelect.innerHTML = docToOriginalHtml;
        hideOwnDesignationOptions(docToSelect);
    }

    function applyForwardingDestinationOptions(voucherType, processHistory) {
        if (!docToSelect || isLiaisonOfficer) {
            return;
        }

        if (targetArray2.includes('ICU')) {
            docToSelect.innerHTML = `
        <option value="" disabled selected>Please Select</option>
        <option value="Budget Unit">Budget Unit</option>
        <option value="Accounting Unit">Accounting Unit</option>
        <option value="Planning Section">Planning Section</option>
        <option value="Accountant III">Chief Accountant</option>

        <?php if (!isset($_SESSION['logged_user_udc']) || $_SESSION['logged_user_udc'] !== '4HyLy') : ?>
            <option value="4HyLy">Marife C. Briton</option>
        <?php endif; ?>

        <?php if (!isset($_SESSION['logged_user_udc']) || $_SESSION['logged_user_udc'] !== 'YS9M3') : ?>
            <option value="YS9M3">Diana E. Costuna</option>
        <?php endif; ?>

        <?php if (!isset($_SESSION['logged_user_udc']) || $_SESSION['logged_user_udc'] !== 's1JxV') : ?>
            <option value="s1JxV">Gracile B. Palce</option>
        <?php endif; ?>

        <?php if (!isset($_SESSION['logged_user_udc']) || $_SESSION['logged_user_udc'] !== '5Cw9e') : ?>
            <option value="5Cw9e">Eda Buen</option>
        <?php endif; ?>
    `;
            return;
        }

        if (isAlternateForwardingRole() && !forwardingTreatAsSameOfficeWorkflow(voucherType, processHistory)) {
            docToSelect.innerHTML = `
        <option value="" disabled selected>Please Select</option>
        <option value="Budget Unit">Budget Unit</option>
        <option value="Accounting Unit">Accounting Unit</option>
        <option value="Planning Section">Planning Section</option>
        <option value="Accountant III">Chief Accountant</option>

        <?php if (!isset($_SESSION['logged_user_udc']) || $_SESSION['logged_user_udc'] !== '4HyLy') : ?>
            <option value="4HyLy">Marife C. Briton</option>
        <?php endif; ?>

        <?php if (!isset($_SESSION['logged_user_udc']) || $_SESSION['logged_user_udc'] !== 'YS9M3') : ?>
            <option value="YS9M3">Diana E. Costuna</option>
        <?php endif; ?>

        <?php if (!isset($_SESSION['logged_user_udc']) || $_SESSION['logged_user_udc'] !== 's1JxV') : ?>
            <option value="s1JxV">Gracile B. Palce</option>
        <?php endif; ?>

        <?php if (!isset($_SESSION['logged_user_udc']) || $_SESSION['logged_user_udc'] !== '5Cw9e') : ?>
            <option value="5Cw9e">Eda Buen</option>
        <?php endif; ?>
    `;

            docToSelect.value = '';
            return;
        }

        restoreForwardDestinationOptions();
    }

    const targetArray2 = target2.split(',').map(function(item) {
        return item.trim();
    }).filter(Boolean);

    selectElements2.forEach(selectElement => {
        hideOwnDesignationOptions(selectElement);
    });

    // Make ORS No. editable, required, and visually emphasized (red border)
    // for Budget Unit and Budget Unit Chief (Budget Officer).
    if (targetArray2.includes("Budget Unit") || targetArray2.includes("Budget Officer")) {
        const orsInput = document.getElementById("ors_no");
        if (orsInput) {
            orsInput.required = true;
            orsInput.readOnly = false;
            orsInput.style.border = "2px solid red";
        }
    } else if (targetArray2.includes("Accounting Unit") || targetArray2.includes("Processor")) {
        document.getElementById("dv_no").required = true;
        document.querySelector(".dv_no").readOnly = false;
    } else if (targetArray2.includes("Cashiers Unit") || targetArray2.includes("Cashier")) {
        const orsInput = document.getElementById("ors_no");
        const adaInput = document.getElementById("ada_check_no");
        if (adaInput) {
            adaInput.required = true;
            adaInput.readOnly = false;
            orsInput.readOnly = false;
            document.querySelector(".dv_no").readOnly = false;
            adaInput.style.border = "2px solid red";
        }
    }

    // Prevent forwarding for Budget Unit / Budget Officer when ORS No. is empty or "TBD"
    const forwardingForm = document.getElementById("myForm_Forwarding");
    if (forwardingForm) {
        forwardingForm.addEventListener("submit", function(e) {
            const isBudgetRole = targetArray2.includes("Budget Unit") || targetArray2.includes("Budget Officer");
            const actionButton = document.querySelector(".btn-dynamic");
            const actionName = actionButton ? actionButton.getAttribute("name") : "";

            if (isBudgetRole && actionName === "forward_voucher") {
                const orsInput = document.getElementById("ors_no");
                if (orsInput) {
                    const orsValue = (orsInput.value || "").trim();
                    if (orsValue === "" || orsValue.toUpperCase() === "TBD") {
                        e.preventDefault();
                        showNotify("Please enter a valid ORS No. before forwarding. Empty or 'TBD' is not allowed.", 'error', 3500);
                        orsInput.focus();
                    }
                }
            }
        });
    }
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

    function setAmountSplitViewMode(showSplit) {
        const amountMainLabel = document.querySelector('.amount_main_label');
        const amountMainDisplay = document.querySelector('.amount_main_display');
        const splitPanel = document.getElementById('voucherAmountSplitPanel');
        if (amountMainLabel) amountMainLabel.style.display = showSplit ? 'none' : '';
        if (amountMainDisplay) amountMainDisplay.style.display = showSplit ? 'none' : '';
        if (splitPanel) splitPanel.style.display = showSplit ? 'block' : 'none';
    }

    function watchAmountInput(inputElement, outputElement) {
        if (!inputElement || !outputElement) return;

        inputElement.readOnly = false;
        outputElement.readOnly = false;

        let lastValue = inputElement.value;

        function checkForChange() {
            const currentValue = inputElement.value;
            if (currentValue !== lastValue) {
                lastValue = currentValue;
                syncAmountFields(currentValue, outputElement);
            }
        }

        if (!inputElement._amountIntervalId) {
            inputElement._amountIntervalId = setInterval(checkForChange, 100);
        }
    }

    function enableAmountEditing() {
        watchAmountInput(
            document.getElementById('original_string_amount'),
            document.getElementById('gross_amount')
        );
        watchAmountInput(
            document.getElementById('charged_string_amount'),
            document.getElementById('int_amount')
        );
    }

    function resetVoucherDetailEditing() {
        ['address', 'voucher_date'].forEach(function(id) {
            var el = document.getElementById(id);
            if (el) {
                el.readOnly = true;
                if (id === 'address') {
                    el.setAttribute('required', 'required');
                }
            }
        });
        var particularsEl = document.getElementById('particulars');
        if (particularsEl) {
            particularsEl.readOnly = true;
            particularsEl.setAttribute('required', 'required');
        }
    }

    function enableVoucherDetailEditing() {
        ['address', 'voucher_date'].forEach(function(id) {
            var el = document.getElementById(id);
            if (el) {
                el.readOnly = false;
                if (id === 'address') {
                    el.removeAttribute('required');
                }
            }
        });
        var particularsEl = document.getElementById('particulars');
        if (particularsEl) {
            particularsEl.readOnly = false;
            particularsEl.removeAttribute('required');
        }
    }

    function isAccountingAmountEditRole() {
        return targetArray2.includes('Accounting Unit') ||
            targetArray2.includes('Processor') ||
            targetArray2.includes('Accountant III') ||
            targetArray2.includes('Cashiers Unit');
    }

    function isAmountEditRole() {
        return isAccountingAmountEditRole() ||
            targetArray2.includes('Budget Unit') ||
            targetArray2.includes('Budget Officer');
    }

    function isVoucherDetailEditRole() {
        return targetArray2.includes('ICU') || isAmountEditRole();
    }

    // Get all buttons with class 'btn-forward'
    var buttons = document.querySelectorAll('#my-Table .btn');

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

            var name = this.name.toString();

            // Extract data from the row
            var processing_no = row.querySelector('[data-label="processing_no"]').textContent;
            var ors_no = row.querySelector('[data-label="ors_no"]').textContent;
            var dv_no = (row.querySelector('[data-label="dv_no"]')?.textContent || '').trim();
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
            var priority_cell = row.querySelector('[data-label="priority"]');
            var priority_val = priority_cell ? priority_cell.textContent.trim() : '';
            var process_status = row.querySelector('[data-label="process_status"]').textContent;
            var combined_remarks = row.querySelector('[data-label="combined_remarks"]').textContent;
            var voucher_type = row.querySelector('[data-label="voucher_type"]').textContent;
            var process_history_cell = row.querySelector('[data-label="process_history"]');
            var process_history_val = process_history_cell ? process_history_cell.textContent.trim() : '';
            var charged_amount_cell = row.querySelector('[data-label="charged_amount"]');
            var charged_amount = normalizeAmountInput(charged_amount_cell ? charged_amount_cell.textContent : '');
            var coa_options_cell = row.querySelector('[data-label="coa_options"]');
            var coa_options = coa_options_cell ? coa_options_cell.textContent : '';
            var coa_category_cell = row.querySelector('[data-label="coa_category"]');
            var coa_category = coa_category_cell ? coa_category_cell.textContent : '';
            var coa_subsection_cell = row.querySelector('[data-label="coa_subsection"]');
            var coa_subsection = coa_subsection_cell ? coa_subsection_cell.textContent : '';

            // Use net only when it differs from gross; otherwise keep gross.
            var amount = hasDistinctNetAmount(amountOriginal, charged_amount) ? charged_amount : amountOriginal;

            const convertedBack = normalizeAmountInput(String(amount));

            // Send it via AJAX to the server
            document.querySelector('.processing_no').value = processing_no;
            document.querySelector('.ors_no').value = ors_no;
            document.querySelector('.dv_no').value = dv_no;
            document.querySelector('.ada_check_no').value = ada_check_no;
            document.querySelector('.payee').value = payee;
            document.querySelector('.address').value = address;
            document.querySelector('.particulars').value = particulars;
            document.querySelector('.tin_employee_no').value = tin_employee_no;
            document.querySelector('.amount').value = convertedBack;
            setAmountDisplayValue(document.getElementById('string_amount'), amount);
            const grossHiddenInput = document.getElementById('gross_amount');
            if (grossHiddenInput) {
                grossHiddenInput.value = normalizeAmountInput(amountOriginal);
            }

            document.querySelector('.voucher_date').value = voucher_date;
            document.querySelector('.office_from').value = office_from;
            document.querySelector('.office_to').value = office_to;
            document.querySelector('.sender_udc').value = sender_udc;
            document.querySelector('.receiver_udc').value = receiver_udc;
            document.querySelector('.encoded_by').value = encoded_by;
            document.querySelector('.encoded_from').value = encoded_from;
            document.querySelector('.datetime_encoded').value = datetime_encoded;
            var priorityInput = document.getElementById('priority');
            if (priorityInput) {
                priorityInput.value = priority_val || 'N/A';
            }
            document.querySelector('.process_status').value = process_status;
            document.querySelector('.combined_remarks').value = combined_remarks;
            document.querySelector('.voucher_type').value = voucher_type;
            var phInput = document.getElementById('process_history');
            if (phInput) {
                phInput.value = process_history_val;
            }
            // Preserve COA selections for forwarding (if present on the receiving record)
            var coaHiddenForward = document.getElementById('selected_coa_options_forward');
            if (coaHiddenForward) {
                coaHiddenForward.value = coa_options || '';
            }
            var coaCategoryForward = document.getElementById('coa_category_forward');
            if (coaCategoryForward) {
                coaCategoryForward.value = coa_category || '';
            }
            var coaSubsectionForward = document.getElementById('coa_subsection_forward');
            if (coaSubsectionForward) {
                coaSubsectionForward.value = coa_subsection || '';
            }

            resetVoucherDetailEditing();

            const amountPrimaryBlock = document.querySelector('.amount_primary_block');
            const originalContainer = document.querySelector('.original_charged_container');
            const chargedContainer = document.querySelector('.charged_amount_container');
            const stringAmountInput = document.getElementById('string_amount');

            document.querySelectorAll('.hidden_input').forEach(function(input) {
                if (
                    input.classList.contains('original_charged_container') ||
                    input.classList.contains('charged_amount_container')
                ) {
                    return;
                }
                input.style.display = 'none';
            });

            if (originalContainer) originalContainer.style.display = 'none';
            if (chargedContainer) chargedContainer.style.display = 'none';

            const originalStringInput = document.getElementById('original_string_amount');
            const chargedStringInput = document.getElementById('charged_string_amount');
            if (originalStringInput) originalStringInput.disabled = true;
            if (chargedStringInput) chargedStringInput.disabled = true;

            const hasCharged = hasDistinctNetAmount(amountOriginal, charged_amount);
            setAmountSplitViewMode(hasCharged);

            if (hasCharged) {
                if (amountPrimaryBlock) amountPrimaryBlock.style.display = '';
                if (stringAmountInput) stringAmountInput.removeAttribute('required');
                if (originalContainer) originalContainer.style.display = 'flex';
                if (chargedContainer) chargedContainer.style.display = 'flex';
                if (originalStringInput) setAmountDisplayValue(originalStringInput, amountOriginal);
                if (chargedStringInput) setAmountDisplayValue(chargedStringInput, charged_amount);
            } else {
                if (amountPrimaryBlock) amountPrimaryBlock.style.display = '';
                if (stringAmountInput) stringAmountInput.setAttribute('required', 'required');
                if (originalContainer) originalContainer.style.display = 'none';
                if (chargedContainer) chargedContainer.style.display = 'none';
                if (originalStringInput) originalStringInput.value = '';
                if (chargedStringInput) chargedStringInput.value = '';
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
            }

            setVoucherPortalViewMode(false);

            if (name === "btn-return") {
                restoreForwardDestinationOptions();
                document.getElementById("myForm_Forwarding").setAttribute('action', '../../protected/handler/voucher_return_module/voucher_return_handler.php');
                document.getElementById("document_to").required = false;
                document.querySelector(".btn-dynamic").textContent = "Return";
                document.getElementById("form_title").textContent = "Return Voucher";
                document.querySelector(".btn-dynamic").setAttribute("name", "return_voucher");
                document.querySelector(".btn-dynamic").classList.remove("success");
                document.querySelector(".btn-dynamic").classList.add("warning");
                document.querySelectorAll('.input-dynamic').forEach(function(input) {
                    input.style.display = 'none';
                });
                var forwardedByCell = row.querySelector('[data-label="forwarded_by"]');
                var forwardedBy = forwardedByCell ? forwardedByCell.textContent.trim() : '';
                var forwardedByInput = document.getElementById('forwarded_by');
                if (forwardedByInput) {
                    forwardedByInput.value = forwardedBy;
                }
                var senderRemarksCell = row.querySelector('[data-label="sender_remarks_raw"]');
                var senderRemarksVal = senderRemarksCell ? senderRemarksCell.textContent.trim() : '';
                var senderRemarksInput = document.getElementById('sender_remarks');
                if (senderRemarksInput) {
                    senderRemarksInput.value = senderRemarksVal;
                }
                var returnSourceInput = document.getElementById('return_source');
                if (returnSourceInput) {
                    returnSourceInput.value = 'forwarding';
                }
                var process_history = process_history_val;
                var office_from_val = office_from;
                if (typeof openReturnOptionsPopup === 'function') {
                    openReturnOptionsPopup(
                        processing_no,
                        process_history,
                        office_from_val,
                        String(encoded_from || '').trim(),
                        String(encoded_by || '').trim()
                    );
                }
                return;
            }

            if (name === "btn-forward") {

                document.getElementById("myForm_Forwarding").setAttribute('action', '../../protected/handler/voucher_receiving_module/voucher_receiving_handler.php');
                document.querySelector(".btn-dynamic").textContent = "Forward";
                document.getElementById("form_title").textContent = "Forward Voucher";
                restoreForwardDestinationOptions();
                var docToForward = document.getElementById("document_to");
                if (docToForward) {
                    docToForward.required = true;
                    if (isLiaisonOfficer) {
                        docToForward.value = 'ICU';
                    } else {
                        applyForwardingDestinationOptions(voucher_type, process_history_val);
                    }
                }
                document.querySelector(".btn-dynamic").setAttribute("name", "forward_voucher");
                document.querySelector(".btn-dynamic").classList.remove("warning");
                document.querySelector(".btn-dynamic").classList.remove("success");
                document.querySelector(".btn-dynamic").classList.remove("tertiary");
                document.querySelector(".btn-dynamic").classList.remove("danger");
                document.querySelector(".btn-dynamic").classList.add("primary");

                document.querySelectorAll('.input-dynamic').forEach(function(input) {
                    input.style.display = 'flex';
                });
            }

            if (name === "btn-transmit") {

                document.getElementById("myForm_Forwarding").setAttribute('action', '../../protected/handler/voucher_receiving_module/voucher_receiving_handler.php');
                document.querySelector(".btn-dynamic").textContent = "Transmit";
                document.getElementById("form_title").textContent = "Transmit Voucher";
                document.getElementById("document_to").required = false;
                document.querySelector(".btn-dynamic").setAttribute("name", "transmit_voucher");
                document.querySelector(".btn-dynamic").classList.remove("primary");
                document.querySelector(".btn-dynamic").classList.remove("success");
                document.querySelector(".btn-dynamic").classList.remove("tertiary");
                document.querySelector(".btn-dynamic").classList.remove("danger");
                document.querySelector(".btn-dynamic").classList.add("warning");

                document.querySelectorAll('.input-dynamic').forEach(function(input) {
                    input.style.display = 'none';
                });
            }

            if (name === "btn-re_transmit") {

                document.getElementById("myForm_Forwarding").setAttribute('action', '../../protected/handler/voucher_receiving_module/voucher_receiving_handler.php');
                document.querySelector(".btn-dynamic").textContent = "Transmit";
                document.getElementById("form_title").textContent = "Transmit Voucher";
                document.getElementById("document_to").required = false;
                document.querySelector(".btn-dynamic").setAttribute("name", "re_transmit_voucher");
                document.querySelector(".btn-dynamic").classList.remove("primary");
                document.querySelector(".btn-dynamic").classList.remove("success");
                document.querySelector(".btn-dynamic").classList.remove("tertiary");
                document.querySelector(".btn-dynamic").classList.remove("danger");
                document.querySelector(".btn-dynamic").classList.add("warning");

                document.querySelectorAll('.input-dynamic').forEach(function(input) {
                    input.style.display = 'none';
                });
            }

            if (name === "btn-pay") {

                restoreForwardDestinationOptions();
                document.getElementById("myForm_Forwarding").setAttribute('action', '../../protected/handler/voucher_archiving_module/voucher_archiving_handler.php');
                document.querySelector(".btn-dynamic").textContent = "Pay";
                document.getElementById("form_title").textContent = "Pay Voucher";
                document.getElementById("document_to").required = false;
                var docToEl = document.getElementById("document_to");
                if (docToEl) {
                    docToEl.value = "Budget Unit";
                }
                document.querySelector(".btn-dynamic").setAttribute("name", "archive_voucher");
                document.querySelector(".btn-dynamic").classList.remove("primary");
                document.querySelector(".btn-dynamic").classList.remove("success");
                document.querySelector(".btn-dynamic").classList.remove("tertiary");
                document.querySelector(".btn-dynamic").classList.remove("warning");
                document.querySelector(".btn-dynamic").classList.add("danger");

                document.querySelectorAll('.input-dynamic').forEach(function(input) {
                    input.style.display = 'none';
                });
            }

            if (name === "btn_process") {

                document.getElementById("myForm_Forwarding").setAttribute('action', '../../protected/handler/voucher_receiving_module/voucher_receiving_handler.php');
                document.querySelector(".btn-dynamic").textContent = "Process";
                document.getElementById("form_title").textContent = "Process Voucher";
                document.getElementById("document_to").required = false;
                document.querySelector(".btn-dynamic").setAttribute("name", "process_voucher");
                document.querySelector(".btn-dynamic").classList.remove("primary");
                document.querySelector(".btn-dynamic").classList.remove("success");
                document.querySelector(".btn-dynamic").classList.remove("warning");
                document.querySelector(".btn-dynamic").classList.remove("danger");
                document.querySelector(".btn-dynamic").classList.add("tertiary");

                document.querySelectorAll('.input-dynamic').forEach(function(input) {
                    input.style.display = 'none';
                });
            }

            if (name === "btn_process_confirm") {

                document.getElementById("myForm_Forwarding").setAttribute('action', '../../protected/handler/voucher_receiving_module/voucher_receiving_handler.php');
                document.querySelector(".btn-dynamic").textContent = "Process";
                document.getElementById("form_title").textContent = "Process Voucher";
                document.getElementById("document_to").required = false;
                document.querySelector(".btn-dynamic").setAttribute("name", "confirm_process_voucher");
                document.querySelector(".btn-dynamic").classList.remove("primary");
                document.querySelector(".btn-dynamic").classList.remove("tertiary");
                document.querySelector(".btn-dynamic").classList.remove("warning");
                document.querySelector(".btn-dynamic").classList.remove("danger");
                document.querySelector(".btn-dynamic").classList.add("success");

                document.querySelectorAll('.input-dynamic').forEach(function(input) {
                    input.style.display = 'none';
                });
            }

            if (name === "btn-edit_amount") {
                document.getElementById("myForm_Forwarding").setAttribute('action', '../../protected/handler/voucher_receiving_module/voucher_receiving_handler.php');
                document.querySelector(".btn-dynamic").textContent = "Save";
                document.getElementById("document_to").required = false;
                document.querySelector(".btn-dynamic").setAttribute("name", "edit_voucher_amount");
                document.querySelector(".btn-dynamic").classList.remove("warning");
                document.querySelector(".btn-dynamic").classList.remove("success");
                document.querySelector(".btn-dynamic").classList.remove("tertiary");
                document.querySelector(".btn-dynamic").classList.remove("danger");
                document.querySelector(".btn-dynamic").classList.add("primary");

                document.querySelectorAll('.input-dynamic').forEach(function(input) {
                    input.style.display = 'none';
                });

                var addressEl = document.getElementById('address');
                if (addressEl) {
                    addressEl.removeAttribute('required');
                }
                var dvInput = document.getElementById('dv_no');
                if (dvInput) {
                    dvInput.required = false;
                    if (targetArray2.includes('Accounting Unit') || targetArray2.includes('Processor') || targetArray2.includes('Accountant III') || targetArray2.includes('Cashiers Unit')) {
                        dvInput.readOnly = false;
                    }
                }

                const amountPrimaryBlock = document.querySelector('.amount_primary_block');
                const originalContainer = document.querySelector('.original_charged_container');
                const chargedContainer = document.querySelector('.charged_amount_container');
                const stringAmountInput = document.getElementById('string_amount');
                const originalStringInput = document.getElementById('original_string_amount');
                const chargedStringInput = document.getElementById('charged_string_amount');
                const canEditAmount = isAmountEditRole();
                const canEditDetails = isVoucherDetailEditRole();

                if (canEditDetails) {
                    enableVoucherDetailEditing();
                }

                if (canEditAmount) {
                    document.getElementById("form_title").textContent = canEditDetails ?
                        "Edit Voucher" :
                        "Edit Amount";

                    setAmountSplitViewMode(true);
                    if (amountPrimaryBlock) amountPrimaryBlock.style.display = '';
                    if (stringAmountInput) stringAmountInput.removeAttribute('required');
                    if (originalContainer) originalContainer.style.display = 'flex';
                    if (chargedContainer) chargedContainer.style.display = 'flex';
                    if (originalStringInput) {
                        originalStringInput.disabled = false;
                        setAmountDisplayValue(originalStringInput, amountOriginal);
                    }
                    if (chargedStringInput) {
                        chargedStringInput.disabled = false;
                        setAmountDisplayValue(
                            chargedStringInput,
                            hasDistinctNetAmount(amountOriginal, charged_amount) ? charged_amount : amountOriginal
                        );
                    }

                    const mainAmountInput = document.getElementById('string_amount');
                    if (mainAmountInput) mainAmountInput.readOnly = true;

                    const grossHiddenInput = document.getElementById('gross_amount');
                    if (grossHiddenInput) {
                        grossHiddenInput.value = normalizeAmountInput(amountOriginal);
                    }
                    const intAmountInput = document.getElementById('int_amount');
                    if (intAmountInput) {
                        intAmountInput.value = normalizeAmountInput(
                            hasDistinctNetAmount(amountOriginal, charged_amount) ? charged_amount : amountOriginal
                        );
                    }

                    enableAmountEditing();
                } else {
                    document.getElementById("form_title").textContent = "Edit Voucher";

                    setAmountSplitViewMode(false);
                    if (amountPrimaryBlock) amountPrimaryBlock.style.display = 'none';
                    if (stringAmountInput) stringAmountInput.removeAttribute('required');
                    if (originalContainer) originalContainer.style.display = 'none';
                    if (chargedContainer) chargedContainer.style.display = 'none';
                    if (originalStringInput) originalStringInput.disabled = true;
                    if (chargedStringInput) chargedStringInput.disabled = true;
                }
            }
        });
    });

    (function() {
        const form = document.getElementById('myForm_Forwarding');
        if (!form) return;

        form.addEventListener('submit', function() {
            const actionButton = document.querySelector('.btn-dynamic');
            const actionName = actionButton ? actionButton.getAttribute('name') : '';
            if (actionName !== 'edit_voucher_amount') {
                return;
            }

            const intAmount = document.getElementById('int_amount');
            const grossHidden = document.getElementById('gross_amount');
            const grossInput = document.getElementById('original_string_amount');
            const chargedInput = document.getElementById('charged_string_amount');

            if (chargedInput && intAmount && !chargedInput.disabled) {
                syncAmountFields(chargedInput.value, intAmount);
            }
            if (grossInput && grossHidden && !grossInput.disabled) {
                syncAmountFields(grossInput.value, grossHidden);
            }

            if (intAmount && (!chargedInput || chargedInput.disabled)) {
                const stringInput = document.getElementById('string_amount');
                if (stringInput) {
                    syncAmountFields(stringInput.value, intAmount);
                }
            }
        });
    })();
</script>

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
<?php if ($showCashierArchiveCol) : ?>
    <script>
        (function() {
            var fwdAdaSignatoryDefaults = <?= json_encode(
                                                $ada_option_defaults,
                                                JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE
                                            ) ?>;
            var fwdAdaSignatoryBundles = <?= json_encode(
                                                $ada_signatory_bundles_indexed,
                                                JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE
                                            ) ?>;
            var fwdAdaSignatoryOffices = <?= json_encode(
                                                $ada_signatory_offices,
                                                JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE
                                            ) ?>;
            var fwdCanSelectAdaSignatoryOffice = <?= json_encode($ada_can_select_signatory_office) ?>;
            var fwdAdaDefaultOffice = <?= json_encode($ada_default_office, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;
            var bulkPayUrl = '../../protected/handler/voucher_archiving_module/voucher_bulk_pay_handler.php';
            var bulkPayToken = <?= json_encode($bulkForwardToken, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE) ?>;
            window.bulkPayToken = bulkPayToken;
            var bulkPaySelectAllEl = document.getElementById('voucherBulkPaySelectAll');
            var bulkPayBtn = document.getElementById('voucherBulkPayBtn');
            var bulkPayStatusEl = document.getElementById('voucherBulkPayStatus');
            var bulkPayInFlight = false;
            var bulkPayMode = false;
            var bulkPayProcessingNos = [];
            var bulkPayAdaCheckNos = {};

            function fwdBulkPayEscapeHtml(value) {
                if (value == null) {
                    return '';
                }
                var div = document.createElement('div');
                div.textContent = String(value);
                return div.innerHTML;
            }

            function fwdBulkPayRowForProcessingNo(processingNo) {
                var normalized = String(processingNo || '').trim();
                if (normalized === '') {
                    return null;
                }
                var rows = document.querySelectorAll('#my-Table tbody tr');
                for (var i = 0; i < rows.length; i++) {
                    var row = rows[i];
                    var cell = row.querySelector('[data-label="processing_no"]');
                    if (cell && String(cell.textContent || '').trim() === normalized) {
                        return row;
                    }
                }
                return null;
            }

            function fwdBulkPayCellText(row, label) {
                if (!row) {
                    return '';
                }
                var cell = row.querySelector('[data-label="' + label + '"]');
                return cell ? String(cell.textContent || '').trim() : '';
            }

            function selectedBulkPayProcessingNos() {
                return Array.from(document.querySelectorAll('#my-Table input.voucher-bulk-pay-select:checked'))
                    .map(function(cb) {
                        return String(cb.value || '').trim();
                    })
                    .filter(function(pn) {
                        return pn !== '';
                    });
            }

            function syncBulkPaySelectAllState() {
                if (!bulkPaySelectAllEl) {
                    return;
                }
                var boxes = Array.from(document.querySelectorAll('#my-Table input.voucher-bulk-pay-select'));
                if (boxes.length === 0) {
                    bulkPaySelectAllEl.checked = false;
                    bulkPaySelectAllEl.indeterminate = false;
                    return;
                }
                var checkedCount = boxes.filter(function(cb) {
                    return cb.checked;
                }).length;
                bulkPaySelectAllEl.checked = checkedCount === boxes.length;
                bulkPaySelectAllEl.indeterminate = checkedCount > 0 && checkedCount < boxes.length;
            }

            function setBulkPayStatus(message) {
                if (bulkPayStatusEl) {
                    bulkPayStatusEl.textContent = message || '';
                }
            }

            function resetBulkPayMode() {
                bulkPayMode = false;
                bulkPayProcessingNos = [];
                bulkPayAdaCheckNos = {};
                var bulkModal = document.getElementById('bulkPayVoucherModal');
                var bulkOverlay = document.getElementById('overlayBulkPayVoucher');
                if (bulkModal) {
                    bulkModal.style.display = 'none';
                }
                if (bulkOverlay) {
                    bulkOverlay.style.display = 'none';
                }
                var bulkAdaInput = document.getElementById('bulk_pay_ada_check_no');
                if (bulkAdaInput) {
                    bulkAdaInput.value = '';
                }
                var adaFieldWrap = document.getElementById('fwd_bulk_ada_check_wrap');
                if (adaFieldWrap) {
                    adaFieldWrap.style.display = '';
                }
                var adaInput = document.getElementById('fwd_ada_check_no');
                if (adaInput) {
                    adaInput.required = true;
                }
                var adaOfficeWrap = document.getElementById('fwd_ada_office_wrap');
                if (adaOfficeWrap) {
                    adaOfficeWrap.style.display = 'none';
                }
                var titleEl = document.getElementById('archive_process_form_title');
                if (titleEl) {
                    titleEl.textContent = 'Process Voucher';
                }
                var printBtn = document.getElementById('fwd_passData');
                if (printBtn) {
                    printBtn.style.display = '';
                }
            }

            function buildBulkPayVoucherList(processingNos) {
                var listEl = document.getElementById('bulkPayVoucherList');
                if (!listEl) {
                    return;
                }
                var sharedAda = '';
                var sharedAdaSet = false;
                processingNos.forEach(function(processingNo) {
                    var row = fwdBulkPayRowForProcessingNo(processingNo);
                    var adaCheckNo = fwdBulkPayCellText(row, 'ada_check_no');
                    if (typeof fwdIsInvalidAdaCheckNo === 'function' && fwdIsInvalidAdaCheckNo(adaCheckNo)) {
                        return;
                    }
                    if (!sharedAdaSet) {
                        sharedAda = adaCheckNo;
                        sharedAdaSet = true;
                    } else if (sharedAda !== adaCheckNo) {
                        sharedAda = '';
                    }
                });
                var bulkAdaInput = document.getElementById('bulk_pay_ada_check_no');
                if (bulkAdaInput) {
                    bulkAdaInput.value = sharedAda;
                }
                var rowsHtml = processingNos.map(function(processingNo) {
                    var row = fwdBulkPayRowForProcessingNo(processingNo);
                    return '<tr>' +
                        '<td>' + fwdBulkPayEscapeHtml(processingNo) + '</td>' +
                        '<td>' + fwdBulkPayEscapeHtml(fwdBulkPayCellText(row, 'payee')) + '</td>' +
                        '<td>' + fwdBulkPayEscapeHtml(fwdBulkPayCellText(row, 'dv_no')) + '</td>' +
                        '</tr>';
                }).join('');
                listEl.innerHTML = '<table class="bulk-pay-voucher-table">' +
                    '<thead><tr><th>Processing No.</th><th>Payee</th><th>DV No.</th></tr></thead>' +
                    '<tbody>' + rowsHtml + '</tbody></table>';
            }

            function closeBulkPayVoucherModal(resetAll) {
                var bulkModal = document.getElementById('bulkPayVoucherModal');
                var bulkOverlay = document.getElementById('overlayBulkPayVoucher');
                if (bulkModal) {
                    bulkModal.style.display = 'none';
                }
                if (bulkOverlay) {
                    bulkOverlay.style.display = 'none';
                }
                if (resetAll) {
                    resetBulkPayMode();
                }
            }

            function openBulkPayVoucherModal(processingNos) {
                bulkPayMode = true;
                bulkPayProcessingNos = processingNos.slice();
                bulkPayAdaCheckNos = {};
                var titleEl = document.getElementById('bulk_pay_voucher_title');
                if (titleEl) {
                    titleEl.textContent = 'Pay Vouchers (' + processingNos.length + ')';
                }
                buildBulkPayVoucherList(processingNos);
                var bulkModal = document.getElementById('bulkPayVoucherModal');
                var bulkOverlay = document.getElementById('overlayBulkPayVoucher');
                if (bulkModal) {
                    bulkModal.style.display = 'block';
                    bulkModal.style.animation = 'slideIn 0.5s ease';
                }
                if (bulkOverlay) {
                    bulkOverlay.style.display = 'block';
                }
                var bulkAdaInput = document.getElementById('bulk_pay_ada_check_no');
                if (bulkAdaInput) {
                    window.setTimeout(function() {
                        bulkAdaInput.focus();
                    }, 0);
                }
            }

            function collectBulkPayAdaCheckNos() {
                var adaInput = document.getElementById('bulk_pay_ada_check_no');
                var value = adaInput ? String(adaInput.value || '').trim() : '';
                if (typeof fwdIsInvalidAdaCheckNo === 'function' && fwdIsInvalidAdaCheckNo(value)) {
                    return {
                        map: {},
                        invalid: bulkPayProcessingNos.slice()
                    };
                }
                var map = {};
                bulkPayProcessingNos.forEach(function(processingNo) {
                    map[processingNo] = value;
                });
                return {
                    map: map,
                    invalid: []
                };
            }

            function openBulkProcessModal() {
                fwdApplyAdaSignatoryOfficeUi('');
                var adaFieldWrap = document.getElementById('fwd_bulk_ada_check_wrap');
                if (adaFieldWrap) {
                    adaFieldWrap.style.display = 'none';
                }
                var adaInput = document.getElementById('fwd_ada_check_no');
                if (adaInput) {
                    adaInput.required = false;
                }
                var titleEl = document.getElementById('archive_process_form_title');
                if (titleEl) {
                    titleEl.textContent = 'Process Vouchers (' + bulkPayProcessingNos.length + ')';
                }
                var printBtn = document.getElementById('fwd_passData');
                if (printBtn) {
                    printBtn.style.display = 'none';
                }
                var adaDate = document.getElementById('fwd_ada_date');
                if (adaDate) {
                    adaDate.value = new Date().toISOString().split('T')[0];
                }
                var ovProc = document.getElementById('overlayArchiveProcess');
                if (ovProc) {
                    ovProc.style.display = 'block';
                }
                var adaForm = document.getElementById('archiveAdaForm');
                if (adaForm) {
                    adaForm.style.display = 'block';
                    adaForm.style.animation = 'slideIn 0.5s ease';
                }
                var ov = document.getElementById('overlay');
                if (ov) {
                    ov.style.display = 'block';
                }
            }

            function proceedBulkPayToProcess() {
                var collected = collectBulkPayAdaCheckNos();
                if (collected.invalid.length > 0) {
                    if (typeof showNotify === 'function') {
                        showNotify('Enter a valid ADA/Check No. for all selected vouchers.', 'error', 5000);
                    }
                    var adaInput = document.getElementById('bulk_pay_ada_check_no');
                    if (adaInput && typeof fwdNotifyInvalidAdaCheckNo === 'function') {
                        fwdNotifyInvalidAdaCheckNo(adaInput);
                    }
                    return;
                }
                bulkPayAdaCheckNos = collected.map;
                closeBulkPayVoucherModal(false);
                openBulkProcessModal();
            }

            function runBulkPay() {
                if (bulkPayInFlight) {
                    return;
                }
                var processingNos = selectedBulkPayProcessingNos();
                if (processingNos.length === 0) {
                    if (typeof showNotify === 'function') {
                        showNotify('Select at least one voucher to pay.', 'warning', 2800);
                    }
                    return;
                }
                openBulkPayVoucherModal(processingNos);
            }

            function fwdBulkPaySendSaveData() {
                var formData = fwdCollectArchiveProcessFormData();
                var required = ['certified_correct', 'approved_by', 'agency_authorized_signatory', 'ada_check_date'];
                var missing = required.filter(function(key) {
                    return String(formData[key] || '').trim() === '';
                });
                if (missing.length > 0) {
                    if (typeof showNotify === 'function') {
                        showNotify('Complete all signatory fields before paying selected vouchers.', 'warning', 3500);
                    }
                    return;
                }

                var confirmMsg = 'Pay ' + bulkPayProcessingNos.length + ' selected voucher(s)? They will be processed one at a time.';
                var proceed = function() {
                    bulkPayInFlight = true;
                    if (bulkPayBtn) {
                        bulkPayBtn.disabled = true;
                    }
                    setBulkPayStatus('Paying…');
                    fetch(bulkPayUrl, {
                            method: 'POST',
                            credentials: 'same-origin',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify({
                                token: window.bulkPayToken || bulkPayToken,
                                processing_nos: bulkPayProcessingNos,
                                ada_check_nos: bulkPayAdaCheckNos,
                                certified_correct: formData.certified_correct,
                                approved_by: formData.approved_by,
                                agency_authorized_signatory: formData.agency_authorized_signatory,
                                ada_check_date: formData.ada_check_date
                            })
                        })
                        .then(function(r) {
                            return r.json().then(function(payload) {
                                return {
                                    ok: r.ok,
                                    payload: payload
                                };
                            });
                        })
                        .then(function(res) {
                            bulkPayInFlight = false;
                            if (bulkPayBtn) {
                                bulkPayBtn.disabled = false;
                            }
                            var payload = res.payload || {};
                            if (payload.ok === true) {
                                var msg = payload.message || ('Paid ' + (payload.paid || 0) + ' voucher(s).');
                                if (payload.token) {
                                    window.bulkPayToken = payload.token;
                                }
                                setBulkPayStatus(msg);
                                if (typeof showNotify === 'function') {
                                    showNotify(msg, Number(payload.failed || 0) > 0 ? 'warning' : 'success', 4000);
                                }
                                closeArchiveAdaPopup();
                                window.location.href = 'voucher_forwarding.php';
                                return;
                            }
                            var err = payload.error || payload.message || 'Bulk pay failed.';
                            setBulkPayStatus('');
                            if (typeof showNotify === 'function') {
                                showNotify(err, 'error', 5000);
                            }
                        })
                        .catch(function() {
                            bulkPayInFlight = false;
                            if (bulkPayBtn) {
                                bulkPayBtn.disabled = false;
                            }
                            setBulkPayStatus('');
                            if (typeof showNotify === 'function') {
                                showNotify('Bulk pay request failed.', 'error', 4000);
                            }
                        });
                };

                if (typeof functionAlert === 'function') {
                    functionAlert(confirmMsg, 'bulk-pay-confirm', proceed);
                } else if (window.confirm(confirmMsg)) {
                    proceed();
                }
            }

            function fwdNormalizeAdaOfficeKey(value) {
                return String(value || '').trim().replace(/\s+/g, ' ');
            }

            function fwdResolveAdaOfficeKey(officeFrom, encodedFrom) {
                var officeFromKey = fwdNormalizeAdaOfficeKey(officeFrom);
                if (officeFromKey !== '') {
                    return officeFromKey;
                }
                return fwdNormalizeAdaOfficeKey(encodedFrom);
            }

            function fwdFindAdaSignatoryBundle(officeFrom, encodedFrom) {
                var targetKey = fwdResolveAdaOfficeKey(officeFrom, encodedFrom);
                var bundles = fwdAdaSignatoryBundles || {};
                var keys = Object.keys(bundles);
                var fallback = bundles.__default__ || {
                    options: {},
                    defaults: fwdAdaSignatoryDefaults || {}
                };

                if (targetKey === '') {
                    return fallback;
                }

                for (var i = 0; i < keys.length; i++) {
                    var key = keys[i];
                    if (key === '__default__') {
                        continue;
                    }
                    if (key.toLowerCase() === targetKey.toLowerCase()) {
                        return bundles[key];
                    }
                }

                return fallback;
            }

            function fwdRebuildAdaSignatorySelect(select, optionValues, defaultValue) {
                if (!select) return;
                var values = Array.isArray(optionValues) ? optionValues : [];
                var hasDefault = defaultValue !== '' && values.indexOf(defaultValue) !== -1;
                select.innerHTML = '';

                var placeholder = document.createElement('option');
                placeholder.value = '';
                placeholder.disabled = true;
                placeholder.selected = !hasDefault;
                placeholder.textContent = 'Please Select:';
                select.appendChild(placeholder);

                if (!values.length) {
                    var emptyOpt = document.createElement('option');
                    emptyOpt.value = '';
                    emptyOpt.disabled = true;
                    emptyOpt.textContent = '(No options configured — ask System Admin)';
                    select.appendChild(emptyOpt);
                    return;
                }

                values.forEach(function(value) {
                    var opt = document.createElement('option');
                    opt.value = value;
                    opt.textContent = value;
                    if (hasDefault && value === defaultValue) {
                        opt.selected = true;
                    }
                    select.appendChild(opt);
                });
            }

            function fwdListAdaOfficeOptions() {
                var offices = Array.isArray(fwdAdaSignatoryOffices) ? fwdAdaSignatoryOffices.slice() : [];
                var bundles = fwdAdaSignatoryBundles || {};
                Object.keys(bundles).forEach(function(key) {
                    if (key === '__default__') {
                        return;
                    }
                    var exists = offices.some(function(office) {
                        return String(office).toLowerCase() === String(key).toLowerCase();
                    });
                    if (!exists) {
                        offices.push(key);
                    }
                });
                offices.sort(function(a, b) {
                    return String(a).localeCompare(String(b), undefined, {
                        sensitivity: 'base'
                    });
                });
                return offices;
            }

            function fwdPopulateAdaOfficeSelect(selectedOffice) {
                var select = document.getElementById('fwd_ada_office_select');
                if (!select) {
                    return;
                }
                var offices = fwdListAdaOfficeOptions();
                var resolved = String(selectedOffice || fwdAdaDefaultOffice || offices[0] || '').trim();

                select.innerHTML = '';
                if (!offices.length) {
                    var emptyOpt = document.createElement('option');
                    emptyOpt.value = '';
                    emptyOpt.disabled = true;
                    emptyOpt.selected = true;
                    emptyOpt.textContent = '(No offices configured)';
                    select.appendChild(emptyOpt);
                    return;
                }

                offices.forEach(function(officeName) {
                    var option = document.createElement('option');
                    option.value = officeName;
                    option.textContent = officeName;
                    if (resolved !== '' && String(officeName).toLowerCase() === resolved.toLowerCase()) {
                        option.selected = true;
                    }
                    select.appendChild(option);
                });

                if (!select.value && offices.length) {
                    select.selectedIndex = 0;
                }
            }

            function fwdResolveBulkPaySignatoryOffice() {
                var resolvedOffices = [];
                bulkPayProcessingNos.forEach(function(processingNo) {
                    var row = fwdBulkPayRowForProcessingNo(processingNo);
                    var officeFrom = fwdBulkPayCellText(row, 'office_from');
                    var encodedFrom = fwdBulkPayCellText(row, 'encoded_from');
                    var resolved = fwdResolveAdaOfficeKey(officeFrom, encodedFrom);
                    if (resolved !== '') {
                        var exists = resolvedOffices.some(function(office) {
                            return office.toLowerCase() === resolved.toLowerCase();
                        });
                        if (!exists) {
                            resolvedOffices.push(resolved);
                        }
                    }
                });
                return resolvedOffices.length === 1 ? resolvedOffices[0] : '';
            }

            function fwdShouldShowAdaOfficeSelect() {
                return !!fwdCanSelectAdaSignatoryOffice || bulkPayMode;
            }

            function fwdApplyAdaSignatoryOfficeUi(preferredOffice) {
                var wrap = document.getElementById('fwd_ada_office_wrap');
                var select = document.getElementById('fwd_ada_office_select');
                var showOfficeSelect = fwdShouldShowAdaOfficeSelect();
                if (wrap) {
                    wrap.style.display = showOfficeSelect ? '' : 'none';
                }
                if (!showOfficeSelect) {
                    var fwdForm = document.getElementById('myForm_Forwarding');
                    var officeFrom = '';
                    var encodedFrom = '';
                    if (fwdForm) {
                        var officeFromEl = fwdForm.querySelector('[name="office_from"]');
                        var encodedFromEl = fwdForm.querySelector('[name="encoded_from"]');
                        officeFrom = officeFromEl ? String(officeFromEl.value || '').trim() : '';
                        encodedFrom = encodedFromEl ? String(encodedFromEl.value || '').trim() : '';
                    }
                    fwdApplyAdaSignatoryForOffice(officeFrom, encodedFrom);
                    return;
                }

                var office = String(preferredOffice || '').trim();
                if (!office && select && select.value) {
                    office = String(select.value || '').trim();
                }
                if (!office && bulkPayMode) {
                    office = fwdResolveBulkPaySignatoryOffice();
                }
                if (!office) {
                    var fwdFormSingle = document.getElementById('myForm_Forwarding');
                    if (fwdFormSingle) {
                        var officeFromElSingle = fwdFormSingle.querySelector('[name="office_from"]');
                        var encodedFromElSingle = fwdFormSingle.querySelector('[name="encoded_from"]');
                        office = fwdResolveAdaOfficeKey(
                            officeFromElSingle ? officeFromElSingle.value : '',
                            encodedFromElSingle ? encodedFromElSingle.value : ''
                        );
                    }
                }
                if (!office) {
                    office = String(fwdAdaDefaultOffice || '').trim();
                }

                fwdPopulateAdaOfficeSelect(office);
                var selectedOffice = select ? String(select.value || '').trim() : office;
                fwdApplyAdaSignatoryForOffice(selectedOffice, '');
            }

            function fwdApplyAdaSignatoryForOffice(officeFrom, encodedFrom) {
                var form = document.getElementById('myForm_ArchiveProcessing');
                if (!form) return;

                var bundle = fwdFindAdaSignatoryBundle(officeFrom, encodedFrom);
                var options = (bundle && bundle.options) ? bundle.options : {};
                var defaults = (bundle && bundle.defaults) ? bundle.defaults : (fwdAdaSignatoryDefaults || {});

                ['certified_correct', 'approved_by', 'agency_authorized_signatory'].forEach(function(name) {
                    var select = form.querySelector('select[name="' + name + '"]');
                    fwdRebuildAdaSignatorySelect(select, options[name] || [], defaults[name] || '');
                });
            }

            function fwdApplyAdaSignatoryDefaults() {
                fwdApplyAdaSignatoryOfficeUi('');
            }

            function fwdIsInvalidAdaCheckNo(value) {
                var v = String(value || '').trim();
                return v === '' || v.toUpperCase() === 'TBD';
            }

            function fwdNotifyInvalidAdaCheckNo(focusEl) {
                if (typeof showNotify === 'function') {
                    showNotify("Please enter a valid ADA/Check No. before paying. Empty or 'TBD' is not allowed.", 'error', 3500);
                }
                if (focusEl) {
                    focusEl.focus();
                }
            }

            function fwdSyncPayVoucherAdaCheckNo() {
                var payAdaCheckNo = document.getElementById('ada_check_no');
                var processAdaCheckNo = document.getElementById('fwd_ada_check_no');
                if (!payAdaCheckNo || !processAdaCheckNo) return;
                processAdaCheckNo.value = String(payAdaCheckNo.value || '').trim();
            }

            function fwdCollectForwardingVoucherRow() {
                var f = document.getElementById('myForm_Forwarding');
                if (!f) return {};

                function val(name) {
                    var el = f.querySelector('[name="' + name + '"]');
                    return el ? String(el.value).trim() : '';
                }
                var strAmt = val('string_amount');
                var numAmt = val('amount');
                var dispAmt = strAmt || numAmt;
                return {
                    processing_no: val('processing_no'),
                    ors_no: val('ors_no'),
                    dv_no: val('dv_no'),
                    payee: val('payee'),
                    address: val('address'),
                    particulars: val('particulars'),
                    tin_employee_no: val('tin_employee_no'),
                    amount: dispAmt,
                    final_amount: numAmt || strAmt,
                    voucher_date: val('voucher_date'),
                    voucher_type: val('voucher_type'),
                    office_to: val('office_to'),
                    office_from: val('office_from'),
                    encoded_by: val('encoded_by'),
                    datetime_encoded: val('datetime_encoded'),
                    remarks: val('combined_remarks') || val('remarks'),
                    process_history: val('process_history')
                };
            }

            function fwdCollectArchiveProcessFormData() {
                var form = document.getElementById('myForm_ArchiveProcessing');
                if (!form) return {};
                var fd = new FormData(form);
                var data = {};
                fd.forEach(function(value, key) {
                    data[key] = value;
                });
                // Back-compat: server expects both check_no and ada_no, but UI uses one field.
                if (data.ada_check_no && (!data.check_no || !data.ada_no)) {
                    data.check_no = data.check_no || data.ada_check_no;
                    data.ada_no = data.ada_no || data.ada_check_no;
                }
                return data;
            }

            function fwdArchiveSendSaveData() {
                if (bulkPayMode) {
                    fwdBulkPaySendSaveData();
                    return;
                }
                fwdSyncPayVoucherAdaCheckNo();
                var adaInput = document.getElementById('fwd_ada_check_no') || document.getElementById('ada_check_no');
                if (fwdIsInvalidAdaCheckNo(adaInput ? adaInput.value : '')) {
                    fwdNotifyInvalidAdaCheckNo(adaInput);
                    return;
                }
                var tableData = [Object.assign({}, fwdCollectForwardingVoucherRow(), fwdCollectArchiveProcessFormData())];
                var combinedData = {
                    data: tableData
                };
                if (typeof functionAlert !== 'function') {
                    console.error('functionAlert is not available');
                    return;
                }
                functionAlert('Are you sure you want to submit the data?', 'ada-submit-confirm', function() {
                    fetch('../../protected/handler/voucher_ada_multi/multi_handler.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify(combinedData)
                        })
                        .then(function(response) {
                            return response.text().then(function(text) {
                                return {
                                    ok: response.ok,
                                    text: text
                                };
                            });
                        })
                        .then(function(payload) {
                            var result = (payload.text || '').trim();
                            var parsed = null;
                            try {
                                parsed = JSON.parse(result);
                            } catch (e) {
                                var start = result.indexOf('{');
                                var end = result.lastIndexOf('}');
                                if (start >= 0 && end > start) {
                                    try {
                                        parsed = JSON.parse(result.slice(start, end + 1));
                                    } catch (e2) {
                                        parsed = null;
                                    }
                                }
                            }

                            if (parsed && typeof parsed === 'object' && !Array.isArray(parsed)) {
                                if (parsed.ok === true) {
                                    if (typeof showNotify === 'function') {
                                        showNotify(parsed.message || 'Data saved successfully!', parsed.notify_type || 'success', 2500);
                                    }
                                    window.location.href = 'voucher_forwarding.php';
                                    return;
                                }
                                if (parsed.ok === false) {
                                    if (typeof showNotify === 'function') {
                                        showNotify(parsed.error || 'ADA save failed.', parsed.notify_type || 'error', 6000);
                                    }
                                    return;
                                }
                            }

                            if (result === 'Data saved successfully!' || result.indexOf('Data saved successfully!') !== -1) {
                                if (typeof showNotify === 'function') {
                                    showNotify('Data saved successfully!', 'success', 2500);
                                }
                                window.location.href = 'voucher_forwarding.php';
                                return;
                            }

                            if (!payload.ok) {
                                if (typeof showNotify === 'function') {
                                    showNotify(result || 'ADA save failed.', 'error', 6000);
                                }
                                return;
                            }

                            if (typeof showNotify === 'function') {
                                showNotify('Unexpected save response. Please refresh and verify the voucher status.', 'warning', 5000);
                            }
                        })
                        .catch(function(error) {
                            console.error('Error:', error);
                            if (typeof showNotify === 'function') {
                                showNotify('ADA save failed. ' + (error && error.message ? error.message : ''), 'error', 6000);
                            }
                        });
                });
            }

            function fwdArchiveSendPrintData() {
                fwdSyncPayVoucherAdaCheckNo();
                var tableData = [Object.assign({}, fwdCollectForwardingVoucherRow(), fwdCollectArchiveProcessFormData())];
                var combinedData = {
                    data: tableData
                };
                if (typeof functionAlert !== 'function') {
                    console.error('functionAlert is not available');
                    return;
                }
                functionAlert('Are you sure you want to print the data?', 'ada-print-confirm', function() {
                    fetch('lddap.php', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json'
                            },
                            body: JSON.stringify(combinedData)
                        })
                        .then(function(response) {
                            return response.text();
                        })
                        .then(function(result) {
                            var ovProc = document.getElementById('overlayArchiveProcess');
                            if (ovProc) {
                                ovProc.style.display = 'block';
                            }
                            var adaEl = document.getElementById('archiveAdaForm');
                            if (adaEl) {
                                adaEl.style.display = 'block';
                                adaEl.style.animation = 'slideIn 0.5s ease';
                            }
                            var ov = document.getElementById('overlay');
                            if (ov) ov.style.display = 'block';
                            var resultDiv = document.getElementById('fwd_lddap_result');
                            if (resultDiv) {
                                resultDiv.innerHTML = result;
                            } else {
                                document.body.insertAdjacentHTML('beforeend', '<div id="fwd_lddap_result">' + result + '</div>');
                            }
                            window.print();
                        })
                        .catch(function(error) {
                            console.error('Error:', error);
                        });
                });
            }

            function fwdArchiveCheckPrintInputs() {
                fwdSyncPayVoucherAdaCheckNo();
                var adaInput = document.getElementById('fwd_ada_check_no') || document.getElementById('ada_check_no');
                if (fwdIsInvalidAdaCheckNo(adaInput ? adaInput.value : '')) {
                    fwdNotifyInvalidAdaCheckNo(adaInput);
                    return;
                }
                var inputs = document.querySelectorAll('#myForm_ArchiveProcessing input, #myForm_ArchiveProcessing select');
                var allFilled = Array.from(inputs).every(function(input) {
                    return input.value.trim() !== '';
                });
                if (allFilled) {
                    fwdArchiveSendPrintData();
                }
            }

            function closeArchiveAdaPopup() {
                resetBulkPayMode();
                var adaEl = document.getElementById('archiveAdaForm');
                if (adaEl) {
                    adaEl.style.display = 'none';
                }
                var ovProc = document.getElementById('overlayArchiveProcess');
                if (ovProc) {
                    ovProc.style.display = 'none';
                }
                var ov = document.getElementById('overlay');
                var mainPop = document.getElementById('popupForm');
                if (ov && mainPop && mainPop.style.display !== 'block') {
                    ov.style.display = 'none';
                }
            }

            function openArchiveProcessDocumentFromModal() {
                fwdSyncPayVoucherAdaCheckNo();
                fwdApplyAdaSignatoryDefaults();

                var adaDate = document.getElementById('fwd_ada_date');
                if (adaDate) {
                    adaDate.value = new Date().toISOString().split('T')[0];
                }
                var ovProc = document.getElementById('overlayArchiveProcess');
                if (ovProc) {
                    ovProc.style.display = 'block';
                }
                var adaForm = document.getElementById('archiveAdaForm');
                if (adaForm) {
                    adaForm.style.display = 'block';
                    adaForm.style.animation = 'slideIn 0.5s ease';
                }
                var ov = document.getElementById('overlay');
                if (ov) {
                    ov.style.display = 'block';
                }
            }

            document.addEventListener('DOMContentLoaded', function() {
                var fwdForm = document.getElementById('myForm_Forwarding');
                if (fwdForm) {
                    fwdForm.addEventListener('submit', function(e) {
                        var actionBtn = document.querySelector('.btn-dynamic');
                        if (!actionBtn || actionBtn.getAttribute('name') !== 'archive_voucher') {
                            return;
                        }
                        e.preventDefault();
                        e.stopPropagation();
                        var adaInput = document.getElementById('ada_check_no');
                        if (fwdIsInvalidAdaCheckNo(adaInput ? adaInput.value : '')) {
                            fwdNotifyInvalidAdaCheckNo(adaInput);
                            return;
                        }
                        openArchiveProcessDocumentFromModal();
                    });
                }

                var adaOfficeSelect = document.getElementById('fwd_ada_office_select');
                if (adaOfficeSelect) {
                    adaOfficeSelect.addEventListener('change', function() {
                        fwdApplyAdaSignatoryForOffice(String(adaOfficeSelect.value || '').trim(), '');
                    });
                }

                fwdApplyAdaSignatoryDefaults();

                var adaDate = document.getElementById('fwd_ada_date');
                if (adaDate && !adaDate.value) {
                    adaDate.value = new Date().toISOString().split('T')[0];
                }

                var ovGlobal = document.getElementById('overlay');
                if (ovGlobal) {
                    ovGlobal.addEventListener('click', function() {
                        var ada = document.getElementById('archiveAdaForm');
                        if (ada && ada.style.display === 'block') {
                            closeArchiveAdaPopup();
                        }
                    }, true);
                }

                var ovProcClick = document.getElementById('overlayArchiveProcess');
                if (ovProcClick) {
                    ovProcClick.addEventListener('click', function() {
                        closeArchiveAdaPopup();
                    });
                }

                var saveBtn = document.getElementById('fwd_saveData');
                if (saveBtn) {
                    saveBtn.addEventListener('click', function(e) {
                        if (e && typeof e.preventDefault === 'function') e.preventDefault();
                        fwdArchiveSendSaveData();
                    });
                }

                var printBtn = document.getElementById('fwd_passData');
                if (printBtn) {
                    printBtn.addEventListener('click', function(e) {
                        if (e && typeof e.preventDefault === 'function') e.preventDefault();
                        fwdArchiveCheckPrintInputs();
                    });
                }

                var cx = document.getElementById('close_archive_ada_header');
                var cf = document.getElementById('close_archive_ada_footer');
                if (cx) cx.addEventListener('click', closeArchiveAdaPopup);
                if (cf) cf.addEventListener('click', closeArchiveAdaPopup);

                if (bulkPaySelectAllEl) {
                    bulkPaySelectAllEl.addEventListener('change', function() {
                        var checked = !!bulkPaySelectAllEl.checked;
                        bulkPaySelectAllEl.indeterminate = false;
                        document.querySelectorAll('#my-Table input.voucher-bulk-pay-select').forEach(function(cb) {
                            cb.checked = checked;
                        });
                        syncBulkPaySelectAllState();
                    });
                }
                if (bulkPayBtn) {
                    bulkPayBtn.addEventListener('click', runBulkPay);
                }
                var bulkPayContinueBtn = document.getElementById('bulkPayVoucherContinue');
                if (bulkPayContinueBtn) {
                    bulkPayContinueBtn.addEventListener('click', proceedBulkPayToProcess);
                }
                var bulkPayCloseHeader = document.getElementById('close_bulk_pay_voucher_header');
                var bulkPayCloseFooter = document.getElementById('close_bulk_pay_voucher_footer');
                var bulkPayOverlay = document.getElementById('overlayBulkPayVoucher');
                if (bulkPayCloseHeader) {
                    bulkPayCloseHeader.addEventListener('click', function() {
                        closeBulkPayVoucherModal(true);
                    });
                }
                if (bulkPayCloseFooter) {
                    bulkPayCloseFooter.addEventListener('click', function() {
                        closeBulkPayVoucherModal(true);
                    });
                }
                if (bulkPayOverlay) {
                    bulkPayOverlay.addEventListener('click', function(e) {
                        if (e.target === bulkPayOverlay) {
                            closeBulkPayVoucherModal(true);
                        }
                    });
                }
                var bulkPayModalEl = document.getElementById('bulkPayVoucherModal');
                if (bulkPayModalEl) {
                    bulkPayModalEl.addEventListener('click', function(e) {
                        e.stopPropagation();
                    });
                }
                document.querySelectorAll('#my-Table input.voucher-bulk-pay-select').forEach(function(cb) {
                    cb.addEventListener('change', syncBulkPaySelectAllState);
                });
                syncBulkPaySelectAllState();
            });
        })();
    </script>
    <!-- Bulk Pay Voucher: shared ADA/Check No. for all selected vouchers before Process Voucher -->
    <div class="overlay voucher-premium-overlay" id="overlayBulkPayVoucher" style="display: none;" aria-hidden="true"></div>
    <div class="popup-form voucher-premium-modal popup-form--compact" id="bulkPayVoucherModal" style="display: none;">
        <div class="popupForm-box__container">
            <div class="popupForm-header__container">
                <p id="bulk_pay_voucher_title">Pay Vouchers</p>
                <i class="ri-close-fill close-icon" id="close_bulk_pay_voucher_header" role="button" tabindex="0" aria-label="Close"></i>
            </div>
            <div class="f-container">
                <div class="popupForm-body__container">
                    <div class="bulk-pay-ada-field">
                        <label for="bulk_pay_ada_check_no">ADA/Check No.</label>
                        <input type="text" class="form-custom-input" id="bulk_pay_ada_check_no" name="bulk_pay_ada_check_no" placeholder="Enter ADA/Check No." autocomplete="off">
                    </div>
                    <p class="bulk-pay-voucher-hint">The same ADA/Check No. will apply to all selected vouchers.</p>
                    <div class="bulk-pay-voucher-list-wrap" id="bulkPayVoucherList"></div>
                </div>
                <div class="popupForm-footer__container">
                    <div class="footer-button__container">
                        <button class="btn transparent primary" id="bulkPayVoucherContinue" type="button">CONTINUE</button>
                        <button class="btn secondary transparent" id="close_bulk_pay_voucher_footer" type="button">CANCEL</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Process Voucher: end-of-body so it is not inside #main / #popupForm; second layer uses #overlayArchiveProcess + higher z-index -->
    <div class="overlay overlay-archive-process" id="overlayArchiveProcess" style="display: none;" aria-hidden="true"></div>
    <div class="popup-form voucher-premium-modal popup-form--compact fwd-process-document-modal" id="archiveAdaForm" style="display: none;">
        <div class="popupForm-box__container">
            <div class="popupForm-header__container">
                <p id="archive_process_form_title">Process Voucher</p>
                <i class="ri-close-fill close-icon" id="close_archive_ada_header" role="button" tabindex="0" aria-label="Close"></i>
            </div>
            <form class="f-container fwdArchiveTargetForm" id="myForm_ArchiveProcessing" action="#" method="post" onsubmit="return false;">
                <div class="box-body__container flex-row">
                    <div class="popupForm-body__container">
                        <div class="form-container">
                            <div class="label-input__container" id="fwd_ada_office_wrap" style="display: none;">
                                <label for="fwd_ada_office_select">Office</label>
                                <select class="form-custom-input" id="fwd_ada_office_select"></select>
                            </div>
                            <div class="label-input__container">
                                <label for="">Certified Correct:</label>
                                <select name="certified_correct" class="form-custom-input" required>
                                    <?php
                                    $list = $ada_options['certified_correct'] ?? [];
                                    $defaultVal = $ada_option_defaults['certified_correct'] ?? '';
                                    $hasDefault = $defaultVal !== '' && in_array($defaultVal, $list, true);
                                    ?>
                                    <option value="" disabled <?= $hasDefault ? '' : 'selected' ?>>Please Select:</option>
                                    <?php
                                    if (!$list) :
                                    ?>
                                        <option value="" disabled>(No options configured — ask System Admin)</option>
                                        <?php
                                    else :
                                        foreach ($list as $v) :
                                            $selected = ($hasDefault && $v === $defaultVal) ? ' selected' : '';
                                        ?>
                                            <option value="<?= htmlspecialchars($v, ENT_QUOTES, 'UTF-8') ?>" <?= $selected ?>><?= htmlspecialchars($v, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php
                                        endforeach;
                                    endif;
                                    ?>
                                </select>
                            </div>
                            <div class="label-input__container">
                                <label for="">Approved By:</label>
                                <select name="approved_by" class="form-custom-input" required>
                                    <?php
                                    $list = $ada_options['approved_by'] ?? [];
                                    $defaultVal = $ada_option_defaults['approved_by'] ?? '';
                                    $hasDefault = $defaultVal !== '' && in_array($defaultVal, $list, true);
                                    ?>
                                    <option value="" disabled <?= $hasDefault ? '' : 'selected' ?>>Please Select:</option>
                                    <?php
                                    if (!$list) :
                                    ?>
                                        <option value="" disabled>(No options configured — ask System Admin)</option>
                                        <?php
                                    else :
                                        foreach ($list as $v) :
                                            $selected = ($hasDefault && $v === $defaultVal) ? ' selected' : '';
                                        ?>
                                            <option value="<?= htmlspecialchars($v, ENT_QUOTES, 'UTF-8') ?>" <?= $selected ?>><?= htmlspecialchars($v, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php
                                        endforeach;
                                    endif;
                                    ?>
                                </select>
                            </div>
                            <div class="label-input__container">
                                <label for="">Agency Authorized Signatory:</label>
                                <select name="agency_authorized_signatory" class="form-custom-input" required>
                                    <?php
                                    $list = $ada_options['agency_authorized_signatory'] ?? [];
                                    $defaultVal = $ada_option_defaults['agency_authorized_signatory'] ?? '';
                                    $hasDefault = $defaultVal !== '' && in_array($defaultVal, $list, true);
                                    ?>
                                    <option value="" disabled <?= $hasDefault ? '' : 'selected' ?>>Please Select:</option>
                                    <?php
                                    if (!$list) :
                                    ?>
                                        <option value="" disabled>(No options configured — ask System Admin)</option>
                                        <?php
                                    else :
                                        foreach ($list as $v) :
                                            $selected = ($hasDefault && $v === $defaultVal) ? ' selected' : '';
                                        ?>
                                            <option value="<?= htmlspecialchars($v, ENT_QUOTES, 'UTF-8') ?>" <?= $selected ?>><?= htmlspecialchars($v, ENT_QUOTES, 'UTF-8') ?></option>
                                    <?php
                                        endforeach;
                                    endif;
                                    ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="popupForm-body__container">
                        <div class="form-container">
                            <div class="label-input__container" id="fwd_bulk_ada_check_wrap">
                                <label for="">ADA/Check No:</label>
                                <input type="text" class="form-custom-input" name="ada_check_no" id="fwd_ada_check_no" required style="border: 2px solid red;">
                            </div>
                            <div class="label-input__container">
                                <label for="">Date:</label>
                                <input type="date" class="form-custom-input" name="ada_check_date" id="fwd_ada_date" required>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="popupForm-footer__container">
                    <div class="footer-button__container">
                        <button class="btn transparent primary" id="fwd_saveData" type="button">SAVE</button>
                        <!-- <button class="btn transparent warning" id="fwd_passData" type="button">PRINT</button> -->
                        <button class="btn secondary transparent" id="close_archive_ada_footer" type="button">CANCEL</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>
<script>
    // Return options popup (Forwarding table)
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
            'CONSERVATION & DEVELOPMENT': 'Conservation & Development Section',
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
            return {
                user: user,
                action: action,
                section: section
            };
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

        function loadReturnOffices(processHistory, officeFrom, encodedFrom, encodedBy) {
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
            var selected = document.querySelector('input[name="return_destination_popup"]:checked');
            if (!confirmBtn) return;
            confirmBtn.textContent = selected && selected.value === 'retract' ? 'Retract' : 'Return';
        }

        function showPopup(processingNo, processHistory, officeFrom, encodedFrom, encodedBy) {
            if (popup) popup.style.display = 'block';
            if (overlay) overlay.style.display = 'block';
            loadReturnOffices(processHistory, officeFrom, encodedFrom, encodedBy);
            updateReturnConfirmLabel();
        }

        function hidePopup() {
            if (popup) popup.style.display = 'none';
            if (overlay) overlay.style.display = 'none';
            document.querySelectorAll('input[name="return_destination_popup"]').forEach(function(r) {
                r.checked = false;
            });
            var returnOfficeContainer = document.getElementById('return_office_container');
            var returnOfficeSelect = document.getElementById('return_office_select');
            if (returnOfficeContainer) returnOfficeContainer.style.display = 'none';
            if (returnOfficeSelect) returnOfficeSelect.selectedIndex = 0;
            var remarksField = document.getElementById('return_remarks_popup');
            if (remarksField) remarksField.value = '';
            updateReturnConfirmLabel();
        }

        window.openReturnOptionsPopup = showPopup;

        if (closeBtn) closeBtn.addEventListener('click', hidePopup);
        if (cancelBtn) cancelBtn.addEventListener('click', hidePopup);
        if (overlay) overlay.addEventListener('click', hidePopup);

        var previousSenderRadio = document.querySelector('input[name="return_destination_popup"][value="previous_sender"]');
        var encoderRadio = document.querySelector('input[name="return_destination_popup"][value="encoder"]');
        var retractRadio = document.querySelector('input[name="return_destination_popup"][value="retract"]');
        var returnOfficeContainer = document.getElementById('return_office_container');

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
                if (this.checked) returnOfficeContainer.style.display = 'block';
            });
        }
        if (encoderRadio && returnOfficeContainer) {
            encoderRadio.addEventListener('change', function() {
                if (this.checked) returnOfficeContainer.style.display = 'none';
            });
        }
        if (retractRadio && returnOfficeContainer) {
            retractRadio.addEventListener('change', function() {
                if (this.checked) returnOfficeContainer.style.display = 'none';
            });
        }

        if (confirmBtn) {
            confirmBtn.addEventListener('click', function() {
                var selected = document.querySelector('input[name="return_destination_popup"]:checked');
                if (!selected) {
                    if (typeof showNotify === 'function') {
                        showNotify('Please select where to return the voucher.', 'error', 3000);
                    }
                    return;
                }

                var destinationValue = selected.value;
                var remarksValue = (document.getElementById('return_remarks_popup')?.value || '').trim();
                var destinationInput = document.getElementById('return_destination');
                var remarksInput = document.querySelector('#myForm_Forwarding .remarks');
                var returnTargetEl = document.getElementById('return_target_section');
                var form = document.getElementById('myForm_Forwarding');

                if (destinationValue === 'retract') {
                    if (remarksInput) {
                        remarksInput.value = remarksValue === '' ? 'NULL' : remarksValue;
                    }
                    if (form) {
                        form.setAttribute('action', '../../protected/handler/voucher_return_module/voucher_retract_handler.php');
                        var hiddenRetractSubmit = document.getElementById('hidden_retract_submit');
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
                if (remarksInput) {
                    remarksInput.value = remarksValue === '' ? 'NULL' : remarksValue;
                }
                if (returnTargetEl) {
                    returnTargetEl.value = '';
                }
                if (destinationValue === 'previous_sender') {
                    var office = document.getElementById('return_office_select')?.value || '';
                    if (!office) {
                        if (typeof showNotify === 'function') {
                            showNotify('Please select the previous process to return to.', 'error', 3000);
                        }
                        return;
                    }
                    if (returnTargetEl) {
                        returnTargetEl.value = office;
                    }
                }

                if (form) {
                    form.setAttribute('action', '../../protected/handler/voucher_return_module/voucher_return_handler.php');
                    var hiddenReturnSubmit = document.getElementById('hidden_return_submit');
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

</html>