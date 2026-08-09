<?php
include '../includes/header.php';
require_once __DIR__ . '/../../protected/core/components/helpers/audit_helper.inc.php';
require_once __DIR__ . '/../../protected/core/components/notifications/notification.inc.php';
require_once __DIR__ . '/../../protected/core/components/security/filter_input.inc.php';
require_once __DIR__ . '/../../protected/core/components/helpers/cursor_pagination_helper.php';
require_once __DIR__ . '/../../protected/core/components/helpers/amount_helper.inc.php';
require_once __DIR__ . '/../../protected/handler/voucher_module/voucher.model.inc.php';
require_once __DIR__ . '/../../protected/core/components/helpers/voucher_tracking_helper.inc.php';
require_once __DIR__ . '/../../protected/core/components/helpers/voucher_status_report_helper.inc.php';
require_once __DIR__ . '/checklist_config.php';
AuditHelper::logPageView('Voucher Status');

vouchers_amount_ensure_string_column($pdo);

$rawSearch = (string) ($_GET['searchTerm'] ?? '');
$rawOfficeFilter = trim((string) ($_GET['office'] ?? ''));
$loggedOffice = trim((string) ($_SESSION['logged_user_office'] ?? ''));
$officeQueryContext = voucher_office_query_context($pdo, $loggedOffice, $rawOfficeFilter);
$officeWhere = voucher_office_build_where_clause('vt.office_from', (array) ($officeQueryContext['query_offices'] ?? []), 'vstat_office');
$officeSql = $officeWhere['sql'];
$officeParams = $officeWhere['params'];
$q = filterInput($rawSearch);
$invalidSearch = (trim($rawSearch) !== '' && $q === '');
$rowsPerPage = clamp_int($_GET['rowsPerPage'] ?? null, 1, 50, 50);
$maxBrowse = 100;

$searchParams = [];
$searchSql = '';
if (!$invalidSearch && $q !== '') {
    $pat = '%' . $q . '%';
    $cols = ['processing_no', 'ors_no', 'dv_no', 'ada_check_no', 'payee', 'address', 'particulars', 'voucher_type', 'voucher_status', 'status', 'remarks', 'datetime_encoded', 'datetime_status', 'total_processing_time'];
    $parts = [];
    foreach ($cols as $i => $col) {
        $ph = ':sq' . $i;
        $parts[] = '`' . $col . '` LIKE ' . $ph;
        $searchParams[$ph] = [$pat, PDO::PARAM_STR];
    }
    $searchSql = ' AND (' . implode(' OR ', $parts) . ')';
}

// Exclude encoded/pending at encoder only (active_status = no).
$activeOnlySql = voucher_tracking_counts_include_sql('vt');

if ($invalidSearch) {
    $dbCount = 0;
} else {
    $document_status_queryCount = 'SELECT COUNT(*) AS total FROM voucher_tracking vt WHERE 1=1' . $officeSql . $activeOnlySql . str_replace(
        ['`processing_no`', '`ors_no`', '`dv_no`', '`ada_check_no`', '`payee`', '`address`', '`particulars`', '`voucher_type`', '`voucher_status`', '`status`', '`remarks`', '`datetime_encoded`', '`datetime_status`', '`total_processing_time`'],
        ['`vt`.`processing_no`', '`vt`.`ors_no`', '`vt`.`dv_no`', '`vt`.`ada_check_no`', '`vt`.`payee`', '`vt`.`address`', '`vt`.`particulars`', '`vt`.`voucher_type`', '`vt`.`voucher_status`', '`vt`.`status`', '`vt`.`remarks`', '`vt`.`datetime_encoded`', '`vt`.`datetime_status`', '`vt`.`total_processing_time`'],
        $searchSql
    );
    $document_status_statementCount = $pdo->prepare($document_status_queryCount);
    foreach ($officeParams as $key => $value) {
        $document_status_statementCount->bindValue($key, $value, PDO::PARAM_STR);
    }
    foreach ($searchParams as $key => $pair) {
        $document_status_statementCount->bindValue($key, $pair[0], $pair[1]);
    }
    $document_status_statementCount->execute();
    $dbCount = (int) $document_status_statementCount->fetch(PDO::FETCH_ASSOC)['total'];
}

$displayTotal = min($dbCount, $maxBrowse);
$totalPages = $displayTotal > 0 ? (int) ceil($displayTotal / $rowsPerPage) : 1;
$currentPage = isset($_GET['page']) ? (int) $_GET['page'] : 1;
$currentPage = max(1, $currentPage);
$currentPage = min($currentPage, $totalPages);
$offset = ($currentPage - 1) * $rowsPerPage;
$fetchLimit = $displayTotal > 0 ? min($rowsPerPage, max(0, $maxBrowse - $offset)) : 0;

$fetch_voucher_status_log_query = 'SELECT vt.*,
    COALESCE(NULLIF(TRIM(CAST(vt.charged_amount AS CHAR)), \'\'), NULLIF(TRIM(CAST(v.amount AS CHAR)), \'\'), CAST(vt.amount AS CHAR)) AS amount_resolved,
    COALESCE(NULLIF(TRIM(CAST(v.amount AS CHAR)), \'\'), CAST(vt.amount AS CHAR)) AS amount_original_resolved,
    v.tin_employee_no AS v_tin_employee_no,
    v.voucher_date AS v_voucher_date
    FROM voucher_tracking vt
    LEFT JOIN vouchers v ON v.processing_no = vt.processing_no
    WHERE 1=1' . $officeSql . $activeOnlySql . str_replace(
    ['`processing_no`', '`ors_no`', '`dv_no`', '`ada_check_no`', '`payee`', '`address`', '`particulars`', '`voucher_type`', '`voucher_status`', '`status`', '`remarks`', '`datetime_encoded`', '`datetime_status`', '`total_processing_time`'],
    ['`vt`.`processing_no`', '`vt`.`ors_no`', '`vt`.`dv_no`', '`vt`.`ada_check_no`', '`vt`.`payee`', '`vt`.`address`', '`vt`.`particulars`', '`vt`.`voucher_type`', '`vt`.`voucher_status`', '`vt`.`status`', '`vt`.`remarks`', '`vt`.`datetime_encoded`', '`vt`.`datetime_status`', '`vt`.`total_processing_time`'],
    $searchSql
) . ' ORDER BY vt.processing_no DESC LIMIT :lim OFFSET :off';
$fetch_voucher_status_log = $pdo->prepare($fetch_voucher_status_log_query);
foreach ($officeParams as $key => $value) {
    $fetch_voucher_status_log->bindValue($key, $value, PDO::PARAM_STR);
}
foreach ($searchParams as $key => $pair) {
    $fetch_voucher_status_log->bindValue($key, $pair[0], $pair[1]);
}
$fetch_voucher_status_log->bindValue(':lim', $fetchLimit, PDO::PARAM_INT);
$fetch_voucher_status_log->bindValue(':off', $offset, PDO::PARAM_INT);
$fetch_voucher_status_log->execute();

$totalRows = $displayTotal;
$qsSearch = $rawSearch !== '' ? ('&searchTerm=' . rawurlencode($rawSearch)) : '';
$qsOffice = ($officeQueryContext['is_main_processing_view'] ?? false) && ($officeQueryContext['selected_office'] ?? 'all') !== 'all'
    ? ('&office=' . rawurlencode((string) $officeQueryContext['selected_office']))
    : '';
?>
<script src="../../protected/js/set_print_time.js"></script>
<div id="searchResults"></div> <!-- This is where the search results will be displayed -->
<!--=============== MAIN ===============!-->
<div class="main main--voucher-dashboard" id="main">
    <header class="voucher-dashboard-header">
        <h1 class="voucher-dashboard-title">Voucher Status</h1>
        <p style="color: rgb(75 85 99 / 0.9); margin: 0.25rem 0 0;">Forwarded, received, and returned vouchers (excludes encoded/pending at encoder only)</p>
    </header>
    <div class="voucher-card voucher-card--filter">
        <div class="filter-toolbar">
            <div class="filter-left">
                <form method="GET" action="" id="voucherStatusFilterForm" class="filter-toolbar-form voucher-overview-filter-form" onsubmit="return false;">
                    <div class="filter-chips" aria-label="Filter tools">
                        <a class="filter-icon-btn" href="voucher_status.php" aria-label="Home"></a>
                        <button type="button" class="filter-icon-btn" aria-label="Copy"></button>
                    </div>
                    <?php if (!empty($officeQueryContext['is_main_processing_view'])) : ?>
                        <div class="filter-office-select">
                            <label class="hidden" for="officeFilter">Office</label>
                            <select id="officeFilter" name="office" aria-label="Filter by office">
                                <option value="all"<?= ($officeQueryContext['selected_office'] ?? 'all') === 'all' ? ' selected' : '' ?>>All Offices</option>
                                <?php foreach ((array) ($officeQueryContext['selectable_offices'] ?? []) as $officeName) : ?>
                                    <option value="<?= htmlspecialchars((string) $officeName, ENT_QUOTES, 'UTF-8') ?>"<?= ($officeQueryContext['selected_office'] ?? 'all') !== 'all' && voucher_status_report_office_in_list((string) ($officeQueryContext['selected_office'] ?? ''), [(string) $officeName]) ? ' selected' : '' ?>>
                                        <?= htmlspecialchars((string) $officeName, ENT_QUOTES, 'UTF-8') ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    <?php endif; ?>
                    <div class="filter-search">
                        <input type="text" id="filterInput" name="searchTerm" value="<?php echo htmlspecialchars($rawSearch, ENT_QUOTES, 'UTF-8'); ?>" placeholder="search" autocomplete="off">
                    </div>
                </form>
            </div>
        </div>
    </div>

    <style>
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

    <div class="popup-form voucher-premium-modal popup-form--compact" id="popupForm">
        <div class="popupForm-box__container">
            <div class="popupForm-header__container">
                <p id="form_title">View Voucher</p>
                <i class="ri-close-fill close-icon" id="close_popup4"></i>
            </div>
            <form action="#" class="f-container" method="post" id="voucherStatusViewForm">
                <div class="box-body__container flex-row">
                    <div class="popupForm-body__container">
                        <div class="form-container">
                            <div class="label-input__container">
                                <label for="processing_no">Processing No.</label>
                                <input type="text" name="processing_no" class="processing_no form-custom-input" id="processing_no" value="" readonly>
                            </div>
                            <div class="label-input__container" id="selected-coa-options-container" style="display: none;">
                                <label for="view_coa_requirements_btn">Selected COA Requirements</label>
                                <button type="button" id="view_coa_requirements_btn" class="btn primary" style="width: 100%; padding: 10px; font-weight: bold;">View Selected Requirements</button>
                                <p style="font-size: 0.85em; color: #666; margin-top: 5px;">as per coa-circular-no.-2023-004-June-14-2023</p>
                            </div>
                            <div class="label-input__container">
                                <label for="ors_no">ORS No.</label>
                                <input type="text" name="ors_no" class="ors_no form-custom-input" id="ors_no" value="" readonly>
                            </div>
                            <div class="label-input__container">
                                <label for="ada_check_no">ADA/Check No.</label>
                                <input type="text" name="ada_check_no" class="ada_check_no form-custom-input" id="ada_check_no" value="" readonly>
                            </div>
                            <div class="label-input__container">
                                <label for="dv_no">DV No.</label>
                                <input type="text" name="dv_no" class="dv_no form-custom-input" id="dv_no" value="" readonly>
                            </div>
                            <div class="label-input__container">
                                <label for="payee">Payee</label>
                                <input type="text" name="payee" class="payee form-custom-input" id="payee" value="" readonly>
                            </div>
                            <div class="label-input__container">
                                <label for="address">Address</label>
                                <input type="text" name="address" class="address form-custom-input" id="address" value="" readonly>
                            </div>
                        </div>
                    </div>
                    <div class="popupForm-body__container">
                        <div class="form-container">
                            <div class="label-input__container">
                                <label for="particulars">Particulars</label>
                                <textarea name="particulars" id="particulars" cols="30" rows="10" class="multi-line-input particulars form-custom-multi-input" readonly></textarea>
                            </div>
                            <div class="label-input__container">
                                <label for="tin_employee_no">TIN/Employee No.</label>
                                <input type="text" name="tin_employee_no" class="tin_employee_no form-custom-input" id="tin_employee_no" value="" readonly>
                            </div>
                            <div class="label-input__container number-input amount_primary_block">
                                <label for="int_amount">Amount</label>
                                <input type="text" name="string_amount" class="string_amount form-custom-input" id="int_amount" readonly>
                                <input type="hidden" name="amount" class="amount" value="">
                            </div>
                            <div class="label-input__container number-input charged_amount_container" style="display: none;">
                                <label for="charged_string_amount">Charged Amount (Edited)</label>
                                <input type="text" name="charged_string_amount" class="charged_string_amount form-custom-input" id="charged_string_amount" style="color: red;" readonly>
                            </div>
                            <div class="label-input__container">
                                <label for="voucher_date">Voucher Date</label>
                                <input type="date" name="voucher_date" class="voucher_date form-custom-input" id="voucher_date" value="" readonly>
                            </div>
                            <div class="label-input__container">
                                <label for="sender_remarks">Sender Remarks</label>
                                <input type="text" name="sender_remarks" class="sender_remarks form-custom-input" id="sender_remarks" value="" readonly>
                            </div>
                            <div class="label-input__container">
                                <label for="voucher_status_display">Status</label>
                                <input type="text" name="voucher_status_display" class="form-custom-input" id="voucher_status_display" value="" readonly>
                            </div>
                            <div class="label-input__container">
                                <label for="datetime_status_display">Date/Time Status</label>
                                <input type="text" name="datetime_status_display" class="form-custom-input" id="datetime_status_display" value="" readonly>
                            </div>
                            <div class="label-input__container hidden_input">
                                <label for="combined_remarks">Combined Remarks</label>
                                <input type="text" name="combined_remarks" class="combined_remarks form-custom-input" id="combined_remarks" value="" readonly>
                            </div>
                            <div class="label-input__container hidden_input">
                                <label for="office_from">Office From</label>
                                <input type="text" name="office_from" class="office_from" id="office_from" value="">
                            </div>
                            <div class="label-input__container hidden_input">
                                <label for="office_to">Office To</label>
                                <input type="text" name="office_to" class="office_to" id="office_to" value="">
                            </div>
                            <div class="label-input__container hidden_input">
                                <label for="encoded_by">Encoded By</label>
                                <input type="text" name="encoded_by" class="encoded_by" id="encoded_by" value="">
                            </div>
                            <div class="label-input__container hidden_input">
                                <label for="encoded_from">Encoded From</label>
                                <input type="text" name="encoded_from" class="encoded_from" id="encoded_from" value="">
                            </div>
                            <div class="label-input__container hidden_input">
                                <label for="datetime_encoded">Datetime Encoded</label>
                                <input type="text" name="datetime_encoded" class="datetime_encoded" id="datetime_encoded" value="">
                            </div>
                            <div class="label-input__container hidden_input">
                                <label for="process_status">Process Status</label>
                                <input type="text" name="process_status" class="process_status" id="process_status" value="">
                            </div>
                            <div class="label-input__container hidden_input">
                                <label for="voucher_type">Voucher Type</label>
                                <input type="text" name="voucher_type" class="voucher_type" id="voucher_type" value="">
                            </div>
                            <div class="label-input__container hidden_input">
                                <label for="selected_coa_options">Selected COA Options</label>
                                <input type="text" name="selected_coa_options" class="selected_coa_options" id="selected_coa_options" value="">
                            </div>
                        </div>
                    </div>
                </div>
                <div class="popupForm-footer__container">
                    <div class="footer-button__container">
                        <button class="btn secondary transparent" id="close_popup3" type="button">Close</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
    <div class="overlay voucher-premium-overlay" id="overlay"></div>

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
                                <label for="coa_options_checklist">Selected COA Requirements</label>
                                <div id="coa_options_list" style="background-color: white; border: 1px solid #ccc; border-radius: 8px; padding: 10px; max-height: 400px; overflow-y: auto;"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="popupForm-footer__container">
                    <div class="footer-button__container">
                        <button class="btn secondary transparent" id="coa_modal_cancel" type="button">Close</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="overlay voucher-premium-overlay" id="coa_modal_overlay" style="display: none;"></div>

    <div class="voucher-card voucher-card--table">
        <h2 class="voucher-card-title">Status Summary</h2>
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

            #coa_options_list label.coa-requirement-view-only {
                display: flex;
                align-items: center;
                padding: 10px 12px;
                border-bottom: 1px solid #eee;
                pointer-events: none;
                user-select: none;
            }

            #coa_options_list label.coa-requirement-view-only input[type="checkbox"] {
                margin-right: 10px;
                accent-color: #2563eb;
                opacity: 1;
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
                        <th>Payee Name</th>
                        <th>Amount</th>
                        <th>Status</th>
                        <th>Remarks</th>
                    </tr>
                </thead>
                <tbody id="target_body">
                    <?php while ($row = $fetch_voucher_status_log->fetch(PDO::FETCH_ASSOC)) :
                        $processHistoryRaw = trim((string) ($row['process_history'] ?? ''));
                        $processHistory = voucher_incoming_load_process_history(
                            $pdo,
                            (string) ($row['processing_no'] ?? ''),
                            $processHistoryRaw
                        );
                        $processHistory = voucher_tracking_enrich_process_history_for_return(
                            $pdo,
                            $processHistory,
                            (string) ($row['voucher_type'] ?? '')
                        );
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
                        $senderRemarksRaw = trim((string) ($row['sender_remarks'] ?? ''));
                        if ($remarksLatest === '' && $senderRemarksRaw !== '' && strcasecmp($senderRemarksRaw, 'N/A') !== 0) {
                            if (preg_match_all('/(?:^|,\s*)([^,]+?):\s*(.*?)(?=(?:,\s*[^,]+?:\s)|$)/s', $senderRemarksRaw, $sm) && !empty($sm[0])) {
                                $sidx = count($sm[0]) - 1;
                                $remarksLatest = trim((string) $sm[1][$sidx] . ': ' . (string) $sm[2][$sidx]);
                            } else {
                                $remarksLatest = $senderRemarksRaw;
                            }
                        }
                        $amountOriginalRaw = amount_pdo_value_to_string($row['amount_original_resolved'] ?? $row['amount'] ?? '');
                        $chargedRaw = amount_pdo_value_to_string($row['charged_amount'] ?? '');
                        $voucherStatus = trim((string) ($row['voucher_status'] ?? ''));
                        $isArchivedRow = stripos($voucherStatus, 'archived') !== false || stripos((string) ($row['address'] ?? ''), 'archived') !== false;
                        $coaOptions = trim((string) ($row['coa_options'] ?? ''));
                        $tinEmployeeNo = trim((string) (($row['v_tin_employee_no'] ?? '') !== '' ? $row['v_tin_employee_no'] : ($row['tin_employee_no'] ?? '')));
                        $voucherDate = trim((string) (($row['v_voucher_date'] ?? '') !== '' ? $row['v_voucher_date'] : ($row['voucher_date'] ?? '')));
                    ?>
                        <tr<?= $isArchivedRow ? ' class="vstat-row-archived"' : '' ?>>
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
                            <td data-label="processing_no"><?php echo htmlspecialchars((string) ($row['processing_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="payee"><?php echo htmlspecialchars((string) ($row['payee'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <?php echo voucher_amount_stack_cell_html($amountOriginalRaw, $chargedRaw, 'amount-cell'); ?>
                            <td data-label="voucher_status_display">
                                <?php if ($voucherStatus !== '') : ?>
                                    <span class="vstat-status-badge<?= $isArchivedRow ? ' vstat-status-badge--archived' : '' ?>"><?php echo htmlspecialchars($voucherStatus, ENT_QUOTES, 'UTF-8'); ?></span>
                                <?php endif; ?>
                            </td>
                            <td data-label="remarks_display" class="return-remarks-cell"><?php
                                echo $remarksLatest !== ''
                                    ? '<span class="remarks-badge">' . htmlspecialchars($remarksLatest, ENT_QUOTES, 'UTF-8') . '</span>'
                                    : '';
                            ?></td>
                            <td data-label="ors_no" class="hidden"><?php echo htmlspecialchars((string) ($row['ors_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="dv_no" class="hidden"><?php echo htmlspecialchars((string) ($row['dv_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="ada_check_no" class="hidden"><?php echo htmlspecialchars((string) ($row['ada_check_no'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="address" class="hidden status"><?php echo htmlspecialchars((string) ($row['address'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="particulars" class="hidden"><?php echo htmlspecialchars((string) ($row['particulars'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="amount_original" class="hidden"><?php echo htmlspecialchars($amountOriginalRaw, ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="charged_amount" class="hidden"><?php echo htmlspecialchars($chargedRaw, ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="voucher_date" class="hidden"><?php echo htmlspecialchars($voucherDate, ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="tin_employee_no" class="hidden"><?php echo htmlspecialchars($tinEmployeeNo, ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="office_from" class="hidden"><?php echo htmlspecialchars((string) ($row['office_from'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="office_to" class="hidden"><?php echo htmlspecialchars((string) ($row['office_to'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="encoded_by" class="hidden"><?php echo htmlspecialchars((string) ($row['encoded_by'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="encoded_from" class="hidden"><?php echo htmlspecialchars((string) ($row['encoded_from'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="datetime_encoded" class="hidden"><?php echo htmlspecialchars((string) ($row['datetime_encoded'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="process_status" class="hidden"><?php echo htmlspecialchars((string) ($row['status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="voucher_status" class="hidden"><?php echo htmlspecialchars($voucherStatus, ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="datetime_status" class="hidden"><?php echo htmlspecialchars((string) ($row['datetime_status'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="remarks" class="hidden"><?php echo htmlspecialchars($remarksRaw, ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="sender_remarks" class="hidden"><?php echo htmlspecialchars($senderRemarksRaw, ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="voucher_type" class="hidden"><?php echo htmlspecialchars((string) ($row['voucher_type'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="coa_options" class="hidden"><?php echo htmlspecialchars($coaOptions, ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="coa_category" class="hidden"><?php echo htmlspecialchars((string) ($row['coa_category'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="coa_subsection" class="hidden"><?php echo htmlspecialchars((string) ($row['coa_subsection'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="process_history" class="hidden"><?php echo htmlspecialchars($processHistory, ENT_QUOTES, 'UTF-8'); ?></td>
                            <td data-label="total_processing_time" class="hidden"><?php echo htmlspecialchars((string) ($row['total_processing_time'] ?? ''), ENT_QUOTES, 'UTF-8'); ?></td>
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
                        <?php else: ?>
                            <?php if ($currentPage > 1): ?>
                                <a class="pagination_btn_modern" href="?page=<?php echo ($currentPage - 1); ?>&rowsPerPage=<?php echo (int)$rowsPerPage; ?><?php echo $qsSearch . $qsOffice; ?>">Previous</a>
                            <?php else: ?>
                                <button class="pagination_btn_modern" type="button" disabled>Previous</button>
                            <?php endif; ?>

                            <div class="pagination_pages pagination_pages--modern">
                                <?php
                                $pageRange = 5;
                                $startPage = max(1, $currentPage - (int)floor($pageRange / 2));
                                $endPage2 = min($totalPages, $startPage + $pageRange - 1);
                                if ($endPage2 - $startPage + 1 < $pageRange) {
                                    $startPage = max(1, $endPage2 - $pageRange + 1);
                                }

                                if ($totalPages <= 7) {
                                    for ($i = 1; $i <= $totalPages; $i++) {
                                        $active = ($i == $currentPage) ? ' active' : '';
                                        echo '<a class="pagination_page_num' . $active . '" href="?page=' . $i . '&rowsPerPage=' . (int)$rowsPerPage . $qsSearch . $qsOffice . '">' . $i . '</a>';
                                    }
                                } else {
                                    echo '<a class="pagination_page_num' . (1 == $currentPage ? ' active' : '') . '" href="?page=1&rowsPerPage=' . (int)$rowsPerPage . $qsSearch . $qsOffice . '">1</a>';
                                    if ($startPage > 2) echo '<span class="pagination_ellipsis">...</span>';
                                    for ($i = max(2, $startPage); $i <= min($totalPages - 1, $endPage2); $i++) {
                                        $active = ($i == $currentPage) ? ' active' : '';
                                        echo '<a class="pagination_page_num' . $active . '" href="?page=' . $i . '&rowsPerPage=' . (int)$rowsPerPage . $qsSearch . $qsOffice . '">' . $i . '</a>';
                                    }
                                    if ($endPage2 < $totalPages - 1) echo '<span class="pagination_ellipsis">...</span>';
                                    echo '<a class="pagination_page_num' . ($totalPages == $currentPage ? ' active' : '') . '" href="?page=' . $totalPages . '&rowsPerPage=' . (int)$rowsPerPage . $qsSearch . $qsOffice . '">' . $totalPages . '</a>';
                                }
                                ?>
                            </div>

                            <?php if ($currentPage < $totalPages): ?>
                                <a class="pagination_btn_modern" href="?page=<?php echo ($currentPage + 1); ?>&rowsPerPage=<?php echo (int)$rowsPerPage; ?><?php echo $qsSearch . $qsOffice; ?>">Next</a>
                            <?php else: ?>
                                <button class="pagination_btn_modern" type="button" disabled>Next</button>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <div class="pagination_info">
                        <?php echo $displayTotal < 1 ? 'NO DATA TO DISPLAY' : ('Showing ' . $endEntry . ' of ' . $totalRows . ' results'); ?>
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

        function fitStatusViewport() {
            var wrapperTop = tableWrapper.getBoundingClientRect().top;
            var pagination = tableCard.querySelector('.voucher-pagination-footer');
            var paginationHeight = pagination ? pagination.offsetHeight : 0;
            var bottomGap = 20;
            var available = window.innerHeight - wrapperTop - paginationHeight - bottomGap;
            tableWrapper.style.maxHeight = Math.max(160, available) + 'px';
        }

        function scheduleStatusLayoutSync() {
            if (layoutTimer) {
                clearTimeout(layoutTimer);
            }
            layoutTimer = setTimeout(fitStatusViewport, 80);
        }

        window.addEventListener('resize', scheduleStatusLayoutSync);
        window.addEventListener('load', scheduleStatusLayoutSync);

        if (window.ResizeObserver) {
            var layoutObserver = new ResizeObserver(scheduleStatusLayoutSync);
            layoutObserver.observe(main);
            layoutObserver.observe(tableCard);
        }

        scheduleStatusLayoutSync();
    })();
</script>
<script src="../../protected/js/qr_scanner_search.js"></script>
<script>
    (function() {
        var inp = document.getElementById('filterInput');
        if (!inp) return;
        var initial = String(inp.value || '');
        function applyFilterSearch() {
            var v = String(inp.value || '');
            if (v === initial) return;
            var url = '?page=1&rowsPerPage=50&searchTerm=' + encodeURIComponent(v);
            var officeFilter = document.getElementById('officeFilter');
            if (officeFilter && officeFilter.value && officeFilter.value !== 'all') {
                url += '&office=' + encodeURIComponent(officeFilter.value);
            }
            window.location.href = url;
        }
        var officeFilter = document.getElementById('officeFilter');
        if (officeFilter) {
            officeFilter.addEventListener('change', function() {
                var url = '?page=1&rowsPerPage=50';
                var v = String(inp.value || '');
                if (v !== '') {
                    url += '&searchTerm=' + encodeURIComponent(v);
                }
                if (officeFilter.value && officeFilter.value !== 'all') {
                    url += '&office=' + encodeURIComponent(officeFilter.value);
                }
                window.location.href = url;
            });
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
    document.addEventListener('DOMContentLoaded', function() {
        document.querySelectorAll('.vstat-row-archived').forEach(function(row) {
            row.style.backgroundColor = '#fffbeb';
        });
    });
</script>
<?php if ($invalidSearch): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof showNotify === 'function') {
            showNotify('Invalid search: remove special characters or shorten your query.', 'warning', 2600);
        }
    });
</script>
<?php elseif (trim($rawSearch) !== '' && $q !== '' && $displayTotal < 1): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        if (typeof showNotify === 'function') {
            showNotify('No matching vouchers for your search.', 'warning', 2200);
        }
    });
</script>
<?php endif; ?>
<script src="../../protected/js/main.js"></script>
<script src="../../protected/js/amount_helper.js"></script>
<script src="../../protected/js/popscript.js"></script>
<script>
    function isNonZeroAmount(value) {
        var normalized = typeof normalizeAmountInput === 'function'
            ? normalizeAmountInput(String(value || ''))
            : String(value || '').replace(/,/g, '').trim();
        if (normalized === '' || normalized === '0' || normalized === '0.00') {
            return false;
        }
        var num = parseFloat(normalized);
        return !isNaN(num) && num !== 0;
    }

    function cellText(row, label) {
        var cell = row.querySelector('[data-label="' + label + '"]');
        return cell ? String(cell.textContent || '').trim() : '';
    }

    function populateStatusViewModal(row) {
        var processing_no = cellText(row, 'processing_no');
        var ors_no = cellText(row, 'ors_no');
        var dv_no = cellText(row, 'dv_no');
        var ada_check_no = cellText(row, 'ada_check_no');
        var payee = cellText(row, 'payee');
        var address = cellText(row, 'address');
        var particulars = cellText(row, 'particulars');
        var tin_employee_no = cellText(row, 'tin_employee_no');
        var amountTd = row.querySelector('[data-label="amount"]');
        var grossFromStack = amountTd ? (amountTd.getAttribute('data-amount-gross') || '') : '';
        var netFromStack = amountTd ? (amountTd.getAttribute('data-amount-net') || '') : '';
        var amountOriginal = typeof normalizeAmountInput === 'function'
            ? normalizeAmountInput(cellText(row, 'amount_original') || grossFromStack)
            : (cellText(row, 'amount_original') || grossFromStack);
        var charged_amount = typeof normalizeAmountInput === 'function'
            ? normalizeAmountInput(cellText(row, 'charged_amount') || netFromStack)
            : (cellText(row, 'charged_amount') || netFromStack);
        var amount = typeof hasDistinctNetAmount === 'function' && hasDistinctNetAmount(amountOriginal, charged_amount)
            ? charged_amount
            : amountOriginal;
        var voucher_date = cellText(row, 'voucher_date');
        var office_from = cellText(row, 'office_from');
        var office_to = cellText(row, 'office_to');
        var encoded_by = cellText(row, 'encoded_by');
        var encoded_from = cellText(row, 'encoded_from');
        var datetime_encoded = cellText(row, 'datetime_encoded');
        var process_status = cellText(row, 'process_status');
        var remarks = cellText(row, 'remarks');
        var sender_remarks = cellText(row, 'sender_remarks');
        var voucher_type = cellText(row, 'voucher_type');
        var coa_options = cellText(row, 'coa_options');
        var coa_category = cellText(row, 'coa_category');
        var coa_subsection = cellText(row, 'coa_subsection');
        var voucher_status = cellText(row, 'voucher_status');
        var datetime_status = cellText(row, 'datetime_status');

        document.getElementById('form_title').textContent = 'View Voucher';
        document.querySelector('.processing_no').value = processing_no;
        document.querySelector('.dv_no').value = dv_no;
        document.querySelector('.ors_no').value = ors_no;
        document.querySelector('.ada_check_no').value = ada_check_no;
        document.querySelector('.payee').value = payee;
        document.querySelector('.address').value = address;
        document.querySelector('.particulars').value = particulars;
        document.querySelector('.tin_employee_no').value = tin_employee_no;
        document.querySelector('.amount').value = amount;
        if (typeof setAmountDisplayValue === 'function') {
            setAmountDisplayValue(document.querySelector('.string_amount'), amount);
        } else {
            document.querySelector('.string_amount').value = amount;
        }
        document.querySelector('.voucher_date').value = voucher_date;
        document.querySelector('.office_from').value = office_from;
        document.querySelector('.office_to').value = office_to;
        document.querySelector('.encoded_by').value = encoded_by;
        document.querySelector('.encoded_from').value = encoded_from;
        document.querySelector('.datetime_encoded').value = datetime_encoded;
        document.querySelector('.process_status').value = process_status;
        document.querySelector('.sender_remarks').value = sender_remarks;
        document.querySelector('.combined_remarks').value = remarks;
        document.querySelector('.voucher_type').value = voucher_type;
        document.getElementById('voucher_status_display').value = voucher_status;
        document.getElementById('datetime_status_display').value = datetime_status;

        var selectedCoaOptionsInput = document.getElementById('selected_coa_options');
        var selectedCoaOptionsContainer = document.getElementById('selected-coa-options-container');
        var viewCoaBtn = document.getElementById('view_coa_requirements_btn');

        if (coa_options !== '') {
            if (selectedCoaOptionsInput) selectedCoaOptionsInput.value = coa_options;
            if (viewCoaBtn) {
                viewCoaBtn.dataset.coaOptions = coa_options;
                viewCoaBtn.dataset.coaCategory = coa_category || '';
                viewCoaBtn.dataset.coaSubsection = coa_subsection || '';
            }
            if (selectedCoaOptionsContainer) {
                selectedCoaOptionsContainer.style.display = 'block';
            }
        } else {
            if (selectedCoaOptionsInput) selectedCoaOptionsInput.value = '';
            if (selectedCoaOptionsContainer) selectedCoaOptionsContainer.style.display = 'none';
            if (viewCoaBtn) {
                viewCoaBtn.dataset.coaOptions = '';
                viewCoaBtn.dataset.coaCategory = '';
                viewCoaBtn.dataset.coaSubsection = '';
            }
        }

        var amountPrimaryBlock = document.querySelector('.amount_primary_block');
        var chargedContainer = document.querySelector('.charged_amount_container');
        var chargedStringInput = document.getElementById('charged_string_amount');
        var hasCharged = hasDistinctNetAmount(amountOriginal, charged_amount);

        if (hasCharged) {
            if (amountPrimaryBlock) amountPrimaryBlock.style.display = 'none';
            if (chargedContainer) chargedContainer.style.display = 'flex';
            if (chargedStringInput && typeof setAmountDisplayValue === 'function') {
                setAmountDisplayValue(chargedStringInput, charged_amount);
            } else if (chargedStringInput) {
                chargedStringInput.value = charged_amount;
            }
        } else {
            if (amountPrimaryBlock) amountPrimaryBlock.style.display = '';
            if (chargedContainer) chargedContainer.style.display = 'none';
            if (chargedStringInput) chargedStringInput.value = '';
        }
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
            if (name !== 'btn-view') return;

            populateStatusViewModal(row);
            if (typeof openPopup === 'function') {
                openPopup();
            } else {
                document.getElementById('popupForm').style.display = 'block';
                document.getElementById('overlay').style.display = 'block';
            }
        });
    });
</script>
<script>
    (function() {
        var viewBtn = document.getElementById('view_coa_requirements_btn');
        var modal = document.getElementById('coaOptionsModal');
        var overlay = document.getElementById('coa_modal_overlay');
        var modalTitle = document.getElementById('coa_modal_title');
        var optionsList = document.getElementById('coa_options_list');
        var closeX = document.getElementById('close_coa_modal');
        var cancelBtn = document.getElementById('coa_modal_cancel');

        function closeModal() {
            if (modal) modal.style.display = 'none';
            if (overlay) overlay.style.display = 'none';
        }

        if (closeX) closeX.addEventListener('click', closeModal);
        if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
        if (overlay) overlay.addEventListener('click', closeModal);

        function normalizeCoaSelections(parsed) {
            if (parsed == null) return [];
            if (typeof parsed === 'string') {
                var t = parsed.trim();
                if (!t) return [];
                try {
                    return normalizeCoaSelections(JSON.parse(t));
                } catch (e) {
                    return [{ label: t }];
                }
            }
            if (Array.isArray(parsed)) return parsed;
            if (typeof parsed === 'object') {
                if (Array.isArray(parsed.items)) return parsed.items;
                return Object.keys(parsed).filter(function(k) { return /^\d+$/.test(k); })
                    .sort(function(a, b) { return Number(a) - Number(b); })
                    .map(function(k) { return parsed[k]; });
            }
            return [];
        }

        function coaItemLabel(opt) {
            if (opt == null) return '';
            if (typeof opt === 'string' || typeof opt === 'number') return String(opt).trim();
            if (typeof opt === 'object') return String(opt.label || opt.value || opt.text || '').trim();
            return '';
        }

        if (viewBtn) {
            viewBtn.addEventListener('click', function() {
                var raw = this.dataset.coaOptions || document.getElementById('selected_coa_options')?.value || '';
                var voucherType = document.getElementById('voucher_type')?.value || '';
                if (!raw || String(raw).trim() === '') {
                    if (typeof showNotify === 'function') {
                        showNotify('No checklist requirements found for this voucher.', 'warning', 3000);
                    }
                    return;
                }

                var selected = [];
                try {
                    selected = normalizeCoaSelections(JSON.parse(String(raw).trim()));
                } catch (e) {
                    selected = [{ label: String(raw) }];
                }

                if (modalTitle) modalTitle.textContent = 'Selected Requirements' + (voucherType ? ' - ' + voucherType : '');
                if (optionsList) {
                    optionsList.innerHTML = '';
                    selected.forEach(function(opt, idx) {
                        var labelText = coaItemLabel(opt);
                        if (!labelText) return;
                        var isChecked = (opt && typeof opt === 'object' && Object.prototype.hasOwnProperty.call(opt, 'checked'))
                            ? (opt.checked !== false && opt.checked !== 0 && opt.checked !== '0')
                            : true;
                        var label = document.createElement('label');
                        label.className = 'coa-requirement-view-only';
                        var checkbox = document.createElement('input');
                        checkbox.type = 'checkbox';
                        checkbox.checked = !!isChecked;
                        checkbox.value = labelText;
                        var span = document.createElement('span');
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
</body>

</html>