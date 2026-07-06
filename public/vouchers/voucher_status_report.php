<?php
include '../includes/header.php';
require_once __DIR__ . '/../../protected/core/components/helpers/audit_helper.inc.php';
require_once __DIR__ . '/../../protected/core/components/notifications/notification.inc.php';
require_once __DIR__ . '/../../protected/core/components/security/filter_input.inc.php';
require_once __DIR__ . '/../../protected/core/components/helpers/amount_helper.inc.php';
require_once __DIR__ . '/../../protected/core/components/helpers/voucher_status_report_helper.inc.php';
require_once __DIR__ . '/checklist_config.php';
AuditHelper::logPageView('Status Report');

utilities_signatory_ensure_schema($pdo);
$report_offices = utilities_signatory_fetch_offices($pdo);
$scope = voucher_status_report_scope($pdo, trim((string) ($_SESSION['logged_user_office'] ?? '')));

$rawOffice = trim((string) ($_GET['office'] ?? 'all'));
$officeFilter = ($rawOffice === '' || strcasecmp($rawOffice, 'all') === 0) ? null : utilities_signatory_resolve_office($pdo, $rawOffice);

$entries = voucher_status_report_fetch_entries($pdo, $scope, $officeFilter);
$summary = voucher_status_report_summarize($entries);

$rawStatus = trim((string) ($_GET['status'] ?? 'all'));
$statusFilter = in_array(strtolower($rawStatus), ['all', 'processing', 'paid', 'returned'], true)
    ? strtolower($rawStatus)
    : 'all';
$entries = voucher_status_report_filter_by_status($entries, $statusFilter);
$summary = voucher_status_report_summarize($entries);

$report_voucher_types = checklist_types_with_labels();
$rawType = trim((string) ($_GET['voucher_type'] ?? 'all'));
$typeFilter = ($rawType === '' || strcasecmp($rawType, 'all') === 0) ? 'all' : $rawType;
if ($typeFilter !== 'all' && !isset($report_voucher_types[$typeFilter])) {
    $typeFilter = 'all';
}
$entries = voucher_status_report_filter_by_voucher_type($entries, $typeFilter);
$summary = voucher_status_report_summarize($entries);

$rawSearch = (string) ($_GET['q'] ?? '');
$searchTerm = strtolower(trim($rawSearch));
if ($searchTerm !== '') {
    $entries = array_values(array_filter($entries, static function (array $entry) use ($searchTerm): bool {
        $haystack = strtolower(implode(' ', [
            (string) ($entry['processing_no'] ?? ''),
            (string) ($entry['payee'] ?? ''),
            (string) ($entry['dv_no'] ?? ''),
            (string) ($entry['ors_no'] ?? ''),
            (string) ($entry['origin_office'] ?? ''),
            (string) ($entry['status_label'] ?? ''),
            (string) ($entry['category_label'] ?? ''),
        ]));

        return str_contains($haystack, $searchTerm);
    }));
    $summary = voucher_status_report_summarize($entries);
}

$entriesJson = json_encode($entries, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE);
$selectedOfficeValue = $officeFilter ?? 'all';
$printOfficeLabel = $selectedOfficeValue === 'all' ? 'All Offices' : (string) $selectedOfficeValue;
$printGeneratedAt = date('Y-m-d H:i:s');
$pageTitleHelperName = $header_text ?? 'Status Report';
?>
<div class="main main--voucher-dashboard" id="main">
    <header class="voucher-dashboard-header no-print">
        <h1 class="voucher-dashboard-title">Status Report</h1>
        <p style="color: rgb(75 85 99 / 0.9); margin: 0.25rem 0 0;">
            Voucher status overview for all transmitted vouchers for all offices / per office.
        </p>
    </header>

    <div class="status-report-print-header print-only">
        <div class="status-report-print-banner">
            <div>
                <p class="status-report-print-banner__eyebrow">Voucher Tracking</p>
                <h1><?php echo htmlspecialchars((string) $pageTitleHelperName, ENT_QUOTES, 'UTF-8'); ?></h1>
                <p class="status-report-print-banner__subtitle">Transmitted Voucher Status Report</p>
            </div>
            <div class="status-report-print-banner__meta">
                <div><span>Office</span><strong><?php echo htmlspecialchars($printOfficeLabel, ENT_QUOTES, 'UTF-8'); ?></strong></div>
                <div><span>Generated</span><strong><?php echo htmlspecialchars($printGeneratedAt, ENT_QUOTES, 'UTF-8'); ?></strong></div>
                <div><span>Rows</span><strong id="statusReportPrintRowCount"><?php echo count($entries); ?></strong></div>
            </div>
        </div>
        <?php if ($rawSearch !== '') : ?>
            <p class="status-report-print-search">Search filter: <?php echo htmlspecialchars($rawSearch, ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
        <?php if ($statusFilter !== 'all') : ?>
            <p class="status-report-print-search">Status filter: <?php echo htmlspecialchars(ucfirst($statusFilter), ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
        <?php if ($typeFilter !== 'all') : ?>
            <p class="status-report-print-search">Type filter: <?php echo htmlspecialchars((string) ($report_voucher_types[$typeFilter] ?? $typeFilter), ENT_QUOTES, 'UTF-8'); ?></p>
        <?php endif; ?>
        <div class="status-report-print-summary">
            <div class="status-report-print-summary__item"><span>Total</span><strong><?php echo (int) $summary['total']; ?></strong></div>
            <div class="status-report-print-summary__item status-report-print-summary__item--processing"><span>Processing</span><strong><?php echo (int) $summary['for_processing']; ?></strong></div>
            <div class="status-report-print-summary__item status-report-print-summary__item--returned"><span>Returned</span><strong><?php echo (int) $summary['returned']; ?></strong></div>
            <div class="status-report-print-summary__item status-report-print-summary__item--paid"><span>Paid</span><strong><?php echo (int) $summary['paid']; ?></strong></div>
        </div>
    </div>

    <div class="voucher-card status-report-stats-card no-print" style="margin-bottom: 16px;">
        <div class="status-report-stats">
            <div class="status-report-stat">
                <span class="status-report-stat__label">Total tracked</span>
                <strong class="status-report-stat__value"><?php echo (int) $summary['total']; ?></strong>
            </div>
            <div class="status-report-stat status-report-stat--processing">
                <span class="status-report-stat__label">Processing</span>
                <strong class="status-report-stat__value"><?php echo (int) $summary['for_processing']; ?></strong>
            </div>
            <div class="status-report-stat status-report-stat--returned">
                <span class="status-report-stat__label">Returned</span>
                <strong class="status-report-stat__value"><?php echo (int) $summary['returned']; ?></strong>
            </div>
            <div class="status-report-stat status-report-stat--paid">
                <span class="status-report-stat__label">Paid</span>
                <strong class="status-report-stat__value"><?php echo (int) $summary['paid']; ?></strong>
            </div>
        </div>
    </div>

    <section class="status-report-filter-bar no-print">
        <form method="GET" action="" class="status-report-filter-bar__form">
            <div class="status-report-filter-bar__field">
                <label for="officeFilter">Office</label>
                <select id="officeFilter" name="office">
                    <option value="all"<?php echo $selectedOfficeValue === 'all' ? ' selected' : ''; ?>>All Offices</option>
                    <?php foreach ($report_offices as $office_name) : ?>
                        <option value="<?php echo htmlspecialchars((string) $office_name, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $selectedOfficeValue !== 'all' && utilities_signatory_offices_match((string) $selectedOfficeValue, (string) $office_name) ? ' selected' : ''; ?>>
                            <?php echo htmlspecialchars((string) $office_name, ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="status-report-filter-bar__field">
                <label for="statusFilter">Status</label>
                <select id="statusFilter" name="status">
                    <option value="all"<?php echo $statusFilter === 'all' ? ' selected' : ''; ?>>All Statuses</option>
                    <option value="processing"<?php echo $statusFilter === 'processing' ? ' selected' : ''; ?>>Processing</option>
                    <option value="returned"<?php echo $statusFilter === 'returned' ? ' selected' : ''; ?>>Returned</option>
                    <option value="paid"<?php echo $statusFilter === 'paid' ? ' selected' : ''; ?>>Paid</option>
                </select>
            </div>
            <div class="status-report-filter-bar__field">
                <label for="typeFilter">Type</label>
                <select id="typeFilter" name="voucher_type">
                    <option value="all"<?php echo $typeFilter === 'all' ? ' selected' : ''; ?>>All Types</option>
                    <?php foreach ($report_voucher_types as $type_value => $type_label) : ?>
                        <option value="<?php echo htmlspecialchars((string) $type_value, ENT_QUOTES, 'UTF-8'); ?>"<?php echo $typeFilter === (string) $type_value ? ' selected' : ''; ?>>
                            <?php echo htmlspecialchars((string) $type_label, ENT_QUOTES, 'UTF-8'); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="status-report-filter-bar__field status-report-filter-bar__field--grow">
                <label for="statusReportSearch">Search</label>
                <input type="text" id="statusReportSearch" name="q" value="<?php echo htmlspecialchars($rawSearch, ENT_QUOTES, 'UTF-8'); ?>" placeholder="Processing no., payee, office, status…" autocomplete="off">
            </div>
            <button type="submit" class="status-report-filter-bar__apply">Apply Filters</button>
            <div class="status-report-filter-bar__field status-report-filter-bar__field--print-limit">
                <label for="statusReportPrintLimitMode">Print rows</label>
                <div class="status-report-print-limit-controls">
                    <select id="statusReportPrintLimitMode" aria-label="Print row limit mode">
                        <option value="all">All (max 2000)</option>
                        <option value="custom">Custom amount</option>
                    </select>
                    <input type="number" id="statusReportPrintLimitCustom" min="1" max="2000" value="100" aria-label="Custom print row count" title="Enter how many rows to print (1–2000) when using custom amount">
                </div>
            </div>
            <button type="button" class="status-report-filter-bar__print" id="statusReportPrintBtn">Print Report</button>
        </form>
    </section>

    <div class="voucher-card voucher-card--table status-report-table-card">
        <div class="status-report-table-head no-print">
            <h2 class="voucher-card-title" style="margin:0;">Transmitted Vouchers</h2>
            <p class="status-report-table-meta" id="statusReportRowMeta"><?php echo count($entries); ?> voucher<?php echo count($entries) === 1 ? '' : 's'; ?> shown</p>
        </div>
        <style>
            .status-report-stats {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(150px, 1fr));
                gap: 12px;
                padding: 4px 0;
            }

            .status-report-stat {
                border: 1px solid #e5e7eb;
                border-radius: 12px;
                padding: 12px 14px;
                background: #fff;
            }

            .status-report-stat__label {
                display: block;
                font-size: 12px;
                color: #6b7280;
                margin-bottom: 4px;
            }

            .status-report-stat__value {
                font-size: 24px;
                color: #111827;
            }

            .status-report-stat--processing {
                background: linear-gradient(180deg, #fffbeb 0%, #fff 100%);
            }

            .status-report-stat--returned {
                background: linear-gradient(180deg, #fef2f2 0%, #fff 100%);
            }

            .status-report-stat--paid {
                background: linear-gradient(180deg, #ecfdf5 0%, #fff 100%);
            }

            .status-report-filter-bar {
                display: flex;
                flex-wrap: wrap;
                gap: 15px;
                align-items: flex-end;
                background-color: #fff;
                padding: 16px 20px;
                border-radius: 8px;
                box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
                margin-bottom: 20px;
                color: rgb(75 85 99 / 0.9);
            }

            .status-report-filter-bar__form {
                width: 100%;
                display: flex;
                flex-wrap: wrap;
                gap: 15px;
                align-items: flex-end;
            }

            .status-report-filter-bar__field {
                display: flex;
                flex-direction: column;
                gap: 6px;
                min-width: 180px;
            }

            .status-report-filter-bar__field--grow {
                flex: 1;
                min-width: 240px;
            }

            .status-report-filter-bar__field label {
                font-size: 14px;
                font-weight: 500;
            }

            .status-report-filter-bar__field select,
            .status-report-filter-bar__field input[type="text"] {
                padding: 8px 10px;
                border-radius: 5px;
                border: 1px solid rgb(209 213 219 / 1);
                font-size: 14px;
                min-height: 38px;
            }

            .status-report-filter-bar__field--print-limit {
                min-width: 220px;
            }

            .status-report-print-limit-controls {
                display: flex;
                gap: 8px;
                align-items: center;
            }

            .status-report-print-limit-controls select,
            .status-report-print-limit-controls input[type="number"] {
                padding: 8px 10px;
                border-radius: 5px;
                border: 1px solid rgb(209 213 219 / 1);
                font-size: 14px;
                min-height: 38px;
            }

            .status-report-print-limit-controls input[type="number"] {
                width: 88px;
            }

            .status-report-print-limit-controls input[type="number"]:disabled {
                opacity: 0.55;
                background: #f3f4f6;
            }

            .status-report-select-bar {
                display: flex;
                align-items: center;
                flex-wrap: wrap;
                gap: 12px;
                margin-bottom: 12px;
                padding: 10px 12px;
                border: 1px solid #e5e7eb;
                border-radius: 10px;
                background: #f8fafc;
            }

            .status-report-select-bar label {
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

            .status-report-select-bar label input[type="checkbox"] {
                width: 16px;
                height: 16px;
                cursor: pointer;
                accent-color: #2563eb;
            }

            .status-report-select-status {
                font-size: 12px;
                color: #6b7280;
                font-weight: 500;
            }

            .status-report-select-cell {
                width: 42px;
                text-align: center;
            }

            .status-report-select-cell input[type="checkbox"] {
                width: 16px;
                height: 16px;
                cursor: pointer;
                accent-color: #2563eb;
            }

            .status-report-filter-bar__apply,
            .status-report-filter-bar__print {
                border: none;
                border-radius: 5px;
                padding: 8px 16px;
                height: 38px;
                font-size: 14px;
                cursor: pointer;
            }

            .status-report-filter-bar__apply {
                background-color: #0d6efd;
                color: #fff;
            }

            .status-report-filter-bar__print {
                background-color: #fff;
                color: rgb(75 85 99 / 0.9);
                border: 1px solid rgb(209 213 219 / 1);
            }

            .status-report-table-head {
                display: flex;
                align-items: baseline;
                justify-content: space-between;
                gap: 12px;
                margin-bottom: 12px;
            }

            .status-report-table-meta {
                margin: 0;
                font-size: 12px;
                color: rgb(75 85 99 / 0.75);
            }

            .status-report-print-header {
                display: none;
                margin-bottom: 18px;
            }

            .status-report-print-banner {
                display: flex;
                justify-content: space-between;
                gap: 20px;
                padding: 18px 20px;
                border: 2px solid #111827;
                border-radius: 10px;
                background: #fff;
            }

            .status-report-print-banner__eyebrow {
                margin: 0 0 4px;
                font-size: 11px;
                letter-spacing: 0.12em;
                text-transform: uppercase;
                color: #6b7280;
            }

            .status-report-print-banner h1 {
                margin: 0;
                font-size: 22px;
                line-height: 1.2;
                color: #111827;
            }

            .status-report-print-banner__subtitle {
                margin: 6px 0 0;
                font-size: 13px;
                color: #4b5563;
            }

            .status-report-print-banner__meta {
                display: grid;
                gap: 8px;
                min-width: 210px;
            }

            .status-report-print-banner__meta div {
                display: flex;
                justify-content: space-between;
                gap: 12px;
                font-size: 12px;
                border-bottom: 1px solid #e5e7eb;
                padding-bottom: 4px;
            }

            .status-report-print-banner__meta span {
                color: #6b7280;
            }

            .status-report-print-banner__meta strong {
                color: #111827;
                font-weight: 700;
            }

            .status-report-print-search {
                margin: 10px 0 0;
                font-size: 12px;
                color: #374151;
            }

            .status-report-print-summary {
                display: grid;
                grid-template-columns: repeat(4, minmax(0, 1fr));
                gap: 10px;
                margin-top: 14px;
            }

            .status-report-print-summary__item {
                border: 1px solid #d1d5db;
                border-radius: 8px;
                padding: 10px 12px;
                background: #f9fafb;
            }

            .status-report-print-summary__item span {
                display: block;
                font-size: 11px;
                color: #6b7280;
                margin-bottom: 4px;
            }

            .status-report-print-summary__item strong {
                font-size: 20px;
                color: #111827;
            }

            .status-report-print-summary__item--processing {
                background: #fffbeb;
            }

            .status-report-print-summary__item--returned {
                background: #fef2f2;
            }

            .status-report-print-summary__item--paid {
                background: #ecfdf5;
            }

            .status-report-print-table-title {
                display: none;
                margin: 0 0 10px;
                font-size: 15px;
                font-weight: 700;
                color: #111827;
            }

            .status-report-print-footer {
                display: none;
                margin-top: 12px;
                font-size: 11px;
                color: #6b7280;
                text-align: right;
            }

            .print-only {
                display: none;
            }

            .status-report-row {
                cursor: pointer;
                transition: background 120ms ease;
            }

            .status-report-row:hover {
                background: #f8fafc;
            }

            .status-pill {
                display: inline-flex;
                align-items: center;
                padding: 4px 10px;
                border-radius: 999px;
                font-size: 12px;
                font-weight: 600;
            }

            .status-pill--processing {
                background: #fef3c7;
                color: #92400e;
            }

            .status-pill--returned {
                background: #fee2e2;
                color: #991b1b;
            }

            .status-pill--paid {
                background: #d1fae5;
                color: #065f46;
            }

            #statusReportModal .popupForm-box__container {
                max-width: 920px;
                border-radius: 14px;
            }

            #statusReportModal .status-breakdown-hero {
                display: flex;
                flex-wrap: wrap;
                justify-content: space-between;
                gap: 12px;
                padding: 16px;
                border-radius: 14px;
                background: linear-gradient(135deg, #f8fafc 0%, #eef2ff 100%);
                border: 1px solid #e5e7eb;
                margin-bottom: 14px;
            }

            #statusReportModal .status-breakdown-hero__main {
                min-width: 220px;
            }

            #statusReportModal .status-breakdown-hero__title {
                margin: 0 0 4px;
                font-size: 20px;
                font-weight: 700;
                color: #111827;
            }

            #statusReportModal .status-breakdown-hero__subtitle {
                margin: 0;
                font-size: 13px;
                color: #4b5563;
            }

            #statusReportModal .status-breakdown-hero__subtitle + .status-breakdown-hero__subtitle {
                margin-top: 4px;
            }

            #statusReportModal .status-breakdown-grid {
                display: grid;
                grid-template-columns: repeat(3, minmax(0, 1fr));
                gap: 12px;
            }

            #statusReportModal .status-breakdown-card {
                border: 1px solid #e5e7eb;
                border-radius: 12px;
                padding: 12px;
                background: #fff;
            }

            #statusReportModal .status-breakdown-card--full {
                grid-column: 1 / -1;
            }

            #statusReportModal .status-breakdown-title {
                margin: 0 0 8px;
                font-size: 11px;
                font-weight: 700;
                text-transform: uppercase;
                letter-spacing: 0.06em;
                color: #6b7280;
            }

            #statusReportModal .status-breakdown-content {
                font-size: 13px;
                line-height: 1.45;
                color: #111827;
                white-space: pre-wrap;
            }

            #statusReportModal .status-history-list {
                list-style: none;
                margin: 0;
                padding: 0;
                display: flex;
                flex-direction: column;
                gap: 8px;
                max-height: 280px;
                overflow: auto;
            }

            #statusReportModal .status-history-item {
                padding: 10px 12px;
                border: 1px solid #e5e7eb;
                border-radius: 10px;
                background: #fafafa;
                font-size: 13px;
            }

            .status-report-table-wrap {
                overflow: auto;
                max-height: 70vh;
            }

            @media (max-width: 840px) {
                #statusReportModal .status-breakdown-grid {
                    grid-template-columns: 1fr;
                }
            }

            @media print {
                @page {
                    size: A4 landscape;
                    margin: 12mm;
                }

                html,
                body {
                    height: auto !important;
                    overflow: visible !important;
                    position: static !important;
                }

                .sidebar,
                .header,
                .no-print,
                #statusReportModal,
                #statusReportOverlay {
                    display: none !important;
                }

                .print-only,
                .status-report-print-header,
                .status-report-print-table-title,
                .status-report-print-footer {
                    display: block !important;
                }

                .status-report-print-summary {
                    display: grid !important;
                }

                .main,
                .main--voucher-dashboard,
                .main--dashboard,
                .voucher-card,
                .status-report-table-card,
                .voucher-card--table {
                    position: static !important;
                    width: 100% !important;
                    height: auto !important;
                    max-height: none !important;
                    min-height: 0 !important;
                    margin: 0 !important;
                    padding: 0 !important;
                    overflow: visible !important;
                    flex: none !important;
                    box-shadow: none !important;
                    border: none !important;
                    background: #fff !important;
                }

                body {
                    background: #fff !important;
                    color: #111827 !important;
                    font-family: "Segoe UI", Arial, sans-serif !important;
                }

                .content-wrapper,
                .status-report-table-wrap,
                .content_table,
                .content_table--dashboard {
                    position: static !important;
                    height: auto !important;
                    max-height: none !important;
                    min-height: 0 !important;
                    overflow: visible !important;
                    animation: none !important;
                }

                #statusReportTable {
                    display: table !important;
                    width: 100%;
                    border-collapse: collapse;
                    font-size: 11px;
                    border: none;
                    overflow: visible !important;
                    page-break-inside: auto;
                    box-shadow: none !important;
                }

                #statusReportTable thead {
                    display: table-header-group;
                }

                #statusReportTable tbody {
                    display: table-row-group;
                }

                #statusReportTable tr {
                    display: table-row !important;
                    page-break-inside: avoid;
                    break-inside: avoid-page;
                }

                #statusReportTable th,
                #statusReportTable td {
                    display: table-cell !important;
                    overflow: visible !important;
                    position: static !important;
                }

                .table thead tr,
                .table th {
                    position: static !important;
                }

                #statusReportTable th {
                    background: transparent !important;
                    color: #111827 !important;
                    border: none;
                    border-bottom: 2px solid #111827;
                    padding: 8px 10px;
                    text-align: left;
                    font-weight: 700;
                }

                #statusReportTable td {
                    border: none;
                    border-bottom: 1px solid #e5e7eb;
                    padding: 7px 10px;
                    vertical-align: top;
                }

                #statusReportTable tbody tr:nth-child(even) td {
                    background: transparent !important;
                }

                #statusReportTable .status-report-select-cell,
                #statusReportTable .status-report-select-head {
                    display: none !important;
                }

                #statusReportTable tr.status-report-print-skip {
                    display: none !important;
                }

                .status-report-row {
                    cursor: default;
                }

                .status-pill {
                    border: none;
                    border-radius: 4px;
                    padding: 2px 8px;
                    font-size: 10px;
                    font-weight: 700;
                    background: transparent !important;
                    color: #111827 !important;
                }

                .status-pill--processing {
                    background: transparent !important;
                }

                .status-pill--returned {
                    background: transparent !important;
                }

                .status-pill--paid {
                    background: transparent !important;
                }
            }
        </style>

        <div class="status-report-select-bar no-print" id="statusReportSelectBar">
            <label>
                <input type="checkbox" id="statusReportSelectAll" aria-label="Select all vouchers on this page">
                Select all on page
            </label>
            <span class="status-report-select-status" id="statusReportSelectStatus"></span>
        </div>

        <div class="content-wrapper status-report-table-wrap">
            <h3 class="status-report-print-table-title print-only">Voucher Listing</h3>
            <table class="table content_table content_table--dashboard" id="statusReportTable">
                <thead>
                    <tr>
                        <th class="status-report-select-cell status-report-select-head no-print" aria-label="Select for print"></th>
                        <th>Processing No.</th>
                        <th>Payee</th>
                        <th>Amount</th>
                        <th>Origin Office</th>
                        <th>Status</th>
                        <th>Last Update</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if ($entries === []) : ?>
                        <tr>
                            <td colspan="7" style="text-align:center; color:#6b7280; padding:28px 12px;">No Transmitted vouchers match this report yet.</td>
                        </tr>
                    <?php else : ?>
                        <?php foreach ($entries as $index => $entry) : ?>
                            <tr class="status-report-row" data-entry-index="<?php echo (int) $index; ?>" data-processing-no="<?php echo htmlspecialchars((string) $entry['processing_no'], ENT_QUOTES, 'UTF-8'); ?>" tabindex="0" role="button" aria-label="View status breakdown for <?php echo htmlspecialchars((string) $entry['processing_no'], ENT_QUOTES, 'UTF-8'); ?>">
                                <td class="status-report-select-cell no-print" data-label="">
                                    <input type="checkbox" class="status-report-row-select" value="<?php echo htmlspecialchars((string) $entry['processing_no'], ENT_QUOTES, 'UTF-8'); ?>" aria-label="Select voucher <?php echo htmlspecialchars((string) $entry['processing_no'], ENT_QUOTES, 'UTF-8'); ?>">
                                </td>
                                <td><?php echo htmlspecialchars((string) $entry['processing_no'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) $entry['payee'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars(format_amount_display((string) $entry['amount']), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars((string) ($entry['origin_office'] ?: $entry['office_from'] ?: '—'), ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <?php if (!empty($entry['is_paid'])) : ?>
                                        <span class="status-pill status-pill--paid">Paid</span>
                                    <?php elseif (!empty($entry['is_returned'])) : ?>
                                        <span class="status-pill status-pill--returned">Returned</span>
                                    <?php else : ?>
                                        <span class="status-pill status-pill--processing">Processing</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars((string) ($entry['datetime_status'] ?: $entry['datetime_encoded']), ENT_QUOTES, 'UTF-8'); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
            <p class="status-report-print-footer print-only" id="statusReportPrintFooter">End of report · <?php echo count($entries); ?> record<?php echo count($entries) === 1 ? '' : 's'; ?> · <?php echo htmlspecialchars($printGeneratedAt, ENT_QUOTES, 'UTF-8'); ?></p>
        </div>
    </div>
</div>

<div class="popup-form" id="statusReportModal" style="display:none;">
    <div class="popupForm-box__container">
        <div class="popupForm-header__container">
            <p id="statusReportModalTitle">Status Breakdown</p>
            <i class="ri-close-fill close-icon" id="close_status_report_modal"></i>
        </div>
        <div class="f-container">
            <div class="box-body__container">
                <div class="status-breakdown-hero">
                    <div class="status-breakdown-hero__main">
                        <p class="status-breakdown-hero__title" id="sr_processing_no"></p>
                        <p class="status-breakdown-hero__subtitle" id="sr_voucher_type"></p>
                        <p class="status-breakdown-hero__subtitle" id="sr_payee"></p>
                    </div>
                    <div id="sr_status_pill"></div>
                </div>
                <div class="status-breakdown-grid">
                    <div class="status-breakdown-card">
                        <p class="status-breakdown-title">Origin Office</p>
                        <div class="status-breakdown-content" id="sr_origin"></div>
                    </div>
                    <div class="status-breakdown-card">
                        <p class="status-breakdown-title">Amount</p>
                        <div class="status-breakdown-content" id="sr_amount"></div>
                    </div>
                    <div class="status-breakdown-card">
                        <p class="status-breakdown-title">Route Type</p>
                        <div class="status-breakdown-content" id="sr_route_type"></div>
                    </div>
                    <div class="status-breakdown-card status-breakdown-card--full">
                        <p class="status-breakdown-title">Latest Action</p>
                        <div class="status-breakdown-content" id="sr_latest"></div>
                    </div>
                    <div class="status-breakdown-card status-breakdown-card--full" id="sr_latest_remarks_card" style="display:none;">
                        <p class="status-breakdown-title">Latest Remarks</p>
                        <div class="status-breakdown-content" id="sr_latest_remarks"></div>
                    </div>
                    <div class="status-breakdown-card status-breakdown-card--full">
                        <p class="status-breakdown-title">Complete Process History</p>
                        <ul class="status-history-list" id="sr_history"></ul>
                    </div>
                </div>
            </div>
            <div class="popupForm-footer__container">
                <div class="footer-button__container">
                    <button class="btn secondary transparent" id="close_status_report_modal_btn" type="button">Close</button>
                </div>
            </div>
        </div>
    </div>
</div>
<div class="overlay" id="statusReportOverlay" style="display:none;"></div>

<script>
    (function() {
        const entries = <?php echo $entriesJson ?: '[]'; ?>;
        const voucherTypeLabels = <?php echo json_encode($report_voucher_types, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE); ?>;
        const modal = document.getElementById('statusReportModal');
        const overlay = document.getElementById('statusReportOverlay');

        function escapeHtml(value) {
            if (value == null) return '';
            const div = document.createElement('div');
            div.textContent = String(value);
            return div.innerHTML;
        }

        function renderStatusPill(entry) {
            if (entry && entry.is_paid) {
                return '<span class="status-pill status-pill--paid">Paid</span>';
            }
            if (entry && entry.is_returned) {
                return '<span class="status-pill status-pill--returned">Returned</span>';
            }
            return '<span class="status-pill status-pill--processing">Processing</span>';
        }

        const PRINT_ROW_MAX = 2000;

        function getPrintRowLimit() {
            const modeEl = document.getElementById('statusReportPrintLimitMode');
            const customEl = document.getElementById('statusReportPrintLimitCustom');
            const mode = modeEl ? String(modeEl.value || 'all') : 'all';
            if (mode === 'custom' && customEl) {
                const parsed = parseInt(String(customEl.value || '100'), 10);
                const safe = Math.max(1, Number.isFinite(parsed) ? parsed : 100);
                return Math.min(PRINT_ROW_MAX, safe);
            }
            return PRINT_ROW_MAX;
        }

        function getSelectedProcessingNos() {
            return Array.from(document.querySelectorAll('#statusReportTable input.status-report-row-select:checked'))
                .map(function(cb) { return String(cb.value || '').trim(); })
                .filter(function(pn) { return pn !== ''; });
        }

        function getVisibleDataRows() {
            return Array.from(document.querySelectorAll('#statusReportTable tbody tr.status-report-row'));
        }

        function syncSelectAllState() {
            const selectAllEl = document.getElementById('statusReportSelectAll');
            if (!selectAllEl) return;
            const boxes = Array.from(document.querySelectorAll('#statusReportTable input.status-report-row-select'));
            if (boxes.length === 0) {
                selectAllEl.checked = false;
                selectAllEl.indeterminate = false;
                return;
            }
            const checkedCount = boxes.filter(function(cb) { return cb.checked; }).length;
            selectAllEl.checked = checkedCount === boxes.length;
            selectAllEl.indeterminate = checkedCount > 0 && checkedCount < boxes.length;
        }

        function syncSelectStatusText() {
            const statusEl = document.getElementById('statusReportSelectStatus');
            if (!statusEl) return;
            const selected = getSelectedProcessingNos();
            if (selected.length === 0) {
                statusEl.textContent = 'No rows selected — print will include up to the row limit from the filtered list.';
                return;
            }
            statusEl.textContent = selected.length + ' row' + (selected.length === 1 ? '' : 's') + ' selected for print.';
        }

        function clearPrintSkipMarks() {
            document.querySelectorAll('#statusReportTable tr.status-report-print-skip').forEach(function(row) {
                row.classList.remove('status-report-print-skip');
            });
        }

        function preparePrintRows() {
            const limit = getPrintRowLimit();
            const selected = getSelectedProcessingNos();
            const selectedSet = new Set(selected);
            const rows = getVisibleDataRows();
            let included = 0;

            rows.forEach(function(row) {
                const processingNo = String(row.getAttribute('data-processing-no') || '').trim();
                let shouldInclude = false;

                if (selected.length > 0) {
                    shouldInclude = selectedSet.has(processingNo) && included < limit;
                } else {
                    shouldInclude = included < limit;
                }

                row.classList.toggle('status-report-print-skip', !shouldInclude);
                if (shouldInclude) {
                    included++;
                }
            });

            const printCountEl = document.getElementById('statusReportPrintRowCount');
            const printFooterEl = document.getElementById('statusReportPrintFooter');
            if (printCountEl) {
                printCountEl.textContent = String(included);
            }
            if (printFooterEl) {
                printFooterEl.textContent = 'End of report · ' + included + ' record' + (included === 1 ? '' : 's') + ' · <?php echo htmlspecialchars($printGeneratedAt, ENT_QUOTES, 'UTF-8'); ?>';
            }

            return included;
        }

        function syncPrintLimitControls() {
            const modeEl = document.getElementById('statusReportPrintLimitMode');
            const customEl = document.getElementById('statusReportPrintLimitCustom');
            if (!modeEl || !customEl) return;
            const isCustom = String(modeEl.value || '') === 'custom';
            customEl.disabled = !isCustom;
        }

        function renderHistoryList(raw) {
            const normalized = String(raw || '')
                .replace(/\r\n/g, '\n')
                .replace(/\r/g, '\n')
                .replace(/\\n/g, '\n')
                .trim();
            if (!normalized) {
                return '<li class="status-history-item">No process history recorded.</li>';
            }
            return normalized.split('\n').filter(function(line) {
                return String(line).trim() !== '';
            }).map(function(line) {
                return '<li class="status-history-item">' + escapeHtml(line.trim()) + '</li>';
            }).join('');
        }

        function resolveVoucherTypeLabel(typeKey) {
            const key = String(typeKey || '').trim();
            if (!key) return '—';
            return voucherTypeLabels[key] || key;
        }

        function openBreakdown(entry) {
            if (!entry || !modal || !overlay) return;
            document.getElementById('statusReportModalTitle').textContent = 'Status Breakdown';
            document.getElementById('sr_processing_no').textContent = entry.processing_no || '—';
            document.getElementById('sr_voucher_type').textContent = resolveVoucherTypeLabel(entry.voucher_type);
            document.getElementById('sr_payee').textContent = entry.payee || '—';
            document.getElementById('sr_status_pill').innerHTML = renderStatusPill(entry);
            document.getElementById('sr_origin').textContent = entry.origin_office || entry.office_from || '—';
            document.getElementById('sr_amount').textContent = entry.amount || '—';
            document.getElementById('sr_route_type').textContent = entry.category_label || '—';
            document.getElementById('sr_latest').textContent = (entry.voucher_status || '—') + (entry.datetime_status ? (' · ' + entry.datetime_status) : '');
            const remarksCard = document.getElementById('sr_latest_remarks_card');
            const remarksEl = document.getElementById('sr_latest_remarks');
            const remarks = String(entry.remarks || '').trim();
            if (remarksCard && remarksEl) {
                if (remarks !== '') {
                    remarksEl.textContent = remarks;
                    remarksCard.style.display = '';
                } else {
                    remarksEl.textContent = '';
                    remarksCard.style.display = 'none';
                }
            }
            document.getElementById('sr_history').innerHTML = renderHistoryList(entry.process_history || '');
            modal.style.display = 'block';
            overlay.style.display = 'block';
        }

        function closeBreakdown() {
            if (modal) modal.style.display = 'none';
            if (overlay) overlay.style.display = 'none';
        }

        document.querySelectorAll('.status-report-row').forEach(function(row) {
            row.addEventListener('click', function(e) {
                if (e.target && e.target.closest('.status-report-select-cell')) {
                    return;
                }
                const index = Number(row.getAttribute('data-entry-index'));
                openBreakdown(entries[index]);
            });
            row.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    if (e.target && e.target.closest('.status-report-select-cell')) {
                        return;
                    }
                    e.preventDefault();
                    const index = Number(row.getAttribute('data-entry-index'));
                    openBreakdown(entries[index]);
                }
            });
        });

        document.querySelectorAll('#statusReportTable input.status-report-row-select').forEach(function(cb) {
            cb.addEventListener('click', function(e) {
                e.stopPropagation();
            });
            cb.addEventListener('change', function() {
                syncSelectAllState();
                syncSelectStatusText();
            });
        });

        const selectAllEl = document.getElementById('statusReportSelectAll');
        if (selectAllEl) {
            selectAllEl.addEventListener('change', function() {
                const checked = !!selectAllEl.checked;
                document.querySelectorAll('#statusReportTable input.status-report-row-select').forEach(function(cb) {
                    cb.checked = checked;
                });
                syncSelectAllState();
                syncSelectStatusText();
            });
        }

        const printLimitModeEl = document.getElementById('statusReportPrintLimitMode');
        const printLimitCustomEl = document.getElementById('statusReportPrintLimitCustom');
        if (printLimitModeEl) {
            printLimitModeEl.addEventListener('change', syncPrintLimitControls);
        }
        if (printLimitCustomEl) {
            printLimitCustomEl.addEventListener('input', function() {
                const parsed = parseInt(String(printLimitCustomEl.value || '100'), 10);
                if (!Number.isFinite(parsed)) {
                    return;
                }
                printLimitCustomEl.value = String(Math.min(PRINT_ROW_MAX, Math.max(1, parsed)));
            });
        }
        syncPrintLimitControls();
        syncSelectAllState();
        syncSelectStatusText();

        ['close_status_report_modal', 'close_status_report_modal_btn'].forEach(function(id) {
            const el = document.getElementById(id);
            if (el) el.addEventListener('click', closeBreakdown);
        });
        if (overlay) overlay.addEventListener('click', closeBreakdown);

        const printBtn = document.getElementById('statusReportPrintBtn');
        if (printBtn) {
            printBtn.addEventListener('click', function() {
                const included = preparePrintRows();
                if (included === 0) {
                    alert('No vouchers to print. Adjust your filters or row selection.');
                    clearPrintSkipMarks();
                    return;
                }
                window.print();
            });
        }

        window.addEventListener('afterprint', function() {
            clearPrintSkipMarks();
            const printCountEl = document.getElementById('statusReportPrintRowCount');
            const printFooterEl = document.getElementById('statusReportPrintFooter');
            const totalRows = getVisibleDataRows().length;
            if (printCountEl) {
                printCountEl.textContent = String(totalRows);
            }
            if (printFooterEl) {
                printFooterEl.textContent = 'End of report · ' + totalRows + ' record' + (totalRows === 1 ? '' : 's') + ' · <?php echo htmlspecialchars($printGeneratedAt, ENT_QUOTES, 'UTF-8'); ?>';
            }
        });
    })();
</script>
<script src="../../protected/js/main.js"></script>
<?php require_once __DIR__ . '/../../protected/core/components/notifications/notification_flash.inc.php'; ?>
</body>
</html>
