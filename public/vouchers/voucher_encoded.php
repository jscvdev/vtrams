<?php
include('../includes/header.php');
require_once __DIR__ . '/../../protected/core/components/helpers/audit_helper.inc.php';
AuditHelper::logPageView('Encoded Vouchers');
include('../../protected/handler/voucher_module/voucher_errhandler.inc.php');
include('../../protected/core/components/notifications/err_handler_custom_alert.php');
require_once __DIR__ . '/../../protected/core/components/notifications/custom_alert.php';
require_once __DIR__ . '/../../protected/core/components/notifications/notification.inc.php';
require_once __DIR__ . '/checklist_config.php';
// Preload DV signatories from database for the printable disbursement voucher.
if (!function_exists('voucher_get_signatory')) {
    function voucher_get_signatory(PDO $pdo, string $key): array
    {
        $stmt = $pdo->prepare("
            SELECT display_name, position_line1, position_line2
            FROM voucher_signatories
            WHERE signatory_key = :k AND is_active = 1
            LIMIT 1
        ");
        $stmt->execute([':k' => $key]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
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
                        <input type="text" id="filterInput" placeholder="Search for payee, particulars, processing no., etc" autocomplete="off">
                    </div>
                </form>
            </div>
        </div>
    </div>
    <div class="overlay" id="overlay"></div>
    <div class="voucher-card voucher-card--table">
        <h2 class="voucher-card-title">Encoded Voucher Summary</h2>
        <style>
            /* Keep pagination fixed at the bottom of the table card (match voucher.php) */
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
                        <th>Print</th>
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
                    '<td data-label=""><button class="btn warning" name="btn-gen-slip" type="button">Print</button></td>';
                const printBtn = tr.querySelector('button[name="btn-gen-slip"]');
                if (printBtn) {
                    printBtn.addEventListener('click', function() {
                        const payload = buildPassItemPayload(row);
                        const pn = String(row.processing_no ?? '');
                        if (typeof passItem === 'function') passItem(payload, pn);
                        window.print();
                    });
                }
                frag.appendChild(tr);
            });
            tableBody.appendChild(frag);
            document.querySelectorAll('.amount').forEach(function(el) {
                const num = parseFloat(String(el.innerText).replace(/,/g, ''));
                if (isNaN(num)) return;
                el.innerText = new Intl.NumberFormat('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(num);
            });
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
        window.filterVoucherTable = function() {};
    })();
</script>

<script>
    // Expose logged user name to external scripts (safe JSON encoding)
    window.__loggedUserEmpName = <?php echo json_encode($_SESSION['logged_user_emp_name'] ?? $logged_user_name ?? ''); ?>;
</script>
</body>

</html>