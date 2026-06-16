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

    try {
        // Exclude encoded/pending at encoder only (active_status = no).
        $query = 'SELECT vt.* FROM voucher_tracking vt WHERE 1=1' . voucher_tracking_counts_include_sql('vt');
        $params = [];

        if ($voucher_type !== null && $voucher_type !== '') {
            $query .= ' AND vt.voucher_type = :voucher_type';
            $params[':voucher_type'] = $voucher_type;
        }

        if ($office !== null && $office !== '') {
            $query .= ' AND LOWER(TRIM(vt.office_from)) = LOWER(TRIM(:office))';
            $params[':office'] = $office;
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

        $stmt = $pdo->prepare($query);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();

        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $sectionTiming = voucher_tracking_build_section_timing_report($pdo, $rows);
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
$dashboard_timing_section_labels = array_map(
    static fn(string $section): string => match ($section) {
        'Planning Section' => 'Planning',
        'Conservation & Development Section' => 'CDS',
        'Budget Unit' => 'Budget',
        'Accounting Unit' => 'Accounting',
        'Cashiers Unit' => 'Cashiers',
        default => $section,
    },
    voucher_tracking_dashboard_sections()
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
    /* Keep your existing styles unchanged */
    .dashboard {
        height: 200px;
    }

    .main_dashboard {
        overflow-y: scroll;
    }

    .main-content {
        flex: 1;
        padding: 20px;
        display: flex;
        flex-direction: column;
        gap: 3%;
        width: 100%;
        height: 100%;
    }

    .dashboard-content {
        display: flex;
        gap: 20px;
    }

    .dashboard-content div {
        flex: 1;
        display: flex;
        justify-content: center;
        align-items: center;
    }

    .chart-container {
        background-color: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        padding: 20px;
        height: 350px;
        display: flex;
        flex-direction: column;
    }

    .chart-container canvas {
        flex: 1;
        max-height: 280px;
    }

    .chart-container h3 {
        margin-bottom: 10px;
        color: rgb(75 85 99 / 0.9);
        text-align: left;
        font-size: 16px;
        font-weight: 600;
    }

    .table-container {
        width: 100%;
        background-color: #fff;
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        padding: 20px;
    }

    .stats-card-wrapper {
        display: flex;
        justify-content: space-between;
        flex-wrap: nowrap;
        overflow: hidden;
        gap: 20px;
    }

    .stat-card {
        flex: 1 1 150px;
        /* border-left: 6px solid #ccc; */
        border-radius: 8px;
        padding: 10px 12px;
        display: flex;
        flex-direction: column;
        justify-content: center;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.3);
        color: rgb(75 85 99 / 0.7);
        /* background: linear-gradient(to right, #999, #ccc); */
    }

    .stat-card h4 {
        margin: 0;
        font-size: 16px;
    }

    .stat-card .count {
        font-size: 13px;
        font-weight: bold;
        margin-top: 5px;
        padding: 1.5rem;
        background-color: rgba(255, 255, 255, 1);
        /* fully opaque */
    }

    .filter_options {
        display: flex;
        flex-wrap: wrap;
        gap: 15px;
        align-items: center;
        background-color: #fff;
        padding: 16px 20px;
        border-radius: 8px;
        box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        margin-bottom: 20px;
        color: rgb(75 85 99 / 0.9);
    }

    .filter_options>div {
        display: flex;
        flex-direction: row;
        font-size: 14px;
        align-items: center;
        gap: 1rem;
    }

    .filter_options select {
        padding: 6px;
        border-radius: 5px;
        border: 1px solid #ccc;
        border-color: rgb(209 213 219 / 1);
    }

    .filter_options button {
        background-color: #0d6efd;
        color: white;
        border: none;
        padding: 8px 16px;
        border-radius: 5px;
        height: fit-content;
        align-self: flex-end;
    }


    .modern-stat-card {
        display: flex;
        padding: 12px 16px;
        border-radius: 10px;
        background-color: #ffffff;
        box-shadow: 0 2px 6px rgba(0, 0, 0, 0.05);
        min-width: 220px;
        flex: 1;
    }

    .stat-card-content {
        display: flex;
        gap: 10px;
        align-items: flex-start;
    }

    .stat-icon {
        font-size: 14px;
        width: 40px;
        height: 40px;
        border-radius: 50%;
        display: flex;
        padding: 5px;
        align-items: center;
        justify-content: center;
        box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);

        img {
            width: 24px;
            height: 24px;
        }
    }

    .stat-text {
        display: flex;
        flex-direction: column;
        color: rgb(75 85 99 / 0.9);
    }

    .stat-label {
        font-size: 14px;
        font-weight: 500;
        color: rgb(75 85 99 / 0.9);
        line-height: 1.2;
    }

    .stat-value {
        font-size: 18px;
        font-weight: bold;
        color: #000;
        margin-top: 4px;
    }

    #percentageTable th,
    #percentageTable td {
        border-bottom: 1px solid #ddd;
        padding: 8px;
    }

    #percentageTable {
        width: 100%;
        border-collapse: collapse;
        font-size: 12px;

        th {
            color: rgb(75 85 99 / 0.7)
        }

        td {
            color: rgb(75 85 99 / 1)
        }
    }

    #percentageTable thead {
        background-color: #f9f9f9;
    }

    #percentageTable tr:hover {
        background-color: #f1f1f1;
    }

    #sectionSummaryTable th,
    #sectionSummaryTable td,
    #sectionVoucherTable th,
    #sectionVoucherTable td {
        border-bottom: 1px solid #ddd;
        padding: 8px;
    }

    #sectionSummaryTable,
    #sectionVoucherTable {
        width: 100%;
        border-collapse: collapse;
        font-size: 12px;
    }

    #sectionSummaryTable th,
    #sectionVoucherTable th {
        color: rgb(75 85 99 / 0.7);
        background-color: #f9f9f9;
    }

    #sectionSummaryTable td,
    #sectionVoucherTable td {
        color: rgb(75 85 99 / 1);
    }

    #sectionSummaryTable tr:hover,
    #sectionVoucherTable tr:hover {
        background-color: #f1f1f1;
    }

    .section-table-scroll {
        overflow-x: auto;
        width: 100%;
    }
</style>

<!--=============== MAIN ===============-->
<div class="main" id="main">
    <div style="display: flex; gap: 10px; margin-bottom: 20px;">
    </div>
    <div class="main-content main_dashboard">
        <div style="display:flex; flex-wrap:wrap; align-items:baseline; justify-content:space-between; gap:10px;">
            <div>
                <h1 style="margin-bottom:6px;">Voucher Analytics Dashboard</h1>
                <p style="color: rgb(75 85 99 / 0.9); margin:0;">Analytics for forwarded, received, and returned vouchers (excludes encoded/pending at encoder only)</p>
            </div>
            <p id="dashboardRefreshStatus" style="color: rgb(75 85 99 / 0.75); font-size: 12px; margin: 0;">Loading…</p>
        </div>

        <section class="filter_options">
            <div>
                <label>Voucher Type:</label>
                <select id="voucherTypeFilter">
                    <option value="all" selected>All Types</option>
                    <?php foreach ($dashboard_voucher_types as $type_value => $type_label): ?>
                        <option value="<?= htmlspecialchars((string)$type_value, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars((string)$type_label, ENT_QUOTES, 'UTF-8') ?></option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label for="officeFilter">Office:</label>
                <select id="officeFilter">
                    <option value="all" selected>All Offices</option>
                    <?php foreach ($dashboard_offices as $office_name): ?>
                        <option value="<?= htmlspecialchars((string) $office_name, ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars((string) $office_name, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div>
                <label>Date (MDY):</label>
                <div style="display: flex; gap: 5px;">
                    <select id="monthFilter" style="width: 80px;">
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
                    <select id="dayFilter" style="width: 70px;">
                        <option value="all" selected>Day</option>
                        <?php
                        for ($i = 1; $i <= 31; $i++) {
                            $day = str_pad($i, 2, '0', STR_PAD_LEFT);
                            echo "<option value=\"$day\">$day</option>";
                        }
                        ?>
                    </select>
                    <select id="yearDateFilter" style="width: 80px;">
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

            <button id="applyFiltersBtn">Apply Filters</button>
        </section>

        <div class="table-container">
            <div class="stats-card-wrapper" id="overallTable"></div>
        </div>

        <section class="dashboard-content new_label">
            <div class="chart-container new_label">
                <h3 style="margin-bottom: 10px; color: rgb(75 85 99 / 0.9); text-align:left;">Voucher Type Distribution</h3>
                <canvas id="voucherTypeChart"></canvas>
            </div>
            <div class="chart-container new_label">
                <h3 style="margin-bottom: 10px; color: rgb(75 85 99 / 0.9); text-align:left;">Amount by Voucher Type</h3>
                <canvas id="amountChart"></canvas>
            </div>
        </section>

        <section class="dashboard-content new_label">
            <div class="chart-container new_label">
                <h3 style="margin-bottom: 10px; color: rgb(75 85 99 / 0.9); text-align:left;">Monthly Trends</h3>
                <canvas id="monthlyChart"></canvas>
            </div>
        </section>

        <!-- ✅ Modified Section: Percentage Table -->
        <section class="dashboard-content new_label">
            <div class="chart-container new_label" style="display:flex; flex-direction: column;">
                <h3 style="margin-bottom: 20px; color: rgb(75 85 99 / 0.9); text-align:left; width: 100%;">Voucher Type Breakdown in Percentage</h3>

                <table id="percentageTable">
                    <thead>
                        <tr>
                            <th>Voucher Type</th>
                            <th style="text-align:right;">Count</th>
                            <th style="text-align:right;">Percentage</th>
                        </tr>
                    </thead>
                    <tbody>
                        <!-- JS will populate this -->
                    </tbody>
                </table>
            </div>
        </section>

        <section class="dashboard-content new_label">
            <div class="chart-container new_label">
                <h3 style="margin-bottom: 10px; color: rgb(75 85 99 / 0.9); text-align:left;">Average Processing Time by Section</h3>
                <canvas id="sectionTimeChart"></canvas>
            </div>
            <div class="chart-container new_label" style="display:flex; flex-direction: column; height: auto; min-height: 350px;">
                <h3 style="margin-bottom: 20px; color: rgb(75 85 99 / 0.9); text-align:left; width: 100%;">Section Processing Time Summary</h3>
                <p style="margin: 0 0 12px; color: rgb(75 85 99 / 0.75); font-size: 12px;"><?= htmlspecialchars($dashboard_section_timing_blurb, ENT_QUOTES, 'UTF-8') ?> only — from when received by the section until successfully forwarded (confirmed by the next section/process), or processed/paid for Cashiers.</p>
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
        </section>

        <section class="dashboard-content new_label">
            <div class="chart-container new_label" style="display:flex; flex-direction: column; height: auto; min-height: 350px; width: 100%;">
                <h3 style="margin-bottom: 8px; color: rgb(75 85 99 / 0.9); text-align:left; width: 100%;">Per-Voucher Section Processing Breakdown</h3>
                <p style="margin: 0 0 12px; color: rgb(75 85 99 / 0.75); font-size: 12px;">Last 15 most recently processed vouchers (updates automatically).</p>
                <div class="section-table-scroll">
                    <table id="sectionVoucherTable">
                        <thead></thead>
                        <tbody></tbody>
                    </table>
                </div>
            </div>
        </section>

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

                // Color palettes (repeat when many voucher types)
                const baseColors = [
                    'rgba(75, 192, 192, 0.6)',
                    'rgba(54, 162, 235, 0.6)',
                    'rgba(255, 206, 86, 0.6)',
                    'rgba(255, 99, 132, 0.6)',
                    'rgba(153, 102, 255, 0.6)',
                    'rgba(255, 159, 64, 0.6)',
                    'rgba(199, 199, 199, 0.6)',
                    'rgba(83, 102, 255, 0.6)'
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
                            backgroundColor: colorsForCount(voucherTypeLabels.length)
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false
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
                            backgroundColor: 'rgba(75, 192, 192, 0.6)'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true
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
                            borderColor: 'rgba(153, 102, 255, 0.6)',
                            backgroundColor: 'rgba(153, 102, 255, 0.2)',
                            tension: 0.4,
                            fill: true
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true
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
                        icon: '../assets/icons/total.png',
                        color: '#e6f0fa'
                    },
                    {
                        label: 'Total Amount',
                        value: '₱' + stats.totalAmount.toLocaleString('en-US', {
                            minimumFractionDigits: 2,
                            maximumFractionDigits: 2
                        }),
                        icon: '../assets/icons/total.png',
                        color: '#e6f4ea'
                    },
                    {
                        label: 'Voucher Types',
                        value: Object.keys(stats.voucherType).length,
                        icon: '../assets/icons/total.png',
                        color: '#fff8e6'
                    }
                ];

                statCards.forEach(stat => {
                    const card = document.createElement('div');
                    card.className = 'modern-stat-card';
                    card.innerHTML = `
                        <div class="stat-card-content">
                            <div class="stat-icon" style="background-color: ${stat.color}">
                                <img src="${stat.icon}" alt="${stat.label} icon" class="status-img">
                            </div>
                            <div class="stat-text">
                                <div class="stat-label">${stat.label}</div>
                                <div class="stat-value">${stat.value}</div>
                            </div>
                        </div>`;
                    overallTableDiv.appendChild(card);
                });

                // ==== PERCENTAGE TABLE ====
                const percentageTableBody = document.querySelector('#percentageTable tbody');
                percentageTableBody.innerHTML = '';

                const sortedVoucherTypes = Object.entries(stats.voucherType)
                    .sort((a, b) => b[1] - a[1]);

                sortedVoucherTypes.forEach(([voucherType, count]) => {
                    const percent = stats.totalEntries > 0 ? ((count / stats.totalEntries) * 100).toFixed(2) : '0.00';
                    const row = document.createElement('tr');
                    row.innerHTML = `<td>${voucherType}</td><td style="text-align:right;">${count}</td><td style="text-align:right;">${percent}%</td>`;
                    percentageTableBody.appendChild(row);
                });

                const totalRow = document.createElement('tr');
                totalRow.style.fontWeight = 'bold';
                totalRow.innerHTML = `<td>Total</td><td style="text-align:right;">${stats.totalEntries}</td><td style="text-align:right;">100%</td>`;
                percentageTableBody.appendChild(totalRow);
            }

            function updateSectionTiming(sectionTiming) {
                const timing = sectionTiming && typeof sectionTiming === 'object' ? sectionTiming : {
                    sections: [],
                    summary: [],
                    by_voucher: []
                };
                const summary = Array.isArray(timing.summary) ? timing.summary : [];
                const sections = Array.isArray(timing.sections) ? timing.sections : [];
                const byVoucher = Array.isArray(timing.by_voucher) ? timing.by_voucher : [];

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
                            backgroundColor: 'rgba(255, 159, 64, 0.6)'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        scales: {
                            y: {
                                beginAtZero: true,
                                title: {
                                    display: true,
                                    text: 'Hours'
                                }
                            }
                        },
                        plugins: {
                            tooltip: {
                                callbacks: {
                                    label(ctx) {
                                        const row = summary[ctx.dataIndex];
                                        return row ? `Avg: ${row.avg_label}` : '';
                                    }
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
                sections.forEach(section => {
                    headerHtml += `<th style="text-align:right;">${section}</th>`;
                });
                headerHtml += '<th style="text-align:right;">Total Processing Time</th>';
                headerRow.innerHTML = headerHtml;
                voucherHead.appendChild(headerRow);

                if (byVoucher.length === 0) {
                    const row = document.createElement('tr');
                    row.innerHTML = `<td colspan="${4 + sections.length}" style="text-align:center;color:#888;">No per-voucher section timing data for the current filters.</td>`;
                    voucherBody.appendChild(row);
                    return;
                }

                byVoucher.forEach(row => {
                    const tr = document.createElement('tr');
                    let html = `<td>${row.processing_no || '-'}</td>
                        <td>${row.payee || '-'}</td>
                        <td>${row.dv_no || '-'}</td>`;
                    const labels = row.sections_label || {};
                    sections.forEach(section => {
                        html += `<td style="text-align:right;">${labels[section] || '—'}</td>`;
                    });
                    const tpt = (row.total_processing_time || '').trim();
                    html += `<td style="text-align:right;">${tpt !== '' ? tpt : '—'}</td>`;
                    tr.innerHTML = html;
                    voucherBody.appendChild(tr);
                });
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
                    updateCharts(data);
                    updateSectionTiming(null);
                    setRefreshStatus('Updated ' + new Date().toLocaleTimeString() + ' · auto-refresh every 15s');
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
                        callback([]);
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