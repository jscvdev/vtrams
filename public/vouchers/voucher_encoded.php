<?php
include('../includes/header.php');
require_once __DIR__ . '/../../protected/core/components/helpers/audit_helper.inc.php';
AuditHelper::logPageView('Encoded Vouchers');
include('../../protected/handler/voucher_module/voucher_errhandler.inc.php');
include('../../protected/core/components/notifications/err_handler_custom_alert.php');
require_once __DIR__ . '/../../protected/core/components/notifications/custom_alert.php';
require_once __DIR__ . '/../../protected/core/components/notifications/notification.inc.php';
require_once __DIR__ . '/checklist_config.php';
require_once __DIR__ . '/../../protected/core/components/helpers/utilities_signatory_helper.inc.php';
// Preload DV signatories from database for the printable disbursement voucher.
if (!function_exists('voucher_get_signatory')) {
    function voucher_get_signatory(PDO $pdo, string $key): array
    {
        $office = utilities_signatory_default_office();
        $rows = utilities_fetch_dv_signatory_rows($pdo, $office, $key);
        $row = null;
        foreach ($rows as $candidate) {
            if ((int)($candidate['is_active'] ?? 0) !== 1) {
                continue;
            }
            if ((int)($candidate['is_default'] ?? 0) === 1) {
                $row = $candidate;
                break;
            }
            if ($row === null) {
                $row = $candidate;
            }
        }
        $row = $row ?? [];
        return [
            'name' => (string)($row['display_name'] ?? ''),
            'pos1' => (string)($row['position_line1'] ?? ''),
            'pos2' => (string)($row['position_line2'] ?? ''),
        ];
    }
}
if (isset($pdo) && $pdo instanceof PDO) {
    $dv_cert_key = (($_SESSION['logged_user_division'] ?? '') === 'TSD') ? 'dv_certified_tsd' : 'dv_certified_msd';
    $dv_cert = voucher_get_signatory($pdo, $dv_cert_key);
    $dv_accounting = voucher_get_signatory($pdo, 'dv_accounting_certified');
    $dv_approved = voucher_get_signatory($pdo, 'dv_approved_for_payment');
}
include 'db_voucher.php';
check_voucher_errors();

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
        <h1 class="voucher-dashboard-title">Encoded</h1>
    </header>
    <style>
        #encodedFilterForm {
            display: flex;
            align-items: center;
            flex-wrap: nowrap !important;
            width: 100%;
            gap: 10px;
        }

        #encodedFilterForm .filter-chips {
            flex: 0 0 auto;
            flex-wrap: nowrap !important;
        }

        #encodedFilterForm .filter-search {
            flex: 1 1 auto;
            min-width: 0 !important;
        }

        /* Row burger menu */
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
                <form method="GET" action="" id="encodedFilterForm" class="filter-toolbar-form" onsubmit="return false;">
                    <div class="filter-chips" aria-label="Voucher filter tools">
                        <a class="filter-icon-btn" href="voucher_encoded.php" aria-label="Home">
                        </a>
                        <button type="button" class="filter-icon-btn" aria-label="Copy">
                        </button>
                    </div>
                    <div class="filter-search">
                        <input type="text" id="filterInput" placeholder="search" autocomplete="off">
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="overlay voucher-premium-overlay" id="overlay"></div>
    <style>
        #coa_options_list_forward input[type="checkbox"] {
            margin-right: 10px;
            cursor: pointer;
        }
        .voucher-print-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 6px;
            align-items: center;
        }
    </style>
    <!-- COA checklist modal (forward slip reprint) -->
    <div class="popup-form voucher-premium-modal popup-form--compact" id="coaOptionsModalForward" style="display: none;">
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
                                <div id="coa_options_list_forward" style="background-color: white; border: 1px solid #ccc; border-radius: 4px; padding: 10px; max-height: 400px; overflow-y: auto;"></div>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="popupForm-footer__container">
                    <div class="footer-button__container" style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
                        <p style="margin: 0; font-size: 12px; color: #555; flex: 1; min-width: 200px;">Use <strong>Save</strong> to store your default checklist for this voucher type. <strong>Confirm</strong> applies your selection and opens the slip print dialog.</p>
                        <button class="btn tertiary" id="coa_modal_select_all_forward" type="button">Select all</button>
                        <button class="btn secondary" id="coa_modal_persist_forward" type="button">Save</button>
                        <button class="btn primary" id="coa_modal_save_forward" type="button">Confirm</button>
                        <button class="btn secondary transparent" id="coa_modal_cancel_forward" type="button">CANCEL</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <div class="overlay voucher-premium-overlay" id="coa_modal_overlay_forward" style="display: none;"></div>
    <!-- DV signatory selection modal (before print) -->
    <div class="popup-form voucher-premium-modal popup-form--compact" id="signatoryModal" style="display: none;">
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
                            <div class="label-input__container" id="dv_sig_office_container" style="display: none;">
                                <label for="dv_sig_office_select">Office <span style="color: #64748b; font-weight: normal;">(optional)</span></label>
                                <select class="form-custom-input" id="dv_sig_office_select"></select>
                            </div>
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
    <div class="overlay voucher-premium-overlay" id="signatory_modal_overlay" style="display: none;"></div>
    <div class="voucher-card voucher-card--table">
        <h2 class="voucher-card-title">Encoded Voucher Summary</h2>
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
                gap: 2px;
            }

            #my-Table .voucher-table-actions-group .voucher-table-action-btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                width: 28px !important;
                min-width: 28px !important;
                height: 28px !important;
                min-height: 28px !important;
                padding: 0 !important;
                border: 1px solid #e2e8f0 !important;
                border-radius: 6px !important;
                background: #ffffff !important;
                color: #64748b !important;
                box-shadow: none !important;
                font-size: 0 !important;
                line-height: 1;
                cursor: pointer;
                transition: background 120ms ease, border-color 120ms ease, color 120ms ease;
            }

            #my-Table .voucher-table-actions-group .voucher-table-action-btn i {
                font-size: 15px;
                line-height: 1;
            }

            #my-Table .voucher-table-actions-group .voucher-table-action-btn span {
                display: none;
            }

            #my-Table .voucher-table-actions-group .voucher-table-action-btn:hover {
                background: #f1f5f9 !important;
                border-color: #cbd5e1 !important;
                color: #475569 !important;
                transform: none;
            }

            #my-Table .voucher-table-actions-group .voucher-table-action-btn:active {
                background: #e2e8f0 !important;
            }

            .voucher-print-actions {
                display: inline-flex;
                flex-wrap: wrap;
                gap: 6px;
                align-items: center;
            }

            .voucher-pagination-footer {
                position: static;
                background: #fff;
                border-top: 1px solid rgba(229, 231, 235, 1);
                padding: 10px 0 0;
                margin-top: auto;
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
                        <th>Remarks</th>
                        <th class="voucher-table-actions-cell">Actions</th>
                    </tr>
                </thead>
                <tbody id="tableBody"></tbody>
            </table>
            <div class="no-display" id="voucherNoData" style="display:none; width: 100%; height: inherit; justify-content: center; align-items: center;">
                <p>NO DATA TO DISPLAY</p>
            </div>
        </div>
        <div class="voucher-pagination-footer">
            <div class="pagination">
                <div class="pagination_container pagination_container--modern" id="voucherPagination" style="display:none;">
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

<!-- Nature of Claim (forward slip reprint) -->
<div class="popup-form voucher-premium-modal popup-form--compact" id="natureOfClaimModal" style="display: none;" role="dialog" aria-modal="true" aria-labelledby="nature_of_claim_modal_title">
    <div class="popupForm-box__container">
        <div class="popupForm-header__container">
            <p id="nature_of_claim_modal_title">Nature of Claim</p>
            <i class="ri-close-fill close-icon" id="close_nature_of_claim_modal"></i>
        </div>
        <div class="f-container">
            <div class="box-body__container flex-row">
                <div class="popupForm-body__container" style="width: 100%;">
                    <div class="form-container">
                        <p style="margin: 0 0 12px; font-size: 13px; color: #555;">Enter the nature of claim to appear on the printed forward slip.</p>
                        <div class="label-input__container">
                            <label for="nature_of_claim_modal_input">Nature of Claim <span style="color: red;">*</span></label>
                            <input type="text" class="form-custom-input" id="nature_of_claim_modal_input" autocomplete="off" placeholder="e.g. Traveling Expenses, Procurement of Supplies">
                        </div>
                    </div>
                </div>
            </div>
            <div class="popupForm-footer__container">
                <div class="footer-button__container">
                    <button class="btn primary" id="nature_of_claim_modal_confirm" type="button">Confirm &amp; Print Slip</button>
                    <button class="btn secondary transparent" id="nature_of_claim_modal_cancel" type="button">CANCEL</button>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="overlay voucher-premium-overlay" id="nature_of_claim_modal_overlay" style="display: none;" aria-hidden="true"></div>

<div id="encoded-slip-hidden-fields" style="display:none;" aria-hidden="true">
    <input type="text" id="encoded_processing_no" value="">
    <input type="text" id="encoded_payee" value="">
    <input type="text" id="string_amount" value="">
    <input type="text" id="voucher_type" value="">
    <input type="text" id="encoded_type_hidden" value="">
    <input type="text" id="remarks" value="">
    <input type="text" id="nature_of_claim" value="">
    <input type="text" id="selected_coa_options_forward" value="">
    <input type="text" id="coa_category_forward_hidden" value="">
    <input type="text" id="coa_subsection_forward_hidden" value="">
    <button type="button" id="print_forward_slip" tabindex="-1" aria-hidden="true"></button>
</div>

<script>
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
        let currentPage = 1;
        let totalPages = 1;
        let totalRows = 0;
        let activeController = null;
        if (tableWrapper) tableWrapper.setAttribute('data-table-loading', 'true');
        const filterEl = document.getElementById('filterInput');

        function applySearch() {
            if (!filterEl) return;
            searchQ = String(filterEl.value || '');
            currentPage = 1;
            loadPage(1);
        }
        if (filterEl) {
            filterEl.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    applySearch();
                }
            });
        }

        const typeLabels = <?php echo json_encode(checklist_types_with_labels(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

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
                    if (typeof window.prepareDvPrint === 'function') {
                        window.prepareDvPrint();
                    }
                    window.print();
                }
            });
        }
        function populateSlipFields(row) {
            const setVal = function(id, val) {
                const el = document.getElementById(id);
                if (el) el.value = String(val ?? '');
            };
            const amountNorm = normalizeAmountInput(row.amount || '');
            const amountShown = amountNorm !== '' && typeof formatAmountDisplay === 'function'
                ? formatAmountDisplay(amountNorm)
                : String(row.amount || '');
            setVal('encoded_processing_no', row.processing_no);
            setVal('encoded_payee', row.payee);
            setVal('string_amount', amountShown);
            setVal('voucher_type', row.voucher_type);
            setVal('encoded_type_hidden', row.voucher_type);
            setVal('remarks', row.return_remarks || '');
            setVal('nature_of_claim', '');
            setVal('coa_category_forward_hidden', row.voucher_type || '');
            setVal('coa_subsection_forward_hidden', row.voucher_type || '');
            const coaRaw = String(row.coa_options || '').trim();
            setVal('selected_coa_options_forward', coaRaw);
        }
        function attachSlipPrintHandler(btn, row) {
            btn.addEventListener('click', function() {
                populateSlipFields(row);
                if (typeof window.openEncodedSlipChecklistModal === 'function') {
                    window.openEncodedSlipChecklistModal(row);
                } else if (typeof showNotify === 'function') {
                    showNotify('Checklist dialog is not available. Please refresh the page.', 'warning', 3500);
                }
            });
        }
        function setPagination() {
            if (!pagWrap || !pagInfo || !prevBtn || !nextBtn) return;
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
        function rowMenuHtml(processingNo) {
            var pn = String(processingNo ?? '');
            return '<td class="voucher-row-menu-cell" data-label="">' +
                '<div class="voucher-row-menu">' +
                '<button type="button" class="voucher-row-menu-trigger" aria-label="Row actions" aria-haspopup="true" aria-expanded="false">' +
                '<i class="ri-more-2-fill" aria-hidden="true"></i></button>' +
                '<div class="voucher-row-menu-dropdown" role="menu">' +
                '<a class="voucher-row-menu-link" href="voucher_status_report.php?q=' + encodeURIComponent(pn) + '" role="menuitem">' +
                '<i class="ri-history-line" aria-hidden="true"></i><span>History</span></a>' +
                '</div></div></td>';
        }
        function renderRows(data) {
            tableBody.innerHTML = '';
            const frag = document.createDocumentFragment();
            data.forEach(function(row) {
                const tr = document.createElement('tr');
                tr.className = 'voucher-data-row';
                var amountNorm = normalizeAmountInput(row.amount || '');
                var amountShown = amountNorm !== '' ? formatAmountDisplay(amountNorm) : escapeHtml(row.amount || '');
                tr.innerHTML =
                    rowMenuHtml(row.processing_no) +
                    '<td data-label="processing_no">' + escapeHtml(row.processing_no) + '</td>' +
                    '<td data-label="payee">' + escapeHtml(row.payee) + '</td>' +
                    '<td data-label="address" class="hidden">' + escapeHtml(row.address) + '</td>' +
                    '<td data-label="particulars" class="hidden">' + escapeHtml(row.particulars) + '</td>' +
                    '<td data-label="amount" class="amount" data-amount="' + escapeHtml(amountNorm) + '">' + escapeHtml(amountShown) + '</td>' +
                    '<td data-label="voucher_date" class="hidden">' + escapeHtml(row.voucher_date) + '</td>' +
                    '<td data-label="voucher_type_display" class="hidden voucher-type-cell">' + typeBadge(row.voucher_type) + '</td>' +
                    '<td data-label="return_remarks" class="return-remarks-cell">' + remarksBadge(row.return_remarks) + '</td>' +
                    '<td class="voucher-table-actions-cell" data-label="actions"><div class="voucher-table-actions-group">' +
                    '<button class="btn voucher-table-action-btn voucher-table-action-btn--print" name="btn-gen-slip" type="button" aria-label="Print" title="Print"><i class="ri-printer-line" aria-hidden="true"></i><span>Print</span></button>' +
                    '<button class="btn voucher-table-action-btn voucher-table-action-btn--slip" name="btn-print-slip" type="button" aria-label="Slip" title="Slip"><i class="ri-file-paper-2-line" aria-hidden="true"></i><span>Slip</span></button>' +
                    '</div></td>' +
                    '<td data-label="encoded_by" class="hidden">' + escapeHtml(row.encoded_by) + '</td>' +
                    '<td data-label="datetime_encoded" class="hidden">' + escapeHtml(row.datetime_encoded) + '</td>' +
                    '<td data-label="encoded_from" class="hidden">' + escapeHtml(row.encoded_from) + '</td>' +
                    '<td data-label="tin_employee_no" class="hidden">' + escapeHtml(row.tin_employee_no) + '</td>' +
                    '<td data-label="voucher_type" class="hidden">' + escapeHtml(row.voucher_type) + '</td>';
                const printBtn = tr.querySelector('button[name="btn-gen-slip"]');
                if (printBtn) attachPrintHandler(printBtn, row);
                const slipBtn = tr.querySelector('button[name="btn-print-slip"]');
                if (slipBtn) attachSlipPrintHandler(slipBtn, row);
                frag.appendChild(tr);
            });
            tableBody.appendChild(frag);
            if (typeof formatAmountTableCells === 'function') {
                formatAmountTableCells('.amount[data-amount]:not([data-amount-skip])');
            }
        }
        function listUrl(page) {
            var u = '../../protected/handler/fetch_handlers/fetch_my_dv_entries.php?page=' + encodeURIComponent(String(page))
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
                tableBody.innerHTML = '';
                if (noData) {
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

            if (e.target.closest('.voucher-row-menu-link')) {
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
<script src="../../protected/js/amount_helper.js"></script>
<script src="../../protected/js/voucher.js"></script>
<script src="../../protected/js/popscript.js"></script>
<script>
    (function() {
        window.filterVoucherTable = function() {};
    })();
</script>

<script>
    // Expose logged user name to external scripts (safe JSON encoding)
    window.__loggedUserEmpName = <?php echo json_encode($_SESSION['logged_user_emp_name'] ?? $logged_user_name ?? ''); ?>;
</script>

<script>
    // Encoded page: COA checklist modal for forward slip reprint.
    (function() {
        const templates = <?php echo json_encode(checklist_get_active_templates(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
        const COA_PREFS_API = '../../protected/handler/coa_prefs_module/coa_forward_prefs_handler.php';
        const COA_PREFS_TOKEN = <?php echo json_encode((string)($_SESSION['token'] ?? ''), JSON_UNESCAPED_UNICODE); ?>;
        const coaPrefsCache = Object.create(null);
        let activeSlipRow = null;

        const modal = document.getElementById('coaOptionsModalForward');
        const overlay = document.getElementById('coa_modal_overlay_forward');
        const modalTitle = document.getElementById('coa_modal_title_forward');
        const optionsList = document.getElementById('coa_options_list_forward');
        const closeBtn = document.getElementById('close_coa_modal_forward');
        const cancelBtn = document.getElementById('coa_modal_cancel_forward');
        const saveBtn = document.getElementById('coa_modal_save_forward');
        const persistBtn = document.getElementById('coa_modal_persist_forward');
        const selectAllBtn = document.getElementById('coa_modal_select_all_forward');
        const hiddenSelected = document.getElementById('selected_coa_options_forward');
        const hiddenCategory = document.getElementById('coa_category_forward_hidden');
        const hiddenSubsection = document.getElementById('coa_subsection_forward_hidden');

        function getCurrentVoucherType() {
            return String(
                (activeSlipRow && activeSlipRow.voucher_type) ||
                document.getElementById('voucher_type')?.value ||
                document.getElementById('encoded_type_hidden')?.value ||
                ''
            ).trim();
        }

        function labelNeedsExtraText(label) {
            const t = String(label || '').trim().toLowerCase();
            return t === 'etc' || t === 'etc.' || t === 'others' || t === 'other';
        }

        function parseChecklistItem(raw) {
            if (raw && typeof raw === 'object' && !Array.isArray(raw) && raw.label) {
                const subs = Array.isArray(raw.subitems)
                    ? raw.subitems.map(function(s) { return String(s || '').trim(); }).filter(Boolean)
                    : [];
                return { label: String(raw.label || '').trim(), subitems: subs };
            }
            return { label: String(raw || '').trim(), subitems: [] };
        }

        function getTemplateLabels(voucherType) {
            const t = templates[voucherType];
            const labels = new Set();
            if (!t || !Array.isArray(t.items)) return labels;
            t.items.forEach(function(raw) {
                const meta = parseChecklistItem(raw);
                if (meta.label) labels.add(meta.label);
                meta.subitems.forEach(function(s) { labels.add(String(s || '').trim()); });
            });
            return labels;
        }

        function loadSavedPrefs(voucherType) {
            const vt = String(voucherType || '').trim();
            if (!vt) return Promise.resolve(null);
            if (Object.prototype.hasOwnProperty.call(coaPrefsCache, vt)) {
                return Promise.resolve(coaPrefsCache[vt]);
            }
            const url = COA_PREFS_API + '?voucher_type=' + encodeURIComponent(vt);
            return fetch(url, { credentials: 'same-origin' })
                .then(function(res) {
                    return res.json().then(function(data) {
                        return { ok: res.ok, data: data };
                    });
                })
                .then(function(payload) {
                    const data = payload && payload.data;
                    if (!payload || !payload.ok || !data || !data.ok || !Array.isArray(data.items)) {
                        coaPrefsCache[vt] = null;
                        return null;
                    }
                    coaPrefsCache[vt] = data.items.length ? data.items : null;
                    return coaPrefsCache[vt];
                })
                .catch(function() {
                    coaPrefsCache[vt] = null;
                    return null;
                });
        }

        function saveSavedPrefsToDb(voucherType, selectedOptions) {
            const vt = String(voucherType || '').trim();
            if (!vt || !Array.isArray(selectedOptions) || selectedOptions.length === 0) {
                return Promise.reject(new Error('No selections to save'));
            }
            return fetch(COA_PREFS_API, {
                method: 'POST',
                credentials: 'same-origin',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    token: COA_PREFS_TOKEN,
                    voucher_type: vt,
                    selected_options: selectedOptions
                })
            })
                .then(function(res) { return res.json(); })
                .then(function(data) {
                    if (!data || !data.ok) {
                        throw new Error((data && data.error) ? data.error : 'Save failed');
                    }
                    coaPrefsCache[vt] = selectedOptions;
                    return data;
                });
        }

        function splitSavedCoaLabel(full, knownBase) {
            const s = String(full || '').trim();
            const base = String(knownBase || '').trim();
            if (!base) return { base: s, extra: '' };
            if (s === base) return { base: base, extra: '' };
            const prefix = base + ' - ';
            if (s.indexOf(prefix) === 0) {
                return { base: base, extra: s.slice(prefix.length).trim() };
            }
            return { base: s, extra: '' };
        }

        function savedItemMatchesCheckbox(savedOpt, checkboxBase) {
            const full = String((savedOpt && (savedOpt.label || savedOpt.value)) || '').trim();
            const base = String(checkboxBase || '').trim();
            if (!full || !base) return false;
            if (full === base) return true;
            return splitSavedCoaLabel(full, base).base === base;
        }

        function filterSavedToTemplate(voucherType, savedItems) {
            const allowed = getTemplateLabels(voucherType);
            if (!allowed.size) return [];
            return (savedItems || []).filter(function(opt) {
                const full = String((opt && (opt.label || opt.value)) || '').trim();
                if (!full) return false;
                if (allowed.has(full)) return true;
                for (const lab of allowed) {
                    if (full === lab || full.indexOf(lab + ' - ') === 0) return true;
                }
                return false;
            });
        }

        function getCheckboxBaseLabel(cb) {
            return (cb.parentElement && cb.parentElement.querySelector('span')
                ? cb.parentElement.querySelector('span').textContent
                : cb.value) || '';
        }

        function applySelectionsToList(savedItems) {
            if (!optionsList || !Array.isArray(savedItems) || savedItems.length === 0) return;
            const checkboxes = Array.from(optionsList.querySelectorAll('input[type="checkbox"][name="coa_options_checklist_forward[]"]'));
            checkboxes.forEach(function(cb) {
                const base = String(getCheckboxBaseLabel(cb) || '').trim();
                const match = savedItems.find(function(opt) {
                    return savedItemMatchesCheckbox(opt, base);
                });
                cb.checked = !!match;
                const extraInput = cb.parentElement && cb.parentElement.querySelector('input[type="text"][data-coa-extra-text="1"]');
                if (extraInput && match) {
                    const full = String((match.label || match.value) || '').trim();
                    extraInput.value = splitSavedCoaLabel(full, base).extra;
                } else if (extraInput) {
                    extraInput.value = '';
                }
                cb.dispatchEvent(new Event('change', { bubbles: true }));
            });
            if (selectAllBtn && checkboxes.length) {
                const allChecked = checkboxes.every(function(cb) { return cb.checked; });
                selectAllBtn.textContent = allChecked ? 'Unselect all' : 'Select all';
            }
        }

        function getStoredCoaSelections() {
            const raw = String(hiddenSelected && hiddenSelected.value || '').trim();
            if (!raw) return null;
            try {
                const parsed = JSON.parse(raw);
                return Array.isArray(parsed) && parsed.length ? parsed : null;
            } catch (e) {
                return null;
            }
        }

        function collectSelectedOptionsFromCheckboxes() {
            if (!optionsList) return [];
            const allCheckboxes = Array.from(optionsList.querySelectorAll('input[type="checkbox"][name="coa_options_checklist_forward[]"]'));
            const checked = allCheckboxes.filter(function(cb) { return cb.checked; });
            return checked.map(function(cb) {
                const base = cb.parentElement.querySelector('span') ? cb.parentElement.querySelector('span').textContent : cb.value;
                const extra = cb.parentElement.querySelector('input[type="text"][data-coa-extra-text="1"]');
                const extraText = String(extra && extra.value || '').trim();
                const label = extraText ? (String(base || '').trim() + ' - ' + extraText) : String(base || '').trim();
                return {
                    id: cb.getAttribute('data-id'),
                    value: label,
                    label: label
                };
            });
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

        function closeModal() {
            if (modal) modal.style.display = 'none';
            if (overlay) overlay.style.display = 'none';
        }

        function renderChecklistOptions(voucherType) {
            const t = templates[voucherType] || {
                title: 'SUPPORTING DOCUMENTS',
                items: ['Obligation Request and Status', 'Disbursement Voucher', 'Supporting documents as per checklist', 'Others']
            };

            if (modalTitle) {
                modalTitle.textContent = 'Select COA Requirements - ' + (t.title || voucherType);
            }

            if (!optionsList) return;

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
                return;
            }

            items.forEach(function(raw, idx) {
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
                row.addEventListener('click', function(e) {
                    const target = e.target;
                    if (target && target.closest && target.closest('.coa-subitem-row')) return;
                    if (target && target.tagName === 'INPUT') return;
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

                    function syncParentFromSubs() {
                        checkbox.checked = subCheckboxes.some(function(cb) { return cb.checked; });
                    }
                    checkbox.addEventListener('change', function() {
                        const on = checkbox.checked;
                        if (!on) {
                            subCheckboxes.forEach(function(cb) { cb.checked = false; });
                        } else if (!subCheckboxes.some(function(cb) { return cb.checked; }) && subCheckboxes.length) {
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

        function openModal(row) {
            activeSlipRow = row || activeSlipRow;
            const voucherType = getCurrentVoucherType();
            if (!voucherType) {
                if (typeof showNotify === 'function') showNotify('This voucher has no type set. Cannot open checklist.', 'warning', 3000);
                return;
            }
            if (persistBtn) {
                persistBtn.disabled = false;
                persistBtn.textContent = 'Save';
            }

            renderChecklistOptions(voucherType);

            const applyToModal = function(savedItems) {
                const savedForModal = filterSavedToTemplate(voucherType, savedItems);
                if (savedForModal.length) {
                    applySelectionsToList(savedForModal);
                }
            };

            const stored = getStoredCoaSelections();
            if (stored) {
                applyToModal(stored);
            } else {
                loadSavedPrefs(voucherType).then(function(saved) {
                    applyToModal(saved || []);
                });
            }

            if (selectAllBtn && (!optionsList || !optionsList.querySelector('input[type="checkbox"][name="coa_options_checklist_forward[]"]'))) {
                selectAllBtn.textContent = 'Select all';
            }

            if (hiddenCategory) hiddenCategory.value = voucherType;
            if (hiddenSubsection) hiddenSubsection.value = voucherType;

            if (modal) modal.style.display = 'block';
            if (overlay) overlay.style.display = 'block';
        }

        window.openEncodedSlipChecklistModal = openModal;
        window.openCoaForwardChecklistModal = openModal;

        if (closeBtn) closeBtn.addEventListener('click', closeModal);
        if (cancelBtn) cancelBtn.addEventListener('click', closeModal);
        if (overlay) overlay.addEventListener('click', closeModal);

        if (selectAllBtn) {
            selectAllBtn.addEventListener('click', function() {
                if (!optionsList) return;
                const checkboxes = Array.from(optionsList.querySelectorAll('input[type="checkbox"][name="coa_options_checklist_forward[]"]'));
                if (checkboxes.length === 0) return;
                const allChecked = checkboxes.every(function(cb) { return cb.checked; });
                checkboxes.forEach(function(cb) {
                    cb.checked = !allChecked;
                    cb.dispatchEvent(new Event('change', { bubbles: true }));
                });
                selectAllBtn.textContent = allChecked ? 'Select all' : 'Unselect all';
            });
        }

        if (persistBtn) {
            persistBtn.addEventListener('click', function() {
                const selectedOptions = collectSelectedOptionsFromCheckboxes();
                if (selectedOptions.length === 0) {
                    if (typeof showNotify === 'function') showNotify('Please select at least one requirement to save.', 'warning', 3000);
                    return;
                }
                const voucherType = getCurrentVoucherType();
                persistBtn.disabled = true;
                const prevLabel = persistBtn.textContent;
                persistBtn.textContent = 'Saving...';
                saveSavedPrefsToDb(voucherType, selectedOptions)
                    .then(function() {
                        if (typeof showNotify === 'function') {
                            showNotify('Default checklist saved for this voucher type.', 'success', 3000);
                        }
                    })
                    .catch(function(err) {
                        if (typeof showNotify === 'function') {
                            showNotify(err && err.message ? err.message : 'Could not save checklist.', 'error', 3500);
                        }
                    })
                    .finally(function() {
                        persistBtn.disabled = false;
                        persistBtn.textContent = prevLabel;
                    });
            });
        }

        if (saveBtn) {
            saveBtn.addEventListener('click', function() {
                if (!hiddenSelected) return;
                const selectedOptions = collectSelectedOptionsFromCheckboxes();
                if (selectedOptions.length === 0) {
                    if (typeof showNotify === 'function') showNotify('Please select at least one requirement.', 'warning', 3000);
                    return;
                }
                hiddenSelected.value = JSON.stringify(selectedOptions);
                hiddenSelected.dispatchEvent(new Event('change', { bubbles: true }));
                closeModal();
                if (typeof window.openNatureOfClaimModalForSlip === 'function') {
                    window.openNatureOfClaimModalForSlip();
                } else if (typeof showNotify === 'function') {
                    showNotify('Slip print dialog is not available. Please refresh the page.', 'warning', 3500);
                }
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
        const officeContainer = document.getElementById('dv_sig_office_container');
        const officeSelect = document.getElementById('dv_sig_office_select');
        let signatoryFetchInFlight = null;

        function getSigCfg() {
            return window.DV_SIGNATORY || {};
        }

        function canSelectSignatoryOffice() {
            return !!getSigCfg().canSelectOffice;
        }

        function applySignatoryPayload(payload) {
            if (typeof applyDvSignatoryPayload === 'function') {
                applyDvSignatoryPayload(payload);
            }
        }

        function populateOfficeSelect(selectedOffice) {
            if (!officeSelect || !officeContainer) return;
            const cfg = getSigCfg();
            const offices = Array.isArray(cfg.offices) ? cfg.offices : [];
            const defaultOffice = String(cfg.office || cfg.penroOffice || '').trim();
            const resolved = String(selectedOffice || defaultOffice || offices[0] || '').trim();
            officeSelect.innerHTML = '';
            offices.forEach(function(officeName) {
                const option = document.createElement('option');
                option.value = officeName;
                option.textContent = officeName;
                if (officeName === resolved) option.selected = true;
                officeSelect.appendChild(option);
            });
            if (!officeSelect.value && offices.length) officeSelect.selectedIndex = 0;
        }

        function populateAllSignatorySelects() {
            if (typeof populateAllDvSignatorySelects === 'function') {
                populateAllDvSignatorySelects({
                    cert: certSelect,
                    accounting: accountingSelect,
                    approved: approvedSelect,
                });
            }
        }

        function fetchSignatoriesForOffice(office) {
            const cfg = getSigCfg();
            const url = String(cfg.fetchUrl || '../../protected/handler/fetch_handlers/fetch_dv_signatories.php');
            const targetOffice = String(office || cfg.office || '').trim();
            const requestUrl = targetOffice
                ? (url + (url.indexOf('?') >= 0 ? '&' : '?') + 'office=' + encodeURIComponent(targetOffice))
                : url;
            if (signatoryFetchInFlight) return signatoryFetchInFlight;
            signatoryFetchInFlight = fetch(requestUrl, {
                credentials: 'same-origin',
                headers: { 'Accept': 'application/json' }
            }).then(function(res) {
                return res.json().then(function(payload) {
                    return { ok: res.ok, payload: payload };
                });
            }).then(function(result) {
                if (!result.ok || !result.payload || result.payload.error) {
                    throw new Error((result.payload && result.payload.error) ? result.payload.error : 'Failed to load signatories');
                }
                applySignatoryPayload(result.payload);
                populateAllSignatorySelects();
                return result.payload;
            }).finally(function() {
                signatoryFetchInFlight = null;
            });
            return signatoryFetchInFlight;
        }

        function hasPrintableSignatoryOptions() {
            if (typeof hasDvPrintableSignatoryOptions === 'function') {
                return hasDvPrintableSignatoryOptions();
            }

            const cfg = getSigCfg();
            if (cfg.printable === true) {
                return true;
            }

            const byKey = cfg.optionsByKey || {};
            const hasCert = (Array.isArray(byKey.dv_certified_msd) && byKey.dv_certified_msd.length)
                || (Array.isArray(byKey.dv_certified_tsd) && byKey.dv_certified_tsd.length)
                || (Array.isArray(byKey.dv_certified_penro) && byKey.dv_certified_penro.length);
            const hasAccounting = Array.isArray(byKey.dv_accounting_certified) && byKey.dv_accounting_certified.length;
            const hasApproved = Array.isArray(byKey.dv_approved_for_payment) && byKey.dv_approved_for_payment.length;

            return hasCert && hasAccounting && hasApproved;
        }

        function closeSignatoryModal() {
            if (modal) modal.style.display = 'none';
            if (overlay) overlay.style.display = 'none';
        }

        function openSignatoryModal() {
            const cfg = getSigCfg();
            if (officeContainer) officeContainer.style.display = canSelectSignatoryOffice() ? '' : 'none';
            if (canSelectSignatoryOffice()) populateOfficeSelect(cfg.office || '');
            const targetOffice = (canSelectSignatoryOffice() && officeSelect && officeSelect.value)
                ? officeSelect.value
                : String(cfg.office || '').trim();
            if (printBtn) printBtn.disabled = true;
            fetchSignatoriesForOffice(targetOffice).then(function() {
                populateAllSignatorySelects();
                if (!hasPrintableSignatoryOptions()) {
                    if (typeof showNotify === 'function') {
                        const updatedCfg = getSigCfg();
                        const officeLabel = String((updatedCfg && updatedCfg.office) || targetOffice || '').trim();
                        const officeHint = officeLabel ? (' (office: ' + officeLabel + ')') : '';
                        showNotify(
                            'DV signatories are not configured for your office yet' + officeHint + '. A system administrator must set active DV signatories in Utilities for A. Certified, C. Accounting, and D. Approved. PENRO defaults are used when an office has no local entries.',
                            'warning',
                            5000
                        );
                    }
                    return;
                }
                if (modal) modal.style.display = 'block';
                if (overlay) overlay.style.display = 'block';
            }).catch(function(err) {
                if (typeof showNotify === 'function') {
                    showNotify(String(err && err.message ? err.message : 'Failed to load signatories. Please try again.'), 'warning', 3200);
                }
            }).finally(function() {
                if (printBtn) printBtn.disabled = false;
            });
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

        function readSignatoryFromSelect(selectEl) {
            return typeof readDvSignatoryFromSelect === 'function'
                ? readDvSignatoryFromSelect(selectEl)
                : { name: '', pos1: '', pos2: '' };
        }

        function proceedToPrint() {
            if (!validateSelections()) return;
            const selection = {
                cert: readSignatoryFromSelect(certSelect),
                accounting: readSignatoryFromSelect(accountingSelect),
                approved: readSignatoryFromSelect(approvedSelect),
            };
            if (typeof storeDvSignatories === 'function') storeDvSignatories(selection);
            if (typeof applyDvSignatories === 'function') applyDvSignatories(selection);
            closeSignatoryModal();
            window.requestAnimationFrame(function() {
                if (typeof applyDvSignatories === 'function') applyDvSignatories(selection);
                if (typeof window.prepareDvPrint === 'function') {
                    window.prepareDvPrint();
                }
                window.print();
            });
        }

        window.openSignatoryModal = openSignatoryModal;

        if (closeBtn) closeBtn.addEventListener('click', closeSignatoryModal);
        if (cancelBtn) cancelBtn.addEventListener('click', closeSignatoryModal);
        if (overlay) overlay.addEventListener('click', closeSignatoryModal);
        if (printBtn) printBtn.addEventListener('click', proceedToPrint);
        if (officeSelect) {
            officeSelect.addEventListener('change', function() {
                fetchSignatoriesForOffice(officeSelect.value).catch(function(err) {
                    if (typeof showNotify === 'function') {
                        showNotify(String(err && err.message ? err.message : 'Failed to load signatories for the selected office.'), 'warning', 3200);
                    }
                });
            });
        }
    })();
</script>
<?php
$forwardSlipJsPath = __DIR__ . '/../../protected/js/forward_slip.js';
$forwardSlipJsVer = is_file($forwardSlipJsPath) ? (int) filemtime($forwardSlipJsPath) : time();
?>
<script src="../../protected/js/forward_slip.js?v=<?= $forwardSlipJsVer ?>"></script>
</body>

</html>