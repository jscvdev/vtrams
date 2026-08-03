<?php
if (isset($_GET['fetch']) && $_GET['fetch'] === 'voucher_tracking') {
    require __DIR__ . '/../../protected/core/components/security/err_blocker.inc.php';
    require __DIR__ . '/../../protected/dbconnection.inc.php';
    require __DIR__ . '/../../protected/core/components/security/config_session.inc.php';
    require __DIR__ . '/../../protected/core/components/security/router.inc.php';
    require_once __DIR__ . '/../../protected/core/components/security/access_control.inc.php';

    if (!AccessControl::canAccessOverviewReports()) {
        header('Content-Type: application/json; charset=UTF-8');
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }

    require_once __DIR__ . '/../../protected/core/components/helpers/voucher_tracking_helper.inc.php';
    require_once __DIR__ . '/../../protected/core/components/helpers/utilities_signatory_helper.inc.php';

    $voucher_type = isset($_GET['voucher_type']) && $_GET['voucher_type'] !== 'all' ? trim((string) $_GET['voucher_type']) : null;
    $office = isset($_GET['office']) && $_GET['office'] !== 'all' ? trim((string) $_GET['office']) : null;
    $month = isset($_GET['month']) && $_GET['month'] !== 'all' ? (int) $_GET['month'] : null;
    $day = isset($_GET['day']) && $_GET['day'] !== 'all' ? (int) $_GET['day'] : null;
    $yearDate = isset($_GET['yearDate']) && $_GET['yearDate'] !== 'all' ? (int) $_GET['yearDate'] : null;
    $search = trim((string) ($_GET['search'] ?? ''));
    $calculationMode = isset($_GET['calculation']) && (string) $_GET['calculation'] === '1';

    if ($calculationMode && !AccessControl::canAccessCalculationBreakdown()) {
        header('Content-Type: application/json; charset=UTF-8');
        http_response_code(403);
        echo json_encode(['error' => 'Access denied']);
        exit;
    }

    try {
        $params = [];
        $searchActive = $search !== '';

        if ($calculationMode && $searchActive) {
            // Full-database search for calculation breakdown (ignores type/office/date filters).
            $query = 'SELECT vt.* FROM voucher_tracking vt WHERE 1=1' . voucher_tracking_counts_include_sql('vt');
            $query .= voucher_tracking_dashboard_search_sql('vt');
            voucher_tracking_dashboard_bind_search_params($params, $search);
            $query .= ' ORDER BY COALESCE(NULLIF(vt.datetime_status, \'\'), vt.voucher_date, vt.datetime_encoded) DESC';
        } else {
            $query = 'SELECT vt.* FROM voucher_tracking vt WHERE 1=1' . voucher_tracking_counts_include_sql('vt');

            if ($voucher_type !== null && $voucher_type !== '') {
                $query .= ' AND vt.voucher_type = :voucher_type';
                $params[':voucher_type'] = $voucher_type;
            }

            if ($office !== null && $office !== '') {
                $query .= ' AND LOWER(TRIM(vt.office_from)) = LOWER(TRIM(:office))';
                $params[':office'] = $office;
            }

            if ($searchActive) {
                $query .= voucher_tracking_dashboard_search_sql('vt');
                voucher_tracking_dashboard_bind_search_params($params, $search);
            }

            if ($month !== null && $day !== null && $yearDate !== null) {
                $query .= ' AND DATE(vt.voucher_date) = :date_filter';
                $params[':date_filter'] = sprintf('%04d-%02d-%02d', $yearDate, $month, $day);
            } elseif ($month !== null && $yearDate !== null) {
                $query .= ' AND MONTH(vt.voucher_date) = :month AND YEAR(vt.voucher_date) = :yearDate';
                $params[':month'] = $month;
                $params[':yearDate'] = $yearDate;
            } elseif ($yearDate !== null) {
                $query .= ' AND YEAR(vt.voucher_date) = :yearDate';
                $params[':yearDate'] = $yearDate;
            }

            $query .= ' ORDER BY vt.voucher_date DESC';
        }

        $stmt = $pdo->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $breakdownLimit = null;
        if ($calculationMode) {
            $breakdownLimit = $searchActive
                ? min(count($rows), voucher_tracking_dashboard_calculation_search_limit())
                : voucher_tracking_dashboard_calculation_recent_limit();
        }
        $sectionTiming = voucher_tracking_build_section_timing_report(
            $pdo,
            $rows,
            voucher_tracking_dashboard_breakdown_sections(),
            $breakdownLimit
        );
        header('Content-Type: application/json; charset=UTF-8');
        echo json_encode([
            'rows' => $rows,
            'section_timing' => $sectionTiming,
        ], JSON_UNESCAPED_UNICODE);
    } catch (Throwable $e) {
        header('Content-Type: application/json; charset=UTF-8');
        http_response_code(500);
        echo json_encode(['error' => 'Server error', 'detail' => $e->getMessage()]);
    }
    exit;
}

include('../includes/header.php');
if (!AccessControl::canAccessOverviewReports()) {
    header('Location: ../documents/index.php');
    die();
}
require_once __DIR__ . '/../../protected/core/components/helpers/audit_helper.inc.php';
require_once __DIR__ . '/../../protected/core/components/helpers/voucher_tracking_helper.inc.php';
require_once __DIR__ . '/../../protected/core/components/helpers/utilities_signatory_helper.inc.php';
AuditHelper::logPageView('Dashboard');
require_once __DIR__ . '/checklist_config.php';
utilities_signatory_ensure_schema($pdo);
$dashboard_offices = utilities_signatory_fetch_offices($pdo);
$dashboard_breakdown_sections = voucher_tracking_dashboard_breakdown_sections();
$dashboard_timing_section_labels = array_map(
    static fn(string $section): string => voucher_tracking_dashboard_section_label($section),
    $dashboard_breakdown_sections
);
$dashboard_section_timing_blurb = count($dashboard_timing_section_labels) > 1
    ? implode(', ', array_slice($dashboard_timing_section_labels, 0, -1))
        . ', and ' . $dashboard_timing_section_labels[array_key_last($dashboard_timing_section_labels)]
    : (string) ($dashboard_timing_section_labels[0] ?? '');
$dashboard_voucher_types = checklist_types_with_labels();
// Root-relative URL so fetch works regardless of /public vs /vtrams/public path depth
$scriptName = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$fetch_voucher_data_url = 'dashboard.php?fetch=voucher_tracking';
if ($scriptName !== '') {
    $fetch_voucher_data_url = rtrim(dirname($scriptName), '/') . '/dashboard.php?fetch=voucher_tracking';
}
?>
<style>
    .main.main--analytics-dashboard {
        height: calc(100dvh - 4rem);
        max-height: calc(100dvh - 4rem);
        overflow: hidden;
        padding: clamp(0.75rem, 1.6vw, 1.5rem) clamp(0.875rem, 2vw, 1.75rem);
        box-sizing: border-box;
        display: flex;
        flex-direction: column;
        gap: clamp(0.75rem, 1.4vw, 1.25rem);
        background: #F5F6F8;
    }

    .analytics-dashboard-shell {
        display: flex;
        flex-direction: column;
        gap: clamp(0.75rem, 1.4vw, 1.25rem);
        flex: 1;
        min-height: 0;
        min-width: 0;
    }

    .analytics-dashboard-toolbar {
        display: flex;
        flex-direction: column;
        gap: clamp(0.75rem, 1.4vw, 1.25rem);
        flex-shrink: 0;
    }

    .analytics-dashboard-viewport {
        flex: 1;
        min-height: 0;
        min-width: 0;
        overflow: auto;
        overflow-x: hidden;
        -webkit-overflow-scrolling: touch;
    }

    .analytics-dashboard-scale {
        width: 100%;
        min-width: 0;
        display: flex;
        flex-direction: column;
        gap: 12px;
    }

    .analytics-dashboard-header {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-start;
        justify-content: space-between;
        gap: 10px;
    }

    .analytics-dashboard-header__title {
        margin: 0 0 2px;
        font-size: 1.125rem;
        font-weight: 600;
        color: #111827;
        letter-spacing: -0.015em;
    }

    .analytics-dashboard-header__subtitle {
        margin: 0;
        color: #94a3b8;
        font-size: 0.8125rem;
        line-height: 1.4;
        max-width: 640px;
    }

    .analytics-dashboard-refresh {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 6px 10px;
        border-radius: 999px;
        background: #fff;
        border: 1px solid #eef2f7;
        color: #64748b;
        font-size: 11px;
        font-weight: 500;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        white-space: nowrap;
    }

    .analytics-dashboard-refresh::before {
        content: "";
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: #4A76FF;
        box-shadow: 0 0 0 2px rgba(74, 118, 255, 0.16);
    }

    .analytics-filter-bar {
        display: flex;
        flex-wrap: wrap;
        align-items: flex-end;
        gap: 10px;
        padding: 12px 14px;
        background: #fff;
        border: 1px solid #eef2f7;
        border-radius: 14px;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
    }

    .analytics-filter-field {
        display: flex;
        flex-direction: column;
        gap: 4px;
        min-width: 0;
    }

    .analytics-filter-field label {
        font-size: 10px;
        font-weight: 600;
        letter-spacing: 0.07em;
        text-transform: uppercase;
        color: #94a3b8;
    }

    .analytics-filter-field select {
        min-height: 34px;
        padding: 0 10px;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
        background: #fafbfc;
        color: #334155;
        font-size: 12px;
        font-weight: 500;
        transition: border-color 120ms ease, box-shadow 120ms ease, background 120ms ease;
    }

    .analytics-filter-field select:focus {
        outline: none;
        background: #fff;
        border-color: #b8c9ff;
        box-shadow: 0 0 0 3px rgba(74, 118, 255, 0.12);
    }

    .analytics-filter-field--date-group {
        flex: 1 1 260px;
    }

    .analytics-filter-date-row {
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }

    .analytics-filter-date-row select {
        flex: 1 1 72px;
        min-width: 72px;
    }

    .analytics-filter-apply {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-height: 34px;
        padding: 0 14px;
        border: none;
        border-radius: 8px;
        background: linear-gradient(135deg, #4A76FF, #3d67e8);
        color: #fff;
        font-size: 12px;
        font-weight: 600;
        letter-spacing: 0.01em;
        cursor: pointer;
        box-shadow: 0 2px 8px rgba(74, 118, 255, 0.22);
        transition: transform 120ms ease, box-shadow 120ms ease;
    }

    .analytics-filter-apply:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 12px rgba(74, 118, 255, 0.28);
    }

    .analytics-tabs {
        display: inline-flex;
        flex-wrap: wrap;
        gap: 6px;
        padding: 4px;
        background: #fff;
        border: 1px solid #eef2f7;
        border-radius: 10px;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        width: fit-content;
    }

    .analytics-tab-btn {
        border: none;
        background: transparent;
        color: #64748b;
        padding: 8px 12px;
        border-radius: 7px;
        cursor: pointer;
        font-size: 12px;
        font-weight: 600;
        transition: background 120ms ease, color 120ms ease, box-shadow 120ms ease;
    }

    .analytics-tab-btn:hover {
        background: #f3f6fb;
        color: #1d4ed8;
    }

    .analytics-tab-btn.is-active {
        background: linear-gradient(135deg, #4A76FF, #3d67e8);
        color: #fff;
        box-shadow: 0 4px 12px rgba(74, 118, 255, 0.24);
    }

    .analytics-tab-panel {
        display: none;
        flex-direction: column;
        gap: 12px;
        min-width: 0;
        max-width: 100%;
    }

    .analytics-tab-panel.is-active {
        display: flex;
    }

    .analytics-stats-grid {
        display: grid;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 10px;
    }

    .analytics-stat-card {
        display: flex;
        align-items: flex-start;
        gap: 10px;
        padding: 12px 13px;
        background: #fff;
        border: 1px solid #eef2f7;
        border-radius: 12px;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
        min-width: 0;
    }

    .analytics-stat-card__body {
        min-width: 0;
        flex: 1;
    }

    .analytics-stat-card__icon {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        width: 34px;
        height: 34px;
        border-radius: 9px;
        font-size: 16px;
        flex-shrink: 0;
    }

    .analytics-stat-card__icon--blue {
        background: linear-gradient(135deg, rgba(74, 118, 255, 0.16), rgba(74, 118, 255, 0.08));
        color: #4A76FF;
    }

    .analytics-stat-card__icon--green {
        background: linear-gradient(135deg, rgba(5, 150, 105, 0.16), rgba(5, 150, 105, 0.08));
        color: #059669;
    }

    .analytics-stat-card__icon--amber {
        background: linear-gradient(135deg, rgba(245, 158, 11, 0.16), rgba(245, 158, 11, 0.08));
        color: #d97706;
    }

    .analytics-stat-card__label {
        font-size: 10px;
        font-weight: 600;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: #94a3b8;
        line-height: 1.2;
    }

    .analytics-stat-card__value {
        margin-top: 2px;
        font-size: 1.125rem;
        font-weight: 600;
        color: #0f172a;
        line-height: 1.15;
        letter-spacing: -0.02em;
        font-variant-numeric: tabular-nums;
    }

    .analytics-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }

    .analytics-grid--full {
        grid-template-columns: minmax(0, 1fr);
    }

    .analytics-card {
        background: #fff;
        border: 1px solid #eef2f7;
        border-radius: 14px;
        box-shadow: 0 1px 3px rgba(15, 23, 42, 0.05);
        padding: 14px 16px 16px;
        display: flex;
        flex-direction: column;
        min-width: 0;
    }

    .analytics-card__title {
        margin: 0 0 10px;
        color: #475569;
        font-size: 12px;
        font-weight: 600;
        letter-spacing: 0.01em;
    }

    .analytics-card__note {
        margin: -6px 0 10px;
        color: #94a3b8;
        font-size: 11px;
        line-height: 1.45;
    }

    .analytics-card--chart {
        min-height: auto;
    }

    .analytics-chart-wrap {
        position: relative;
        height: 220px;
        min-height: 220px;
        max-height: 220px;
    }

    .analytics-card--chart canvas {
        max-height: none;
        height: 100% !important;
    }

    .analytics-card--table {
        height: auto;
        min-height: auto;
    }

    .analytics-table-wrap {
        overflow-x: auto;
        width: 100%;
        border: 1px solid #eef2f7;
        border-radius: 10px;
    }

    #sectionSummaryTable,
    #sectionVoucherTable {
        width: 100%;
        border-collapse: collapse;
        font-size: 12px;
    }

    #sectionSummaryTable th,
    #sectionVoucherTable th,
    #sectionSummaryTable td,
    #sectionVoucherTable td {
        padding: 9px 12px;
        border-bottom: 1px solid #f1f5f9;
        text-align: left;
    }

    #sectionSummaryTable th,
    #sectionVoucherTable th {
        background: #fafbfc;
        color: #94a3b8;
        font-size: 10px;
        font-weight: 600;
        letter-spacing: 0.06em;
        text-transform: uppercase;
    }

    #sectionSummaryTable tbody tr:hover,
    #sectionVoucherTable tbody tr:hover {
        background: #f8fbff;
    }

    #sectionSummaryTable td,
    #sectionVoucherTable td {
        color: #334155;
    }

    @media (max-width: 1100px) {
        .analytics-stats-grid,
        .analytics-grid {
            grid-template-columns: minmax(0, 1fr);
        }
    }

    @media (max-width: 640px) {
        .main.main--analytics-dashboard {
            padding: 1rem;
        }

        .analytics-filter-bar {
            padding: 14px;
        }

        .analytics-filter-apply {
            width: 100%;
        }
    }
</style>

<!--=============== MAIN ===============-->
<div class="main main--analytics-dashboard" id="main">
    <div class="analytics-dashboard-shell">
        <div class="analytics-dashboard-toolbar">
            <header class="analytics-dashboard-header">
                <div>
                    <h1 class="analytics-dashboard-header__title">Voucher Analytics Dashboard</h1>
                    <p class="analytics-dashboard-header__subtitle">Analytics for forwarded, received, and returned vouchers (excludes encoded/pending at encoder only)</p>
                </div>
                <p class="analytics-dashboard-refresh" id="dashboardRefreshStatus">Loading…</p>
            </header>

            <section class="analytics-filter-bar">
        <div class="analytics-filter-field">
            <label for="voucherTypeFilter">Voucher Type</label>
            <select id="voucherTypeFilter">
                <option value="all" selected>All Types</option>
                <?php foreach ($dashboard_voucher_types as $type_value => $type_label): ?>
                    <option value="<?= htmlspecialchars((string)$type_value, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)$type_label, ENT_QUOTES, 'UTF-8') ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="analytics-filter-field">
            <label for="officeFilter">Office</label>
            <select id="officeFilter">
                <option value="all" selected>All Offices</option>
                <?php foreach ($dashboard_offices as $office_name): ?>
                    <option value="<?= htmlspecialchars((string) $office_name, ENT_QUOTES, 'UTF-8') ?>">
                        <?= htmlspecialchars((string) $office_name, ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="analytics-filter-field analytics-filter-field--date-group">
            <label>Date (MDY)</label>
            <div class="analytics-filter-date-row">
                <select id="monthFilter" aria-label="Filter month">
                    <option value="all" selected>Month</option>
                    <option value="01">Jan</option>
                    <option value="02">Feb</option>
                    <option value="03">Mar</option>
                    <option value="04">Apr</option>
                    <option value="05">May</option>
                    <option value="06">Jun</option>
                    <option value="07">Jul</option>
                    <option value="08">Aug</option>
                    <option value="09">Sep</option>
                    <option value="10">Oct</option>
                    <option value="11">Nov</option>
                    <option value="12">Dec</option>
                </select>
                <select id="dayFilter" aria-label="Filter day">
                    <option value="all" selected>Day</option>
                    <?php
                    for ($i = 1; $i <= 31; $i++) {
                        $day = str_pad($i, 2, '0', STR_PAD_LEFT);
                        echo "<option value=\"$day\">$day</option>";
                    }
                    ?>
                </select>
                <select id="yearDateFilter" aria-label="Filter year">
                    <option value="all" selected>Year</option>
                    <?php
                    $currentYear = date('Y');
                    for ($year = $currentYear; $year >= 2010; $year--) {
                        echo "<option value=\"$year\">$year</option>";
                    }
                    ?>
                </select>
            </div>
        </div>

        <button type="button" class="analytics-filter-apply" id="applyFiltersBtn">Apply Filters</button>
            </section>

            <nav class="analytics-tabs" aria-label="Dashboard views">
                <button type="button" class="analytics-tab-btn dashboard-tab-btn is-active" data-dashboard-tab="analytics">Analytics</button>
                <button type="button" class="analytics-tab-btn dashboard-tab-btn" data-dashboard-tab="processing">Processing Times</button>
            </nav>
        </div>

        <div class="analytics-dashboard-viewport" id="analyticsDashboardViewport">
            <div class="analytics-dashboard-scale" id="analyticsDashboardScale">
    <div class="analytics-tab-panel dashboard-tab-panel is-active" id="dashboardTabAnalytics">
        <div class="analytics-stats-grid" id="overallTable"></div>

        <section class="analytics-grid">
            <article class="analytics-card analytics-card--chart">
                <h3 class="analytics-card__title">Voucher Type Distribution</h3>
                <div class="analytics-chart-wrap"><canvas id="voucherTypeChart"></canvas></div>
            </article>
            <article class="analytics-card analytics-card--chart">
                <h3 class="analytics-card__title">Amount by Voucher Type</h3>
                <div class="analytics-chart-wrap"><canvas id="amountChart"></canvas></div>
            </article>
        </section>

        <section class="analytics-grid analytics-grid--full">
            <article class="analytics-card analytics-card--chart">
                <h3 class="analytics-card__title">Monthly Trends</h3>
                <div class="analytics-chart-wrap"><canvas id="monthlyChart"></canvas></div>
            </article>
        </section>
    </div>

    <div class="analytics-tab-panel dashboard-tab-panel" id="dashboardTabProcessing">
        <section class="analytics-grid">
            <article class="analytics-card analytics-card--chart">
                <h3 class="analytics-card__title">Average Processing Time by Section</h3>
                <div class="analytics-chart-wrap"><canvas id="sectionTimeChart"></canvas></div>
            </article>
            <article class="analytics-card analytics-card--table">
                <h3 class="analytics-card__title">Section Processing Time Summary</h3>
                <p class="analytics-card__note"><?= htmlspecialchars($dashboard_section_timing_blurb, ENT_QUOTES, 'UTF-8') ?> only — from when received by the section until successfully forwarded (confirmed by the next section/process), or processed/paid for Cashiers. Includes each re-processing stint after a return (e.g. Accounting returns to Planning and Planning forwards again). Encoding and forwarding by a process-section user before receive is excluded. Processing time counts Monday through Thursday only (Fridays, Saturdays, and Sundays are excluded).</p>
                <div class="analytics-table-wrap">
                    <table id="sectionSummaryTable">
                        <thead>
                            <tr>
                                <th>Section</th>
                                <th style="text-align:right;">Vouchers</th>
                                <th style="text-align:right;">Avg Time</th>
                                <th style="text-align:right;">Min</th>
                                <th style="text-align:right;">Max</th>
                            </tr>
                        </thead>
                        <tbody></tbody>
                    </table>
                </div>
            </article>
        </section>

        <section class="analytics-grid analytics-grid--full">
            <article class="analytics-card analytics-card--table">
                <h3 class="analytics-card__title">Per-Voucher Section Processing Breakdown</h3>
                <p class="analytics-card__note" id="dashboardVoucherListMeta">Showing the 15 most recently processed vouchers for the current filters (updates automatically).</p>
                <div class="analytics-table-wrap">
                    <table id="sectionVoucherTable">
                        <thead></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </article>
        </section>
    </div>
            </div>
        </div>
    </div>
</div>

    <!--=============== JS ===============-->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="../../protected/js/amount_helper.js"></script>
    <script src="../../protected/js/main.js"></script>
    <script src="../../protected/js/popscript.js"></script>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const FETCH_VOUCHER_DATA = <?= json_encode($fetch_voucher_data_url, JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS) ?>;
            const voucherTypeFilter = document.getElementById('voucherTypeFilter');
            const officeFilter = document.getElementById('officeFilter');
            const monthFilter = document.getElementById('monthFilter');
            const dayFilter = document.getElementById('dayFilter');
            const yearDateFilter = document.getElementById('yearDateFilter');
            const applyBtn = document.getElementById('applyFiltersBtn');
            const refreshStatusEl = document.getElementById('dashboardRefreshStatus');
            const REFRESH_INTERVAL_MS = 15000;
            let refreshTimer = null;
            let fetchInFlight = false;

            // Chart contexts
            const ctxVoucherType = document.getElementById('voucherTypeChart').getContext('2d');
            const ctxAmount = document.getElementById('amountChart').getContext('2d');
            const ctxMonthly = document.getElementById('monthlyChart').getContext('2d');
            const ctxSectionTime = document.getElementById('sectionTimeChart').getContext('2d');

            let voucherTypeChart, amountChart, monthlyChart, sectionTimeChart;

            let layoutSyncTimer = null;

            const chartFont = {
                family: 'inherit',
                size: 10,
                weight: '500'
            };

            const chartLegend = {
                position: 'bottom',
                labels: {
                    boxWidth: 8,
                    boxHeight: 8,
                    padding: 10,
                    font: chartFont,
                    color: '#94a3b8'
                }
            };

            const chartScaleTicks = {
                font: chartFont,
                color: '#94a3b8',
                maxTicksLimit: 7
            };

            function formatPesoAmount(raw) {
                if (raw === '' || raw == null) return '';
                const n = parseFloat(String(raw).replace(/,/g, ''));
                if (!isFinite(n)) return String(raw);
                return n.toLocaleString('en-PH', {
                    minimumFractionDigits: 2,
                    maximumFractionDigits: 2
                });
            }

            function resizeAllCharts() {
                [voucherTypeChart, amountChart, monthlyChart, sectionTimeChart].forEach(chart => {
                    if (chart && typeof chart.resize === 'function') {
                        chart.resize();
                    }
                });
            }

            function scheduleChartResize() {
                if (layoutSyncTimer) {
                    clearTimeout(layoutSyncTimer);
                }
                layoutSyncTimer = setTimeout(resizeAllCharts, 120);
            }

            window.addEventListener('resize', scheduleChartResize);

            const tabButtons = document.querySelectorAll('.dashboard-tab-btn');
            const tabPanels = {
                analytics: document.getElementById('dashboardTabAnalytics'),
                processing: document.getElementById('dashboardTabProcessing'),
            };

            tabButtons.forEach(btn => {
                btn.addEventListener('click', () => {
                    const tab = btn.getAttribute('data-dashboard-tab');
                    tabButtons.forEach(b => b.classList.toggle('is-active', b === btn));
                    Object.entries(tabPanels).forEach(([key, panel]) => {
                        if (panel) {
                            panel.classList.toggle('is-active', key === tab);
                        }
                    });
                    scheduleChartResize();
                });
            });

            function processData(data) {
                const stats = {
                    voucherType: {},
                    amountByType: {},
                    monthly: {},
                    totalEntries: 0,
                    totalAmount: 0
                };

                data.forEach(row => {
                    // Voucher type distribution
                    const voucherType = row.voucher_type || 'Unknown';
                    stats.voucherType[voucherType] = (stats.voucherType[voucherType] || 0) + 1;

                    // Amount by voucher type (prefer charged_amount when set on voucher_tracking)
                    const amountSource = isNonZeroAmount(row.charged_amount) ? row.charged_amount : (row.amount || '');
                    const amount = parseFloat(normalizeAmountInput(amountSource)) || 0;
                    stats.amountByType[voucherType] = (stats.amountByType[voucherType] || 0) + amount;

                    // Monthly distribution
                    if (row.voucher_date) {
                        const date = new Date(row.voucher_date);
                        const monthYear = date.toLocaleDateString('en-US', {
                            month: 'short',
                            year: 'numeric'
                        });
                        stats.monthly[monthYear] = (stats.monthly[monthYear] || 0) + 1;
                    }

                    stats.totalEntries++;
                    stats.totalAmount += amount;
                });

                return stats;
            }

            function updateCharts(data) {
                const stats = processData(data);

                // Color palettes aligned with dashboard theme
                const baseColors = [
                    'rgba(74, 118, 255, 0.72)',
                    'rgba(5, 150, 105, 0.72)',
                    'rgba(245, 158, 11, 0.72)',
                    'rgba(139, 92, 246, 0.72)',
                    'rgba(236, 72, 153, 0.72)',
                    'rgba(14, 165, 233, 0.72)',
                    'rgba(107, 114, 128, 0.72)',
                    'rgba(239, 68, 68, 0.72)'
                ];

                function colorsForCount(n) {
                    const out = [];
                    for (let i = 0; i < n; i++) {
                        out.push(baseColors[i % baseColors.length]);
                    }
                    return out;
                }

                // ==== VOUCHER TYPE DOUGHNUT CHART ====
                if (voucherTypeChart) voucherTypeChart.destroy();
                const voucherTypeLabels = Object.keys(stats.voucherType);
                const voucherTypeData = Object.values(stats.voucherType);
                voucherTypeChart = new Chart(ctxVoucherType, {
                    type: 'doughnut',
                    data: {
                        labels: voucherTypeLabels,
                        datasets: [{
                            data: voucherTypeData,
                            backgroundColor: colorsForCount(voucherTypeLabels.length),
                            borderWidth: 0,
                            spacing: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: { duration: 350 },
                        cutout: '62%',
                        plugins: { legend: chartLegend }
                    }
                });

                // ==== AMOUNT BY VOUCHER TYPE BAR CHART ====
                if (amountChart) amountChart.destroy();
                const amountLabels = Object.keys(stats.amountByType);
                const amountData = Object.values(stats.amountByType);
                amountChart = new Chart(ctxAmount, {
                    type: 'bar',
                    data: {
                        labels: amountLabels,
                        datasets: [{
                            label: 'Total Amount',
                            data: amountData,
                            backgroundColor: 'rgba(74, 118, 255, 0.72)',
                            borderRadius: 6,
                            maxBarThickness: 36
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: { duration: 350 },
                        plugins: { legend: { display: false } },
                        scales: {
                            x: {
                                ticks: chartScaleTicks,
                                grid: { display: false }
                            },
                            y: {
                                beginAtZero: true,
                                ticks: chartScaleTicks,
                                grid: { color: '#f1f5f9', drawBorder: false }
                            }
                        }
                    }
                });

                // ==== MONTHLY TRENDS LINE CHART ====
                if (monthlyChart) monthlyChart.destroy();
                const monthlyLabels = Object.keys(stats.monthly).sort((a, b) => {
                    const dateA = new Date(a);
                    const dateB = new Date(b);
                    return dateA - dateB;
                });
                const monthlyData = monthlyLabels.map(m => stats.monthly[m] || 0);
                monthlyChart = new Chart(ctxMonthly, {
                    type: 'line',
                    data: {
                        labels: monthlyLabels,
                        datasets: [{
                            label: 'Number of Vouchers',
                            data: monthlyData,
                            borderColor: 'rgba(74, 118, 255, 0.95)',
                            backgroundColor: 'rgba(74, 118, 255, 0.12)',
                            pointBackgroundColor: '#4A76FF',
                            pointBorderColor: '#fff',
                            pointBorderWidth: 2,
                            pointRadius: 0,
                            pointHitRadius: 8,
                            borderWidth: 2,
                            tension: 0.35,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: { duration: 350 },
                        interaction: { mode: 'index', intersect: false },
                        plugins: { legend: { display: false } },
                        scales: {
                            x: {
                                ticks: chartScaleTicks,
                                grid: { color: '#f1f5f9', drawBorder: false }
                            },
                            y: {
                                beginAtZero: true,
                                ticks: chartScaleTicks,
                                grid: { color: '#f1f5f9', drawBorder: false }
                            }
                        }
                    }
                });


                // ==== STAT CARDS ====
                const overallTableDiv = document.getElementById('overallTable');
                overallTableDiv.innerHTML = '';

                const statCards = [{
                        label: 'Total Vouchers',
                        value: stats.totalEntries,
                        icon: 'ri-file-list-3-line',
                        tone: 'blue'
                    },
                    {
                        label: 'Total Amount',
                        value: '₱' + formatPesoAmount(stats.totalAmount),
                        icon: 'ri-money-dollar-circle-line',
                        tone: 'green'
                    },
                    {
                        label: 'Voucher Types',
                        value: Object.keys(stats.voucherType).length,
                        icon: 'ri-stack-line',
                        tone: 'amber'
                    }
                ];

                statCards.forEach(stat => {
                    const card = document.createElement('article');
                    card.className = 'analytics-stat-card';
                    card.innerHTML = `
                        <div class="analytics-stat-card__icon analytics-stat-card__icon--${stat.tone}">
                            <i class="${stat.icon}" aria-hidden="true"></i>
                        </div>
                        <div class="analytics-stat-card__body">
                            <div class="analytics-stat-card__label">${stat.label}</div>
                            <div class="analytics-stat-card__value">${stat.value}</div>
                        </div>`;
                    overallTableDiv.appendChild(card);
                });

                scheduleChartResize();
            }

            function updateSectionTiming(sectionTiming) {
                const timing = sectionTiming && typeof sectionTiming === 'object' ? sectionTiming : {
                    sections: [],
                    summary: [],
                    by_voucher: []
                };
                const summary = Array.isArray(timing.summary) ? timing.summary : [];
                const sections = Array.isArray(timing.sections) ? timing.sections : [];
                const byVoucherSections = Array.isArray(timing.by_voucher_sections) && timing.by_voucher_sections.length
                    ? timing.by_voucher_sections
                    : sections;
                const byVoucher = Array.isArray(timing.by_voucher) ? timing.by_voucher : [];
                const voucherListMeta = document.getElementById('dashboardVoucherListMeta');
                if (voucherListMeta) {
                    const limit = timing.by_voucher_limit || byVoucher.length || 15;
                    voucherListMeta.textContent = byVoucher.length > 0
                        ? `Showing the ${byVoucher.length} most recently processed voucher(s) for the current filters (limit ${limit}; updates automatically).`
                        : 'No per-voucher section timing data for the current filters.';
                }

                if (sectionTimeChart) sectionTimeChart.destroy();
                const sectionLabels = summary.map(row => row.section || 'Unknown');
                const sectionAvgHours = summary.map(row => ((row.avg_seconds || 0) / 3600));
                sectionTimeChart = new Chart(ctxSectionTime, {
                    type: 'bar',
                    data: {
                        labels: sectionLabels,
                        datasets: [{
                            label: 'Avg Hours',
                            data: sectionAvgHours,
                            backgroundColor: 'rgba(74, 118, 255, 0.72)',
                            borderRadius: 6,
                            maxBarThickness: 36
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        animation: { duration: 350 },
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label(ctx) {
                                        const row = summary[ctx.dataIndex];
                                        return row ? `Avg: ${row.avg_label}` : '';
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                ticks: chartScaleTicks,
                                grid: { display: false }
                            },
                            y: {
                                beginAtZero: true,
                                ticks: chartScaleTicks,
                                grid: { color: '#f1f5f9', drawBorder: false },
                                title: {
                                    display: true,
                                    text: 'Hours',
                                    font: chartFont,
                                    color: '#94a3b8'
                                }
                            }
                        }
                    }
                });

                const summaryBody = document.querySelector('#sectionSummaryTable tbody');
                summaryBody.innerHTML = '';
                if (summary.length === 0) {
                    const row = document.createElement('tr');
                    row.innerHTML = '<td colspan="5" style="text-align:center;color:#888;">No section timing data for the current filters.</td>';
                    summaryBody.appendChild(row);
                } else {
                    summary.forEach(row => {
                        const tr = document.createElement('tr');
                        tr.innerHTML = `<td>${row.section || '-'}</td>
                            <td style="text-align:right;">${row.count || 0}</td>
                            <td style="text-align:right;">${row.avg_label || '—'}</td>
                            <td style="text-align:right;">${row.min_label || '—'}</td>
                            <td style="text-align:right;">${row.max_label || '—'}</td>`;
                        summaryBody.appendChild(tr);
                    });
                }

                const voucherHead = document.querySelector('#sectionVoucherTable thead');
                const voucherBody = document.querySelector('#sectionVoucherTable tbody');
                voucherHead.innerHTML = '';
                voucherBody.innerHTML = '';

                const headerRow = document.createElement('tr');
                let headerHtml = '<th>Processing No.</th><th>Payee</th><th>DV No.</th>';
                byVoucherSections.forEach(section => {
                    headerHtml += `<th style="text-align:right;">${section}</th>`;
                });
                headerHtml += '<th style="text-align:right;">Total Processing Time</th>';
                headerRow.innerHTML = headerHtml;
                voucherHead.appendChild(headerRow);

                if (byVoucher.length === 0) {
                    const row = document.createElement('tr');
                    row.innerHTML = `<td colspan="${4 + byVoucherSections.length}" style="text-align:center;color:#888;">No per-voucher section timing data for the current filters.</td>`;
                    voucherBody.appendChild(row);
                    return;
                }

                byVoucher.forEach(row => {
                    const tr = document.createElement('tr');
                    let html = `<td>${row.processing_no || '-'}</td>
                        <td>${row.payee || '-'}</td>
                        <td>${row.dv_no || '-'}</td>`;
                    const labels = row.sections_label || {};
                    byVoucherSections.forEach(section => {
                        html += `<td style="text-align:right;">${labels[section] || '—'}</td>`;
                    });
                    const tpt = (row.total_processing_time || '').trim();
                    html += `<td style="text-align:right;">${tpt !== '' ? tpt : '—'}</td>`;
                    tr.innerHTML = html;
                    voucherBody.appendChild(tr);
                });

                scheduleChartResize();
            }

            function parseFetchResponse(res) {
                const ct = res.headers.get('content-type') || '';
                if (ct.includes('application/json')) {
                    return res.json();
                }
                return res.text().then(text => {
                    const t = text.trim();
                    if (t.startsWith('{') || t.startsWith('[')) {
                        try {
                            return JSON.parse(t);
                        } catch (e) {
                            console.error('Invalid JSON body:', t.substring(0, 300));
                            throw e;
                        }
                    }
                    console.error('Non-JSON response (HTTP ' + res.status + '):', t.substring(0, 400));
                    throw new Error('Server returned non-JSON response');
                });
            }

            function setRefreshStatus(message) {
                if (refreshStatusEl) {
                    refreshStatusEl.textContent = message;
                }
            }

            function applyVoucherData(data) {
                if (Array.isArray(data)) {
                    console.error('Voucher data fetch returned unexpected array payload');
                    updateCharts([]);
                    updateSectionTiming(null);
                    setRefreshStatus('Update failed · retrying…');
                    return;
                }
                if (data && Array.isArray(data.rows)) {
                    updateCharts(data.rows);
                    updateSectionTiming(data.section_timing || null);
                    setRefreshStatus('Updated ' + new Date().toLocaleTimeString() + ' · auto-refresh every 15s');
                    return;
                }
                if (data && typeof data.error === 'string') {
                    console.error('Voucher data API error:', data.detail || data.error);
                    setRefreshStatus('Update failed · retrying…');
                } else {
                    console.error('Invalid data format received:', data);
                    setRefreshStatus('Update failed · retrying…');
                }
                updateCharts([]);
                updateSectionTiming(null);
            }

            function buildFetchUrl() {
                const params = new URLSearchParams({
                    voucher_type: voucherTypeFilter.value,
                    office: officeFilter.value,
                    month: monthFilter.value,
                    day: dayFilter.value,
                    yearDate: yearDateFilter.value,
                    _: String(Date.now())
                });
                const q = params.toString();
                const joiner = FETCH_VOUCHER_DATA.includes('?') ? '&' : '?';
                return FETCH_VOUCHER_DATA + (q ? joiner + q : '');
            }

            function safeFetch(url, callback) {
                fetch(url, {
                        credentials: 'same-origin',
                        cache: 'no-store'
                    })
                    .then(res => {
                        if (!res.ok) {
                            console.error('Voucher data fetch failed HTTP', res.status, url);
                        }
                        return parseFetchResponse(res);
                    })
                    .then(data => callback(data))
                    .catch(err => {
                        console.error('Error fetching voucher data:', err);
                        callback(null);
                    });
            }

            function fetchFilteredData() {
                if (fetchInFlight) {
                    return;
                }
                fetchInFlight = true;
                safeFetch(buildFetchUrl(), data => {
                    fetchInFlight = false;
                    applyVoucherData(data);
                });
            }

            function startAutoRefresh() {
                if (refreshTimer) {
                    clearInterval(refreshTimer);
                }
                refreshTimer = setInterval(fetchFilteredData, REFRESH_INTERVAL_MS);
            }

            function stopAutoRefresh() {
                if (refreshTimer) {
                    clearInterval(refreshTimer);
                    refreshTimer = null;
                }
            }

            document.addEventListener('visibilitychange', () => {
                if (document.hidden) {
                    stopAutoRefresh();
                    return;
                }
                fetchFilteredData();
                startAutoRefresh();
            });

            // Initial load (same endpoint as filter, all types)
            fetchFilteredData();
            startAutoRefresh();

            applyBtn.addEventListener('click', () => {
                fetchFilteredData();
            });
        });
    </script>



    </body>

    </html>
    </html>