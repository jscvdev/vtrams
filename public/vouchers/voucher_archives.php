<?php
include('../includes/header.php');
require_once __DIR__ . '/../../protected/core/components/helpers/audit_helper.inc.php';
require_once __DIR__ . '/../../protected/core/components/notifications/notification.inc.php';
require_once __DIR__ . '/../../protected/core/components/security/filter_input.inc.php';
require_once __DIR__ . '/../../protected/core/components/helpers/cursor_pagination_helper.php';
require_once __DIR__ . '/../../protected/core/components/helpers/schema_cache_helper.inc.php';
require_once __DIR__ . '/../../protected/core/components/helpers/amount_helper.inc.php';
AuditHelper::logPageView('Voucher Archives');
include('../../protected/handler/archiving_module/archiving_errhandler.inc.php');
require_once __DIR__ . '/checklist_config.php';
include 'lddap.php';
check_archiving_errors();
$dashboard_voucher_types = checklist_types_with_labels();

$rowsPerPage = clamp_int($_GET['rowsPerPage'] ?? null, 1, 50, 50);
$maxBrowse = 100;
$voucher_type_filter = isset($_GET['voucher_type']) && $_GET['voucher_type'] !== 'all' ? trim((string) $_GET['voucher_type']) : 'all';
$rawQ = (string) ($_GET['q'] ?? '');
$q = filterInput($rawQ);
$invalidSearch = (trim($rawQ) !== '' && $q === '');

function build_archives_page_url(int $page, string $voucherType, string $rawSearch = '', int $rowsPerPage = 50): string
{
    $params = ['page' => $page, 'rowsPerPage' => $rowsPerPage];
    if ($voucherType !== 'all') {
        $params['voucher_type'] = $voucherType;
    }
    if ($rawSearch !== '') {
        $params['q'] = $rawSearch;
    }

    return '?' . http_build_query($params);
}

$searchParams = [];
$searchSql = '';
if (!$invalidSearch && $q !== '') {
    $pat = '%' . $q . '%';
    // voucher_archives may have either `ada_check_no` (new) or `check_no`/`ada_no` (legacy). Only reference columns that exist.
    $existingCols = schema_table_column_map($pdo, 'voucher_archives');

    $desiredTextCols = [
        'processing_no', 'ors_no', 'ada_check_no', 'check_no', 'ada_no', 'dv_no',
        'payee', 'address', 'particulars', 'tin_employee_no', 'voucher_type',
        'certified_correct', 'approved_by', 'agency_authorized_signatory',
        'voucher_date', 'ada_check_date',
        'encoded_by', 'datetime_encoded',
        'action', 'action_by', 'datetime_action',
        'office_from', 'office_to',
        'remarks', 'process_history', 'supporting_documents',
        'coa_category', 'coa_subsection', 'coa_options',
        'sender_udc', 'receiver_udc', 'priority',
    ];

    $parts = [];
    $i = 0;
    foreach ($desiredTextCols as $col) {
        if (!isset($existingCols[$col])) {
            continue;
        }
        $ph = ':sq' . $i;
        $parts[] = '`' . $col . '` LIKE ' . $ph;
        $searchParams[$ph] = [$pat, PDO::PARAM_STR];
        $i++;
    }
    if (isset($existingCols['amount'])) {
        $ph = ':sq' . $i;
        $parts[] = 'CAST(`amount` AS CHAR) LIKE ' . $ph;
        $searchParams[$ph] = [$pat, PDO::PARAM_STR];
        $i++;
    }
    if (isset($existingCols['charged_amount'])) {
        $ph = ':sq' . $i;
        $parts[] = 'CAST(`charged_amount` AS CHAR) LIKE ' . $ph;
        $searchParams[$ph] = [$pat, PDO::PARAM_STR];
        $i++;
    }

    if ($parts === []) {
        $searchSql = ' AND 1=0';
    } else {
        $searchSql = ' AND (' . implode(' OR ', $parts) . ')';
    }
}

if ($invalidSearch) {
    $dbCount = 0;
} else {
    $archives_voucher_data_queryCount = 'SELECT COUNT(*) AS total FROM voucher_archives WHERE office_to = :office_to';
    if ($voucher_type_filter !== 'all') {
        $archives_voucher_data_queryCount .= ' AND voucher_type = :voucher_type';
    }
    $archives_voucher_data_queryCount .= $searchSql;
    $archives_voucher_data_statementCount = $pdo->prepare($archives_voucher_data_queryCount);
    $archives_voucher_data_statementCount->bindParam(':office_to', $_SESSION['logged_user_office']);
    if ($voucher_type_filter !== 'all') {
        $archives_voucher_data_statementCount->bindParam(':voucher_type', $voucher_type_filter);
    }
    foreach ($searchParams as $key => $pair) {
        $archives_voucher_data_statementCount->bindValue($key, $pair[0], $pair[1]);
    }
    $archives_voucher_data_statementCount->execute();
    $dbCount = (int) $archives_voucher_data_statementCount->fetch(PDO::FETCH_ASSOC)['total'];
}

$displayTotal = min($dbCount, $maxBrowse);
$totalPages = $displayTotal > 0 ? (int) ceil($displayTotal / $rowsPerPage) : 1;
$currentPage = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$currentPage = max(1, $currentPage);
$currentPage = min($currentPage, $totalPages);
$offset = ($currentPage - 1) * $rowsPerPage;
$fetchLimit = $displayTotal > 0 ? min($rowsPerPage, max(0, $maxBrowse - $offset)) : 0;

$fetch_voucher_archives_data_query = 'SELECT * FROM voucher_archives WHERE office_to = :office_to';
if ($voucher_type_filter !== 'all') {
    $fetch_voucher_archives_data_query .= ' AND voucher_type = :voucher_type';
}
$fetch_voucher_archives_data_query .= $searchSql . ' ORDER BY datetime_action DESC LIMIT :lim OFFSET :off';
$fetch_voucher_archives_data = $pdo->prepare($fetch_voucher_archives_data_query);
$fetch_voucher_archives_data->bindParam(':office_to', $_SESSION['logged_user_office']);
if ($voucher_type_filter !== 'all') {
    $fetch_voucher_archives_data->bindParam(':voucher_type', $voucher_type_filter);
}
foreach ($searchParams as $key => $pair) {
    $fetch_voucher_archives_data->bindValue($key, $pair[0], $pair[1]);
}
$fetch_voucher_archives_data->bindValue(':lim', $fetchLimit, PDO::PARAM_INT);
$fetch_voucher_archives_data->bindValue(':off', $offset, PDO::PARAM_INT);
$fetch_voucher_archives_data->execute();

$totalRows = $displayTotal;
$qsArchive = $rawQ !== '' ? ('&q=' . rawurlencode($rawQ)) : '';


$count = 0;
$c2 = 0;
?>
<!--=============== MAIN ===============!-->
<div class="main main--voucher-dashboard" id="main">
    <header class="voucher-dashboard-header">
        <h1 class="voucher-dashboard-title">Archives</h1>
    </header>
    <style>
        /* Keep archives filter toolbar in one row */
        #archivesFilterForm {
            display: flex;
            align-items: center;
            flex-wrap: nowrap !important;
            width: 100%;
            gap: 10px;
        }

        #archivesFilterForm .filter-chips {
            flex: 0 0 auto;
            flex-wrap: nowrap !important;
        }

        #archivesFilterForm .filter-search {
            flex: 1 1 auto;
            min-width: 0 !important;
        }

        /* Modernized voucher type dropdown */
        #archivesFilterForm .filter-type-select.filter-type-select--modern {
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

        #archivesFilterForm .filter-type-select.filter-type-select--modern::after {
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

        #archivesFilterForm .filter-type-select.filter-type-select--modern.filter-type-select--custom::after {
            transition: transform 120ms ease;
        }

        #archivesFilterForm .filter-type-select.filter-type-select--modern.filter-type-select--custom.is-open::after {
            transform: translateY(-35%) rotate(-135deg);
        }

        #archivesFilterForm .filter-type-select.filter-type-select--modern.filter-type-select--custom .filter-type-trigger {
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

        #archivesFilterForm .filter-type-select.filter-type-select--modern.filter-type-select--custom .filter-type-menu {
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

        #archivesFilterForm .filter-type-select.filter-type-select--modern.filter-type-select--custom.is-open .filter-type-menu {
            display: block;
        }

        #archivesFilterForm .filter-type-select.filter-type-select--modern.filter-type-select--custom .filter-type-option {
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

        #archivesFilterForm .filter-type-select.filter-type-select--modern.filter-type-select--custom .filter-type-option:hover {
            background: #f3f6fb;
        }

        #archivesFilterForm .filter-type-select.filter-type-select--modern.filter-type-select--custom .filter-type-option.is-active {
            background: #e8f0ff;
            color: #1d4ed8;
            font-weight: 600;
        }

        #archivesFilterForm .filter-type-select.filter-type-select--modern:hover {
            border-color: #c2ccda;
            box-shadow: 0 4px 12px rgba(15, 23, 42, 0.08);
        }

        #archivesFilterForm .filter-type-select.filter-type-select--modern:focus-within {
            border-color: #8fb2ff;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2);
        }

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

        .vstat-status-badge {
            display: inline-flex;
            align-items: center;
            max-width: 100%;
            padding: 4px 10px;
            border-radius: 999px;
            background: linear-gradient(180deg, #f8fbff 0%, #eef4ff 100%);
            border: 1px solid #dbeafe;
            color: #1e40af;
            font-size: 12px;
            font-weight: 600;
            line-height: 1.35;
            white-space: normal;
            word-break: break-word;
        }

        .vstat-status-badge--archived {
            background: linear-gradient(180deg, #fffbeb 0%, #fef3c7 100%);
            border-color: #fde68a;
            color: #92400e;
        }
    </style>
    <div class="voucher-card voucher-card--filter">
        <div class="filter-toolbar">
            <div class="filter-left">
                <form method="GET" action="" id="archivesFilterForm" class="filter-toolbar-form">
                    <div class="filter-chips" aria-label="Voucher filter tools">
                        <a class="filter-icon-btn" href="voucher_archives.php" aria-label="Home">
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
                <p>View Document</p>
                <i class="ri-close-fill close-icon" id="close_popup4"></i>
            </div>
            <form action="#" class="f-container" method="post" id="">
                <div class="box-body__container flex-row">
                    <div class="popupForm-body__container">
                        <div class="form-container">
                            <div class="label-input__container">
                                <label for="">Processing No.</label>
                                <input type="text" name="processing_no" class="processing_no form-custom-input" id="processing_no" value="" placeholder="Processing No." required>
                            </div>
                            <div class="label-input__container">
                                <label for="">ORS No.</label>
                                <input type="text" name="ors_no" class="ors_no form-custom-input" id="ors_no" value="" placeholder="ORS No.">
                            </div>
                            <div class="label-input__container">
                                <label for="">ADA/Check No.</label>
                                <input type="text" name="ada_check_no" class="ada_check_no form-custom-input" id="ada_check_no" value="" placeholder="ADA/Check No.">
                            </div>
                            <div class="label-input__container">
                                <label for="">ADA/Check Date</label>
                                <input type="text" name="ada_check_date" class="ada_check_date form-custom-input" id="ada_check_date" value="" placeholder="ADA/Check No.">
                            </div>
                            <div class="label-input__container">
                                <label for="">DV No.</label>
                                <input type="text" name="dv_no" class="dv_no form-custom-input" id="dv_no" value="" placeholder="DV No." required>
                            </div>
                            <div class="label-input__container">
                                <label for="">Voucher Type</label>
                                <input type="text" name="voucher_type" class="voucher_type form-custom-input" id="voucher_type" value="" placeholder="Voucher Type">
                            </div>
                            <div class="label-input__container">
                                <label for="">Payee</label>
                                <input type="text" name="payee" class="payee form-custom-input" id="payee" value="" placeholder="Payee" required>
                            </div>
                        </div>
                    </div>
                    <div class="popupForm-body__container">
                        <div class="form-container">
                            <div class="label-input__container">
                                <label for="">Address</label>
                                <input type="text" name="address" class="address form-custom-input" id="address" value="" placeholder="Address">
                            </div>
                            <div class="label-input__container">
                                <label for="">TIN/Employee No.</label>
                                <input type="text" name="tin_employee_no" class="tin_employee_no form-custom-input" id="tin_employee_no" value="" placeholder="TIN/Employee No.">
                            </div>
                            <div class="label-input__container number-input amount_primary_block">
                                <label for="" class="amount_main_label">Amount</label>
                                <input type="text" name="string_amount" class="string_amount form-custom-input amount_main_display" id="string_amount" placeholder="Amount" readonly>
                                <input type="hidden" name="amount" class="amount" id="int_amount" value="">
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
                                <input type="date" name="voucher_date" class="voucher_date form-custom-input" id="voucher_date" value="" required disabled>
                            </div>
                            <div class="label-input__container">
                                <label for="">Certified Correct:</label>
                                <input type="text" name="certified_correct" class="certified_correct form-custom-input" id="certified_correct" placeholder="Certified Correct">
                            </div>
                            <div class="label-input__container">
                                <label for="">Approved By:</label>
                                <input type="text" name="approved_by" class="approved_by form-custom-input" id="approved_by" placeholder="Approved By">
                            </div>
                            <div class="label-input__container">
                                <label for="">Agency Authorized Signatory:</label>
                                <input type="text" name="agency_authorized_signatory" class="agency_authorized_signatory form-custom-input" id="agency_authorized_signatory" placeholder="Agency Authorized Signatory">
                            </div>

                            <div class="label-input__container hidden_input">
                                <label for="">Priority</label>
                                <input type="text" name="priority" class="priority" id="priority" value="">
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
                                <label for="">Encoded By</label>
                                <input type="text" name="encoded_by" class="encoded_by" id="encoded_by" value="">
                            </div>
                            <div class="label-input__container hidden_input">
                                <label for="">Date/Time Encoded</label>
                                <input type="text" name="datetime_encoded" class="datetime_encoded" id="datetime_encoded" value="">
                            </div>
                        </div>
                    </div>
                    <div class="popupForm-body__container">
                        <div class="form-container">
                            <div class="label-input__container">
                                <label for="">Particulars</label>
                                <textarea name="particulars" id="particulars" style="height: 400px;" cols="30" rows="10" class="multi-line-input particulars form-custom-multi-input" placeholder="Particulars ...." required></textarea>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="popupForm-footer__container">
                    <div class="footer-button__container">
                        <button class="btn secondary transparent" id="close_popup3" type="button">CANCEL</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <div class="overlay voucher-premium-overlay" id="overlay"></div>
    <div class="voucher-card voucher-card--table">
        <h2 class="voucher-card-title">Archives Summary</h2>
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

            #my-Table td.voucher-amount-stack-cell {
                min-width: 148px;
                vertical-align: middle;
            }
        </style>
        <div class="content-wrapper">
            <table class="table content_table content_table--dashboard" id="my-Table">
                <thead>
                    <tr>
                        <th class="voucher-row-menu-cell" aria-label="Menu"></th>
                        <th>Processing No.</th>
                        <th>ORS No.</th>
                        <th>DV No.</th>
                        <th>Payee Name</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody>
                    <?php while ($row = $fetch_voucher_archives_data->fetch(PDO::FETCH_ASSOC)) :
                        $adaCheckNo = '';
                        if (isset($row['ada_check_no'])) {
                            $adaCheckNo = (string) $row['ada_check_no'];
                        } elseif (isset($row['check_no']) && (string) $row['check_no'] !== '') {
                            $adaCheckNo = (string) $row['check_no'];
                        } elseif (isset($row['ada_no'])) {
                            $adaCheckNo = (string) $row['ada_no'];
                        }

                        $remarksRaw = trim((string) ($row['remarks'] ?? ''));
                        $remarksLatest = '';
                        if ($remarksRaw !== '') {
                            $pattern = '/(?:^|,\s*)([^,]+?):\s*(.*?)(?=(?:,\s*[^,]+?:\s)|$)/s';
                            if (preg_match_all($pattern, $remarksRaw, $m) && !empty($m[0])) {
                                $idx = count($m[0]) - 1;
                                $remarksLatest = trim((string) $m[1][$idx] . ': ' . (string) $m[2][$idx]);
                            } else {
                                $parts = array_map('trim', explode(',', $remarksRaw));
                                $remarksLatest = (string) end($parts);
                            }
                        }

                        $archiveAction = trim((string) ($row['action'] ?? ''));
                        $isPaidArchive = stripos($archiveAction, 'processed') !== false
                            || stripos($archiveAction, 'paid') !== false
                            || stripos($archiveAction, 'archived') !== false;
                    ?>
                        <tr>
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
                                        <button class="btn tertiary voucher-row-menu-item" name="btn-history" type="button" role="menuitem">
                                            <i class="ri-history-line" aria-hidden="true"></i>
                                            <span>History</span>
                                        </button>
                                        <a class="voucher-row-menu-link" href="voucher_status_report.php?q=<?php echo htmlspecialchars((string) ($row['processing_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?>" role="menuitem">
                                            <i class="ri-file-list-3-line" aria-hidden="true"></i>
                                            <span>Report</span>
                                        </a>
                                    </div>
                                </div>
                            </td>
                            <td data-label="processing_no"><?php echo htmlspecialchars((string) ($row['processing_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="ors_no"><?php echo htmlspecialchars((string) ($row['ors_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="dv_no"><?php echo htmlspecialchars((string) ($row['dv_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="payee"><?php echo htmlspecialchars((string) ($row['payee'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <?php echo voucher_amount_stack_cell_html($row['amount'] ?? '', $row['charged_amount'] ?? '', 'amount-cell'); ?>
                            <td data-label="archive_status_display">
                                <?php if ($archiveAction !== '') : ?>
                                    <span class="vstat-status-badge<?= $isPaidArchive ? ' vstat-status-badge--archived' : '' ?>"><?php echo htmlspecialchars($archiveAction, ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td data-label="remarks_display" class="return-remarks-cell"><?php
                                echo $remarksLatest !== ''
                                    ? '<span class="remarks-badge">' . htmlspecialchars($remarksLatest, ENT_QUOTES, 'UTF-8') . '</span>'
                                    : '';
                            ?></td>
                            <td data-label="ada_check_no" class="hidden"><?php echo htmlspecialchars($adaCheckNo, ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="voucher_type" class="hidden"><?php echo htmlspecialchars((string) ($row['voucher_type'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="ada_check_date" class="hidden"><?php echo htmlspecialchars((string) ($row['ada_check_date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="address" class="hidden"><?php echo htmlspecialchars((string) ($row['address'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="particulars" class="hidden"><?php echo htmlspecialchars((string) ($row['particulars'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="amount_original" class="hidden"><?php echo htmlspecialchars((string) ($row['amount'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="charged_amount" class="hidden"><?php echo htmlspecialchars((string) ($row['charged_amount'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="voucher_date" class="hidden"><?php echo htmlspecialchars((string) ($row['voucher_date'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="datetime_action" class="hidden"><?php echo htmlspecialchars((string) ($row['datetime_action'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="priority" class="prioritized hidden"><?php echo htmlspecialchars((string) ($row['priority'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="office_from" class="hidden"><?php echo htmlspecialchars((string) ($row['office_from'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="office_to" class="hidden"><?php echo htmlspecialchars((string) ($row['office_to'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="sender_udc" class="hidden"><?php echo htmlspecialchars((string) ($row['sender_udc'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="receiver_udc" class="hidden"><?php echo htmlspecialchars((string) ($row['receiver_udc'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="encoded_by" class="hidden"><?php echo htmlspecialchars((string) ($row['encoded_by'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="datetime_encoded" class="hidden"><?php echo htmlspecialchars((string) ($row['datetime_encoded'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="certified_correct" class="hidden"><?php echo htmlspecialchars((string) ($row['certified_correct'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="approved_by" class="hidden"><?php echo htmlspecialchars((string) ($row['approved_by'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="agency_authorized_signatory" class="hidden"><?php echo htmlspecialchars((string) ($row['agency_authorized_signatory'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="tin_employee_no" class="hidden"><?php echo htmlspecialchars((string) ($row['tin_employee_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="remarks" class="hidden"><?php echo htmlspecialchars($remarksRaw, ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="process_history" class="hidden"><?php echo htmlspecialchars((string) ($row['process_history'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                        </tr>
                    <?php endwhile; ?>
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
                            <a class="pagination_btn_modern" href="<?php echo htmlspecialchars(build_archives_page_url($currentPage - 1, $voucher_type_filter, $rawQ, $rowsPerPage), ENT_QUOTES, 'UTF-8'); ?>">Previous</a>
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
                                        echo '<a class="pagination_page_num' . $active . '" href="' . htmlspecialchars(build_archives_page_url($i, $voucher_type_filter, $rawQ, $rowsPerPage), ENT_QUOTES, 'UTF-8') . '">' . $i . '</a>';
                                    }
                                } else {
                                    echo '<a class="pagination_page_num' . (1 == $currentPage ? ' active' : '') . '" href="' . htmlspecialchars(build_archives_page_url(1, $voucher_type_filter, $rawQ, $rowsPerPage), ENT_QUOTES, 'UTF-8') . '">1</a>';
                                    if ($startPage > 2) {
                                        echo '<span class="pagination_ellipsis">...</span>';
                                    }
                                    for ($i = max(2, $startPage); $i <= min($totalPages - 1, $endPage); $i++) {
                                        $active = ($i == $currentPage) ? ' active' : '';
                                        echo '<a class="pagination_page_num' . $active . '" href="' . htmlspecialchars(build_archives_page_url($i, $voucher_type_filter, $rawQ, $rowsPerPage), ENT_QUOTES, 'UTF-8') . '">' . $i . '</a>';
                                    }
                                    if ($endPage < $totalPages - 1) {
                                        echo '<span class="pagination_ellipsis">...</span>';
                                    }
                                    echo '<a class="pagination_page_num' . ($totalPages == $currentPage ? ' active' : '') . '" href="' . htmlspecialchars(build_archives_page_url($totalPages, $voucher_type_filter, $rawQ, $rowsPerPage), ENT_QUOTES, 'UTF-8') . '">' . $totalPages . '</a>';
                                }
                                ?>
                            </div>

                            <?php if ($currentPage < $totalPages): ?>
                                <a class="pagination_btn_modern" href="<?php echo htmlspecialchars(build_archives_page_url($currentPage + 1, $voucher_type_filter, $rawQ, $rowsPerPage), ENT_QUOTES, 'UTF-8'); ?>">Next</a>
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

            if (e.target.closest('[name="btn-view"]') || e.target.closest('[name="btn-history"]') || e.target.closest('.voucher-row-menu-link')) {
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

        function fitArchivesViewport() {
            var wrapperTop = tableWrapper.getBoundingClientRect().top;
            var pagination = tableCard.querySelector('.voucher-pagination-footer');
            var paginationHeight = pagination ? pagination.offsetHeight : 0;
            var bottomGap = 20;
            var available = window.innerHeight - wrapperTop - paginationHeight - bottomGap;
            tableWrapper.style.maxHeight = Math.max(160, available) + 'px';
        }

        function scheduleArchivesLayoutSync() {
            if (layoutTimer) {
                clearTimeout(layoutTimer);
            }
            layoutTimer = setTimeout(fitArchivesViewport, 80);
        }

        window.addEventListener('resize', scheduleArchivesLayoutSync);
        window.addEventListener('load', scheduleArchivesLayoutSync);

        if (window.ResizeObserver) {
            var layoutObserver = new ResizeObserver(scheduleArchivesLayoutSync);
            layoutObserver.observe(main);
            layoutObserver.observe(tableCard);
        }

        scheduleArchivesLayoutSync();
    })();
</script>

<script>
    (function() {
        var typeFilter = document.getElementById('filterInputType');
        var form = document.getElementById('archivesFilterForm');
        var dropdown = document.getElementById('filterTypeDropdown');
        var trigger = document.getElementById('filterTypeTrigger');
        var menu = document.getElementById('filterTypeMenu');
        if (typeFilter && form && dropdown && trigger && menu) {
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
        }

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
            showNotify('No matching archived vouchers for your search.', 'warning', 2200);
        }
    });
</script>
<?php endif; ?>
<script>
    $(document).ready(function() {
        $(".purpose").each(function() {
            if ($(this).text() == "Encode") {
                $(this).parent().css("background-color", "lightyellow");
                $(this).parent().children('td').css("color", "orangered");
            }
        })
        $(".purpose").each(function() {
            if ($(this).text() == "Reply") {
                $(this).parent().css("background-color", "darkgray");
                $(this).parent().children('td').css("color", "#000000");
            }
        })
    });
</script>
<!--=============== MAIN.JS ===============!-->
<script src="../../protected/js/main.js"></script>
<script src="../../protected/js/amount_helper.js"></script>
<script src="../../protected/js/popscript.js"></script>
<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
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
        var parts, user, action, section, html = '<ul class="hist-process-list">';
        for (var i = 0; i < lines.length; i++) {
            parts = lines[i].split(/\s*:\s*/);
            user = (parts[0] || '').trim();
            if (parts.length >= 3) {
                action = (parts[1] || '').trim();
                section = (parts.slice(2).join(' : ')).trim();
            } else {
                action = '';
                section = (parts[1] || '').trim();
            }
            html += '<li class="hist-process-item">';
            html += '<span class="hist-process-item__part hist-process-item__part--user"><i class="ri-user-line"></i><span>' + escapeHtml(user) + '</span></span>';
            if (action) {
                html += '<span class="hist-process-sep">·</span>';
                html += '<span class="hist-process-item__part hist-process-item__part--action"><i class="ri-check-double-line"></i><span>' + escapeHtml(action) + '</span></span>';
            }
            html += '<span class="hist-process-sep">·</span>';
            html += '<span class="hist-process-item__part hist-process-item__part--section"><i class="ri-building-4-line"></i><span>' + escapeHtml(section) + '</span></span>';
            html += '</li>';
        }
        html += '</ul>';
        return html;
    }

    function cellText(row, label) {
        var cell = row.querySelector('[data-label="' + label + '"]');
        return cell ? String(cell.textContent || '').trim() : '';
    }

    function openArchivesHistoryModal(row) {
        const modal = document.getElementById('historyModal');
        const overlay = document.getElementById('historyOverlay');
        const procNo = cellText(row, 'processing_no');
        const combinedRemarks = row.querySelector('[data-label="remarks"]')?.textContent || '';
        const processHistory = row.querySelector('[data-label="process_history"]')?.textContent || '';
        const procEl = document.getElementById('hist_processing_no');
        const senderEl = document.getElementById('hist_sender_remarks');
        const combinedEl = document.getElementById('hist_combined_remarks');
        const histEl = document.getElementById('hist_process_history');

        if (procEl) procEl.value = procNo;
        if (senderEl) senderEl.textContent = '';
        if (combinedEl) combinedEl.textContent = combinedRemarks && combinedRemarks.trim() !== '' ? combinedRemarks.trim() : '';
        if (histEl) {
            histEl.classList.add('hist-content--process-list');
            histEl.innerHTML = renderProcessHistory(processHistory);
        }

        if (modal) modal.style.display = 'block';
        if (overlay) overlay.style.display = 'block';
    }

    function populateArchivesViewModal(row) {
        var processing_no = cellText(row, 'processing_no');
        var ors_no = cellText(row, 'ors_no');
        var dv_no = cellText(row, 'dv_no');
        var ada_check_no = cellText(row, 'ada_check_no');
        var voucher_type = cellText(row, 'voucher_type');
        var ada_check_date = cellText(row, 'ada_check_date');
        var payee = cellText(row, 'payee');
        var address = cellText(row, 'address');
        var particulars = cellText(row, 'particulars');
        var tin_employee_no = cellText(row, 'tin_employee_no');
        var amountTd = row.querySelector('[data-label="amount"]');
        var grossFromStack = amountTd ? (amountTd.getAttribute('data-amount-gross') || '') : '';
        var netFromStack = amountTd ? (amountTd.getAttribute('data-amount-net') || '') : '';
        var amountOriginal = typeof normalizeAmountInput === 'function'
            ? normalizeAmountInput(cellText(row, 'amount_original') || grossFromStack || (amountTd ? (amountTd.getAttribute('data-amount') || '') : ''))
            : (cellText(row, 'amount_original') || grossFromStack);
        var charged_amount = typeof normalizeAmountInput === 'function'
            ? normalizeAmountInput(cellText(row, 'charged_amount') || netFromStack)
            : (cellText(row, 'charged_amount') || netFromStack);
        var voucher_date = cellText(row, 'voucher_date');
        var passed_priority = cellText(row, 'priority');
        var office_to = cellText(row, 'office_to');
        var office_from = cellText(row, 'office_from');
        var encoded_by = cellText(row, 'encoded_by');
        var datetime_encoded = cellText(row, 'datetime_encoded');
        var certified_correct = cellText(row, 'certified_correct');
        var approved_by = cellText(row, 'approved_by');
        var agency_authorized_signatory = cellText(row, 'agency_authorized_signatory');

        document.querySelector('.processing_no').value = processing_no;
        document.querySelector('.ors_no').value = ors_no;
        document.querySelector('.dv_no').value = dv_no;
        document.querySelector('.ada_check_no').value = ada_check_no;
        document.querySelector('.voucher_type').value = voucher_type;
        document.querySelector('.ada_check_date').value = ada_check_date;
        document.querySelector('.payee').value = payee;
        document.querySelector('.address').value = address;
        document.querySelector('.particulars').value = particulars;
        document.querySelector('.tin_employee_no').value = tin_employee_no;

        const originalStringInput = document.getElementById('original_string_amount');
        const chargedStringInput = document.getElementById('charged_string_amount');
        if (originalStringInput) originalStringInput.disabled = true;
        if (chargedStringInput) chargedStringInput.disabled = true;

        if (typeof populateAmountSplitView === 'function') {
            populateAmountSplitView(amountOriginal, charged_amount);
        }

        const grossHiddenInput = document.getElementById('gross_amount');
        if (grossHiddenInput && typeof normalizeAmountInput === 'function') {
            grossHiddenInput.value = normalizeAmountInput(amountOriginal);
        }

        document.querySelector('.voucher_date').value = voucher_date;
        document.querySelector('.priority').value = passed_priority;
        document.querySelector('.office_from').value = office_from;
        document.querySelector('.office_to').value = office_to;
        document.querySelector('.encoded_by').value = encoded_by;
        document.querySelector('.datetime_encoded').value = datetime_encoded;
        document.querySelector('.certified_correct').value = certified_correct;
        document.querySelector('.approved_by').value = approved_by;
        document.querySelector('.agency_authorized_signatory').value = agency_authorized_signatory;

        document.querySelectorAll('.hidden_input').forEach(function(input) {
            if (
                input.classList.contains('original_charged_container') ||
                input.classList.contains('charged_amount_container')
            ) {
                return;
            }
            input.style.display = 'none';
        });
    }

    document.querySelectorAll('.btn').forEach(function(button) {
        button.addEventListener('click', function() {
            var row = this.closest('tr');
            if (!row) {
                var portaledDropdown = this.closest('.voucher-row-menu-dropdown');
                if (portaledDropdown && portaledDropdown._ownerRow) {
                    row = portaledDropdown._ownerRow;
                }
            }
            if (!row) return;

            var name = this.getAttribute('name') || '';
            if (name === 'btn-history') {
                openArchivesHistoryModal(row);
                return;
            }
            if (name !== 'btn-view') return;

            populateArchivesViewModal(row);
            if (typeof openPopup === 'function') {
                openPopup();
            } else {
                document.getElementById('popupForm').style.display = 'block';
                document.getElementById('overlay').style.display = 'block';
            }
        });
    });
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
</body>

</html>