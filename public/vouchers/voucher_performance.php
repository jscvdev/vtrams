<?php
include('../includes/header.php');
if (!AccessControl::canAccessExtended()) {
    header('Location: ../documents/index.php');
    die();
}
require_once __DIR__ . '/../../protected/core/components/helpers/audit_helper.inc.php';
require_once __DIR__ . '/../../protected/core/components/notifications/notification.inc.php';
AuditHelper::logPageView('Performance');
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

    .analytics-filter-field select,
    .analytics-filter-field input[type="date"],
    .analytics-filter-field input[type="text"] {
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

    .analytics-filter-field select:focus,
    .analytics-filter-field input:focus {
        outline: none;
        background: #fff;
        border-color: #b8c9ff;
        box-shadow: 0 0 0 3px rgba(74, 118, 255, 0.12);
    }

    .analytics-filter-field--search {
        flex: 1 1 200px;
        margin-left: auto;
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
        box-shadow: 0 6px 16px rgba(74, 118, 255, 0.34);
    }

    .analytics-filter-apply--print {
        background: linear-gradient(135deg, #10b981, #059669);
        box-shadow: 0 4px 12px rgba(16, 185, 129, 0.28);
    }

    .analytics-filter-apply--print:hover {
        box-shadow: 0 6px 16px rgba(16, 185, 129, 0.34);
    }

    .analytics-stats-grid {
        display: grid;
        grid-template-columns: repeat(7, minmax(0, 1fr));
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

    .stat-amount {
        display: block;
        font-size: 10px;
        color: #64748b;
        margin-top: 3px;
        font-variant-numeric: tabular-nums;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        max-width: 100%;
    }

    .analytics-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
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
        padding: 14px 16px 16px;
    }

    .analytics-table-wrap {
        overflow-x: auto;
        width: 100%;
        border: 1px solid #eef2f7;
        border-radius: 10px;
    }

    .perf-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }

    .perf-table th,
    .perf-table td {
        padding: 9px 12px;
        text-align: left;
        border-bottom: 1px solid #f1f5f9;
    }

    .perf-table th {
        background: #fafbfc;
        color: #94a3b8;
        font-size: 10px;
        font-weight: 600;
        letter-spacing: 0.06em;
        text-transform: uppercase;
    }

    .perf-table tbody tr:hover {
        background: #f8fbff;
    }

    .perf-table td {
        color: #334155;
        font-size: 12px;
    }

    .no-data {
        text-align: center;
        padding: 2rem;
        color: #666;
    }

    .perf-table-pager {
        display: none;
        margin-top: 16px;
        align-items: center;
        justify-content: center;
        flex-wrap: wrap;
        gap: 10px 16px;
        padding: 12px 16px;
        background: linear-gradient(180deg, #fafbfc 0%, #f3f4f6 100%);
        border: 1px solid #e5e7eb;
        border-radius: 10px;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.04);
    }

    .perf-table-pager__btn {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        gap: 6px;
        padding: 8px 14px;
        font-size: 13px;
        font-weight: 600;
        color: #374151;
        background: #fff;
        border: 1px solid #d1d5db;
        border-radius: 8px;
        cursor: pointer;
        transition: background 0.15s ease, border-color 0.15s ease, color 0.15s ease, box-shadow 0.15s ease;
        box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05);
    }

    .perf-table-pager__btn i {
        font-size: 16px;
        opacity: 0.9;
    }

    .perf-table-pager__btn:hover:not(:disabled) {
        background: #f9fafb;
        border-color: #4A76FF;
        color: #4A76FF;
        box-shadow: 0 2px 6px rgba(74, 118, 255, 0.12);
    }

    .perf-table-pager__btn:focus-visible {
        outline: 2px solid #4A76FF;
        outline-offset: 2px;
    }

    .perf-table-pager__btn:disabled {
        opacity: 0.42;
        cursor: not-allowed;
        box-shadow: none;
    }

    .perf-table-pager__info {
        font-size: 13px;
        font-weight: 500;
        color: rgb(75 85 99 / 0.92);
        font-variant-numeric: tabular-nums;
        letter-spacing: 0.01em;
        padding: 6px 12px;
        background: rgba(255, 255, 255, 0.75);
        border-radius: 8px;
        border: 1px solid #e5e7eb;
        min-width: 0;
        text-align: center;
    }

    .perf-table-pager__info--empty {
        color: #9ca3af;
        font-weight: 600;
        text-transform: uppercase;
        font-size: 12px;
        letter-spacing: 0.06em;
    }

    .perf-total-row td {
        font-weight: 700;
        background: #f9fafb;
        border-top: 2px solid #e5e7eb;
    }

    @media (max-width: 1400px) {
        .analytics-stats-grid {
            grid-template-columns: repeat(auto-fit, minmax(118px, 1fr));
        }
    }

    @media (max-width: 1100px) {
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

        .analytics-filter-field--search {
            margin-left: 0;
            flex-basis: 100%;
        }
    }

    @media print {
        .sidebar,
        .header,
        .analytics-filter-bar,
        .no-print,
        .analytics-grid {
            display: none !important;
        }

        .main {
            margin: 0 !important;
        }

        body {
            background: white !important;
        }

        .print-only {
            display: block !important;
        }

        .perf-print-header {
            display: block !important;
        }

        .perf-table th,
        .perf-table td {
            border: none;
            border-bottom: 1px solid #e5e7eb;
            padding: 7px 10px;
            font-size: 11px;
        }

        .perf-table th {
            border-bottom: 2px solid #111827;
            background: transparent !important;
        }
    }

    .print-only,
    .perf-print-header {
        display: none;
    }

    .perf-print-banner {
        display: flex;
        justify-content: space-between;
        gap: 20px;
        padding: 18px 20px;
        border: 2px solid #111827;
        border-radius: 10px;
        background: #fff;
        margin-bottom: 14px;
    }

    .perf-print-banner__eyebrow {
        margin: 0 0 4px;
        font-size: 11px;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: #6b7280;
    }

    .perf-print-banner h2 {
        margin: 0;
        font-size: 22px;
        line-height: 1.2;
        color: #111827;
    }

    .perf-print-banner__subtitle {
        margin: 6px 0 0;
        font-size: 13px;
        color: #4b5563;
    }

    .perf-print-banner__meta {
        display: grid;
        gap: 8px;
        min-width: 210px;
    }

    .perf-print-banner__meta div {
        display: flex;
        justify-content: space-between;
        gap: 12px;
        font-size: 12px;
        border-bottom: 1px solid #e5e7eb;
        padding-bottom: 4px;
    }

    .perf-print-banner__meta span {
        color: #6b7280;
    }

    .perf-print-banner__meta strong {
        color: #111827;
        font-weight: 700;
    }

    .perf-print-summary {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(120px, 1fr));
        gap: 10px;
        margin-bottom: 16px;
    }

    .perf-print-summary__item {
        border: 1px solid #d1d5db;
        border-radius: 8px;
        padding: 10px 12px;
        background: #f9fafb;
    }

    .perf-print-summary__item span {
        display: block;
        font-size: 11px;
        color: #6b7280;
        margin-bottom: 4px;
    }

    .perf-print-summary__item strong {
        font-size: 18px;
        color: #111827;
    }

    .perf-print-summary__item small {
        display: block;
        font-size: 11px;
        color: #4b5563;
        margin-top: 4px;
    }
</style>

<div class="main main--analytics-dashboard" id="main">
    <div class="analytics-dashboard-shell">
        <div class="analytics-dashboard-toolbar">
            <header class="analytics-dashboard-header">
                <div>
                    <h1 class="analytics-dashboard-header__title">Performance</h1>
                    <p class="analytics-dashboard-header__subtitle">Voucher action counts: Forwarded, Processed, Returned, Received, Transmitted.</p>
                </div>
            </header>

            <section class="analytics-filter-bar no-print">
                <div class="analytics-filter-field">
                    <label for="dateFilter">Date</label>
                    <input type="date" id="dateFilter">
                </div>
                <div class="analytics-filter-field">
                    <label for="yearFilter">Year</label>
                    <select id="yearFilter">
                        <option value="all">All</option>
                        <?php for ($y = date('Y'); $y >= 2020; $y--) echo "<option value=\"$y\">$y</option>"; ?>
                    </select>
                </div>
                <div class="analytics-filter-field">
                    <label for="monthFilter">Month</label>
                    <select id="monthFilter">
                        <option value="all">All</option>
                        <?php for ($m = 1; $m <= 12; $m++) echo '<option value="' . str_pad((string)$m, 2, '0', STR_PAD_LEFT) . '">' . date('M', mktime(0, 0, 0, $m, 1)) . '</option>'; ?>
                    </select>
                </div>
                <div class="analytics-filter-field">
                    <label for="actionTypeFilter">Action</label>
                    <select id="actionTypeFilter">
                        <option value="all">All Actions</option>
                        <option value="Forwarded">Forwarded</option>
                        <option value="Processed">Processed</option>
                        <option value="Returned">Returned</option>
                        <option value="Received">Received</option>
                        <option value="Transmitted">Transmitted</option>
                        <option value="Other">Other</option>
                    </select>
                </div>
                <button type="button" class="analytics-filter-apply" id="applyFiltersBtn">Apply</button>
                <button type="button" class="analytics-filter-apply analytics-filter-apply--print" id="printDailyBtn">Print Report</button>
                <div class="analytics-filter-field analytics-filter-field--search">
                    <label for="perfTableSearch">Table search</label>
                    <input type="text" id="perfTableSearch" placeholder="Processing no., payee, action…" autocomplete="off">
                </div>
            </section>
        </div>

        <div class="analytics-dashboard-viewport" id="perfAnalyticsViewport">
            <div class="analytics-dashboard-scale" id="perfAnalyticsScale">
                <div class="analytics-stats-grid" id="statsCards"></div>

                <section class="analytics-grid no-print">
                    <div class="analytics-card analytics-card--chart">
                        <h3 class="analytics-card__title">Action Type Distribution</h3>
                        <div class="analytics-chart-wrap">
                            <canvas id="actionChart"></canvas>
                        </div>
                    </div>
                    <div class="analytics-card analytics-card--chart">
                        <h3 class="analytics-card__title">Daily Taken-Action Trend</h3>
                        <div class="analytics-chart-wrap">
                            <canvas id="dailyChart"></canvas>
                        </div>
                    </div>
                </section>

                <div class="perf-print-header print-only" id="perfPrintHeader">
                    <div class="perf-print-banner">
                        <div>
                            <p class="perf-print-banner__eyebrow">Voucher Tracking</p>
                            <h2>Performance Report</h2>
                            <p class="perf-print-banner__subtitle">Taken-Action Summary</p>
                        </div>
                        <div class="perf-print-banner__meta">
                            <div><span>Date</span><strong id="perfPrintDate">—</strong></div>
                            <div><span>Action</span><strong id="perfPrintAction">All</strong></div>
                            <div><span>Rows</span><strong id="perfPrintRows">0</strong></div>
                            <div><span>Generated</span><strong id="perfPrintGenerated">—</strong></div>
                        </div>
                    </div>
                    <div class="perf-print-summary" id="perfPrintSummary"></div>
                </div>

                <section class="analytics-card analytics-card--table">
                    <h3 class="analytics-card__title">Taken-Action</h3>
                    <div class="analytics-table-wrap">
                        <table class="perf-table" id="perfTable">
                            <thead>
                                <tr>
                                    <th>Processing No</th>
                                    <th>DV No</th>
                                    <th>Payee</th>
                                    <th style="text-align:right;">Amount</th>
                                    <th>Action Type</th>
                                    <th>Action By</th>
                                    <th>Date/Time</th>
                                </tr>
                            </thead>
                            <tbody></tbody>
                            <tfoot id="perfTableFoot" style="display:none;">
                                <tr class="perf-total-row">
                                    <td colspan="3">Total</td>
                                    <td style="text-align:right;" id="perfTableTotalAmount">—</td>
                                    <td colspan="3" id="perfTableTotalCount"></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <div class="no-data" id="noDataMsg" style="display:none;">No data for selected filters.</div>
                    <div class="no-print perf-table-pager" id="perfTablePager">
                        <button type="button" id="perfTablePrev" class="perf-table-pager__btn" aria-label="Previous page">
                            <i class="ri-arrow-left-s-line" aria-hidden="true"></i><span>Previous</span>
                        </button>
                        <span id="perfTablePagerInfo" class="perf-table-pager__info"></span>
                        <button type="button" id="perfTableNext" class="perf-table-pager__btn" aria-label="Next page">
                            <span>Next</span><i class="ri-arrow-right-s-line" aria-hidden="true"></i>
                        </button>
                    </div>
                </section>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script src="../../protected/js/main.js"></script>
<script src="../../protected/js/popscript.js"></script>
<script>
    (function() {
        const base = '../../protected/handler/fetch_handlers/fetch_performance_data.php';
        let actionChart, dailyChart;
        let tablePage = 1;
        let tableTotalPages = 1;
        let tableQ = '';
        let debounceTableSearch = null;
        const perfSearch = document.getElementById('perfTableSearch');

        const actionColors = {
            Forwarded: 'rgba(74, 118, 255, 0.85)',
            Processed: 'rgba(16, 185, 129, 0.85)',
            Returned: 'rgba(245, 158, 11, 0.85)',
            Received: 'rgba(139, 92, 246, 0.85)',
            Transmitted: 'rgba(236, 72, 153, 0.85)',
            Other: 'rgba(148, 163, 184, 0.85)'
        };

        const analyticsViewport = document.getElementById('perfAnalyticsViewport');
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
            var n = parseFloat(String(raw).replace(/,/g, ''));
            if (!isFinite(n)) return String(raw);
            return n.toLocaleString('en-PH', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        function resizeAllCharts() {
            if (actionChart) actionChart.resize();
            if (dailyChart) dailyChart.resize();
        }

        function scheduleChartResize() {
            if (layoutSyncTimer) {
                clearTimeout(layoutSyncTimer);
            }
            layoutSyncTimer = setTimeout(resizeAllCharts, 120);
        }

        window.addEventListener('resize', scheduleChartResize);

        function getParams() {
            const p = {};
            const d = document.getElementById('dateFilter').value;
            const y = document.getElementById('yearFilter').value;
            const m = document.getElementById('monthFilter').value;
            const a = document.getElementById('actionTypeFilter').value;
            if (d) p.date = d;
            if (y !== 'all') p.year = y;
            if (m !== 'all') p.month = m;
            if (a !== 'all') p.action_type = a;
            return new URLSearchParams(p).toString();
        }

        function formatFilterLabel() {
            const d = document.getElementById('dateFilter').value;
            const y = document.getElementById('yearFilter').value;
            const m = document.getElementById('monthFilter').value;
            const a = document.getElementById('actionTypeFilter').value;
            if (d) return d;
            const parts = [];
            if (m !== 'all') parts.push(m);
            if (y !== 'all') parts.push(y);
            return parts.length ? parts.join('/') : 'All dates';
        }

        function fetchData(cb) {
            const q = getParams();
            const params = new URLSearchParams(q);
            params.set('table_page', String(tablePage));
            params.set('table_per_page', '50');
            if (tableQ.trim() !== '') params.set('q', tableQ.trim());
            const url = base + '?' + params.toString();
            fetch(url)
                .then(r => r.json().then(payload => ({ ok: r.ok, status: r.status, payload })))
                .then(res => {
                    if (!res.ok) {
                        const msg = (res.payload && res.payload.error) ? res.payload.error : ('Request failed (' + res.status + ')');
                        if (typeof showNotify === 'function') showNotify(msg, 'warning', 2600);
                        throw new Error(msg);
                    }
                    const data = res.payload;
                    if (data.error) throw new Error(data.error);
                    cb(data);
                })
                .catch(err => {
                    console.error(err);
                    cb({
                        overall: {},
                        overall_amount: {},
                        total_amount: '0.00',
                        daily: {},
                        table: [],
                        table_meta: { page: 1, per_page: 50, total: 0, total_pages: 1 },
                        users: []
                    });
                });
        }

        function renderStats(overall, overallAmount, totalAmount) {
            const order = ['Forwarded', 'Processed', 'Returned', 'Received', 'Transmitted', 'Other'];
            const icons = {
                Forwarded: 'ri-send-plane-line',
                Processed: 'ri-checkbox-circle-line',
                Returned: 'ri-arrow-left-line',
                Received: 'ri-inbox-line',
                Transmitted: 'ri-broadcast-line',
                Other: 'ri-more-line'
            };
            const html = order.map(t => {
                const v = overall[t] || 0;
                const rawAmt = (overallAmount && overallAmount[t]) ? overallAmount[t] : '';
                const amt = rawAmt !== '' ? formatPesoAmount(rawAmt) : '';
                const c = actionColors[t] || actionColors.Other;
                const iconClass = icons[t] || icons.Other;
                const amtHtml = amt ? '<span class="stat-amount">₱' + amt + '</span>' : '';
                return '<div class="analytics-stat-card"><div class="analytics-stat-card__icon" style="background:' + c.replace('0.85', '0.12') + ';color:' + c.replace('0.85', '1') + '"><i class="' + iconClass + '"></i></div><div class="analytics-stat-card__body"><div class="analytics-stat-card__label">' + t + '</div><div class="analytics-stat-card__value">' + v + '</div>' + amtHtml + '</div></div>';
            }).join('');
            const totalFormatted = totalAmount ? formatPesoAmount(totalAmount) : '';
            const totalCard = '<div class="analytics-stat-card"><div class="analytics-stat-card__icon" style="background:rgba(15,23,42,0.06);color:#334155"><i class="ri-money-dollar-circle-line"></i></div><div class="analytics-stat-card__body"><div class="analytics-stat-card__label">Total Amount</div><div class="analytics-stat-card__value">' + (totalFormatted ? '₱' + totalFormatted : '—') + '</div></div></div>';
            document.getElementById('statsCards').innerHTML = html + totalCard;
        }

        function renderPrintSummary(overall, overallAmount, totalAmount) {
            const el = document.getElementById('perfPrintSummary');
            if (!el) return;
            const order = ['Forwarded', 'Processed', 'Returned', 'Received', 'Transmitted'];
            const items = order.filter(t => (overall[t] || 0) > 0).map(t => {
                const amt = (overallAmount && overallAmount[t]) ? `₱${overallAmount[t]}` : '';
                return `<div class="perf-print-summary__item"><span>${t}</span><strong>${overall[t] || 0}</strong>${amt ? `<small>${amt}</small>` : ''}</div>`;
            }).join('');
            const totalItem = `<div class="perf-print-summary__item"><span>Total Amount</span><strong>${totalAmount ? '₱' + totalAmount : '—'}</strong></div>`;
            el.innerHTML = items + totalItem;
        }

        function renderActionChart(overall) {
            const labels = Object.keys(overall).filter(k => overall[k] > 0);
            const data = labels.map(k => overall[k]);
            const colors = labels.map(k => actionColors[k] || actionColors.Other);
            const ctx = document.getElementById('actionChart').getContext('2d');
            if (actionChart) actionChart.destroy();
            actionChart = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels,
                    datasets: [{
                        data,
                        backgroundColor: colors,
                        borderWidth: 0,
                        spacing: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: { duration: 350 },
                    cutout: '62%',
                    plugins: {
                        legend: chartLegend
                    }
                }
            });
        }

        function renderDailyChart(daily) {
            const dates = Object.keys(daily).sort();
            const types = ['Forwarded', 'Processed', 'Returned', 'Received', 'Transmitted'];
            const datasets = types.map((t) => ({
                label: t,
                data: dates.map(d => daily[d][t] || 0),
                borderColor: (actionColors[t] || actionColors.Other).replace('0.85', '1'),
                backgroundColor: (actionColors[t] || actionColors.Other).replace('0.85', '0.12'),
                borderWidth: 2,
                pointRadius: 0,
                pointHitRadius: 8,
                fill: true,
                tension: 0.35
            }));
            const ctx = document.getElementById('dailyChart').getContext('2d');
            if (dailyChart) dailyChart.destroy();
            dailyChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: dates,
                    datasets
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: { duration: 350 },
                    interaction: { mode: 'index', intersect: false },
                    plugins: {
                        legend: chartLegend
                    },
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
        }

        function renderTable(rows, meta, totalAmount) {
            const tbody = document.querySelector('#perfTable tbody');
            const tfoot = document.getElementById('perfTableFoot');
            const totalAmtEl = document.getElementById('perfTableTotalAmount');
            const totalCountEl = document.getElementById('perfTableTotalCount');
            const noData = document.getElementById('noDataMsg');
            const pager = document.getElementById('perfTablePager');
            const prev = document.getElementById('perfTablePrev');
            const next = document.getElementById('perfTableNext');
            const pinfo = document.getElementById('perfTablePagerInfo');
            tbody.innerHTML = '';
            const p = (meta && Number(meta.page)) ? Number(meta.page) : 1;
            const per = (meta && Number(meta.per_page)) ? Number(meta.per_page) : 50;
            const tot = (meta && Number(meta.total)) ? Number(meta.total) : 0;
            const tp = Math.max(1, (meta && Number(meta.total_pages)) ? Number(meta.total_pages) : 1);
            tableTotalPages = tp;
            if (rows.length === 0) {
                noData.style.display = 'block';
                if (tfoot) tfoot.style.display = 'none';
                if (pager && prev && next && pinfo) {
                    pager.style.display = 'flex';
                    prev.disabled = true;
                    next.disabled = true;
                    pinfo.textContent = 'No data to display';
                    pinfo.classList.add('perf-table-pager__info--empty');
                }
                if (tableQ.trim() !== '' && typeof showNotify === 'function') {
                    showNotify('No matching rows in Taken-Action table for your search.', 'warning', 2200);
                }
                return;
            }
            noData.style.display = 'none';
            rows.forEach(r => {
                const tr = document.createElement('tr');
                const amt = r.amount ? formatPesoAmount(r.amount) : '';
                tr.innerHTML = `<td>${r.processing_no || '-'}</td><td>${r.dv_no || '-'}</td><td>${r.payee || '-'}</td><td style="text-align:right;">${amt ? '₱' + amt : '—'}</td><td>${r.action_type || '-'}</td><td>${r.action_by || '-'}</td><td>${r.datetime_action || '-'}</td>`;
                tbody.appendChild(tr);
            });
            if (tfoot && totalAmtEl && totalCountEl) {
                tfoot.style.display = '';
                const totalFmt = totalAmount ? formatPesoAmount(totalAmount) : '';
                totalAmtEl.textContent = totalFmt ? '₱' + totalFmt : '—';
                totalCountEl.textContent = tot + ' voucher' + (tot === 1 ? '' : 's');
            }
            if (pager && prev && next && pinfo) {
                const end = Math.min(p * per, tot);
                pinfo.classList.remove('perf-table-pager__info--empty');
                pinfo.textContent = 'Showing ' + end + ' of ' + tot + ' results';
                pager.style.display = 'flex';
                prev.disabled = p <= 1;
                next.disabled = p >= tp;
            }
        }

        let lastOverall = {};
        let lastOverallAmount = {};
        let lastTotalAmount = '';

        function refresh() {
            tablePage = 1;
            if (perfSearch) tableQ = String(perfSearch.value || '');
            fetchData(data => {
                lastOverall = data.overall || {};
                lastOverallAmount = data.overall_amount || {};
                lastTotalAmount = data.total_amount || '';
                renderStats(lastOverall, lastOverallAmount, lastTotalAmount);
                renderPrintSummary(lastOverall, lastOverallAmount, lastTotalAmount);
                renderActionChart(lastOverall);
                renderDailyChart(data.daily || {});
                renderTable(data.table || [], data.table_meta || {}, lastTotalAmount);
                scheduleChartResize();
            });
        }

        function refreshTableOnly() {
            fetchData(data => {
                lastTotalAmount = data.total_amount || '';
                renderTable(data.table || [], data.table_meta || {}, lastTotalAmount);
            });
        }

        document.getElementById('applyFiltersBtn').addEventListener('click', refresh);
        document.getElementById('printDailyBtn').addEventListener('click', function() {
            const d = document.getElementById('dateFilter').value;
            const actionSel = document.getElementById('actionTypeFilter');
            const actionLabel = actionSel && actionSel.value !== 'all' ? actionSel.value : 'All';
            const generated = new Date().toLocaleString();
            const p = new URLSearchParams(getParams());
            p.set('full_table', '1');
            fetch(base + '?' + p.toString())
                .then(r => r.json())
                .then(data => {
                    const rows = data.table || [];
                    const overall = data.overall || {};
                    const overallAmount = data.overall_amount || {};
                    const totalAmount = data.total_amount || '';
                    if (rows.length === 0) {
                        if (typeof showNotify === 'function') showNotify('No data to print for selected filters.', 'warning', 3000);
                        return;
                    }
                    const printWindow = window.open('', '_blank');
                    const dateLabel = d || formatFilterLabel();
                    const summaryItems = ['Forwarded', 'Processed', 'Returned', 'Received', 'Transmitted'].filter(t => (overall[t] || 0) > 0).map(t => {
                        const amt = overallAmount[t] ? ` · ₱${overallAmount[t]}` : '';
                        return `<div class="summary-item"><span>${t}</span><strong>${overall[t] || 0}${amt}</strong></div>`;
                    }).join('');
                    printWindow.document.write(`
<!DOCTYPE html><html><head><title>Performance Report</title>
<style>
@page { size: A4 landscape; margin: 12mm; }
body{font-family:"Segoe UI",Arial,sans-serif;padding:20px;color:#111827;}
.banner{display:flex;justify-content:space-between;gap:20px;padding:18px 20px;border:2px solid #111827;border-radius:10px;margin-bottom:14px;}
.banner__eyebrow{margin:0 0 4px;font-size:11px;letter-spacing:.12em;text-transform:uppercase;color:#6b7280;}
.banner h1{margin:0;font-size:22px;}
.banner__subtitle{margin:6px 0 0;font-size:13px;color:#4b5563;}
.banner__meta{display:grid;gap:8px;min-width:210px;font-size:12px;}
.banner__meta div{display:flex;justify-content:space-between;border-bottom:1px solid #e5e7eb;padding-bottom:4px;}
.summary{display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:10px;margin-bottom:16px;}
.summary-item{border:1px solid #d1d5db;border-radius:8px;padding:10px 12px;background:#f9fafb;}
.summary-item span{display:block;font-size:11px;color:#6b7280;margin-bottom:4px;}
.summary-item strong{font-size:18px;}
table{width:100%;border-collapse:collapse;font-size:11px;}
th{border-bottom:2px solid #111827;padding:8px 10px;text-align:left;}
td{border-bottom:1px solid #e5e7eb;padding:7px 10px;}
tfoot td{font-weight:700;background:#f9fafb;border-top:2px solid #e5e7eb;}
.footer{margin-top:16px;font-size:11px;color:#6b7280;text-align:right;}
</style></head><body>
<div class="banner">
  <div>
    <p class="banner__eyebrow">Voucher Tracking</p>
    <h1>Performance Report</h1>
    <p class="banner__subtitle">Taken-Action Summary</p>
  </div>
  <div class="banner__meta">
    <div><span>Date</span><strong>${dateLabel}</strong></div>
    <div><span>Action</span><strong>${actionLabel}</strong></div>
    <div><span>Rows</span><strong>${rows.length}</strong></div>
    <div><span>Generated</span><strong>${generated}</strong></div>
  </div>
</div>
<div class="summary">${summaryItems}<div class="summary-item"><span>Total Amount</span><strong>${totalAmount ? '₱' + totalAmount : '—'}</strong></div></div>
<h3 style="margin:0 0 10px;font-size:15px;">Voucher Listing</h3>
<table><thead><tr><th>Processing No</th><th>DV No</th><th>Payee</th><th style="text-align:right;">Amount</th><th>Action Type</th><th>Action By</th><th>Date/Time</th></tr></thead><tbody>
${rows.map(r=>`<tr><td>${r.processing_no||'-'}</td><td>${r.dv_no||'-'}</td><td>${r.payee||'-'}</td><td style="text-align:right;">${r.amount?'₱'+r.amount:'—'}</td><td>${r.action_type||'-'}</td><td>${r.action_by||'-'}</td><td>${r.datetime_action||'-'}</td></tr>`).join('')}
</tbody><tfoot><tr><td colspan="3">Total</td><td style="text-align:right;">${totalAmount?'₱'+totalAmount:'—'}</td><td colspan="3">${rows.length} voucher${rows.length===1?'':'s'}</td></tr></tfoot></table>
<p class="footer">End of report · ${rows.length} record${rows.length===1?'':'s'} · ${generated}</p>
</body></html>`);
                    printWindow.document.close();
                    printWindow.print();
                    printWindow.close();
                });
        });

        if (perfSearch) {
            perfSearch.addEventListener('input', function() {
                clearTimeout(debounceTableSearch);
                debounceTableSearch = setTimeout(function() {
                    tableQ = String(perfSearch.value || '');
                    tablePage = 1;
                    refreshTableOnly();
                }, 300);
            });
        }
        document.getElementById('perfTablePrev').addEventListener('click', function() {
            if (tablePage > 1) {
                tablePage--;
                refreshTableOnly();
            }
        });
        document.getElementById('perfTableNext').addEventListener('click', function() {
            if (tablePage < tableTotalPages) {
                tablePage++;
                refreshTableOnly();
            }
        });

        refresh();
    })();
</script>
</body>

</html>